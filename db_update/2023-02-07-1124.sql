CREATE TABLE `metric_list` (
  `metric_id` INT NOT NULL AUTO_INCREMENT,
  `come_referrer` VARCHAR(120) NULL,
  `come_url` VARCHAR(120) NULL,
  `come_media_id` VARCHAR(45) NULL,
  `come_inviter_id` INT NULL,
  `user_id` INT NULL,
  `device_is_mobile` TINYINT NULL,
  `device_platform` VARCHAR(45) NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`metric_id`))
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_unicode_ci;