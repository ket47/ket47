ALTER TABLE `order_list` 
DROP FOREIGN KEY `modifiedby`;
ALTER TABLE `order_list` 
CHANGE COLUMN `modified_by` `updated_by` INT NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `order_list` 
ADD CONSTRAINT `modifiedby`
  FOREIGN KEY (`updated_by`)
  REFERENCES `user_list` (`user_id`);



ALTER TABLE `order_list` 
CHANGE COLUMN `order_id` `order_id` INT NOT NULL AUTO_INCREMENT ;
