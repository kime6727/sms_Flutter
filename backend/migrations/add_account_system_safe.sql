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
