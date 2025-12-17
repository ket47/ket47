ALTER TABLE `delivery_job_list` 
ADD COLUMN `expired_at` DATETIME NULL AFTER `created_at`,

ADD COLUMN `notify_at` DATETIME NULL AFTER `expired_at`;
