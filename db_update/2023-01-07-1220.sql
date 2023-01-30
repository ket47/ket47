ALTER TABLE `transaction_list` 
ADD COLUMN `trans_date` DATETIME NULL AFTER `trans_id`;


ALTER TABLE `transaction_list` 
ADD COLUMN `created_by` INT NULL AFTER `created_at`;
