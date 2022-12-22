CREATE TABLE `user_card_list` (
  `card_id` int NOT NULL AUTO_INCREMENT,
  `card_type` varchar(10) DEFAULT NULL,
  `card_mask` varchar(45) DEFAULT NULL,
  `card_acquirer` varchar(45) DEFAULT NULL,
  `card_remote_id` int DEFAULT NULL,
  `is_main` tinyint DEFAULT NULL,
  `is_disabled` tinyint DEFAULT NULL,
  `owner_id` int DEFAULT NULL,
  `owner_ally_ids` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
