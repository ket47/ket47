ALTER TABLE `order_list` 
ADD COLUMN `order_group_id` INT NULL AFTER `order_id`;



ALTER TABLE `order_list` 
CHANGE COLUMN `order_decsription` `order_description` VARCHAR(200) NULL DEFAULT NULL ;