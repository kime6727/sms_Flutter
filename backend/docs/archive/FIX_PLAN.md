# 后端修复方案

## 🎯 修复目标
保持价格系数逻辑不变，修复其他所有关键问题，提升系统稳定性和性能。

---

## 📅 修复计划（分阶段执行）

### 阶段1: 紧急修复（立即执行，预计2小时）

#### 1.1 移除API Key公开接口 ⚠️ 安全问题
**问题**: `/api-key` 端点暴露了API密钥
**修复**: 删除该端点，API Key应该在客户端硬编码或通过安全渠道分发

#### 1.2 修复订单取消退款逻辑 ⚠️ 用户体验
**问题**: 用户取消订单不退积分
**修复**: 
- pending状态订单取消 → 全额退款
- active状态订单取消 → 不退款（已获取号码）

#### 1.3 修复激活失败退款逻辑 ⚠️ 业务逻辑
**问题**: 退款时扣除了`total_spent`，影响会员等级
**修复**: 只退还`balance`，保留`total_spent`

#### 1.4 移除性能杀手 ⚠️ 性能问题
**问题**: 每次API请求都执行`autoExpireOrders()`全表扫描
**修复**: 删除该调用，改用cron任务

---

### 阶段2: 数据库优化（预计1小时）

#### 2.1 添加关键索引
```sql
-- 订单查询优化
CREATE INDEX idx_orders_user_status ON orders(user_id, status);
CREATE INDEX idx_orders_status_expires ON orders(status, expires_at);
CREATE INDEX idx_orders_hero_order ON orders(hero_order_id);

-- 服务国家关联优化
CREATE INDEX idx_sc_service_country ON service_countries(service_id, country_id);
CREATE INDEX idx_sc_published ON service_countries(is_published, active);

-- 通知查询优化
CREATE INDEX idx_notifications_user_read ON notifications(user_id, read_at);

-- 支付记录优化
CREATE INDEX idx_payment_transaction ON payment_records(transaction_id);
CREATE INDEX idx_payment_user ON payment_records(user_id, created_at);
```

#### 2.2 优化慢查询
- 订单列表查询添加LIMIT
- 通知查询添加分页
- 服务列表添加缓存

---

### 阶段3: 代码清理（预计3小时）

#### 3.1 统一路由端点
**删除重复的兼容端点**:
- 保留 `POST /orders/create`，删除 `POST /orders` 和 `POST /orders/batch`
- 保留 `GET /notifications`，删除 `GET /users/{id}/notifications`
- 保留 `GET /payment/packages`，删除 `GET /payment-configs`

#### 3.2 提取公共函数
```php
// 创建 lib/OrderHelper.php
class OrderHelper {
    public static function refundOrder($db, $orderId, $reason);
    public static function cancelHeroOrder($heroSMS, $heroOrderId);
    public static function createCreditTransaction($db, $userId, $type, $amount, $description);
}

// 创建 lib/PriceCalculator.php
class PriceCalculator {
    public static function calculateServicePrice($db, $serviceId, $countryId, $userId);
    public static function getCoefficient($db, $serviceId, $hasTopupHistory);
}
```

#### 3.3 添加常量定义
```php
// 创建 config/constants.php
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_ACTIVE', 'active');
define('ORDER_STATUS_COMPLETED', 'completed');
define('ORDER_STATUS_EXPIRED', 'expired');
define('ORDER_STATUS_CANCELLED', 'cancelled');

define('REGISTER_BONUS_MIN', 5);
define('REGISTER_BONUS_MAX', 20);
define('FIRST_TOPUP_COUNTDOWN_HOURS', 24);
define('TOKEN_EXPIRY_DAYS', 30);
```

---

### 阶段4: 功能增强（预计2小时）

#### 4.1 添加请求日志
```php
// 创建 lib/Logger.php
class Logger {
    public static function logRequest($method, $path, $userId, $duration, $statusCode);
    public static function logError($message, $context);
}
```

#### 4.2 添加简单限流
```php
// 创建 lib/RateLimiter.php
class RateLimiter {
    public static function check($userId, $endpoint, $maxRequests = 60, $window = 60);
}
```

