CREATE TABLE `trans_group_list` (
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
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
