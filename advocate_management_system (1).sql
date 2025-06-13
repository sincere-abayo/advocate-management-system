-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 13, 2025 at 04:21 PM
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
-- Database: `advocate_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `advocate_activities`
--

CREATE TABLE `advocate_activities` (
  `activity_id` int(11) NOT NULL,
  `advocate_id` int(11) NOT NULL,
  `case_id` int(11) DEFAULT NULL,
  `activity_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `hours_spent` decimal(5,2) NOT NULL,
  `activity_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `billable` tinyint(1) DEFAULT 1,
  `billing_rate` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `advocate_other_income`
--

CREATE TABLE `advocate_other_income` (
  `income_id` int(11) NOT NULL,
  `advocate_id` int(11) NOT NULL,
  `income_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `income_category` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `advocate_profiles`
--

CREATE TABLE `advocate_profiles` (
  `advocate_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `license_number` varchar(50) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `education` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `total_income_ytd` decimal(12,2) DEFAULT 0.00,
  `total_expenses_ytd` decimal(12,2) DEFAULT 0.00,
  `profit_ytd` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `advocate_profiles`
--

INSERT INTO `advocate_profiles` (`advocate_id`, `user_id`, `license_number`, `specialization`, `experience_years`, `education`, `bio`, `hourly_rate`, `total_income_ytd`, `total_expenses_ytd`, `profit_ytd`) VALUES
(1, 2, '', NULL, NULL, NULL, NULL, NULL, 440.00, 400.00, -360.00);

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `advocate_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `case_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('scheduled','completed','cancelled','rescheduled') DEFAULT 'scheduled',
  `location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `advocate_id`, `client_id`, `case_id`, `title`, `description`, `appointment_date`, `start_time`, `end_time`, `status`, `location`, `created_at`, `updated_at`) VALUES
(2, 1, 1, 1, 'hgcgcgcjc', 'sdfgvhjklkhgfdsa\\SDCGHJKL;\'IHGDS', '2025-05-15', '17:30:00', '18:30:00', 'scheduled', 'Musanze Rwanda', '2025-05-14 14:23:08', '2025-05-14 14:23:08'),
(3, 1, 1, 2, 'Property Dispute - Mugabo vs. Abayo appointment', 'This case involves a dispute over the ownership of a piece of land located in the Kimihurura area. Mr. Smith claims ownership based on a title deed from 2018, while Mr. Doe asserts he purchased the land in 2020 with a different agreement.', '2025-05-15', '13:00:00', '14:00:00', 'scheduled', 'Kicukiro Kigali', '2025-05-15 09:50:33', '2025-05-15 09:50:33');

-- --------------------------------------------------------

--
-- Table structure for table `billings`
--