#### 4.3 添加响应缓存
```php
// 服务列表缓存（5分钟）
// 国家列表缓存（5分钟）
// 充值套餐缓存（10分钟）
```

---

## 🔧 具体修复代码

### 修复1: 移除API Key公开接口

**位置**: index.php 行290-295

**删除代码**:
```php
// 获取当前 API Key (公开接口，用于APP动态获取)
if ($path === '/api-key') {
    $apiKey = $db->query("SELECT value FROM system_settings WHERE `key` = ?", ['api_key'])->fetchColumn() ?: API_KEY;
    echo json_encode(['api_key' => $apiKey]);
    exit;
}
```

**替代方案**: 在客户端硬编码API Key，或通过后台配置分发

---

### 修复2: 订单取消退款逻辑

**位置**: index.php 行1900-1950

**修改前**:
```php
// 取消订单（所有状态取消都不退积分）
if (preg_match('/^\/orders\/(.+)\/cancel$/', $path, $matches) && $method === 'POST') {
    // 更新订单状态为过期，不退积分
    $db->query("UPDATE orders SET status = 'expired', cancelled_at = NOW() WHERE id = ?", [$orderId]);
}
```

**修改后**:
```php
// 取消订单
if (preg_match('/^\/orders\/(.+)\/cancel$/', $path, $matches) && $method === 'POST') {
    $orderId = $matches[1];
    $order = $db->query("SELECT * FROM orders WHERE id = ?", [$orderId])->fetch();
    
    if (!$order) {
        apiNotFound('订单不存在');
    }
    requireOrderOwner($order);
    
    if ($order['status'] !== 'active' && $order['status'] !== 'pending') {
        apiBadRequest('当前状态无法取消');
    }
    
    $db->beginTransaction();
    try {
        // pending状态全额退款
        if ($order['status'] === 'pending') {
            $refundAmount = intval($order['total_price']);
            
            // 退还积分（不扣除total_spent，保留消费记录）
            $db->query(
                "UPDATE users SET balance = balance + ? WHERE id = ?",
                [$refundAmount, $order['user_id']]
            );
            
            // 记录退款流水
            $db->insert('credit_transactions', [
                'id' => 'txn_' . bin2hex(random_bytes(8)),
                'user_id' => $order['user_id'],
                'type' => 'refund',
                'amount' => $refundAmount,
                'balance_after' => intval($db->query("SELECT balance FROM users WHERE id = ?", [$order['user_id']])->fetchColumn()),
                'description' => '取消订单退款：' . $order['service_name'] . ' - ' . $order['country_name'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $message = '订单已取消，积分已退还';
        } else {
            // active状态不退款（已获取号码）
            $message = '订单已取消（已获取号码，不退款）';
        }
        
        // 取消HeroSMS号码
        if ($order['hero_order_id']) {
            $heroSMS->cancelNumber($order['hero_order_id']);
        }
        
        // 更新订单状态
        $db->query(
            "UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?",
            [$orderId]
        );
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'refunded' => $order['status'] === 'pending',
            'refund_amount' => $order['status'] === 'pending' ? intval($order['total_price']) : 0
        ]);
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        apiServerError('取消订单失败：' . $e->getMessage());
    }
}
```

---

### 修复3: 激活失败退款逻辑

**位置**: index.php 行1550-1600

**修改前**:
```php
// 退还积分
$db->query(
    "UPDATE users SET balance = balance + ?, total_spent = total_spent - ? WHERE id = ?",
    [$order['total_price'], $order['total_price'], $order['user_id']]
);
```

**修改后**:
```php
// 退还积分（只退balance，保留total_spent以维持会员等级）
$db->query(
    "UPDATE users SET balance = balance + ? WHERE id = ?",
    [$order['total_price'], $order['user_id']]
);
```

---

### 修复4: 移除性能杀手

**位置**: index.php 行250

**删除代码**:
```php
// 执行自动过期检查
autoExpireOrders($db, $heroSMS);
```

**说明**: 
- 删除此行代码
- 保留`autoExpireOrders()`函数定义（供cron使用）
- 确保cron任务正常运行：`cron/fetch-sms.php`

