ALTER TABLE `product_list` 
ADD COLUMN `product_promo_price` FLOAT NULL AFTER `product_quantity`,
ADD COLUMN `product_promo_start` DATETIME NULL AFTER `product_promo_price`,
ADD COLUMN `product_promo_finish` DATETIME NULL AFTER `product_promo_start`;