CREATE TABLE `billings` (
  `billing_id` int(11) NOT NULL,
  `case_id` int(11) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `advocate_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `billing_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','paid','overdue','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billings`
--

INSERT INTO `billings` (`billing_id`, `case_id`, `client_id`, `advocate_id`, `amount`, `description`, `billing_date`, `due_date`, `status`, `payment_method`, `payment_date`, `created_at`) VALUES
(1, 1, 1, 1, 210.00, 'advance of total payment', '2025-05-14', '2025-06-13', 'pending', NULL, NULL, '2025-05-14 20:11:39'),
(2, 2, 1, 1, 440.00, 'The amount to be paid for this case', '2025-05-19', '2025-06-18', 'paid', NULL, '2025-05-19', '2025-05-19 20:24:04');

-- --------------------------------------------------------

--
-- Table structure for table `billing_items`
--

CREATE TABLE `billing_items` (
  `item_id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `billing_items`
--

INSERT INTO `billing_items` (`item_id`, `billing_id`, `description`, `quantity`, `rate`, `amount`, `created_at`) VALUES
(1, 1, 'a half', 2.00, 100.00, 200.00, '2025-05-14 20:11:39'),
(2, 1, 'hhhh', 1.00, 10.00, 10.00, '2025-05-14 20:11:39'),
(3, 2, 'The case amount to be paid', 1.00, 440.00, 440.00, '2025-05-19 20:24:04');

-- --------------------------------------------------------

--
-- Table structure for table `cases`
--

CREATE TABLE `cases` (
  `case_id` int(11) NOT NULL,
  `case_number` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `case_type` varchar(100) NOT NULL,
  `court` varchar(100) DEFAULT NULL,
  `filing_date` date DEFAULT NULL,
  `hearing_date` date DEFAULT NULL,
  `status` enum('pending','active','closed','won','lost','settled') NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `client_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_income` decimal(10,2) DEFAULT 0.00,
  `total_expenses` decimal(10,2) DEFAULT 0.00,
  `profit` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cases`
--

INSERT INTO `cases` (`case_id`, `case_number`, `title`, `description`, `case_type`, `court`, `filing_date`, `hearing_date`, `status`, `priority`, `client_id`, `created_at`, `updated_at`, `total_income`, `total_expenses`, `profit`) VALUES
(1, 'CASE-202505-0001', 'Alleged Theft - Johnson', 'Ms. Johnson has been accused of theft from her former workplace. We are preparing her defense.', 'Criminal', 'Gasabo Primary Court', '2025-05-13', '2025-05-28', 'active', 'high', 1, '2025-05-13 22:47:30', '2025-05-19 20:22:08', 0.00, 400.00, -800.00),
(2, 'CASE-202505-0002', 'Property Dispute - Mugabo vs. Abayo', 'This case involves a dispute over the ownership of a piece of land located in the Kimihurura area. Mr. Smith claims ownership based on a title deed from 2018, while Mr. Doe asserts he purchased the land in 2020 with a different agreement.', 'Civil', 'Kigali High Court', '2025-05-14', '2025-05-20', 'pending', 'medium', 1, '2025-05-14 20:30:27', '2025-05-14 20:32:52', 0.00, 0.00, 0.00),
(3, 'CASE-202505-0003', 'Divorce Proceedings - Williams vs. Williams', '', 'Family', 'Intermediate Court', '2025-05-14', '2025-06-14', 'pending', 'low', 2, '2025-05-14 21:16:41', '2025-05-14 21:16:41', 0.00, 0.00, 0.00),
(4, 'CASE-202505-0004', 'Boundary Dispute - Keza vs. Niyonzima', 'This case concerns a disagreement over the boundaries between Ms. Keza\'s and Mr. Niyonzima\'s adjacent properties.', 'Insurance', 'Kicukiro Land Tribunal', '2025-05-14', '2025-05-15', 'active', 'high', 3, '2025-05-14 21:22:41', '2025-05-14 21:22:41', 0.00, 0.00, 0.00),
(5, 'CASE-202505-0005', 'Murder', 'The murder was held not out of defense', 'Criminal', 'Gasabo Primary Court', '2025-05-19', '2025-05-20', 'settled', 'high', 3, '2025-05-19 20:15:38', '2025-05-19 20:15:38', 0.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `case_activities`
--

CREATE TABLE `case_activities` (
  `activity_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` enum('update','document','hearing','note','status_change') NOT NULL,
  `description` text NOT NULL,
  `activity_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_activities`
--

INSERT INTO `case_activities` (`activity_id`, `case_id`, `user_id`, `activity_type`, `description`, `activity_date`) VALUES
(1, 1, 2, 'update', 'Case created with status: active', '2025-05-13 22:47:30'),
(2, 1, 3, 'update', 'Appointment requested: hgcgcgcjc on May 15, 2025 at 5:30 PM', '2025-05-14 14:23:08'),
(3, 1, 2, 'update', 'Invoice created: $210.00', '2025-05-14 20:11:39'),
(4, 2, 2, 'update', 'Case created with status: pending', '2025-05-14 20:30:27'),
(5, 2, 2, 'update', 'Case details updated', '2025-05-14 20:32:52'),
(6, 1, 2, 'update', 'Case details updated', '2025-05-14 20:36:26'),
(7, 1, 2, 'update', 'Case details updated', '2025-05-14 20:36:51'),
(8, 3, 2, 'update', 'Case created with status: pending', '2025-05-14 21:16:41'),
(9, 4, 6, 'update', 'Case created with status: active', '2025-05-14 21:22:41'),
(10, 2, 3, 'document', 'Document uploaded: Property Dispute - Mugabo vs. Abayo', '2025-05-15 09:46:24'),
(11, 2, 3, 'update', 'Appointment requested: Property Dispute - Mugabo vs. Abayo appointment on May 15, 2025 at 1:00 PM', '2025-05-15 09:50:33'),
(12, 5, 2, 'update', 'Case created with status: settled', '2025-05-19 20:15:38'),
(13, 5, 2, 'document', 'Document uploaded: Murder', '2025-05-19 20:19:54'),
(14, 2, 2, 'status_change', 'Case status updated from \'pending\' to \'pending\'', '2025-05-19 20:20:09'),
(15, 1, 2, 'update', 'Expense added: $400.00 - Robbery was held so the victim want the payment back', '2025-05-19 20:22:08'),
(16, 2, 2, 'update', 'Invoice created: $440.00', '2025-05-19 20:24:04');

-- --------------------------------------------------------

--
-- Table structure for table `case_assignments`
--

CREATE TABLE `case_assignments` (
  `assignment_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `advocate_id` int(11) NOT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) DEFAULT 'primary'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_assignments`
--

INSERT INTO `case_assignments` (`assignment_id`, `case_id`, `advocate_id`, `assigned_date`, `role`) VALUES
(1, 1, 1, '2025-05-13 22:47:30', 'primary'),
(2, 2, 1, '2025-05-14 20:30:27', 'primary'),
(3, 3, 1, '2025-05-14 21:16:41', 'primary'),
(4, 5, 1, '2025-05-19 20:15:38', 'primary');

-- --------------------------------------------------------

--
-- Table structure for table `case_expenses`
--

CREATE TABLE `case_expenses` (
  `expense_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `advocate_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `expense_category` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_expenses`
--

INSERT INTO `case_expenses` (`expense_id`, `case_id`, `advocate_id`, `expense_date`, `amount`, `description`, `receipt_file`, `expense_category`, `created_at`) VALUES
(1, 1, 1, '2025-05-19', 400.00, 'Robbery was held so the victim want the payment back', 'receipt_1747686127_682b92eff35a4.pdf', 'Judgement', '2025-05-19 20:22:07');

-- --------------------------------------------------------

--
-- Table structure for table `case_hearings`
--

CREATE TABLE `case_hearings` (
  `hearing_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `hearing_date` date NOT NULL,
  `hearing_time` time NOT NULL,
  `hearing_type` varchar(100) NOT NULL,
  `court_room` varchar(100) DEFAULT NULL,
  `judge` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `outcome` text DEFAULT NULL,
  `next_steps` text DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','postponed') DEFAULT 'scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `case_income`
--

CREATE TABLE `case_income` (
  `income_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `advocate_id` int(11) NOT NULL,
  `income_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `income_category` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_profiles`
--

CREATE TABLE `client_profiles` (
  `client_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `reference_source` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_profiles`
--

INSERT INTO `client_profiles` (`client_id`, `user_id`, `occupation`, `date_of_birth`, `reference_source`) VALUES
(1, 3, 'Unemployed', '2025-05-13', ''),
(2, 4, NULL, NULL, NULL),
(3, 5, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contact_requests`
--

CREATE TABLE `contact_requests` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `request_type` varchar(50) NOT NULL,
  `status` enum('new','in_progress','completed') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `initiator_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`conversation_id`, `initiator_id`, `recipient_id`, `subject`, `created_at`, `updated_at`) VALUES
(1, 3, 2, 'check', '2025-05-14 14:23:30', '2025-05-19 20:40:14');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `document_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `document_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`document_id`, `case_id`, `title`, `file_path`, `document_type`, `description`, `uploaded_by`, `upload_date`) VALUES
(1, 2, 'Property Dispute - Mugabo vs. Abayo', 'uploads/documents/doc_6825b7f0e9ac8_1747302384.pdf', '', 'This case involves a dispute over the ownership of a piece of land located in the Kimihurura area. Mr. Smith claims ownership based on a title deed from 2018, while Mr. Doe asserts he purchased the land in 2020 with a different agreement.', 3, '2025-05-15 09:46:24'),
(2, 5, 'Murder', 'doc_1747685994_682b926a744fb.pdf', 'Judgment', 'The victim\'s brother want justice', 2, '2025-05-19 20:19:54');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `conversation_id`, `sender_id`, `content`, `is_read`, `created_at`) VALUES
(1, 1, 3, 'SDFGVHJKLJHCDXSFGHJ', 1, '2025-05-14 14:23:30'),
(2, 1, 3, 'I wanted to meet in person as the Upcoming Hearrings is so soon', 1, '2025-05-15 09:52:14'),
(3, 1, 3, 'I\'m sorry for delaying my payment', 0, '2025-05-19 20:40:14');

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `status` enum('active','unsubscribed') DEFAULT 'active',
  `subscription_date` datetime NOT NULL,
  `unsubscription_date` datetime DEFAULT NULL,
  `source` varchar(50) DEFAULT 'website'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `related_to` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `is_read`, `created_at`, `related_to`, `related_id`) VALUES
