ALTER TABLE `product_list` 
ADD FULLTEXT INDEX `textsearch` (`product_description`, `product_name`);
