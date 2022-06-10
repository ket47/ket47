DROP TABLE  IF EXISTS `promo_list`;

CREATE TABLE `promo_list` (
  `promo_id` int NOT NULL AUTO_INCREMENT,
  `promo_activator_id` int DEFAULT NULL,
  `promo_order_id` int DEFAULT NULL,
  `promo_name` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `promo_value` float DEFAULT NULL,
  `owner_id` int DEFAULT NULL,
  `owner_ally_ids` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `is_disabled` tinyint DEFAULT '1',
  `is_used` tinyint DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expired_at` datetime DEFAULT NULL,
  PRIMARY KEY (`promo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
