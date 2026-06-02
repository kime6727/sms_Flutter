-- ============================================
-- 创建充值套餐表
-- 执行日期: 2026-05-16
-- 说明: price字段不需要，价格由Apple IAP返回
-- ============================================

-- 1. 创建 topup_packages 表
CREATE TABLE IF NOT EXISTS `topup_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '套餐名称',
  `name_en` varchar(100) DEFAULT NULL COMMENT '套餐名称(英文)',
  `name_cn` varchar(100) DEFAULT NULL COMMENT '套餐名称(中文)',
  `description` text COMMENT '套餐描述',
  `points` int(11) NOT NULL COMMENT '获得积分数量',
  `product_id` varchar(100) NOT NULL COMMENT 'Apple IAP 消耗型产品ID',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `is_recommended` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否推荐',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '显示顺序',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_product_id` (`product_id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值套餐表';

-- 2. 插入初始充值套餐数据
-- 注意：price由Apple返回，此处不配置
-- product_id需要与AppStore Connect中创建的产品ID一致
INSERT INTO `topup_packages` (`name`, `name_en`, `name_cn`, `description`, `points`, `product_id`, `is_active`, `is_recommended`, `sort_order`) VALUES
('小额充值', 'Small Top-up', '小额充值', '适合偶尔使用的用户', 50, 'com.niceapps.sms.topup50', 1, 0, 1),
('标准套餐', 'Standard Package', '标准套餐', '适合轻度使用的用户', 100, 'com.niceapps.sms.topup100', 1, 0, 2),
('热门套餐', 'Popular Package', '热门套餐', '最受欢迎的选择', 300, 'com.niceapps.sms.topup300', 1, 1, 3),
('超值套餐', 'Value Package', '超值套餐', '性价比更高的选择', 500, 'com.niceapps.sms.topup500', 1, 0, 4),
('高级套餐', 'Premium Package', '高级套餐', '适合频繁使用的用户', 1000, 'com.niceapps.sms.topup1000', 1, 0, 5),
('豪华套餐', 'Deluxe Package', '豪华套餐', '适合重度使用的用户', 2000, 'com.niceapps.sms.topup2000', 1, 0, 6);

-- ============================================
-- 迁移完成
-- ============================================
SELECT '充值套餐表创建完成！' AS status;
