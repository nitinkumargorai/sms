-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 07, 2026 at 06:31 AM
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
-- Database: `users`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `get_branch_statistics` ()   BEGIN
    SELECT 
        b.id,
        b.branch_code,
        b.branch_name,
        b.short_name,
        b.description,
        b.is_active,
        b.established_year,
        (SELECT COUNT(*) FROM admission WHERE Branch = b.branch_code) as student_count,
        (SELECT COUNT(*) FROM teacher WHERE branch = b.branch_code) as teacher_count,
        (SELECT COUNT(*) FROM subjects WHERE branch = b.branch_code) as subject_count,
        b.created_at
    FROM branches b
    ORDER BY b.branch_code;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `search_students` (IN `p_search` VARCHAR(100), IN `p_branch` VARCHAR(50), IN `p_semester` INT)   BEGIN
    SELECT * FROM admission 
    WHERE (p_search IS NULL OR Name LIKE CONCAT('%', p_search, '%') OR Email LIKE CONCAT('%', p_search, '%') OR registration_no LIKE CONCAT('%', p_search, '%'))
    AND (p_branch IS NULL OR Branch = p_branch)
    AND (p_semester IS NULL OR Semester = p_semester)
    ORDER BY id DESC;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `get_student_count_by_branch` (`p_branch` VARCHAR(50)) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE student_count INT;
    SELECT COUNT(*) INTO student_count FROM admission WHERE Branch = p_branch;
    RETURN student_count;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `year` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_years`
--

INSERT INTO `academic_years` (`id`, `year`, `start_date`, `end_date`, `is_current`, `created_at`) VALUES
(1, '2025-2026', '2025-08-01', '2026-05-31', 1, '2026-03-01 10:37:26');

-- --------------------------------------------------------

--
-- Table structure for table `admission`
--

CREATE TABLE `admission` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `Name` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `registration_no` varchar(50) NOT NULL,
  `Branch` varchar(50) NOT NULL,
  `Semester` int(11) NOT NULL,
  `mobile` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `admission`
--
DELIMITER $$
CREATE TRIGGER `update_admission_timestamp` BEFORE UPDATE ON `admission` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admission_requests`
--

CREATE TABLE `admission_requests` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `registration_no` varchar(50) NOT NULL,
  `branch` varchar(50) NOT NULL,
  `semester` int(11) NOT NULL,
  `mobile` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `father_name` varchar(100) DEFAULT NULL,
  `mother_name` varchar(100) DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `due_date` date NOT NULL,
  `due_time` time DEFAULT NULL,
  `total_marks` int(11) DEFAULT 20,
  `file_path` varchar(255) DEFAULT NULL,
  `downloads` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `subject_id`, `teacher_id`, `title`, `description`, `due_date`, `due_time`, `total_marks`, `file_path`, `downloads`, `created_at`, `updated_at`) VALUES
(1, 32, 21, 'sdfsdf', 'sdfsdfs', '2026-05-06', '23:59:00', 20, 'uploads/assignments/1777964946_1704822641677.jpg', 0, '2026-05-05 07:09:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late') NOT NULL,
  `marked_by` int(11) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `subject_id`, `date`, `status`, `marked_by`, `remarks`, `created_at`) VALUES
(1, 7, 1, '2026-04-01', 'present', 8, NULL, '2026-04-01 14:51:03'),
(2, 2, 1, '2026-04-01', 'present', 8, NULL, '2026-04-01 14:51:03'),
(3, 7, 2, '2026-04-01', 'present', 8, NULL, '2026-04-01 14:52:00'),
(4, 2, 2, '2026-04-01', 'present', 8, NULL, '2026-04-01 14:52:00'),
(5, 10, 2, '2026-04-01', 'present', 8, NULL, '2026-04-01 14:52:00'),
(6, 8, 2, '2026-04-01', 'absent', 8, NULL, '2026-04-01 14:52:00');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `branch_code` varchar(20) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `short_name` varchar(10) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `established_year` year(4) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `branch_code`, `branch_name`, `short_name`, `description`, `established_year`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'CSE', 'Computer Science Engineering', 'CSE', 'Computer Science and Engineering department', NULL, 1, '2026-04-06 04:49:46', '2026-04-06 05:06:25'),
(13, 'ECE', 'Electrical Engineering', 'ECE', 'This is Our Electrical Engineering Branch. Most Welcome to all students.....', '2021', 1, '2026-05-05 06:17:26', NULL),
(14, 'MEA', 'Mechanical Engineering', 'MEA', 'This is the best branch.', '2026', 1, '2026-05-05 15:28:21', NULL);

