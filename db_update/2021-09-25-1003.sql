ALTER TABLE `image_list` 
ADD COLUMN `image_order` INT NULL AFTER `image_hash`;



ALTER TABLE `store_list` 
ADD COLUMN `store_tax_num` BIGINT(14) NULL AFTER `store_email`,
ADD COLUMN `store_company_name` VARCHAR(45) NULL AFTER `store_tax_num`;
