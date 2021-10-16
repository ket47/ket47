ALTER TABLE `product_group_list` 
ADD COLUMN `is_disabled` TINYINT NULL DEFAULT 0 AFTER `owner_ally_ids`,
ADD COLUMN `updated_at` DATETIME NULL AFTER `is_disabled`,
ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`;

ALTER TABLE `store_group_list` 
ADD COLUMN `is_disabled` TINYINT NULL DEFAULT 0 AFTER `owner_ally_ids`,
ADD COLUMN `updated_at` DATETIME NULL AFTER `is_disabled`,
ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`;

ALTER TABLE `user_group_list` 
ADD COLUMN `is_disabled` TINYINT NULL DEFAULT 0 AFTER `owner_ally_ids`,
ADD COLUMN `updated_at` DATETIME NULL AFTER `is_disabled`,
ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`;
