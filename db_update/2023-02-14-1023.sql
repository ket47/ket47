ALTER TABLE `transaction_account_list` 
ADD INDEX `transgtype` (`group_type` ASC);
ALTER TABLE `transaction_account_list` ALTER INDEX `transaccunq`;
