ALTER TABLE `product_list` 
ADD COLUMN `product_quantity_min` FLOAT NULL DEFAULT 1 AFTER `product_quantity`,
ADD COLUMN `product_unit` VARCHAR(5) NULL DEFAULT 'шт' AFTER `product_quantity_min`;
