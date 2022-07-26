CREATE TABLE `token_list` (
  `token_id` int NOT NULL AUTO_INCREMENT,
  `token_holder` varchar(45) DEFAULT NULL,
  `token_holder_id` int DEFAULT NULL,
  `token_hash` varchar(256) DEFAULT NULL,
  `is_disabled` tinyint DEFAULT '0',
  `owner_id` int DEFAULT NULL,
  `owner_ally_ids` varchar(45) DEFAULT NULL,
  `accessed_at` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  PRIMARY KEY (`token_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb3;
