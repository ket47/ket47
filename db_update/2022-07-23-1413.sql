ALTER TABLE `transaction_list` 
DROP COLUMN `acc_credit_code`,
DROP COLUMN `acc_debit_code`,
ADD COLUMN `trans_role` VARCHAR(100) NULL AFTER `trans_data`,
ADD INDEX `trole` (`trans_role` ASC),
DROP INDEX `index3` ,
DROP INDEX `index2` ;
