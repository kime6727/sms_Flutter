-- SMS 接码平台 - 增量更新脚本
-- 添加发布相关字段

USE newsms;

-- 1. 给services表添加上架字段
ALTER TABLE services ADD COLUMN is_published TINYINT DEFAULT 0 AFTER active;

-- 2. 给service_countries表添加上架字段和自动选择标记
ALTER TABLE service_countries ADD COLUMN is_published TINYINT DEFAULT 0 AFTER price;
ALTER TABLE service_countries ADD COLUMN is_auto TINYINT DEFAULT 0 AFTER is_published;

-- 3. 创建服务同步历史记录表（可选，用于追踪同步）
CREATE TABLE IF NOT EXISTS sync_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sync_type VARCHAR(50) NOT NULL COMMENT 'services/countries/prices',
    synced_count INT DEFAULT 0,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
