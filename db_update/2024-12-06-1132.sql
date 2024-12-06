ALTER TABLE `post_list` 
ADD COLUMN `post_holder` VARCHAR(45) NULL AFTER `post_route`,
ADD COLUMN `post_holder_id` INT NULL AFTER `post_holder`,
CHANGE COLUMN `post_type` `post_type` ENUM('homeslide', 'wellcomeslide', 'story') NULL DEFAULT NULL ,
ADD INDEX `pholder` (`post_holder` ASC, `post_holder_id` ASC);
;
