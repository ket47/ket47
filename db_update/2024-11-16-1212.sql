ALTER TABLE `product_list` 
DROP INDEX `textsearch` ,
ADD FULLTEXT INDEX `textsearch_name` (`product_name`);
;
