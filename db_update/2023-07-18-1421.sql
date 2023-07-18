CREATE TABLE `courier_shift_list` (
  `shift_id` int NOT NULL AUTO_INCREMENT,
  `shift_status` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `courier_id` int DEFAULT NULL,
  `total_distance` int DEFAULT NULL,
  `total_duration` int DEFAULT NULL,
  `total_bonus` float DEFAULT NULL,
  `owner_id` int DEFAULT NULL,
  `owner_ally_ids` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`shift_id`),
  KEY `shstatus` (`shift_status`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
