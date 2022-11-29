ALTER TABLE `store_list` 
DROP COLUMN `store_commission`,
ADD COLUMN `store_delivery_allow` TINYINT NULL AFTER `store_minimal_order`,
ADD COLUMN `store_delivery_cost` INT NULL AFTER `store_delivery_allow`,
ADD COLUMN `store_pickup_allow` TINYINT NULL AFTER `store_delivery_cost`;