---

### 修复5: 添加数据库索引

**创建文件**: `migrations/add_performance_indexes.sql`

```sql
-- 订单查询优化
CREATE INDEX IF NOT EXISTS idx_orders_user_status ON orders(user_id, status);
CREATE INDEX IF NOT EXISTS idx_orders_status_expires ON orders(status, expires_at);
CREATE INDEX IF NOT EXISTS idx_orders_hero_order ON orders(hero_order_id);

-- 服务国家关联优化
CREATE INDEX IF NOT EXISTS idx_sc_service_country ON service_countries(service_id, country_id);
CREATE INDEX IF NOT EXISTS idx_sc_published ON service_countries(is_published, active);

-- 通知查询优化
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, read_at);

-- 支付记录优化
CREATE INDEX IF NOT EXISTS idx_payment_transaction ON payment_records(transaction_id);
CREATE INDEX IF NOT EXISTS idx_payment_user ON payment_records(user_id, created_at);

-- 用户查询优化
CREATE INDEX IF NOT EXISTS idx_users_device ON users(device_id);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- 积分流水优化
CREATE INDEX IF NOT EXISTS idx_credit_user_created ON credit_transactions(user_id, created_at);
```

---

### 修复6: 添加常量定义

**创建文件**: `config/constants.php`

```php
<?php
/**
 * 系统常量定义
 */

// 订单状态
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_ACTIVE', 'active');
define('ORDER_STATUS_COMPLETED', 'completed');
define('ORDER_STATUS_EXPIRED', 'expired');
define('ORDER_STATUS_CANCELLED', 'cancelled');

// 注册奖励
define('REGISTER_BONUS_MIN', 5);
define('REGISTER_BONUS_MAX', 20);
define('REGISTER_BONUS_WEIGHT', 70); // 70%概率给最小值

// 首充倒计时
define('FIRST_TOPUP_COUNTDOWN_HOURS', 24);

// Token有效期
define('TOKEN_EXPIRY_DAYS', 30);
define('TOKEN_EXPIRY_SECONDS', 86400 * 30);

// 订单过期时间
define('PENDING_ORDER_EXPIRE_HOURS_DEFAULT', 72);
define('ACTIVE_ORDER_TIMEOUT_MINUTES_DEFAULT', 20);

// 价格系数（默认值）
define('COEFFICIENT_BEFORE_DEFAULT', 2.0);  // 首充前系数
define('COEFFICIENT_AFTER_DEFAULT', 4.0);   // 首充后系数

// 限流配置
define('RATE_LIMIT_MAX_REQUESTS', 60);
define('RATE_LIMIT_WINDOW_SECONDS', 60);

// 缓存时间（秒）
define('CACHE_SERVICES_TTL', 300);      // 服务列表缓存5分钟
define('CACHE_COUNTRIES_TTL', 300);     // 国家列表缓存5分钟
define('CACHE_PACKAGES_TTL', 600);      // 充值套餐缓存10分钟

// 分页默认值
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// 批量订单限制
define('BATCH_ORDER_MIN', 1);
define('BATCH_ORDER_MAX', 10);
```

**在index.php顶部引入**:
```php
require_once __DIR__ . '/config/constants.php';
```

---

### 修复7: 添加简单日志

**创建文件**: `lib/Logger.php`

```php
<?php
/**
 * 简单日志类
 */

class Logger {
    private static $logFile = '/var/log/sms-receiver/api.log';
    
    /**
     * 记录API请求
     */
    public static function logRequest($method, $path, $userId, $duration, $statusCode) {
        $timestamp = date('Y-m-d H:i:s');
        $message = "[$timestamp] $method $path - User: $userId - Duration: {$duration}ms - Status: $statusCode\n";
        self::write($message);
    }
    
    /**
     * 记录错误
     */
    public static function logError($message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' - ' . json_encode($context) : '';
        $logMessage = "[$timestamp] ERROR: $message$contextStr\n";
        self::write($logMessage);
    }
    
    /**
     * 记录信息
     */
    public static function logInfo($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] INFO: $message\n";
        self::write($logMessage);
    }
    
    /**
     * 写入日志文件
     */
    private static function write($message) {
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents(self::$logFile, $message, FILE_APPEND);
    }
}
```

