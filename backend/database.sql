-- NOTE: 已移除所有 FOREIGN KEY 约束（TiDB Cloud 默认不支持）
-- 注: FOREIGN KEY 的引用完整性由应用层 PHP 代码管理，删除它们对业务无影响
-- ======================================================================
-- ⚠️  使用前必读：
--   TiDB Cloud SQL Editor 不允许 CREATE DATABASE，请先在 UI 建库：
--     Cluster -> SQL Editor -> 顶部 database 下拉 -> Create database
--     名字填: sms_receiver
--   然后顶部 database 选 sms_receiver，再执行本文件
--
-- 用法:
--   1. 先在 TiDB Cloud UI 创建 database: sms_receiver
--   2. SQL Editor 顶部 database 选 sms_receiver
--   3. 复制下面所有内容到 SQL Editor -> 点击 Run
-- 适用: MySQL 5.7+ / MySQL 8.0 / TiDB Cloud
-- 包含: 21 张基础表 + 8 次增量迁移 + 25+ 索引 + 初始数据
-- 注意: 所有 FOREIGN KEY 约束已移除（TiDB Cloud 默认不支持）
-- ======================================================================

-- Part A: 基础 schema (21 张表 + 初始数据)
-- ---------------------------------------------------------------------

-- ============================================
-- SMS 接码平台 - MySQL 5.7.44 初始化脚本
-- ============================================
-- 兼容版本: MySQL 5.7.44+ / MariaDB 10.2+
-- 创建日期: 2026-05-16
-- 说明: 完整的数据库初始化脚本，包含表结构、初始数据和性能索引
-- 使用方法: mysql -u root -p < newsms_init.sql
-- ============================================

-- 创建数据库（如果不存在）
-- 设置 SQL 模式
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- 1. 管理员表
-- ============================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id` varchar(36) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'admin',
  `status` varchar(20) DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `login_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认管理员账号 (密码: admin123)
-- 用两步：先 INSERT IGNORE 插入，再 UPDATE 强制刷新密码
-- 这样无论数据库有没有这条 admin 记录，最终密码都是正确的
INSERT IGNORE INTO `admins` (`id`, `username`, `password`, `email`, `role`, `status`) VALUES
('admin_001', 'admin', '$2y$10$2h0WZ8UA7Bq/k0Nj9JjuEOv3WqJvC2BilsFvpeUdGixYOEy26o9je', 'admin@example.com', 'admin', 'active');

-- 强制刷新 admin 密码（hash 之前拼错了，必须用 UPDATE 覆盖）
UPDATE `admins` SET `password` = '$2y$10$2h0WZ8UA7Bq/k0Nj9JjuEOv3WqJvC2BilsFvpeUdGixYOEy26o9je' WHERE `username` = 'admin';

-- ============================================
-- 2. 管理员操作日志表
-- ============================================
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` varchar(36) NOT NULL,
  `admin_username` varchar(255) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `resource` varchar(255) DEFAULT NULL,
  `resource_id` varchar(36) DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. 国家表
-- ============================================
CREATE TABLE IF NOT EXISTS `countries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hero_country_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `name_cn` varchar(255) DEFAULT NULL,
  `code` varchar(10) DEFAULT NULL,
  `flag` varchar(255) DEFAULT NULL,
  `phone_code` varchar(10) DEFAULT NULL,
  `active` tinyint DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. 服务表
