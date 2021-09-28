ALTER TABLE `product_list` 
DROP COLUMN `product_img`,
ADD COLUMN `product_name_new` VARCHAR(255) NULL AFTER `product_name`,
ADD COLUMN `product_description_new` VARCHAR(1000) NULL AFTER `product_description`;
