-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2026 at 03:37 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `internalization_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_monitoring`
--

CREATE TABLE `activity_monitoring` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `week_number` int(11) DEFAULT NULL,
  `report` text DEFAULT NULL,
  `supervisor_feedback` text DEFAULT NULL,
  `progress` int(11) DEFAULT 0,
  `status` varchar(50) DEFAULT 'ongoing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agreement_types`
--

CREATE TABLE `agreement_types` (
  `id` int(11) NOT NULL,
  `agreement_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `agreement_types`
--

INSERT INTO `agreement_types` (`id`, `agreement_name`, `description`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 'MOA', 'Memorandum of Agreement', '2026-11-22', '2027-11-22', 'Active', '2026-11-06 17:17:41'),
(3, 'MOU', 'Memorandum of Understanding', '2026-10-22', '2027-10-22', 'Active', '2026-11-06 17:17:41');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `announcement` text NOT NULL,
  `publish_date` date NOT NULL,
  `status` enum('Published','Draft') DEFAULT 'Published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `mobility_type` enum('Inbound','Outbound') NOT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `institution` varchar(150) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `status` enum('submitted','under_review','approved','rejected','completed') DEFAULT 'submitted',
  `documents_status` enum('pending','complete','incomplete') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `applicant_id`, `program_id`, `reason`, `department`, `mobility_type`, `semester`, `school_year`, `institution`, `country`, `status`, `documents_status`, `notes`, `reviewed_at`, `approval_date`, `created_at`, `updated_at`) VALUES
(10, 9, 5, NULL, NULL, 'Outbound', NULL, NULL, NULL, NULL, 'approved', 'complete', NULL, '2026-11-23 01:35:24', NULL, '2026-11-22 16:07:58', '2026-11-22 17:35:24');

--
-- Triggers `applications`
--
DELIMITER $$
CREATE TRIGGER `notify_admin_new_application` AFTER INSERT ON `applications` FOR EACH ROW BEGIN
    DECLARE v_program_name VARCHAR(150);
    DECLARE v_user_name VARCHAR(200);
    
    SELECT program_name INTO v_program_name FROM programs WHERE id = NEW.program_id;
    SELECT CONCAT(first_name, ' ', last_name) INTO v_user_name FROM users WHERE id = NEW.applicant_id;
    
    INSERT INTO `notifications` (`user_id`, `type`, `title`, `message`)
    SELECT `id`, 'system_alert', 'New Application Received', CONCAT(v_user_name, ' submitted an application for ', IFNULL(v_program_name, 'a program'), '.')
    FROM `users` 
    WHERE `user_role` IN ('admin', 'super admin');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `notify_user_status_change` AFTER UPDATE ON `applications` FOR EACH ROW BEGIN
    DECLARE v_program_name VARCHAR(150);
    DECLARE v_title VARCHAR(255);
    DECLARE v_message TEXT;
    
    IF OLD.status != NEW.status THEN
        SELECT program_name INTO v_program_name FROM programs WHERE id = NEW.program_id;
        
        IF NEW.status = 'approved' THEN
            SET v_title = 'Application Approved';
            SET v_message = CONCAT('Congratulations! Your application for ', v_program_name, ' has been approved. You may now proceed with the next requirements.');
        ELSEIF NEW.status = 'rejected' THEN
            SET v_title = 'Application Rejected';
            SET v_message = CONCAT('We regret to inform you that your application for ', v_program_name, ' was not approved. Please review the details provided by the Internationalization Office.');
        ELSEIF NEW.status = 'under_review' THEN
            SET v_title = 'Application Under Review';
            SET v_message = CONCAT('Your application for ', v_program_name, ' is currently being reviewed by the admin.');
        END IF;
        
        IF v_title IS NOT NULL THEN
            INSERT INTO `notifications` (`user_id`, `type`, `title`, `message`)
            VALUES (NEW.applicant_id, NEW.status, v_title, v_message);
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`id`, `user_id`, `action`, `description`, `created_at`) VALUES
(1, 3, 'update_user', 'Updated user account #9 (Wendy Latorre)', '2026-11-21 17:16:41'),
(2, 3, 'create_user', 'Created new campus coordinator account #10 (Campus Coordinator)', '2026-11-22 11:10:20'),
(3, 3, 'create_user', 'Created new ovplia staff account #11 (staff Ovplia)', '2026-11-22 11:14:07'),
(4, 3, 'create_user', 'Created new approving official account #12 (Campus Officials)', '2026-11-22 11:16:06'),
(5, 3, 'update_user', 'Updated user account #12 (Campus Officials)', '2026-11-22 11:18:03'),
(6, 3, 'create_user', 'Created new bor secretary account #13 (BOR Secrectary)', '2026-11-22 11:19:12'),
(7, 3, 'update_user', 'Updated user account #13 (BOR Secrectary)', '2026-11-22 11:20:51'),
(8, 3, 'reject_application', 'Application #6 was rejected.', '2026-11-22 15:44:44'),
(9, 3, 'reject_application', 'Application #7 was rejected.', '2026-11-22 15:48:58'),
(10, 3, 'reject_application', 'Application #8 was rejected.', '2026-11-22 15:55:02'),
(11, 3, 'reject_application', 'Application #9 was rejected.', '2026-11-22 15:55:07'),
(12, 3, 'approve_application', 'Application #10 was approved.', '2026-11-22 17:35:24');

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `country_name` varchar(100) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `country_name`, `status`, `created_at`) VALUES
(2, 'Philippines', 'Active', '2026-11-06 17:17:41'),
(4, 'reret', 'Active', '2026-11-15 02:50:02'),
(5, 'Vietnam', 'Active', '2026-11-15 03:05:16');

-- --------------------------------------------------------

--
-- Table structure for table `countries_addresses`
--

CREATE TABLE `countries_addresses` (
  `id` int(11) NOT NULL,
  `country_name` varchar(100) NOT NULL,
  `region` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `street_address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `countries_addresses`
--

INSERT INTO `countries_addresses` (`id`, `country_name`, `region`, `province`, `city`, `postal_code`, `street_address`, `status`, `created_at`) VALUES
(1, 'Philippines', 'Region I - Ilocos Region', 'Pangasinan', 'San Carlos', NULL, '', 'active', '2026-11-07 11:19:07'),
(2, 'Vietnam', 'sdgsg', 'efsdgs', '', 'rert', 'dfdgfd', 'active', '2026-11-15 02:49:26');

-- --------------------------------------------------------

--
-- Table structure for table `deadlines`
--

CREATE TABLE `deadlines` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `requirement_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `employee_id_number` varchar(50) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forms`
--

CREATE TABLE `forms` (
  `id` int(11) NOT NULL,
  `form_name` varchar(255) DEFAULT NULL,
  `form_type` varchar(100) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `form_schema` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`form_schema`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `forms`
--

INSERT INTO `forms` (`id`, `form_name`, `form_type`, `file_path`, `status`, `created_at`, `form_schema`) VALUES
(1, 'Applicant Information Form', 'online', NULL, 'active', '2026-11-07 11:26:50', NULL),
(2, 'Travel Authorization Request', 'online', NULL, 'active', '2026-11-07 11:28:46', '[\r\n        {\"type\": \"text\", \"name\": \"destination\", \"label\": \"Destination Country\", \"required\": true, \"placeholder\": \"e.g. Vietnam\"},\r\n        {\"type\": \"date\", \"name\": \"departure_date\", \"label\": \"Departure Date\", \"required\": true},\r\n        {\"type\": \"select\", \"name\": \"travel_type\", \"label\": \"Type of Travel\", \"required\": true, \"options\": [\"Student Exchange\", \"Faculty Exchange\", \"Internship\", \"Conference\"]},\r\n        {\"type\": \"textarea\", \"name\": \"purpose\", \"label\": \"Purpose of Travel\", \"required\": true, \"placeholder\": \"Briefly explain the reason for your travel\"},\r\n        {\"type\": \"checkbox\", \"name\": \"agreement\", \"label\": \"I confirm that all information provided is accurate.\", \"required\": true}\r\n    ]');

-- --------------------------------------------------------

--
-- Table structure for table `form_submissions`
--

CREATE TABLE `form_submissions` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers`)),
  `status` enum('draft','submitted','approved') DEFAULT 'submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('application','approval','rejected','document','reminder','announcement','system') NOT NULL DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, '', 'New Application Received', 'Julio Obero submitted an application for IZN Exchange Student 2026.', 1, '2026-11-06 17:17:41'),
(2, 4, '', 'Application Approved', 'Congratulations! Your application for IZN Exchange Student 2026 has been approved.', 1, '2026-11-06 17:17:41'),
(3, 2, '', 'New Application Received', 'Julio Obero submitted an application for sddf.', 1, '2026-11-07 15:12:28'),
(4, 3, '', 'New Application Received', 'Julio Obero submitted an application for sddf.', 1, '2026-11-07 15:12:28'),
(5, 7, '', 'New Application Received', 'Julio Obero submitted an application for sddf.', 0, '2026-11-07 15:12:28'),
(6, 2, '', 'New Application Received', 'Julio Obero submitted an application for IZN Exchange Student 2028.', 1, '2026-11-07 16:56:16'),
(7, 3, '', 'New Application Received', 'Julio Obero submitted an application for IZN Exchange Student 2028.', 1, '2026-11-07 16:56:16'),
(8, 7, '', 'New Application Received', 'Julio Obero submitted an application for IZN Exchange Student 2028.', 0, '2026-11-07 16:56:16'),
(9, 4, 'rejected', 'Application Rejected', 'We regret to inform you that your application for sddf was not approved. Please review the details provided by the Internationalization Office.', 1, '2026-11-09 05:46:39'),
(10, 4, 'rejected', 'Application Rejected', 'We regret to inform you that your application for IZN Exchange Student 2026 was not approved. Please review the details provided by the Internationalization Office.', 1, '2026-11-10 14:02:51'),
(11, 4, 'rejected', 'Application Rejected', 'We regret to inform you that your application for IZN Exchange Student 2028 was not approved. Please review the details provided by the Internationalization Office.', 1, '2026-11-10 14:40:54'),
(12, 2, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for Faculty Exchange 2027.', 0, '2026-11-16 08:06:15'),
(13, 3, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for Faculty Exchange 2027.', 1, '2026-11-16 08:06:15'),
(14, 7, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for Faculty Exchange 2027.', 0, '2026-11-16 08:06:15'),
(15, 2, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for IZN student exchange 2027.', 0, '2026-11-22 15:41:56'),
(16, 3, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for IZN student exchange 2027.', 1, '2026-11-22 15:41:56'),
(17, 7, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for IZN student exchange 2027.', 0, '2026-11-22 15:41:56'),
(18, 3, 'rejected', 'Application Rejected', 'We regret to inform you that your application for IZN student exchange 2027 was not approved. Please review the details provided by the Internationalization Office.', 1, '2026-11-22 15:44:44'),
(19, 3, 'rejected', 'Application Rejected', 'Your application was rejected.', 1, '2026-11-22 15:44:44'),
(20, 2, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for IZN student exchange 2027.', 0, '2026-11-22 15:45:25'),
(21, 3, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for IZN student exchange 2027.', 1, '2026-11-22 15:45:25'),
(22, 7, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for IZN student exchange 2027.', 0, '2026-11-22 15:45:25'),
(23, 3, 'rejected', 'Application Rejected', 'We regret to inform you that your application for IZN student exchange 2027 was not approved. Please review the details provided by the Internationalization Office.', 1, '2026-11-22 15:48:58'),
(24, 3, 'rejected', 'Application Rejected', 'Your application was rejected.', 1, '2026-11-22 15:48:58'),
(25, 2, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for IZN student exchange 2027.', 0, '2026-11-22 15:49:14'),
(26, 3, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for IZN student exchange 2027.', 1, '2026-11-22 15:49:14'),
(27, 7, '', 'New Application Received', 'Wendy Alyssa Latorre submitted an application for IZN student exchange 2027.', 0, '2026-11-22 15:49:14'),
(28, 2, '', 'New Application Received', 'Wendy Latorre submitted an application for IZN student exchange 2027.', 0, '2026-11-22 15:52:37'),
(29, 3, '', 'New Application Received', 'Wendy Latorre submitted an application for IZN student exchange 2027.', 1, '2026-11-22 15:52:37'),
(30, 7, '', 'New Application Received', 'Wendy Latorre submitted an application for IZN student exchange 2027.', 0, '2026-11-22 15:52:37'),
(31, 3, 'rejected', 'Application Rejected', 'We regret to inform you that your application for IZN student exchange 2027 was not approved. Please review the details provided by the Internationalization Office.', 1, '2026-11-22 15:55:02'),
(32, 3, 'rejected', 'Application Rejected', 'Your application was rejected.', 1, '2026-11-22 15:55:02'),
(33, 9, 'rejected', 'Application Rejected', 'We regret to inform you that your application for IZN student exchange 2027 was not approved. Please review the details provided by the Internationalization Office.', 1, '2026-11-22 15:55:07'),
(34, 9, 'rejected', 'Application Rejected', 'Your application was rejected.', 1, '2026-11-22 15:55:07'),
(35, 2, '', 'New Application Received', 'Wendy Latorre submitted an application for IZN student exchange 2027.', 0, '2026-11-22 16:07:58'),
(36, 3, '', 'New Application Received', 'Wendy Latorre submitted an application for IZN student exchange 2027.', 1, '2026-11-22 16:07:58'),
(37, 7, '', 'New Application Received', 'Wendy Latorre submitted an application for IZN student exchange 2027.', 0, '2026-11-22 16:07:58'),
(38, 9, '', 'Application Approved', 'Congratulations! Your application for IZN student exchange 2027 has been approved. You may now proceed with the next requirements.', 1, '2026-11-22 17:35:24'),
(39, 9, 'approval', 'Application Approved', 'Your application has been approved.', 1, '2026-11-22 17:35:24');

-- --------------------------------------------------------

--
-- Table structure for table `partners`
--

CREATE TABLE `partners` (
  `id` int(11) NOT NULL,
  `institution_name` varchar(255) NOT NULL,
  `country_id` int(11) DEFAULT NULL,
  `contact_first_name` varchar(100) DEFAULT NULL,
  `contact_middle_name` varchar(100) DEFAULT NULL,
  `contact_last_name` varchar(100) DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `agreement_type_id` int(11) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `partners`
--

INSERT INTO `partners` (`id`, `institution_name`, `country_id`, `contact_first_name`, `contact_middle_name`, `contact_last_name`, `contact_email`, `agreement_type_id`, `expiry_date`, `notes`, `status`, `created_at`, `created_by`) VALUES
(78, 'Van Lang University', 5, 'Wendy', NULL, 'Latorre', 'latorrewendy5@gmail.com', 1, '2028-12-22', 'should fecth the institution name', 'Active', '2026-11-15 03:05:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `token_hash` char(64) DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `email`, `otp_hash`, `token_hash`, `attempts`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 9, '23bgu0353_ms@psu.edu.ph', NULL, 'c695246fb1e2e84eb2e911bbdb24b410a2d664bffdec4003ce6c37f234b91fa4', 0, '2026-11-22 16:27:45', '2026-11-22 16:15:56', '2026-11-22 16:12:45'),
(2, 9, '23bgu0353_ms@psu.edu.ph', NULL, '96647b5aca31a31d0e679cab67f6ecacbf560c7cf25db28f9d660063beb12539', 0, '2026-11-22 16:30:56', '2026-11-22 16:17:58', '2026-11-22 16:15:56'),
(3, 9, '23bgu0353_ms@psu.edu.ph', NULL, '3671be42b96f4d07cabbe6c6bd525ea76aeb2d8829dff896065f2f0e8dd31ae8', 0, '2026-11-22 16:32:58', NULL, '2026-11-22 16:17:58');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
  `program_name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `program_type` varchar(100) NOT NULL,
  `eligible_department` varchar(100) DEFAULT NULL,
  `eligible_account_type` enum('Student','Faculty') NOT NULL DEFAULT 'Student',
  `country_id` int(11) DEFAULT NULL,
  `partner_id` int(11) DEFAULT NULL,
  `agreement_type_id` int(11) DEFAULT NULL,
  `status` enum('Active','Upcoming','Completed','Suspended') DEFAULT 'Active',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `slots` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `program_name`, `category_id`, `program_type`, `eligible_department`, `eligible_account_type`, `country_id`, `partner_id`, `agreement_type_id`, `status`, `start_date`, `end_date`, `description`, `slots`, `created_at`, `created_by`) VALUES
(4, 'Faculty Exchange 2027', NULL, 'Faculty', 'All Departments', 'Faculty', 5, 78, NULL, 'Active', '2026-12-01', '2027-02-01', 'hshshs', 3, '2026-11-15 03:41:24', NULL),
(5, 'IZN student exchange 2027', NULL, 'Student Exchange', 'All Departments', 'Student', 5, 78, NULL, 'Upcoming', '2027-10-20', '2027-11-20', 'yrtyr', 2, '2026-11-22 09:36:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `program_category`
--

CREATE TABLE `program_category` (
  `id` int(11) NOT NULL,
  `category_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program_category`
--

INSERT INTO `program_category` (`id`, `category_name`, `description`, `status`, `created_at`) VALUES
(1, 'Faculty', 'mobility', 'Active', '2026-11-14 14:41:42'),
(2, 'Student Exchange', 'mobility student', 'Active', '2026-11-15 02:53:29');

-- --------------------------------------------------------

--
-- Table structure for table `program_requirements`
--

CREATE TABLE `program_requirements` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `requirement_id` int(11) NOT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `requirement_file` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program_requirements`
--

INSERT INTO `program_requirements` (`id`, `program_id`, `requirement_id`, `is_required`, `created_at`, `requirement_file`) VALUES
(2, 4, 1, 1, '2026-11-15 04:20:23', 'uploads/requirement_templates/57a214131efee9cf0c96ea5251115032.pdf'),
(3, 5, 1, 1, '2026-11-22 15:42:44', 'uploads/requirement_templates/ef349fb5c3705839e823a4fbf8a2fa3c.pdf'),
(4, 5, 2, 1, '2026-11-22 15:43:15', 'uploads/requirement_templates/92f302e9adf073c16fab17e94d363e37.pdf'),
(5, 5, 3, 1, '2026-11-22 15:43:22', 'uploads/requirement_templates/06c364bfd6b4947a7ac5b962c5a1dd53.pdf'),
(6, 5, 4, 1, '2026-11-22 15:43:28', 'uploads/requirement_templates/d19388733d44b8b37b65e2160400fbe3.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `requirements`
--

CREATE TABLE `requirements` (
  `id` int(11) NOT NULL,
  `requirement_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `requirements`
--

INSERT INTO `requirements` (`id`, `requirement_name`, `description`, `created_at`) VALUES
(1, 'travel', 'letter consent', '2026-11-10 14:30:24'),
(2, '1', 'ss', '2026-11-22 15:42:52'),
(3, '2', 'ere', '2026-11-22 15:42:57'),
(4, '3', '', '2026-11-22 15:43:01');

-- --------------------------------------------------------

--
-- Table structure for table `requirements_library`
--

CREATE TABLE `requirements_library` (
  `id` int(11) NOT NULL,
  `requirement_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `student_id_number` varchar(30) DEFAULT NULL,
  `program` varchar(150) DEFAULT NULL,
  `year_level` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `system_name` varchar(150) DEFAULT NULL,
  `organization` varchar(150) DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `semester` varchar(30) DEFAULT NULL,
  `session_timeout` int(11) DEFAULT 30,
  `password_length` int(11) DEFAULT 8,
  `login_attempts` int(11) DEFAULT 5,
  `allow_registration` tinyint(1) DEFAULT 1,
  `email_verification` tinyint(1) DEFAULT 1,
  `maintenance_mode` tinyint(1) DEFAULT 0,
  `maintenance_message` text DEFAULT NULL,
  `smtp_host` varchar(150) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_email` varchar(150) DEFAULT NULL,
  `smtp_sender` varchar(150) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `travel_information`
--

CREATE TABLE `travel_information` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `applicant_name` varchar(150) DEFAULT NULL,
  `departure_date` date DEFAULT NULL,
  `departure_time` time NOT NULL,
  `departure_airport` varchar(150) NOT NULL,
  `flight_number` varchar(20) NOT NULL,
  `arrival_date` date NOT NULL,
  `arrival_time` time NOT NULL,
  `arrival_airport` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `extension_name` varchar(20) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `student_id_number` varchar(30) DEFAULT NULL,
  `employee_id_number` varchar(30) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `sex` enum('Male','Female','Prefer not to say') DEFAULT NULL,
  `program` varchar(150) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `faculty_rank` varchar(100) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `user_role` enum('super admin','admin','applicant','faculty','campus coordinator','ovplia staff','approving official','bor secretary') DEFAULT 'applicant',
  `account_type` enum('Student','Faculty') NOT NULL DEFAULT 'Student',
  `status` enum('active','inactive') DEFAULT 'active',
  `password_hash` varchar(255) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `extension_name`, `last_name`, `suffix`, `student_id_number`, `employee_id_number`, `birthdate`, `sex`, `program`, `year_level`, `faculty_rank`, `email`, `department`, `contact`, `user_role`, `account_type`, `status`, `password_hash`, `reset_token`, `reset_token_expiry`, `created_at`, `profile_picture`) VALUES
(2, 'Sherwin John', NULL, NULL, 'Vizconde', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ADsvizconde@psuxizn.com', NULL, NULL, 'admin', 'Student', 'active', '$2y$10$RYL06MYWBZgawvOI/9mM.eoITFjwHakj/ugOQIVE.1RNh35YkS8VK', NULL, NULL, '2026-11-06 17:17:41', NULL),
(3, 'Wendy Alyssa', NULL, NULL, 'Latorre', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'SAwlatorre@psuxizn.com', NULL, NULL, 'super admin', 'Student', 'active', '$2y$10$lEArMgSGVfOSljWLLiPrmOUNRkYNyI1uaYPVZ72iKvWDU/myZkRiC', NULL, NULL, '2026-11-06 17:17:41', NULL),
(4, 'Julio', NULL, NULL, 'Obero', NULL, NULL, NULL, NULL, NULL, 'BSIT', NULL, NULL, 'APjobero@psuxizn.com', NULL, NULL, 'applicant', 'Student', 'active', '$2y$10$UyknPCZRSTarJVz7Xo1z9uODQDCek96tCYF6jff290.vX2Jh1n0RO', NULL, NULL, '2026-11-06 17:17:41', NULL),
(7, 'Kurt Ian', NULL, NULL, 'Lucas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ADklucas@psuxizn.com', NULL, NULL, 'admin', 'Student', 'active', '$2y$10$9RrMQvGFLmHPLaTN96X7ue385XZhLwwRjiztsfE0vDTLSDn5sIJ.e', NULL, NULL, '2026-11-06 17:17:41', NULL),
(9, 'Wendy', NULL, NULL, 'Latorre', NULL, '23-bgu-0353', NULL, '2004-09-13', 'Female', NULL, NULL, NULL, '23bgu0353_ms@psu.edu.ph', 'BS Information Technology major in Web and Mobile Technologies', '09876543219', 'applicant', 'Student', 'active', '$2y$10$FjX2PV.QXE09CcIu0Xx9SusYMNIkMHRmkjLQY/8BQwXEJhw1Y1SsK', NULL, NULL, '2026-11-13 09:13:18', NULL),
(10, 'Campus', NULL, NULL, 'Coordinator', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'CCccoordinator@psuxizn.com', NULL, NULL, 'campus coordinator', 'Student', 'active', '$2y$10$sN10YIzd9.kB2QaCNYu3oe2tFuofRZsT/n2WKFpe9cp63D5R/9ZSK', NULL, NULL, '2026-11-22 04:10:20', NULL),
(11, 'staff', NULL, NULL, 'Ovplia', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'OSsovplia@psuxizn.com', NULL, NULL, 'ovplia staff', 'Student', 'active', '$2y$10$avN.qzJbV3fKyjMmS1Yq3.5GGyZgx71sMQo2KG8KuWCD78vx0ZkWG', NULL, NULL, '2026-11-22 04:14:07', NULL),
(12, 'Campus', NULL, NULL, 'Officials', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AOcofficials@psuxizn.com', NULL, NULL, 'approving official', 'Student', 'active', '$2y$10$oN63Xs/5oUiJp7pbHKBKveoSxHIuHWVAYBDKKgQ4OTt8DnIrGRAGC', NULL, NULL, '2026-11-22 04:16:06', NULL),
(13, 'BOR', NULL, NULL, 'Secrectary', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BSbsecretary@psuxizn.com', NULL, NULL, 'bor secretary', 'Student', 'active', '$2y$10$2dFbzMwVgcs4QZM2.qww4u8yZ7VL8ZIVf7xOg3l.tnU59418BiS6i', NULL, NULL, '2026-11-22 04:19:12', NULL),
(14, 'Kurt', NULL, NULL, 'Lucas', NULL, '22-BGU-1283', NULL, '2003-08-10', 'Male', 'BS Information Technology', '4th Year', NULL, 'kurtian30@gmail.com', 'BS Information Technology major in Web and Mobile Technologies', NULL, 'applicant', 'Student', 'active', '$2y$10$PeDboCmsBJjFVZwwwTa7tOpJcmefGAzq1kMGDsISJHLJ87HX4s5I6', NULL, NULL, '2026-11-23 04:40:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_documents`
--

CREATE TABLE `user_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `requirement_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_documents`
--

INSERT INTO `user_documents` (`id`, `user_id`, `application_id`, `requirement_id`, `file_path`, `status`, `uploaded_at`) VALUES
(1, 9, NULL, 4, 'uploads/user_docs/e5cba53a41f0c73bf254915fa3c132bd.pdf', 'pending', '2026-11-23 01:14:38'),
(2, 9, NULL, 5, 'uploads/user_docs/368020f347d8a4c096ed767d086c3e2c.pdf', 'pending', '2026-11-23 01:14:47'),
(3, 9, NULL, 6, 'uploads/user_docs/946bb5c2539ff6d804fee0c3107ccac7.pdf', 'pending', '2026-11-23 01:14:55'),
(4, 9, NULL, 3, 'uploads/user_docs/7b70d348aa47b099fcd50c89fbc9b820.pdf', 'pending', '2026-11-23 01:15:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_monitoring`
--
ALTER TABLE `activity_monitoring`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `agreement_types`
--
ALTER TABLE `agreement_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_application_user` (`applicant_id`),
  ADD KEY `fk_application_program` (`program_id`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `countries_addresses`
--
ALTER TABLE `countries_addresses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `deadlines`
--
ALTER TABLE `deadlines`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_document_user` (`user_id`),
  ADD KEY `idx_documents_application` (`application_id`),
  ADD KEY `idx_documents_requirement` (`requirement_id`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `forms`
--
ALTER TABLE `forms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `form_submissions`
--
ALTER TABLE `form_submissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `form_id` (`form_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `partners`
--
ALTER TABLE `partners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_country` (`country_id`),
  ADD KEY `idx_agreement` (`agreement_type_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_password_resets_token_hash` (`token_hash`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `country_id` (`country_id`),
  ADD KEY `partner_id` (`partner_id`),
  ADD KEY `agreement_type_id` (`agreement_type_id`);

--
-- Indexes for table `program_category`
--
ALTER TABLE `program_category`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `program_requirements`
--
ALTER TABLE `program_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `requirement_id` (`requirement_id`);

--
-- Indexes for table `requirements`
--
ALTER TABLE `requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `requirements_library`
--
ALTER TABLE `requirements_library`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `travel_information`
--
ALTER TABLE `travel_information`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_app_requirement` (`application_id`,`requirement_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_monitoring`
--
ALTER TABLE `activity_monitoring`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agreement_types`
--
ALTER TABLE `agreement_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `countries_addresses`
--
ALTER TABLE `countries_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `deadlines`
--
ALTER TABLE `deadlines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forms`
--
ALTER TABLE `forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `form_submissions`
--
ALTER TABLE `form_submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `partners`
--
ALTER TABLE `partners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `program_category`
--
ALTER TABLE `program_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `program_requirements`
--
ALTER TABLE `program_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `requirements`
--
ALTER TABLE `requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `requirements_library`
--
ALTER TABLE `requirements_library`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `travel_information`
--
ALTER TABLE `travel_information`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `user_documents`
--
ALTER TABLE `user_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_monitoring`
--
ALTER TABLE `activity_monitoring`
  ADD CONSTRAINT `activity_monitoring_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_application_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_application_user` FOREIGN KEY (`applicant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `fk_document_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `faculty`
--
ALTER TABLE `faculty`
  ADD CONSTRAINT `faculty_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `form_submissions`
--
ALTER TABLE `form_submissions`
  ADD CONSTRAINT `fk_sub_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `partners`
--
ALTER TABLE `partners`
  ADD CONSTRAINT `fk_partner_agreement` FOREIGN KEY (`agreement_type_id`) REFERENCES `agreement_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_partner_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `programs_ibfk_2` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `programs_ibfk_3` FOREIGN KEY (`agreement_type_id`) REFERENCES `agreement_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `program_requirements`
--
ALTER TABLE `program_requirements`
  ADD CONSTRAINT `program_requirements_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `program_requirements_ibfk_2` FOREIGN KEY (`requirement_id`) REFERENCES `requirements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
