-- 性能索引优化脚本
-- 执行时间: 2026-05-16
-- 说明: 添加5个缺失的性能索引以提升查询效率

-- 1. 订单表: 用户ID + 状态联合索引 (加速用户订单列表查询)
CREATE INDEX IF NOT EXISTS idx_orders_user_status ON orders(user_id, status);

-- 2. 订单表: 状态 + 过期时间联合索引 (加速过期订单清理查询)
CREATE INDEX IF NOT EXISTS idx_orders_status_expires ON orders(status, expires_at);

-- 3. 服务国家关联表: 服务ID + 国家ID联合索引 (加速价格查询)
-- 注意: 已有 unique_service_country 唯一索引，但这是普通联合索引用于查询优化
CREATE INDEX IF NOT EXISTS idx_sc_service_country ON service_countries(service_id, country_id);

-- 4. 通知表: 用户ID + 已读状态联合索引 (加速通知列表查询)
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, is_read);

-- 5. 支付记录表: 交易号索引 (加速支付回调查询)
CREATE INDEX IF NOT EXISTS idx_payment_transaction ON payment_records(transaction_id);
