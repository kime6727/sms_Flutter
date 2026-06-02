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
