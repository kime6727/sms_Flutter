# ⚡ 立即执行 - 后端修复部署

## 🎯 当前状态

✅ **代码已更新** - 所有修复已应用到本地代码  
⏳ **待部署到服务器** - 需要执行以下步骤

---

## 📋 快速执行清单（5分钟）

### 步骤1: 备份（必须！）⏱️ 1分钟

```bash
# SSH连接到服务器
ssh user@sms.niceapp.eu.cc

# 备份数据库
mysqldump -u root -p newsms > /backup/newsms_$(date +%Y%m%d_%H%M%S).sql

# 备份代码（如果需要）
# cp -r /path/to/backend /backup/backend_$(date +%Y%m%d_%H%M%S)
```

---

### 步骤2: 执行数据库迁移 ⏱️ 2分钟

```bash
# 进入backend目录
cd /path/to/backend

# 执行索引创建
mysql -u root -p newsms < migrations/add_performance_indexes.sql

# 验证索引创建
mysql -u root -p newsms -e "
SELECT COUNT(*) as index_count 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'newsms' 
AND INDEX_NAME LIKE 'idx_%';
"
```

**预期输出**: `index_count` 应该 >= 15

---

### 步骤3: 创建日志目录 ⏱️ 30秒

```bash
# 创建日志目录
sudo mkdir -p /var/log/sms-receiver

# 设置权限（替换www-data为你的PHP用户）
sudo chown -R www-data:www-data /var/log/sms-receiver
sudo chmod -R 755 /var/log/sms-receiver

# 验证
ls -la /var/log/sms-receiver/
```

---

### 步骤4: 重启PHP服务 ⏱️ 30秒

```bash
# 如果使用PHP-FPM
sudo systemctl restart php-fpm
# 或
sudo systemctl restart php8.1-fpm

# 如果使用Apache
sudo systemctl restart apache2

# 验证服务状态
sudo systemctl status php-fpm
```

---

### 步骤5: 快速验证 ⏱️ 1分钟

```bash
# 测试健康检查
curl https://sms.niceapp.eu.cc/health

# 测试API Key端点（应该返回404或401）
curl https://sms.niceapp.eu.cc/api-key

# 检查日志文件
tail -f /var/log/sms-receiver/api.log
```

---

## ✅ 验证清单

部署完成后，请确认：

- [ ] 数据库索引已创建（至少15个）
- [ ] 日志目录存在且有写权限
- [ ] PHP服务已重启且运行正常
- [ ] `/health`端点返回200
- [ ] `/api-key`端点返回404或401
- [ ] 日志文件开始记录请求

---

## 🧪 功能测试（可选）

### 测试订单取消退款

```bash
# 1. 获取API Key和Token（从后台或数据库）
API_KEY="your_api_key"
TOKEN="your_token"

# 2. 创建测试订单
curl -X POST https://sms.niceapp.eu.cc/orders/create \
  -H "X-API-Key: $API_KEY" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "your_user_id",
    "service_id": 1,
    "country_id": 1,
    "quantity": 1
  }'

# 记录返回的order_id

# 3. 取消订单（应该退款）
curl -X POST https://sms.niceapp.eu.cc/orders/{order_id}/cancel \
  -H "X-API-Key: $API_KEY" \
  -H "Authorization: Bearer $TOKEN"

# 预期响应:
# {
#   "success": true,
#   "message": "订单已取消，积分已退还",
#   "refunded": true,
#   "refund_amount": 100
# }
```

---

## 📊 性能测试（可选）

```bash
# 安装ab工具（如果没有）
sudo apt-get install apache2-utils

# 测试API性能
ab -n 1000 -c 100 https://sms.niceapp.eu.cc/services

# 预期结果:
# - Requests per second: > 200
# - Time per request: < 500ms
# - Failed requests: 0
```

---

## ⚠️ 如果遇到问题

### 问题1: 索引创建失败

```bash
# 检查数据库连接
mysql -u root -p newsms -e "SELECT 1;"

# 手动创建索引（逐条执行）
mysql -u root -p newsms -e "CREATE INDEX idx_orders_user_status ON orders(user_id, status);"
```

### 问题2: 日志文件不生成

```bash
# 检查目录权限
ls -la /var/log/sms-receiver/

# 检查PHP用户
ps aux | grep php-fpm

# 重新设置权限
sudo chown -R www-data:www-data /var/log/sms-receiver
sudo chmod -R 755 /var/log/sms-receiver
```

### 问题3: 500错误

```bash
# 检查PHP错误日志
tail -f /var/log/php-fpm/error.log
# 或
tail -f /var/log/apache2/error.log

# 检查文件权限
ls -la /path/to/backend/config/constants.php
ls -la /path/to/backend/lib/Logger.php

# 检查PHP语法
php -l /path/to/backend/index.php
```

---

## 🔄 回滚方案（如果需要）

```bash
# 1. 恢复数据库
mysql -u root -p newsms < /backup/newsms_YYYYMMDD_HHMMSS.sql

# 2. 恢复代码（如果备份了）
# rm -rf /path/to/backend
# cp -r /backup/backend_YYYYMMDD_HHMMSS /path/to/backend

# 3. 重启服务
sudo systemctl restart php-fpm
```

---

## 📞 需要帮助？

### 查看日志
```bash
# API日志
tail -100 /var/log/sms-receiver/api.log

# 错误日志
tail -100 /var/log/sms-receiver/error.log

# PHP错误
tail -100 /var/log/php-fpm/error.log
```

### 运行测试脚本
```bash
cd /path/to/backend
./test_fixes.sh
```

### 查看文档
- [DEPLOYMENT_GUIDE.md](./DEPLOYMENT_GUIDE.md) - 详细部署指南
- [FIXES_SUMMARY.md](./FIXES_SUMMARY.md) - 修复总结
- [README_FIXES.md](./README_FIXES.md) - 修复说明

---

## 🎉 部署完成

部署完成后：

1. ✅ 访问运营后台验证功能
   - https://sms.niceapp.eu.cc/admin/login.php

2. ✅ 测试订单创建和取消

3. ✅ 检查日志文件

4. ✅ 监控性能指标

---

**预计总耗时**: 5-10分钟  
**难度**: ⭐⭐☆☆☆ (简单)  
**风险**: ⭐☆☆☆☆ (低，已备份)

🚀 **开始部署吧！**
