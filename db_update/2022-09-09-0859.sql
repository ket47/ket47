ALTER TABLE `order_list` 
ADD COLUMN `order_store_admins` VARCHAR(45) NULL AFTER `order_store_id`,
ADD COLUMN `order_courier_admins` VARCHAR(45) NULL AFTER `order_courier_id`;
