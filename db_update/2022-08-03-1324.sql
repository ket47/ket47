ALTER TABLE `product_list` 
DROP INDEX `extId` ,
ADD UNIQUE INDEX `extId` (`product_external_id` ASC, `store_id` ASC);
