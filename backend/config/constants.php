<?php
/**
 * 系统常量定义
 * 
 * 将魔法数字和硬编码值集中管理
 */

// ==================== 订单状态 ====================
define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_ACTIVE', 'active');
define('ORDER_STATUS_COMPLETED', 'completed');
define('ORDER_STATUS_EXPIRED', 'expired');
define('ORDER_STATUS_CANCELLED', 'cancelled');

// ==================== 注册奖励 ====================
define('REGISTER_BONUS_MIN', 5);
define('REGISTER_BONUS_MAX', 20);
define('REGISTER_BONUS_WEIGHT', 70); // 70%概率给最小值

// ==================== 首充倒计时 ====================
define('FIRST_TOPUP_COUNTDOWN_HOURS', 24);

// ==================== Token有效期 ====================
define('TOKEN_EXPIRY_DAYS', 30);
define('TOKEN_EXPIRY_SECONDS', 86400 * 30);

// ==================== 订单过期时间 ====================
define('PENDING_ORDER_EXPIRE_HOURS_DEFAULT', 72);
define('ACTIVE_ORDER_TIMEOUT_MINUTES_DEFAULT', 20);

// ==================== 价格系数（默认值）====================
// 与 routes/services.php 保持一致：首充前 4.0，首充后 4.5
define('COEFFICIENT_BEFORE_DEFAULT', 4.0);
define('COEFFICIENT_AFTER_DEFAULT', 4.5);

// ==================== 限流配置 ====================
define('RATE_LIMIT_MAX_REQUESTS', 60);
define('RATE_LIMIT_WINDOW_SECONDS', 60);

// ==================== 缓存时间（秒）====================
define('CACHE_SERVICES_TTL', 300);      // 服务列表缓存5分钟
define('CACHE_COUNTRIES_TTL', 300);     // 国家列表缓存5分钟
define('CACHE_PACKAGES_TTL', 600);      // 充值套餐缓存10分钟

// ==================== 分页默认值 ====================
define('DEFAULT_PAGE_SIZE', 20);
define('MAX_PAGE_SIZE', 100);

// ==================== 批量订单限制 ====================
define('BATCH_ORDER_MIN', 1);
define('BATCH_ORDER_MAX', 10);

// ==================== 积分交易类型 ====================
define('CREDIT_TYPE_BONUS', 'bonus');           // 奖励
define('CREDIT_TYPE_PURCHASE', 'purchase');     // 购买
define('CREDIT_TYPE_REFUND', 'refund');         // 退款
define('CREDIT_TYPE_TOPUP', 'topup');           // 充值

// ==================== 通知类型 ====================
define('NOTIFICATION_TYPE_ORDER_EXPIRED', 'order_expired');
define('NOTIFICATION_TYPE_ORDER_COMPLETED', 'order_completed');
define('NOTIFICATION_TYPE_SYSTEM', 'system');

// ==================== HTTP状态码 ====================
define('HTTP_OK', 200);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_NOT_FOUND', 404);
define('HTTP_CONFLICT', 409);
define('HTTP_TOO_MANY_REQUESTS', 429);
define('HTTP_SERVER_ERROR', 500);
define('HTTP_SERVICE_UNAVAILABLE', 503);
