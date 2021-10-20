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
-- Table structure for table `image_list`
--

DROP TABLE IF EXISTS `image_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `image_list` (
  `image_id` int NOT NULL AUTO_INCREMENT,
  `image_holder` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `image_holder_id` int DEFAULT NULL,
  `image_hash` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `image_order` int DEFAULT NULL,
  `is_main` tinyint NOT NULL DEFAULT '0',
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`image_id`),
  KEY `hldr` (`image_holder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `image_list`
--

LOCK TABLES `image_list` WRITE;
/*!40000 ALTER TABLE `image_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `image_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `imported_list`
--

DROP TABLE IF EXISTS `imported_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `imported_list` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_ids` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `holder` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `holder_id` int DEFAULT NULL,
  `target` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `target_id` int DEFAULT NULL,
  `action` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `C1` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C2` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C3` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C4` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C5` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C6` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C7` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C8` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C9` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C10` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C11` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C12` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C13` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C14` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C15` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `C16` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `imported_list`
--

LOCK TABLES `imported_list` WRITE;
/*!40000 ALTER TABLE `imported_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `imported_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pref_list`
--

DROP TABLE IF EXISTS `pref_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `pref_list` (
  `pref_name` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `pref_value` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `pref_json` json NOT NULL,
  PRIMARY KEY (`pref_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pref_list`
--

LOCK TABLES `pref_list` WRITE;
/*!40000 ALTER TABLE `pref_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `pref_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_group_list`
--

DROP TABLE IF EXISTS `product_group_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `product_group_list` (
  `group_id` int NOT NULL AUTO_INCREMENT,
  `group_parent_id` int DEFAULT NULL,
  `group_name` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_type` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_path_id` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_path` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_ids` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `is_disabled` tinyint DEFAULT '0',
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `prdunq` (`group_name`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_group_list`
--

LOCK TABLES `product_group_list` WRITE;
/*!40000 ALTER TABLE `product_group_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_group_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_group_member_list`
--

DROP TABLE IF EXISTS `product_group_member_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `product_group_member_list` (
  `member_id` int NOT NULL,
  `group_id` int NOT NULL,
  PRIMARY KEY (`member_id`,`group_id`),
  KEY `productgroupId_idx` (`group_id`),
  CONSTRAINT `productgroupId` FOREIGN KEY (`group_id`) REFERENCES `product_group_list` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `productID` FOREIGN KEY (`member_id`) REFERENCES `product_list` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_group_member_list`
--

LOCK TABLES `product_group_member_list` WRITE;
/*!40000 ALTER TABLE `product_group_member_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_group_member_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_list`
--

DROP TABLE IF EXISTS `product_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `product_list` (
  `product_id` int NOT NULL AUTO_INCREMENT,
  `store_id` int DEFAULT NULL,
  `product_code` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_name_new` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_description` varchar(1000) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_description_new` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `product_weight` float DEFAULT NULL,
  `product_price` float DEFAULT NULL,
  `product_quantity` float DEFAULT NULL,
  `is_produced` tinyint NOT NULL DEFAULT '0',
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`product_id`),
  KEY `store_idx` (`store_id`),
  CONSTRAINT `store` FOREIGN KEY (`store_id`) REFERENCES `store_list` (`store_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=383 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_list`
--

LOCK TABLES `product_list` WRITE;
/*!40000 ALTER TABLE `product_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `store_group_list`
--

DROP TABLE IF EXISTS `store_group_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `store_group_list` (
  `group_id` int NOT NULL AUTO_INCREMENT,
  `group_parent_id` int DEFAULT NULL,
  `group_name` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_type` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_path_id` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_path` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `owner_id` int NOT NULL DEFAULT '0',
  `owner_ally_ids` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0',
  `is_disabled` tinyint DEFAULT '0',
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `strunq` (`group_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `store_group_list`
--

LOCK TABLES `store_group_list` WRITE;
/*!40000 ALTER TABLE `store_group_list` DISABLE KEYS */;
INSERT INTO `store_group_list` VALUES (1,NULL,'Продукты','foodstore','/1/','/Продукты/',0,'0',0,NULL,NULL),(2,NULL,'Канцтовары',NULL,'/2/','/Канцтовары/',0,'0',0,NULL,NULL),(3,NULL,'Ресторан','restaraunt','/3/','/Ресторан/',0,'0',0,NULL,NULL);
/*!40000 ALTER TABLE `store_group_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `store_group_member_list`
--

DROP TABLE IF EXISTS `store_group_member_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `store_group_member_list` (
  `member_id` int NOT NULL,
  `group_id` int NOT NULL,
  PRIMARY KEY (`member_id`,`group_id`),
  KEY `storegroupId_idx` (`group_id`),
  CONSTRAINT `storegroupId` FOREIGN KEY (`group_id`) REFERENCES `store_group_list` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `storeID` FOREIGN KEY (`member_id`) REFERENCES `store_list` (`store_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `store_group_member_list`
--

LOCK TABLES `store_group_member_list` WRITE;
/*!40000 ALTER TABLE `store_group_member_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `store_group_member_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `store_list`
--

DROP TABLE IF EXISTS `store_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `store_list` (
  `store_id` int NOT NULL AUTO_INCREMENT,
  `store_name` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `store_name_new` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `store_description` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `store_description_new` text COLLATE utf8_unicode_ci,
  `store_address` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `store_phone` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `store_email` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `store_tax_num` bigint DEFAULT NULL,
  `store_company_name` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `store_company_name_new` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `store_minimal_order` int DEFAULT NULL,
  `store_time_preparation` tinyint DEFAULT NULL,
  `store_time_opens_0` tinyint DEFAULT NULL,
  `store_time_opens_1` tinyint DEFAULT NULL,
  `store_time_opens_2` tinyint DEFAULT NULL,
  `store_time_opens_3` tinyint DEFAULT NULL,
  `store_time_opens_4` tinyint DEFAULT NULL,
  `store_time_opens_5` tinyint DEFAULT NULL,
  `store_time_opens_6` tinyint DEFAULT NULL,
  `store_time_closes_0` tinyint DEFAULT NULL,
  `store_time_closes_1` tinyint DEFAULT NULL,
  `store_time_closes_2` tinyint DEFAULT NULL,
  `store_time_closes_3` tinyint DEFAULT NULL,
  `store_time_closes_4` tinyint DEFAULT NULL,
  `store_time_closes_5` tinyint DEFAULT NULL,
  `store_time_closes_6` tinyint DEFAULT NULL,
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`store_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
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
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `user_group_list` (
  `group_id` int NOT NULL AUTO_INCREMENT,
  `group_parent_id` int DEFAULT NULL,
  `group_name` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_type` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_path_id` varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `group_path` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `is_disabled` tinyint DEFAULT '0',
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`group_id`),
  UNIQUE KEY `usrunq` (`group_name`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_group_list`
--

LOCK TABLES `user_group_list` WRITE;
/*!40000 ALTER TABLE `user_group_list` DISABLE KEYS */;
INSERT INTO `user_group_list` VALUES (2,0,'Покупатель','customer','/2/','/Покупатель/',0,'0',0,NULL,NULL),(3,2,'Курьер','courier','/2/3/','/Покупатель/Курьер/',0,'0',0,NULL,NULL),(4,2,'Поставщик','supplier','/2/4/','/Покупатель/Поставщик/',0,'0',0,NULL,NULL),(5,0,'Админ','admin','/5/','/Админ/',0,'0',0,NULL,NULL);
/*!40000 ALTER TABLE `user_group_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_group_member_list`
--

DROP TABLE IF EXISTS `user_group_member_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `user_group_member_list` (
  `member_id` int NOT NULL,
  `group_id` int NOT NULL,
  PRIMARY KEY (`member_id`,`group_id`),
  KEY `usergroupId_idx` (`group_id`),
  CONSTRAINT `usergroupId` FOREIGN KEY (`group_id`) REFERENCES `user_group_list` (`group_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `userID` FOREIGN KEY (`member_id`) REFERENCES `user_list` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_group_member_list`
--

LOCK TABLES `user_group_member_list` WRITE;
/*!40000 ALTER TABLE `user_group_member_list` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_group_member_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_list`
--

DROP TABLE IF EXISTS `user_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `user_list` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `user_name` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_surname` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_middlename` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_phone` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_phone_verified` int DEFAULT NULL,
  `user_email` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_email_verified` int DEFAULT NULL,
  `user_pass` varchar(70) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_avatar_name` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_disabled` tinyint NOT NULL DEFAULT '0',
  `signed_in_at` datetime DEFAULT NULL,
  `signed_out_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `owner_id` int NOT NULL,
  `owner_ally_ids` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `phone` (`user_phone`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_list`
--

LOCK TABLES `user_list` WRITE;
/*!40000 ALTER TABLE `user_list` DISABLE KEYS */;
INSERT INTO `user_list` VALUES (41,'John',NULL,NULL,'79186414455',1,NULL,NULL,'$2y$10$M300HGuW5zg1HeZgX/W3ZuyjcEuGDOy90VvLPaGAUI8I82LlrnqG2','man',0,'2021-10-19 09:32:10','2021-10-14 14:22:20','2021-09-25 17:52:37','2021-10-19 16:18:53',NULL,41,'');
/*!40000 ALTER TABLE `user_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_role_permission_list`
--

DROP TABLE IF EXISTS `user_role_permission_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `user_role_permission_list` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `permited_class` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `permited_method` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `owner` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `ally` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `other` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `unq` (`permited_method`,`permited_class`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_role_permission_list`
--

LOCK TABLES `user_role_permission_list` WRITE;
/*!40000 ALTER TABLE `user_role_permission_list` DISABLE KEYS */;
INSERT INTO `user_role_permission_list` VALUES (2,'UserModel','item','r,w','',''),(3,'GroupModel','item','r,w','','r'),(4,'UserVerificationModel','item','r,w','',''),(5,'StoreModel','item','r,w','r','r'),(6,'StoreModel','disabled','r','',''),(7,'ImageModel','disabled','r','',''),(8,'ImageModel','item','r,w','',''),(9,'ProductModel','item','r,w','r','r'),(10,'ProductModel','disabled','r','',''),(11,'ImporterModel','item','r,w','',''),(12,'ImporterModel','disabled','r','','');
/*!40000 ALTER TABLE `user_role_permission_list` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_verification_list`
--

DROP TABLE IF EXISTS `user_verification_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8 */;
CREATE TABLE `user_verification_list` (
  `user_verification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `verification_type` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `verification_value` varchar(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_verification_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
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

-- Dump completed on 2021-10-20 11:59:24
