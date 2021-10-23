CREATE TABLE `order_entry_list` (
  `entry_id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `entry_text` varchar(200) CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci DEFAULT NULL,
  `entry_quantity` float DEFAULT NULL,
  `entry_self_price` float DEFAULT NULL,
  `entry_price` float DEFAULT NULL,
  `entry_comment` varchar(45) CHARACTER SET utf8mb3 COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`entry_id`),
  KEY `entrorderid_idx` (`order_id`),
  CONSTRAINT `entrorderid` FOREIGN KEY (`order_id`) REFERENCES `order_list` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
