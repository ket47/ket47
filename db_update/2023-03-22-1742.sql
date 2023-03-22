CREATE TABLE `reaction_list` (
  `reaction_id` int NOT NULL AUTO_INCREMENT,
  `reaction_is_like` tinyint DEFAULT NULL,
  `reaction_is_dislike` tinyint DEFAULT NULL,
  `reaction_comment` varchar(400) COLLATE utf8_bin DEFAULT NULL,
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_ids` varchar(45) COLLATE utf8_bin DEFAULT NULL,
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `sealed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`reaction_id`)
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb3 COLLATE=utf8_bin;


CREATE TABLE `reaction_tag_list` (
  `link_id` int NOT NULL AUTO_INCREMENT,
  `member_id` int NOT NULL,
  `tag_name` varchar(45) NOT NULL DEFAULT '',
  `tag_id` int NOT NULL DEFAULT '0',
  `tag_type` varchar(45) NOT NULL DEFAULT '',
  `tag_option` varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `ruindex` (`member_id`,`tag_name`,`tag_id`,`tag_type`,`tag_option`),
  KEY `m_idx` (`member_id`),
  KEY `rtindex` (`tag_name`,`tag_id`,`tag_type`,`tag_option`),
  CONSTRAINT `rforeign` FOREIGN KEY (`member_id`) REFERENCES `reaction_list` (`reaction_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=163 DEFAULT CHARSET=latin1;