--
-- Triggers `branches`
--
DELIMITER $$
CREATE TRIGGER `before_branch_delete` BEFORE DELETE ON `branches` FOR EACH ROW BEGIN
    DECLARE student_count INT;
    DECLARE teacher_count INT;
    DECLARE subject_count INT;
    
    SELECT COUNT(*) INTO student_count FROM admission WHERE Branch = OLD.branch_code;
    SELECT COUNT(*) INTO teacher_count FROM teacher WHERE branch = OLD.branch_code;
    SELECT COUNT(*) INTO subject_count FROM subjects WHERE branch = OLD.branch_code;
    
    IF student_count > 0 OR teacher_count > 0 OR subject_count > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot delete branch because it has associated students, teachers, or subjects';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `deleted_students_log`
--

CREATE TABLE `deleted_students_log` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `student_email` varchar(100) NOT NULL,
  `registration_no` varchar(50) NOT NULL,
  `deleted_by` int(11) NOT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deleted_students_log`
--

INSERT INTO `deleted_students_log` (`id`, `student_id`, `student_name`, `student_email`, `registration_no`, `deleted_by`, `deleted_at`) VALUES
(1, 14, 'Subham Gorai', 'subhamgorai2010@gmail.com', '', 0, '2026-05-07 04:20:23'),
(2, 13, 'Malay Kumar Gorai', 'malaykr1963@gmail.com', '', 0, '2026-05-07 04:20:25'),
(3, 12, 'Rohit Gope', 'student@gmail.com', '', 0, '2026-05-07 04:20:28');

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(20) DEFAULT NULL,
  `file_size` varchar(20) DEFAULT NULL,
  `upload_date` date NOT NULL,
  `downloads` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `usertype` enum('admin','teacher','student','all') DEFAULT 'all',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `usertype`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, NULL, 'all', 'Welcome to StudyBuddyHub', 'New academic session started', NULL, 1, '2026-03-01 10:37:26'),
(2, NULL, 'student', 'Assignment Deadline', 'Submit your assignments by Friday', NULL, 1, '2026-03-01 10:37:26'),
(3, 25, 'student', 'sdsdf', 'sdfsd', 'sdfsdf', 0, '2026-05-05 06:34:59'),
(4, 25, 'student', 'Notification', 'Please Clear Due Amount of this semester', '', 0, '2026-05-05 06:36:04');

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `exam_type` varchar(50) NOT NULL,
  `marks` int(11) NOT NULL,
  `max_marks` int(11) NOT NULL,
  `grade` varchar(5) DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_settings`
--

CREATE TABLE `student_settings` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `assignment_alerts` tinyint(1) NOT NULL DEFAULT 1,
  `material_updates` tinyint(1) NOT NULL DEFAULT 1,
  `attendance_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `short_name` varchar(20) DEFAULT NULL,
  `credits` int(11) NOT NULL,
  `branch` varchar(50) NOT NULL,
  `semester` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `short_name`, `credits`, `branch`, `semester`, `description`, `created_at`) VALUES
(28, 'CSE001', 'Physics', 'CSEPHY', 2, 'CSE', 1, '', '2026-04-08 11:46:49'),
(29, 'CSE', 'nothing', 'SLDKFJLSDKJF', 3, 'CSE', 1, '', '2026-04-08 11:57:13'),
(30, 'SDFSDF', 'sfsdfsd', 'SDFSD', 4, 'CSE', 2, '', '2026-04-08 11:57:55'),
(31, 'CSE111', 'ENGLISH TYPING', 'ENGLISH TYPING', 3, 'CSE', 3, '', '2026-04-10 04:16:46'),
(32, 'ECE-01', 'Electronics Basics and Fundamental', 'ECE', 4, 'ECE', 1, '', '2026-05-05 06:25:31'),
(33, 'ECE-02', 'Electronics Hardware', 'ECE', 6, 'ECE', 2, 'intermediate level subject', '2026-05-05 06:29:36'),
(34, 'MEA01', 'Mechanical Advance Engineering', 'MAE', 5, 'MEA', 1, 'Basics of MEA', '2026-05-05 15:31:55');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_date` date NOT NULL,
  `submission_time` time DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `remarks` text DEFAULT NULL,
  `marks` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `status` enum('submitted','late','graded') DEFAULT 'submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `syllabus`
--

CREATE TABLE `syllabus` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `unit_no` int(11) NOT NULL,
  `unit_title` varchar(255) NOT NULL,
  `topics` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `syllabus`
--

