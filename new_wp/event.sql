-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 06, 2025 at 11:07 AM
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
-- Database: `event`
--

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `id` int(11) NOT NULL,
  `club_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clubs`
--

INSERT INTO `clubs` (`id`, `club_name`) VALUES
(1, 'ELGE'),
(2, 'research'),
(3, 'cyber owls'),
(4, 'tarang'),
(5, 'impulse'),
(6, 'Code -IT');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `related_proposal_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `related_proposal_id`, `created_at`, `is_read`) VALUES
(1, 3, 'Your proposal \"nagula panchami\" has been approved.', 1, '2025-03-31 14:50:26', 0),
(2, 3, 'Query raised for your proposal \"webathon\": why that much budject', 2, '2025-03-31 17:06:08', 0),
(3, 3, 'Your proposal \"webathon\" has been approved.', 2, '2025-03-31 17:13:17', 0),
(4, 3, 'Your proposal \"Hackathon 2.0\" was rejected: Over budget and buses wont stop', 3, '2025-04-01 07:04:55', 0),
(5, 10, 'Your proposal \"Learnathon\" was rejected by Faculty Mentor: ', 7, '2025-04-04 15:14:02', 0);

-- --------------------------------------------------------

--
-- Table structure for table `proposals`
--

CREATE TABLE `proposals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `event_date` date NOT NULL,
  `event_location` varchar(255) DEFAULT NULL,
  `event_description` text DEFAULT NULL,
  `event_budget` decimal(10,2) DEFAULT NULL,
  `collaboration` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `query_details` text DEFAULT NULL,
  `query_deadline` date DEFAULT NULL,
  `query_response` text DEFAULT NULL,
  `club_id` int(11) NOT NULL,
  `faculty_mentor_status` varchar(20) DEFAULT 'Pending',
  `program_chair_status` varchar(20) DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `proposals`
--

