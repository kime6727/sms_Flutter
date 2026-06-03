# HeroSMS 第三方接码服务 API 文档

> 本文档系统化整理 HeroSMS（基于 SMS-Activate 协议）的 API 规范，配套说明本项目在 [lib/HeroSMS.php](../lib/HeroSMS.php) 的封装与使用方式。

**文档版本**: v1.0
**最后更新**: 2026-06-02
**适用项目**: SMS 接码平台 (sms_Flutter)
**官方文档**: https://hero-sms.com/api

---

## 目录

1. [概述](#1-概述)
2. [基础信息](#2-基础信息)
3. [认证机制](#3-认证机制)
4. [端点清单](#4-端点清单)
5. [核心端点详解](#5-核心端点详解)
   - [5.1 getBalance - 查询余额](#51-getbalance---查询余额)
   - [5.2 getNumber - 获取手机号](#52-getnumber---获取手机号)
   - [5.3 getStatus - 查询状态](#53-getstatus---查询状态)
   - [5.4 getStatusV2 - 查询状态(详细)](#54-getstatusv2---查询状态详细)
   - [5.5 setStatus - 修改状态](#55-setstatus---修改状态)
   - [5.6 getSms - 获取短信](#56-getsms---获取短信)
   - [5.7 cancelNumber - 取消号码](#57-cancelnumber---取消号码)
   - [5.8 getNumbersStatus - 查询库存](#58-getnumbersstatus---查询库存)
   - [5.9 getServicesList - 服务列表](#59-getserviceslist---服务列表)
   - [5.10 getCountries - 国家列表](#510-getcountries---国家列表)
   - [5.11 getPrices - 价格查询](#511-getprices---价格查询)
   - [5.12 setUserSetting - 用户设置](#512-setusersetting---用户设置)
6. [状态码字典](#6-状态码字典)
7. [错误码字典](#7-错误码字典)
8. [国家代码表(部分)](#8-国家代码表部分)
9. [Webhook 推送协议](#9-webhook-推送协议)
10. [本项目封装层](#10-本项目封装层)
11. [限流与最佳实践](#11-限流与最佳实践)

---

## 1. 概述

HeroSMS 是一家提供"全球虚拟手机号接收短信"服务的第三方供应商，其 API 协议与已停运的 **SMS-Activate** 完全兼容。

**业务定位**: 当用户在我们平台下单时，本项目后端扮演**中间商**角色：

```
用户(Flutter) → 本项目后端(PHP) → HeroSMS 真实供应商
                       ↓
                  接收 webhook 推送
                       ↓
            更新订单状态，推送验证码给用户
```

---

## 2. 基础信息

| 项目 | 值 |
|------|---|
| **Base URL** | `https://hero-sms.com/stubs/handler_api.php` |
| **协议** | HTTP/HTTPS (GET 或 POST) |
| **数据格式** | URL-encoded query string 入参，**纯文本**或 **JSON** 出参 |
| **字符编码** | UTF-8 |
| **限流** | 150 RPS (请求/秒) |
| **响应** | 同步响应，**无**长连接/Server-Sent Events |

**本项目封装后实际访问 URL**: `https://hero-sms.com/stubs/handler_api.php?api_key=xxx&action=xxx&...`

---

## 3. 认证机制

所有请求通过 URL query string 携带 `api_key`：

```
GET https://hero-sms.com/stubs/handler_api.php?api_key=YOUR_KEY&action=getBalance
```

**API Key 存储位置**: 本项目用 `hero_sms_api_key` 字段存于数据库 `system_settings` 表（见 [lib/KeyManager.php](../lib/KeyManager.php) ），**不写入代码或 .env**。通过后台管理界面配置。

---

## 4. 端点清单

| # | action | 本项目方法 | 说明 | 必传参数 |
|---|--------|-----------|------|---------|
| 1 | `getBalance` | `HeroSMS::getBalance()` | 查询账户余额 | `api_key, action` |
| 2 | `getNumber` | `HeroSMS::getNumber($service, $country)` | 下单获取号码 | `api_key, action, service, country` |
| 3 | `getStatus` | `HeroSMS::getStatus($id)` | 查询订单状态 | `api_key, action, id` |
| 4 | `getStatusV2` | `HeroSMS::getStatusV2($id)` | 查询订单状态(详细) | `api_key, action, id` |
| 5 | `setStatus` | `HeroSMS::setStatus($id, $status)` | 修改订单状态 | `api_key, action, id, status` |
| 6 | `getSms` | `HeroSMS::getSMS($id)` | 获取短信内容 | `api_key, action, id` |
| 7 | `cancelNumber` | `HeroSMS::cancelNumber($id)` | 取消号码 | `api_key, action, id` |
| 8 | `getNumbersStatus` | `HeroSMS::checkStock($service, $country)` | 库存数量 | `api_key, action, service, country` |
| 9 | `getServicesList` | `HeroSMS::getServicesList()` | 服务列表 | `api_key, action` |
| 10 | `getCountries` | `HeroSMS::getCountries()` | 国家列表 | `api_key, action` |
| 11 | `getPrices` | `HeroSMS::getPrices()` / `getServiceCountries($service)` | 价格查询 | `api_key, action` 可选 `service/country` |
| 12 | `setUserSetting` | `HeroSMS::setWebhookUrl($url)` | 设置 webhook | `api_key, action, setting, value` |
| 13 | `getUserSetting` | `HeroSMS::getWebhookUrl()` | 读取 webhook | `api_key, action, setting` |

---

## 5. 核心端点详解

### 5.1 getBalance - 查询余额

**用途**: 查询本平台在 HeroSMS 账户的可用余额。

**请求**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=getBalance
```

**响应**:
```
ACCESS_BALANCE:463.02
```

或 JSON 格式（部分版本）:
```json
{ "balance": 463.02 }
```

**本项目处理** ([HeroSMS.php:49-73](../lib/HeroSMS.php#L49-L73)):
- 兼容两种格式（`ACCESS_BALANCE:xxx` 字符串 + JSON）
- 返回 `['success' => true, 'balance' => 463.02]`

**错误响应**:
- `BAD_KEY` - API Key 无效

---

### 5.2 getNumber - 获取手机号

**用途**: 下单获取一个真实手机号。**这是整个业务最核心的端点**。

**请求**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=getNumber&service=wa&country=6
```

| 参数 | 必填 | 类型 | 说明 |
|------|------|------|------|
| `api_key` | ✅ | string | API Key |
| `action` | ✅ | string | `getNumber` |
| `service` | ✅ | string | 服务代码（`wa`=WhatsApp, `tg`=Telegram, `ig`=Instagram 等） |
| `country` | ✅ | int | 国家代码（`6`=Indonesia, `0`=Russia 等） |
| `operator` | ❌ | string | 强制指定运营商（`any` 表示任意） |
| `maxPrice` | ❌ | float | 最高接受价，超过此价不下单 |
| `ref` | ❌ | string | 推荐人 ID |
| `lang` | ❌ | string | `ru` 或 `en` |

**成功响应**:
```
ACCESS_NUMBER:234242:79991728822
```

**V2 响应**（若服务端支持 `getNumberV2`）:
```json
{
  "activationId": 4100,
  "phoneNumber": "62838*****",
  "activationCost": 2.4,
  "currency": 643,
  "countryCode": "6",
  "canGetAnotherSms": 1,
  "activationTime": "2026-06-02 13:50:08",
  "activationOperator": "any"
}
```

**字段含义**:
- `activationId` / `ACCESS_NUMBER` 第二段 - **本平台存的 hero_order_id**，后续所有 setStatus/getStatus 都靠它
- `phoneNumber` - 真实手机号，**带国家区号**
- `activationCost` - 实际扣费（卢布/美元）

**错误响应**（均为纯文本）:

| 错误码 | 含义 | 本项目处理 |
|--------|------|------------|
| `NO_NUMBERS` | 当前没有可用号码 | [HeroSMS.php:95-96](../lib/HeroSMS.php#L95-L96) |
| `NO_BALANCE` | 余额不足 | [HeroSMS.php:97-98](../lib/HeroSMS.php#L97-L98) |
| `BAD_SERVICE` | 无效的服务代码 | [HeroSMS.php:99-100](../lib/HeroSMS.php#L99-L100) |
| `BAD_KEY` | API Key 无效 | [HeroSMS.php:101-102](../lib/HeroSMS.php#L101-L102) |
| `WRONG_MAX_PRICE:13.21` | maxPrice 低于最低价 | 附带最低价提示 |

**本项目调用方**: [routes/orders.php:154-240](../routes/orders.php#L154-L240) 订单激活逻辑。

---

### 5.3 getStatus - 查询状态

**用途**: 轮询获取某订单的当前状态、短信内容。

**请求**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=getStatus&id=234242
```

**响应状态**（见 [状态码字典](#6-状态码字典)）:
```
STATUS_WAIT_CODE              ← 等待短信到达
STATUS_OK:12345               ← 收到短信，验证码在冒号后
STATUS_CANCEL                 ← 已取消
STATUS_WAIT_RETRY:next_sms    ← 等待重发（保留旧短信同时等新的）
```

**本项目实现** [HeroSMS.php:255-277](../lib/HeroSMS.php#L255-L277) 将字符串响应统一解析为：
```php
[
    'success' => true,
    'statusCode' => 1/3/6/8,
    'status' => 'STATUS_XXX',
    'sms' => '验证码或null',
    'message' => '中文描述'
]
```

---

### 5.4 getStatusV2 - 查询状态(详细)

**用途**: 返回更多元数据（SMS 接收时间、号码、运营商等），与 `getStatus` 用法相同。

**请求**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=getStatusV2&id=234242
```

**响应**: 与 `getStatus` 兼容，额外字段在 `raw` 中保留（[HeroSMS.php:343-364](../lib/HeroSMS.php#L343-L364)）。

**本项目使用场景**: 默认用 `getStatus`，仅在调试时用 V2。

---

### 5.5 setStatus - 修改状态

**用途**: 通知 HeroSMS 当前订单进度。是状态机的核心驱动。

**请求**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=setStatus&id=234242&status=1
```

**状态码**（**关键**，务必牢记）:

| 状态值 | 含义 | 调用时机 | 本项目封装方法 |
|--------|------|---------|----------------|
| `1` | 通知 HeroSMS "已发送短信" | `getNumber` 成功**之后立即调用** | `markReady($id)` |
| `3` | 请求重发（免费） | 用户点"重新发送"按钮 | `requestResend($id)` |
| `6` | 完成激活 | 收到验证码后调 | `complete($id)` |
| `8` | 取消激活（自动退款） | 用户点"取消订单" | `cancel($id)` |

**状态机时间线**:
```
getNumber → 立刻 setStatus(1) → 等 SMS → 收到后 setStatus(6) → 完成
              │                       │
              │                       └─ 用户主动取消 → setStatus(8) → 退款
              │
              └─ 主动请求重发 → setStatus(3) → 继续等
```

**响应**:
- `ACCESS_READY` - status=1 成功
- `ACCESS_RETRY_GET` - status=3 成功
- `STATUS_OK` - status=6 成功
- `ACCESS_CANCEL` - status=8 成功

**错误**:
- `EARLY_CANCEL_DENIED` - 号码下单后 2 分钟内不能取消（计费保护）
- `BAD_STATUS` - 状态码非法
- `NO_ACTIVATION` - 订单 ID 不存在或已过期

**本项目调用方**:
- `setStatus(1)` - [routes/orders.php:196](../routes/orders.php#L196)
- `setStatus(8)` - [routes/orders.php:280-310](../routes/orders.php#L280-L310) 取消订单
- `setStatus(3)` - [routes/orders.php:330-360](../routes/orders.php#L330-L360) 重新发送

---

### 5.6 getSms - 获取短信

**用途**: 单独获取某订单的最新短信内容（部分旧版 API 用）。

**请求**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=getSms&id=234242
```

**响应**:
- `SMS_RECEIVED:<完整短信内容>` - 已收到
- `NO_SMS` - 未到达
- `SMS_CANCELLED` - 已取消

> ⚠️ **本项目推荐用 `getStatus`** 替代 `getSms`，因为 `getStatus` 的 `STATUS_OK` 字段已经包含验证码，单独调 `getSms` 会浪费一次 API 调用配额。`getSms` 仅保留为兼容旧版（[HeroSMS.php:115-141](../lib/HeroSMS.php#L115-L141)）。

---

### 5.7 cancelNumber - 取消号码

**用途**: 强制取消某订单，会触发 HeroSMS 退款。

**请求**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=cancelNumber&id=234242
```

**响应**:
- `ACCESS_CANCEL` - 成功
- `NO_NUMBERS` - 已无号码（可能已退款）

**限制**: 号码下发后 **2 分钟内**不允许取消（HeroSMS 政策保护），会返回 `EARLY_CANCEL_DENIED`。

**本项目实现** [HeroSMS.php:225-243](../lib/HeroSMS.php#L225-L243) + 别名 [cancelOrder()](../lib/HeroSMS.php#L248-L250)。

---

### 5.8 getNumbersStatus - 查询库存

**用途**: 查询某服务某国家的可用号码数量（用于前端"库存 0"提示）。

**请求**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=getNumbersStatus&service=wa&country=6
```

**响应**: 纯数字，表示可用数。
```
153
```

**本项目实现** [HeroSMS.php:530-559](../lib/HeroSMS.php#L530-L559)：
- 解析失败时**默认返回 999**（避免阻塞用户），这是有意的"乐观降级"。

---

### 5.9 getServicesList - 服务列表

**请求**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=getServicesList
```

**响应**（JSON）:
```json
{
  "status": "success",
  "services": [
    {"id": "wa", "name": "WhatsApp", "price": 0.5, "quantity": 1500},
    {"id": "tg", "name": "Telegram", "price": 0.3, "quantity": 800}
  ]
}
```

**本项目实现** [HeroSMS.php:146-163](../lib/HeroSMS.php#L146-L163)。**实际不调用**——服务列表由后台手动维护在 `services` 表。

---

### 5.10 getCountries - 国家列表

**请求**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=getCountries
```

**响应**（JSON）:
```json
{
  "status": "success",
  "countries": [
    {"id": 6, "name": "Indonesia"},
    {"id": 0, "name": "Russia"}
  ]
}
```

**本项目实现** [HeroSMS.php:168-185](../lib/HeroSMS.php#L168-L185)。**实际不调用**——国家列表由 `countries` 表维护。

---

### 5.11 getPrices - 价格查询

**用途**: 实时价格查询。本项目主要用 `getServiceCountries()` 变体。

**请求（无过滤）**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=getPrices
```

**请求（按服务）**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=getPrices&service=wa
```

**响应**（嵌套 JSON）:
```json
{
  "6": {
    "wa": {"cost": 0.5, "count": 1500, "physicalCount": 50}
  },
  "0": {
    "wa": {"cost": 3.2, "count": 200, "physicalCount": 0}
  }
}
```

**本项目实现** [HeroSMS.php:190-220](../lib/HeroSMS.php#L190-L220) `getServiceCountries()` 反向解析：将国家作为数组元素返回，附带 `cost`、`count`、`physicalCount`。

**与本项目业务的关系**:
- HeroSMS 返回的 `cost` 是**美元**（按 USD 计价）
- 本平台在 [helpers/functions.php:77-128](../helpers/functions.php#L77-L128) 转换为**积分**：
  ```
  points = cost × 100 × coefficient × (1 - membership_discount)
  ```
- 默认系数在 [config/constants.php:32-33](../config/constants.php#L32-L33) 或 DB `system_settings`

---

### 5.12 setUserSetting - 用户设置

**用途**: 本项目用此端点**注册 webhook 回调地址**。

**请求（注册 webhook）**:
```
GET /stubs/handler_api.php?api_key={KEY}&action=setUserSetting&setting=webhook_url&value=https://yourapi.com/webhook/hero-sms
```

**响应**:
- `ACCESS` 开头的字符串或 `{"status":"success"}` JSON

**本项目实现** [HeroSMS.php:421-440](../lib/HeroSMS.php#L421-L440)。

**启动时自动注册** [index.php:99-118](../index.php#L99-L118)：
```php
// 启动时若 DB 中 webhook URL 与 HeroSMS 端不一致，自动重新注册
$currentWebhookUrl = rtrim(APP_URL, '/') . '/webhook/hero-sms';
$heroSms->setWebhookUrl($currentWebhookUrl);
```

---

## 6. 状态码字典

本项目 [HeroSMS::parseStatusString()](../lib/HeroSMS.php#L375-L416) 维护的映射表：

| HeroSMS 响应 | 含义 | 本项目 statusCode | 订单状态映射 |
|--------------|------|------------------|--------------|
| `STATUS_WAIT_CODE` | 等待短信 | `1` | `active` (waiting_sms) |
| `STATUS_WAIT_RESEND` | 等待重发 | `3` | `active` (waiting_resend) |
| `STATUS_OK:12345` | 收到短信 | `6` | `completed` |
| `STATUS_CANCEL` | 已取消 | `8` | `cancelled` |
| `STATUS_OK:next_sms` | 重发短信到达 | `6` | `completed`（更新验证码） |

**本项目订单状态机** ([orders 表 ENUM](../database.sql)):
```
pending → active → completed
   │         │
   │         └→ expired (20分钟自动过期)
   └→ cancelled (用户未支付/超时24h)
```

---

## 7. 错误码字典

| HeroSMS 错误码 | HTTP 等价 | 含义 | 本项目建议处理 |
|----------------|-----------|------|---------------|
| `BAD_KEY` | 401 | API Key 无效 | 后台告警，停止所有调用 |
| `BAD_ACTION` | 400 | action 拼写错误 | 代码 bug，立即修复 |
| `BAD_SERVICE` | 400 | 服务代码无效 | 显示"该服务暂不可用" |
| `BAD_COUNTRY` | 400 | 国家代码无效 | 显示"该国家暂无可用号码" |
| `BAD_STATUS` | 400 | setStatus 状态码非法 | 代码 bug，记录日志 |
| `BAD_LANG` | 400 | lang 参数错误 | 移除 lang 参数 |
| `NO_BALANCE` | 402 | 余额不足 | 后台充值 HeroSMS 账户 |
| `NO_NUMBERS` | 404 | 无可用号码 | 引导用户换国家/服务 |
| `NO_ACTIVATION` | 404 | 订单 ID 不存在/已过期 | 标记订单 `expired` |
| `EARLY_CANCEL_DENIED` | 429 | 2 分钟内不能取消 | 提示"请稍后再试" |
| `WRONG_MAX_PRICE:min` | 400 | maxPrice 过低 | 用 `min` 重新下单 |
| `ERROR_SQL` | 500 | HeroSMS 数据库错误 | 重试 3 次后告警 |
| `ERROR_API` | 500 | HeroSMS 内部错误 | 重试 3 次后告警 |
| `REQUEST_LIMIT` | 429 | 超过 RPS 限流 | 退避 1 秒后重试 |
| `BAD_REF` | 400 | 推荐人 ID 无效 | 移除 ref 参数 |
| `TOO_MANY_REQUESTS` (HTTP 429) | 429 | 触发限流 | 立即降并发 |

---

## 8. 国家代码表(部分)

> 完整列表见 https://sms-activation-service.com/en/api-sms-activate 附录 1。
> 本项目用内置映射表 [HeroSMS.php:10-32](../lib/HeroSMS.php#L10-L32) 把国家 ID 转为电话区号：

| ID | 国家 | 区号 | 常用服务 |
|----|------|------|----------|
| 0 | Russia | +7 | 全部 |
| 1 | Ukraine | +380 | 全部 |
| 3 | China | +86 | 微信/QQ |
| 6 | Indonesia | +62 | WhatsApp/Telegram |
| 16 | UK (England) | +44 | 全部 |
| 22 | India | +91 | 全部 |
| 36 | Canada | +1 | 全部 |
| 43 | Thailand | +66 | 全部 |
| 45 | Mexico | +52 | 全部 |
| 78 | France | +33 | 全部 |
| 187 | Bahrain | +973 | 全部 |

**ID 与 ISO 国家代码不同**，需要在 [service_countries](../database.sql) 表中维护映射。

---

## 9. Webhook 推送协议

> ⚠️ **当前本项目 webhook 未实现**（routes 缺 `/webhook/hero-sms` 端点），仅自动注册 URL。

### HeroSMS Webhook 协议（基于 SMS-Activate 兼容）

**触发时机**: 当 HeroSMS 收到短信时，POST 推送到预设的 webhook URL。

**典型推送载荷**:
```
id=<orderId>&number=<phone>&sms=<smsContent>&code=<extractedCode>&country=<countryId>&received_at=<timestamp>
```

**本项目应有的处理**（**未实现**，需补全）:
```php
// 伪代码 - 应当新建 routes/webhook.php
POST /webhook/hero-sms
  id=234242&number=79991728822&sms=Your+code+is+12345&code=12345&country=0

  → 根据 hero_order_id 查 orders 表
  → 更新 sms_code, sms_received_at, status='completed'
  → 推 APNs/FCM 通知用户（可选）
```

**替代方案**（**当前实现**）:
- Flutter [order_detail_screen.dart:135-176](../../app_Flutter/lib/screens/orders/order_detail_screen.dart#L135-L176) 启动后**轮询** `/orders/{id}` 端点（每 5 秒一次），由 [routes/orders.php:140-160](../routes/orders.php#L140-L160) `getOrder` 内部调用 `getStatus` 检测 `STATUS_OK`
- 这种"客户端轮询"模式**功能可行但浪费 HeroSMS 配额**

---

## 10. 本项目封装层

[lib/HeroSMS.php](../lib/HeroSMS.php) 完整方法列表：

| 方法 | 端点 | 返回结构 |
|------|------|---------|
| `getBalance()` | getBalance | `['success', 'balance']` |
| `getNumber($service, $country)` | getNumber | `['success', 'heroOrderId', 'phoneNumber']` |
| `getStatus($id)` | getStatus | `['success', 'statusCode', 'status', 'sms', 'message']` |
| `getStatusV2($id)` | getStatusV2 | 同上 + `raw` |
| `setStatus($id, $status)` | setStatus | `['success', 'message']` |
| `markReady($id)` | setStatus(1) | 同上 |
| `requestResend($id)` | setStatus(3) | 同上 |
| `complete($id)` | setStatus(6) | 同上 |
| `cancel($id)` | setStatus(8) | 同上 |
| `getSMS($id)` | getSms | `['success', 'sms']` |
| `cancelNumber($id)` | cancelNumber | `['success', 'message']` |
| `cancelOrder($id)` | cancelNumber 别名 | 同上 |
| `getServicesList()` | getServicesList | `['success', 'services']` |
| `getCountries()` | getCountries | `['success', 'countries']` |
| `getCountriesList()` | getCountries 别名 | 同上 |
| `getPrices()` | getPrices | `['success', 'prices']` |
| `getServiceCountries($service)` | getPrices (按服务) | `['success', 'countries[]']` |
| `checkStock($service, $country)` | getNumbersStatus | `['success', 'available']`（失败降级 999） |
| `setWebhookUrl($url)` | setUserSetting | `['success', 'message']` |
| `getWebhookUrl()` | getUserSetting | `['success', 'url']` |
| `getPhoneCodeByCountryId($id)` | 内置映射 | `string` 区号 |

**统一异常处理** [HeroSMS.php:466-496](../lib/HeroSMS.php#L466-L496)：
- 10 秒超时
- 失败抛 `Exception`（捕获后转 `NETWORK_ERROR` 错误码）
- 响应非 200 → 抛 HTTP 错误
- 自动尝试 JSON 解析，失败回退为纯字符串

**调用入口** [lib/KeyManager.php:24-40](../lib/KeyManager.php#L24-L40)：
```php
function getHeroSmsClient() {
    $apiKey = getSetting($GLOBALS['db'], 'hero_sms_api_key');
    return new HeroSMS($apiKey);
}
```

---

## 11. 限流与最佳实践

### 限流
- HeroSMS 限制 150 RPS，本项目单用户流量远低于此
- 启动时一次 `setWebhookUrl` 是最重的调用
- 订单状态轮询由前端控制频率（每 5 秒一次），**不会**打满

### 最佳实践

1. **缓存服务/国家列表** - 不应每次都调 `getServicesList` / `getCountries`，用 DB 缓存 24 小时
2. **乐观库存** - `checkStock` 失败时默认返回 999，避免阻塞用户下单
3. **超时降级** - 网络错误时让用户重试，而非直接报"系统错误"
4. **价格定时同步** - 后台 cron 任务每小时调 `getServiceCountries` 同步成本平台缓存
5. **余额监控** - `getBalance` 在每次 `getNumber` 之前调一次，若 `NO_BALANCE` 立即告警
6. **webhook + 轮询双保险** - 当前只实现轮询，**建议补全 webhook**（参见 §9）
7. **API Key 安全** - 存数据库 [system_settings.hero_sms_api_key](../database.sql) 而非代码或 .env

### 常见坑

| 现象 | 原因 | 解决 |
|------|------|------|
| 一直返回 `NO_NUMBERS` | 库存真无 / 服务+国家组合不存在 | 换国家或服务；查 HeroSMS 官方可用表 |
| `getNumber` 成功但 `getStatus` 返回 `NO_ACTIVATION` | 超过 20 分钟未收码，订单已自动失效 | 让用户重新下单 |
| `setStatus(8)` 报 `EARLY_CANCEL_DENIED` | 号码下发后不足 2 分钟 | 业务上"取消"按钮在订单 2 分钟后可点 |
| 价格抖动大 | HeroSMS 实时按供需定价 | 价格缓存时间缩短到 30 分钟 |
| webhook 推不到 | 后端 `/webhook/hero-sms` 不存在 | **本项目当前未实现 webhook**，仅靠轮询 |

---

## 附录:与本项目其他文档的关系

- [README.md](README.md) - 本平台对外 API 文档
- [QUICK_START.md](QUICK_START.md) - 5 分钟集成指南
- [ERROR_HANDLING.md](ERROR_HANDLING.md) - 错误码处理
- [BEST_PRACTICES.md](BEST_PRACTICES.md) - 性能优化
- [INDEX.md](INDEX.md) - 文档总入口
- [../lib/HeroSMS.php](../lib/HeroSMS.php) - 封装类实现
- [../routes/orders.php](../routes/orders.php) - 订单激活/取消业务逻辑
- [../index.php](../index.php) - webhook 自动注册入口

---

**维护人**: 待指定
**最后审查**: 2026-06-02
**下次审查**: 每次 HeroSMS API 大版本变更时
