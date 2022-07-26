ALTER TABLE `product_list` 
ADD COLUMN `product_external_id` VARCHAR(45) NULL AFTER `store_id`,
ADD INDEX `extId` (`product_external_id` ASC);
;
