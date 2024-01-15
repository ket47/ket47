ALTER TABLE `courier_shift_list` 
ADD COLUMN `actual_longitude` FLOAT NULL AFTER `total_bonus`,
ADD COLUMN `actual_latitude` FLOAT NULL AFTER `actual_longitude`,
ADD COLUMN `last_longitude` FLOAT NULL AFTER `actual_latitude`,
ADD COLUMN `last_latitude` FLOAT NULL AFTER `last_longitude`,
ADD COLUMN `last_finish_plan` BIGINT NULL AFTER `last_latitude`,

ADD COLUMN `courier_reach` INT NULL AFTER `courier_id`,
ADD COLUMN `courier_speed` FLOAT NULL AFTER `courier_reach`,
CHANGE COLUMN `courier_id` `courier_id` INT NULL DEFAULT NULL AFTER `shift_id`;

