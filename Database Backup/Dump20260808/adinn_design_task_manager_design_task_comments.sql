-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: localhost    Database: adinn_design_task_manager
-- ------------------------------------------------------
-- Server version	8.0.46

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
-- Table structure for table `design_task_comments`
--

DROP TABLE IF EXISTS `design_task_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `design_task_comments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `design_task_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `status_at_comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `design_task_comments_user_id_foreign` (`user_id`),
  KEY `design_task_comments_idx` (`design_task_id`,`created_at`),
  CONSTRAINT `design_task_comments_design_task_id_foreign` FOREIGN KEY (`design_task_id`) REFERENCES `design_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `design_task_comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `design_task_comments`
--

LOCK TABLES `design_task_comments` WRITE;
/*!40000 ALTER TABLE `design_task_comments` DISABLE KEYS */;
INSERT INTO `design_task_comments` VALUES (1,7,6,'need_clarification','Please upload a clear photo of the Site','2026-08-07 07:59:07','2026-08-07 07:59:07'),(2,7,6,'need_clarification','Hello','2026-08-07 08:04:43','2026-08-07 08:04:43'),(3,7,6,'need_clarification','Please check this and verify','2026-08-07 08:06:25','2026-08-07 08:06:25'),(4,9,9,'review_analysis','hello','2026-08-08 06:17:28','2026-08-08 06:17:28'),(5,9,9,'review_analysis','Hellp','2026-08-08 06:24:00','2026-08-08 06:24:00'),(6,9,9,'review_analysis','Hello','2026-08-08 06:34:30','2026-08-08 06:34:30'),(7,9,9,'review_analysis','Hello','2026-08-08 06:38:19','2026-08-08 06:38:19'),(8,11,6,'review_analysis','Hello','2026-08-08 09:45:47','2026-08-08 09:45:47'),(9,13,6,'review_analysis','I have checked the requirements','2026-08-08 09:59:40','2026-08-08 09:59:40'),(10,13,6,'need_clarification','Clarification resolved','2026-08-08 10:05:57','2026-08-08 10:05:57');
/*!40000 ALTER TABLE `design_task_comments` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-08 16:11:16
