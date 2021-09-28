ALTER TABLE `store_list` 
ADD COLUMN `store_time_opens` VARCHAR(45) NULL AFTER `store_company_name`,
ADD COLUMN `store_time_closes` VARCHAR(45) NULL AFTER `store_time_opens`,
ADD COLUMN `store_time_preparation` VARCHAR(45) NULL AFTER `store_time_closes`,
ADD COLUMN `store_minimal_order` INT NULL AFTER `store_time_preparation`;
