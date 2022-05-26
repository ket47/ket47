ALTER TABLE `order_list` 
ADD COLUMN `order_objection` VARCHAR(200) NULL AFTER `order_description`;
ALTER TABLE `order_list` 
ADD COLUMN `order_sum_promo` FLOAT NULL AFTER `order_sum_delivery`;