INSERT INTO `syllabus` (`id`, `subject_id`, `unit_no`, `unit_title`, `topics`, `created_at`) VALUES
(1, 1, 1, 'Introduction to Networks', 'OSI Model,TCP/IP,Network Topologies', '2026-03-01 10:37:26'),
(2, 1, 2, 'Data Link Layer', 'Error Detection,Flow Control,MAC', '2026-03-01 10:37:26'),
(3, 2, 1, 'Introduction to DBMS', 'Database Concepts,Data Models', '2026-03-01 10:37:26'),
(4, 2, 2, 'SQL', 'DDL,DML,Joins,Subqueries', '2026-03-01 10:37:26');

-- --------------------------------------------------------

--
-- Table structure for table `teacher`
--

CREATE TABLE `teacher` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile` varchar(10) NOT NULL,
  `branch` varchar(50) DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `experience` int(11) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher`
--

INSERT INTO `teacher` (`id`, `user_id`, `is_active`, `name`, `email`, `mobile`, `branch`, `qualification`, `experience`, `joining_date`, `address`, `dob`, `gender`, `created_at`, `updated_at`, `updated_by`) VALUES
(21, NULL, 1, 'Nitin', 'teacher@gmail.com', '9835289540', NULL, 'sdfds', 1, NULL, '', NULL, NULL, '2026-04-08 12:16:50', '2026-04-10 04:08:40', NULL),
(22, NULL, 1, 'Mahima Surin', 'mahimasurin@gmail.com', '9241732188', 'ECE', 'Diploma in ECE', 10, NULL, 'At: Chandil colony, Chandil', NULL, NULL, '2026-05-05 06:19:09', NULL, NULL),
(23, NULL, 1, 'Kundan Kumar', 'kundankumar@gmail.com', '9835289540', 'MEA', 'P. Hd', 0, NULL, 'Chandil dam ka piche taraf ka red color ghar\r\n', NULL, NULL, '2026-05-05 15:30:26', NULL, NULL);

--
-- Triggers `teacher`
--
DELIMITER $$
CREATE TRIGGER `update_teacher_timestamp` BEFORE UPDATE ON `teacher` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_settings`
--

CREATE TABLE `teacher_settings` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `assignment_alerts` tinyint(1) NOT NULL DEFAULT 1,
  `attendance_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `material_updates` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subjects`
--

CREATE TABLE `teacher_subjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `academic_year` varchar(20) DEFAULT '2025-2026',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_subjects`
--

INSERT INTO `teacher_subjects` (`id`, `teacher_id`, `subject_id`, `academic_year`, `created_at`) VALUES
(28, 8, 27, '2026-2027', '2026-04-08 07:21:21'),
(31, 8, 27, '2026-2028', '2026-04-08 07:29:55'),
(38, 21, 31, '2026-2027', '2026-04-15 09:50:12'),
(39, 21, 30, '2026-2027', '2026-05-05 04:28:02'),
(41, 21, 32, '2026-2027', '2026-05-05 07:05:59'),
(42, 23, 34, '2026-2027', '2026-05-05 15:33:00');

-- --------------------------------------------------------

--
-- Table structure for table `timetable`
--

CREATE TABLE `timetable` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `day` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_no` varchar(20) DEFAULT NULL,
  `branch` varchar(50) NOT NULL,
  `semester` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `timetable`
--

INSERT INTO `timetable` (`id`, `subject_id`, `teacher_id`, `day`, `start_time`, `end_time`, `room_no`, `branch`, `semester`, `academic_year`, `created_at`) VALUES
(1, 1, 1, 'Monday', '09:00:00', '10:00:00', 'Lab 101', 'CSE', 4, '2025-2026', '2026-03-01 10:37:26'),
(2, 2, 2, 'Monday', '10:00:00', '11:00:00', 'Room 203', 'CSE', 4, '2025-2026', '2026-03-01 10:37:26'),
(3, 3, 3, 'Tuesday', '11:00:00', '12:00:00', 'Lab 102', 'CSE', 4, '2025-2026', '2026-03-01 10:37:26'),
(4, 31, 21, 'Tuesday', '10:37:00', '00:37:00', '10', 'CSE', 3, '2025-2026', '2026-04-10 05:07:41');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `usertype` enum('admin','student','teacher') NOT NULL DEFAULT 'student',
  `profile_pic` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `username`, `email`, `password`, `password_hash`, `usertype`, `profile_pic`, `last_login`, `created_at`) VALUES
