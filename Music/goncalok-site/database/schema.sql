-- ============================================================
-- GONCA LÖK — Taze kurulum şeması
-- Kullanım: mysql -u root -p DB_ADI < database/schema.sql
-- Ardından: php migrate.php --owner-email=sizin@email.com --owner-name="Adınız"
-- (migrate.php; owner kullanıcısını ve kalan ayarları idempotent kurar)
-- Not: Tablolar yeniden kurulur (DROP + CREATE). Yedeğiniz olsun.
-- ============================================================
SET FOREIGN_KEY_CHECKS=0;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `actions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `session_id` int DEFAULT NULL,
  `goal_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `due_date` date DEFAULT NULL,
  `status` enum('open','in_progress','done','overdue') NOT NULL DEFAULT 'open',
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_actions_student` (`student_id`,`status`),
  KEY `idx_actions_due` (`due_date`),
  KEY `idx_actions_org` (`organization_id`),
  CONSTRAINT `fk_actions_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_busy_slots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `busy_date` date NOT NULL,
  `busy_time` time NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_busy_slot` (`busy_date`,`busy_time`),
  KEY `idx_admin_busy_slots_org` (`organization_id`),
  CONSTRAINT `fk_admin_busy_slots_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `kind` enum('university','internship','job','other') NOT NULL DEFAULT 'university',
  `organization` varchar(255) NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('preparing','submitted','interview','accepted','rejected') NOT NULL DEFAULT 'preparing',
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_applications_student` (`student_id`,`status`),
  KEY `idx_applications_org` (`organization_id`),
  CONSTRAINT `fk_applications_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `coaching_type` varchar(100) NOT NULL DEFAULT 'Öğrenci Koçluğu',
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  `student_id` int DEFAULT NULL,
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_appt_slot` (`appointment_date`,`appointment_time`,`status`),
  KEY `idx_appt_student` (`student_id`),
  KEY `idx_appointments_org` (`organization_id`),
  CONSTRAINT `fk_appointments_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `actor_name` varchar(150) NOT NULL DEFAULT 'Admin',
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` int DEFAULT NULL,
  `details` text,
  `ip` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_logs_org` (`organization_id`),
  CONSTRAINT `fk_audit_logs_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blog_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `slug` varchar(191) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'Öğrenci Koçluğu',
  `image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `reading_time` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_blog_posts_org` (`organization_id`),
  CONSTRAINT `fk_blog_posts_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `career_profiles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `target_university` varchar(255) DEFAULT NULL,
  `target_department` varchar(255) DEFAULT NULL,
  `target_role` varchar(255) DEFAULT NULL,
  `skills` text,
  `interests` text,
  `cv_path` varchar(255) DEFAULT NULL,
  `notes` text,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `idx_career_profiles_org` (`organization_id`),
  CONSTRAINT `fk_career_profiles_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `consents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `consent_type` varchar(100) NOT NULL DEFAULT 'kvkk',
  `text_version` varchar(50) NOT NULL DEFAULT '1.0',
  `granted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(64) DEFAULT NULL,
  `withdrawn_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_consents_student` (`student_id`),
  KEY `idx_consents_org` (`organization_id`),
  CONSTRAINT `fk_consents_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `subject_id` int DEFAULT NULL,
  `exam_name` varchar(255) NOT NULL,
  `exam_date` date NOT NULL,
  `score` decimal(6,2) DEFAULT NULL,
  `target_score` decimal(6,2) DEFAULT NULL,
  `max_score` decimal(6,2) NOT NULL DEFAULT '100.00',
  `note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exams_student_date` (`student_id`,`exam_date`),
  KEY `idx_exams_org` (`organization_id`),
  CONSTRAINT `fk_exams_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_metrics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `goal_id` int NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_metrics_goal` (`goal_id`,`recorded_at`),
  KEY `idx_goal_metrics_org` (`organization_id`),
  CONSTRAINT `fk_goal_metrics_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `goals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `session_id` int DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT 'Akademik',
  `description` text,
  `target_date` date DEFAULT NULL,
  `status` enum('active','completed','paused') NOT NULL DEFAULT 'active',
  `progress` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_goals_student` (`student_id`,`status`),
  KEY `idx_goals_org` (`organization_id`),
  CONSTRAINT `fk_goals_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `interventions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `flag_id` int DEFAULT NULL,
  `action` text NOT NULL,
  `owner` varchar(150) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('open','done') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_interventions_student` (`student_id`,`status`),
  KEY `idx_interventions_org` (`organization_id`),
  CONSTRAINT `fk_interventions_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lessons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `subject_id` int DEFAULT NULL,
  `lesson_date` date NOT NULL,
  `topic` varchar(255) NOT NULL,
  `duration_min` int NOT NULL DEFAULT '60',
  `status` enum('planned','done','cancelled') NOT NULL DEFAULT 'planned',
  `coach_note` text,
  `student_feedback` text,
  `homework` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lessons_student_date` (`student_id`,`lesson_date`),
  KEY `idx_lessons_org` (`organization_id`),
  CONSTRAINT `fk_lessons_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int DEFAULT NULL,
  `kind` varchar(100) NOT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text,
  `status` enum('sent','failed','skipped') NOT NULL DEFAULT 'skipped',
  `error` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_student` (`student_id`,`created_at`),
  KEY `idx_notifications_created` (`created_at`),
  KEY `idx_notification_log_org` (`organization_id`),
  CONSTRAINT `fk_notification_log_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `role` enum('student','guardian') NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_portal_token` (`token_hash`),
  KEY `idx_portal_tokens_student_role` (`student_id`,`role`,`active`),
  KEY `idx_portal_tokens_org` (`organization_id`),
  CONSTRAINT `fk_portal_tokens_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_limits` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `bucket` varchar(64) NOT NULL,
  `identifier` varchar(190) NOT NULL,
  `hits` int NOT NULL DEFAULT '1',
  `window_start` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rate` (`bucket`,`identifier`,`window_start`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `risk_flags` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `rule` varchar(100) NOT NULL,
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `message` text NOT NULL,
  `triggered_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('open','reviewed','resolved') NOT NULL DEFAULT 'open',
  `resolution_note` text,
  `resolved_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_risk_student_status` (`student_id`,`status`),
  KEY `idx_risk_rule_time` (`student_id`,`rule`,`triggered_at`),
  KEY `idx_risk_flags_org` (`organization_id`),
  CONSTRAINT `fk_risk_flags_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_migrations` (
  `version` varchar(80) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `session_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `name` varchar(150) NOT NULL,
  `type` varchar(100) NOT NULL DEFAULT 'Düzenli Seans',
  `fields` longtext,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session_templates_org` (`organization_id`),
  CONSTRAINT `fk_session_templates_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `appointment_id` int DEFAULT NULL,
  `template_id` int DEFAULT NULL,
  `session_date` date NOT NULL,
  `session_time` time NOT NULL,
  `session_type` varchar(100) NOT NULL DEFAULT 'Düzenli Seans',
  `status` enum('planned','completed','cancelled','no_show') NOT NULL DEFAULT 'planned',
  `duration_min` int NOT NULL DEFAULT '60',
  `mood_before` tinyint DEFAULT NULL,
  `mood_after` tinyint DEFAULT NULL,
  `shared_note` text,
  `private_note` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_student_date` (`student_id`,`session_date`),
  KEY `idx_sessions_status` (`status`),
  KEY `idx_sessions_org` (`organization_id`),
  CONSTRAINT `fk_sessions_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL,
  `grade_level` varchar(100) DEFAULT NULL,
  `coaching_type` varchar(100) NOT NULL DEFAULT 'Öğrenci Koçluğu',
  `status` enum('active','at_risk','passive','graduate') NOT NULL DEFAULT 'active',
  `risk_level` enum('low','medium','high') NOT NULL DEFAULT 'low',
  `guardian_name` varchar(150) DEFAULT NULL,
  `guardian_phone` varchar(50) DEFAULT NULL,
  `guardian_email` varchar(255) DEFAULT NULL,
  `notes` text,
  `consent_at` datetime DEFAULT NULL,
  `consent_version` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_code` (`student_code`),
  KEY `idx_students_status` (`status`),
  KEY `idx_students_phone` (`phone`),
  KEY `idx_students_org` (`organization_id`),
  CONSTRAINT `fk_students_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL DEFAULT '1',
  `student_id` int NOT NULL,
  `name` varchar(150) NOT NULL,
  `teacher` varchar(150) DEFAULT NULL,
  `term` varchar(100) DEFAULT NULL,
  `target_grade` varchar(50) DEFAULT NULL,
  `current_grade` varchar(50) DEFAULT NULL,
  `status` enum('active','completed','paused') NOT NULL DEFAULT 'active',
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subjects_student` (`student_id`,`status`),
  KEY `idx_subjects_org` (`organization_id`),
  CONSTRAINT `fk_subjects_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `organization_id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('owner','coach','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'coach',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_org` (`organization_id`),
  CONSTRAINT `fk_users_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
SET FOREIGN_KEY_CHECKS=1;

-- Varsayılan organizasyon (tek pratikli kurulum). Çok org için satır ekleyin.
INSERT IGNORE INTO organizations (id, name, slug) VALUES (1, 'Gonca LÖK', 'gonca-lok');
