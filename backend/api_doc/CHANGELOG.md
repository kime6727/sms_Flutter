# API 更新日志

本文档记录 SMS 接码平台 API 的所有重要变更。

---

## [v1.2.0] - 2026-06-02 🔧 全局修复

### 🐛 重大 Bug 修复

#### 后端

- ✅ **P0-1** `/orders/{id}` 与 `/orders` 列表：新增子查询关联 `sms_messages` 表返回 `sms_code / sms_received_at / sms_content`，用户终于能看到验证码
- ✅ **P0-2** [webhook.php](../webhook.php)：移除不存在的 `sms_code` 列 UPDATE，事务改为只更新 `status=completed` + 写 `sms_messages` + 写 `notifications`（含 `related_order_id`）
- ✅ **P0-3** `/auth/manual-register` 响应新增 `credentials: {username, password, email}` 字段，快速注册可展示密码
- ✅ **P0-5** `/auth/forgot-password` 响应新增 `new_password` 字段（已注册邮箱才返回，未注册仅 success）
- ✅ **P0-4** 简化为单一流程：忘记密码 = `/auth/forgot-password`，不再需要 `reset_token`
- ✅ **P1-1** `UserModel.hasFirstTopupBonus` 改用后端返回的 `first_topup_countdown_hours` 字段
- ✅ **P1-2/P1-3** 统一密码规则 ≥ 8 位（前后端、注册/改密均同步）
- ✅ **P1-5** `notifications` 表新增 `related_order_id varchar(36)` 列 + 索引
- ✅ **P1-6** [cron/expire_orders.php](../cron/expire_orders.php) 字段名修正：`content` → `body`
- ✅ **P1-7** Flutter `UserModel.totalSpent / minSpent` 改为 `double`，避免精度丢失
- ✅ **P1-8** 删除 webroot 下的 `index.php.bak / test_*.php / check_*.php / setup*.php / repair_*.php / icon.php`，全部移入 [scripts/](../scripts/) 子目录并配 `.htaccess` 拒绝访问
- ✅ **P1-8** [cron_expire_orders.php](../cron_expire_orders.php) 移入 [cron/](../cron/) 子目录
- ✅ **P2-1** [services.php](../routes/services.php) 系数默认值与 constants.php 统一为 4.0 / 4.5
- ✅ **P2-2** [payment.php](../routes/payment.php) 移除 `/service-countries/published` 死代码
- ✅ **P2-5** `ApiService.init()` 不再抛 `StateError`，API Key 缺失时仅 `debugPrint` 警告，App 可启动到健康检查页
- ✅ **P2-6** `ApiService` 新增 `addOnUnauthorizedListener`，`AuthProvider` 注册 401 回调，触发自动跳登录页
- ✅ **P2-7** 删除 `temp_web/` 临时 Flutter 模板

#### 数据库

- ✅ 新增迁移 [20260602_add_notifications_related_order.sql](../migrations/20260602_add_notifications_related_order.sql)
- ✅ [database.sql](../database.sql) `notifications` 表结构补充 `related_order_id` 列
- ✅ 通知表 `content` → `body` 字段（统一为 spec 字段名）

#### 前端 Flutter

- ✅ [forgot_password_screen.dart](../../app_Flutter/lib/screens/auth/forgot_password_screen.dart) 简化为只输邮箱，UI 直接展示 `new_password`
- ✅ [user_model.dart](../../app_Flutter/lib/models/user_model.dart) 金额字段全部改 `double`
- ✅ [api_service.dart](../../app_Flutter/lib/services/api_service.dart) 401 监听器 + 优雅降级
- ✅ [auth_provider.dart](../../app_Flutter/lib/providers/auth_provider.dart) 注册 401 回调 + 注销

### 📚 文档更新

- ✅ [README.md](README.md) **完全重写**，从 17 个端点扩充到 **34 个端点**，与代码 100% 对齐
  - 移除不存在的 `/auth/register`（仅保留 `/auth/manual-register`）
  - 移除密码 ≥ 6 位，统一为 ≥ 8 位
  - 新增完整 `forgot-password` 流程（已废弃 `reset_token`）
  - 新增订单批量、库存查询、请求重发、用户余额流水、设备注册等 17 个端点
  - 新增 Webhook 章节（`/webhook/hero-sms`）
  - 新增会员等级、通知、横幅、系统设置等 4 个模块
  - 补充错误码字典（28 个错误码）
- ✅ 新增 [HEROSMS_API.md](HEROSMS_API.md)（上一版本）— 第三方供应商完整 API

---

## [v1.1.0] - 2026-06-02

### 📚 文档补充

#### 新增

- ✅ **HeroSMS 第三方 API 文档** ([HEROSMS_API.md](HEROSMS_API.md))
  - 完整收录 HeroSMS 13 个端点
  - 与本项目 [lib/HeroSMS.php](../lib/HeroSMS.php) 封装方法一一对应
  - 状态码字典、错误码字典、国家代码表
  - Webhook 推送协议说明
  - 限流、最佳实践、常见坑

#### INDEX.md 更新

- 新增「场景5:对接 HeroSMS 第三方供应商」入口
- 文档导航新增 HeroSMS 文档链接

---

## [v1.0.0] - 2026-05-12

### 🎉 初始版本

- 完整 API 文档
- 包含 17 个端点
- 错误码字典
- Postman 集合
- 5 分钟集成指南
- 错误处理最佳实践
- 性能优化指南
- 部署检查清单

### 🎉 初始版本发布

#### 新增功能

