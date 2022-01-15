ALTER TABLE `order_entry_list` 
DROP FOREIGN KEY `entrorderid`;
ALTER TABLE `order_entry_list` 
ADD CONSTRAINT `entrorderid`
  FOREIGN KEY (`order_id`)
  REFERENCES `order_list` (`order_id`)
  ON DELETE CASCADE;


ALTER TABLE `order_group_member_list` 
DROP FOREIGN KEY `orderId`;
ALTER TABLE `order_group_member_list` 
ADD CONSTRAINT `orderId`
  FOREIGN KEY (`member_id`)
  REFERENCES `order_list` (`order_id`)
  ON DELETE CASCADE;
