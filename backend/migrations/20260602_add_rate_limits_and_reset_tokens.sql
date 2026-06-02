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
