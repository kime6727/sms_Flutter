-- ============================================
-- 数据库迁移脚本 - 添加 total_cost 字段到 orders 表
-- 适用于已部署的 MySQL 5.7.44 数据库
-- 执行日期: 2026-05-16
-- ============================================

-- 检查 total_cost 字段是否存在，如果不存在则添加
SET @dbname = DATABASE();
SET @tablename = 'orders';
SET @columnname = 'total_cost';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN `', @columnname, '` decimal(10,2) DEFAULT ''0.00'' AFTER `price`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 为现有订单计算 total_cost（如果 price 有值但 total_cost 为空）
UPDATE orders SET total_cost = price * quantity WHERE total_cost = 0 OR total_cost IS NULL;

-- 验证结果
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'orders' 
  AND COLUMN_NAME = 'total_cost';

SELECT 'Migration completed successfully!' AS status;
