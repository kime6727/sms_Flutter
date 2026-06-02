# SMS接码平台 API 文档中心

欢迎使用SMS接码平台API！本文档中心提供完整的API集成指南。

---

## 📚 文档导航

### 🚀 快速开始

**适合**: 第一次使用API的开发者

1. **[快速开始指南](QUICK_START.md)** ⭐ 推荐首先阅读
   - 5分钟快速集成
   - 完整流程演示
   - 代码示例（JavaScript/Python/Dart）
   - 常见问题解答

### 📖 核心文档

**适合**: 需要详细了解API的开发者

2. **[完整API文档](README.md)**
   - 所有API端点详细说明
   - 请求/响应格式
   - 认证机制
   - 错误码对照表
   - 完整代码示例

3. **[错误处理指南](ERROR_HANDLING.md)**
   - 错误响应格式
   - 所有错误码详解
   - 错误处理最佳实践
   - 统一错误处理器示例
   - 用户友好的错误提示

4. **[最佳实践指南](BEST_PRACTICES.md)**
   - 认证与安全
   - 请求优化
   - 状态管理
   - 用户体验优化
   - 性能优化技巧
   - 测试建议

### 🔧 工具和资源

5. **[Postman集合](SMS_API.postman_collection.json)**
   - 可直接导入Postman
   - 包含所有API端点
   - 预配置的请求示例
   - 环境变量模板

### 📝 更新日志

6. **[更新日志](CHANGELOG.md)**
   - 版本历史
   - 新增功能
   - 变更记录
   - 迁移指南

---

## 🎯 根据场景选择文档

### 场景1: 我是新手，想快速开始

```
1. 阅读 [快速开始指南](QUICK_START.md)
2. 导入 [Postman集合](SMS_API.postman_collection.json) 测试API
3. 参考代码示例开始开发
```

### 场景2: 我需要详细的API参考

```
1. 查看 [完整API文档](README.md)
2. 了解 [错误处理指南](ERROR_HANDLING.md)
3. 遵循 [最佳实践指南](BEST_PRACTICES.md)
```

### 场景3: 我遇到了错误

```
1. 查看 [错误处理指南](ERROR_HANDLING.md) 找到错误码
2. 参考 [完整API文档](README.md) 检查请求格式
3. 使用 [Postman集合](SMS_API.postman_collection.json) 测试
```

### 场景4: 我想优化现有集成

```
1. 阅读 [最佳实践指南](BEST_PRACTICES.md)
2. 检查 [更新日志](CHANGELOG.md) 了解新特性
3. 参考错误处理和性能优化章节
```

---

## 🔑 核心概念

### API基础信息

- **Base URL**: `https://smsapi2.niceapp.eu.cc/api`
- **数据格式**: JSON
- **认证方式**: API Key + Bearer Token
- **字符编码**: UTF-8

### 认证流程

```
1. 设备注册 → 获取Token
2. 使用Token访问受保护的API
3. 在请求头中携带API Key和Token
```

### 订单流程

```
1. 浏览服务 → 选择服务和国家
2. 创建订单 → 扣除积分
3. 激活订单 → 获取手机号
4. 等待验证码 → 轮询订单状态
5. 使用验证码 → 完成注册
```

---

## 📊 API端点概览

### 认证相关 (3个端点)

| 端点 | 方法 | 说明 |
|------|------|------|
| `/auth/register` | POST | 设备注册/登录 |
| `/auth/manual-register` | POST | 邮箱注册 |
| `/auth/password-login` | POST | 密码登录 |

### 服务相关 (3个端点)

| 端点 | 方法 | 说明 |
|------|------|------|
| `/service-countries/published` | GET | 获取已发布的服务-国家组合 |
| `/services` | GET | 获取服务列表 |
| `/countries` | GET | 获取国家列表 |

### 订单相关 (6个端点)

| 端点 | 方法 | 说明 |
|------|------|------|
| `/orders` | POST | 创建订单 |
| `/orders` | GET | 获取订单列表 |
| `/orders/{id}` | GET | 获取订单详情 |
| `/orders/{id}/activate` | POST | 激活订单 |
| `/orders/{id}/cancel` | POST | 取消订单 |
| `/orders/{id}/sms` | GET | 获取短信验证码 |

### 用户相关 (2个端点)

| 端点 | 方法 | 说明 |
|------|------|------|
| `/user/profile` | GET | 获取用户信息 |
| `/user/balance` | GET | 获取积分余额 |

### 支付相关 (2个端点)

| 端点 | 方法 | 说明 |
|------|------|------|
| `/points/packages` | GET | 获取充值套餐 |
| `/verify-receipt` | POST | 验证支付收据 |

