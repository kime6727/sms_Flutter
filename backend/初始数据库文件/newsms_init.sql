-- ============================================
-- SMS 接码平台 - MySQL 5.7.44 初始化脚本
-- ============================================
-- 兼容版本: MySQL 5.7.44+ / MariaDB 10.2+
-- 创建日期: 2026-05-16
-- 说明: 完整的数据库初始化脚本，包含表结构、初始数据和性能索引
-- 使用方法: mysql -u root -p < newsms_init.sql
-- ============================================

-- 创建数据库（如果不存在）
CREATE DATABASE IF NOT EXISTS `newsms` 
  DEFAULT CHARACTER SET utf8mb4 
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `newsms`;

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
INSERT IGNORE INTO `admins` (`id`, `username`, `password`, `email`, `role`, `status`) VALUES
('admin_001', 'admin', '$2y$10$./afSj92jcsZMOvo4m.H/uUfW.sCrOyaIG5wNm4E60jvsIS7mp3ja', 'admin@example.com', 'admin', 'active');

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
  `parent_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. 服务国家关联表
-- ============================================
CREATE TABLE IF NOT EXISTS `service_countries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `country_id` int NOT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `is_published` tinyint DEFAULT '0',
  `is_active` tinyint DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_service_country` (`service_id`,`country_id`),
  KEY `idx_service_id` (`service_id`),
  KEY `idx_country_id` (`country_id`),
  KEY `idx_sc_service_country` (`service_id`, `country_id`),
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
  `status` varchar(20) DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_notifications_user_read` (`user_id`, `status`)
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
  `name` varchar(100) NOT NULL,
  `coefficient` decimal(5,2) NOT NULL DEFAULT '1.00',
  `description` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
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
-- ============================================
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

ALTER TABLE `credit_transactions`
  ADD CONSTRAINT `credit_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `devices`
  ADD CONSTRAINT `devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_3` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `payment_records`
  ADD CONSTRAINT `payment_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_records_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

ALTER TABLE `service_countries`
  ADD CONSTRAINT `service_countries_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_countries_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE;

ALTER TABLE `sms_messages`
  ADD CONSTRAINT `sms_messages_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

COMMIT;
