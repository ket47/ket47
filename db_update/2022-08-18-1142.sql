ALTER TABLE `image_list` 
DROP INDEX `hldr` ,
ADD INDEX `hldr` (`image_holder` ASC, `image_holder_id` ASC);