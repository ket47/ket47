ALTER TABLE `order_list` 
DROP COLUMN `order_courier_user_id`,
DROP COLUMN `order_customer_id`,
ADD COLUMN `order_courier_id` INT NULL AFTER `order_store_id`,
ADD COLUMN `order_start_location_id` INT NULL AFTER `order_courier_id`,
ADD COLUMN `order_finish_location_id` INT NULL AFTER `order_start_location_id`,
CHANGE COLUMN `order_description` `order_description` VARCHAR(200) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL AFTER `order_sum_total`;