-- ============================================
CREATE TABLE IF NOT EXISTS `services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hero_service_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `name_cn` varchar(255) DEFAULT NULL,
  `code` varchar(50) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `description` text,
  `is_published` tinyint DEFAULT '0',
  `is_active` tinyint DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `is_pinned` tinyint DEFAULT '0' COMMENT '是否置顶显示',
  `tag` tinyint DEFAULT '0' COMMENT '标签: 0=无 1=热门 2=推荐',
  `parent_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_hero_service_id` (`hero_service_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_published` (`is_published`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_pinned` (`is_pinned`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. 服务国家关联表
-- ============================================
CREATE TABLE IF NOT EXISTS `service_countries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `country_id` int NOT NULL,
  `price` decimal(10,4) DEFAULT '0.0000' COMMENT 'HeroSMS 返回的成本价(美元)',
  `stock` int NOT NULL DEFAULT '0' COMMENT '库存数量',
  `cost_points` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '用户需支付积分(根据 price × 汇率计算)',
  `is_published` tinyint DEFAULT '0',
  `is_active` tinyint DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_service_country` (`service_id`,`country_id`),
  KEY `idx_service_id` (`service_id`),
  KEY `idx_country_id` (`country_id`),
  KEY `idx_published_active` (`is_published`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. 用户表
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
  `id` varchar(36) NOT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `nickname` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `role` varchar(20) DEFAULT 'user',
  `balance` decimal(10,2) DEFAULT '0.00',
  `total_recharge` decimal(10,2) DEFAULT '0.00',
  `total_spent` decimal(10,2) DEFAULT '0.00' COMMENT '累计消费（用于优惠/等级判断）',
  `order_count` int DEFAULT 0 COMMENT '订单总数',
  `first_recharge_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `login_ip` varchar(45) DEFAULT NULL,
  `register_ip` varchar(45) DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_device_id` (`device_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. 订单表
-- ============================================
CREATE TABLE IF NOT EXISTS `orders` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `service_id` int NOT NULL,
  `country_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `price` decimal(10,2) DEFAULT '0.00',
  `total_cost` decimal(10,2) DEFAULT '0.00',
  `total_price` decimal(10,2) DEFAULT '0.00' COMMENT '总售价（用户支付）',
  `cost_price` decimal(10,2) DEFAULT '0.00' COMMENT '成本价（hero-sms 提供）',
  `profit` decimal(10,2) DEFAULT '0.00' COMMENT '利润',
  `status` varchar(20) DEFAULT 'pending',
  `phone_number` varchar(20) DEFAULT NULL,
  `service_name` varchar(255) DEFAULT NULL,
  `country_name` varchar(255) DEFAULT NULL,
  `hero_order_id` varchar(50) DEFAULT NULL,
  `hero_status` varchar(50) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_hero_order_id` (`hero_order_id`),
  KEY `idx_orders_user_status` (`user_id`, `status`),
  KEY `idx_orders_status_expires` (`status`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. 短信消息表
-- ============================================
CREATE TABLE IF NOT EXISTS `sms_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` varchar(36) NOT NULL,
  `sender` varchar(50) DEFAULT NULL,
  `content` text NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `received_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_received_at` (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. 系统设置表
-- ============================================
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text,
  `type` varchar(20) DEFAULT 'string',
  `description` text,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. 通知表
-- ============================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(100) DEFAULT NULL,
  `body` text,
  `data` json DEFAULT NULL,
  `related_order_id` varchar(36) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_notifications_user_read` (`user_id`, `status`),
  KEY `idx_related_order_id` (`related_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. 支付配置表
-- ============================================
CREATE TABLE IF NOT EXISTS `payment_configs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `credits` int NOT NULL DEFAULT '0',
  `active` tinyint DEFAULT '1',
  `config_name` varchar(255) DEFAULT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_id` (`product_id`),
  KEY `idx_active` (`active`),
  KEY `idx_min_price` (`price`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12.1 充值订单表（topup_orders）+ 支付订单（payment_orders）
-- 合并：同一张表支持 Apple IAP / 充值 / 支付 多种来源
-- ============================================
CREATE TABLE IF NOT EXISTS `topup_orders` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `package_id` int DEFAULT NULL COMMENT 'payment_configs.id 或 topup_packages.id',
  `package_source` varchar(20) DEFAULT 'topup_packages' COMMENT 'topup_packages / payment_configs',
  `product_id` varchar(50) DEFAULT NULL,
  `package_name` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '支付金额（CNY/USD）',
  `currency` varchar(10) DEFAULT 'CNY',
  `points` int NOT NULL DEFAULT '0' COMMENT '到账积分',
  `payment_method` varchar(20) DEFAULT 'apple_iap' COMMENT 'apple_iap / manual / admin',
  `status` varchar(20) DEFAULT 'pending' COMMENT 'pending / paid / failed / refunded',
  `transaction_id` varchar(255) DEFAULT NULL COMMENT 'Apple transaction id',
  `receipt` mediumtext COMMENT 'Apple receipt（base64）',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_transaction_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- payment_orders 兼容别名（部分老代码引用此表）
CREATE TABLE IF NOT EXISTS `payment_orders` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `package_id` int DEFAULT NULL,
  `product_id` varchar(50) DEFAULT NULL,
  `package_name` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(10) DEFAULT 'CNY',
  `points` int NOT NULL DEFAULT '0',
  `payment_method` varchar(20) DEFAULT 'apple_iap',
  `status` varchar(20) DEFAULT 'pending',
  `transaction_id` varchar(255) DEFAULT NULL,
  `receipt` mediumtext,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. 支付记录表
-- ============================================
CREATE TABLE IF NOT EXISTS `payment_records` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `product_id` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `environment` varchar(20) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'completed',
  `order_id` varchar(36) DEFAULT NULL,
  `receipt` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_transaction_id` (`transaction_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_payment_transaction` (`transaction_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. 积分变动记录表
-- ============================================
CREATE TABLE IF NOT EXISTS `credit_transactions` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_before` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `description` text,
  `order_id` varchar(36) DEFAULT NULL,
  `payment_id` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 14. Banner 轮播配置表
-- ============================================
CREATE TABLE IF NOT EXISTS `banners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Banner名称',
  `image_url` varchar(500) NOT NULL COMMENT '图片URL',
  `link_url` varchar(500) NOT NULL COMMENT '跳转URL',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT '显示顺序',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enabled_sort` (`is_enabled`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Banner轮播配置表';

-- ============================================
-- 15. 会员等级表
-- ============================================
CREATE TABLE IF NOT EXISTS `membership_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `name_en` varchar(50) DEFAULT NULL,
  `name_cn` varchar(50) DEFAULT NULL,
  `min_spent` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(5,2) NOT NULL DEFAULT '1.00',
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_min_spent` (`min_spent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 16. 设备表（已废弃，保留兼容）
-- ============================================
CREATE TABLE IF NOT EXISTS `devices` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `device_token` varchar(255) DEFAULT NULL,
  `device_type` varchar(20) DEFAULT 'ios',
  `app_version` varchar(20) DEFAULT NULL,
  `os_version` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_device_token` (`device_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 17. 收藏表（已废弃，保留兼容）
-- ============================================
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) NOT NULL,
  `service_id` int NOT NULL,
  `country_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_service_id` (`service_id`),
  KEY `idx_country_id` (`country_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 18. 用户 Token 表
-- ============================================
CREATE TABLE IF NOT EXISTS `user_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(36) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 19. 邮箱验证码表
-- ============================================
CREATE TABLE IF NOT EXISTS `email_verification_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 20. 服务系数表
-- ============================================
CREATE TABLE IF NOT EXISTS `service_coefficients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int DEFAULT NULL,
  `coefficient_before` decimal(5,2) DEFAULT NULL COMMENT '首充前价格系数，NULL则用系统默认值',
  `coefficient_after` decimal(5,2) DEFAULT NULL COMMENT '首充后价格系数，NULL则用系统默认值',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_service_id` (`service_id`),
  KEY `idx_service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 21. 操作日志表
-- ============================================
CREATE TABLE IF NOT EXISTS `operation_logs` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `resource` varchar(50) DEFAULT NULL,
  `resource_id` varchar(36) DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 外键约束
-- 注：所有 FOREIGN KEY 约束已移除（见文件顶部说明）
-- ============================================
-- Part B: 增量迁移
-- ---------------------------------------------------------------------

-- Migration 1: migrations/add_account_system_safe.sql
-- 添加账号密码系统字段（安全版本）
-- 执行时间：2026-03-27

-- 检查并添加 username 字段
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'users' 
AND COLUMN_NAME = 'username';

SET @query = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN username VARCHAR(50) UNIQUE COMMENT ''用户账号'' AFTER id',
    'SELECT ''username 字段已存在'' AS message');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加 password_hash 字段
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'users' 
AND COLUMN_NAME = 'password_hash';

SET @query = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) COMMENT ''密码哈希'' AFTER username',
    'SELECT ''password_hash 字段已存在'' AS message');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加 email 字段
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'users' 
AND COLUMN_NAME = 'email';

SET @query = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN email VARCHAR(255) UNIQUE COMMENT ''绑定邮箱'' AFTER password_hash',
    'SELECT ''email 字段已存在'' AS message');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加 username 索引
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'users' 
AND INDEX_NAME = 'idx_username';

SET @query = IF(@index_exists = 0,
    'CREATE INDEX idx_username ON users(username)',
    'SELECT ''idx_username 索引已存在'' AS message');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加 email 索引
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'users' 
AND INDEX_NAME = 'idx_email';

SET @query = IF(@index_exists = 0,
    'CREATE INDEX idx_email ON users(email)',
    'SELECT ''idx_email 索引已存在'' AS message');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 显示最终结果
SELECT 'Migration completed!' AS status;
SHOW COLUMNS FROM users;

-- Migration 2: migrations/add_countries_unique_index.sql
-- 为countries表添加唯一索引，避免并发插入冲突
-- 执行时间：2026-03-25

-- 添加hero_country_id唯一索引
ALTER TABLE `countries`
ADD UNIQUE KEY `uk_hero_country_id` (`hero_country_id`);

-- 为service_countries表添加联合唯一索引
ALTER TABLE `service_countries`
ADD UNIQUE KEY `uk_service_country` (`service_id`, `country_id`);

-- Migration 2.1: migrations/add_services_unique_index.sql
-- 为services表添加hero_service_id唯一索引，让 /sync ON DUPLICATE KEY 能去重
ALTER TABLE `services`
ADD UNIQUE KEY `uk_hero_service_id` (`hero_service_id`);

-- Migration 2.2: migrations/add_topup_packages_price.sql
-- 为topup_packages表添加price字段，Flutter model需要
ALTER TABLE `topup_packages`
ADD COLUMN `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '显示价格(参考用，实际以App Store为准)' AFTER `points`;

-- Migration 2.3: migrations/enhance_service_countries.sql
-- 增强 service_countries：加 stock 库存、cost_points 积分价、改 price 精度为 4 位
ALTER TABLE `service_countries`
  MODIFY COLUMN `price` decimal(10,4) DEFAULT '0.0000' COMMENT 'HeroSMS 返回的成本价(美元)',
  ADD COLUMN `stock` int NOT NULL DEFAULT '0' COMMENT '库存数量' AFTER `price`,
  ADD COLUMN `cost_points` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '用户需支付积分(根据 price × 汇率计算)' AFTER `stock`;

-- Migration 2.4: migrations/fix_service_coefficients.sql
-- 修正 service_coefficients 表：原 schema 错误地用了 (name, coefficient)，代码需要 service_id/coefficient_before/coefficient_after
-- 已存在表需修复（Installer 会跳过"已存在"错误）
ALTER TABLE `service_coefficients` ADD COLUMN `service_id` int DEFAULT NULL AFTER `id`;
ALTER TABLE `service_coefficients` ADD COLUMN `coefficient_before` decimal(5,2) DEFAULT NULL AFTER `service_id`;
ALTER TABLE `service_coefficients` ADD COLUMN `coefficient_after` decimal(5,2) DEFAULT NULL AFTER `coefficient_before`;
ALTER TABLE `service_coefficients` ADD UNIQUE KEY `uk_service_id` (`service_id`);

-- Migration 2.5: migrations/add_services_pinned_tag.sql
-- admin 服务管理页需要 is_pinned (置顶) 和 tag (热门/推荐标签) 字段
ALTER TABLE `services`
  ADD COLUMN `is_pinned` tinyint DEFAULT '0' COMMENT '是否置顶显示' AFTER `sort_order`,
  ADD COLUMN `tag` tinyint DEFAULT '0' COMMENT '标签: 0=无 1=热门 2=推荐' AFTER `is_pinned`,
  ADD KEY `idx_pinned` (`is_pinned`);

-- Migration 2.6: migrations/add_service_countries_custom_price.sql
-- service_countries 表增加「自定义价格」「系数」字段，admin 后台覆盖上游价格使用
ALTER TABLE `service_countries`
  ADD COLUMN `custom_price` decimal(10,4) DEFAULT NULL COMMENT '自定义价格(覆盖 HeroSMS 返回的成本价)' AFTER `price`,
  ADD COLUMN `coefficient` decimal(5,2) NOT NULL DEFAULT '1.00' COMMENT '价格倍数(在自定义价或上游价基础上乘)' AFTER `custom_price`;

-- Migration 3: migrations/add_performance_indexes.sql
-- 性能优化索引
-- 执行时间: 2026-05-11
-- 说明: 添加关键查询索引以提升API性能

-- 订单查询优化
CREATE INDEX idx_orders_user_status ON orders(user_id, status);
CREATE INDEX idx_orders_status_expires ON orders(status, expires_at);
CREATE INDEX idx_orders_hero_order ON orders(hero_order_id);
CREATE INDEX idx_orders_created ON orders(created_at DESC);

-- 服务国家关联优化
CREATE INDEX idx_sc_service_country ON service_countries(service_id, country_id);
CREATE INDEX idx_sc_published ON service_countries(is_published, is_active);
CREATE INDEX idx_sc_service_published ON service_countries(service_id, is_published, is_active);

-- 通知查询优化
CREATE INDEX idx_notifications_user_read ON notifications(user_id, read_at);
CREATE INDEX idx_notifications_created ON notifications(created_at DESC);

-- 支付记录优化
CREATE INDEX idx_payment_transaction ON payment_records(transaction_id);
CREATE INDEX idx_payment_user ON payment_records(user_id, created_at DESC);

-- 用户查询优化
CREATE INDEX idx_users_device ON users(device_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);

-- 积分流水优化
CREATE INDEX idx_credit_user_created ON credit_transactions(user_id, created_at DESC);

-- 短信消息优化
CREATE INDEX idx_sms_order ON sms_messages(order_id, received_at DESC);

-- 服务优化
CREATE INDEX idx_services_published ON services(is_published, is_active, sort_order);
CREATE INDEX idx_services_code ON services(code);

-- 国家优化
CREATE INDEX idx_countries_active ON countries(active);
CREATE INDEX idx_countries_hero_id ON countries(hero_country_id);

-- 验证索引创建
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'newsms'
AND INDEX_NAME LIKE 'idx_%'
ORDER BY TABLE_NAME, INDEX_NAME;

-- Migration 4: migrations/add_banners_table.sql
-- 创建 banners 表
CREATE TABLE IF NOT EXISTS `banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Banner名称',
  `image_url` varchar(500) NOT NULL COMMENT '图片URL',
  `link_url` varchar(500) NOT NULL COMMENT '跳转URL',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '显示顺序',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enabled_sort` (`is_enabled`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Banner轮播配置表';

-- Migration 5: migrations/20260516_account_system_optimization.sql
-- ============================================
-- 账户系统优化迁移脚本
-- 适用于 MySQL 5.7.44
-- 执行日期: 2026-05-16
-- ============================================

-- 0.0 添加 has_topup_history 字段（首充后打折用，price 计算依赖）
ALTER TABLE users ADD COLUMN has_topup_history TINYINT DEFAULT 0 COMMENT '是否充值过（首充前/后系数）';

-- 0.0.1 添加 membership_levels.active 字段（/user/profile 端点用到）
ALTER TABLE membership_levels ADD COLUMN active TINYINT DEFAULT 1 COMMENT '是否启用';

-- 0. 添加 orders 表缺字段（total_price / cost_price / profit，订单业务用到）
ALTER TABLE orders
  ADD COLUMN total_price decimal(10,2) DEFAULT '0.00' COMMENT '总售价（用户支付）',
  ADD COLUMN cost_price decimal(10,2) DEFAULT '0.00' COMMENT '成本价（hero-sms 提供）',
  ADD COLUMN profit decimal(10,2) DEFAULT '0.00' COMMENT '利润';

-- 0. 添加 total_spent / order_count 字段（密码登录 / 订单查询用到）
-- MySQL 8.0+ 不支持 ALTER TABLE ADD COLUMN IF NOT EXISTS，依赖 Installer 跳过 Duplicate column name
ALTER TABLE users
  ADD COLUMN total_spent decimal(10,2) DEFAULT '0.00' COMMENT '累计消费',
  ADD COLUMN order_count int DEFAULT 0 COMMENT '订单总数';

-- 1. 添加 Google/Apple 登录支持字段
-- MySQL 8.0+ 不支持 ALTER TABLE ADD COLUMN IF NOT EXISTS，依赖 Installer 跳过 Duplicate column name
ALTER TABLE users 
  ADD COLUMN google_id varchar(255) DEFAULT NULL COMMENT 'Google账号ID' AFTER email,
  ADD COLUMN apple_id varchar(255) DEFAULT NULL COMMENT 'Apple账号ID' AFTER google_id;

-- 2. 为 Google/Apple ID 添加唯一索引（如果不存在）
-- MySQL 5.7 不支持 IF NOT EXISTS，需要先检查
SET @index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'users' 
    AND index_name = 'uk_google_id'
);
SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE users ADD UNIQUE KEY uk_google_id (google_id)', 
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists2 = (
    SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'users' 
    AND index_name = 'uk_apple_id'
);
SET @sql2 = IF(@index_exists2 = 0, 
    'ALTER TABLE users ADD UNIQUE KEY uk_apple_id (apple_id)', 
    'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 3. 为现有用户自动生成昵称（如果昵称为空）
UPDATE users 
SET nickname = CONCAT('User_', FLOOR(100000 + RAND() * 900000))
WHERE nickname IS NULL OR nickname = '';

-- 4. 添加设备ID唯一索引（用于防刷）
-- 注意：不强制唯一，因为一个设备可能绑定多个账号，但注册奖励只给第一个
-- 这里添加普通索引提高查询性能
SET @index_exists3 = (
    SELECT COUNT(*) FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
    AND table_name = 'users' 
    AND index_name = 'idx_device_id'
);
-- 如果已有索引则跳过
SELECT IF(@index_exists3 > 0, '索引已存在', '索引不存在，请手动添加') AS result;

-- 5. 确保系统设置中有注册奖励配置
INSERT IGNORE INTO system_settings (`key`, value, type, description, updated_at) VALUES
('register_bonus_min', '5', 'string', '注册赠送积分最小值', NOW()),
('register_bonus_max', '10', 'string', '注册赠送积分最大值', NOW());

-- ============================================
-- 迁移完成
-- ============================================
SELECT '账户系统优化迁移完成！' AS status;

-- Migration 6: migrations/20260516_create_apple_transactions.sql
-- 创建 Apple 交易记录表，防止重复充值
CREATE TABLE IF NOT EXISTS `apple_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` VARCHAR(36) NOT NULL,
  `transaction_id` VARCHAR(100) NOT NULL UNIQUE,
  `original_transaction_id` VARCHAR(100) DEFAULT NULL,
  `product_id` VARCHAR(100) NOT NULL,
  `purchase_date` DATETIME DEFAULT NULL,
  `points_awarded` INT NOT NULL DEFAULT 0,
  `is_first_topup` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_transaction_id` (`transaction_id`),
  INDEX `idx_original_transaction_id` (`original_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 7: migrations/20260516_create_topup_packages.sql
-- ============================================
-- 创建充值套餐表
-- 执行日期: 2026-05-16
-- 说明: price字段不需要，价格由Apple IAP返回
-- ============================================

-- 1. 创建 topup_packages 表
CREATE TABLE IF NOT EXISTS `topup_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '套餐名称',
  `name_en` varchar(100) DEFAULT NULL COMMENT '套餐名称(英文)',
  `name_cn` varchar(100) DEFAULT NULL COMMENT '套餐名称(中文)',
  `description` text COMMENT '套餐描述',
  `points` int(11) NOT NULL COMMENT '获得积分数量',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '显示价格(参考用，实际以App Store为准)',
  `product_id` varchar(100) NOT NULL COMMENT 'Apple IAP 消耗型产品ID',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `is_recommended` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否推荐',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '显示顺序',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_id` (`product_id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值套餐表';

-- 2. 插入初始充值套餐数据
-- 注意：price由Apple返回，此处不配置
-- product_id需要与AppStore Connect中创建的产品ID一致
INSERT INTO `topup_packages` (`name`, `name_en`, `name_cn`, `description`, `points`, `product_id`, `is_active`, `is_recommended`, `sort_order`) VALUES
('小额充值', 'Small Top-up', '小额充值', '适合偶尔使用的用户', 50, 'com.niceapps.sms.topup50', 1, 0, 1),
('标准套餐', 'Standard Package', '标准套餐', '适合轻度使用的用户', 100, 'com.niceapps.sms.topup100', 1, 0, 2),
('热门套餐', 'Popular Package', '热门套餐', '最受欢迎的选择', 300, 'com.niceapps.sms.topup300', 1, 1, 3),
('超值套餐', 'Value Package', '超值套餐', '性价比更高的选择', 500, 'com.niceapps.sms.topup500', 1, 0, 4),
('高级套餐', 'Premium Package', '高级套餐', '适合频繁使用的用户', 1000, 'com.niceapps.sms.topup1000', 1, 0, 5),
('豪华套餐', 'Deluxe Package', '豪华套餐', '适合重度使用的用户', 2000, 'com.niceapps.sms.topup2000', 1, 0, 6);

-- ============================================
-- 迁移完成
-- ============================================
SELECT '充值套餐表创建完成！' AS status;

-- Migration 8: migrations/20260602_add_rate_limits_and_reset_tokens.sql
-- 频率限制表：用于敏感接口（忘记密码、重置密码）的限流
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `action` VARCHAR(64) NOT NULL,
  `subject` VARCHAR(128) NOT NULL,
  `created_at` DATETIME NOT NULL,
  INDEX `idx_action_subject_time` (`action`, `subject`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 密码重置令牌表：一次性、30 分钟有效
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_token_hash` (`token_hash`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Migration 2.7: 补齐 payment_configs 缺失字段
--   修复运营后台 packages.php 报错：
--   "Unknown column 'display_price' / 'is_recommended' in 'field list'"
--   注:MySQL 5.7 / MariaDB 不支持 IF NOT EXISTS,
--      此 migration 仅作文档参考,实际由 packages.php 顶部自愈执行
-- ============================================
-- ALTER TABLE `payment_configs`
--   ADD COLUMN `display_price` DECIMAL(10,2) DEFAULT '0.00' COMMENT '参考价格(USD)' AFTER `credits`,
--   ADD COLUMN `is_recommended` TINYINT(1) DEFAULT '0' COMMENT '是否推荐' AFTER `description`,
--   ADD KEY `idx_is_recommended` (`is_recommended`);