### 系统相关 (1个端点)

| 端点 | 方法 | 说明 |
|------|------|------|
| `/health` | GET | 健康检查 |

**总计**: 17个API端点

---

## 💡 快速示例

### JavaScript

```javascript
// 1. 注册
const { token } = await fetch('https://smsapi2.niceapp.eu.cc/api/auth/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ device_id: 'my_device' })
}).then(r => r.json());

// 2. 创建订单
const { data: order } = await fetch('https://smsapi2.niceapp.eu.cc/api/orders', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Key': 'YOUR_API_KEY',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({ service_id: 1, country_id: 1 })
}).then(r => r.json());

// 3. 激活订单
const { data: activeOrder } = await fetch(
  `https://smsapi2.niceapp.eu.cc/api/orders/${order.id}/activate`,
  {
    method: 'POST',
    headers: {
      'X-API-Key': 'YOUR_API_KEY',
      'Authorization': `Bearer ${token}`
    }
  }
).then(r => r.json());

console.log('手机号:', activeOrder.phone_number);
```

### Python

```python
import requests

# 1. 注册
response = requests.post('https://smsapi2.niceapp.eu.cc/api/auth/register',
    json={'device_id': 'my_device'})
token = response.json()['token']

# 2. 创建订单
response = requests.post('https://smsapi2.niceapp.eu.cc/api/orders',
    headers={
        'X-API-Key': 'YOUR_API_KEY',
        'Authorization': f'Bearer {token}'
    },
    json={'service_id': 1, 'country_id': 1})
order = response.json()['data']

# 3. 激活订单
response = requests.post(
    f'https://smsapi2.niceapp.eu.cc/api/orders/{order["id"]}/activate',
    headers={
        'X-API-Key': 'YOUR_API_KEY',
        'Authorization': f'Bearer {token}'
    })
active_order = response.json()['data']

print(f'手机号: {active_order["phone_number"]}')
```

---

## 🛠️ 开发工具

### Postman

1. 下载 [Postman集合](SMS_API.postman_collection.json)
2. 在Postman中导入集合
3. 配置环境变量:
   - `base_url`: `https://smsapi2.niceapp.eu.cc/api`
   - `api_key`: 你的API Key
   - `token`: 登录后获取的Token
4. 开始测试API

### cURL

```bash
# 测试健康检查
curl https://smsapi2.niceapp.eu.cc/api/health

# 测试注册
curl -X POST https://smsapi2.niceapp.eu.cc/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"device_id":"test_device"}'
```

---

## ❓ 常见问题

### Q: 如何获取API Key?

A: API Key已配置在系统中: `606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077`

### Q: Token有效期多久?

A: Token长期有效，除非用户主动登出或重置。

### Q: 如何处理积分不足?

A: 创建订单时会返回`insufficient_balance`错误，引导用户充值。

### Q: 订单多久过期?

A: Pending订单24小时，Active订单20分钟。

### Q: 如何获取验证码?

A: 轮询订单详情接口，当`sms_code`字段不为空时表示收到验证码。

更多问题请查看 [快速开始指南](QUICK_START.md) 的常见问题章节。

---

## 📞 技术支持

### 文档问题

如果文档有不清楚的地方，请查看:
1. [完整API文档](README.md) - 详细说明
2. [错误处理指南](ERROR_HANDLING.md) - 错误解决
3. [最佳实践指南](BEST_PRACTICES.md) - 开发建议

### API问题

如果API使用遇到问题:
1. 检查请求格式是否正确
2. 查看错误码对照表
3. 使用Postman测试
4. 联系技术支持

---

## 📈 版本信息

- **当前版本**: v1.0.0
- **发布日期**: 2026-05-12
- **更新日志**: [CHANGELOG.md](CHANGELOG.md)

---

## 📄 许可和使用条款

使用本API即表示你同意遵守相关的使用条款和隐私政策。

---

## 🎓 学习路径

### 初级 (1-2小时)

1. ✅ 阅读快速开始指南
2. ✅ 使用Postman测试基础API
3. ✅ 完成第一个订单流程

### 中级 (3-5小时)

1. ✅ 阅读完整API文档
2. ✅ 实现错误处理
3. ✅ 集成到实际项目

### 高级 (1-2天)

1. ✅ 学习最佳实践
2. ✅ 优化性能和用户体验
3. ✅ 实现完整的生产级集成

---

## 🔄 文档更新

本文档会随着API的更新而持续更新。建议定期查看 [更新日志](CHANGELOG.md) 了解最新变化。

**最后更新**: 2026-05-12

---

**开始使用**: [快速开始指南](QUICK_START.md) →
