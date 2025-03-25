ALTER TABLE `user_verification_list` 
ADD COLUMN `is_verified` TINYINT NULL COMMENT 'true if user entered right code' AFTER `expired_at`;
