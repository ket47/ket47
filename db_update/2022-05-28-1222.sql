ALTER TABLE `product_list` 
CHANGE COLUMN `product_quantity_updated_at` `product_quantity_expire_at` DATETIME NULL DEFAULT NULL ;
ALTER TABLE `product_list` 
CHANGE COLUMN `product_quantity_reserve` `product_quantity_reserved` FLOAT NULL DEFAULT NULL ;

