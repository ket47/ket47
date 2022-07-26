ALTER TABLE `transaction_list` 
ADD COLUMN `trans_tags` VARCHAR(1000) NULL AFTER `trans_holder_id`,
ADD FULLTEXT INDEX `ttags` (`trans_tags`) VISIBLE;
