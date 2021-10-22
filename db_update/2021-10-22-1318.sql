ALTER TABLE `product_group_member_list` 
ADD COLUMN `created_at` DATETIME NULL AFTER `group_id`;

ALTER TABLE `store_group_member_list` 
ADD COLUMN `created_at` DATETIME NULL AFTER `group_id`;

ALTER TABLE `user_group_member_list` 
ADD COLUMN `created_at` DATETIME NULL AFTER `group_id`;