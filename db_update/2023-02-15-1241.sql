CREATE TABLE `transaction_tag_list` (
  `link_id` int NOT NULL AUTO_INCREMENT,
  `trans_id` int NOT NULL,
  `tag_name` varchar(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '',
  `tag_id` int NOT NULL DEFAULT '0',
  `tag_type` varchar(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '',
  `tag_option` varchar(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`link_id`),
  UNIQUE KEY `thindex` (`trans_id`,`tag_name`,`tag_id`,`tag_type`,`tag_option`),
  KEY `ff_idx` (`trans_id`) /*!80000 INVISIBLE */,
  KEY `hindex` (`tag_name`,`tag_id`,`tag_type`,`tag_option`),
  CONSTRAINT `tforeign` FOREIGN KEY (`trans_id`) REFERENCES `transaction_list` (`trans_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE latin1_swedish_ci;