ALTER TABLE `image_list` 
ADD COLUMN `image_order` INT NULL AFTER `image_hash`;



ALTER TABLE `store_list` 
ADD COLUMN `store_tax_num` BIGINT(14) NULL AFTER `store_email`,
ADD COLUMN `store_company_name` VARCHAR(45) NULL AFTER `store_tax_num`;


ALTER TABLE `product_list` 
CHANGE COLUMN `product_description` `product_description` VARCHAR(1000) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL ;


ALTER TABLE `product_list` 
DROP COLUMN `product_img`,
ADD COLUMN `product_name_new` VARCHAR(255) NULL AFTER `product_name`,
ADD COLUMN `product_description_new` VARCHAR(1000) NULL AFTER `product_description`;
