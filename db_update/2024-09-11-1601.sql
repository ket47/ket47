ALTER TABLE `mailing_list` 
ADD COLUMN `regular_group` VARCHAR(45) NULL DEFAULT '0' AFTER `is_started`;