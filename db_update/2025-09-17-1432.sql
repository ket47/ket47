ALTER TABLE `promo_list` 
ADD COLUMN `is_summable` TINYINT NULL DEFAULT 0 AFTER `is_used`;
