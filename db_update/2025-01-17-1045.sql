ALTER TABLE `post_list` 
ADD COLUMN `is_published` TINYINT NOT NULL DEFAULT 0 AFTER `is_promoted`;
