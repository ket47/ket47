ALTER TABLE `order_list` 
ADD COLUMN `order_status` ENUM('started', 'finished', 'canceled') GENERATED ALWAYS AS (IF(order_data->>'$.order_is_canceled','canceled',IF(order_group_id=56,'finished','started'))) VIRTUAL AFTER `order_stock_status`;


ALTER TABLE `order_list` 
ADD INDEX `order_status` (`order_status` ASC) VISIBLE;
