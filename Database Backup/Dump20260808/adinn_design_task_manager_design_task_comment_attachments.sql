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
-- Table structure for table `design_task_comment_attachments`
--

DROP TABLE IF EXISTS `design_task_comment_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `design_task_comment_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `design_task_comment_id` bigint unsigned NOT NULL,
  `disk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'spaces',
  `path` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `design_task_comment_attachments_design_task_comment_id_foreign` (`design_task_comment_id`),
  CONSTRAINT `design_task_comment_attachments_design_task_comment_id_foreign` FOREIGN KEY (`design_task_comment_id`) REFERENCES `design_task_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `design_task_comment_attachments`
--

LOCK TABLES `design_task_comment_attachments` WRITE;
/*!40000 ALTER TABLE `design_task_comment_attachments` DISABLE KEYS */;
INSERT INTO `design_task_comment_attachments` VALUES (1,6,'spaces','design_task_manager/2026/outdoor/DT-2026-00009_test-4/mockup-requirements/comments/comment-6/DT-2026-00009__comment-00006__all-task-conban-view__20260808-120430-810__01.jpeg','All task- Conban view.jpeg','image/jpeg',64892,'2026-08-08 06:34:31','2026-08-08 06:34:31'),(2,7,'spaces','design_task_manager/2026/outdoor/DT-2026-00009_test-4/mockup-requirements/comments/comment-7/DT-2026-00009__comment-00007__all-task-conban-view__20260808-120819-422__01.jpeg','All task- Conban view.jpeg','image/jpeg',64892,'2026-08-08 06:38:19','2026-08-08 06:38:19'),(3,7,'spaces','design_task_manager/2026/outdoor/DT-2026-00009_test-4/mockup-requirements/comments/comment-7/DT-2026-00009__comment-00007__task-info-pipeline-history-tab__20260808-120819-969__02.jpeg','Task info- Pipeline history tab.jpeg','image/jpeg',99051,'2026-08-08 06:38:20','2026-08-08 06:38:20'),(4,8,'spaces','design_task_manager/2026/outdoor/DT-2026-00011_test-5/creative-adaptation/comments/comment-8/DT-2026-00011__comment-00008__all-task-conban-view__20260808-151547-258__01.jpeg','All task- Conban view.jpeg','image/jpeg',64892,'2026-08-08 09:45:47','2026-08-08 09:45:47'),(5,9,'spaces','design_task_manager/2026/outdoor/DT-2026-00013_test-7/mockup-requirements/comments/comment-9/DT-2026-00013__comment-00009__all-task-conban-view__20260808-152940-865__01.jpeg','All task- Conban view.jpeg','image/jpeg',64892,'2026-08-08 09:59:41','2026-08-08 09:59:41');
/*!40000 ALTER TABLE `design_task_comment_attachments` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-08 16:11:15