(1, 'Admin', 'admin@gmail.com', '1234', '$2y$10$pzaK1O6/z2oPNKZuIjyRzu/lBMbMHJ7G1aNjNXk5/eiBoC4Hi8SUO', 'admin', NULL, '2026-05-07 04:08:41', '2026-03-01 11:22:12'),
(24, 'Nitin', 'teacher@gmail.com', '123456', '$2y$10$zoZt5A5SZ4KLG5w2bKeJg.5AExEr2xZq23MmcYFkrv6bPm5j8njnq', 'teacher', NULL, '2026-05-07 03:50:23', '2026-04-08 12:16:50'),
(27, 'Mahima Surin', 'mahimasurin@gmail.com', '123456', '$2y$10$UfWgih2Nm03SNwJNAbcnEeTxD4TR4M1U2YCiaG9WjH91YaU6wNURi', 'teacher', NULL, '2026-05-05 06:27:43', '2026-05-05 06:19:09'),
(29, 'Kundan Kumar', 'kundankumar@gmail.com', '123456', '$2y$10$FQcMSznTCPuvlFB0J9ZuAeNKfuiW9ZxlxOrGG1AqUX4.RDvdwvRQC', 'teacher', NULL, '2026-05-05 15:33:14', '2026-05-05 15:30:26');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_pending_requests`
-- (See below for the actual view)
--
CREATE TABLE `view_pending_requests` (
`id` int(11)
,`name` varchar(100)
,`email` varchar(100)
,`registration_no` varchar(50)
,`branch` varchar(50)
,`semester` int(11)
,`mobile` varchar(10)
,`address` text
,`dob` date
,`gender` varchar(10)
,`father_name` varchar(100)
,`mother_name` varchar(100)
,`blood_group` varchar(5)
,`requested_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_students`
-- (See below for the actual view)
--
CREATE TABLE `view_students` (
`id` int(11)
,`Name` varchar(100)
,`Email` varchar(100)
,`registration_no` varchar(50)
,`Branch` varchar(50)
,`Semester` int(11)
,`mobile` varchar(10)
,`address` text
,`dob` date
,`gender` varchar(10)
,`father_name` varchar(100)
,`mother_name` varchar(100)
,`blood_group` varchar(5)
,`created_at` timestamp
,`username` varchar(100)
,`last_login` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_teachers`
-- (See below for the actual view)
--
CREATE TABLE `view_teachers` (
`id` int(11)
,`name` varchar(100)
,`email` varchar(100)
,`mobile` varchar(10)
,`branch` varchar(50)
,`qualification` varchar(255)
,`experience` int(11)
,`joining_date` date
,`address` text
,`dob` date
,`gender` varchar(10)
,`is_active` tinyint(1)
,`created_at` timestamp
,`username` varchar(100)
,`last_login` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `view_pending_requests`
--
DROP TABLE IF EXISTS `view_pending_requests`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_pending_requests`  AS SELECT `admission_requests`.`id` AS `id`, `admission_requests`.`name` AS `name`, `admission_requests`.`email` AS `email`, `admission_requests`.`registration_no` AS `registration_no`, `admission_requests`.`branch` AS `branch`, `admission_requests`.`semester` AS `semester`, `admission_requests`.`mobile` AS `mobile`, `admission_requests`.`address` AS `address`, `admission_requests`.`dob` AS `dob`, `admission_requests`.`gender` AS `gender`, `admission_requests`.`father_name` AS `father_name`, `admission_requests`.`mother_name` AS `mother_name`, `admission_requests`.`blood_group` AS `blood_group`, `admission_requests`.`requested_at` AS `requested_at` FROM `admission_requests` WHERE `admission_requests`.`status` = 'pending' ORDER BY `admission_requests`.`requested_at` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `view_students`
--
DROP TABLE IF EXISTS `view_students`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_students`  AS SELECT `a`.`id` AS `id`, `a`.`Name` AS `Name`, `a`.`Email` AS `Email`, `a`.`registration_no` AS `registration_no`, `a`.`Branch` AS `Branch`, `a`.`Semester` AS `Semester`, `a`.`mobile` AS `mobile`, `a`.`address` AS `address`, `a`.`dob` AS `dob`, `a`.`gender` AS `gender`, `a`.`father_name` AS `father_name`, `a`.`mother_name` AS `mother_name`, `a`.`blood_group` AS `blood_group`, `a`.`created_at` AS `created_at`, `u`.`username` AS `username`, `u`.`last_login` AS `last_login` FROM (`admission` `a` left join `user` `u` on(`a`.`user_id` = `u`.`id`)) WHERE `a`.`id` is not null ;

-- --------------------------------------------------------

--
-- Structure for view `view_teachers`
--
DROP TABLE IF EXISTS `view_teachers`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_teachers`  AS SELECT `t`.`id` AS `id`, `t`.`name` AS `name`, `t`.`email` AS `email`, `t`.`mobile` AS `mobile`, `t`.`branch` AS `branch`, `t`.`qualification` AS `qualification`, `t`.`experience` AS `experience`, `t`.`joining_date` AS `joining_date`, `t`.`address` AS `address`, `t`.`dob` AS `dob`, `t`.`gender` AS `gender`, `t`.`is_active` AS `is_active`, `t`.`created_at` AS `created_at`, `u`.`username` AS `username`, `u`.`last_login` AS `last_login` FROM (`teacher` `t` left join `user` `u` on(`t`.`user_id` = `u`.`id`)) WHERE `t`.`id` is not null ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year` (`year`);

--
-- Indexes for table `admission`
--
ALTER TABLE `admission`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD UNIQUE KEY `Registration No.` (`registration_no`),
  ADD KEY `fk_admission_user` (`user_id`),
  ADD KEY `idx_registration_no` (`registration_no`),
  ADD KEY `idx_branch_semester` (`Branch`,`Semester`);

--
-- Indexes for table `admission_requests`
--
ALTER TABLE `admission_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `registration_no` (`registration_no`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assignments_teacher_id` (`teacher_id`),
  ADD KEY `fk_assignments_subject` (`subject_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_attendance_record` (`student_id`,`subject_id`,`date`),
  ADD UNIQUE KEY `uniq_attendance` (`student_id`,`subject_id`,`date`),
  ADD KEY `idx_attendance_student_id` (`student_id`),
  ADD KEY `idx_attendance_marked_by` (`marked_by`),
  ADD KEY `fk_attendance_subject` (`subject_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branch_code` (`branch_code`);

--
-- Indexes for table `deleted_students_log`
--
ALTER TABLE `deleted_students_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_materials_teacher_id` (`teacher_id`),
  ADD KEY `fk_materials_subject` (`subject_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_results_student_id` (`student_id`),
  ADD KEY `fk_results_subject` (`subject_id`),
  ADD KEY `idx_student_subject` (`student_id`,`subject_id`);

--
-- Indexes for table `student_settings`
--
ALTER TABLE `student_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_settings` (`student_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`),
  ADD KEY `idx_branch_semester` (`branch`,`semester`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_submission` (`assignment_id`,`student_id`),
  ADD KEY `idx_submissions_student_id` (`student_id`);

--
-- Indexes for table `syllabus`
--
ALTER TABLE `syllabus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `teacher`
--
ALTER TABLE `teacher`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_teacher_branch` (`branch`),
  ADD KEY `idx_teacher_email` (`email`),
  ADD KEY `idx_teacher_user_id` (`user_id`);

--
-- Indexes for table `teacher_settings`
--
ALTER TABLE `teacher_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_teacher_settings` (`teacher_id`);

--
-- Indexes for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_teacher_subject_year` (`teacher_id`,`subject_id`,`academic_year`),
  ADD KEY `fk_teacher_subjects_subject` (`subject_id`);

--
-- Indexes for table `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_timetable_teacher_id` (`teacher_id`),
  ADD KEY `fk_timetable_subject` (`subject_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admission`
--
ALTER TABLE `admission`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `admission_requests`
--
ALTER TABLE `admission_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `deleted_students_log`
--
ALTER TABLE `deleted_students_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_settings`
--
ALTER TABLE `student_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `syllabus`
--
ALTER TABLE `syllabus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `teacher`
--
ALTER TABLE `teacher`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `teacher_settings`
--
ALTER TABLE `teacher_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `timetable`
--
ALTER TABLE `timetable`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admission`
--
ALTER TABLE `admission`
  ADD CONSTRAINT `fk_admission_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignments_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `fk_assignments_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`id`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendance_markedby` FOREIGN KEY (`marked_by`) REFERENCES `teacher` (`id`),
  ADD CONSTRAINT `fk_attendance_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `fk_materials_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `fk_materials_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`id`),
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `fk_results_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `fk_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`),
  ADD CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `syllabus`
--
ALTER TABLE `syllabus`
  ADD CONSTRAINT `syllabus_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher`
--
ALTER TABLE `teacher`
  ADD CONSTRAINT `fk_teacher_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_settings`
--
ALTER TABLE `teacher_settings`
  ADD CONSTRAINT `fk_teacher_settings_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD CONSTRAINT `fk_teacher_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_teacher_subjects_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ts_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `fk_ts_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`id`),
  ADD CONSTRAINT `teacher_subjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `fk_timetable_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `fk_timetable_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`id`),
  ADD CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
