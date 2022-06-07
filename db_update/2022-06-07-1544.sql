update order_list set order_sum_tax=0,order_sum_promo=coalesce(order_sum_promo,0),order_sum_delivery=coalesce(order_sum_delivery,0);
ALTER TABLE `order_list` 
ADD COLUMN `order_sum_product` FLOAT NOT NULL DEFAULT 0 AFTER `order_finish_location_id`,
CHANGE COLUMN `order_sum_tax` `order_sum_tax` FLOAT NOT NULL DEFAULT 0 ,
CHANGE COLUMN `order_sum_delivery` `order_sum_delivery` FLOAT NOT NULL DEFAULT 0 ,
CHANGE COLUMN `order_sum_promo` `order_sum_promo` FLOAT NOT NULL DEFAULT 0 ,
CHANGE COLUMN `order_sum_total` `order_sum_total` FLOAT GENERATED ALWAYS AS (order_sum_product+order_sum_tax+order_sum_delivery-order_sum_promo) STORED ;