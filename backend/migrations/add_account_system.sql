-- 添加账号密码系统字段
-- 执行时间：2026-03-27

-- 1. 添加字段
ALTER TABLE users ADD COLUMN username VARCHAR(50) UNIQUE COMMENT '用户账号' AFTER id;
ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) COMMENT '密码哈希' AFTER username;
ALTER TABLE users ADD COLUMN email VARCHAR(255) UNIQUE COMMENT '绑定邮箱' AFTER password_hash;

-- 2. 添加索引
CREATE INDEX idx_username ON users(username);
CREATE INDEX idx_email ON users(email);
