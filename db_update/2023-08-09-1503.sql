CREATE TABLE `mailing_list` (
  `mailing_id` int NOT NULL AUTO_INCREMENT,
  `user_filter` json DEFAULT NULL,
  `subject_template` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `text_template` text COLLATE utf8mb4_bin,
  `transport` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `sound` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `link` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_started` tinyint NOT NULL DEFAULT '0',
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_ids` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `start_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`mailing_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;


CREATE TABLE `mailing_message_list` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `reciever_id` int DEFAULT NULL,
  `mailing_id` int DEFAULT NULL,
  `is_sent` tinyint NOT NULL DEFAULT '0',
  `is_failed` tinyint NOT NULL DEFAULT '0',
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_ids` varchar(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `willsend_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  UNIQUE KEY `umunq` (`reciever_id`,`mailing_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
