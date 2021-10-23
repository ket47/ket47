CREATE TABLE `transaction_list` (
  `trans_id` INT NOT NULL,
  `trans_amount` FLOAT NULL,
  `holder` VARCHAR(45) NULL,
  `holder_id` INT NULL,
  `owner_id` INT NOT NULL DEFAULT 0,
  `owner_ally_id` VARCHAR(45) NOT NULL DEFAULT 0,
  `created_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  `updated_by` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`trans_id`));
