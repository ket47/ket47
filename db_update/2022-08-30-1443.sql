ALTER TABLE `product_list` 
CHANGE COLUMN `product_price` `product_price` DECIMAL(10,0) NULL DEFAULT NULL ,
CHANGE COLUMN `product_promo_price` `product_promo_price` DECIMAL(10,0) NULL DEFAULT NULL ;