(2, 3, 'New Case Created', 'A new case \'trtgr\' has been created for you with case number: CASE-202505-0001', 0, '2025-05-13 22:47:30', 'case', 1),
(4, 2, 'New Message', 'You have received a new message from Abayo Margot', 0, '2025-05-14 14:23:30', 'message', 1),
(5, 3, 'New Invoice Created', 'A new invoice (INV-00001) for $210.00 has been created.', 0, '2025-05-14 20:11:39', 'invoice', 1),
(6, 3, 'New Case Created', 'A new case \'Property Dispute - Mugabo vs. Abayo\' has been created for you with case number: CASE-202505-0002', 0, '2025-05-14 20:30:27', 'case', 2),
(7, 4, 'New Case Created', 'A new case \'Divorce Proceedings - Williams vs. Williams\' has been created for you with case number: CASE-202505-0003', 0, '2025-05-14 21:16:41', 'case', 3),
(8, 5, 'New Case Created', 'A new case \'Boundary Dispute - Keza vs. Niyonzima\' has been created for you with case number: CASE-202505-0004', 0, '2025-05-14 21:22:41', 'case', 4),
(9, 2, 'New Appointment Request', 'Client Abayo Margot has requested an appointment on May 15, 2025 at 1:00 PM.', 0, '2025-05-15 09:50:33', 'appointment', 3),
(10, 2, 'New Message', 'You have received a new message from Abayo Margot', 0, '2025-05-15 09:52:14', 'message', 1),
(11, 5, 'New Case Created', 'A new case \'Murder\' has been created for you with case number: CASE-202505-0005', 0, '2025-05-19 20:15:38', 'case', 5),
(12, 3, 'New Invoice Created', 'A new invoice (INV-00002) for $440.00 has been created.', 0, '2025-05-19 20:24:04', 'invoice', 2),
(14, 2, 'New Message', 'You have received a new message from Abayo Margot', 0, '2025-05-19 20:40:14', 'message', 1);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `billing_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `billing_id`, `amount`, `payment_date`, `payment_method`, `notes`, `created_at`, `created_by`) VALUES
(1, 2, 440.00, '2025-05-19', 'Manual', 'Marked as paid by user', '2025-05-19 20:24:11', 2);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `user_type` enum('admin','advocate','client') NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive','suspended','pending') DEFAULT 'active',
  `verification_token` varchar(64) DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `full_name`, `phone`, `address`, `user_type`, `profile_image`, `created_at`, `updated_at`, `status`, `verification_token`, `reset_token`, `reset_token_expiry`) VALUES
(1, 'admin', '$2y$10$xv.9jpe.osgb92yGNlkVYe5XCopGWaohqkcUb4AmHfDTlSAeq8vHm', 'admin@example.com', 'System Administrator', NULL, NULL, 'admin', NULL, '2025-05-13 13:10:38', '2025-05-13 13:13:06', 'active', NULL, NULL, NULL),
(2, 'imbabazi', '$2y$10$Gj40VOwuN4L3CRQpUBFW7.IBs/s76fLk6GctaB53NrqsYKx8dsXgm', 'niwashikilvan@gmail.com', 'IMBABAZI Nilvan', '0790355566', NULL, 'advocate', NULL, '2025-05-13 13:54:28', '2025-05-13 13:54:28', 'active', 'ab7ead2ec503224e2031c79d840ba6004b30a60263182b8351924115b14590fc', NULL, NULL),
(3, 'Abayo', '$2y$10$/kJHtEKNJ4H28h3TzTF4KekXIHNgK6tXtklO07RQA/eILpKF9bzTO', 'abayo@gmail.com', 'Abayo Margot', '0734345653', 'Gasabo', 'client', 'uploads/profiles/client_3_1747167721.jpg', '2025-05-13 13:55:33', '2025-05-13 20:22:01', 'active', '5711d946832a2ffd4d81dda923ccd0771ae0958b064fcd2387e11ec122fd0dac', NULL, NULL),
(4, 'Mutima', '$2y$10$VS7mln9dkBvv7zphupLVWu3mg0gCd6raScC40wvPwyOQwn3FhLGcW', 'mutimukeyeflorence120@gmail.com', 'Mutimukeye Florence', '0791606530', NULL, 'client', NULL, '2025-05-14 20:38:52', '2025-05-14 20:38:52', 'active', '62ea38173ff4d0c22260194219aa546e31578a1f48567a42b7b6c9dab3bf356c', NULL, NULL),
(5, 'Amani', '$2y$10$f9uVERqHDZEZ.rgxk4mrq.BnsIX6i2fkpN1raRTgRcQQt1Eljl786', 'amani@gmail.com', 'Amani Fadhili', '0734345653', NULL, 'client', NULL, '2025-05-14 21:12:52', '2025-05-14 21:12:52', 'active', '92c5831d4bbd271127bfb568399aee0b43ba1521c56d95e4ff62beccb5971ca9', NULL, NULL),
(6, 'Kenny', '$2y$10$C0zOH7tKpsoagiMfWkILYe.J8E8oyzjgL7Xq7mV/4fx96JUpcXiTm', 'nziza@gmail.com', 'Nziza Kenny', '0790355566', NULL, 'advocate', NULL, '2025-05-14 21:18:12', '2025-05-14 21:18:12', 'active', '3671c75cd5a27b45d53f56d2e835da54d60f3a977ab899bd8bb685d4c92e215a', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `advocate_activities`
--
ALTER TABLE `advocate_activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `advocate_id` (`advocate_id`),
  ADD KEY `case_id` (`case_id`);

