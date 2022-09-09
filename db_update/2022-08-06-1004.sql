ALTER TABLE `transaction_list` 
ADD INDEX `tdebit` (`trans_debit` ASC),
ADD INDEX `tcredit` (`trans_credit` ASC);
ALTER TABLE `transaction_list` ALTER INDEX `thold`;

