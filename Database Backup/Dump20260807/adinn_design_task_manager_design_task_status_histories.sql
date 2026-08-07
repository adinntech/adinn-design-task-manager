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
-- Table structure for table `design_task_status_histories`
--

DROP TABLE IF EXISTS `design_task_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `design_task_status_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `design_task_id` bigint unsigned NOT NULL,
  `from_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` bigint unsigned NOT NULL,
  `change_source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'designer',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `design_task_status_histories_changed_by_foreign` (`changed_by`),
  KEY `design_task_status_history_idx` (`design_task_id`,`created_at`),
  CONSTRAINT `design_task_status_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `design_task_status_histories_design_task_id_foreign` FOREIGN KEY (`design_task_id`) REFERENCES `design_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `design_task_status_histories`
--

LOCK TABLES `design_task_status_histories` WRITE;
/*!40000 ALTER TABLE `design_task_status_histories` DISABLE KEYS */;
INSERT INTO `design_task_status_histories` VALUES (1,5,'assigned_tasks','review_analysis',6,'kanban_drag',NULL,'2026-08-06 12:09:37','2026-08-06 12:09:37'),(2,5,'review_analysis','need_clarification',6,'kanban_drag',NULL,'2026-08-06 12:09:50','2026-08-06 12:09:50'),(3,5,'need_clarification','in_progress',6,'kanban_drag',NULL,'2026-08-06 12:09:54','2026-08-06 12:09:54'),(4,5,'in_progress','waiting_confirmation',6,'kanban_drag',NULL,'2026-08-06 12:09:57','2026-08-06 12:09:57'),(5,6,'assigned_tasks','review_analysis',6,'kanban_drag',NULL,'2026-08-06 12:55:24','2026-08-06 12:55:24'),(6,6,'review_analysis','yet_to_start',6,'kanban_drag',NULL,'2026-08-06 12:55:28','2026-08-06 12:55:28'),(7,6,'yet_to_start','in_progress',6,'kanban_drag',NULL,'2026-08-06 12:55:38','2026-08-06 12:55:38'),(8,6,'in_progress','waiting_confirmation',6,'kanban_drag',NULL,'2026-08-06 12:55:41','2026-08-06 12:55:41'),(9,7,'assigned_tasks','review_analysis',6,'detail_button',NULL,'2026-08-07 07:53:46','2026-08-07 07:53:46'),(10,7,'review_analysis','need_clarification',6,'kanban_drag',NULL,'2026-08-07 07:54:02','2026-08-07 07:54:02'),(11,8,'assigned_tasks','review_analysis',9,'kanban_drag',NULL,'2026-08-07 12:18:39','2026-08-07 12:18:39'),(12,8,'review_analysis','need_clarification',9,'kanban_drag',NULL,'2026-08-07 12:18:41','2026-08-07 12:18:41'),(13,8,'need_clarification','yet_to_start',9,'kanban_drag',NULL,'2026-08-07 12:18:49','2026-08-07 12:18:49'),(14,8,'yet_to_start','in_progress',9,'kanban_drag',NULL,'2026-08-07 12:18:50','2026-08-07 12:18:50'),(15,8,'in_progress','waiting_confirmation',9,'kanban_drag',NULL,'2026-08-07 12:18:52','2026-08-07 12:18:52');
/*!40000 ALTER TABLE `design_task_status_histories` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-07 18:14:45
