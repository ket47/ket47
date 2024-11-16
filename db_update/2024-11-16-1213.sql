ALTER TABLE `product_list` 
ADD FULLTEXT INDEX `textsearch_descr` (`product_description`);
;
