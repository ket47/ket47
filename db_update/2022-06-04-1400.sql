CREATE TABLE `promo_list` (
  `promo_id` INT NOT NULL AUTO_INCREMENT,
  `promo_name` VARCHAR(45) NULL,
  `promo_order_id` INT NULL,
  `promo_activator_id` INT NULL,
  `owner_id` INT NULL,
  `owner_ally_ids` VARCHAR(45) NULL,
  `is_disabled` TINYINT NULL DEFAULT 0,
  `is_used` TINYINT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `expired_at` DATETIME NULL,
  PRIMARY KEY (`promo_id`));
