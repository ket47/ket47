ALTER TABLE `image_list` 
ADD COLUMN `is_main` TINYINT NOT NULL DEFAULT 0 AFTER `image_order`;
