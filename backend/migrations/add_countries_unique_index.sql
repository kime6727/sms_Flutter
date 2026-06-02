-- 为countries表添加唯一索引，避免并发插入冲突
-- 执行时间：2026-03-25

-- 添加hero_country_id唯一索引
ALTER TABLE `countries` 
ADD UNIQUE KEY `uk_hero_country_id` (`hero_country_id`);

-- 为service_countries表添加联合唯一索引
ALTER TABLE `service_countries` 
ADD UNIQUE KEY `uk_service_country` (`service_id`, `country_id`);
