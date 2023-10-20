ALTER TABLE `token_list` 
ADD COLUMN `token_device` VARCHAR(150) NULL AFTER `token_hash`,
ADD UNIQUE INDEX `tuniq` (`token_hash` ASC);
ALTER TABLE `token_list` ALTER INDEX `thash`;
