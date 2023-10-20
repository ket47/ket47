ALTER TABLE `tariff_list` 
ADD COLUMN `credit_allow` TINYINT NOT NULL DEFAULT 0 AFTER `cash_fee`,
ADD COLUMN `credit_fee` TINYINT NOT NULL DEFAULT 0 AFTER `credit_allow`,
ADD COLUMN `is_shipping` TINYINT NULL DEFAULT 0 AFTER `is_disabled`,
CHANGE COLUMN `delivery_allow` `delivery_allow` TINYINT NOT NULL DEFAULT 0 AFTER `order_cost`,
CHANGE COLUMN `delivery_fee` `delivery_fee` TINYINT NOT NULL DEFAULT '0' AFTER `delivery_allow`,
CHANGE COLUMN `delivery_cost` `delivery_cost` SMALLINT NOT NULL DEFAULT '0' AFTER `delivery_fee`,
CHANGE COLUMN `cash_allow` `cash_allow` TINYINT NOT NULL DEFAULT '0' ;