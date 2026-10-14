-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 09, 2026 at 03:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `crm_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
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
  `event_title` varchar(255) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `event_time` time DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `user_id`, `client_name`, `phone_number`, `project_location`, `project_name`, `unit_area`, `the_role`, `receiving`, `unit_price`, `down_payment`, `installment`, `installment_amount`, `visit`, `call_outcome`, `visit_outcome`, `final_status`, `event_title`, `event_date`, `event_time`, `note`, `created_at`) VALUES
(1, 1, 'مازن خالد', '+201065740316', 'New Cairo', '0', 1223, 'Ground Floor', 'Ready to Move', 21212.00, 122.00, 'Monthly', 2134.00, 'Location', 'Interested', 'Follow-up', 'Follow-up', 'متابعة', '2026-01-16', '18:45:00', 'تابتينني', '2026-01-09 13:46:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'managercrm', '$2y$10$l8xBk2bjxTzcRu6edyIojOmggUqRveOydb03q8lxnqUx95zt/QEzi', '2026-01-09 13:43:19'),
(2, 'mohamed', '$2y$10$ZL0/U2WBZ3IeVQAM4KBi5uaa8198fm6Tjlt4P0DchrHu5069iwIBW', '2026-01-09 13:51:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------

--
-- Table structure for table `client_activity_log`
-- (Activity Timeline — created automatically at runtime as well,
-- this is included here for manual imports / reference.)
--

CREATE TABLE `client_activity_log` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `employee_name` varchar(100) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `client_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `client_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Sales Pipeline (Kanban board) support.
-- Adds a `pipeline_stage` column to `clients`. The application also creates
-- this column automatically at runtime if it's missing (see
-- src/pipeline_helper.php), so this statement is only needed for fresh
-- database imports and is safe to skip if the column already exists.
--

ALTER TABLE `clients`
  ADD COLUMN `pipeline_stage` VARCHAR(50) NOT NULL DEFAULT 'New Lead' AFTER `final_status`;

-- --------------------------------------------------------

--
-- Lead Source Management support.
-- Adds a `lead_source` column to `clients` tracking where each lead came
-- from (Facebook, Instagram, TikTok, Website, WhatsApp, Referral). The
-- application also creates this column automatically at runtime if it's
-- missing (see ensure_company_id_columns() in src/tenant_helper.php), so
-- this statement is only needed for fresh database imports and is safe to
-- skip if the column already exists.
--

ALTER TABLE `clients`
  ADD COLUMN `lead_source` VARCHAR(50) NOT NULL DEFAULT 'Website' AFTER `pipeline_stage`;

-- --------------------------------------------------------

--
-- Client Document Management support.
-- Stores uploaded Images / PDF files / Contracts / Voice Notes per client.
-- The application also creates this table automatically at runtime if it's
-- missing (see src/document_helper.php), so this statement is only needed
-- for fresh database imports and is safe to skip if the table already
-- exists.
--

CREATE TABLE `client_documents` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `category` varchar(20) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `client_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `user_id` (`user_id`);

ALTER TABLE `client_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;

-- --------------------------------------------------------

--
-- Notification Center support.
-- Stores New Lead / Follow-up Due / Sale Completed / Status Changed /
-- Missed Follow-up alerts per user. The application also creates this
-- table automatically at runtime if it's missing (see
-- src/notification_helper.php), so this statement is only needed for
-- fresh database imports and is safe to skip if the table already exists.
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text DEFAULT NULL,
  `ref_date` date DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `type` (`type`);

ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;

-- --------------------------------------------------------

--
-- Subscription Plans Management support.
-- Lets the Super Admin define reusable plans (name, monthly/yearly
-- price, max users, storage limit, active/inactive status) and assign
-- one to each company. Assigning a plan automatically applies its
-- max_users / storage_limit_gb onto the company (see
-- companies.max_users / companies.storage_limit_gb, enforced by
-- src/tenant_helper.php and src/storage_helper.php respectively). The
-- application also creates this table and the `companies.plan_id`
-- column automatically at runtime if they're missing (see
-- src/plan_helper.php), so this statement is only needed for fresh
-- database imports and is safe to skip if they already exist.
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `monthly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `yearly_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_users` int(11) DEFAULT NULL,
  `storage_limit_gb` decimal(10,2) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- `companies` is created by the app at runtime, so this is skipped on a fresh import.
ALTER TABLE IF EXISTS `companies`
  ADD COLUMN IF NOT EXISTS `plan_id` INT(11) DEFAULT NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
