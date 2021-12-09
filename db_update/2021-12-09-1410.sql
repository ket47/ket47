ALTER TABLE `courier_group_member_list` 
DROP FOREIGN KEY `courGroup`,
DROP FOREIGN KEY `courID`;
ALTER TABLE `courier_group_member_list` 
ADD CONSTRAINT `courGroup`
  FOREIGN KEY (`group_id`)
  REFERENCES `tezkel_db`.`courier_group_list` (`group_id`)
  ON DELETE CASCADE
  ON UPDATE CASCADE,
ADD CONSTRAINT `courID`
  FOREIGN KEY (`member_id`)
  REFERENCES `tezkel_db`.`courier_list` (`courier_id`)
  ON DELETE CASCADE
  ON UPDATE CASCADE;
ALTER TABLE `courier_list` 
CHANGE COLUMN `owner_ally_ids` `owner_ally_ids` VARCHAR(45) NOT NULL DEFAULT 0 ;
