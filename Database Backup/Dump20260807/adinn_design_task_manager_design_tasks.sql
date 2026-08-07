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
-- Table structure for table `design_tasks`
--

DROP TABLE IF EXISTS `design_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `design_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `assigned_at` timestamp NOT NULL,
  `assigned_by` bigint unsigned NOT NULL,
  `task_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vertical` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_nature` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_type` enum('client','agency') COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mobile_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL,
  `due_at` timestamp NOT NULL,
  `designer_id` bigint unsigned NOT NULL,
  `total_creatives` int unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'assigned_tasks',
  `requirements` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `design_tasks_task_id_unique` (`task_id`),
  KEY `design_tasks_assigned_by_foreign` (`assigned_by`),
  KEY `design_tasks_vertical_task_nature_index` (`vertical`,`task_nature`),
  KEY `design_tasks_designer_id_status_index` (`designer_id`,`status`),
  CONSTRAINT `design_tasks_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  CONSTRAINT `design_tasks_designer_id_foreign` FOREIGN KEY (`designer_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `design_tasks`
--

LOCK TABLES `design_tasks` WRITE;
/*!40000 ALTER TABLE `design_tasks` DISABLE KEYS */;
INSERT INTO `design_tasks` VALUES (1,'DT-2026-00001','2026-08-06 09:16:49',1,'adinn','outdoor','mockup_requirements','client','adinn','adinn','1111111111','low','2026-08-06 11:15:00',2,1,'assigned_tasks','{\"board_size\": {\"unit\": \"feet\", \"width\": 24, \"height\": 12, \"square_feet\": 288}, \"description\": \"test\", \"mockup_type\": \"Mock-up\", \"website_link\": null}','2026-08-06 09:16:50','2026-08-06 09:16:50'),(2,'DT-2026-00002','2026-08-06 09:54:40',1,'adinn','outdoor','mockup_requirements','client','adinn','adinn','1212112121','low','2026-08-06 12:56:00',2,1,'assigned_tasks','{\"board_size\": {\"unit\": \"feet\", \"width\": 40, \"height\": 20, \"square_feet\": 800}, \"description\": \"tessta\", \"mockup_type\": \"Mock-up\", \"website_link\": null}','2026-08-06 09:54:40','2026-08-06 09:54:40'),(3,'DT-2026-00003','2026-08-06 11:06:02',1,'adinn','outdoor','mockup_requirements','client','adinn','adinn','9898989898','low','2026-08-06 12:05:00',2,1,'assigned_tasks','{\"creative\": \"design_task_manager/2026/outdoor/DT-2026-00003_adinn/mockup-requirements/creative/DT-2026-00003__creative__task-info-pipeline-history-tab__20260806-163603-752__01.jpeg\", \"board_size\": {\"unit\": \"feet\", \"width\": 80, \"height\": 55, \"square_feet\": 4400}, \"site_photo\": \"design_task_manager/2026/outdoor/DT-2026-00003_adinn/mockup-requirements/site_photo/DT-2026-00003__site-photo__all-task-conban-view__20260806-163602-963__01.jpeg\", \"description\": \"test\", \"mockup_type\": \"Mock-up\", \"website_link\": null}','2026-08-06 11:06:02','2026-08-06 11:06:03'),(4,'DT-2026-00004','2026-08-06 11:14:58',1,'adinn','outdoor','mockup_requirements','client','adinn','adinn','8989898989','low','2026-08-06 13:14:00',2,1,'assigned_tasks','{\"creative\": \"design_task_manager/2026/outdoor/DT-2026-00004_adinn/mockup-requirements/creative/DT-2026-00004__creative__all-task-conban-view__20260806-164459-426__01.jpeg\", \"board_size\": {\"unit\": \"feet\", \"width\": 80, \"height\": 40, \"square_feet\": 3200}, \"site_photo\": \"design_task_manager/2026/outdoor/DT-2026-00004_adinn/mockup-requirements/site_photo/DT-2026-00004__site-photo__task-info-comments-tab__20260806-164458-748__01.jpeg\", \"description\": \"test\", \"mockup_type\": \"Mock-up\", \"website_link\": null}','2026-08-06 11:14:58','2026-08-06 11:14:59'),(5,'DT-2026-00005','2026-08-06 12:09:00',1,'adinn','roadshow','creative_adaptation_requirements','client','adinn','adinn','9999999999','low','2026-08-07 12:08:00',6,1,'waiting_confirmation','{\"media\": \"LED\", \"location\": null, \"description\": \"ada\", \"vehicle_type\": \"Single-sided LED Vehicle\", \"roadshow_subtype\": \"Creative Adaptation\", \"vehicle_quantity\": null}','2026-08-06 12:09:00','2026-08-06 12:09:57'),(6,'DT-2026-00006','2026-08-06 12:28:56',1,'adinn','outdoor','mockup_requirements','client','adinn','adinn','1212121212','low','2026-08-08 14:28:00',6,1,'waiting_confirmation','{\"board_size\": {\"unit\": \"feet\", \"width\": 60, \"height\": 30, \"square_feet\": 1800}, \"description\": \"211312312\", \"mockup_type\": \"Mock-up\", \"website_link\": null}','2026-08-06 12:28:56','2026-08-06 12:55:41'),(7,'DT-2026-00007','2026-08-07 07:52:49',1,'Test -3','outdoor','mockup_requirements','client','Test -3','Test -3','9898989898','low','2026-08-07 10:51:00',9,1,'need_clarification','{\"board_size\": {\"unit\": \"feet\", \"width\": 40, \"height\": 20, \"square_feet\": 800}, \"site_photo\": \"design_task_manager/2026/outdoor/DT-2026-00007_test-3/mockup-requirements/site_photo/DT-2026-00007__site-photo__all-task-conban-view__20260807-132249-190__01.jpeg\", \"description\": \"Test -3\", \"mockup_type\": \"Mock-up\", \"website_link\": null}','2026-08-07 07:52:49','2026-08-07 11:39:45'),(8,'DT-2026-00008','2026-08-07 12:17:17',1,'Test -3 (Split)','outdoor','mockup_requirements','client','Test -3','Test -3','9898989898','low','2026-08-07 10:51:00',9,10,'waiting_confirmation','{\"board_size\": {\"unit\": \"feet\", \"width\": 40, \"height\": 20, \"square_feet\": 800}, \"site_photo\": \"design_task_manager/2026/outdoor/DT-2026-00007_test-3/mockup-requirements/site_photo/DT-2026-00007__site-photo__all-task-conban-view__20260807-132249-190__01.jpeg\", \"description\": \"Test -3\", \"mockup_type\": \"Mock-up\", \"website_link\": null}','2026-08-07 12:17:17','2026-08-07 12:18:51');
/*!40000 ALTER TABLE `design_tasks` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-07 18:14:44
