ALTER TABLE `imported_list` 
ADD COLUMN `holder_data_hash` VARCHAR(32) NULL AFTER `holder_id`,
DROP INDEX `hldr` ,
ADD UNIQUE INDEX `hldr_data` (`holder` ASC, `holder_id` ASC, `holder_data_hash` ASC);
;