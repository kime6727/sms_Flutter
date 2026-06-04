# 快速开始指南

## 5分钟快速集成

本指南将帮助你在5分钟内完成SMS接码平台API的集成。

---

## 步骤1: 获取API Key

API Key已配置在系统中：

```
606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077
```

⚠️ **注意**: 生产环境中不要在客户端硬编码API Key，应通过安全的方式分发。

---

## 步骤2: 用户注册

使用设备ID注册用户，获取Token：

```bash
curl -X POST https://sms.niceapp.eu.cc/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "device_id": "my_device_123"
  }'
```

**响应示例**:
```json
{
  "success": true,
  "user": {
    "id": "user_abc123",
    "username": "user_123456",
    "balance": 10
  },
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "credentials": {
    "username": "user_123456",
    "password": "Abc12345"
  },
  "bonus_credits": 10
}
```

💡 **保存Token**: 将返回的`token`保存到本地，后续请求需要使用。

---

## 步骤3: 浏览服务

获取所有可用的服务和国家：

```bash
curl -X GET https://sms.niceapp.eu.cc/api/service-countries/published \
  -H "X-API-Key: 606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077"
```

**响应示例**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "service_id": 105,
      "country_id": 129,
      "name": "美国",
      "name_en": "United States",
      "price": 10
    }
  ]
}
```

---

## 步骤4: 创建订单

选择服务和国家，创建订单：

```bash
curl -X POST https://sms.niceapp.eu.cc/api/orders \
  -H "Content-Type: application/json" \
  -H "X-API-Key: 606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "service_id": 105,
    "country_id": 129
  }'
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "id": "order_xyz789",
    "status": "pending",
    "total_price": 10,
    "expires_at": "2026-05-13 16:00:00"
  }
}
```

💡 **保存订单ID**: 将返回的`id`保存，用于后续激活。

---

## 步骤5: 激活订单

激活订单获取真实手机号码：

```bash
curl -X POST https://sms.niceapp.eu.cc/api/orders/ORDER_ID/activate \
  -H "X-API-Key: 606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "id": "order_xyz789",
    "status": "active",
    "phone_number": "+12345678901",
    "expires_at": "2026-05-12 16:20:00"
  }
}
```

📱 **使用号码**: 复制`phone_number`到目标平台注册。

---

## 步骤6: 获取验证码

轮询订单状态，获取验证码：

```bash
curl -X GET https://sms.niceapp.eu.cc/api/orders/ORDER_ID \
  -H "X-API-Key: 606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "id": "order_xyz789",
    "status": "completed",
    "phone_number": "+12345678901",
    "sms_code": "123456",
    "completed_at": "2026-05-12 16:05:00"
  }
}
```

✅ **完成**: 当`sms_code`不为空时，表示收到验证码。

---

## 完整流程图

```
┌─────────────────┐
│  1. 设备注册     │
│  获取Token      │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  2. 浏览服务     │
│  选择服务和国家  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  3. 创建订单     │
│  扣除积分       │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  4. 激活订单     │
│  获取手机号     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  5. 使用号码     │
│  在目标平台注册  │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  6. 获取验证码   │
│  轮询订单状态   │
└─────────────────┘
```

---

## 代码示例

### JavaScript

```javascript
const API_BASE = 'https://sms.niceapp.eu.cc/api';
const API_KEY = '606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077';

// 1. 注册
const registerResponse = await fetch(`${API_BASE}/auth/register`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ device_id: 'my_device_123' })
});
const { token } = await registerResponse.json();

// 2. 获取服务
const servicesResponse = await fetch(`${API_BASE}/service-countries/published`, {
  headers: { 'X-API-Key': API_KEY }
});
const { data: services } = await servicesResponse.json();

// 3. 创建订单
const orderResponse = await fetch(`${API_BASE}/orders`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Key': API_KEY,
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    service_id: services[0].service_id,
    country_id: services[0].country_id
  })
});
const { data: order } = await orderResponse.json();

// 4. 激活订单
const activateResponse = await fetch(`${API_BASE}/orders/${order.id}/activate`, {
  method: 'POST',
  headers: {
    'X-API-Key': API_KEY,
    'Authorization': `Bearer ${token}`
  }
});
const { data: activeOrder } = await activateResponse.json();
console.log('手机号:', activeOrder.phone_number);

// 5. 轮询获取验证码
const checkSms = async () => {
  const response = await fetch(`${API_BASE}/orders/${order.id}`, {
    headers: {
      'X-API-Key': API_KEY,
      'Authorization': `Bearer ${token}`
    }
  });
  const { data } = await response.json();
  if (data.sms_code) {
    console.log('验证码:', data.sms_code);
    return data.sms_code;
  }
  // 5秒后重试
  setTimeout(checkSms, 5000);
};
checkSms();
```

### Python

```python
import requests
import time

API_BASE = 'https://sms.niceapp.eu.cc/api'
API_KEY = '606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077'

# 1. 注册
response = requests.post(f'{API_BASE}/auth/register', 
    json={'device_id': 'my_device_123'})
token = response.json()['token']

# 2. 获取服务
response = requests.get(f'{API_BASE}/service-countries/published',
    headers={'X-API-Key': API_KEY})
services = response.json()['data']

# 3. 创建订单
response = requests.post(f'{API_BASE}/orders',
    headers={
        'X-API-Key': API_KEY,
        'Authorization': f'Bearer {token}'
    },
    json={
        'service_id': services[0]['service_id'],
        'country_id': services[0]['country_id']
    })
order = response.json()['data']

# 4. 激活订单
response = requests.post(f'{API_BASE}/orders/{order["id"]}/activate',
    headers={
        'X-API-Key': API_KEY,
        'Authorization': f'Bearer {token}'
    })
active_order = response.json()['data']
print(f'手机号: {active_order["phone_number"]}')

# 5. 轮询获取验证码
while True:
    response = requests.get(f'{API_BASE}/orders/{order["id"]}',
        headers={
            'X-API-Key': API_KEY,
            'Authorization': f'Bearer {token}'
        })
    data = response.json()['data']
    if data.get('sms_code'):
        print(f'验证码: {data["sms_code"]}')
        break
    time.sleep(5)
```

---

## 常见问题

### Q1: 如何测试API？

**A**: 使用Postman导入`SMS_API.postman_collection.json`文件，或使用curl命令测试。

### Q2: Token有效期多久？

**A**: Token长期有效，除非用户主动登出或重置。建议安全存储。

### Q3: 如何处理积分不足？

**A**: 创建订单时会返回`insufficient_balance`错误，引导用户充值。

### Q4: 订单多久过期？

**A**: 
- Pending订单：24小时
- Active订单：20分钟

### Q5: 如何获取验证码？

**A**: 轮询订单详情接口，当`sms_code`字段不为空时表示收到验证码。建议每5-10秒轮询一次。

---

## 下一步

- 📖 阅读[完整API文档](README.md)
- 🔧 导入[Postman集合](SMS_API.postman_collection.json)
- 💻 查看[错误处理指南](ERROR_HANDLING.md)
- 🎯 了解[最佳实践](BEST_PRACTICES.md)

---

## 技术支持

如有问题，请查阅完整文档或联系技术支持。

**Base URL**: https://sms.niceapp.eu.cc/api  
**文档版本**: v1.0
