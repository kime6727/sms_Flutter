# SMS 接码平台 API 文档 (v1.2)

> **最后更新**: 2026-06-02
> **基线代码**: 后端 [backend/routes/](../routes/) 8 个路由文件 + Flutter [app_Flutter/lib/services/api_service.dart](../../app_Flutter/lib/services/api_service.dart)
> **本文档已与代码实现完全对齐，废弃的端点和参数已移除**

---

## 📋 目录

1. [快速开始](#快速开始)
2. [认证机制](#认证机制)
3. [通用说明](#通用说明)
4. [API 端点](#api-端点)
   - [认证 (auth)](#认证-auth)
   - [服务与国家 (services)](#服务与国家-services)
   - [订单 (orders)](#订单-orders)
   - [用户 (user)](#用户-user)
   - [支付与充值 (payment/topup)](#支付与充值-paymenttopup)
   - [会员 (membership)](#会员-membership)
   - [通知 (notifications)](#通知-notifications)
   - [横幅 (banners)](#横幅-banners)
   - [系统 (system)](#系统-system)
   - [Webhook](#webhook)
5. [错误码](#错误码)
6. [示例代码](#示例代码)

---

## 快速开始

### 基础信息

- **Base URL**: `https://<your-domain>/api`
- **API 版本**: v1.2
- **数据格式**: JSON (UTF-8)
- **限流**: 默认 60 次/分钟/IP

### 快速测试

```bash
# 健康检查
curl https://<your-domain>/api/system/health

# 邮箱注册（自动生成用户名/密码）
curl -X POST https://<your-domain>/api/auth/manual-register \
  -H "Content-Type: application/json" \
  -H "X-Device-Id: test_device_001" \
  -d '{"email":"user@example.com","password":"MyStr0ng!Pass"}'
```

---

## 认证机制

### 1. API Key 认证

所有非公开端点需在请求头携带：
```
X-API-Key: <your_api_key>
```

### 2. Token 认证

用户私有接口额外需要：
```
Authorization: Bearer <token>
```

Token 有效期 30 天，HMAC-SHA256 签名，存于 `users.auth_token` 字段。

### 3. 公开端点（无需 API Key / Token）

| 端点 | 方法 | 说明 |
|------|------|------|
| `/system/health` | GET | 健康检查 |
| `/system/settings` | GET | 全局系统设置（公开部分）|
| `/banners` | GET | 横幅列表 |
| `/auth/manual-register` | POST | 邮箱一键注册 |
| `/auth/forgot-password` | POST | 忘记密码（生成新密码）|
| `/auth/password-login` | POST | 邮箱密码登录 |
| `/service-countries/published` | GET | 服务-国家组合 |
| `/points/packages` | GET | 积分套餐 |
| `/topup-packages` | GET | 充值套餐 |
| `/membership/levels` | GET | 会员等级列表 |
| `/coefficients/active` | GET | 当前生效系数 |

---

## 通用说明

### 请求头

```http
Content-Type: application/json
X-API-Key: <your_api_key>
X-Device-Id: <device_uuid>     # 部分接口必填
Authorization: Bearer <token>  # 用户私有接口必填
```

### 响应格式

**成功**:
```json
{
  "success": true,
  "data": { ... }
}
```

**失败**:
```json
{
  "success": false,
  "error": "错误信息",
  "code": "error_code"
}
```

### HTTP 状态码

| 状态码 | 含义 |
|--------|------|
| 200 | 成功 |
| 400 | 请求参数错误 |
| 401 | 未授权（Token 无效/过期）|
| 403 | 禁止访问 |
| 404 | 资源不存在 |
| 409 | 冲突（如邮箱已注册）|
| 429 | 触发限流 |
| 500 | 服务器错误 |
| 503 | 上游服务不可用（HeroSMS 余额不足等）|

---

## API 端点

### 认证 (auth)

> 实现位置: [routes/auth.php](../routes/auth.php) + [routes/payment.php](../routes/payment.php) (manual-register 别名)

#### 1. 邮箱密码登录

**端点**: `POST /auth/password-login`

**请求**:
```json
{
  "login": "user@example.com",  // 邮箱
  "password": "MyStr0ng!Pass"
}
```

**响应**:
```json
{
  "success": true,
  "user": {
    "id": "uuid",
    "username": "user_xxx",
    "email": "user@example.com",
    "balance": 10,
    "total_spent": 0,
    "order_count": 0,
    "is_new_device": true,
    "membership": { ... },
    "next_level": { ... },
    "progress": { ... },
    "all_levels": [...]
  },
  "token": "base64-encoded-hmac-token"
}
```

---

#### 2. 邮箱一键注册（推荐）

**端点**: `POST /auth/manual-register`

**请求**:
```json
{
  "email": "user@example.com",         // 必填
  "password": "MyStr0ng!Pass",          // 必填, ≥8 位
  "device_id": "device_uuid_xxx"        // 可选, 不传则自动生成
}
```

**响应**:
```json
{
  "success": true,
  "credentials": {
    "username": "user_abc123",         // ⚠️ 仅返回一次,请提示用户保存
    "password": "随机密码",
    "email": "user@example.com"
  },
  "user": {
    "id": "uuid",
    "username": "user_abc123",
    "email": "user@example.com",
    "nickname": "user_abc123",
    "balance": 10,
    "is_new_device": true
  },
  "token": "..."
}
```

**错误码**: `email_exists` / `invalid_email` / `password_too_short` (<8)

---

#### 3. 忘记密码

**端点**: `POST /auth/forgot-password`

**请求**:
```json
{ "email": "user@example.com" }
```

**响应**:
```json
{
  "success": true,
  "new_password": "随机8位密码",  // ⚠️ 后端生成,前端必须直接展示给用户
  "message": "新密码已生成,请妥善保管"
}
```

**安全说明**:
- 新密码同时通过邮件发送
- 该接口已废弃 `reset_token` 机制,无需先获取 token 再 reset,简化为"邮箱 → 新密码"
- ⚠️ 已注册邮箱会返回 `new_password`,未注册邮箱返回 `success: true` 但无 `new_password`(防枚举)

---

#### 4. 修改密码（登录后）

**端点**: `POST /auth/change-password`

**请求**:
```json
{
  "old_password": "原密码",
  "new_password": "新密码 (≥8 位)"
}
```

**响应**: `{ "success": true, "message": "密码已更新" }`

**错误码**: `wrong_password` / `password_too_short`

---

#### 5. 校验旧密码

**端点**: `POST /auth/verify-password`

**请求**:
```json
{ "password": "当前密码" }
```

**响应**: `{ "success": true, "valid": true }`

---

### 服务与国家 (services)

> 实现位置: [routes/services.php](../routes/services.php)

#### 6. 获取已发布的服务-国家组合

**端点**: `GET /service-countries/published`

**查询参数**:
- `lang`: `zh` / `en`(默认 `zh`)

**响应**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "service_id": 1,
      "country_id": 6,
      "service_name": "WhatsApp",
      "service_name_en": "WhatsApp",
      "service_icon": "https://<host>/pic/fuwu/wa.png",
      "country_name": "印度尼西亚",
      "country_name_en": "Indonesia",
      "country_code": "ID",
      "country_flag": "https://<host>/pic/country/id.svg",
      "phone_code": "+62",
      "price": 0.5,
      "price_points": 100,        // 实际扣的积分(已算系数)
      "available": 1500,          // 当前库存
      "is_auto": 1,
      "active": 1,
      "is_published": 1
    }
  ]
}
```

**价格计算公式**:
```
points = cost_usd × 100 × coefficient × (1 - membership_discount)
- coefficient 来自 system_settings(默认 4.0/4.5)
- discount 来自 membership_levels(默认 0)
```

---

#### 7. 获取服务列表

**端点**: `GET /services`

**响应**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "WhatsApp",
      "name_en": "WhatsApp",
      "name_cn": "WhatsApp",
      "code": "wa",
      "icon": "https://...wa.png",
      "is_published": 1
    }
  ]
}
```

---

#### 8. 获取国家列表

**端点**: `GET /countries?service_id=1`

**响应**:
```json
{
  "success": true,
  "data": [
    {
      "id": 6,
      "name": "印度尼西亚",
      "name_en": "Indonesia",
      "name_cn": "印度尼西亚",
      "code": "ID",
      "phone_code": "+62",
      "flag": "https://...id.svg",
      "hero_country_id": "6"
    }
  ]
}
```

---

### 订单 (orders)

> 实现位置: [routes/orders.php](../routes/orders.php)
> 状态机: `pending` → `active` → `completed | expired | cancelled`

#### 9. 创建订单

**端点**: `POST /orders`

**请求**:
```json
{
  "service_id": 1,
  "country_id": 6,
  "quantity": 1              // 可选, 默认 1
}
```

**响应**:
```json
{
  "success": true,
  "order": {
    "id": "uuid",
    "user_id": "uuid",
    "service_id": 1,
    "country_id": 6,
    "service_name": "WhatsApp",
    "country_name": "印度尼西亚",
    "status": "pending",
    "total_cost": 100,        // 实际扣的积分
    "price_points": 100,
    "expires_at": "2026-06-03 16:00:00",  // 24h 后过期
    "created_at": "2026-06-02 16:00:00"
  }
}
```

**错误码**: `insufficient_balance` / `service_not_found` / `country_not_found` / `service_country_not_published`

---

#### 10. 激活订单（拉真实号码）

**端点**: `POST /orders/{order_id}/activate`

**说明**:
- 调 HeroSMS 第三方 API 拿真号码
- 调 HeroSMS `setStatus(1)` 通知开始等短信
- 激活失败自动退款

**响应**:
```json
{
  "success": true,
  "order": {
    "id": "uuid",
    "status": "active",
    "phone_number": "+628123456789",
    "hero_order_id": "234242",
    "expires_at": "2026-06-02 16:20:00"  // 20 分钟
  }
}
```

**错误码**: `order_not_pending` / `order_expired` / `no_available_numbers` / `insufficient_hero_balance` / `hero_api_error`

---

#### 11. 获取订单详情

**端点**: `GET /orders/{order_id}`

**响应**:
```json
{
  "success": true,
  "order": {
    "id": "uuid",
    "user_id": "uuid",
    "service_id": 1,
    "country_id": 6,
    "service_name": "WhatsApp",
    "service_name_en": "WhatsApp",
    "service_icon": "https://...wa.png",
    "country_name": "印度尼西亚",
    "country_name_en": "Indonesia",
    "country_code": "ID",
    "country_flag": "https://...id.svg",
    "status": "completed",
    "phone_number": "+628123456789",
    "sms_code": "123456",           // ⚠️ 仅从 sms_messages 关联查询
    "sms_received_at": "2026-06-02 16:05:00",
    "sms_content": "Your code is 123456",
    "total_cost": 100,
    "price_points": 100,
    "hero_order_id": "234242",
    "expires_at": "2026-06-02 16:20:00",
    "activated_at": "2026-06-02 16:00:30",
    "completed_at": "2026-06-02 16:05:00",
    "created_at": "2026-06-02 16:00:00"
  }
}
```

---

#### 12. 获取订单列表

**端点**: `GET /orders`

**查询参数**:
- `status`: `pending` / `active` / `completed` / `expired` / `cancelled`
- `page`: 页码(默认 1)
- `limit`: 每页数量(默认 20, 最大 100)

**响应**:
```json
{
  "success": true,
  "orders": [ { ... 同 11 字段 ... } ],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_items": 100,
    "per_page": 20
  }
}
```

---

#### 13. 取消订单

**端点**: `POST /orders/{order_id}/cancel`

**说明**: 仅 pending 状态可取消,自动退款写 `credit_transactions`

**响应**:
```json
{
  "success": true,
  "order": { "id": "uuid", "status": "cancelled" },
  "refund_amount": 100
}
```

**错误码**: `cannot_cancel` (active/completed 不可取消)

---

#### 14. 请求重发 SMS

**端点**: `POST /orders/{order_id}/request-resend`

**说明**: 调 HeroSMS `setStatus(3)`,免费重发一次

**响应**: `{ "success": true, "message": "已请求重发,请等待新短信" }`

---

#### 15. 批量创建订单

**端点**: `POST /orders/batch`

**请求**:
```json
{
  "service_id": 1,
  "country_id": 6,
  "quantity": 5        // 1-10
}
```

**响应**:
```json
{
  "success": true,
  "count": 5,
  "total_cost": 500,
  "orders": [ ... ]
}
```

---

#### 16. 查询库存

**端点**: `GET /orders/stock?service_id=1&country_id=6`

**响应**:
```json
{ "success": true, "available": 1500 }
```

---

### 用户 (user)

> 实现位置: [routes/user.php](../routes/user.php)

#### 17. 获取用户信息

**端点**: `GET /user/profile`

**响应**:
```json
{
  "success": true,
  "user": {
    "id": "uuid",
    "username": "user_xxx",
    "email": "user@example.com",
    "balance": 100,
    "total_spent": 50.00,        // decimal
    "order_count": 5,
    "has_topup_history": true,
    "first_topup_countdown_hours": 18,  // >0 表示首充双倍仍生效
    "membership": {
      "level": "silver",
      "level_cn": "银卡",
      "min_spent": 100.00,
      "discount": 0.95,
      "icon": "https://...silver.png",
      "color": "#C0C0C0"
    },
    "next_level": {
      "level": "gold",
      "level_cn": "金卡",
      "min_spent": 500.00,
      "discount": 0.90
    },
    "progress": {
      "current": 50.00,
      "needed": 500.00,
      "percentage": 0
    },
    "all_levels": [...]
  }
}
```

---

#### 18. 更新用户资料

**端点**: `PUT /user/profile`

**请求**:
```json
{
  "nickname": "新昵称",
  "email": "new@example.com",
  "old_password": "原密码",   // 改密时必填
  "new_password": "新密码",   // 改密时必填
  "set_password": "新密码"    // 未设过密码时直接设
}
```

---

#### 19. 获取积分余额与流水

**端点**: `GET /user/balance?limit=20`

**响应**:
```json
{
  "success": true,
  "balance": 100,
  "transactions": [
    {
      "id": "txn_uuid",
      "type": "topup",
      "amount": 100,
      "balance_after": 100,
      "description": "充值",
      "created_at": "2026-06-02 16:00:00"
    }
  ]
}
```

---

#### 20. 设备注册/绑定

**端点**: `POST /devices/register`

**请求**:
```json
{
  "device_id": "device_uuid",
  "platform": "ios",
  "device_model": "iPhone 15"
}
```

---

### 支付与充值 (payment/topup)

> 实现位置: [routes/payment.php](../routes/payment.php) + [routes/topup.php](../routes/topup.php)

#### 21. 获取积分套餐（IAP 套餐）

**端点**: `GET /points/packages`

**响应**:
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
      "currency": "USD",
      "is_recommended": false,
      "is_first_topup_bonus": true  // 首充双倍时返 true
    }
  ]
}
```

---

#### 22. 获取充值套餐

**端点**: `GET /topup-packages`

**响应**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "标准充值",
      "credits": 500,
      "original_price": 4.99,
      "sale_price": 3.99,
      "bonus_credits": 50,
      "is_popular": true
    }
  ]
}
```

---

#### 23. 创建支付订单

**端点**: `POST /payment/create`

**请求**:
```json
{
  "package_id": "pkg_medium",
  "payment_method": "apple_iap"
}
```

**响应**:
```json
{
  "success": true,
  "order_id": "topup_uuid",
  "product_id": "com.sms.credits.500"
}
```

---

#### 24. 验证 Apple IAP 收据

**端点**: `POST /topup/verify-apple`

**请求**:
```json
{
  "receipt_data": "base64_encoded_receipt",
  "product_id": "com.sms.credits.500",
  "transaction_id": "1000000123456789",
  "order_id": "topup_uuid"
}
```

**响应**:
```json
{
  "success": true,
  "credits_added": 500,
  "new_balance": 600,
  "is_first_topup": false,
  "transaction_id": "txn_uuid"
}
```

---

#### 25. 获取首充奖励状态

**端点**: `GET /user/first-topup-status`

**响应**:
```json
{
  "success": true,
  "is_first_topup": true,
  "countdown_hours": 18,
  "bonus_multiplier": 2
}
```

---

### 会员 (membership)

> 实现位置: [routes/payment.php](../routes/payment.php)

#### 26. 获取会员等级列表

**端点**: `GET /membership/levels`

**响应**:
```json
{
  "success": true,
  "data": [
    { "level": "bronze", "level_cn": "铜卡", "min_spent": 0,    "discount": 1.0 },
    { "level": "silver", "level_cn": "银卡", "min_spent": 100,  "discount": 0.95 },
    { "level": "gold",   "level_cn": "金卡", "min_spent": 500,  "discount": 0.9 },
    { "level": "diamond","level_cn": "钻石", "min_spent": 2000, "discount": 0.85 }
  ]
}
```

---

### 通知 (notifications)

> 实现位置: [routes/notifications.php](../routes/notifications.php)

#### 27. 获取通知列表

**端点**: `GET /notifications?status=unread&page=1&limit=20`

**响应**:
```json
{
  "success": true,
  "notifications": [
    {
      "id": "notif_uuid",
      "type": "sms_received",
      "title": "验证码已收到",
      "body": "您的验证码: 123456",
      "data": { "order_id": "...", "sms_code": "123456" },
      "related_order_id": "order_uuid",
      "status": "unread",
      "read_at": null,
      "created_at": "2026-06-02 16:05:00"
    }
  ],
  "unread_count": 3
}
```

---

#### 28. 标记已读

**端点**: `POST /notifications/{id}/read`

---

#### 29. 全部标记已读

**端点**: `POST /notifications/mark-all-read`

---

### 横幅 (banners)

> 实现位置: [routes/payment.php](../routes/payment.php)

#### 30. 获取首页横幅

**端点**: `GET /banners`

**响应**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "首充双倍积分",
      "subtitle": "新用户首充 2 倍积分",
      "image_url": "https://...banner.png",
      "link_type": "topup",  // / web / service
      "link_value": "topup",
      "sort_order": 1,
      "is_active": true
    }
  ]
}
```

---

### 系统 (system)

> 实现位置: [routes/system.php](../routes/system.php)

#### 31. 健康检查

**端点**: `GET /system/health`

**响应**:
```json
{
  "status": "ok",
  "timestamp": "2026-06-02 16:00:00",
  "db": "ok",
  "hero_sms": "configured"
}
```

---

#### 32. 全局系统设置

**端点**: `GET /system/settings`

**响应**:
```json
{
  "success": true,
  "data": {
    "app_name": "Simu SMS",
    "support_email": "support@smsapi2.niceapp.eu.cc",
    "min_topup": 1,
    "max_topup": 10000,
    "default_coefficient_before": 4.0,
    "default_coefficient_after": 4.5,
    "register_bonus_min": 5,
    "register_bonus_max": 20,
    "first_topup_countdown_hours": 24,
    "active_order_timeout_minutes": 20
  }
}
```

---

#### 33. 当前生效价格系数

**端点**: `GET /coefficients/active`

---

### Webhook

> 实现位置: [backend/webhook.php](../../backend/webhook.php) — 通过 [router.php](../../backend/router.php#L26) 转发 `/webhook/*`

#### 34. HeroSMS 短信到达回调

**端点**: `POST /webhook/hero-sms` (注册到 HeroSMS 后台)

**请求** (POST, application/x-www-form-urlencoded):
```
id=234242&number=79991728822&sms=Your+code+is+12345&code=12345&country=0
```

**处理**:
1. 写 `sms_messages` 表
2. 更新 `orders.status = 'completed'`
3. 写 `notifications` 表(关联 `related_order_id`)

**响应**: `200 OK { "success": true }`

---

## 错误码

### 通用

| 错误码 | 含义 | HTTP |
|--------|------|------|
| `invalid_api_key` | API Key 无效 | 401 |
| `unauthorized` | 未授权 | 401 |
| `invalid_token` | Token 无效 | 401 |
| `token_expired` | Token 已过期 | 401 |
| `forbidden` | 禁止访问 | 403 |
| `not_found` | 资源不存在 | 404 |
| `rate_limit` | 触发限流 | 429 |
| `server_error` | 服务器错误 | 500 |

### 认证

| 错误码 | 含义 | HTTP |
|--------|------|------|
| `email_exists` | 邮箱已注册 | 409 |
| `invalid_email` | 邮箱格式无效 | 400 |
| `password_too_short` | 密码少于 8 位 | 400 |
| `wrong_password` | 密码错误 | 401 |
| `invalid_credentials` | 用户名/密码错误 | 401 |
| `email_not_registered` | 邮箱未注册 | 404 |

### 订单

| 错误码 | 含义 | HTTP |
|--------|------|------|
| `insufficient_balance` | 积分不足 | 400 |
| `order_not_found` | 订单不存在 | 404 |
| `order_not_pending` | 订单非 pending 状态 | 400 |
| `order_expired` | 订单已过期 | 400 |
| `cannot_cancel` | 订单不可取消 | 400 |
| `service_not_found` | 服务不存在 | 404 |
| `country_not_found` | 国家不存在 | 404 |
| `service_country_not_published` | 服务-国家组合未发布 | 400 |
| `no_available_numbers` | 暂无可用号码 | 503 |
| `insufficient_hero_balance` | HeroSMS 余额不足 | 503 |
| `hero_api_error` | HeroSMS API 错误 | 503 |
| `quantity_invalid` | 数量超出范围 1-10 | 400 |

### 支付

| 错误码 | 含义 | HTTP |
|--------|------|------|
| `package_not_found` | 套餐不存在 | 404 |
| `invalid_receipt` | Apple 收据无效 | 400 |
| `product_mismatch` | 产品 ID 不匹配 | 400 |
| `duplicate_transaction` | 重复交易 | 409 |

---

## 示例代码

### Flutter (官方客户端)

参考 [app_Flutter/lib/services/api_service.dart](../../app_Flutter/lib/services/api_service.dart) 实现。

### 关键流程示例

#### 完整下单收码

```dart
// 1. 登录
final loginResp = await apiService.login(
  login: 'user@example.com',
  password: 'MyStr0ng!Pass',
);
final token = loginResp['token'];

// 2. 加载服务
final services = await apiService.get('/service-countries/published');

// 3. 创建订单
final orderResp = await apiService.post('/orders', body: {
  'service_id': 1,
  'country_id': 6,
});
final orderId = orderResp['order']['id'];

// 4. 激活
final activateResp = await apiService.post('/orders/$orderId/activate');
final phone = activateResp['order']['phone_number'];

// 5. 轮询等短信(每 5 秒)
Timer.periodic(Duration(seconds: 5), (timer) async {
  final detail = await apiService.get('/orders/$orderId');
  final smsCode = detail['order']['sms_code'];
  if (smsCode != null) {
    timer.cancel();
    print('收到验证码: $smsCode');
  }
});
```

#### 邮箱一键注册

```dart
final resp = await apiService.register(
  email: 'newuser@example.com',
  password: 'MyStr0ng!Pass',
);

// resp.credentials.{username, password, email} 仅返回一次
// 必须用 AlertDialog 展示给用户
showDialog(
  context: context,
  builder: (_) => CredentialsDialog(credentials: resp['credentials']),
);
```

#### 忘记密码

```dart
final resp = await authProvider.forgotPassword('user@example.com');

if (resp['new_password'] != null) {
  // 后端生成了新密码
  showDialog(
    context: context,
    builder: (_) => AlertDialog(
      title: Text('新密码已生成'),
      content: SelectableText(resp['new_password']),
    ),
  );
}
```

---

## 完整使用流程

```
1. POST /auth/manual-register        → 注册并获得 token
2. GET  /service-countries/published  → 浏览服务-国家(显示价格)
3. POST /orders                       → 创建 pending 订单(扣积分)
4. POST /orders/{id}/activate         → 拿真实号码
5. GET  /orders/{id}  (轮询 5s)       → 等待 sms_code 非空
6. POST /orders/{id}/cancel (可选)    → 不想用了取消(仅 pending)
```

---

## 注意事项

### 1. 订单时效

- **Pending 订单**: 24h 有效期,超时自动 expired 退款
- **Active 订单**: 20min 收码窗口,超时自动 expired 退款
- 退款写 `credit_transactions` 流水

### 2. 积分规则

- 创建订单扣积分
- 取消 pending 订单退积分
- 激活失败自动退积分
- active/completed 不可退

### 3. 轮询建议

- 建议每 5-10 秒轮询一次
- 收码后立即停止轮询
- 可依赖本地通知推送(webhook + FCM/APNs)

### 4. 安全

- API Key 通过 `--dart-define=API_KEY=xxx` 编译注入
- Token 用 `flutter_secure_storage` 存 Keychain/Keystore
- 用户密码用 bcrypt 哈希
- HeroSMS API Key 从数据库 `system_settings` 读,不入代码

### 5. 字段命名规范

- 后端返驼峰或下划线均可,客户端 `fromJson` 兼容两种
- 价格字段:**decimal 优先**(用 double 接收)
- 时间字段:**ISO 8601 字符串**

---

## 技术支持

- **API Base URL**: `https://<your-domain>/api`
- **文档版本**: v1.2
- **最后更新**: 2026-06-02
- **配套文档**: [INDEX.md](INDEX.md) | [ERROR_HANDLING.md](ERROR_HANDLING.md) | [BEST_PRACTICES.md](BEST_PRACTICES.md) | [HEROSMS_API.md](HEROSMS_API.md)
