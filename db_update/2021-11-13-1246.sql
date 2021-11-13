/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
/**
 * Author:  bayci
 * Created: Nov 13, 2021
 */

CREATE TABLE `task_list` (
  `task_id` int NOT NULL AUTO_INCREMENT,
  `task_name` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `task_programm` json DEFAULT NULL,
  `task_result` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `task_interval_day` int DEFAULT '0',
  `task_interval_hour` int DEFAULT '0',
  `task_interval_min` int DEFAULT '0',
  `task_next_start` datetime DEFAULT NULL,
  `task_last_start` datetime DEFAULT NULL,
  `task_status` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
