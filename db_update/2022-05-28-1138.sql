ALTER TABLE `order_list` 
ADD COLUMN `order_stock_status` VARCHAR(10) NULL AFTER `order_objection`,
ADD INDEX `stock_status` (`order_stock_status` ASC);
;
