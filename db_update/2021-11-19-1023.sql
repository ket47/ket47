ALTER TABLE `store_list` 
ADD COLUMN `is_working` TINYINT NULL DEFAULT 1 AFTER `is_disabled`;
