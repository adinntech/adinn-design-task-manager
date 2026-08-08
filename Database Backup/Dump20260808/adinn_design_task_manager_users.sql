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
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','designer','bd','designer_head') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'designer',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Demo BD','bd@adinn.com',NULL,'$2y$12$sP4nXd16pWSqFMSaPFKDg.GjI9Iuj8zZm4rbVtyz1pmW2Qq9r01Ru','bd',1,NULL,'2026-08-05 10:42:51','2026-08-07 09:12:55'),(2,'Aarav Designer','designer1@adinn.com',NULL,'$2y$12$C5P/hOm1lJsXnLZ6agkJjehVLHMa5wzod0Cz074/bgas/mhunJhkS','designer',1,NULL,'2026-08-05 10:42:51','2026-08-05 10:42:51'),(3,'Meera Designer','designer2@adinn.com',NULL,'$2y$12$38SSJw1T3z6bmE60FTXyxePRU4sh7VlQgeYH7AGRCtp7ut9uXz.1e','designer',1,NULL,'2026-08-05 10:42:51','2026-08-05 10:42:51'),(4,'Kavin Designer','designer3@adinn.com',NULL,'$2y$12$yZVLp.E9Rbu3r3WN/t5fI.PjeXC6CPFvQE/sDAD23Paolx.Z..ELS','designer',1,NULL,'2026-08-05 10:42:52','2026-08-05 10:42:52'),(5,'Designer Head','head@adinn.com',NULL,'$2y$12$mrrglsBuX8ksPi/SJj8XHefue4w2o7wQjWWXiswChn0MRdKBviawO','designer_head',1,NULL,'2026-08-05 10:42:52','2026-08-05 10:42:52'),(6,'Demo Designer','designer@adinn.com',NULL,'$2y$12$EakPdUOcnLk/6yR.T6u2YeENtdDS4dhjSRWTafdwM6GDuxjgInRTu','designer',1,NULL,'2026-08-06 12:03:23','2026-08-07 09:12:55'),(7,'Adinn Admin','admin@adinn.com',NULL,'$2y$12$4pi88JwqXWHI/sp.yZWOTeWlHNZ9BN38gPcSTL4br.YAuvSAjK8gK','admin',1,NULL,'2026-08-07 05:17:49','2026-08-07 09:12:55'),(8,'Designer Head','designerhead@adinn.com',NULL,'$2y$12$HUHkR8WnQwmcoyL7MR2v.OlZzElnYsLjSI5L7Z8XLvN7l.oZWrcFG','designer_head',1,NULL,'2026-08-07 09:12:57','2026-08-07 09:12:57'),(9,'Test 2','designer6@adinn.com',NULL,'$2y$12$KRQjMXpAKMFo/xf5nlbySeMD5RJKqb8QSdqSm2NbR9GU48r6t8gWu','designer',1,NULL,'2026-08-07 10:50:11','2026-08-07 10:50:11');
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

-- Dump completed on 2026-08-08 16:11:15
