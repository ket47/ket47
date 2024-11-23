CREATE TABLE `post_list` (
  `post_id` int NOT NULL AUTO_INCREMENT,
  `store_id` int DEFAULT NULL,
  `post_title` varchar(45) DEFAULT NULL,
  `post_content` varchar(500) DEFAULT NULL,
  `post_data` json DEFAULT NULL,
  `post_type` enum('homeslide','wellcomeslide') DEFAULT NULL,
  `post_route` varchar(45) DEFAULT NULL,
  `is_disabled` tinyint NOT NULL DEFAULT '1',
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