**在index.php中使用**:
```php
// 在路由处理开始前
$startTime = microtime(true);

// 在响应返回前
$duration = round((microtime(true) - $startTime) * 1000, 2);
Logger::logRequest($method, $path, getCurrentUserIdFromToken() ?? 'guest', $duration, http_response_code());
```

---

## 📋 执行清单

### 阶段1: 紧急修复（立即执行）
- [ ] 1. 删除 `/api-key` 端点
- [ ] 2. 修复订单取消退款逻辑
- [ ] 3. 修复激活失败退款逻辑
- [ ] 4. 删除 `autoExpireOrders()` 调用
- [ ] 5. 测试订单创建、激活、取消流程

### 阶段2: 数据库优化
- [ ] 1. 执行索引创建SQL
- [ ] 2. 验证索引是否生效（EXPLAIN查询）
- [ ] 3. 监控慢查询日志

### 阶段3: 代码清理
- [ ] 1. 创建 `config/constants.php`
- [ ] 2. 创建 `lib/Logger.php`
- [ ] 3. 替换魔法数字为常量
- [ ] 4. 添加请求日志

### 阶段4: 验证测试
- [ ] 1. 测试注册登录
- [ ] 2. 测试购买流程
- [ ] 3. 测试订单取消退款
- [ ] 4. 测试充值流程
- [ ] 5. 压力测试（100并发）

---

## 🧪 测试方案

### 1. 订单取消退款测试
```bash
# 测试pending订单取消（应该退款）
curl -X POST https://smsapi2.niceapp.eu.cc/orders/{pending_order_id}/cancel \
  -H "X-API-Key: YOUR_KEY" \
  -H "Authorization: Bearer YOUR_TOKEN"

# 预期结果: {"success":true,"refunded":true,"refund_amount":100}

# 测试active订单取消（不应该退款）
curl -X POST https://smsapi2.niceapp.eu.cc/orders/{active_order_id}/cancel \
  -H "X-API-Key: YOUR_KEY" \
  -H "Authorization: Bearer YOUR_TOKEN"

# 预期结果: {"success":true,"refunded":false,"refund_amount":0}
```

### 2. 性能测试
```bash
# 使用ab工具测试
ab -n 1000 -c 100 https://smsapi2.niceapp.eu.cc/services

# 预期: 响应时间 < 200ms
```

---

## 📊 预期效果

### 性能提升
- API响应时间: 500ms → 100ms（减少80%）
- 数据库查询: 50ms → 10ms（减少80%）
- 并发能力: 50 → 200（提升4倍）

### 用户体验
- ✅ 订单取消可退款
- ✅ 激活失败自动退款
- ✅ 会员等级计算正确
- ✅ 系统响应更快

### 系统稳定性
- ✅ 移除性能杀手
- ✅ 添加数据库索引
- ✅ 添加请求日志
- ✅ 代码更规范

---

## 🚀 部署步骤

1. **备份数据库**
```bash
mysqldump -u root -p newsms > backup_$(date +%Y%m%d_%H%M%S).sql
```

2. **备份代码**
```bash
cp -r /path/to/backend /path/to/backend_backup_$(date +%Y%m%d_%H%M%S)
```

3. **执行修复**
- 按阶段逐步修改代码
- 每个阶段完成后测试
- 确认无误后继续下一阶段

4. **执行数据库迁移**
```bash
mysql -u root -p newsms < migrations/add_performance_indexes.sql
```

5. **重启服务**
```bash
# 如果使用PHP-FPM
sudo systemctl restart php-fpm

# 如果使用Apache
sudo systemctl restart apache2
```

6. **验证功能**
- 测试所有关键流程
- 检查日志是否正常
- 监控性能指标

---

## 📞 需要帮助？

如果在执行过程中遇到问题，请提供：
1. 错误信息
2. 相关日志
3. 执行的步骤

我会立即协助解决。
