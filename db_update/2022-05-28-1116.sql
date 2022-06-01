ALTER TABLE `product_list` 
ADD COLUMN `product_quantity_reserve` FLOAT NULL AFTER `product_quantity_min`,
ADD COLUMN `product_quantity_updated_at` DATETIME NULL AFTER `product_quantity_reserve`,
CHANGE COLUMN `product_unit` `product_unit` VARCHAR(5) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT 'шт' AFTER `product_description_new`,
CHANGE COLUMN `product_price` `product_price` FLOAT NULL DEFAULT NULL AFTER `product_quantity_updated_at`;
