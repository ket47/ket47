ALTER TABLE `user_list` 
ADD COLUMN `user_birthday` DATETIME NULL AFTER `user_avatar_name`,
CHANGE COLUMN `owner_id` `owner_id` INT NOT NULL AFTER `is_disabled`,
CHANGE COLUMN `owner_ally_ids` `owner_ally_ids` VARCHAR(45) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NOT NULL AFTER `owner_id`;
