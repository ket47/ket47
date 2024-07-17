ALTER TABLE `user_verification_list` 
ADD COLUMN `verification_target` VARCHAR(45) NULL AFTER `verification_type`,
ADD COLUMN `expired_at` DATETIME NULL AFTER `updated_at`;