-- CRM database backup (PHP fallback dump)
-- Generated: 2026-09-30 01:05:49
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
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('41', '11', '23', 'mazen', 'client_created', 'New client \"abdlelrahman\" was added to the system.', '2026-09-20 21:45:20', '8');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('42', '12', '23', 'mazen', 'client_created', 'New client \"amr\" was added to the system.', '2026-09-20 21:49:31', '8');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('43', '13', '24', 'hamza', 'client_created', 'New client \"حاتم\" was added to the system.', '2026-09-20 21:55:54', '8');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('44', '13', '22', 'Sales Manager', 'lead_assigned', 'Assigned this lead to mazen.', '2026-09-20 22:06:57', '8');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('45', '13', '21', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"New Lead\" to \"Contacted\".', '2026-09-21 13:03:16', '8');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('46', '13', '21', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"Contacted\" to \"Interested\".', '2026-09-21 13:03:27', '8');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('47', '13', '21', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"Interested\" to \"Contacted\".', '2026-09-21 13:18:15', '8');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('48', '13', '21', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"Contacted\" to \"Visit Scheduled\".', '2026-09-21 13:18:18', '8');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('49', '13', '21', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"Visit Scheduled\" to \"New Lead\".', '2026-09-21 13:18:19', '8');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('50', '14', '25', 'managercrm', 'client_created', 'New client \"amr\" was added to the system.', '2026-09-30 01:56:01', '9');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('51', '14', '25', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"New Lead\" to \"Contacted\".', '2026-09-30 02:00:24', '9');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('52', '14', '25', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"Contacted\" to \"Interested\".', '2026-09-30 02:00:27', '9');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('53', '14', '25', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"Interested\" to \"Visit Scheduled\".', '2026-09-30 02:00:29', '9');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('54', '14', '25', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"Visit Scheduled\" to \"Lost\".', '2026-09-30 02:00:33', '9');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('55', '14', '25', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"Lost\" to \"Visit Scheduled\".', '2026-09-30 02:00:35', '9');
INSERT INTO `client_activity_log` (`id`, `client_id`, `user_id`, `employee_name`, `action_type`, `description`, `created_at`, `company_id`) VALUES ('56', '14', '25', 'managercrm', 'pipeline_stage_changed', 'Pipeline stage moved from \"Visit Scheduled\" to \"New Lead\".', '2026-09-30 02:00:38', '9');

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
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('26', '11', '23', '2026-09-20', '21:45:00', 'created', 'followup', '2026-09-20 21:45:20', NULL, '8');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('27', '11', '23', '2026-09-20', NULL, 'completed', 'Follow-up marked done from Smart Alert popup.', '2026-09-20 21:48:26', NULL, '8');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('28', '12', '23', '2026-09-20', '12:49:00', 'created', 'followup', '2026-09-20 21:49:31', NULL, '8');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('29', '12', '23', '2026-09-20', NULL, 'completed', 'Follow-up marked done from Smart Alert popup.', '2026-09-20 21:49:37', NULL, '8');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('30', '13', '24', '2026-09-21', '23:55:00', 'created', 'intersted', '2026-09-20 21:55:54', NULL, '8');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('31', '12', '21', '2026-09-20', NULL, 'dismissed', 'Follow-up alert dismissed from Smart Alert popup.', '2026-09-20 21:56:44', NULL, '8');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('32', '11', '21', '2026-09-20', NULL, 'dismissed', 'Follow-up alert dismissed from Smart Alert popup.', '2026-09-20 21:56:45', NULL, '8');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('33', '11', '22', '2026-09-20', NULL, 'dismissed', 'Follow-up alert dismissed from Smart Alert popup.', '2026-09-20 22:00:58', NULL, '8');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('34', '12', '22', '2026-09-20', NULL, 'dismissed', 'Follow-up alert dismissed from Smart Alert popup.', '2026-09-20 22:00:59', NULL, '8');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('35', '13', '21', '2026-09-21', NULL, 'dismissed', 'Follow-up alert dismissed from Smart Alert popup.', '2026-09-21 12:06:52', NULL, '8');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('36', '14', '25', '2026-09-30', '02:55:00', 'created', 'followup', '2026-09-30 01:56:01', NULL, '9');
INSERT INTO `client_reminders` (`id`, `client_id`, `user_id`, `reminder_date`, `reminder_time`, `status`, `note`, `created_at`, `resolved_at`, `company_id`) VALUES ('37', '14', '25', '2026-09-30', NULL, 'completed', 'Follow-up marked done from Smart Alert popup.', '2026-09-30 01:56:26', NULL, '9');

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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `clients` (`id`, `user_id`, `client_name`, `phone_number`, `project_location`, `project_name`, `unit_area`, `the_role`, `receiving`, `unit_price`, `down_payment`, `installment`, `installment_amount`, `visit`, `call_outcome`, `visit_outcome`, `final_status`, `pipeline_stage`, `event_title`, `event_date`, `event_time`, `note`, `created_at`, `company_id`, `lead_source`) VALUES ('11', '23', 'abdlelrahman', '+201065740316', 'El Shorouk City', '0', '126666', 'Duplex', 'Under Construction', '122666.00', '55699.00', 'Semi-annually', '26966.00', 'Company', 'Follow-up', 'Interested', 'Interested', 'New Lead', 'followup', '2026-09-20', '21:45:00', 'مخخههتهه', '2026-09-20 21:45:20', '8', 'Instagram');
INSERT INTO `clients` (`id`, `user_id`, `client_name`, `phone_number`, `project_location`, `project_name`, `unit_area`, `the_role`, `receiving`, `unit_price`, `down_payment`, `installment`, `installment_amount`, `visit`, `call_outcome`, `visit_outcome`, `final_status`, `pipeline_stage`, `event_title`, `event_date`, `event_time`, `note`, `created_at`, `company_id`, `lead_source`) VALUES ('12', '23', 'amr', '+201122777227', 'Badr City', '0', '19999', 'Duplex', 'Under Construction', '54655.00', '25665.00', 'Semi-annually', '26666.00', 'Call', 'Follow-up', 'Follow-up', 'Follow-up', 'New Lead', 'followup', '2026-09-20', '12:49:00', 'منتنتخه', '2026-09-20 21:49:31', '8', 'Facebook');
INSERT INTO `clients` (`id`, `user_id`, `client_name`, `phone_number`, `project_location`, `project_name`, `unit_area`, `the_role`, `receiving`, `unit_price`, `down_payment`, `installment`, `installment_amount`, `visit`, `call_outcome`, `visit_outcome`, `final_status`, `pipeline_stage`, `event_title`, `event_date`, `event_time`, `note`, `created_at`, `company_id`, `lead_source`) VALUES ('13', '23', 'حاتم', '+201122777227', 'Nasr City', '0', '266959', 'Typical Floor', 'Under Construction', '2656.00', '65959.00', 'Monthly', '26659.00', 'Company', 'Interested', 'Interested', 'Interested', 'New Lead', 'intersted', '2026-09-21', '23:55:00', 'لبلاباب', '2026-09-20 21:55:54', '8', 'TikTok');
INSERT INTO `clients` (`id`, `user_id`, `client_name`, `phone_number`, `project_location`, `project_name`, `unit_area`, `the_role`, `receiving`, `unit_price`, `down_payment`, `installment`, `installment_amount`, `visit`, `call_outcome`, `visit_outcome`, `final_status`, `pipeline_stage`, `event_title`, `event_date`, `event_time`, `note`, `created_at`, `company_id`, `lead_source`) VALUES ('14', '25', 'amr', '+201065740316', 'Badr City', '0', '13669', 'Duplex', 'Under Construction', '150000.00', '32666.00', 'Quarterly', '13666.00', 'Company', 'Follow-up', 'Follow-up', 'Interested', 'New Lead', 'followup', '2026-09-30', '02:55:00', 'تلخخمخمخم', '2026-09-30 01:56:01', '9', 'Facebook');

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `companies` (`id`, `name`, `created_at`, `subscription_start`, `subscription_end`, `status`, `max_users`, `storage_limit_gb`, `plan_id`) VALUES ('7', 'innovatech', '2026-09-01 20:36:49', NULL, NULL, 'active', NULL, NULL, NULL);
INSERT INTO `companies` (`id`, `name`, `created_at`, `subscription_start`, `subscription_end`, `status`, `max_users`, `storage_limit_gb`, `plan_id`) VALUES ('8', 'masria', '2026-09-20 14:13:30', NULL, NULL, 'active', NULL, NULL, NULL);
INSERT INTO `companies` (`id`, `name`, `created_at`, `subscription_start`, `subscription_end`, `status`, `max_users`, `storage_limit_gb`, `plan_id`) VALUES ('9', 'nawal', '2026-09-30 01:51:47', NULL, NULL, 'active', NULL, NULL, NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `db_backups` (`id`, `filename`, `size_bytes`, `type`, `status`, `triggered_by`, `error_message`, `created_at`) VALUES ('13', 'backup_20260901_193623_2794a964.sql', '12370', 'auto', 'success', 'system/auto-backup', NULL, '2026-09-01 20:36:23');

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `employee_lead_targets`;
CREATE TABLE `employee_lead_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `period_month` char(7) NOT NULL,
  `target_leads` int(11) NOT NULL DEFAULT 0,
  `set_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_target` (`company_id`,`user_id`,`period_month`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('30', '23', '11', 'new_lead', 'New Lead', 'New lead \"abdlelrahman\" was added by mazen.', NULL, '1', '2026-09-20 21:45:20', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('31', '23', '11', 'followup_due', 'Follow-up Due', 'Follow-up with \"abdlelrahman\" is due today.', '2026-09-20', '1', '2026-09-20 21:45:20', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('32', '23', '11', 'followup_alert', 'Follow-up Due Today', 'Follow-up with \"abdlelrahman\" is due today.', '2026-09-20', '1', '2026-09-20 21:45:20', '1', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('33', '23', '12', 'new_lead', 'New Lead', 'New lead \"amr\" was added by mazen.', NULL, '1', '2026-09-20 21:49:31', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('34', '23', '12', 'missed_followup', 'Missed Follow-up', 'Follow-up with \"amr\" was missed (was due 2026-09-20).', '2026-09-20', '1', '2026-09-20 21:49:31', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('35', '23', '12', 'followup_alert', 'Follow-up Due Today', 'Follow-up with \"amr\" is due today.', '2026-09-20', '1', '2026-09-20 21:49:31', '1', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('36', '24', '13', 'new_lead', 'New Lead', 'New lead \"حاتم\" was added by hamza.', NULL, '1', '2026-09-20 21:55:54', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('37', '21', '12', 'followup_alert', 'Follow-up Due Today', 'Follow-up with \"amr\" is due today.', '2026-09-20', '1', '2026-09-20 21:56:41', '0', '2026-09-20 23:59:44', '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('38', '21', '11', 'followup_alert', 'Follow-up Due Today', 'Follow-up with \"abdlelrahman\" is due today.', '2026-09-20', '1', '2026-09-20 21:56:41', '0', '2026-09-20 23:59:45', '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('39', '22', '12', 'followup_alert', 'Follow-up Due Today', 'Follow-up with \"amr\" is due today.', '2026-09-20', '1', '2026-09-20 22:00:56', '0', '2026-09-20 23:59:59', '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('40', '22', '11', 'followup_alert', 'Follow-up Due Today', 'Follow-up with \"abdlelrahman\" is due today.', '2026-09-20', '1', '2026-09-20 22:00:56', '0', '2026-09-20 23:59:58', '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('41', '23', '13', 'new_lead', 'Lead Assigned', '\"حاتم\" was assigned to you by Sales Manager.', NULL, '1', '2026-09-20 22:06:57', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('42', '23', '11', 'missed_followup', 'Missed Follow-up', 'Follow-up with \"abdlelrahman\" was missed (was due 2026-09-20).', '2026-09-20', '1', '2026-09-20 22:45:56', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('43', '23', '13', 'followup_due', 'Follow-up Due', 'Follow-up with \"حاتم\" is due today.', '2026-09-21', '1', '2026-09-21 12:06:49', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('44', '21', '13', 'followup_alert', 'Follow-up Due Today', 'Follow-up with \"حاتم\" is due today.', '2026-09-21', '1', '2026-09-21 12:06:49', '0', '2026-09-21 23:59:52', '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('45', '23', '13', 'status_changed', 'Status Changed', '\"حاتم\" moved from \"New Lead\" to \"Contacted\" in the Sales Pipeline.', NULL, '1', '2026-09-21 13:03:16', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('46', '23', '13', 'status_changed', 'Status Changed', '\"حاتم\" moved from \"Contacted\" to \"Interested\" in the Sales Pipeline.', NULL, '1', '2026-09-21 13:03:27', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('47', '23', '13', 'status_changed', 'Status Changed', '\"حاتم\" moved from \"Interested\" to \"Contacted\" in the Sales Pipeline.', NULL, '1', '2026-09-21 13:18:15', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('48', '23', '13', 'status_changed', 'Status Changed', '\"حاتم\" moved from \"Contacted\" to \"Visit Scheduled\" in the Sales Pipeline.', NULL, '1', '2026-09-21 13:18:18', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('49', '23', '13', 'status_changed', 'Status Changed', '\"حاتم\" moved from \"Visit Scheduled\" to \"New Lead\" in the Sales Pipeline.', NULL, '1', '2026-09-21 13:18:19', '0', NULL, '8');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('50', '25', '14', 'new_lead', 'New Lead', 'New lead \"amr\" was added by managercrm.', NULL, '1', '2026-09-30 01:56:01', '0', NULL, '9');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('51', '25', '14', 'followup_due', 'Follow-up Due', 'Follow-up with \"amr\" is due today.', '2026-09-30', '1', '2026-09-30 01:56:02', '0', NULL, '9');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('52', '25', '14', 'followup_alert', 'Follow-up Due Today', 'Follow-up with \"amr\" is due today.', '2026-09-30', '1', '2026-09-30 01:56:02', '1', NULL, '9');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('53', '25', '14', 'status_changed', 'Status Changed', '\"amr\" moved from \"New Lead\" to \"Contacted\" in the Sales Pipeline.', NULL, '1', '2026-09-30 02:00:24', '0', NULL, '9');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('54', '25', '14', 'status_changed', 'Status Changed', '\"amr\" moved from \"Contacted\" to \"Interested\" in the Sales Pipeline.', NULL, '1', '2026-09-30 02:00:27', '0', NULL, '9');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('55', '25', '14', 'status_changed', 'Status Changed', '\"amr\" moved from \"Interested\" to \"Visit Scheduled\" in the Sales Pipeline.', NULL, '1', '2026-09-30 02:00:29', '0', NULL, '9');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('56', '25', '14', 'status_changed', 'Status Changed', '\"amr\" moved from \"Visit Scheduled\" to \"Lost\" in the Sales Pipeline.', NULL, '1', '2026-09-30 02:00:33', '0', NULL, '9');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('57', '25', '14', 'status_changed', 'Status Changed', '\"amr\" moved from \"Lost\" to \"Visit Scheduled\" in the Sales Pipeline.', NULL, '1', '2026-09-30 02:00:35', '0', NULL, '9');
INSERT INTO `notifications` (`id`, `user_id`, `client_id`, `type`, `title`, `message`, `ref_date`, `is_read`, `created_at`, `is_done`, `snoozed_until`, `company_id`) VALUES ('58', '25', '14', 'status_changed', 'Status Changed', '\"amr\" moved from \"Visit Scheduled\" to \"New Lead\" in the Sales Pipeline.', NULL, '1', '2026-09-30 02:00:38', '0', NULL, '9');

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

INSERT INTO `super_admins` (`id`, `username`, `password`, `created_at`) VALUES ('1', 'superadmin', '$2y$10$G0cckOv0LlyZobeMQ39IPuQbDjBA19fyNEbacvh/QBxT2toJFbQvK', '2026-08-02 01:23:07');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `company_id` int(11) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'sales_employee',
  `manager_id` int(11) DEFAULT NULL,
  `last_lead_assigned_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_id_username` (`company_id`,`username`),
  UNIQUE KEY `company_id_email` (`company_id`,`email`),
  KEY `manager_id` (`manager_id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `username`, `password`, `created_at`, `company_id`, `email`, `role`, `manager_id`, `last_lead_assigned_at`) VALUES ('20', 'managercrm', '$2y$10$sbBnbk97/qf0J5sNiyYvqeIEn.HGFFGvbUhIltImMHuSPY/06Resy', '2026-09-01 20:36:50', '7', 'mazeenbadrr@gmail.com', 'company_admin', NULL, NULL);
INSERT INTO `users` (`id`, `username`, `password`, `created_at`, `company_id`, `email`, `role`, `manager_id`, `last_lead_assigned_at`) VALUES ('21', 'managercrm', '$2y$10$KUPZpjfGjrKO/XVSZTHWNOx216yZXd47yLOqZEazMadS9liqOOFNS', '2026-09-20 14:13:30', '8', 'khaledbadr2010@gmail.com', 'company_admin', NULL, NULL);
INSERT INTO `users` (`id`, `username`, `password`, `created_at`, `company_id`, `email`, `role`, `manager_id`, `last_lead_assigned_at`) VALUES ('22', 'Sales Manager', '$2y$10$N7Ibv.Nw75Of0bkghOgFyu9cU1mFLUhR.eYaen0f9apgRtmQa3myS', '2026-09-20 21:25:48', '8', 'mohandkhaled442@gmail.com', 'sales_manager', NULL, NULL);
INSERT INTO `users` (`id`, `username`, `password`, `created_at`, `company_id`, `email`, `role`, `manager_id`, `last_lead_assigned_at`) VALUES ('23', 'mazen', '$2y$10$XlLuagLSqLTsOvcwhmzSXuczRRQBfGfjAdHT74zAd5P63aHuBeH9e', '2026-09-20 21:42:35', '8', 'mazeenbadrr@gmail.com', 'sales_employee', '22', NULL);
INSERT INTO `users` (`id`, `username`, `password`, `created_at`, `company_id`, `email`, `role`, `manager_id`, `last_lead_assigned_at`) VALUES ('24', 'hamza', '$2y$10$YE1z6uSp9wOzrx4xKv4GGO6.gNM2IkTQthdwQhfLocnfoP85kcHoC', '2026-09-20 21:54:23', '8', '123042@sta.edu.eg', 'sales_employee', '22', NULL);
INSERT INTO `users` (`id`, `username`, `password`, `created_at`, `company_id`, `email`, `role`, `manager_id`, `last_lead_assigned_at`) VALUES ('25', 'managercrm', '$2y$10$Sl6tIR5bPD4gjacabr6QN.px2fTFSCKl7J8iglsG8VCsUvbWd.QjS', '2026-09-30 01:51:47', '9', 'mazeenbadrr@gmail.com', 'company_admin', NULL, NULL);

SET FOREIGN_KEY_CHECKS=1;
