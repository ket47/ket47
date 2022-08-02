ALTER TABLE `imported_list` 
ADD COLUMN `target_external_id` VARCHAR(45) NULL AFTER `target_id`,
ADD INDEX `hldr` (`holder` ASC, `holder_id` ASC);
