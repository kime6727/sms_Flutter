# SMS 接码平台 API 文档中心

> **当前版本**: v1.2.0 (2026-06-02)
> 文档与代码 100% 对齐。上一个版本是 v1.0.0 (2026-05-12)，中间所有遗漏端点/错误参数已在 v1.2.0 全面修复。

---

## 📚 文档导航

### 🚀 快速开始

**适合**: 第一次使用 API 的开发者

1. **[快速开始指南](QUICK_START.md)** ⭐ 推荐首先阅读
   - 5 分钟快速集成
   - 完整流程演示
   - 代码示例（JavaScript/Python/Dart）
   - 常见问题解答

### 📖 核心文档

**适合**: 需要详细了解 API 的开发者

2. **[完整 API 文档](README.md)** ⭐ 已重写，34 个端点
   - 所有 API 端点详细说明
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

5. **[Postman 集合](SMS_API.postman_collection.json)**
   - 可直接导入 Postman
   - 包含所有 API 端点
   - 预配置的请求示例
   - 环境变量模板

6. **[HeroSMS 第三方 API 文档](HEROSMS_API.md)**
   - 第三方接码服务商的完整 API 规范
   - 与本项目 [lib/HeroSMS.php](../lib/HeroSMS.php) 封装层的对应关系
   - 13 个端点详解 + 状态码/错误码字典
   - 国家代码表 + Webhook 推送协议
   - 限流与最佳实践

### 📝 更新日志

7. **[更新日志](CHANGELOG.md)**
   - 版本历史
   - 新增功能
   - 变更记录
   - 迁移指南

---

## 🎯 根据场景选择文档

### 场景1: 我是新手，想快速开始

```
1. 阅读 [快速开始指南](QUICK_START.md)
2. 导入 [Postman 集合](SMS_API.postman_collection.json) 测试 API
3. 参考代码示例开始开发
```

### 场景2: 我需要详细的 API 参考

```
1. 查看 [完整 API 文档](README.md)
2. 了解 [错误处理指南](ERROR_HANDLING.md)
3. 遵循 [最佳实践指南](BEST_PRACTICES.md)
```

### 场景3: 我遇到了错误

```
1. 查看 [错误处理指南](ERROR_HANDLING.md) 找到错误码
2. 参考 [完整 API 文档](README.md) 检查请求格式
3. 使用 [Postman 集合](SMS_API.postman_collection.json) 测试
```

### 场景4: 我想优化现有集成

```
1. 阅读 [最佳实践指南](BEST_PRACTICES.md)
2. 检查 [更新日志](CHANGELOG.md) 了解新特性
3. 参考错误处理和性能优化章节
```

### 场景5: 我想对接 HeroSMS 第三方供应商

```
1. 阅读 [HeroSMS 第三方 API 文档](HEROSMS_API.md)
2. 查看 [../lib/HeroSMS.php](../lib/HeroSMS.php) 了解本项目封装
3. 查看 [../routes/orders.php](../routes/orders.php) 了解业务集成
4. 查看 [../webhook.php](../webhook.php) 了解 HeroSMS 回调处理
```

---

## 🔑 核心概念

### API 基础信息

