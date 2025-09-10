ALTER TABLE `metric_act_list` 
CHANGE COLUMN `act_group` `act_group` ENUM('auth', 'home', 'store', 'product', 'search', 'order', 'location', 'UI') NULL DEFAULT NULL ;
