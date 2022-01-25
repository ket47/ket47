ALTER TABLE `order_group_member_list` 
ADD COLUMN `link_id` INT NOT NULL AUTO_INCREMENT,
DROP PRIMARY KEY,
ADD PRIMARY KEY (`link_id`),
ADD UNIQUE INDEX `unq` (`member_id` ASC, `group_id` ASC);