- **Base URL**: `https://<your-domain>/api`
- **数据格式**: JSON
- **认证方式**: API Key + Bearer Token
- **字符编码**: UTF-8
- **公开端点**: 无需鉴权，详见 [README.md §公开端点](README.md#公开端点无需-api-key--token)

### 认证流程

```
1. 邮箱一键注册 /auth/manual-register → 获得 token
2. 使用 token 访问受保护的 API
3. 在请求头中携带 API Key + Authorization: Bearer <token>
```

### 订单流程

```
1. 浏览服务 → GET /service-countries/published
2. 创建订单 → POST /orders (扣积分, status=pending)
3. 激活订单 → POST /orders/{id}/activate (拿真实号码, status=active)
4. 等待验证码 → GET /orders/{id} 轮询 (sms_code 不为空即收到)
5. 使用验证码 → 完成第三方注册
6. (可选) 取消 → POST /orders/{id}/cancel
```

---

## 📊 API 端点概览（v1.2.0，共 34 个）

### 认证 (5)
| 端点 | 方法 | 说明 |
|------|------|------|
| `/auth/manual-register` | POST | 邮箱一键注册（推荐）|
| `/auth/password-login` | POST | 邮箱密码登录 |
| `/auth/forgot-password` | POST | 忘记密码（生成新密码）|
| `/auth/change-password` | POST | 登录后改密 |
| `/auth/verify-password` | POST | 校验当前密码 |

### 服务 (3)
| 端点 | 方法 | 说明 |
|------|------|------|
| `/service-countries/published` | GET | 已发布的服务-国家组合 |
| `/services` | GET | 服务列表 |
| `/countries` | GET | 国家列表 |

### 订单 (8)
| 端点 | 方法 | 说明 |
|------|------|------|
| `/orders` | POST | 创建订单 |
| `/orders` | GET | 订单列表（分页/筛选）|
| `/orders/{id}` | GET | 订单详情（含 sms_code）|
| `/orders/{id}/activate` | POST | 激活订单 |
| `/orders/{id}/cancel` | POST | 取消订单（仅 pending）|
| `/orders/{id}/request-resend` | POST | 请求重发 SMS |
| `/orders/batch` | POST | 批量创建订单 |
| `/orders/stock` | GET | 查询库存 |

### 用户 (4)
| 端点 | 方法 | 说明 |
|------|------|------|
| `/user/profile` | GET | 用户详情 |
| `/user/profile` | PUT | 更新资料/改密 |
| `/user/balance` | GET | 积分余额+流水 |
| `/devices/register` | POST | 设备注册 |

### 支付/会员 (8)
| 端点 | 方法 | 说明 |
|------|------|------|
| `/points/packages` | GET | IAP 积分套餐 |
| `/topup-packages` | GET | 充值套餐 |
| `/payment/create` | POST | 创建支付订单 |
| `/topup/verify-apple` | POST | 验证 Apple IAP 收据 |
| `/user/first-topup-status` | GET | 首充奖励状态 |
| `/membership/levels` | GET | 会员等级列表 |
| `/coefficients/active` | GET | 当前生效系数 |
| `/recommend/numbers` | GET | 推荐号码（高级）|

### 通知 (3)
| 端点 | 方法 | 说明 |
|------|------|------|
| `/notifications` | GET | 通知列表 |
| `/notifications/{id}/read` | POST | 标记已读 |
| `/notifications/mark-all-read` | POST | 全部已读 |

### 横幅/系统 (3)
| 端点 | 方法 | 说明 |
|------|------|------|
| `/banners` | GET | 横幅列表 |
| `/system/settings` | GET | 全局设置 |
| `/system/health` | GET | 健康检查 |

### Webhook (1)
| 端点 | 方法 | 说明 |
|------|------|------|
| `/webhook/hero-sms` | POST | HeroSMS 短信到达回调 |

**总计**: 34 个 API 端点

---

## 💡 快速示例

### Dart (Flutter 客户端)

```dart
// 1. 邮箱一键注册
final resp = await apiService.register(
  email: 'user@example.com',
  password: 'MyStr0ng!Pass',
);
final token = resp['token'];

// 2. 加载服务
final scResp = await apiService.get('/service-countries/published');
final services = scResp['data'];

// 3. 创建订单
final orderResp = await apiService.post('/orders', body: {
  'service_id': services[0]['service_id'],
  'country_id': services[0]['country_id'],
});
final orderId = orderResp['order']['id'];

// 4. 激活
final activateResp = await apiService.post('/orders/$orderId/activate');
final phone = activateResp['order']['phone_number'];

// 5. 轮询等短信
Timer.periodic(Duration(seconds: 5), (timer) async {
  final detail = await apiService.get('/orders/$orderId');
  final smsCode = detail['order']['sms_code'];
  if (smsCode != null) {
    timer.cancel();
    print('收到验证码: $smsCode');
  }
});
```

### cURL

```bash
# 1. 健康检查
curl https://<your-domain>/api/system/health

# 2. 邮箱一键注册
curl -X POST https://<your-domain>/api/auth/manual-register \
  -H "Content-Type: application/json" \
  -H "X-API-Key: 606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077" \
  -d '{"email":"user@example.com","password":"MyStr0ng!Pass"}'
```

---

## 🛠️ 开发工具

### Postman

1. 下载 [Postman 集合](SMS_API.postman_collection.json)
2. 在 Postman 中导入集合
3. 配置环境变量:
   - `base_url`: `https://<your-domain>/api`
   - `api_key`: 你的 API Key
   - `token`: 登录后获取的 Token
4. 开始测试 API

---

## ❓ 常见问题

### Q: 如何获取 API Key?

A: 默认 API Key 已配置在系统中: `606e960bb9bf523fd52b9df68d2ad44e549fef6b9a48ccdcbadd3ca90d5b3077`

### Q: Token 有效期多久?

A: 30 天。过期或 401 时 App 自动跳转登录页。

### Q: 如何处理积分不足?

A: 创建订单时会返回 `insufficient_balance` 错误，引导用户充值。

### Q: 订单多久过期?

A: Pending 订单 24 小时，Active 订单 20 分钟。

### Q: 如何获取验证码?

A: 轮询 `GET /orders/{id}` 详情接口，当 `sms_code` 字段不为空时表示收到验证码。

更多问题请查看 [快速开始指南](QUICK_START.md) 的常见问题章节。

---

## 📞 技术支持

### 文档问题

如果文档有不清楚的地方，请查看:
1. [完整 API 文档](README.md) - 详细说明
2. [错误处理指南](ERROR_HANDLING.md) - 错误解决
3. [最佳实践指南](BEST_PRACTICES.md) - 开发建议

### API 问题

如果 API 使用遇到问题:
1. 检查请求格式是否正确
2. 查看错误码对照表
3. 使用 Postman 测试
4. 联系技术支持

---

## 📈 版本信息

- **当前版本**: v1.2.0
- **发布日期**: 2026-06-02
- **更新日志**: [CHANGELOG.md](CHANGELOG.md)

---

## 📄 许可和使用条款

使用本 API 即表示你同意遵守相关的使用条款和隐私政策。

---

## 🔄 文档更新

本文档会随着 API 的更新而持续更新。建议定期查看 [更新日志](CHANGELOG.md) 了解最新变化。

**最后更新**: 2026-06-02
