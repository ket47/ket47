ALTER TABLE `store_list` 
DROP COLUMN `store_address`,
ADD COLUMN `validity` TINYINT NULL AFTER `is_primary`;