ALTER TABLE `product_list` 
ADD FULLTEXT INDEX `textsearch` (`product_description`, `product_name`);
ALTER TABLE `product_list` ALTER INDEX `extId`;
