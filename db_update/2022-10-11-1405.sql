CREATE TABLE `tariff_member_list` (
  `store_id` int NOT NULL,
  `tariff_id` int NOT NULL,
  `start_at` datetime DEFAULT NULL,
  `finish_at` datetime DEFAULT NULL,
  PRIMARY KEY (`store_id`,`tariff_id`),
  KEY `tarifid_idx` (`tariff_id`),
  CONSTRAINT `tariffstoreid` FOREIGN KEY (`store_id`) REFERENCES `store_list` (`store_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `tarifid` FOREIGN KEY (`tariff_id`) REFERENCES `tariff_list` (`tariff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