**认证系统**
- ✅ 设备注册/登录 (`POST /auth/register`)
- ✅ 邮箱注册 (`POST /auth/manual-register`)
- ✅ 密码登录 (`POST /auth/password-login`)
- ✅ Token认证机制
- ✅ 首次注册赠送积分（5-20积分）

**服务管理**
- ✅ 获取已发布的服务-国家组合 (`GET /service-countries/published`)
- ✅ 获取服务列表 (`GET /services`)
- ✅ 获取国家列表 (`GET /countries`)
- ✅ 服务图标和国家旗帜支持

**订单系统**
- ✅ 创建订单 (`POST /orders`)
- ✅ 激活订单 (`POST /orders/{id}/activate`)
- ✅ 获取订单详情 (`GET /orders/{id}`)
- ✅ 获取订单列表 (`GET /orders`)
- ✅ 取消订单 (`POST /orders/{id}/cancel`)
- ✅ 获取短信验证码 (`GET /orders/{id}/sms`)
- ✅ 订单状态管理（pending/active/completed/expired/cancelled）
- ✅ 订单超时机制（pending 24小时，active 20分钟）

**用户系统**
- ✅ 获取用户信息 (`GET /user/profile`)
- ✅ 获取积分余额 (`GET /user/balance`)
- ✅ 交易记录查询
- ✅ 首充倒计时功能

**支付系统**
- ✅ 获取充值套餐 (`GET /points/packages`)
- ✅ Apple IAP支付验证 (`POST /verify-receipt`)
- ✅ 积分充值和扣除

**系统功能**
- ✅ 健康检查 (`GET /health`)
- ✅ API Key认证
- ✅ 统一错误响应格式

#### 技术特性

- ✅ RESTful API设计
- ✅ JSON数据格式
- ✅ Bearer Token认证
- ✅ API Key认证
- ✅ HTTPS加密传输
- ✅ 统一错误码系统
- ✅ 分页支持
- ✅ 请求参数验证

#### 文档

- ✅ 完整API文档 (README.md)
- ✅ 快速开始指南 (QUICK_START.md)
- ✅ 错误处理指南 (ERROR_HANDLING.md)
- ✅ 最佳实践指南 (BEST_PRACTICES.md)
- ✅ Postman集合文件
- ✅ 多语言代码示例（JavaScript/Python/Dart）

---

## 计划中的功能

### v1.1.0 (计划中)

**认证增强**
- [ ] 刷新Token机制
- [ ] 双因素认证
- [ ] 社交账号登录（Google/Apple）
- [ ] 密码重置功能

**订单增强**
- [ ] 订单搜索和筛选
- [ ] 批量订单操作
- [ ] 订单导出功能
- [ ] 订单统计分析

**通知系统**
- [ ] WebSocket实时通知
- [ ] 推送通知支持
- [ ] 短信到达通知
- [ ] 订单状态变更通知

**支付增强**
- [ ] Google Play支付
- [ ] 信用卡支付
- [ ] PayPal支付
- [ ] 优惠券系统

**用户功能**
- [ ] 用户偏好设置
- [ ] 收藏服务
- [ ] 使用历史统计
- [ ] 推荐奖励系统

**管理功能**
- [ ] 用户反馈API
- [ ] 服务评价系统
- [ ] 问题工单系统

### v1.2.0 (计划中)

**性能优化**
- [ ] GraphQL支持
- [ ] 响应压缩
- [ ] CDN加速
- [ ] 请求限流优化

**安全增强**
- [ ] 请求签名验证
- [ ] IP白名单
- [ ] 设备指纹识别
- [ ] 异常行为检测

**国际化**
- [ ] 多语言支持
- [ ] 多货币支持
- [ ] 本地化价格
- [ ] 时区处理

---

## 版本说明

### 版本号规则

遵循语义化版本 (Semantic Versioning):

- **主版本号 (Major)**: 不兼容的API变更
- **次版本号 (Minor)**: 向下兼容的功能新增
- **修订号 (Patch)**: 向下兼容的问题修正

示例: `v1.2.3`
- `1` - 主版本
- `2` - 次版本
- `3` - 修订版本

### 变更类型

- 🎉 **新增** - 新功能
- 🔧 **修改** - 功能变更
- 🐛 **修复** - Bug修复
- ⚠️ **废弃** - 即将移除的功能
- 🗑️ **移除** - 已移除的功能
- 🔒 **安全** - 安全相关更新
- 📝 **文档** - 文档更新

---

## 迁移指南

### 从未来版本迁移

当有不兼容的变更时，我们会在这里提供详细的迁移指南。

---

## 弃用政策

- 功能弃用会提前至少一个主版本通知
- 弃用的功能会在文档中标注 `@deprecated`
- 弃用的端点会返回 `Deprecated` 响应头
- 完全移除前会提供替代方案

---

## 支持政策

- **当前版本**: 完全支持，持续更新
- **前一个主版本**: 安全更新和关键Bug修复
- **更早版本**: 不再支持

当前支持的版本:
- v1.x.x ✅ 完全支持

---

## 反馈和建议

如果你有任何建议或发现问题，请通过以下方式联系我们：

- 📧 Email: support@example.com
- 💬 技术支持: 查看文档或联系客服
- 📝 功能建议: 提交功能请求

---

## 相关链接

- [API文档](README.md)
- [快速开始](QUICK_START.md)
- [错误处理](ERROR_HANDLING.md)
- [最佳实践](BEST_PRACTICES.md)

---

**最后更新**: 2026-05-12  
**当前版本**: v1.0.0
