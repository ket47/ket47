ALTER TABLE `transaction_list` 
CHANGE COLUMN `holder` `trans_holder` VARCHAR(20) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL ,
CHANGE COLUMN `holder_id` `trans_holder_id` INT NULL DEFAULT NULL ,
ADD INDEX `thold` (`trans_holder` ASC, `trans_holder_id` ASC);
