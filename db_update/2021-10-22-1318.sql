ALTER TABLE `product_group_member_list` 
ADD COLUMN `created_at` DATETIME NULL AFTER `group_id`,
ADD COLUMN `update_at` DATETIME NULL AFTER `created_at`;

ALTER TABLE `store_group_member_list` 
ADD COLUMN `created_at` DATETIME NULL AFTER `group_id`,
ADD COLUMN `update_at` DATETIME NULL AFTER `created_at`;

ALTER TABLE `user_group_member_list` 
ADD COLUMN `created_at` DATETIME NULL AFTER `group_id`,
ADD COLUMN `update_at` DATETIME NULL AFTER `created_at`;