DROP TABLE `transaction_list`;
CREATE TABLE `transaction_list` (
  `trans_id` int NOT NULL AUTO_INCREMENT,
  `trans_amount` decimal(10,2) DEFAULT NULL,
  `trans_data` json DEFAULT NULL,
  `acc_debit_code` varchar(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `acc_credit_code` varchar(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `holder` varchar(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `holder_id` int DEFAULT NULL,
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_id` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`trans_id`),
  KEY `index2` (`acc_debit_code`) /*!80000 INVISIBLE */,
  KEY `index3` (`acc_credit_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
