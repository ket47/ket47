ALTER TABLE `imported_list` 
ADD COLUMN `target` VARCHAR(45) NULL AFTER `holder_id`,
ADD COLUMN `target_id` INT NULL AFTER `target`;
