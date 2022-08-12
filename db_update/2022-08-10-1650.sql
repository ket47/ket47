ALTER TABLE `order_list` 
ADD COLUMN `order_sum_fixed` FLOAT NULL DEFAULT 0 AFTER `order_finish_location_id`;
