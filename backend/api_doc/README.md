# SMS接码平台 API 文档

## 📋 目录

1. [快速开始](#快速开始)
2. [认证机制](#认证机制)
3. [通用说明](#通用说明)
4. [API端点](#api端点)
   - [认证相关](#认证相关)
   - [服务相关](#服务相关)
   - [订单相关](#订单相关)
   - [用户相关](#用户相关)
   - [支付相关](#支付相关)
5. [错误码](#错误码)
6. [示例代码](#示例代码)

---

## 快速开始

### 基础信息

- **Base URL**: `https://smsapi2.niceapp.eu.cc/api`
- **API版本**: v1.0
- **数据格式**: JSON
- **字符编码**: UTF-8

### 快速测试

```bash
# 健康检查
curl https://smsapi2.niceapp.eu.cc/api/health

# 设备注册
curl -X POST https://smsapi2.niceapp.eu.cc/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"device_id":"test_device_123"}'
```

---

## 认证机制

### API Key 认证

大部分API需要在请求头中携带API Key：

```
X-API-Key: 606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077
```

### Token 认证

用户相关操作需要Bearer Token：

```
Authorization: Bearer {token}
```

### 公开端点

以下端点无需API Key：
- `/health` - 健康检查
- `/auth/register` - 设备注册
- `/auth/login` - 用户登录
- `/auth/password-login` - 密码登录
- `/auth/manual-register` - 邮箱注册
- `/verify-receipt` - 支付验证

---

## 通用说明

### 请求头

```http
Content-Type: application/json
X-API-Key: {your_api_key}
Authorization: Bearer {token}  # 需要认证的接口
```

### 响应格式

#### 成功响应

```json
{
  "success": true,
  "message": "操作成功",
  "data": {
    // 响应数据
  }
}
```

#### 错误响应

```json
{
  "success": false,
  "error": "错误信息",
  "code": "error_code"  // 可选
}
```

### HTTP状态码

- `200` - 成功
- `400` - 请求参数错误
- `401` - 未授权
- `404` - 资源不存在
- `409` - 冲突（如邮箱已存在）
- `500` - 服务器错误

---

## API端点

### 认证相关

#### 1. 设备注册/登录

自动注册或登录设备，返回用户信息和Token。

**端点**: `POST /auth/register`

**请求参数**:
```json
{
  "device_id": "string"  // 必填，设备唯一标识
}
```

**响应示例**:
```json
{
  "success": true,
  "user": {
    "id": "user_f021264f3a5e6c99",
    "username": "user_644186",
    "balance": 5,
    "total_spent": 0,
    "order_count": 0,
    "created_at": "2026-05-12 15:25:02"
  },
  "token": "dXNlcl81NzQwODM4ZDU0ZDkzNDI2...",
  "credentials": {
    "username": "user_644186",
    "password": "B5zh2493",
    "show_once": true
  },
  "bonus_credits": 5,
  "message": "请保存您的账号密码，这是唯一一次显示密码的机会"
}
```

**说明**:
- 首次注册会生成随机用户名和密码
- 赠送5-20积分（可配置）
- 密码仅显示一次，请妥善保管
- 已注册设备直接返回登录信息

---

#### 2. 邮箱注册

使用邮箱和密码注册新账号。

**端点**: `POST /auth/manual-register`

**请求参数**:
```json
{
  "email": "user@example.com",  // 必填
  "password": "password123",     // 必填，至少6位
  "device_id": "device_123"      // 可选
}
```

**响应示例**:
```json
{
  "success": true,
  "user": {
    "id": "user_abc123",
    "username": "user_123456",
    "email": "user@example.com",
    "balance": 10,
    "created_at": "2026-05-12 16:00:00"
  },
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "bonus_credits": 10
}
```

**错误码**:
- `email_exists` - 邮箱已被使用
- `invalid_email` - 邮箱格式无效
- `password_too_short` - 密码少于6位

---

#### 3. 密码登录

使用邮箱和密码登录。

**端点**: `POST /auth/password-login`

**请求参数**:
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**响应示例**:
```json
{
  "success": true,
  "user": {
    "id": "user_abc123",
    "username": "user_123456",
    "email": "user@example.com",
    "balance": 100,
    "total_spent": 50,
    "order_count": 5
  },
  "token": "eyJhbGciOiJIUzI1NiIs..."
}
```

---

### 服务相关

#### 4. 获取已发布的服务国家

获取所有已发布的服务-国家组合列表。

**端点**: `GET /service-countries/published`

**请求头**:
```
X-API-Key: {your_api_key}
```

**响应示例**:
```json
{
  "success": true,
  "data": [
    {
      "id": 129,
      "service_id": 105,
      "country_id": 129,
      "name": "佐治亞州",
      "name_en": "Georgia",
      "flag": "https://cdn.hero-sms.com/assets/img/country/128.svg",
      "code": "",
      "phone_code": "269",
      "price": 0.01,
      "is_auto": 0,
      "active": 1,
      "is_published": 1
    }
  ]
}
```

**说明**:
- 返回所有已发布的服务-国家组合
- 客户端可根据service_id或country_id筛选
- price为积分价格

---

#### 5. 获取服务列表

获取所有可用服务。

**端点**: `GET /services`

**请求头**:
```
X-API-Key: {your_api_key}
```

**响应示例**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "WhatsApp",
      "name_cn": "WhatsApp",
      "code": "wa",
      "icon": "https://example.com/whatsapp.png",
      "description": "WhatsApp验证码接收",
      "is_active": 1
    }
  ]
}
```

---

#### 6. 获取国家列表

获取指定服务的可用国家。

**端点**: `GET /countries?service_id={service_id}`

**请求头**:
```
X-API-Key: {your_api_key}
```

**查询参数**:
- `service_id` (必填): 服务ID

**响应示例**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "美国",
      "name_en": "United States",
      "code": "US",
      "phone_code": "1",
      "flag": "https://example.com/us.svg",
      "price": 10
    }
  ]
}
```

---

### 订单相关

#### 7. 创建订单

创建新的接码订单。

**端点**: `POST /orders`

**请求头**:
```
X-API-Key: {your_api_key}
Authorization: Bearer {token}
```

**请求参数**:
```json
{
  "service_id": 1,   // 必填
  "country_id": 1    // 必填
}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "id": "order_abc123",
    "user_id": "user_xyz789",
    "service_id": 1,
    "country_id": 1,
    "service_name": "WhatsApp",
    "country_name": "美国",
    "status": "pending",
    "total_price": 10,
    "created_at": "2026-05-12 16:00:00",
    "expires_at": "2026-05-13 16:00:00"
  }
}
```

**订单状态**:
- `pending` - 待激活（24小时有效期）
- `active` - 进行中（20分钟超时）
- `completed` - 已完成
- `expired` - 已过期
- `cancelled` - 已取消

**错误码**:
- `insufficient_balance` - 积分不足
- `service_not_found` - 服务不存在
- `country_not_found` - 国家不存在

---

#### 8. 激活订单

激活pending状态的订单，从HeroSMS获取真实号码。

**端点**: `POST /orders/{order_id}/activate`

**请求头**:
```
X-API-Key: {your_api_key}
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "id": "order_abc123",
    "status": "active",
    "phone_number": "+12345678901",
    "expires_at": "2026-05-12 16:20:00",
    "hero_order_id": "12345678"
  }
}
```

**说明**:
- 激活后有20分钟时间接收短信
- 超时未收到短信订单自动完成（不退款）
- 返回的手机号码用于注册目标平台

---

#### 9. 获取订单详情

获取指定订单的详细信息。

**端点**: `GET /orders/{order_id}`

**请求头**:
```
X-API-Key: {your_api_key}
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "id": "order_abc123",
    "user_id": "user_xyz789",
    "service_id": 1,
    "country_id": 1,
    "service_name": "WhatsApp",
    "country_name": "美国",
    "phone_number": "+12345678901",
    "status": "completed",
    "sms_code": "123456",
    "total_price": 10,
    "created_at": "2026-05-12 16:00:00",
    "activated_at": "2026-05-12 16:01:00",
    "completed_at": "2026-05-12 16:05:00",
    "expires_at": "2026-05-12 16:21:00"
  }
}
```

---

#### 10. 获取订单列表

获取当前用户的订单列表。

**端点**: `GET /orders`

**请求头**:
```
X-API-Key: {your_api_key}
Authorization: Bearer {token}
```

**查询参数**:
- `status` (可选): 筛选状态 (pending/active/completed/expired/cancelled)
- `page` (可选): 页码，默认1
- `limit` (可选): 每页数量，默认20

**响应示例**:
```json
{
  "success": true,
  "data": [
    {
      "id": "order_abc123",
      "service_name": "WhatsApp",
      "country_name": "美国",
      "phone_number": "+12345678901",
      "status": "completed",
      "sms_code": "123456",
      "total_price": 10,
      "created_at": "2026-05-12 16:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_items": 100,
    "per_page": 20
  }
}
```

---

#### 11. 取消订单

取消pending状态的订单，退还积分。

**端点**: `POST /orders/{order_id}/cancel`

**请求头**:
```
X-API-Key: {your_api_key}
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "id": "order_abc123",
    "status": "cancelled",
    "refund_amount": 10
  }
}
```

**说明**:
- 仅pending状态且未过期的订单可取消
- 取消后积分退还到账户
- active/completed状态订单不可取消

---

#### 12. 获取短信验证码

主动获取订单的短信验证码。

**端点**: `GET /orders/{order_id}/sms`

**请求头**:
```
X-API-Key: {your_api_key}
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "code": "123456",
    "received_at": "2026-05-12 16:05:00",
    "message": "Your verification code is: 123456"
  }
}
```

---

### 用户相关

#### 13. 获取用户信息

获取当前用户的个人信息。

**端点**: `GET /user/profile`

**请求头**:
```
X-API-Key: {your_api_key}
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "id": "user_abc123",
    "username": "user_123456",
    "email": "user@example.com",
    "balance": 100,
    "total_spent": 50,
    "order_count": 5,
    "created_at": "2026-05-01 10:00:00",
    "last_login": "2026-05-12 16:00:00",
    "has_topup_history": true,
    "first_topup_countdown_hours": 0
  }
}
```

---

#### 14. 获取积分余额

获取用户当前积分余额和交易记录。

**端点**: `GET /user/balance`

**请求头**:
```
X-API-Key: {your_api_key}
Authorization: Bearer {token}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "balance": 100,
    "transactions": [
      {
        "id": "txn_abc123",
        "type": "topup",
        "amount": 100,
        "balance_after": 100,
        "description": "充值",
        "created_at": "2026-05-12 16:00:00"
      },
      {
        "id": "txn_def456",
        "type": "purchase",
        "amount": -10,
        "balance_after": 90,
        "description": "购买WhatsApp号码",
        "created_at": "2026-05-12 16:05:00"
      }
    ]
  }
}
```

**交易类型**:
- `topup` - 充值
- `purchase` - 购买
- `refund` - 退款
- `bonus` - 赠送

---

### 支付相关

#### 15. 获取充值套餐

获取所有可用的充值套餐。

**端点**: `GET /points/packages`

**请求头**:
```
X-API-Key: {your_api_key}
```

**响应示例**:
```json
{
  "success": true,
  "data": [
    {
      "id": "pkg_small",
      "product_id": "com.sms.credits.100",
      "name": "小额充值",
      "credits": 100,
      "points": 100,
      "price": 0.99,
      "display_price": 0.99,
      "description": "适合偶尔使用",
      "is_recommended": false
    },
    {
      "id": "pkg_medium",
      "product_id": "com.sms.credits.500",
      "name": "标准充值",
      "credits": 500,
      "points": 500,
      "price": 4.99,
      "display_price": 4.99,
      "description": "最受欢迎",
      "is_recommended": true
    }
  ]
}
```

---

#### 16. 验证支付收据

验证Apple IAP支付收据并充值积分。

**端点**: `POST /verify-receipt`

**请求参数**:
```json
{
  "receipt_data": "base64_encoded_receipt",
  "product_id": "com.sms.credits.100",
  "transaction_id": "1000000123456789"
}
```

**响应示例**:
```json
{
  "success": true,
  "data": {
    "credits_added": 100,
    "new_balance": 200,
    "transaction_id": "txn_abc123"
  }
}
```

---

### 系统相关

#### 17. 健康检查

检查API服务状态。

**端点**: `GET /health`

**响应示例**:
```json
{
  "status": "ok",
  "timestamp": "2026-05-12 16:00:00"
}
```

---

## 错误码

### 通用错误码

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| `invalid_api_key` | API Key无效 | 401 |
| `unauthorized` | 未授权 | 401 |
| `invalid_token` | Token无效 | 401 |
| `token_expired` | Token已过期 | 401 |
| `resource_not_found` | 资源不存在 | 404 |
| `server_error` | 服务器错误 | 500 |

### 认证错误码

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| `email_exists` | 邮箱已被使用 | 409 |
| `invalid_email` | 邮箱格式无效 | 400 |
| `password_too_short` | 密码少于6位 | 400 |
| `invalid_credentials` | 用户名或密码错误 | 401 |
| `device_exists` | 设备已注册 | 409 |

### 订单错误码

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| `insufficient_balance` | 积分不足 | 400 |
| `order_not_found` | 订单不存在 | 404 |
| `order_expired` | 订单已过期 | 400 |
| `order_already_activated` | 订单已激活 | 400 |
| `cannot_cancel` | 订单不可取消 | 400 |
| `service_not_found` | 服务不存在 | 404 |
| `country_not_found` | 国家不存在 | 404 |
| `no_available_numbers` | 暂无可用号码 | 400 |

---

## 示例代码

### JavaScript/TypeScript

```typescript
// 配置
const API_BASE_URL = 'https://smsapi2.niceapp.eu.cc/api';
const API_KEY = 'your_api_key_here';

// 设备注册
async function registerDevice(deviceId: string) {
  const response = await fetch(`${API_BASE_URL}/auth/register`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ device_id: deviceId }),
  });
  return await response.json();
}

// 获取服务列表
async function getServices(token: string) {
  const response = await fetch(`${API_BASE_URL}/service-countries/published`, {
    headers: {
      'X-API-Key': API_KEY,
      'Authorization': `Bearer ${token}`,
    },
  });
  return await response.json();
}

// 创建订单
async function createOrder(token: string, serviceId: number, countryId: number) {
  const response = await fetch(`${API_BASE_URL}/orders`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-API-Key': API_KEY,
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({
      service_id: serviceId,
      country_id: countryId,
    }),
  });
  return await response.json();
}

// 激活订单
async function activateOrder(token: string, orderId: string) {
  const response = await fetch(`${API_BASE_URL}/orders/${orderId}/activate`, {
    method: 'POST',
    headers: {
      'X-API-Key': API_KEY,
      'Authorization': `Bearer ${token}`,
    },
  });
  return await response.json();
}

// 获取订单详情
async function getOrderDetail(token: string, orderId: string) {
  const response = await fetch(`${API_BASE_URL}/orders/${orderId}`, {
    headers: {
      'X-API-Key': API_KEY,
      'Authorization': `Bearer ${token}`,
    },
  });
  return await response.json();
}
```

### Python

```python
import requests

# 配置
API_BASE_URL = 'https://smsapi2.niceapp.eu.cc/api'
API_KEY = 'your_api_key_here'

# 设备注册
def register_device(device_id):
    response = requests.post(
        f'{API_BASE_URL}/auth/register',
        json={'device_id': device_id}
    )
    return response.json()

# 获取服务列表
def get_services(token):
    response = requests.get(
        f'{API_BASE_URL}/service-countries/published',
        headers={
            'X-API-Key': API_KEY,
            'Authorization': f'Bearer {token}'
        }
    )
    return response.json()

# 创建订单
def create_order(token, service_id, country_id):
    response = requests.post(
        f'{API_BASE_URL}/orders',
        headers={
            'Content-Type': 'application/json',
            'X-API-Key': API_KEY,
            'Authorization': f'Bearer {token}'
        },
        json={
            'service_id': service_id,
            'country_id': country_id
        }
    )
    return response.json()

# 激活订单
def activate_order(token, order_id):
    response = requests.post(
        f'{API_BASE_URL}/orders/{order_id}/activate',
        headers={
            'X-API-Key': API_KEY,
            'Authorization': f'Bearer {token}'
        }
    )
    return response.json()
```

### Dart/Flutter

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

class SmsApiClient {
  static const String baseUrl = 'https://smsapi2.niceapp.eu.cc/api';
  static const String apiKey = 'your_api_key_here';
  
  // 设备注册
  static Future<Map<String, dynamic>> registerDevice(String deviceId) async {
    final response = await http.post(
      Uri.parse('$baseUrl/auth/register'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({'device_id': deviceId}),
    );
    return jsonDecode(response.body);
  }
  
  // 获取服务列表
  static Future<Map<String, dynamic>> getServices(String token) async {
    final response = await http.get(
      Uri.parse('$baseUrl/service-countries/published'),
      headers: {
        'X-API-Key': apiKey,
        'Authorization': 'Bearer $token',
      },
    );
    return jsonDecode(response.body);
  }
  
  // 创建订单
  static Future<Map<String, dynamic>> createOrder(
    String token,
    int serviceId,
    int countryId,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl/orders'),
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': apiKey,
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode({
        'service_id': serviceId,
        'country_id': countryId,
      }),
    );
    return jsonDecode(response.body);
  }
  
  // 激活订单
  static Future<Map<String, dynamic>> activateOrder(
    String token,
    String orderId,
  ) async {
    final response = await http.post(
      Uri.parse('$baseUrl/orders/$orderId/activate'),
      headers: {
        'X-API-Key': apiKey,
        'Authorization': 'Bearer $token',
      },
    );
    return jsonDecode(response.body);
  }
}
```

---

## 完整使用流程

### 1. 用户注册/登录

```
POST /auth/register
{
  "device_id": "unique_device_id"
}

→ 获得 token 和用户信息
```

### 2. 浏览服务

```
GET /service-countries/published
Headers: X-API-Key, Authorization

→ 获得所有可用的服务-国家组合
```

### 3. 创建订单

```
POST /orders
{
  "service_id": 1,
  "country_id": 1
}
Headers: X-API-Key, Authorization

→ 创建pending状态订单
```

### 4. 激活订单

```
POST /orders/{order_id}/activate
Headers: X-API-Key, Authorization

→ 获得真实手机号码
→ 订单状态变为active
→ 开始20分钟倒计时
```

### 5. 等待验证码

```
GET /orders/{order_id}
Headers: X-API-Key, Authorization

→ 轮询获取订单状态
→ 当sms_code不为空时表示收到验证码
```

### 6. 使用验证码

```
复制验证码到目标平台完成注册
```

---

## 注意事项

### 1. 订单时效

- **Pending订单**: 24小时有效期，超时自动过期
- **Active订单**: 20分钟超时，超时自动完成
- 超时未收到短信不退款

### 2. 积分规则

- 创建订单时扣除积分
- 取消pending订单退还积分
- Active/Completed订单不可退款

### 3. 轮询建议

- 建议每5-10秒轮询一次订单状态
- 不要过于频繁请求，避免被限流
- 收到验证码后停止轮询

### 4. 错误处理

- 始终检查响应的success字段
- 根据error和code字段处理错误
- 网络错误时实现重试机制

### 5. 安全建议

- 不要在客户端硬编码API Key
- Token应安全存储（如Keychain/Keystore）
- 使用HTTPS确保传输安全
- 定期刷新Token

---

## 更新日志

### v1.0 (2026-05-12)
- 初始版本发布
- 支持设备注册/登录
- 支持邮箱注册/登录
- 支持服务浏览和订单管理
- 支持Apple IAP支付

---

## 技术支持

如有问题，请联系技术支持团队。

**API Base URL**: https://smsapi2.niceapp.eu.cc/api  
**文档版本**: v1.0  
**最后更新**: 2026-05-12
