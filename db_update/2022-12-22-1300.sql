ALTER TABLE `product_list` 
ADD COLUMN `product_parent_id` INT NULL AFTER `store_id`,
ADD COLUMN `product_net_price` DECIMAL(10,0) NULL AFTER `product_price`,
ADD COLUMN `product_option` VARCHAR(45) NULL AFTER `product_barcode`;

ALTER TABLE `product_list` 
ADD INDEX `index4` (`product_parent_id` ASC);