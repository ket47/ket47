ALTER TABLE `delivery_job_list` 
ADD COLUMN `start_address` VARCHAR(500) NULL AFTER `start_color`,
ADD COLUMN `finish_address` VARCHAR(500) NULL AFTER `finish_color`;
