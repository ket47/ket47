ALTER TABLE `product_group_list` 
ADD COLUMN `group_description` VARCHAR(500) NULL AFTER `group_path`,
ADD FULLTEXT INDEX `searchdescr` (`group_description`);