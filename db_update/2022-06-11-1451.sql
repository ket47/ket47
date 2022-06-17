CREATE TABLE `message_sub_list` (
  `sub_id` INT NOT NULL AUTO_INCREMENT,
  `sub_user_id` INT NULL,
  `sub_registration_id` VARCHAR(200) NULL,
  `sub_device` VARCHAR(200) NULL,
  `sub_type` VARCHAR(45) NULL,
  `created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sub_id`),
  UNIQUE KEY `registrunique` (`sub_registration_id`)
  )ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;;
