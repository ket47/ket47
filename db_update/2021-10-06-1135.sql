ALTER TABLE `imported_list` 
ADD COLUMN `is_disabled` TINYINT NOT NULL DEFAULT 0 AFTER `holder_id`,
ADD COLUMN `deleted_at` DATETIME NULL AFTER `updated_at`;
