CREATE TABLE `delivery_job_list` (
  `job_id` int NOT NULL AUTO_INCREMENT,
  `job_data` json DEFAULT NULL,
  `job_name` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `courier_id` int DEFAULT NULL,
  `start_latitude` float DEFAULT NULL,
  `start_longitude` float DEFAULT NULL,
  `start_prep_time` int DEFAULT NULL,
  `start_arrival_time` int DEFAULT NULL,
  `start_plan` bigint DEFAULT NULL,
  `start_color` varchar(7) COLLATE utf8_unicode_ci DEFAULT NULL,
  `finish_latitude` float DEFAULT NULL,
  `finish_longitude` float DEFAULT NULL,
  `finish_arrival_time` int DEFAULT NULL,
  `finish_color` varchar(7) COLLATE utf8_unicode_ci DEFAULT NULL,
  `owner_id` int DEFAULT '0',
  `owner_ally_ids` varchar(45) COLLATE utf8_unicode_ci DEFAULT '0',
  `is_disabled` tinyint DEFAULT '0',
  `stage` enum('scheduled','awaited','inited','assigned','started','finished','canceled') COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`job_id`),
  KEY `jorderid` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
