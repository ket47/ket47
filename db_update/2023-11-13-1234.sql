ALTER TABLE `order_list` 
CHANGE COLUMN `is_shipping` `is_shipment` TINYINT NULL DEFAULT '0' ;


ALTER TABLE `tariff_list` 
CHANGE COLUMN `is_shipping` `is_shipment` TINYINT NULL DEFAULT '0' ;
