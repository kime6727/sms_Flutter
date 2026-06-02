-- 性能优化索引
-- 执行时间: 2026-05-11
-- 说明: 添加关键查询索引以提升API性能

-- 订单查询优化
CREATE INDEX IF NOT EXISTS idx_orders_user_status ON orders(user_id, status);
CREATE INDEX IF NOT EXISTS idx_orders_status_expires ON orders(status, expires_at);
CREATE INDEX IF NOT EXISTS idx_orders_hero_order ON orders(hero_order_id);
CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at DESC);

-- 服务国家关联优化
CREATE INDEX IF NOT EXISTS idx_sc_service_country ON service_countries(service_id, country_id);
CREATE INDEX IF NOT EXISTS idx_sc_published ON service_countries(is_published, active);
CREATE INDEX IF NOT EXISTS idx_sc_service_published ON service_countries(service_id, is_published, active);

-- 通知查询优化
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, read_at);
CREATE INDEX IF NOT EXISTS idx_notifications_created ON notifications(created_at DESC);

-- 支付记录优化
CREATE INDEX IF NOT EXISTS idx_payment_transaction ON payment_records(transaction_id);
CREATE INDEX IF NOT EXISTS idx_payment_user ON payment_records(user_id, created_at DESC);

-- 用户查询优化
CREATE INDEX IF NOT EXISTS idx_users_device ON users(device_id);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- 积分流水优化
CREATE INDEX IF NOT EXISTS idx_credit_user_created ON credit_transactions(user_id, created_at DESC);

-- 短信消息优化
CREATE INDEX IF NOT EXISTS idx_sms_order ON sms_messages(order_id, received_at DESC);

-- 服务优化
CREATE INDEX IF NOT EXISTS idx_services_published ON services(is_published, active, sort_order);
CREATE INDEX IF NOT EXISTS idx_services_code ON services(code);

-- 国家优化
CREATE INDEX IF NOT EXISTS idx_countries_active ON countries(active);
CREATE INDEX IF NOT EXISTS idx_countries_hero_id ON countries(hero_country_id);

-- 验证索引创建
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'newsms'
AND INDEX_NAME LIKE 'idx_%'
ORDER BY TABLE_NAME, INDEX_NAME;
