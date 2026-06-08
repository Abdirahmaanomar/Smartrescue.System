-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: smartrescuesystem
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `dispatches`
--

DROP TABLE IF EXISTS `dispatches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dispatches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `status` enum('on_the_way','arrived','done') DEFAULT 'on_the_way',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `request_id` (`request_id`),
  KEY `unit_id` (`unit_id`),
  CONSTRAINT `dispatches_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `emergency_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dispatches_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `emergency_units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dispatches`
--

LOCK TABLES `dispatches` WRITE;
/*!40000 ALTER TABLE `dispatches` DISABLE KEYS */;
/*!40000 ALTER TABLE `dispatches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `emergency_contacts`
--

DROP TABLE IF EXISTS `emergency_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `emergency_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `emergency_contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `emergency_contacts`
--

LOCK TABLES `emergency_contacts` WRITE;
/*!40000 ALTER TABLE `emergency_contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `emergency_contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `emergency_requests`
--

DROP TABLE IF EXISTS `emergency_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `emergency_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `latitude` varchar(50) NOT NULL,
  `longitude` varchar(50) NOT NULL,
  `status` enum('pending','assigned','completed') DEFAULT 'pending',
  `description` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `emergency_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `emergency_requests`
--

LOCK TABLES `emergency_requests` WRITE;
/*!40000 ALTER TABLE `emergency_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `emergency_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `emergency_units`
--

DROP TABLE IF EXISTS `emergency_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `emergency_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` enum('available','busy') DEFAULT 'available',
  `unit_name` varchar(100) DEFAULT NULL,
  `unit_type` varchar(50) DEFAULT NULL,
  `plate_number` varchar(50) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `current_lat` decimal(10,8) DEFAULT NULL,
  `current_lng` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `emergency_units`
--

LOCK TABLES `emergency_units` WRITE;
/*!40000 ALTER TABLE `emergency_units` DISABLE KEYS */;
INSERT INTO `emergency_units` VALUES (9,'available','AMB_001','medical','SOM-001-A',4,2.01574700,45.27209500),(12,'available','FRF_001','fire','SOM_001_B',NULL,NULL,NULL),(13,'available','TPO','police','SOM_099_A',21,2.01574700,45.27209500);
/*!40000 ALTER TABLE `emergency_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('sms','push') NOT NULL,
  `status` enum('sent','pending') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rescue_requests`
--

DROP TABLE IF EXISTS `rescue_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rescue_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `emergency_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `evidence_image` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_unit_id` int(11) DEFAULT NULL,
  `accuracy` float DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rescue_requests`
--

LOCK TABLES `rescue_requests` WRITE;
/*!40000 ALTER TABLE `rescue_requests` DISABLE KEYS */;
INSERT INTO `rescue_requests` VALUES (5,1,2.03290000,45.34620000,'Medical','','','completed','2026-03-26 16:45:11',2,NULL),(6,1,2.03290000,45.34620000,'Medical','','','completed','2026-03-26 17:00:42',2,0),(7,1,2.03290000,45.34620000,'Medical','','','completed','2026-03-26 17:15:42',2,52662),(9,1,2.06000000,45.30000000,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-03-27 14:59:14',5,50000),(12,1,2.03607804,45.30042242,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-03-29 08:53:01',5,58),(13,1,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-03-29 17:33:23',4,300),(16,1,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-02 16:57:58',5,300),(17,11,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-02 19:12:46',4,300),(18,1,2.01574700,45.27209500,'Fire','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-03 11:25:37',5,300),(19,1,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-04 16:39:40',5,300),(20,1,2.01574700,45.27209500,'Medical','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','completed','2026-04-05 14:31:51',5,300),(21,1,2.01574700,45.27209500,'Medical','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','completed','2026-04-05 14:51:10',5,300),(22,1,2.01574700,45.27209500,'Accident','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','completed','2026-04-06 10:18:28',7,187),(23,1,2.01574700,45.27209500,'Medical','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','completed','2026-04-07 16:30:04',7,212),(25,11,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-14 09:41:21',9,212),(26,11,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-15 16:44:49',9,187),(27,1,2.03845525,45.30271865,'Medical','','','accepted','2026-04-17 17:39:17',NULL,381),(28,19,2.03290000,45.34620000,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-17 17:50:30',9,52662),(29,1,2.03845525,45.30271865,'Medical','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','accepted','2026-04-17 17:57:48',NULL,381),(30,1,2.01574700,45.27209500,'Medical','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','completed','2026-04-18 10:31:08',9,500),(31,11,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-18 13:37:32',9,999),(32,11,2.03774416,45.30108229,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-18 14:42:11',13,75),(33,11,2.03776170,45.30109320,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-21 09:53:18',9,69),(34,1,2.03845525,45.30271865,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','pending','2026-04-25 10:40:41',NULL,381),(35,1,2.03845525,45.30271865,'Fire','MEDICAL ID: \r\n\r\nMSG: ','','accepted','2026-04-25 10:49:05',NULL,381),(36,1,2.03845525,45.30271865,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','en_route','2026-04-25 10:49:45',13,381);
/*!40000 ALTER TABLE `rescue_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
INSERT INTO `system_logs` VALUES (1,3,'Database Backup','Manual SQL dump generated and downloaded.','safe','2026-04-19 09:07:08'),(2,3,'Database Backup','Manual SQL dump generated and downloaded.','safe','2026-04-19 09:10:15'),(3,3,'Settings Updated','Admin modified system configuration parameters.','success','2026-04-19 09:11:51'),(4,3,'Settings Updated','Admin modified system configuration parameters.','success','2026-04-19 09:11:59'),(5,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-19 14:16:30'),(6,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-23 16:23:00'),(7,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-25 08:13:22'),(8,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-25 08:28:25'),(9,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-25 09:41:48'),(10,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-25 09:43:34'),(11,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-25 13:18:10'),(12,3,'Login','Administrator logged into the secure dashboard.','info','2026-05-04 12:20:19'),(13,3,'Settings Updated','Admin modified system configuration parameters.','success','2026-05-04 12:58:04'),(14,3,'Settings Updated','Admin modified system configuration parameters.','success','2026-05-04 12:59:01'),(15,3,'Login','Administrator logged into the secure dashboard.','info','2026-05-06 16:51:37'),(16,3,'Login','Administrator logged into the secure dashboard.','info','2026-05-06 16:56:05');
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'site_name','SmartRescue','2026-04-15 09:33:22'),(2,'contact_email','admin@smartrescue.so','2026-04-15 09:33:22'),(3,'contact_phone','+252 61 000 0000','2026-04-15 09:33:22'),(4,'timezone','Africa/Mogadishu','2026-04-15 09:33:22'),(5,'language','en','2026-05-04 12:59:01'),(6,'maps_api_key','','2026-04-15 09:33:22'),(7,'sms_api_key','','2026-04-15 09:33:22'),(8,'push_api_key','','2026-04-15 09:33:22'),(9,'notif_email','1','2026-05-04 12:59:01'),(10,'notif_sms','1','2026-05-04 12:59:01'),(11,'notif_sound','1','2026-05-04 12:59:01'),(12,'notif_push','on','2026-04-15 09:35:35'),(13,'refresh_rate','4','2026-04-15 09:33:22'),(14,'max_units','50','2026-04-15 09:33:22'),(15,'sos_timeout','30','2026-04-15 09:33:22'),(16,'dark_mode','0','2026-04-15 09:33:22'),(17,'two_fa','0','2026-04-15 09:33:22'),(18,'session_timeout','30','2026-04-15 16:56:04'),(19,'backup_auto','on','2026-04-15 09:35:35'),(35,'audit_log','1','2026-04-18 11:01:30'),(71,'auto_backup','0','2026-04-18 18:44:38');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `profile_image` varchar(255) DEFAULT NULL,
  `dark_mode` tinyint(1) DEFAULT 0,
  `medical_info` text DEFAULT NULL,
  `emergency_contacts` text DEFAULT NULL,
  `current_lat` decimal(10,8) DEFAULT NULL,
  `current_lng` decimal(11,8) DEFAULT NULL,
  `location_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `language` varchar(10) DEFAULT 'en',
  `date_format` varchar(10) DEFAULT 'dmy',
  `gps_enabled` tinyint(1) DEFAULT 1,
  `share_live_location` tinyint(1) DEFAULT 1,
  `location_history` tinyint(1) DEFAULT 0,
  `vibration_enabled` tinyint(1) DEFAULT 1,
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `gps_access` tinyint(1) DEFAULT 1,
  `live_sos_location` tinyint(1) DEFAULT 1,
  `time_format_24h` tinyint(1) DEFAULT 1,
  `sound_alerts` tinyint(1) DEFAULT 1,
  `emergency_updates` tinyint(1) DEFAULT 1,
  `location_sharing` tinyint(1) DEFAULT 1,
  `auto_gps_tracking` tinyint(1) DEFAULT 1,
  `session_timeout` varchar(20) DEFAULT '30',
  `birth_date` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `last_session_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Mohamed saed sha','613065900','mohamedsaedsahal27@gmail.com','$2y$10$6saIv5iKenhSOGqh6dXj4eVVw6bHDoz4uXNCOccPMarFCM27PxGOe','2026-03-25 11:37:10','user','uploads/avatars/avatar_1_1777114249.jpg',0,'','saciid sahal: 613065946\r\njimcaale cali ibraahim: 615547940\r\nAbdirahmaan Yahye Shire: 612892778',NULL,NULL,'2026-05-09 09:06:27','en','dmy',1,1,1,1,1,0,0,1,1,1,1,1,'30',NULL,'Male','3qefem8ufgokeaafep6ccv0m7q'),(2,'liiban','12345678','','$2y$10$D0zG8woMJ1Via606Q36EK.AaYXa/uJubZf/5iNTBMbGpv.Vl83fXi','2026-03-25 11:56:31','admin',NULL,0,NULL,NULL,NULL,NULL,'2026-03-31 13:43:56','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,NULL),(3,'liiban axmed abukar','123456789','liban@gmail.com','$2y$10$76o8l2Mn5t6sMee/rWylqe7GIeuGlpcliBNnobuQjjYJ.IV3hIbvS','2026-03-25 11:58:57','admin','uploads/avatars/avatar_3_1776598486.jpg',0,NULL,NULL,NULL,NULL,'2026-05-06 16:56:05','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,'hnseo3s089sb985f89qe0q0qir'),(4,'maanka cali geedi','618448689','manka@gmail.com','$2y$10$vs6X8Er36Ah9D8nOn3ox/e.lxq8ENddBzO4qaVmcwIscrs9zAOGMS','2026-03-26 15:18:18','driver','uploads/avatars/avatar_4_1777114512.jpg',1,NULL,NULL,2.01574700,45.27209500,'2026-05-07 15:16:22','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'never','2000-07-18','Male','t103kqa4dqtrhmcr67eaablr5a'),(6,'Test User','613065947','test@test.com','$2y$10$nuWZtYdOwoWoMDdeT.fYYOsWhkA4MUQmdTky4gGDwmpNspyT.HCq2','2026-03-31 13:49:30','user',NULL,0,NULL,NULL,NULL,NULL,'2026-03-31 13:49:30','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,NULL),(8,'UI Tester','615999999','619999999uitester2@example.com','$2y$10$IKeISx0d4whANVqrkq3Y8.rUYuqCoSIEjvvTeW4WZjhTex2WB7Oam','2026-03-31 14:41:10','user',NULL,0,NULL,NULL,NULL,NULL,'2026-03-31 14:41:10','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,NULL),(10,'Admin Tester','615998877','admin@test.com','$2y$10$WaFKyTHTQLoBAKQzCtxTeu/GgRAiqjEFoW15.0VwAYOlsnURuairy','2026-04-01 15:55:58','admin',NULL,0,NULL,NULL,NULL,NULL,'2026-04-01 15:55:58','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,NULL),(11,'naciimo sahal','615001378','naciimo@gamail.com','$2y$10$0AqYh.vc.V.cRhMxXe9oEOcGazw1F5xLl5e/wu7DhlXKACLnMomzG','2026-04-02 11:36:27','user','uploads/avatars/avatar_11_1776607909.png',0,'','6100000000: geedi',NULL,NULL,'2026-05-09 09:06:58','en','dmy',1,1,1,1,1,1,1,1,1,1,1,1,'30',NULL,'Female','ocm0f4gnerkv366jl5olr0g6r2'),(16,'Admin User','0615000000','admin@smartrescue.com','$2y$10$IgutL89Zefxb8gtJ2ZsLsupIJLLgwiPKMRdaB4o9WA1CR.e/e8Teq','2026-04-07 13:43:02','admin',NULL,0,NULL,NULL,NULL,NULL,'2026-04-07 13:43:02','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,NULL),(19,'Test User','611223344','123456789testuser@example.com','$2y$10$w8/VVKOAiJN.3fW/9c04KenQsNbIiDSMJ7b30gRh57P.l.ftk1ZoS','2026-04-17 17:48:13','user',NULL,0,'','farax geedi: 123456789',2.03290000,45.34620000,'2026-04-18 13:51:19','en','dmy',1,1,1,1,1,1,1,1,1,1,1,1,'30','2000-01-01','Male',NULL),(21,'Axmed Sayid ','619524812','axmed@gmail.com','$2y$10$7kCza7fxvZ390cz7qQtXsOMQhHocZvFVfyuuEBwC5dwgzDL8rYWsi','2026-04-19 08:35:16','driver','uploads/avatars/avatar_21_1776598354.jpg',1,NULL,NULL,2.01574700,45.27209500,'2026-05-07 15:12:38','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,'Male','du8i45bhaf80osohdrcu4mq5p0');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
  `current_lat` decimal(10,8) DEFAULT NULL,
  `current_lng` decimal(11,8) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `emergency_units`
--

LOCK TABLES `emergency_units` WRITE;
/*!40000 ALTER TABLE `emergency_units` DISABLE KEYS */;
INSERT INTO `emergency_units` VALUES (9,'available','AMB_001','medical','SOM-001-A',4,2.01574700,45.27209500),(12,'available','FRF_001','fire','SOM_001_B',NULL,NULL,NULL),(13,'available','TPO','police','SOM_099_A',21,2.01574700,45.27209500);
/*!40000 ALTER TABLE `emergency_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('sms','push') NOT NULL,
  `status` enum('sent','pending') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rescue_requests`
--

DROP TABLE IF EXISTS `rescue_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rescue_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `lat` decimal(10,8) DEFAULT NULL,
  `lng` decimal(11,8) DEFAULT NULL,
  `emergency_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `evidence_image` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_unit_id` int(11) DEFAULT NULL,
  `accuracy` float DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rescue_requests`
--

LOCK TABLES `rescue_requests` WRITE;
/*!40000 ALTER TABLE `rescue_requests` DISABLE KEYS */;
INSERT INTO `rescue_requests` VALUES (5,1,2.03290000,45.34620000,'Medical','','','completed','2026-03-26 16:45:11',2,NULL),(6,1,2.03290000,45.34620000,'Medical','','','completed','2026-03-26 17:00:42',2,0),(7,1,2.03290000,45.34620000,'Medical','','','completed','2026-03-26 17:15:42',2,52662),(9,1,2.06000000,45.30000000,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-03-27 14:59:14',5,50000),(12,1,2.03607804,45.30042242,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-03-29 08:53:01',5,58),(13,1,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-03-29 17:33:23',4,300),(16,1,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-02 16:57:58',5,300),(17,11,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-02 19:12:46',4,300),(18,1,2.01574700,45.27209500,'Fire','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-03 11:25:37',5,300),(19,1,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-04 16:39:40',5,300),(20,1,2.01574700,45.27209500,'Medical','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','completed','2026-04-05 14:31:51',5,300),(21,1,2.01574700,45.27209500,'Medical','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','completed','2026-04-05 14:51:10',5,300),(22,1,2.01574700,45.27209500,'Accident','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','completed','2026-04-06 10:18:28',7,187),(23,1,2.01574700,45.27209500,'Medical','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','completed','2026-04-07 16:30:04',7,212),(25,11,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-14 09:41:21',9,212),(26,11,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-15 16:44:49',9,187),(27,1,2.03845525,45.30271865,'Medical','','','accepted','2026-04-17 17:39:17',NULL,381),(28,19,2.03290000,45.34620000,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-17 17:50:30',9,52662),(29,1,2.03845525,45.30271865,'Medical','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','accepted','2026-04-17 17:57:48',NULL,381),(30,1,2.01574700,45.27209500,'Medical','MEDICAL ID: Blood: A+ | Allergies: eggs | Conditions: asthma  | Meds: none\r\n\r\nwaxayaalo kale\r\n\r\nMSG: ','','completed','2026-04-18 10:31:08',9,500),(31,11,2.01574700,45.27209500,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-18 13:37:32',9,999),(32,11,2.03774416,45.30108229,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-18 14:42:11',13,75),(33,11,2.03776170,45.30109320,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','completed','2026-04-21 09:53:18',9,69),(34,1,2.03845525,45.30271865,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','pending','2026-04-25 10:40:41',NULL,381),(35,1,2.03845525,45.30271865,'Fire','MEDICAL ID: \r\n\r\nMSG: ','','accepted','2026-04-25 10:49:05',NULL,381),(36,1,2.03845525,45.30271865,'Medical','MEDICAL ID: \r\n\r\nMSG: ','','en_route','2026-04-25 10:49:45',13,381);
/*!40000 ALTER TABLE `rescue_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `type` varchar(50) DEFAULT 'info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
INSERT INTO `system_logs` VALUES (1,3,'Database Backup','Manual SQL dump generated and downloaded.','safe','2026-04-19 09:07:08'),(2,3,'Database Backup','Manual SQL dump generated and downloaded.','safe','2026-04-19 09:10:15'),(3,3,'Settings Updated','Admin modified system configuration parameters.','success','2026-04-19 09:11:51'),(4,3,'Settings Updated','Admin modified system configuration parameters.','success','2026-04-19 09:11:59'),(5,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-19 14:16:30'),(6,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-23 16:23:00'),(7,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-25 08:13:22'),(8,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-25 08:28:25'),(9,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-25 09:41:48'),(10,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-25 09:43:34'),(11,3,'Login','Administrator logged into the secure dashboard.','info','2026-04-25 13:18:10'),(12,3,'Login','Administrator logged into the secure dashboard.','info','2026-05-04 12:20:19'),(13,3,'Settings Updated','Admin modified system configuration parameters.','success','2026-05-04 12:58:04'),(14,3,'Settings Updated','Admin modified system configuration parameters.','success','2026-05-04 12:59:01'),(15,3,'Login','Administrator logged into the secure dashboard.','info','2026-05-06 16:51:37'),(16,3,'Login','Administrator logged into the secure dashboard.','info','2026-05-06 16:56:05');
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES (1,'site_name','SmartRescue','2026-04-15 09:33:22'),(2,'contact_email','admin@smartrescue.so','2026-04-15 09:33:22'),(3,'contact_phone','+252 61 000 0000','2026-04-15 09:33:22'),(4,'timezone','Africa/Mogadishu','2026-04-15 09:33:22'),(5,'language','en','2026-05-04 12:59:01'),(6,'maps_api_key','','2026-04-15 09:33:22'),(7,'sms_api_key','','2026-04-15 09:33:22'),(8,'push_api_key','','2026-04-15 09:33:22'),(9,'notif_email','1','2026-05-04 12:59:01'),(10,'notif_sms','1','2026-05-04 12:59:01'),(11,'notif_sound','1','2026-05-04 12:59:01'),(12,'notif_push','on','2026-04-15 09:35:35'),(13,'refresh_rate','4','2026-04-15 09:33:22'),(14,'max_units','50','2026-04-15 09:33:22'),(15,'sos_timeout','30','2026-04-15 09:33:22'),(16,'dark_mode','0','2026-04-15 09:33:22'),(17,'two_fa','0','2026-04-15 09:33:22'),(18,'session_timeout','30','2026-04-15 16:56:04'),(19,'backup_auto','on','2026-04-15 09:35:35'),(35,'audit_log','1','2026-04-18 11:01:30'),(71,'auto_backup','0','2026-04-18 18:44:38');
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL DEFAULT 'user',
  `profile_image` varchar(255) DEFAULT NULL,
  `dark_mode` tinyint(1) DEFAULT 0,
  `medical_info` text DEFAULT NULL,
  `emergency_contacts` text DEFAULT NULL,
  `current_lat` decimal(10,8) DEFAULT NULL,
  `current_lng` decimal(11,8) DEFAULT NULL,
  `location_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `language` varchar(10) DEFAULT 'en',
  `date_format` varchar(10) DEFAULT 'dmy',
  `gps_enabled` tinyint(1) DEFAULT 1,
  `share_live_location` tinyint(1) DEFAULT 1,
  `location_history` tinyint(1) DEFAULT 0,
  `vibration_enabled` tinyint(1) DEFAULT 1,
  `notifications_enabled` tinyint(1) DEFAULT 1,
  `gps_access` tinyint(1) DEFAULT 1,
  `live_sos_location` tinyint(1) DEFAULT 1,
  `time_format_24h` tinyint(1) DEFAULT 1,
  `sound_alerts` tinyint(1) DEFAULT 1,
  `emergency_updates` tinyint(1) DEFAULT 1,
  `location_sharing` tinyint(1) DEFAULT 1,
  `auto_gps_tracking` tinyint(1) DEFAULT 1,
  `session_timeout` varchar(20) DEFAULT '30',
  `birth_date` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `last_session_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Mohamed saed sha','613065900','mohamedsaedsahal27@gmail.com','$2y$10$6saIv5iKenhSOGqh6dXj4eVVw6bHDoz4uXNCOccPMarFCM27PxGOe','2026-03-25 11:37:10','user','uploads/avatars/avatar_1_1777114249.jpg',0,'','saciid sahal: 613065946\r\njimcaale cali ibraahim: 615547940\r\nAbdirahmaan Yahye Shire: 612892778',NULL,NULL,'2026-05-09 09:06:27','en','dmy',1,1,1,1,1,0,0,1,1,1,1,1,'30',NULL,'Male','3qefem8ufgokeaafep6ccv0m7q'),(2,'liiban','12345678','','$2y$10$D0zG8woMJ1Via606Q36EK.AaYXa/uJubZf/5iNTBMbGpv.Vl83fXi','2026-03-25 11:56:31','admin',NULL,0,NULL,NULL,NULL,NULL,'2026-03-31 13:43:56','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,NULL),(3,'liiban axmed abukar','123456789','liban@gmail.com','$2y$10$76o8l2Mn5t6sMee/rWylqe7GIeuGlpcliBNnobuQjjYJ.IV3hIbvS','2026-03-25 11:58:57','admin','uploads/avatars/avatar_3_1776598486.jpg',0,NULL,NULL,NULL,NULL,'2026-05-06 16:56:05','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,'hnseo3s089sb985f89qe0q0qir'),(4,'maanka cali geedi','618448689','manka@gmail.com','$2y$10$vs6X8Er36Ah9D8nOn3ox/e.lxq8ENddBzO4qaVmcwIscrs9zAOGMS','2026-03-26 15:18:18','driver','uploads/avatars/avatar_4_1777114512.jpg',1,NULL,NULL,2.01574700,45.27209500,'2026-05-07 15:16:22','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'never','2000-07-18','Male','t103kqa4dqtrhmcr67eaablr5a'),(6,'Test User','613065947','test@test.com','$2y$10$nuWZtYdOwoWoMDdeT.fYYOsWhkA4MUQmdTky4gGDwmpNspyT.HCq2','2026-03-31 13:49:30','user',NULL,0,NULL,NULL,NULL,NULL,'2026-03-31 13:49:30','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,NULL),(8,'UI Tester','615999999','619999999uitester2@example.com','$2y$10$IKeISx0d4whANVqrkq3Y8.rUYuqCoSIEjvvTeW4WZjhTex2WB7Oam','2026-03-31 14:41:10','user',NULL,0,NULL,NULL,NULL,NULL,'2026-03-31 14:41:10','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,NULL),(10,'Admin Tester','615998877','admin@test.com','$2y$10$WaFKyTHTQLoBAKQzCtxTeu/GgRAiqjEFoW15.0VwAYOlsnURuairy','2026-04-01 15:55:58','admin',NULL,0,NULL,NULL,NULL,NULL,'2026-04-01 15:55:58','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,NULL),(11,'naciimo sahal','615001378','naciimo@gamail.com','$2y$10$0AqYh.vc.V.cRhMxXe9oEOcGazw1F5xLl5e/wu7DhlXKACLnMomzG','2026-04-02 11:36:27','user','uploads/avatars/avatar_11_1776607909.png',0,'','6100000000: geedi',NULL,NULL,'2026-05-09 09:06:58','en','dmy',1,1,1,1,1,1,1,1,1,1,1,1,'30',NULL,'Female','ocm0f4gnerkv366jl5olr0g6r2'),(16,'Admin User','0615000000','admin@smartrescue.com','$2y$10$IgutL89Zefxb8gtJ2ZsLsupIJLLgwiPKMRdaB4o9WA1CR.e/e8Teq','2026-04-07 13:43:02','admin',NULL,0,NULL,NULL,NULL,NULL,'2026-04-07 13:43:02','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,NULL,NULL),(19,'Test User','611223344','123456789testuser@example.com','$2y$10$w8/VVKOAiJN.3fW/9c04KenQsNbIiDSMJ7b30gRh57P.l.ftk1ZoS','2026-04-17 17:48:13','user',NULL,0,'','farax geedi: 123456789',2.03290000,45.34620000,'2026-04-18 13:51:19','en','dmy',1,1,1,1,1,1,1,1,1,1,1,1,'30','2000-01-01','Male',NULL),(21,'Axmed Sayid ','619524812','axmed@gmail.com','$2y$10$7kCza7fxvZ390cz7qQtXsOMQhHocZvFVfyuuEBwC5dwgzDL8rYWsi','2026-04-19 08:35:16','driver','uploads/avatars/avatar_21_1776598354.jpg',1,NULL,NULL,2.01574700,45.27209500,'2026-05-07 15:12:38','en','dmy',1,1,0,1,1,1,1,1,1,1,1,1,'30',NULL,'Male','du8i45bhaf80osohdrcu4mq5p0');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-09 12:22:21


-- --- LEGACY SCHEMA FIXES (from fix_schema.sql) ---
ALTER TABLE emergency_units 
DROP COLUMN type, 
DROP COLUMN driver_name, 
DROP COLUMN phone, 
DROP COLUMN latitude, 
DROP COLUMN longitude, 
DROP COLUMN updated_at;

ALTER TABLE rescue_requests 
DROP COLUMN driver_id, 
DROP COLUMN priority, 
DROP COLUMN patient_age, 
DROP COLUMN patient_condition, 
DROP COLUMN patient_notes;

ALTER TABLE rescue_requests MODIFY COLUMN lat DECIMAL(10,8);
ALTER TABLE rescue_requests MODIFY COLUMN lng DECIMAL(11,8);

ALTER TABLE users
DROP COLUMN ice_contacts,
DROP COLUMN avatar;
