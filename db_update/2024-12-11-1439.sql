ALTER TABLE `post_list` 
ADD COLUMN `is_promoted` TINYINT NOT NULL DEFAULT 0 AFTER `is_disabled`;