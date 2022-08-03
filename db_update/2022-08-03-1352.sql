ALTER TABLE `token_list` 
ADD INDEX `thash` (`token_holder` ASC, `token_hash` ASC);
;