INSERT INTO `proposals` (`id`, `user_id`, `event_name`, `event_type`, `event_date`, `event_location`, `event_description`, `event_budget`, `collaboration`, `created_at`, `query_details`, `query_deadline`, `query_response`, `club_id`, `faculty_mentor_status`, `program_chair_status`) VALUES
(1, 3, 'nagula panchami', 'Seminar', '2025-03-31', 'mpl', 'we will bring a snake', 12345.00, 'stme', '2025-03-31 14:19:50', NULL, NULL, NULL, 0, 'Pending', 'Pending'),
(2, 3, 'webathon', 'Workshop', '2025-04-09', 'library', 'creating webs', 20000.00, '', '2025-03-31 17:04:25', 'why that much budject', '2025-04-02', 'for gifts', 0, 'Pending', 'Pending'),
(3, 3, 'Hackathon 2.0', 'Competition', '2025-04-18', 'library', 'solutions for the problems', 20000.00, 'no', '2025-04-01 07:04:07', NULL, NULL, NULL, 0, 'Pending', 'Pending'),
(4, 7, 'poster presentation', 'Competition', '2025-04-04', 'mph', 'exploring research', 3000.00, 'no', '2025-04-03 11:02:44', NULL, NULL, NULL, 2, 'Approved', 'Pending'),
(5, 8, 'cricket', 'Competition', '2025-04-25', 'ground', 'playing', 500000.00, 'no', '2025-04-03 11:06:51', NULL, NULL, NULL, 5, 'Pending', 'Pending'),
(6, 10, 'Panel Discussion', 'Other', '2025-04-10', 'Auditorium', 'Panel Discussion ()', 5000.00, 'NO', '2025-04-04 14:51:08', NULL, NULL, NULL, 1, 'Approved', 'Pending'),
(7, 10, 'Learnathon', 'Workshop', '2025-04-05', 'Auditorium', 'Learning About AWS', 10000.00, 'No', '2025-04-04 15:12:47', NULL, NULL, NULL, 1, 'Rejected', 'Pending'),
(8, 10, 'Event new', 'Workshop', '2025-04-04', 'Library', 'Event New ', 7000.00, 'No', '2025-04-04 15:48:58', NULL, NULL, NULL, 1, 'Approved', 'Pending'),
(9, 10, 'New', 'Workshop', '2025-04-11', 'MPH', '/....', 5000.00, 'No', '2025-04-04 15:54:24', NULL, NULL, NULL, 1, 'Pending', 'Pending'),
(10, 10, 'Poster Presentation', 'Competition', '2025-04-24', 'Auditorium', 'Event Poster', 4999.00, 'No', '2025-04-04 18:54:51', NULL, NULL, NULL, 1, 'Pending', 'Pending'),
(11, 10, 'Poster Presentation 1234', 'Competition', '2025-04-12', 'Auditorium', 'Event Presentation', 12454.00, '-', '2025-04-04 18:57:54', NULL, NULL, NULL, 1, 'Pending', 'Pending'),
(12, 7, 'Research Paper', 'Competition', '2025-04-17', 'MPH', 'paper presentation', 20000.00, 'No', '2025-04-04 19:00:01', NULL, NULL, NULL, 2, 'Pending', 'Pending'),
(13, 10, 'Cricket match', 'Competition', '2025-04-11', 'Ground', 'Match', 4999.00, 'XLR8', '2025-04-04 19:11:05', NULL, NULL, NULL, 1, 'Approved', 'Pending'),
(14, 10, 'Hackathon 4.0', 'Competition', '2025-04-12', 'Library', 'This is the hackathon event ', 5000.00, 'No', '2025-04-05 04:48:50', NULL, NULL, NULL, 1, 'Pending', 'Pending'),
(15, 10, 'ISOC UA Day', 'Seminar', '2025-04-12', 'Microsoft', 'Seminar at Hyderabad', 18000.00, 'ISOC CLUB', '2025-04-05 08:34:04', NULL, NULL, NULL, 1, 'Approved', 'Approved'),
(16, 10, 'birthday of stme', 'Other', '2025-04-14', 'Central Lawn', 'Bithday of STME', 50000.00, 'NO', '2025-04-05 09:25:28', NULL, NULL, NULL, 1, 'Approved', 'Approved'),
(17, 7, 'debate', 'Competition', '2025-05-04', 'MPH', 'fight', 10000.00, 'no', '2025-04-05 09:33:04', NULL, NULL, NULL, 2, 'Approved', 'Approved'),
(18, 13, 'Webathon 3.0', 'Competition', '2025-04-19', 'Library', 'Competition .......', 10000.00, 'No', '2025-04-05 09:51:22', NULL, NULL, NULL, 6, 'Approved', 'Approved');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','head','student') NOT NULL,
  `sub_role` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `club_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `sub_role`, `created_at`, `club_id`) VALUES
(1, 'bunny', 'bunny@gmail.com', '$2y$10$VZAr8Y8yee1Cvh301iO8l.8VecT.XXFSdUNGYnD3O5kDosgsHh8JC', 'student', NULL, '2025-03-31 14:15:22', NULL),
(2, 'vidya', 'vidya@gmail.com', '$2y$10$4aLnoeiEv5mNgCuAlp2Xm.lfGFHiibmLjHQZlvUr00su6GUsLo8j2', 'admin', 'faculty-mentor', '2025-03-31 14:16:35', NULL),
(3, 'krishna', 'krishna@gmail.com', '$2y$10$jz7eOpUpeDrf3blqD28kEeHdJwUJ.n62EhhR0KGMD1cmxfzKlBNz2', 'head', NULL, '2025-03-31 14:18:58', NULL),
(4, 'clubhead', 'clubhead@gmail.com', '$2y$10$FSFtfUL9KcEfHJtwXGiyAeBgAVI9.HnnBuZukWefILBrGYP.DI0ey', 'head', NULL, '2025-04-03 09:44:25', NULL),
(5, 'wani', 'wani@gmail.com', '$2y$10$UTo3gU8sI4bfvCpgkXjwtOguJpzpRXkQhpQU4JG8e2E0FgTF/QXoS', 'admin', 'program-chair', '2025-04-03 10:44:50', NULL),
(6, 'naresh', 'naresh@gmail.com', '$2y$10$YTHsXlhmAIwsbI1Pw9owZuI24x3D.sdMBEsM6uqM2UGNYRh9GVtdG', 'admin', 'faculty-mentor', '2025-04-03 10:54:42', 1),
(7, 'trisha', 'trisha@gmail.com', '$2y$10$1iLeietzwkvC93njrPySH.vZMNpa8h76wPF0Wf6.jT8..mfvkd/w6', 'head', '', '2025-04-03 11:01:32', 2),
(8, 'SAI NATH', 'sai.nath@gmail.com', '$2y$10$j96FCutbgWrw0mgPzXCWJ.TYiQq4Qx9Dnc4c/eanMM2838r2tlJTi', 'head', '', '2025-04-03 11:05:50', 5),
(9, 'somu', 'somu@gmail.com', '$2y$10$vDp.ieUJIrffVBZUvYdvmO3UD.WWeIz5pEMM6K.yxtxrauTexioHi', 'admin', 'faculty-mentor', '2025-04-03 11:08:08', 2),
(10, 'Kuchuru Sai Krishna Reddy', 'kuchurusaikrishnareddy@gmail.com', '$2y$10$BlO7Hyvqmya7dBfI9ckKpO7p1jlPb/uvcfiJE8GtQkrFGj825FVQy', 'head', '', '2025-04-03 12:07:11', 1),
(11, 'Rayyan', 'rayyan@gmail.com', '$2y$10$6XXNusBiXBR/gxCZ7Zf4E.A0Gi42nVIU9LYX0Xlf4eJ7z2sbhohCW', 'student', '', '2025-04-05 09:00:08', NULL),
(12, 'Vinayak Mukkawar', 'vinayak@gmail.com', '$2y$10$MwA8Yd40heW2rY7wTL037eMiAqfdWDAcE0QSK70pHMvNyJWXEzuNO', 'admin', 'faculty-mentor', '2025-04-05 09:49:21', 6),
(13, 'Mounika ', 'mounika@gmail.com', '$2y$10$unqJC2L/pkHgdoCyYF2cNeizHJi.J8oZTpQTrj6Hd.Ftl6VJJckZW', 'head', '', '2025-04-05 09:50:25', 6),
(14, 'Rishitha', 'rishitha@gmail.com', '$2y$10$cYGc9YHlDZM.JKfj65FZquWjNvleaC//oNIDkDFo.FhHNyqWeG9HK', 'student', '', '2025-04-05 09:53:41', NULL);

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `prevent_multiple_club_heads` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.role = 'head' THEN
        IF EXISTS (
            SELECT 1 
            FROM users 
            WHERE club_id = NEW.club_id 
            AND role = 'head'
        ) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only one Club Head is allowed per club.';
        END IF;
    END IF;
END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `related_proposal_id` (`related_proposal_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `proposals`
--
ALTER TABLE `proposals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `proposals`
--
ALTER TABLE `proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`related_proposal_id`) REFERENCES `proposals` (`id`);

--
-- Constraints for table `proposals`
--
ALTER TABLE `proposals`
  ADD CONSTRAINT `proposals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
