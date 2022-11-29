ALTER TABLE `order_group_member_list` 
DROP FOREIGN KEY `orderCreatedBy`;
ALTER TABLE `order_group_member_list` 
ADD CONSTRAINT `orderCreatedBy`
  FOREIGN KEY (`created_by`)
  REFERENCES `user_list` (`user_id`)
  ON DELETE SET NULL;
