ALTER TABLE `order_entry_list` 
CHARACTER SET = utf8mb4 , COLLATE = utf8mb4_bin ;
ALTER TABLE `order_entry_list` 
CHANGE COLUMN `entry_text` `entry_text` VARCHAR(400) NULL DEFAULT NULL ,
CHANGE COLUMN `entry_comment` `entry_comment` VARCHAR(45) NULL DEFAULT NULL ;