--
-- Indexes for table `advocate_other_income`
--
ALTER TABLE `advocate_other_income`
  ADD PRIMARY KEY (`income_id`),
  ADD KEY `advocate_id` (`advocate_id`);

--
-- Indexes for table `advocate_profiles`
--
ALTER TABLE `advocate_profiles`
  ADD PRIMARY KEY (`advocate_id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `advocate_id` (`advocate_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `case_id` (`case_id`);

--
-- Indexes for table `billings`
--
ALTER TABLE `billings`
  ADD PRIMARY KEY (`billing_id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `advocate_id` (`advocate_id`);

--
-- Indexes for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `billing_id` (`billing_id`);

--
-- Indexes for table `cases`
--
ALTER TABLE `cases`
  ADD PRIMARY KEY (`case_id`),
  ADD UNIQUE KEY `case_number` (`case_number`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `case_activities`
--
ALTER TABLE `case_activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `case_assignments`
--
ALTER TABLE `case_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `unique_case_advocate` (`case_id`,`advocate_id`),
  ADD KEY `advocate_id` (`advocate_id`);

--
-- Indexes for table `case_expenses`
--
ALTER TABLE `case_expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `advocate_id` (`advocate_id`);

--
-- Indexes for table `case_hearings`
--
ALTER TABLE `case_hearings`
  ADD PRIMARY KEY (`hearing_id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `case_income`
--
ALTER TABLE `case_income`
  ADD PRIMARY KEY (`income_id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `advocate_id` (`advocate_id`);

--
-- Indexes for table `client_profiles`
--
ALTER TABLE `client_profiles`
  ADD PRIMARY KEY (`client_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `contact_requests`
--
ALTER TABLE `contact_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD KEY `initiator_id` (`initiator_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `case_id` (`case_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `billing_id` (`billing_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `advocate_activities`
--
ALTER TABLE `advocate_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `advocate_other_income`
--
ALTER TABLE `advocate_other_income`
  MODIFY `income_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `advocate_profiles`
--
ALTER TABLE `advocate_profiles`
  MODIFY `advocate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `billings`
--
ALTER TABLE `billings`
  MODIFY `billing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `billing_items`
--
ALTER TABLE `billing_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `cases`
--
ALTER TABLE `cases`
  MODIFY `case_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `case_activities`
--
ALTER TABLE `case_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `case_assignments`
--
ALTER TABLE `case_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `case_expenses`
--
ALTER TABLE `case_expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `case_hearings`
--
ALTER TABLE `case_hearings`
  MODIFY `hearing_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `case_income`
--
ALTER TABLE `case_income`
  MODIFY `income_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_profiles`
--
ALTER TABLE `client_profiles`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contact_requests`
--
ALTER TABLE `contact_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `advocate_activities`
--
ALTER TABLE `advocate_activities`
  ADD CONSTRAINT `advocate_activities_ibfk_1` FOREIGN KEY (`advocate_id`) REFERENCES `advocate_profiles` (`advocate_id`),
  ADD CONSTRAINT `advocate_activities_ibfk_2` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`) ON DELETE SET NULL;

--
-- Constraints for table `advocate_other_income`
--
ALTER TABLE `advocate_other_income`
  ADD CONSTRAINT `advocate_other_income_ibfk_1` FOREIGN KEY (`advocate_id`) REFERENCES `advocate_profiles` (`advocate_id`);

--
-- Constraints for table `advocate_profiles`
--
ALTER TABLE `advocate_profiles`
  ADD CONSTRAINT `advocate_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`advocate_id`) REFERENCES `advocate_profiles` (`advocate_id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `client_profiles` (`client_id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`) ON DELETE SET NULL;

--
-- Constraints for table `billings`
--
ALTER TABLE `billings`
  ADD CONSTRAINT `billings_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `billings_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `client_profiles` (`client_id`),
  ADD CONSTRAINT `billings_ibfk_3` FOREIGN KEY (`advocate_id`) REFERENCES `advocate_profiles` (`advocate_id`);

--
-- Constraints for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD CONSTRAINT `billing_items_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billings` (`billing_id`) ON DELETE CASCADE;

--
-- Constraints for table `cases`
--
ALTER TABLE `cases`
  ADD CONSTRAINT `cases_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `client_profiles` (`client_id`);

--
-- Constraints for table `case_activities`
--
ALTER TABLE `case_activities`
  ADD CONSTRAINT `case_activities_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_activities_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `case_assignments`
--
ALTER TABLE `case_assignments`
  ADD CONSTRAINT `case_assignments_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_assignments_ibfk_2` FOREIGN KEY (`advocate_id`) REFERENCES `advocate_profiles` (`advocate_id`) ON DELETE CASCADE;

--
-- Constraints for table `case_expenses`
--
ALTER TABLE `case_expenses`
  ADD CONSTRAINT `case_expenses_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_expenses_ibfk_2` FOREIGN KEY (`advocate_id`) REFERENCES `advocate_profiles` (`advocate_id`);

--
-- Constraints for table `case_hearings`
--
ALTER TABLE `case_hearings`
  ADD CONSTRAINT `case_hearings_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_hearings_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `case_income`
--
ALTER TABLE `case_income`
  ADD CONSTRAINT `case_income_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `case_income_ibfk_2` FOREIGN KEY (`advocate_id`) REFERENCES `advocate_profiles` (`advocate_id`);

--
-- Constraints for table `client_profiles`
--
ALTER TABLE `client_profiles`
  ADD CONSTRAINT `client_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`initiator_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`case_id`) REFERENCES `cases` (`case_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billings` (`billing_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
