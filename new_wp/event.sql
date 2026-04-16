-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2026 at 04:51 AM
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
-- Database: `event`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `actor_user_id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `summary` text NOT NULL,
  `related_proposal_id` int(11) DEFAULT NULL,
  `related_event_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `approval_logs`
--

CREATE TABLE `approval_logs` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `acted_by` int(11) NOT NULL,
  `role_name` varchar(100) DEFAULT NULL,
  `action_type` enum('submitted','approved','rejected','query_raised','resubmitted','forwarded') NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approval_logs`
--

INSERT INTO `approval_logs` (`id`, `proposal_id`, `acted_by`, `role_name`, `action_type`, `remarks`, `created_at`) VALUES
(1, 1, 33, 'club_head', 'submitted', 'Proposal submitted by club head.', '2026-04-15 17:29:14'),
(2, 1, 34, 'faculty_mentor', 'approved', '', '2026-04-15 17:53:46'),
(3, 1, 22, 'president_vc', 'approved', '', '2026-04-15 19:17:25'),
(4, 1, 23, 'gs_treasurer', 'approved', '', '2026-04-16 02:29:40'),
(5, 1, 21, 'school_head', 'rejected', 'why 10000', '2026-04-16 02:30:02'),
(6, 1, 33, 'club_head', 'resubmitted', 'Club Head responded: Sir we want 10000', '2026-04-16 02:32:55'),
(7, 1, 21, 'school_head', 'approved', '', '2026-04-16 02:36:37');

-- --------------------------------------------------------

--
-- Table structure for table `approval_workflow_steps`
--

CREATE TABLE `approval_workflow_steps` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `approver_user_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','query_raised','skipped','not_required','resubmitted','locked') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `acted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approval_workflow_steps`
--

