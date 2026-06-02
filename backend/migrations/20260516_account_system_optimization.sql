-- ============================================
-- 账户系统优化迁移脚本
-- 适用于 MySQL 5.7.44
-- 执行日期: 2026-05-16
-- ============================================

-- 1. 添加 Google/Apple 登录支持字段
ALTER TABLE users 
  ADD COLUMN IF NOT EXISTS google_id varchar(255) DEFAULT NULL COMMENT 'Google账号ID' AFTER email,
  ADD COLUMN IF NOT EXISTS apple_id varchar(255) DEFAULT NULL COMMENT 'Apple账号ID' AFTER google_id;

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
INSERT IGNORE INTO system_settings (`key`, value, type, description, created_at) VALUES
('register_bonus_min', '5', 'string', '注册赠送积分最小值', NOW()),
('register_bonus_max', '10', 'string', '注册赠送积分最大值', NOW());

-- ============================================
-- 迁移完成
-- ============================================
SELECT '账户系统优化迁移完成！' AS status;
