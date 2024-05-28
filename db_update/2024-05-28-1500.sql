ALTER TABLE `product_list` 
CHARACTER SET = utf8mb4 ,
ADD COLUMN `is_hidden` TINYINT NOT NULL DEFAULT 0 AFTER `is_disabled`,
CHANGE COLUMN `is_counted` `is_counted` TINYINT(4) NOT NULL DEFAULT 0 ,
CHANGE COLUMN `product_name` `product_name` VARCHAR(255) NULL DEFAULT NULL ,
CHANGE COLUMN `product_name_new` `product_name_new` VARCHAR(255) NULL DEFAULT NULL ,
CHANGE COLUMN `product_description` `product_description` VARCHAR(1000) NULL DEFAULT NULL ,
CHANGE COLUMN `product_description_new` `product_description_new` VARCHAR(1000) NULL DEFAULT NULL ;
