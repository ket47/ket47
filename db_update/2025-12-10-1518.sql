ALTER TABLE `delivery_job_list` 
ADD COLUMN `courier_name` VARCHAR(15) NULL AFTER `courier_id`,
ADD COLUMN `courier_image_hash` VARCHAR(32) NULL AFTER `courier_name`,
ADD COLUMN `job_courier_type` ENUM('auto', 'shift', 'taxi') NULL DEFAULT 'auto'  AFTER `job_name`;
