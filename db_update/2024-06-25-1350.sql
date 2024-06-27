CREATE TABLE `perk_list` (
  `perk_id` INT NOT NULL AUTO_INCREMENT,
  `perk_holder` VARCHAR(45) NULL,
  `perk_holder_id` INT NULL,
  `perk_type` VARCHAR(45) NULL,
  `perk_value` varchar(45) NULL,
  `expired_at` DATETIME NULL,
  PRIMARY KEY (`perk_id`),
  KEY `pholder` (`perk_holder`,`perk_holder_id`),
  KEY `ptype` (`perk_type`)

);