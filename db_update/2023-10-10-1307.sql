ALTER TABLE `order_list` 
ADD COLUMN `is_shipping` TINYINT NULL DEFAULT 0 AFTER `order_stock_status`;
