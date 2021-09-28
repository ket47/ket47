ALTER TABLE `store_list` 
ADD COLUMN `store_name_new` VARCHAR(45) NULL AFTER `store_name`,
ADD COLUMN `store_description_new` VARCHAR(1000) NULL AFTER `store_description`,
CHANGE COLUMN `store_description` `store_description` VARCHAR(1000) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL ;