INSERT INTO `approval_workflow_steps` (`id`, `proposal_id`, `step_order`, `role_name`, `approver_user_id`, `status`, `remarks`, `acted_at`) VALUES
(1, 1, 1, 'faculty_mentor', 34, 'approved', '', '2026-04-15 23:23:46'),
(2, 1, 2, 'gs_treasurer', 23, 'approved', '', '2026-04-16 07:59:40'),
(3, 1, 3, 'president_vc', 22, 'approved', '', '2026-04-16 00:47:25'),
(4, 1, 4, 'school_head', 21, 'approved', '', '2026-04-16 08:06:37'),
(5, 1, 5, 'it_team', NULL, 'pending', NULL, NULL),
(6, 1, 6, 'housekeeping', NULL, 'not_required', NULL, NULL),
(7, 1, 7, 'security_officer', NULL, 'pending', NULL, NULL),
(8, 1, 8, 'rector', NULL, 'pending', NULL, NULL),
(9, 1, 9, 'purchase_officer', NULL, 'pending', NULL, NULL),
(10, 1, 10, 'accounts_officer', NULL, 'pending', NULL, NULL),
(11, 1, 11, 'admin_office', NULL, 'pending', NULL, NULL),
(12, 1, 12, 'sports_dept', NULL, 'not_required', NULL, NULL),
(13, 1, 13, 'deputy_registrar', NULL, 'pending', NULL, NULL),
(14, 1, 14, 'deputy_director', NULL, 'pending', NULL, NULL),
(15, 1, 15, 'director', NULL, 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `blocked_dates`
--

CREATE TABLE `blocked_dates` (
  `id` int(11) NOT NULL,
  `block_date` date NOT NULL,
  `title` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `block_type` enum('exam','academic_event','holiday','maintenance') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blocked_dates`
--

INSERT INTO `blocked_dates` (`id`, `block_date`, `title`, `reason`, `block_type`, `created_at`) VALUES
(1, '2026-05-10', 'Mid Semester Exam', 'University exam schedule', 'exam', '2026-04-15 17:15:02'),
(2, '2026-05-11', 'Mid Semester Exam', 'University exam schedule', 'exam', '2026-04-15 17:15:02'),
(3, '2026-08-15', 'Independence Day', 'National holiday', 'holiday', '2026-04-15 17:15:02');

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `club_name` varchar(100) NOT NULL,
  `club_code` varchar(30) DEFAULT NULL,
  `club_logo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `club_head_user_id` int(11) DEFAULT NULL,
  `faculty_mentor_user_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clubs`
--

INSERT INTO `clubs` (`id`, `school_id`, `club_name`, `club_code`, `club_logo`, `description`, `club_head_user_id`, `faculty_mentor_user_id`, `status`, `created_at`) VALUES
(1, 1, 'SBM Innovators', 'SBMINNOV', NULL, NULL, 2, 3, 'active', '2026-04-15 17:15:02'),
(2, 2, 'Code IT', 'CODEIT', NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(3, 2, 'Cyber Owls', 'CYBOWL', NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(4, 2, 'Impulse', 'IMP', NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(5, 2, 'Tarang', 'TAR', NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(6, 3, 'Pharma Pulse', 'PHARMAPULSE', NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(7, 4, 'Lex Connect', 'LEXCONNECT', NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(8, 5, 'Commerce Nexus', 'COMNEX', NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(9, 2, 'ELGE', 'SDC ELGE', 'uploads/club_logos/club_69dfc8d7d1f840.12148709_ELGE SDC.png', 'SKILL DEVELOPMENT CENTRE', 33, 34, 'active', '2026-04-15 17:20:23');

-- --------------------------------------------------------

--
-- Table structure for table `collaborations`
--

CREATE TABLE `collaborations` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `invited_club_id` int(11) NOT NULL,
  `invited_by_user_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `department_tasks`
--

CREATE TABLE `department_tasks` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `department_role` varchar(100) NOT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed','not_required') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `acted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_tasks`
--

INSERT INTO `department_tasks` (`id`, `proposal_id`, `department_role`, `assigned_user_id`, `status`, `remarks`, `created_at`, `acted_at`) VALUES
(1, 1, 'food_admin', NULL, 'pending', NULL, '2026-04-15 17:29:14', NULL),
(2, 1, 'it_team', NULL, 'pending', NULL, '2026-04-15 17:29:14', NULL),
(3, 1, 'security_officer', NULL, 'pending', NULL, '2026-04-15 17:29:14', NULL),
(4, 1, 'accounts_officer', NULL, 'pending', NULL, '2026-04-15 17:29:14', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `registration_required` tinyint(1) DEFAULT 0,
  `registration_deadline` date DEFAULT NULL,
  `max_participants` int(11) DEFAULT 0,
  `event_status` enum('upcoming','ongoing','completed','cancelled') DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_gallery_albums`
--

CREATE TABLE `event_gallery_albums` (
  `id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_images`
--

CREATE TABLE `event_images` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `student_user_id` int(11) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attendance_status` enum('registered','attended','absent') DEFAULT 'registered'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_reports`
--

CREATE TABLE `event_reports` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `report_title` varchar(255) DEFAULT NULL,
  `report_description` text NOT NULL,
  `participants_count` int(11) DEFAULT NULL,
  `report_file_path` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('submitted','reviewed') DEFAULT 'submitted'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_report_visibility_log`
--

CREATE TABLE `event_report_visibility_log` (
  `id` int(11) NOT NULL,
  `event_report_id` int(11) NOT NULL,
  `visible_to_user_id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `is_viewed` tinyint(1) DEFAULT 0,
  `viewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `related_proposal_id` int(11) DEFAULT NULL,
  `related_event_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_proposal_id`, `related_event_id`, `is_read`, `created_at`) VALUES
(1, 23, 'Proposal Awaiting Your Approval', 'A proposal is ready for GS / Treasurer review.', 'approval', 1, NULL, 0, '2026-04-15 17:53:46'),
(2, 21, 'Proposal Awaiting Your Approval', 'A proposal is ready for School Head review.', 'approval', 1, NULL, 0, '2026-04-15 19:17:25'),
(3, 21, 'Proposal Awaiting Your Approval', 'A proposal is ready for School Head review.', 'approval', 1, NULL, 0, '2026-04-16 02:29:40'),
(4, 33, 'Proposal Rejected', 'School Head rejected your proposal. Reason: why 10000', 'rejected', 1, NULL, 0, '2026-04-16 02:30:02'),
(5, 21, 'Proposal Resubmitted', 'Club Head responded to your query/rejection and resubmitted the proposal.', 'resubmitted', 1, NULL, 0, '2026-04-16 02:32:55');

-- --------------------------------------------------------

--
-- Table structure for table `proposals`
--

CREATE TABLE `proposals` (
  `id` int(11) NOT NULL,
  `proposal_code` varchar(50) DEFAULT NULL,
  `submitted_by` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `current_approval_level` int(11) DEFAULT 1,
  `faculty_mentor_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Pending',
  `president_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Pending',
  `gs_treasurer_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Pending',
  `school_head_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Pending',
  `admin_officer_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Pending',
  `it_team_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `housekeeping_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `security_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `purchase_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `accounts_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `rector_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `sports_dept_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `dy_registrar_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `dy_director_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `deputy_director_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `director_status` enum('Pending','Approved','Rejected','Query Raised','Skipped','Not Required') DEFAULT 'Not Required',
  `event_name` varchar(255) NOT NULL,
  `event_type` varchar(100) DEFAULT NULL,
  `event_category` varchar(100) DEFAULT NULL,
  `event_mode` enum('offline','online','hybrid') DEFAULT 'offline',
  `submission_date` date DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_day` varchar(20) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `venue_id` int(11) DEFAULT NULL,
  `expected_participants` int(11) DEFAULT 0,
  `classes_first_year` tinyint(1) DEFAULT 0,
  `classes_second_year` tinyint(1) DEFAULT 0,
  `classes_third_year` tinyint(1) DEFAULT 0,
  `classes_fourth_year` tinyint(1) DEFAULT 0,
  `class_first_year` tinyint(1) DEFAULT 0,
  `class_second_year` tinyint(1) DEFAULT 0,
  `class_disruption_details` text DEFAULT NULL,
  `event_description` text DEFAULT NULL,
  `event_details` text DEFAULT NULL,
  `event_objective` text DEFAULT NULL,
  `minute_to_minute_schedule` text DEFAULT NULL,
  `guest_name` varchar(150) DEFAULT NULL,
  `guest_designation` varchar(150) DEFAULT NULL,
  `guest_organization` varchar(150) DEFAULT NULL,
  `guest_bio` text DEFAULT NULL,
  `guest_linkedin` varchar(255) DEFAULT NULL,
  `industry_guest` tinyint(1) DEFAULT 0,
  `travel_permission_required` tinyint(1) DEFAULT 0,
  `collaboration_required` tinyint(1) DEFAULT 0,
  `lead_club_id` int(11) DEFAULT NULL,
  `shared_budget` tinyint(1) DEFAULT 0,
  `shared_responsibilities` text DEFAULT NULL,
  `setup_start_time` time DEFAULT NULL,
  `cleanup_end_time` time DEFAULT NULL,
  `backup_venue_preference` varchar(150) DEFAULT NULL,
  `equipment_needed` text DEFAULT NULL,
  `declaration_head_name` varchar(100) DEFAULT NULL,
  `declaration_co_head_name` varchar(100) DEFAULT NULL,
  `declaration_head_mobile` varchar(20) DEFAULT NULL,
  `declaration_co_head_mobile` varchar(20) DEFAULT NULL,
  `declaration_agreed` tinyint(1) DEFAULT 0,
  `budget_total` decimal(12,2) DEFAULT 0.00,
  `other_requirements` text DEFAULT NULL,
  `grand_total` decimal(12,2) DEFAULT 0.00,
  `current_stage` varchar(100) DEFAULT 'faculty_mentor',
  `overall_status` enum('draft','submitted','under_faculty_mentor_review','under_president_vc_review','under_gs_treasurer_review','under_school_head_review','under_admin_office_review','under_service_clearance','under_deputy_registrar_review','under_dy_director_review','under_director_review','under_review','query_raised','rejected_pending_response','resubmitted','approved','locked','rejected','cancelled','closed','completed') DEFAULT 'submitted',
  `priority_level` enum('normal','high','placement','emergency') DEFAULT 'normal',
  `rejection_count` int(11) DEFAULT 0,
  `is_locked` tinyint(1) DEFAULT 0,
  `locked_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposals`
--

INSERT INTO `proposals` (`id`, `proposal_code`, `submitted_by`, `club_id`, `school_id`, `current_approval_level`, `faculty_mentor_status`, `president_status`, `gs_treasurer_status`, `school_head_status`, `admin_officer_status`, `it_team_status`, `housekeeping_status`, `security_status`, `purchase_status`, `accounts_status`, `rector_status`, `sports_dept_status`, `dy_registrar_status`, `dy_director_status`, `deputy_director_status`, `director_status`, `event_name`, `event_type`, `event_category`, `event_mode`, `submission_date`, `event_date`, `event_day`, `start_time`, `end_time`, `venue_id`, `expected_participants`, `classes_first_year`, `classes_second_year`, `classes_third_year`, `classes_fourth_year`, `class_first_year`, `class_second_year`, `class_disruption_details`, `event_description`, `event_details`, `event_objective`, `minute_to_minute_schedule`, `guest_name`, `guest_designation`, `guest_organization`, `guest_bio`, `guest_linkedin`, `industry_guest`, `travel_permission_required`, `collaboration_required`, `lead_club_id`, `shared_budget`, `shared_responsibilities`, `setup_start_time`, `cleanup_end_time`, `backup_venue_preference`, `equipment_needed`, `declaration_head_name`, `declaration_co_head_name`, `declaration_head_mobile`, `declaration_co_head_mobile`, `declaration_agreed`, `budget_total`, `other_requirements`, `grand_total`, `current_stage`, `overall_status`, `priority_level`, `rejection_count`, `is_locked`, `locked_reason`, `created_at`, `updated_at`) VALUES
(1, 'WP-2026-00001', 33, 9, 2, 5, 'Pending', 'Pending', 'Pending', 'Pending', 'Pending', 'Not Required', 'Not Required', 'Not Required', 'Pending', 'Pending', 'Pending', 'Not Required', 'Pending', 'Pending', 'Pending', 'Pending', 'Webathon 3.0', NULL, NULL, 'offline', '2026-04-15', '2026-04-25', 'Saturday', '10:00:00', '17:00:00', 4, 0, 1, 1, 0, 0, 1, 1, 'First Year, Second Year', 'Webathon 3.0', 'Webathon 3.0', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 9790.00, NULL, 9790.00, 'it_team', 'under_review', 'normal', 1, 0, NULL, '2026-04-15 17:29:14', '2026-04-16 02:36:37');

-- --------------------------------------------------------

--
-- Table structure for table `proposal_attachments`
--

CREATE TABLE `proposal_attachments` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proposal_budget_items`
--

CREATE TABLE `proposal_budget_items` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `rate` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposal_budget_items`
--

INSERT INTO `proposal_budget_items` (`id`, `proposal_id`, `item_name`, `quantity`, `rate`, `total`) VALUES
(1, 1, 'Transport', 0, 0.00, 0.00),
(2, 1, 'Executive Lunch/Dinner', 2, 320.00, 640.00),
(3, 1, 'Water Bottles', 40, 10.00, 400.00),
(4, 1, 'Bouquet', 0, 0.00, 0.00),
(5, 1, 'Gift/Memento', 10, 560.00, 5600.00),
(6, 1, 'Certificates', 0, 0.00, 0.00),
(7, 1, 'Medals', 0, 0.00, 0.00),
(8, 1, 'Others', 0, 0.00, 0.00),
(9, 1, 'certificates', 90, 35.00, 3150.00);

-- --------------------------------------------------------

--
-- Table structure for table `proposal_declaration`
--

CREATE TABLE `proposal_declaration` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `serial_no` int(11) NOT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `signature_text` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposal_declaration`
--

INSERT INTO `proposal_declaration` (`id`, `proposal_id`, `serial_no`, `student_name`, `mobile_number`, `signature_text`) VALUES
(1, 1, 1, 'Kuchuru Sai Krishna Reddy', '9392123577', 'Krishna'),
(2, 1, 2, 'Anoushka Sarkar', '4657898768', 'Sarkar');

-- --------------------------------------------------------

--
-- Table structure for table `proposal_declaration_members`
--

CREATE TABLE `proposal_declaration_members` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `member_name` varchar(100) NOT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `role_label` varchar(50) DEFAULT NULL,
  `signature_text` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposal_declaration_members`
--

INSERT INTO `proposal_declaration_members` (`id`, `proposal_id`, `member_name`, `mobile_number`, `role_label`, `signature_text`) VALUES
(1, 1, 'Kuchuru Sai Krishna Reddy', '9392123577', 'Head', 'Krishna'),
(2, 1, 'Anoushka Sarkar', '4657898768', 'Co-Head', 'Sarkar');

-- --------------------------------------------------------

--
-- Table structure for table `proposal_rejections`
--

CREATE TABLE `proposal_rejections` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `workflow_step_id` int(11) DEFAULT NULL,
  `rejected_by` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `rejection_reason` text NOT NULL,
  `rejection_count` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposal_rejections`
--

INSERT INTO `proposal_rejections` (`id`, `proposal_id`, `workflow_step_id`, `rejected_by`, `role_name`, `rejection_reason`, `rejection_count`, `created_at`) VALUES
(1, 1, 4, 21, 'school_head', 'why 10000', 1, '2026-04-16 02:30:02');

-- --------------------------------------------------------

--
-- Table structure for table `proposal_responses`
--

CREATE TABLE `proposal_responses` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `query_id` int(11) DEFAULT NULL,
  `responded_by` int(11) NOT NULL,
  `response_text` text NOT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposal_responses`
--

INSERT INTO `proposal_responses` (`id`, `proposal_id`, `query_id`, `responded_by`, `response_text`, `attachment_path`, `created_at`) VALUES
(1, 1, 1, 33, 'Sir we want 10000', NULL, '2026-04-16 02:32:55');

-- --------------------------------------------------------

--
-- Table structure for table `proposal_service_requirements`
--

CREATE TABLE `proposal_service_requirements` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `required` tinyint(1) DEFAULT 1,
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposal_service_requirements`
--

INSERT INTO `proposal_service_requirements` (`id`, `proposal_id`, `service_name`, `required`, `remarks`) VALUES
(1, 1, 'Lights and Sound System', 0, NULL),
(2, 1, 'Camera', 0, NULL),
(3, 1, 'Inauguration Lamp', 0, NULL),
(4, 1, 'Executive lunch/dinner', 1, NULL),
(5, 1, 'Projector', 1, NULL),
(6, 1, 'IT Support', 1, NULL),
(7, 1, 'Security', 1, NULL),
(8, 1, 'Sports Event / Venue', 0, NULL),
(9, 1, 'Other Resources', 0, '');

-- --------------------------------------------------------

--
-- Table structure for table `proposal_spoc`
--

CREATE TABLE `proposal_spoc` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `spoc_name` varchar(100) NOT NULL,
  `spoc_phone` varchar(20) NOT NULL,
  `spoc_email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposal_spoc`
--

INSERT INTO `proposal_spoc` (`id`, `proposal_id`, `spoc_name`, `spoc_phone`, `spoc_email`) VALUES
(1, 1, 'Vaishnavi', '7867876777', 'vaishnavi@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `queries`
--

CREATE TABLE `queries` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `workflow_step_id` int(11) DEFAULT NULL,
  `raised_by` int(11) NOT NULL,
  `raised_to` int(11) NOT NULL,
  `role_name` varchar(100) DEFAULT NULL,
  `query_type` enum('query','reject') DEFAULT 'query',
  `query_text` text NOT NULL,
  `club_response` text DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `status` enum('open','responded','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `queries`
--

INSERT INTO `queries` (`id`, `proposal_id`, `workflow_step_id`, `raised_by`, `raised_to`, `role_name`, `query_type`, `query_text`, `club_response`, `deadline`, `status`, `created_at`, `responded_at`) VALUES
(1, 1, 4, 21, 33, 'school_head', 'reject', 'why 10000', 'Sir we want 10000', NULL, 'responded', '2026-04-16 02:30:02', '2026-04-16 08:02:55');

-- --------------------------------------------------------

--
-- Table structure for table `resource_bookings`
--

CREATE TABLE `resource_bookings` (
  `id` int(11) NOT NULL,
  `proposal_id` int(11) NOT NULL,
  `venue_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `booking_status` enum('tentative','confirmed','ongoing','completed','cancelled') DEFAULT 'tentative',
  `priority_level` enum('normal','placement','emergency') DEFAULT 'normal',
  `conflict_flag` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `resource_bookings`
--

INSERT INTO `resource_bookings` (`id`, `proposal_id`, `venue_id`, `booking_date`, `start_time`, `end_time`, `booking_status`, `priority_level`, `conflict_flag`, `created_at`) VALUES
(1, 1, 4, '2026-04-25', '10:00:00', '17:00:00', 'tentative', 'normal', 0, '2026-04-15 17:29:14');

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `school_name` varchar(150) NOT NULL,
  `school_code` varchar(20) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`id`, `school_name`, `school_code`, `logo_path`, `created_at`) VALUES
(1, 'School of Business Management', 'SBM', NULL, '2026-04-15 17:15:02'),
(2, 'School of Technology Management and Engineering', 'STME', NULL, '2026-04-15 17:15:02'),
(3, 'School of Pharmacy and Technology Management', 'SPTM', NULL, '2026-04-15 17:15:02'),
(4, 'School of Law', 'SOL', NULL, '2026-04-15 17:15:02'),
(5, 'School of Commerce', 'SOC', NULL, '2026-04-15 17:15:02');

-- --------------------------------------------------------

--
-- Table structure for table `school_role_assignments`
--

CREATE TABLE `school_role_assignments` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_type` enum('school_head','president_vc','gs_treasurer') NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_role_assignments`
--

INSERT INTO `school_role_assignments` (`id`, `school_id`, `user_id`, `role_type`, `assigned_at`) VALUES
(1, 1, 18, 'school_head', '2026-04-15 17:15:02'),
(2, 1, 19, 'president_vc', '2026-04-15 17:15:02'),
(3, 1, 20, 'gs_treasurer', '2026-04-15 17:15:02'),
(4, 2, 21, 'school_head', '2026-04-15 18:43:18'),
(5, 2, 22, 'president_vc', '2026-04-15 17:15:02'),
(6, 2, 23, 'gs_treasurer', '2026-04-15 17:15:02'),
(7, 3, 24, 'school_head', '2026-04-15 17:15:02'),
(8, 3, 25, 'president_vc', '2026-04-15 17:15:02'),
(9, 3, 26, 'gs_treasurer', '2026-04-15 17:15:02'),
(10, 4, 27, 'school_head', '2026-04-15 17:15:02'),
(11, 4, 28, 'president_vc', '2026-04-15 17:15:02'),
(12, 4, 29, 'gs_treasurer', '2026-04-15 17:15:02'),
(13, 5, 30, 'school_head', '2026-04-15 17:15:02'),
(14, 5, 31, 'president_vc', '2026-04-15 17:15:02'),
(15, 5, 32, 'gs_treasurer', '2026-04-15 17:15:02');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('super_admin','club_head','faculty_mentor','president_vc','gs_treasurer','school_head','admin_office','administration_officer','administrative_officer','rector','deputy_registrar','dy_director','deputy_director','director','it_team','housekeeping','security_officer','purchase_officer','accounts_officer','sports_department','sports_dept','food_admin','student') NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `club_id` int(11) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `employee_student_id` varchar(50) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `phone`, `role`, `school_id`, `club_id`, `profile_image`, `employee_student_id`, `gender`, `department`, `status`, `created_at`) VALUES
(1, 'Super Admin', 'superadmin@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000001', 'super_admin', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(2, 'Club Head', 'clubhead@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000002', 'club_head', 1, 1, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(3, 'Faculty Mentor', 'mentor@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000003', 'faculty_mentor', 1, 1, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(4, 'President VC', 'president@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000004', 'president_vc', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(5, 'GS Treasurer', 'treasurer@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000005', 'gs_treasurer', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(6, 'School Head', 'schoolhead@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000006', 'school_head', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(7, 'Admin Office', 'adminoffice@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000007', 'admin_office', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(8, 'Deputy Registrar', 'dyregistrar@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000008', 'deputy_registrar', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(9, 'Director', 'director@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000009', 'director', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(10, 'IT Team', 'it@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000010', 'it_team', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(11, 'Housekeeping Team', 'housekeeping@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000011', 'housekeeping', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(12, 'Security Officer', 'security@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000012', 'security_officer', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(13, 'Purchase Officer', 'purchase@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000013', 'purchase_officer', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(14, 'Accounts Officer', 'accounts@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000014', 'accounts_officer', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(15, 'Sports Department', 'sports@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000015', 'sports_dept', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(16, 'Food Admin', 'foodadmin@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000016', 'food_admin', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(17, 'Student User', 'student@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000017', 'student', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(18, 'SBM School Head', 'sbmhead@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000101', 'school_head', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(19, 'SBM President VC', 'sbmpresident@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000102', 'president_vc', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(20, 'SBM GS Treasurer', 'sbmgs@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000103', 'gs_treasurer', 1, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(21, 'STME School Head', 'stmehead@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000104', 'school_head', 2, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(22, 'STME President VC', 'stmepresident@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000105', 'president_vc', 2, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(23, 'STME GS Treasurer', 'stmegs@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000106', 'gs_treasurer', 2, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(24, 'SPTM School Head', 'sptmhead@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000107', 'school_head', 3, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(25, 'SPTM President VC', 'sptmpresident@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000108', 'president_vc', 3, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(26, 'SPTM GS Treasurer', 'sptmgs@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000109', 'gs_treasurer', 3, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(27, 'SOL School Head', 'solhead@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000110', 'school_head', 4, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(28, 'SOL President VC', 'solpresident@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000111', 'president_vc', 4, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(29, 'SOL GS Treasurer', 'solgs@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000112', 'gs_treasurer', 4, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(30, 'SOC School Head', 'sochead@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000113', 'school_head', 5, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(31, 'SOC President VC', 'socpresident@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000114', 'president_vc', 5, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(32, 'SOC GS Treasurer', 'socgs@college.com', '$2y$10$UVKBsYMWa6/AoXgUWaAzbeEfXfUCa6z0nXIzCXbkH7iK.3LkCwdXG', '9000000115', 'gs_treasurer', 5, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-15 17:15:02'),
(33, 'Kuchuru Sai Krishna Reddy', 'elgehead@college.com', '$2y$10$NHE6exgie8odYMHzeEMGtOlIPaDfEFzlN0YSAWGpfuO9QCthttXf2', '9392123577', 'club_head', 2, 9, 'uploads/profile_images/profile_69dfc873b3bb21.10094787_krishna profile.jpg', '70572300034', 'male', 'STME', 'active', '2026-04-15 17:18:43'),
(34, 'Dr.Naresh Vurukonda', 'elgementor@college.com', '$2y$10$G62cEXe5a2zkpJfL3fpeAebQb/7d/lpdeek4X2RK8XpgAbVvgIfL2', '9908109980', 'faculty_mentor', 2, 9, NULL, NULL, 'male', 'STME', 'active', '2026-04-15 17:19:53');

-- --------------------------------------------------------

--
-- Table structure for table `venues`
--

CREATE TABLE `venues` (
  `id` int(11) NOT NULL,
  `venue_name` varchar(150) NOT NULL,
  `venue_type` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `location_details` text DEFAULT NULL,
  `managed_by_role` varchar(100) DEFAULT NULL,
  `status` enum('available','unavailable','maintenance') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `venues`
--

INSERT INTO `venues` (`id`, `venue_name`, `venue_type`, `capacity`, `location_details`, `managed_by_role`, `status`) VALUES
(1, 'Library 3rd Floor', 'library', 120, 'Main campus library', 'admin_office', 'available'),
(2, 'Library 2nd Floor', 'library', 120, 'Main campus library', 'admin_office', 'available'),
(3, 'Multi Purpose Hall', 'hall', 500, 'Central campus', 'admin_office', 'available'),
(4, 'Auditorium', 'auditorium', 700, 'Academic block', 'admin_office', 'available'),
(5, 'Computer Lab 3', 'lab', 80, 'Block C, Floor 2', 'it_team', 'available');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_activity_actor` (`actor_user_id`);

--
-- Indexes for table `approval_logs`
--
ALTER TABLE `approval_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_logs_user` (`acted_by`),
  ADD KEY `idx_logs_proposal` (`proposal_id`);

--
-- Indexes for table `approval_workflow_steps`
--
ALTER TABLE `approval_workflow_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_workflow_approver` (`approver_user_id`),
  ADD KEY `idx_workflow_proposal_step` (`proposal_id`,`step_order`),
  ADD KEY `idx_workflow_status` (`status`);

--
-- Indexes for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_block_date_title` (`block_date`,`title`);

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_clubs_school` (`school_id`);

--
-- Indexes for table `collaborations`
--
ALTER TABLE `collaborations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_collab_proposal` (`proposal_id`),
  ADD KEY `fk_collab_club` (`invited_club_id`),
  ADD KEY `fk_collab_user` (`invited_by_user_id`);

--
-- Indexes for table `department_tasks`
--
ALTER TABLE `department_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tasks_proposal` (`proposal_id`),
  ADD KEY `fk_tasks_user` (`assigned_user_id`),
  ADD KEY `idx_tasks_role_status` (`department_role`,`status`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_events_proposal` (`proposal_id`),
  ADD KEY `fk_events_venue` (`venue_id`);

--
-- Indexes for table `event_gallery_albums`
--
ALTER TABLE `event_gallery_albums`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gallery_album_club` (`club_id`),
  ADD KEY `fk_gallery_album_event` (`event_id`);

--
-- Indexes for table `event_images`
--
ALTER TABLE `event_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_event_image_event` (`event_id`),
  ADD KEY `fk_event_image_user` (`uploaded_by`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_event_student` (`event_id`,`student_user_id`),
  ADD KEY `fk_reg_student` (`student_user_id`);

--
-- Indexes for table `event_reports`
--
ALTER TABLE `event_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_event_report_event` (`event_id`),
  ADD KEY `fk_event_report_user` (`submitted_by`);

--
-- Indexes for table `event_report_visibility_log`
--
ALTER TABLE `event_report_visibility_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_report_visibility_report` (`event_report_id`),
  ADD KEY `fk_report_visibility_user` (`visible_to_user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_proposal` (`related_proposal_id`),
  ADD KEY `idx_notifications_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `proposals`
--
ALTER TABLE `proposals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proposal_code` (`proposal_code`),
  ADD KEY `fk_proposals_submitter` (`submitted_by`),
  ADD KEY `fk_proposals_school` (`school_id`),
  ADD KEY `fk_proposals_venue` (`venue_id`),
  ADD KEY `fk_proposals_lead_club` (`lead_club_id`),
  ADD KEY `idx_proposals_event_date` (`event_date`),
  ADD KEY `idx_proposals_approval_level` (`current_approval_level`),
  ADD KEY `idx_proposals_status` (`overall_status`),
  ADD KEY `idx_proposals_stage` (`current_stage`),
  ADD KEY `idx_proposals_club` (`club_id`);

--
-- Indexes for table `proposal_attachments`
--
ALTER TABLE `proposal_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attach_proposal` (`proposal_id`);

--
-- Indexes for table `proposal_budget_items`
--
ALTER TABLE `proposal_budget_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_budget_proposal` (`proposal_id`);

--
-- Indexes for table `proposal_declaration`
--
ALTER TABLE `proposal_declaration`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_proposal_declaration_proposal` (`proposal_id`,`serial_no`);

--
-- Indexes for table `proposal_declaration_members`
--
ALTER TABLE `proposal_declaration_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_decl_proposal` (`proposal_id`);

--
-- Indexes for table `proposal_rejections`
--
ALTER TABLE `proposal_rejections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_rejections_proposal` (`proposal_id`),
  ADD KEY `fk_rejections_step` (`workflow_step_id`),
  ADD KEY `fk_rejections_user` (`rejected_by`);

--
-- Indexes for table `proposal_responses`
--
ALTER TABLE `proposal_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_responses_proposal` (`proposal_id`),
  ADD KEY `fk_responses_query` (`query_id`),
  ADD KEY `fk_responses_user` (`responded_by`);

--
-- Indexes for table `proposal_service_requirements`
--
ALTER TABLE `proposal_service_requirements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_service_proposal` (`proposal_id`),
  ADD KEY `idx_service_name` (`service_name`);

--
-- Indexes for table `proposal_spoc`
--
ALTER TABLE `proposal_spoc`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_spoc_proposal` (`proposal_id`);

--
-- Indexes for table `queries`
--
ALTER TABLE `queries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_queries_proposal` (`proposal_id`),
  ADD KEY `fk_queries_step` (`workflow_step_id`),
  ADD KEY `fk_queries_raised_by` (`raised_by`),
  ADD KEY `fk_queries_raised_to` (`raised_to`),
  ADD KEY `idx_queries_status` (`status`);

--
-- Indexes for table `resource_bookings`
--
ALTER TABLE `resource_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_booking_proposal` (`proposal_id`),
  ADD KEY `idx_booking_venue_time` (`venue_id`,`booking_date`,`start_time`,`end_time`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `school_code` (`school_code`);

--
-- Indexes for table `school_role_assignments`
--
ALTER TABLE `school_role_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_school_role` (`school_id`,`role_type`),
  ADD KEY `fk_school_role_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_school` (`school_id`),
  ADD KEY `idx_users_club` (`club_id`);

--
-- Indexes for table `venues`
--
ALTER TABLE `venues`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `approval_logs`
--
ALTER TABLE `approval_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `approval_workflow_steps`
--
ALTER TABLE `approval_workflow_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `blocked_dates`
--
ALTER TABLE `blocked_dates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `collaborations`
--
ALTER TABLE `collaborations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department_tasks`
--
ALTER TABLE `department_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_gallery_albums`
--
ALTER TABLE `event_gallery_albums`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_images`
--
ALTER TABLE `event_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_reports`
--
ALTER TABLE `event_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_report_visibility_log`
--
ALTER TABLE `event_report_visibility_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `proposals`
--
ALTER TABLE `proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `proposal_attachments`
--
ALTER TABLE `proposal_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proposal_budget_items`
--
ALTER TABLE `proposal_budget_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `proposal_declaration`
--
ALTER TABLE `proposal_declaration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `proposal_declaration_members`
--
ALTER TABLE `proposal_declaration_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `proposal_rejections`
--
ALTER TABLE `proposal_rejections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `proposal_responses`
--
ALTER TABLE `proposal_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `proposal_service_requirements`
--
ALTER TABLE `proposal_service_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `proposal_spoc`
--
ALTER TABLE `proposal_spoc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `queries`
--
ALTER TABLE `queries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `resource_bookings`
--
ALTER TABLE `resource_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `school_role_assignments`
--
ALTER TABLE `school_role_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `venues`
--
ALTER TABLE `venues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `approval_logs`
--
ALTER TABLE `approval_logs`
  ADD CONSTRAINT `fk_logs_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`acted_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `approval_workflow_steps`
--
ALTER TABLE `approval_workflow_steps`
  ADD CONSTRAINT `fk_workflow_approver` FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_workflow_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clubs`
--
ALTER TABLE `clubs`
  ADD CONSTRAINT `fk_clubs_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`);

--
-- Constraints for table `collaborations`
--
ALTER TABLE `collaborations`
  ADD CONSTRAINT `fk_collab_club` FOREIGN KEY (`invited_club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `fk_collab_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_collab_user` FOREIGN KEY (`invited_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `department_tasks`
--
ALTER TABLE `department_tasks`
  ADD CONSTRAINT `fk_tasks_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tasks_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`),
  ADD CONSTRAINT `fk_events_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`);

--
-- Constraints for table `event_gallery_albums`
--
ALTER TABLE `event_gallery_albums`
  ADD CONSTRAINT `fk_gallery_album_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_gallery_album_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_images`
--
ALTER TABLE `event_images`
  ADD CONSTRAINT `fk_event_image_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_image_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `fk_reg_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reg_student` FOREIGN KEY (`student_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_reports`
--
ALTER TABLE `event_reports`
  ADD CONSTRAINT `fk_event_report_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_report_user` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `event_report_visibility_log`
--
ALTER TABLE `event_report_visibility_log`
  ADD CONSTRAINT `fk_report_visibility_report` FOREIGN KEY (`event_report_id`) REFERENCES `event_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_report_visibility_user` FOREIGN KEY (`visible_to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_proposal` FOREIGN KEY (`related_proposal_id`) REFERENCES `proposals` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `proposals`
--
ALTER TABLE `proposals`
  ADD CONSTRAINT `fk_proposals_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `fk_proposals_lead_club` FOREIGN KEY (`lead_club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `fk_proposals_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  ADD CONSTRAINT `fk_proposals_submitter` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_proposals_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`);

--
-- Constraints for table `proposal_attachments`
--
ALTER TABLE `proposal_attachments`
  ADD CONSTRAINT `fk_attach_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proposal_budget_items`
--
ALTER TABLE `proposal_budget_items`
  ADD CONSTRAINT `fk_budget_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proposal_declaration`
--
ALTER TABLE `proposal_declaration`
  ADD CONSTRAINT `fk_proposal_declaration_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proposal_declaration_members`
--
ALTER TABLE `proposal_declaration_members`
  ADD CONSTRAINT `fk_decl_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proposal_rejections`
--
ALTER TABLE `proposal_rejections`
  ADD CONSTRAINT `fk_rejections_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rejections_step` FOREIGN KEY (`workflow_step_id`) REFERENCES `approval_workflow_steps` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_rejections_user` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proposal_responses`
--
ALTER TABLE `proposal_responses`
  ADD CONSTRAINT `fk_responses_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_responses_query` FOREIGN KEY (`query_id`) REFERENCES `queries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_responses_user` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proposal_service_requirements`
--
ALTER TABLE `proposal_service_requirements`
  ADD CONSTRAINT `fk_service_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proposal_spoc`
--
ALTER TABLE `proposal_spoc`
  ADD CONSTRAINT `fk_spoc_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `queries`
--
ALTER TABLE `queries`
  ADD CONSTRAINT `fk_queries_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_queries_raised_by` FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_queries_raised_to` FOREIGN KEY (`raised_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_queries_step` FOREIGN KEY (`workflow_step_id`) REFERENCES `approval_workflow_steps` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `resource_bookings`
--
ALTER TABLE `resource_bookings`
  ADD CONSTRAINT `fk_booking_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_venue` FOREIGN KEY (`venue_id`) REFERENCES `venues` (`id`);

--
-- Constraints for table `school_role_assignments`
--
ALTER TABLE `school_role_assignments`
  ADD CONSTRAINT `fk_school_role_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_school_role_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `fk_users_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
