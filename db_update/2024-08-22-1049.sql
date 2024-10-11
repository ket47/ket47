ALTER TABLE `order_list` 
ADD COLUMN `order_script` ENUM('order_delivery', 'order_supplier', 'shipment') NULL DEFAULT 'order_delivery' AFTER `order_status`;



UPDATE order_list SET order_script=IF(is_shipment=1,'shipment',IF(order_data->>'$.delivery_by_store' OR order_data->>'$.pickup_by_customer' ,'order_supplier','order_delivery'));
