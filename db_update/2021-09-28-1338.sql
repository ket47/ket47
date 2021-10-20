ALTER TABLE `store_list` 
ADD COLUMN `store_email` VARCHAR(45) NULL AFTER `store_name`,
ADD COLUMN `store_company_name` VARCHAR(45) NULL AFTER `store_email`;
