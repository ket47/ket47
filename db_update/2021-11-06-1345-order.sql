
CREATE TABLE `order_list` (
  `order_id` int NOT NULL AUTO_INCREMENT,
  `order_group_id` int DEFAULT NULL,
  `order_store_id` int DEFAULT NULL,
  `order_customer_id` int DEFAULT NULL,
  `order_courier_id` int DEFAULT NULL,
  `order_description` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `order_sum_tax` float DEFAULT NULL,
  `order_sum_shipping` float DEFAULT NULL,
  `order_sum_total` float DEFAULT NULL,
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_ids` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;

CREATE TABLE `order_entry_list` (
  `entry_id` int NOT NULL AUTO_INCREMENT,
  `order_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `entry_text` varchar(400) CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci DEFAULT NULL,
  `entry_quantity` float DEFAULT NULL,
  `entry_self_price` float DEFAULT NULL,
  `entry_price` float DEFAULT NULL,
  `entry_comment` varchar(45) CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_ids` varchar(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`entry_id`),
  UNIQUE KEY `uniqueEntryRow` (`order_id`,`product_id`),
  KEY `entrorderid_idx` (`order_id`),
  CONSTRAINT `entrorderid` FOREIGN KEY (`order_id`) REFERENCES `order_list` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;

CREATE TABLE `order_group_list` (
  `group_id` int NOT NULL AUTO_INCREMENT,
  `group_parent_id` int DEFAULT NULL,
  `group_name` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_type` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_path_id` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_path` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_ids` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `is_disabled` tinyint DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `ordunq` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;

CREATE TABLE `order_group_member_list` (
  `member_id` int NOT NULL,
  `group_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`member_id`,`group_id`),
  KEY `productgroupId_idx` (`group_id`),
  KEY `orderCreatedBy_idx` (`created_by`),
  CONSTRAINT `orderCreatedBy` FOREIGN KEY (`created_by`) REFERENCES `user_list` (`user_id`),
  CONSTRAINT `ordergrpId` FOREIGN KEY (`group_id`) REFERENCES `order_group_list` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `orderId` FOREIGN KEY (`member_id`) REFERENCES `order_list` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;


