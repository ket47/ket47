ALTER TABLE `order_list` 
CHANGE COLUMN `order_stock_status` `order_stock_status` ENUM('reserved', 'commited') CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL ;
