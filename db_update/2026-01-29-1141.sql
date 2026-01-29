ALTER TABLE `courier_list` 
ADD COLUMN `courier_bank_account` VARCHAR(45) NULL AFTER `courier_tax_num`,
ADD COLUMN `courier_bank_id` VARCHAR(45) NULL AFTER `courier_bank_account`,
ADD COLUMN `courier_full_name` VARCHAR(45) NULL AFTER `courier_tax_num`,
ADD COLUMN `courier_bank_assignment` VARCHAR(200) NULL AFTER `courier_bank_id`;



ALTER TABLE `courier_list` 
DROP COLUMN `current_order_id`;
