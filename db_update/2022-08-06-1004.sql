ALTER TABLE `transaction_list` 
ADD COLUMN `trans_credit` VARCHAR(45) NULL AFTER `trans_debit`;

ALTER TABLE `transaction_list` 
ADD INDEX `tdebit` (`trans_debit` ASC),
ADD INDEX `tcredit` (`trans_credit` ASC);
ALTER TABLE `transaction_list` ALTER INDEX `thold`;

