CREATE TABLE `location_list` (
  `location_id` int NOT NULL AUTO_INCREMENT,
  `location_holder` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `location_holder_id` int DEFAULT NULL,
  `location_latitude` double DEFAULT NULL,
  `location_longitude` double DEFAULT NULL,
  `location_point` geometry NOT NULL,
  `location_address` varchar(500) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `location_order` int DEFAULT NULL,
  `is_main` tinyint DEFAULT NULL,
  `is_disabled` tinyint DEFAULT '0',
  `owner_id` int DEFAULT '0',
  `owner_ally_ids` varchar(45) COLLATE utf8_unicode_ci DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;

CREATE TABLE `location_group_list` (
  `group_id` int NOT NULL AUTO_INCREMENT,
  `group_parent_id` int DEFAULT NULL,
  `group_name` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_type` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_path_id` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_path` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `is_disabled` tinyint DEFAULT '0',
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `locunq` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;

CREATE TABLE `location_group_member_list` (
  `member_id` int NOT NULL,
  `group_id` int NOT NULL,
  `created_at` datetime DEFAULT NULL,
  KEY `locID_idx` (`member_id`),
  KEY `locGroup_idx` (`group_id`),
  CONSTRAINT `locGroup` FOREIGN KEY (`group_id`) REFERENCES `location_group_list` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `locId` FOREIGN KEY (`member_id`) REFERENCES `location_list` (`location_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
