-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: bmb_tournaments
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
-- Table structure for table `arena`
--

DROP TABLE IF EXISTS `arena`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arena` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `location` varchar(200) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `status` enum('available','maintenance','unavailable') DEFAULT 'available',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `arena`
--

LOCK TABLES `arena` WRITE;
/*!40000 ALTER TABLE `arena` DISABLE KEYS */;
INSERT INTO `arena` VALUES (1,'Sân Vip 1','Sân Dali Sports',20,'available','','2026-03-29 21:04:21');
/*!40000 ALTER TABLE `arena` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) DEFAULT NULL,
  `group_name` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tournament_id` (`tournament_id`),
  CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
INSERT INTO `groups` VALUES (29,1,'A','2026-03-24 22:02:42'),(30,1,'B','2026-03-24 22:02:42'),(31,1,'C','2026-03-24 22:02:42'),(32,1,'D','2026-03-24 22:02:43'),(33,5,'A','2026-03-29 20:55:13'),(34,5,'B','2026-03-29 20:55:13'),(35,4,'A','2026-03-30 01:52:11'),(36,4,'B','2026-03-30 01:52:11');
/*!40000 ALTER TABLE `groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `matches`
--

DROP TABLE IF EXISTS `matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `matches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_number` int(11) DEFAULT NULL,
  `tournament_id` int(11) DEFAULT NULL,
  `team1_id` int(11) DEFAULT NULL,
  `team2_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `round` varchar(50) DEFAULT NULL,
  `bracket_position` varchar(20) DEFAULT NULL,
  `match_type` enum('group','knockout','quarter','semi','final','live') DEFAULT 'group',
  `score1` int(11) DEFAULT NULL,
  `score2` int(11) DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `match_date` datetime DEFAULT NULL,
  `court` varchar(50) DEFAULT NULL,
  `arena_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `winning_score` int(11) DEFAULT 11,
  `first_server` int(11) DEFAULT 1,
  `server_team` int(11) DEFAULT 1,
  `server_hand` int(11) DEFAULT 1,
  `status` enum('pending','live','completed') DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tournament_id` (`tournament_id`),
  KEY `team1_id` (`team1_id`),
  KEY `team2_id` (`team2_id`),
  KEY `group_id` (`group_id`),
  KEY `winner_id` (`winner_id`),
  KEY `arena_id` (`arena_id`),
  CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`team1_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matches_ibfk_3` FOREIGN KEY (`team2_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matches_ibfk_4` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matches_ibfk_5` FOREIGN KEY (`winner_id`) REFERENCES `teams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `matches_ibfk_6` FOREIGN KEY (`arena_id`) REFERENCES `arena` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=945 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `matches`
--

LOCK TABLES `matches` WRITE;
/*!40000 ALTER TABLE `matches` DISABLE KEYS */;
INSERT INTO `matches` VALUES (802,NULL,1,21,24,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'live',NULL),(803,NULL,1,21,25,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'live',NULL),(804,NULL,1,21,4,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'live',NULL),(805,NULL,1,21,16,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'live',NULL),(806,NULL,1,21,10,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'live',NULL),(807,NULL,1,21,1,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(808,NULL,1,21,15,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(809,NULL,1,24,25,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(810,NULL,1,24,4,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(811,NULL,1,24,16,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(812,NULL,1,24,10,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(813,NULL,1,24,1,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(814,NULL,1,24,15,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(815,NULL,1,25,4,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(816,NULL,1,25,16,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(817,NULL,1,25,10,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(818,NULL,1,25,1,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(819,NULL,1,25,15,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(820,NULL,1,4,16,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(821,NULL,1,4,10,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(822,NULL,1,4,1,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(823,NULL,1,4,15,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(824,NULL,1,16,10,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(825,NULL,1,16,1,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(826,NULL,1,16,15,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(827,NULL,1,10,1,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(828,NULL,1,10,15,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(829,NULL,1,1,15,29,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(830,NULL,1,29,27,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(831,NULL,1,29,7,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(832,NULL,1,29,13,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(833,NULL,1,29,9,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(834,NULL,1,29,12,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(835,NULL,1,29,26,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(836,NULL,1,29,2,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(837,NULL,1,27,7,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(838,NULL,1,27,13,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(839,NULL,1,27,9,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(840,NULL,1,27,12,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(841,NULL,1,27,26,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(842,NULL,1,27,2,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(843,NULL,1,7,13,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(844,NULL,1,7,9,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(845,NULL,1,7,12,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(846,NULL,1,7,26,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(847,NULL,1,7,2,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(848,NULL,1,13,9,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(849,NULL,1,13,12,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(850,NULL,1,13,26,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(851,NULL,1,13,2,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(852,NULL,1,9,12,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(853,NULL,1,9,26,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(854,NULL,1,9,2,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(855,NULL,1,12,26,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(856,NULL,1,12,2,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(857,NULL,1,26,2,30,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(858,NULL,1,28,6,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(859,NULL,1,28,22,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(860,NULL,1,28,31,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(861,NULL,1,28,14,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(862,NULL,1,28,30,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(863,NULL,1,28,5,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(864,NULL,1,28,23,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(865,NULL,1,6,22,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:42',11,1,1,1,'pending',NULL),(866,NULL,1,6,31,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(867,NULL,1,6,14,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(868,NULL,1,6,30,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(869,NULL,1,6,5,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(870,NULL,1,6,23,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(871,NULL,1,22,31,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(872,NULL,1,22,14,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(873,NULL,1,22,30,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(874,NULL,1,22,5,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(875,NULL,1,22,23,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(876,NULL,1,31,14,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(877,NULL,1,31,30,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(878,NULL,1,31,5,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(879,NULL,1,31,23,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(880,NULL,1,14,30,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(881,NULL,1,14,5,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(882,NULL,1,14,23,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(883,NULL,1,30,5,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(884,NULL,1,30,23,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(885,NULL,1,5,23,31,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(886,NULL,1,17,20,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(887,NULL,1,17,19,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(888,NULL,1,17,8,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(889,NULL,1,17,18,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(890,NULL,1,17,11,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(891,NULL,1,17,3,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(892,NULL,1,20,19,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(893,NULL,1,20,8,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(894,NULL,1,20,18,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(895,NULL,1,20,11,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(896,NULL,1,20,3,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(897,NULL,1,19,8,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(898,NULL,1,19,18,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(899,NULL,1,19,11,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(900,NULL,1,19,3,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(901,NULL,1,8,18,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(902,NULL,1,8,11,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(903,NULL,1,8,3,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(904,NULL,1,18,11,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(905,NULL,1,18,3,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(906,NULL,1,11,3,32,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-24 22:02:43',11,1,1,1,'pending',NULL),(917,NULL,NULL,11,1,NULL,'Vòng 1/8',NULL,'group',0,0,1,NULL,NULL,NULL,'2026-03-29 20:55:01',11,1,2,2,'pending','2026-03-30 09:04:10'),(918,NULL,NULL,22,14,NULL,'Vòng 1/8',NULL,'group',0,0,22,NULL,NULL,NULL,'2026-03-29 20:55:01',11,1,1,1,'pending','2026-03-30 09:04:05'),(919,NULL,NULL,12,13,NULL,'Vòng 1/8',NULL,'group',0,0,NULL,NULL,NULL,NULL,'2026-03-29 20:55:01',11,1,1,1,'pending','2026-03-30 09:03:57'),(920,NULL,NULL,10,17,NULL,'Vòng 1/8',NULL,'group',0,0,NULL,NULL,NULL,NULL,'2026-03-29 20:55:01',11,1,1,1,'pending','2026-03-30 09:03:47'),(921,NULL,5,60,61,33,'Vòng bảng 1',NULL,'group',10,15,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'pending','2026-03-30 08:50:09'),(922,NULL,5,57,62,33,'Vòng bảng 1',NULL,'group',15,14,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'pending','2026-03-30 08:50:20'),(923,NULL,5,60,57,33,'Vòng bảng 2',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'pending',NULL),(924,NULL,5,60,61,33,'Vòng bảng 2',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'pending',NULL),(925,NULL,5,60,60,33,'Vòng bảng 3',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'pending',NULL),(926,NULL,5,60,62,33,'Vòng bảng 3',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'pending',NULL),(927,NULL,5,58,56,34,'Vòng bảng 1',NULL,'group',3,15,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'pending','2026-03-30 08:50:31'),(928,NULL,5,59,63,34,'Vòng bảng 1',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'live',NULL),(929,NULL,5,58,59,34,'Vòng bảng 2',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'pending',NULL),(930,NULL,5,58,56,34,'Vòng bảng 2',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'pending',NULL),(931,NULL,5,58,58,34,'Vòng bảng 3',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,1,'pending',NULL),(932,NULL,5,58,63,34,'Vòng bảng 3',NULL,'group',11,8,58,NULL,NULL,NULL,'2026-03-29 20:55:13',11,1,1,2,'completed',NULL),(933,NULL,4,49,52,35,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(934,NULL,4,49,48,35,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(935,NULL,4,49,50,35,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(936,NULL,4,52,48,35,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(937,NULL,4,52,50,35,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(938,NULL,4,48,50,35,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(939,NULL,4,51,53,36,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(940,NULL,4,51,55,36,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(941,NULL,4,51,54,36,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(942,NULL,4,53,55,36,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(943,NULL,4,53,54,36,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL),(944,NULL,4,55,54,36,'Vòng bảng',NULL,'group',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 01:52:11',11,1,1,1,'pending',NULL);
/*!40000 ALTER TABLE `matches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refereeassignments`
--

DROP TABLE IF EXISTS `refereeassignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refereeassignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `match_id` int(11) NOT NULL,
  `referee_id` int(11) NOT NULL,
  `assignment_type` enum('main','assistant') DEFAULT 'main',
  `status` enum('assigned','completed','cancelled') DEFAULT 'assigned',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_match_referee` (`match_id`,`referee_id`),
  KEY `referee_id` (`referee_id`),
  CONSTRAINT `refereeassignments_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `refereeassignments_ibfk_2` FOREIGN KEY (`referee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refereeassignments`
--

LOCK TABLES `refereeassignments` WRITE;
/*!40000 ALTER TABLE `refereeassignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `refereeassignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teams`
--

DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `team_name` varchar(100) NOT NULL,
  `player1` varchar(100) DEFAULT NULL,
  `player2` varchar(100) DEFAULT NULL,
  `skill_level` varchar(10) DEFAULT NULL,
  `group_name` varchar(1) DEFAULT NULL,
  `seed` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_team_tournament` (`team_name`,`tournament_id`),
  KEY `tournament_id` (`tournament_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `teams_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teams_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `tournamentcategories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teams`
--

LOCK TABLES `teams` WRITE;
/*!40000 ALTER TABLE `teams` DISABLE KEYS */;
INSERT INTO `teams` VALUES (1,1,NULL,'Đội 1','Lê Minh Đức','Nguyễn Hoàng Long','3.5',NULL,NULL,'2026-03-24 20:53:25'),(2,1,NULL,'Đội 2','Trần Văn A','Phạm Quốc B','3.5',NULL,NULL,'2026-03-24 20:53:25'),(3,1,NULL,'Đội 3','Vũ Minh Cường','Đỗ Xuân D','3.5',NULL,NULL,'2026-03-24 20:53:25'),(4,1,NULL,'Đội 4','Bùi Thị E','Hoàng Văn F','3.5',NULL,NULL,'2026-03-24 20:53:25'),(5,1,NULL,'Đội 5','Ngô Thị G','Lý Văn H','3.5',NULL,NULL,'2026-03-24 20:53:25'),(6,1,NULL,'Đội 6','Đặng Văn I','Vương Thị J','3.5',NULL,NULL,'2026-03-24 20:53:25'),(7,1,NULL,'Đội 7','Trịnh Văn K','Lê Thị L','3.5',NULL,NULL,'2026-03-24 20:53:25'),(8,1,NULL,'Đội 8','Phan Văn M','Nguyễn Thị N','3.5',NULL,NULL,'2026-03-24 20:53:25'),(9,1,NULL,'Đội 9','Hoàng Văn O','Trần Thị P','3.5',NULL,NULL,'2026-03-24 20:53:25'),(10,1,NULL,'Đội 10','Lê Văn Q','Phạm Văn R','3.5',NULL,NULL,'2026-03-24 20:53:25'),(11,1,NULL,'Đội 11','Vũ Văn S','Đỗ Văn T','3.5',NULL,NULL,'2026-03-24 20:53:25'),(12,1,NULL,'Đội 12','Nguyễn Văn U','Bùi Văn V','3.5',NULL,NULL,'2026-03-24 20:53:25'),(13,1,NULL,'Đội 13','Hoàng Thị X','Ngô Văn Y','3.5',NULL,NULL,'2026-03-24 20:53:25'),(14,1,NULL,'Đội 14','Trần Văn Z','Lê Thị AA','3.5',NULL,NULL,'2026-03-24 20:53:25'),(15,1,NULL,'Đội 15','Phạm Văn AB','Vũ Văn AC','3.5',NULL,NULL,'2026-03-24 20:53:25'),(16,1,NULL,'Đội 16','Đỗ Thị AD','Nguyễn Văn AE','3.5',NULL,NULL,'2026-03-24 20:53:25'),(17,1,NULL,'Đội 17','Lý Văn AF','Trần Văn AG','3.5',NULL,NULL,'2026-03-24 20:53:25'),(18,1,NULL,'Đội 18','Vương Văn AH','Phạm Văn AI','3.5',NULL,NULL,'2026-03-24 20:53:25'),(19,1,NULL,'Đội 19','Trịnh Thị AJ','Hoàng Văn AK','3.5',NULL,NULL,'2026-03-24 20:53:25'),(20,1,NULL,'Đội 20','Nguyễn Thị AL','Lê Văn AM','3.5',NULL,NULL,'2026-03-24 20:53:25'),(21,1,NULL,'Đội 21','Bùi Văn AN','Vũ Thị AO','3.5',NULL,NULL,'2026-03-24 20:53:25'),(22,1,NULL,'Đội 22','Đặng Văn AP','Ngô Văn AQ','3.5',NULL,NULL,'2026-03-24 20:53:25'),(23,1,NULL,'Đội 23','Phan Văn AR','Trịnh Văn AS','3.5',NULL,NULL,'2026-03-24 20:53:25'),(24,1,NULL,'Đội 24','Hoàng Thị AT','Lý Văn AU','3.5',NULL,NULL,'2026-03-24 20:53:25'),(25,1,NULL,'Đội 25','Nguyễn Văn AV','Phạm Thị AW','3.5',NULL,NULL,'2026-03-24 20:53:25'),(26,1,NULL,'Đội 26','Trần Văn AX','Vũ Văn AY','3.5',NULL,NULL,'2026-03-24 20:53:25'),(27,1,NULL,'Đội 27','Lê Văn AZ','Đỗ Văn BA','3.5',NULL,NULL,'2026-03-24 20:53:25'),(28,1,NULL,'Đội 28','Vũ Thị BB','Nguyễn Văn BC','3.5',NULL,NULL,'2026-03-24 20:53:25'),(29,1,NULL,'Đội 29','Ngô Văn BD','Hoàng Văn BE','3.5',NULL,NULL,'2026-03-24 20:53:25'),(30,1,NULL,'Đội 30','Trần Thị BF','Phạm Văn BG','3.5',NULL,NULL,'2026-03-24 20:53:25'),(31,1,NULL,'Đội 31','Đặng Văn BH','Bùi Thị BI','3.5',NULL,NULL,'2026-03-24 20:53:25'),(40,3,NULL,'Đội 1','VĐV 1','VĐV 2','4.0',NULL,NULL,'2026-03-24 21:33:31'),(41,3,NULL,'Đội 2','VĐV 3','VĐV 4','4.0',NULL,NULL,'2026-03-24 21:33:31'),(42,3,NULL,'Đội 3','VĐV 5','VĐV 6','4.0',NULL,NULL,'2026-03-24 21:33:31'),(43,3,NULL,'Đội 4','VĐV 7','VĐV 8','4.0',NULL,NULL,'2026-03-24 21:33:31'),(44,3,NULL,'Đội 5','VĐV 9','VĐV 10','4.0',NULL,NULL,'2026-03-24 21:33:31'),(45,3,NULL,'Đội 6','VĐV 11','VĐV 12','4.0',NULL,NULL,'2026-03-24 21:33:31'),(46,3,NULL,'Đội 7','VĐV 13','VĐV 14','4.0',NULL,NULL,'2026-03-24 21:33:31'),(47,3,NULL,'Đội 8','VĐV 15','VĐV 16','4.0',NULL,NULL,'2026-03-24 21:33:31'),(48,4,NULL,'Đội 1','VĐV 1','VĐV 2','4.0',NULL,NULL,'2026-03-24 21:34:06'),(49,4,NULL,'Đội 2','VĐV 3','VĐV 4','4.0',NULL,NULL,'2026-03-24 21:34:06'),(50,4,NULL,'Đội 3','VĐV 5','VĐV 6','4.0',NULL,NULL,'2026-03-24 21:34:06'),(51,4,NULL,'Đội 4','VĐV 7','VĐV 8','4.0',NULL,NULL,'2026-03-24 21:34:06'),(52,4,NULL,'Đội 5','VĐV 9','VĐV 10','4.0',NULL,NULL,'2026-03-24 21:34:06'),(53,4,NULL,'Đội 6','VĐV 11','VĐV 12','4.0',NULL,NULL,'2026-03-24 21:34:06'),(54,4,NULL,'Đội 7','VĐV 13','VĐV 14','4.0',NULL,NULL,'2026-03-24 21:34:06'),(55,4,NULL,'Đội 8','VĐV 15','VĐV 16','4.0',NULL,NULL,'2026-03-24 21:34:06'),(56,5,NULL,'Đội 1','VĐV 1.1','VĐV 1.2','4.0',NULL,NULL,'2026-03-24 21:34:23'),(57,5,NULL,'Đội 2','VĐV 2.1','VĐV 2.2','4.0',NULL,NULL,'2026-03-24 21:34:23'),(58,5,NULL,'Đội 3','VĐV 3.1','VĐV 3.2','4.0',NULL,NULL,'2026-03-24 21:34:23'),(59,5,NULL,'Đội 4','VĐV 4.1','VĐV 4.2','4.0',NULL,NULL,'2026-03-24 21:34:23'),(60,5,NULL,'Đội 5','VĐV 5.1','VĐV 5.2','4.0',NULL,NULL,'2026-03-24 21:34:23'),(61,5,NULL,'Đội 6','VĐV 6.1','VĐV 6.2','4.0',NULL,NULL,'2026-03-24 21:34:23'),(62,5,NULL,'Đội 7','VĐV 7.1','VĐV 7.2','4.0',NULL,NULL,'2026-03-24 21:34:23'),(63,5,NULL,'Đội 8','VĐV 8.1','VĐV 8.2','4.0',NULL,NULL,'2026-03-24 21:34:23');
/*!40000 ALTER TABLE `teams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tournamentcategories`
--

DROP TABLE IF EXISTS `tournamentcategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournamentcategories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `gender` enum('male','female','mixed','all') DEFAULT 'all',
  `skill_level` varchar(20) DEFAULT NULL,
  `skill_min` float DEFAULT 0,
  `skill_max` float DEFAULT 5.5,
  `age_min` int(11) DEFAULT 0,
  `age_max` int(11) DEFAULT 99,
  `max_teams` int(11) DEFAULT 16,
  `registration_deadline` date DEFAULT NULL,
  `current_teams` int(11) DEFAULT 0,
  `status` enum('open','closed','completed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tournament_id` (`tournament_id`),
  CONSTRAINT `tournamentcategories_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tournamentcategories`
--

LOCK TABLES `tournamentcategories` WRITE;
/*!40000 ALTER TABLE `tournamentcategories` DISABLE KEYS */;
INSERT INTO `tournamentcategories` VALUES (1,6,'Nam Doubles',NULL,'male','3.5',0,5.5,0,99,16,NULL,0,'open','2026-03-29 21:27:16'),(2,6,'Nữ Doubles',NULL,'female','3.5',0,5.5,0,99,16,NULL,0,'open','2026-03-29 21:27:16'),(3,6,'Mixed Doubles',NULL,'mixed','3.5',0,5.5,0,99,16,NULL,0,'open','2026-03-29 21:27:16');
/*!40000 ALTER TABLE `tournamentcategories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tournamentmanagers`
--

DROP TABLE IF EXISTS `tournamentmanagers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournamentmanagers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_level` enum('full','limited') DEFAULT 'full',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_manager_tournament` (`tournament_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `tournamentmanagers_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tournamentmanagers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tournamentmanagers`
--

LOCK TABLES `tournamentmanagers` WRITE;
/*!40000 ALTER TABLE `tournamentmanagers` DISABLE KEYS */;
/*!40000 ALTER TABLE `tournamentmanagers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tournamentregistrations`
--

DROP TABLE IF EXISTS `tournamentregistrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournamentregistrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `team_name` varchar(100) NOT NULL,
  `player1_name` varchar(100) NOT NULL,
  `player1_phone` varchar(20) NOT NULL,
  `player1_dob` date DEFAULT NULL,
  `player2_name` varchar(100) NOT NULL,
  `player2_phone` varchar(20) NOT NULL,
  `player2_dob` date DEFAULT NULL,
  `skill_level` varchar(20) DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tournament_id` (`tournament_id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `tournamentregistrations_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tournamentregistrations_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `tournamentcategories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tournamentregistrations`
--

LOCK TABLES `tournamentregistrations` WRITE;
/*!40000 ALTER TABLE `tournamentregistrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `tournamentregistrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tournaments`
--

DROP TABLE IF EXISTS `tournaments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `format` enum('round_robin','knockout','combined','double_elimination') DEFAULT 'combined',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `registration_deadline` date DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `total_teams` int(11) DEFAULT 0,
  `max_teams` int(11) DEFAULT 32,
  `status` enum('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  `stage` enum('planning','registration','setup','group_stage','knockout_stage','completed') DEFAULT 'planning',
  `owner_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `winning_score` int(11) DEFAULT 11,
  PRIMARY KEY (`id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `tournaments_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tournaments`
--

LOCK TABLES `tournaments` WRITE;
/*!40000 ALTER TABLE `tournaments` DISABLE KEYS */;
INSERT INTO `tournaments` VALUES (1,'Giải Pickleball Mở Rộng 2026','Giải đấu pickleball 2v2 dành cho tất cả các vận động viên','combined','2026-04-15',NULL,NULL,'Sân Pickleball TP.HCM',0,32,'upcoming','completed',NULL,'2026-03-24 20:53:25',11),(3,'Giải Knockout 8 Đội',NULL,'knockout',NULL,NULL,NULL,NULL,0,32,'ongoing','group_stage',NULL,'2026-03-24 21:33:31',11),(4,'Giải Knockout 8 Đội',NULL,'knockout',NULL,NULL,NULL,NULL,0,32,'ongoing','group_stage',NULL,'2026-03-24 21:34:06',11),(5,'Giải Knockout Test',NULL,'knockout',NULL,NULL,NULL,NULL,0,32,'ongoing','group_stage',NULL,'2026-03-24 21:34:23',11),(6,'Giải Pickleball Dali Sports 2026','Giải Pickleball Dali Sports 2026','combined','2026-03-31','2026-03-31',NULL,'Sân Dali Sports',0,32,'upcoming','registration',NULL,'2026-03-29 21:18:41',11);
/*!40000 ALTER TABLE `tournaments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tournamentschedule`
--

DROP TABLE IF EXISTS `tournamentschedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tournamentschedule` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tournament_id` int(11) NOT NULL,
  `match_id` int(11) DEFAULT NULL,
  `court_id` int(11) DEFAULT NULL,
  `scheduled_time` time NOT NULL,
  `scheduled_date` date NOT NULL,
  `actual_start_time` datetime DEFAULT NULL,
  `actual_end_time` datetime DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tournament_id` (`tournament_id`),
  KEY `match_id` (`match_id`),
  KEY `court_id` (`court_id`),
  CONSTRAINT `tournamentschedule_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tournamentschedule_ibfk_2` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tournamentschedule_ibfk_3` FOREIGN KEY (`court_id`) REFERENCES `arena` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tournamentschedule`
--

LOCK TABLES `tournamentschedule` WRITE;
/*!40000 ALTER TABLE `tournamentschedule` DISABLE KEYS */;
/*!40000 ALTER TABLE `tournamentschedule` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','referee','user') DEFAULT 'user',
  `tournament_permissions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Quß║ún Trß╗ï Vi├¬n',NULL,NULL,'admin',NULL,1,'2026-03-24 20:39:28'),(2,'admin123','$2y$10$acQ5lhyzPC4GCuz46tet1OXC.am03an.rQajsL.zoOm0n2yyB6/lm','mạnh nguyễn',NULL,NULL,'admin',NULL,1,'2026-03-29 20:28:23');
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

-- Dump completed on 2026-03-30 11:10:00
