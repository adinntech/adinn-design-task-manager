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
-- Table structure for table `design_task_requests`
--

DROP TABLE IF EXISTS `design_task_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `design_task_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `design_task_id` bigint unsigned NOT NULL,
  `request_type` enum('decline','split','swap') COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `designer_head_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `designer_head_action_by` bigint unsigned DEFAULT NULL,
  `designer_head_action_at` timestamp NULL DEFAULT NULL,
  `admin_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_action_by` bigint unsigned DEFAULT NULL,
  `admin_action_at` timestamp NULL DEFAULT NULL,
  `overall_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_designer_head',
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_designer_id` bigint unsigned DEFAULT NULL,
  `approved_designer_id` bigint unsigned DEFAULT NULL,
  `split_details` json DEFAULT NULL,
  `attachments` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `design_task_requests_design_task_id_foreign` (`design_task_id`),
  KEY `design_task_requests_requested_by_foreign` (`requested_by`),
  KEY `design_task_requests_designer_head_action_by_foreign` (`designer_head_action_by`),
  KEY `design_task_requests_admin_action_by_foreign` (`admin_action_by`),
  KEY `design_task_requests_target_designer_id_foreign` (`target_designer_id`),
  KEY `design_task_requests_approved_designer_id_foreign` (`approved_designer_id`),
  CONSTRAINT `design_task_requests_admin_action_by_foreign` FOREIGN KEY (`admin_action_by`) REFERENCES `users` (`id`),
  CONSTRAINT `design_task_requests_approved_designer_id_foreign` FOREIGN KEY (`approved_designer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `design_task_requests_design_task_id_foreign` FOREIGN KEY (`design_task_id`) REFERENCES `design_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `design_task_requests_designer_head_action_by_foreign` FOREIGN KEY (`designer_head_action_by`) REFERENCES `users` (`id`),
  CONSTRAINT `design_task_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `design_task_requests_target_designer_id_foreign` FOREIGN KEY (`target_designer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `design_task_requests`
--

LOCK TABLES `design_task_requests` WRITE;
/*!40000 ALTER TABLE `design_task_requests` DISABLE KEYS */;
INSERT INTO `design_task_requests` VALUES (1,7,'swap',6,'approved',8,'2026-08-07 11:39:45','pending',NULL,NULL,'approved','testtt',9,NULL,'{\"notes\": \"\"}',NULL,'2026-08-07 10:51:05','2026-08-07 11:39:45'),(2,7,'split',9,'approved',5,'2026-08-07 12:17:17','pending',NULL,NULL,'approved','test',9,NULL,'{\"details\": \"test\", \"creative_count\": 10, \"created_task_id\": 8}',NULL,'2026-08-07 11:41:12','2026-08-07 12:17:17'),(3,9,'split',9,'approved',5,'2026-08-08 05:18:30','not_required',NULL,NULL,'approved','split the Task',6,NULL,'{\"details\": \"split\", \"creative_count\": 3, \"created_task_id\": 10, \"created_task_code\": \"DT-2026-00010\", \"original_remaining_creatives\": 3}',NULL,'2026-08-08 05:18:14','2026-08-08 05:18:30'),(4,11,'split',6,'approved',5,'2026-08-08 06:09:17','not_required',NULL,NULL,'approved','Split',9,9,'{\"details\": \"Split\", \"creative_count\": 3, \"created_task_id\": 12, \"created_task_code\": \"DT-2026-00012\", \"original_remaining_creatives\": 3}',NULL,'2026-08-08 06:08:50','2026-08-08 06:09:17'),(5,13,'split',6,'approved',5,'2026-08-08 10:10:42','not_required',NULL,NULL,'approved','I cannot finish within deadline',9,9,'{\"details\": \"kindly check the attached images\", \"creative_count\": 35, \"created_task_id\": 14, \"created_task_code\": \"DT-2026-00014\", \"original_remaining_creatives\": 35}','[\"design_task_manager/2026/outdoor/DT-2026-00013_test-7/mockup-requirements/requests/split/DT-2026-00013__request-split__all-task-conban-view__20260808-152604-771.jpeg\"]','2026-08-08 09:56:05','2026-08-08 10:10:42'),(6,9,'split',9,'rejected',5,'2026-08-08 10:15:42','not_required',NULL,NULL,'rejected','Cannot finish it within time',6,NULL,'{\"details\": \"I have finished 2\", \"creative_count\": 2}','[\"design_task_manager/2026/outdoor/DT-2026-00009_test-4/mockup-requirements/requests/split/DT-2026-00009__request-split__task-info-task-overview__20260808-154524-115.jpeg\"]','2026-08-08 10:15:24','2026-08-08 10:15:42'),(7,15,'swap',6,'rejected',5,'2026-08-08 10:23:33','not_required',NULL,NULL,'rejected','I want to swap as im occupied with multiple tasks',3,NULL,'{\"notes\": \"\"}',NULL,'2026-08-08 10:21:59','2026-08-08 10:23:33'),(8,16,'swap',9,'approved',5,'2026-08-08 10:29:29','not_required',NULL,NULL,'approved','Cannot complete it',6,6,'{\"notes\": \"\"}',NULL,'2026-08-08 10:28:40','2026-08-08 10:29:29');
/*!40000 ALTER TABLE `design_task_requests` ENABLE KEYS */;
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
