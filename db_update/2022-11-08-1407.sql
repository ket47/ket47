ALTER TABLE `order_list` 
CHANGE COLUMN `order_sum_total` `order_sum_total` FLOAT GENERATED ALWAYS AS (((`order_sum_product`+ `order_sum_delivery`) - `order_sum_promo`)) STORED ;

ALTER TABLE `order_list` 
DROP COLUMN `order_sum_tax`,
DROP COLUMN `order_sum_fixed`,
ADD COLUMN `order_data` JSON NULL AFTER `order_tariff`;
