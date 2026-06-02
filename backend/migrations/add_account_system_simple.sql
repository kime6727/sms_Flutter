-- 添加账号密码系统字段（简单版本）
-- 执行时间：2026-03-27
-- 如果字段已存在会报错，可以忽略

-- 1. 添加 username 字段
ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL DEFAULT NULL AFTER id;

-- 2. 添加 password_hash 字段
ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL DEFAULT NULL AFTER username;

-- 3. 添加 email 字段
ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL DEFAULT NULL AFTER password_hash;

-- 4. 添加唯一索引（如果字段有数据，先不加 UNIQUE）
ALTER TABLE users ADD UNIQUE KEY unique_username (username);
ALTER TABLE users ADD UNIQUE KEY unique_email (email);

-- 5. 添加普通索引
CREATE INDEX idx_username ON users(username);
CREATE INDEX idx_email ON users(email);
