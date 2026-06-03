-- Migration: add related_order_id to notifications + fix body field
-- Date: 2026-06-02
-- Reason: cron/check-timeout.php and webhook.php both insert related_order_id
-- but the actual notifications table only has data JSON, not this column.
-- Adding the column for proper referential integrity.

ALTER TABLE `notifications`
  ADD COLUMN IF NOT EXISTS `related_order_id` varchar(36) DEFAULT NULL AFTER `data`,
  ADD KEY IF NOT EXISTS `idx_related_order_id` (`related_order_id`);

-- Update cron_expire_orders.php to use 'body' instead of 'content' (matches schema)
-- Done in code: see /backend/cron_expire_orders.php

-- Update webhook.php to use related_order_id column directly (instead of JSON)
-- Done in code: see /backend/webhook.php
