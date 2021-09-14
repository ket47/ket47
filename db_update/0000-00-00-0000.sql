-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: tezkel_db
-- ------------------------------------------------------
-- Server version	8.0.25

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `pref_list`
--

DROP TABLE IF EXISTS `pref_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pref_list` (
  `pref_name` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `pref_value` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `pref_json` json NOT NULL,
  PRIMARY KEY (`pref_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pref_list`
--

LOCK TABLES `pref_list` WRITE;
/*!40000 ALTER TABLE `pref_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `pref_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_list`
--

DROP TABLE IF EXISTS `product_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_list` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `store_id` int DEFAULT NULL,
  `product_code` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_description` text COLLATE utf8_unicode_ci,
  `product_weight` float DEFAULT NULL,
  `product_price` float DEFAULT NULL,
  `product_quantity` float DEFAULT NULL,
  `is_food` tinyint DEFAULT NULL,
  `is_disabled` tinyint DEFAULT NULL,
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `modified_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `product_img` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`product_id`),
  KEY `store_idx` (`store_id`),
  CONSTRAINT `store` FOREIGN KEY (`store_id`) REFERENCES `store_list` (`store_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_list`
--

LOCK TABLES `product_list` WRITE;
/*!40000 ALTER TABLE `product_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `store_list`
--

DROP TABLE IF EXISTS `store_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_list` (
  `store_id` int NOT NULL AUTO_INCREMENT,
  `store_name` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `store_description` text COLLATE utf8_unicode_ci,
  `store_address` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `store_coordinates` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_disabled` int DEFAULT NULL,
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `modified_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `store_list`
--

LOCK TABLES `store_list` WRITE;
/*!40000 ALTER TABLE `store_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `store_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_group_list`
--

DROP TABLE IF EXISTS `user_group_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_group_list` (
  `user_group_id` int NOT NULL AUTO_INCREMENT,
  `user_group_parent_id` int DEFAULT NULL,
  `user_group_name` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_group_type` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`user_group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_group_list`
--

LOCK TABLES `user_group_list` WRITE;
/*!40000 ALTER TABLE `user_group_list` DISABLE KEYS */;
INSERT INTO `user_group_list` VALUES (1,0,'Гость','guest',0,'0'),(2,1,'Покупатель','customer',0,'0'),(3,2,'Курьер','courier',0,'0'),(4,2,'Поставщик','supplier',0,'0'),(5,2,'Админ','admin',0,'0');
/*!40000 ALTER TABLE `user_group_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_group_member_list`
--

DROP TABLE IF EXISTS `user_group_member_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_group_member_list` (
  `user_id` int NOT NULL,
  `user_group_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`user_group_id`),
  KEY `groupId_idx` (`user_group_id`),
  CONSTRAINT `groupId` FOREIGN KEY (`user_group_id`) REFERENCES `user_group_list` (`user_group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `userID` FOREIGN KEY (`user_id`) REFERENCES `user_list` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_group_member_list`
--

LOCK TABLES `user_group_member_list` WRITE;
/*!40000 ALTER TABLE `user_group_member_list` DISABLE KEYS */;
INSERT INTO `user_group_member_list` VALUES (29,1),(29,2),(30,2),(29,5);
/*!40000 ALTER TABLE `user_group_member_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_list`
--

DROP TABLE IF EXISTS `user_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_list` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `user_name` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_surname` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_middlename` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_phone` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_phone_verified` int DEFAULT NULL,
  `user_email` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_email_verified` int DEFAULT NULL,
  `user_pass` varchar(70) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_comment` text COLLATE utf8_unicode_ci,
  `is_disabled` tinyint DEFAULT '0',
  `signed_in_at` datetime DEFAULT NULL,
  `signed_out_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `modified_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `phone` (`user_phone`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_list`
--

LOCK TABLES `user_list` WRITE;
/*!40000 ALTER TABLE `user_list` DISABLE KEYS */;
INSERT INTO `user_list` VALUES (29,'John','Lee','Abuserovitch','79787288233',1,'bay@nilsonmag.com',1,'$2y$10$QSi3R9MDBDfIJo4kc7vBpeP5BNS/0oS/3GMC.I52H/klDFf1uHRvi','sdfxdf',0,'2021-09-13 17:57:59','2021-09-11 15:23:21','2021-09-09 17:22:49','2021-09-13 17:57:59',NULL,29,'0'),(30,'Merilyn','Monroe','','79787288246',1,NULL,0,'$2y$10$zUTOZj5qyyyvQ2np0memcuy7/1ZqawmM1Y48tHToMWuXYJjMkMw3.',NULL,1,NULL,'2021-09-11 11:58:38','2021-09-10 12:00:14','2021-09-13 15:33:22',NULL,30,'0');
/*!40000 ALTER TABLE `user_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_role_permission_list`
--

DROP TABLE IF EXISTS `user_role_permission_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_role_permission_list` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `permited_class` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `permited_method` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `owner` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `ally` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  `other` varchar(45) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `unq` (`permited_method`,`permited_class`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_role_permission_list`
--

LOCK TABLES `user_role_permission_list` WRITE;
/*!40000 ALTER TABLE `user_role_permission_list` DISABLE KEYS */;
INSERT INTO `user_role_permission_list` VALUES (2,'UserModel','item','r,w','',''),(3,'UserGroupModel','item','r,w','',''),(4,'UserVerificationModel','item','r,w','','');
/*!40000 ALTER TABLE `user_role_permission_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_verification_list`
--

DROP TABLE IF EXISTS `user_verification_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_verification_list` (
  `user_verification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `verification_type` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `verification_value` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_verification_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_verification_list`
--

LOCK TABLES `user_verification_list` WRITE;
/*!40000 ALTER TABLE `user_verification_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_verification_list` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2021-09-14 11:36:04
