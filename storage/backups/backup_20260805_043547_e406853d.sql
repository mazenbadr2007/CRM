-- CRM database backup (PHP fallback dump)
-- Generated: 2026-08-05 04:35:47
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `api_keys`;
CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `label` varchar(150) DEFAULT NULL,
  `key_prefix` varchar(20) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `status` enum('active','revoked') NOT NULL DEFAULT 'active',
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `key_prefix` (`key_prefix`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `api_keys` (`id`, `company_id`, `label`, `key_prefix`, `key_hash`, `status`, `last_used_at`, `created_at`) VALUES ('1', '4', 'CRM', 'crmapi_b72818a', '$2y$10$YaU5Emw6OGygroIGEkmLdudzAdAdjtN8XPtji4tT5nS5goWtost4S', 'revoked', NULL, '2026-08-03 21:44:16');
INSERT INTO `api_keys` (`id`, `company_id`, `label`, `key_prefix`, `key_hash`, `status`, `last_used_at`, `created_at`) VALUES ('2', '4', 'CRM', 'crmapi_561bb40', '$2y$10$znGXtavEU1tT4FEgPazye.BMA/JK/G54JXRMu9F2HzqV9QdEfLOWi', 'revoked', NULL, '2026-08-03 21:53:08');

DROP TABLE IF EXISTS `client_activity_log`;
CREATE TABLE `client_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `employee_name` varchar(100) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `client_documents`;
CREATE TABLE `client_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category` varchar(20) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `client_reminders`;
CREATE TABLE `client_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `reminder_date` date DEFAULT NULL,
  `reminder_time` time DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `client_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `project_location` varchar(100) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `unit_area` int(11) DEFAULT NULL,
  `the_role` varchar(50) NOT NULL,
  `receiving` varchar(50) NOT NULL,
  `unit_price` decimal(15,2) DEFAULT NULL,
  `down_payment` decimal(15,2) DEFAULT NULL,
  `installment` varchar(50) NOT NULL,
  `installment_amount` decimal(15,2) DEFAULT NULL,
  `visit` varchar(50) NOT NULL,
  `call_outcome` varchar(50) DEFAULT NULL,
  `visit_outcome` varchar(50) DEFAULT NULL,
  `final_status` varchar(50) DEFAULT NULL,
  `pipeline_stage` varchar(50) NOT NULL DEFAULT 'New Lead',
  `event_title` varchar(255) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_time` time DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL,
  `lead_source` varchar(50) NOT NULL DEFAULT 'Website',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `subscription_start` date DEFAULT NULL,
  `subscription_end` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `max_users` int(11) DEFAULT NULL,
  `storage_limit_gb` decimal(10,2) DEFAULT NULL,
  `plan_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `company_ai_settings`;
CREATE TABLE `company_ai_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `api_key` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `company_ai_settings` (`id`, `company_id`, `enabled`, `api_key`, `updated_at`) VALUES ('1', '4', '1', 'crmapi_561bb40765e60054cc0843f44d9108644412cd2f5ba768f3', '2026-08-03 21:53:22');

DROP TABLE IF EXISTS `company_settings`;
CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `display_name` varchar(150) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `theme_color` varchar(20) NOT NULL DEFAULT '#0d6efd',
  `contact_email` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_address` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `company_settings` (`id`, `company_id`, `display_name`, `logo_path`, `theme_color`, `contact_email`, `contact_phone`, `contact_address`, `updated_at`) VALUES ('1', '4', 'masria', 'company_4_e600307d356d6b8c.png', '#0d6efd', 'masria@gmail.com', '+201065740316', '123 ard el lewaa', '2026-08-03 02:01:33');

DROP TABLE IF EXISTS `db_backups`;
CREATE TABLE `db_backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `size_bytes` bigint(20) NOT NULL DEFAULT 0,
  `type` enum('manual','auto') NOT NULL DEFAULT 'manual',
  `status` enum('success','failed') NOT NULL DEFAULT 'success',
  `triggered_by` varchar(150) DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `filename` (`filename`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `developer_projects`;
CREATE TABLE `developer_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `developer_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `developer_id` (`developer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `developers`;
CREATE TABLE `developers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `developers` (`id`, `company_id`, `user_id`, `name`, `phone`, `email`, `notes`, `created_at`, `updated_at`) VALUES ('1', '4', '12', 'mazen', '01065740316', 'mazeenbadrr@gmail.com', 'ل', '2026-08-05 04:48:08', '2026-08-05 05:08:35');

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text DEFAULT NULL,
  `ref_date` date DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_done` tinyint(1) NOT NULL DEFAULT 0,
  `snoozed_until` datetime DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `client_id` (`client_id`),
  KEY `type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `password_resets` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES ('1', '18', 'fc9ea61e4235bbf0299ad6a5a49c27cd46efa8d956ec8b391fece637d51a4268', '2026-08-03 23:30:33', NULL, '2026-08-03 23:30:33');

DROP TABLE IF EXISTS `properties`;
CREATE TABLE `properties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `developer` varchar(150) NOT NULL,
  `project` varchar(150) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `unit_type` varchar(100) NOT NULL,
  `area` decimal(10,2) DEFAULT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `price` decimal(14,2) DEFAULT NULL,
  `down_payment` decimal(14,2) DEFAULT NULL,
  `installments` varchar(150) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `company_commission_type` varchar(10) NOT NULL DEFAULT 'percent',
  `company_commission_value` decimal(10,2) DEFAULT NULL,
  `sales_commission_type` varchar(10) NOT NULL DEFAULT 'percent',
  `sales_commission_value` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `user_id` (`user_id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `property_installments`;
CREATE TABLE `property_installments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `property_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `property_id` (`property_id`),
  KEY `due_date` (`due_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `property_installments` (`id`, `company_id`, `user_id`, `property_id`, `due_date`, `amount`, `status`, `paid_at`, `note`, `created_at`, `updated_at`) VALUES ('2', '4', '12', '2', '2026-08-01', '15.00', 'overdue', NULL, 'llll', '2026-08-02 23:00:37', '2026-08-02 23:01:02');

DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `monthly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `yearly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_users` int(11) DEFAULT NULL,
  `storage_limit_gb` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `super_admins`;
CREATE TABLE `super_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `super_admins` (`id`, `username`, `password`, `created_at`) VALUES ('1', 'superadmin', '$2y$10$2K9VZ1rxz3Ww8CyMd8EwDeiuAouino3yzlFcGI89A4nB9cdHzG3fK', '2026-08-02 01:23:07');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id_username` (`company_id`,`username`),
  UNIQUE KEY `company_id_email` (`company_id`,`email`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS=1;
