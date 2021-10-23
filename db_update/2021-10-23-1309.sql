ALTER TABLE `order_group_member_list` 
ADD COLUMN `created_by` INT NULL AFTER `created_at`,
ADD INDEX `orderCreatedBy_idx` (`created_by` ASC);
;
ALTER TABLE `order_group_member_list` 
ADD CONSTRAINT `orderCreatedBy`
  FOREIGN KEY (`created_by`)
  REFERENCES `tezkel_db`.`user_list` (`user_id`)
  ON DELETE NO ACTION
  ON UPDATE NO ACTION;
