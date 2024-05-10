CREATE TABLE `metric_act_list` (
  `act_id` int NOT NULL AUTO_INCREMENT,
  `metric_id` int DEFAULT NULL,
  `act_group` enum('auth','home','store','product','search','order','location') COLLATE utf8_unicode_ci DEFAULT NULL,
  `act_type` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `act_result` enum('ok','wrn','error') COLLATE utf8_unicode_ci DEFAULT NULL,
  `act_target_id` int unsigned DEFAULT NULL,
  `act_description` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `act_data` json DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`act_id`),
  KEY `mtarget` (`act_group`,`act_target_id`) /*!80000 INVISIBLE */,
  KEY `mtype` (`act_group`,`act_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
