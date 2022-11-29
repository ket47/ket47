ALTER TABLE `order_list` 
ADD COLUMN `order_tariff` JSON NULL AFTER `order_finish_location_id`;