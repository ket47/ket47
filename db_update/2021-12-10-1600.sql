CREATE TABLE `courier_list` (
  `courier_id` int NOT NULL AUTO_INCREMENT,
  `courier_vehicle` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `courier_tax_num` bigint DEFAULT NULL,
  `current_order_id` int DEFAULT NULL,
  `courier_comment` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_disabled` tinyint NOT NULL DEFAULT '1',
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_ids` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`courier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;



CREATE TABLE `courier_group_list` (
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
  UNIQUE KEY `courunq` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;


CREATE TABLE `courier_group_member_list` (
  `member_id` int NOT NULL,
  `group_id` int NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`member_id`,`group_id`),
  KEY `courID_idx` (`member_id`),
  KEY `courGroup_idx` (`group_id`),
  CONSTRAINT `courGroup` FOREIGN KEY (`group_id`) REFERENCES `courier_group_list` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `courID` FOREIGN KEY (`member_id`) REFERENCES `courier_list` (`courier_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;

