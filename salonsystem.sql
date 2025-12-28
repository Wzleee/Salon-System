-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 21, 2025 at 05:05 PM
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
-- Database: `salonsystem`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointment`
--

CREATE TABLE `appointment` (
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stylist_id` int(11) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('Confirmed','Completed','Cancelled','No-Show') DEFAULT 'Confirmed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointment`
--

INSERT INTO `appointment` (`appointment_id`, `user_id`, `stylist_id`, `total_price`, `appointment_date`, `appointment_time`, `status`, `created_at`, `updated_at`) VALUES
(60, 37, 32, 22.00, '2025-12-19', '09:00:00', 'Confirmed', '2025-12-19 00:54:44', '2025-12-19 00:54:44'),
(61, 37, 32, 55.00, '2025-12-23', '09:00:00', 'Cancelled', '2025-12-19 00:55:31', '2025-12-19 00:55:50'),
(62, 1, 32, 15.00, '2025-12-26', '11:00:00', 'Cancelled', '2025-12-19 01:44:13', '2025-12-21 12:23:49'),
(63, 36, 32, 35.00, '2025-12-24', '09:00:00', 'Cancelled', '2025-12-19 01:53:34', '2025-12-19 01:55:52'),
(64, 36, 34, 20.00, '2025-12-24', '09:30:00', 'Confirmed', '2025-12-19 01:56:28', '2025-12-19 01:56:28'),
(65, 36, 34, 22.00, '2025-12-24', '10:00:00', 'Confirmed', '2025-12-19 01:57:14', '2025-12-19 01:57:14'),
(66, 36, 32, 20.00, '2025-12-26', '10:00:00', 'Confirmed', '2025-12-19 01:57:46', '2025-12-19 01:57:46'),
(67, 1, 35, 20.00, '2025-12-22', '13:30:00', 'Cancelled', '2025-12-21 06:15:07', '2025-12-21 06:15:30');

-- --------------------------------------------------------

--
-- Table structure for table `appointmentitem`
--

CREATE TABLE `appointmentitem` (
  `item_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `service_price` decimal(10,2) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointmentitem`
--

INSERT INTO `appointmentitem` (`item_id`, `appointment_id`, `service_id`, `service_price`, `quantity`, `created_at`) VALUES
(66, 60, 10, 22.00, 1, '2025-12-19 00:54:44'),
(67, 61, 32, 55.00, 1, '2025-12-19 00:55:31'),
(68, 62, 20, 15.00, 1, '2025-12-19 01:44:13'),
(69, 63, 20, 15.00, 1, '2025-12-19 01:53:34'),
(70, 63, 17, 20.00, 1, '2025-12-19 01:53:34'),
(71, 64, 66, 20.00, 1, '2025-12-19 01:56:28'),
(72, 65, 10, 22.00, 1, '2025-12-19 01:57:14'),
(73, 66, 66, 20.00, 1, '2025-12-19 01:57:46'),
(74, 67, 66, 20.00, 1, '2025-12-21 06:15:07');

-- --------------------------------------------------------

--
-- Table structure for table `auditlog`
--

CREATE TABLE `auditlog` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `category` enum('login','logout') NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auditlog`
--

INSERT INTO `auditlog` (`log_id`, `user_id`, `action`, `category`, `description`, `created_at`) VALUES
(114, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-19 00:23:25'),
(115, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-19 00:27:35'),
(116, 1, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 00:28:09'),
(117, 36, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 00:29:10'),
(118, 37, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 00:30:00'),
(119, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-19 00:30:09'),
(120, 1, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 00:31:09'),
(121, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-19 00:31:39'),
(122, 1, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 00:50:36'),
(123, 37, 'Logged in', 'login', 'User logged into the system', '2025-12-19 00:54:18'),
(124, 37, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 00:56:14'),
(125, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-19 00:56:31'),
(126, 1, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 01:18:34'),
(127, 37, 'Logged in', 'login', 'User logged into the system', '2025-12-19 01:21:27'),
(128, 37, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 01:21:42'),
(129, 39, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 01:27:39'),
(130, 36, 'Logged in', 'login', 'User logged into the system', '2025-12-19 01:29:31'),
(131, 36, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 01:29:50'),
(132, 36, 'Logged in', 'login', 'User logged into the system', '2025-12-19 01:30:03'),
(133, 36, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 01:30:15'),
(134, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-19 01:30:39'),
(135, 1, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 01:36:36'),
(136, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-19 01:36:43'),
(137, 1, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 01:51:34'),
(138, 36, 'Logged in', 'login', 'User logged into the system', '2025-12-19 01:51:46'),
(139, 36, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 01:58:28'),
(140, 34, 'Logged in', 'login', 'User logged into the system', '2025-12-19 02:00:14'),
(141, 34, 'Logged out', 'logout', 'User logged out from the system', '2025-12-19 02:00:58'),
(142, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-19 02:02:05'),
(143, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-21 05:40:39'),
(144, 1, 'Logged out', 'logout', 'User logged out from the system', '2025-12-21 06:34:58'),
(145, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-21 08:07:37'),
(146, 1, 'Logged in', 'login', 'User logged into the system', '2025-12-21 11:01:04');

-- --------------------------------------------------------

--
-- Table structure for table `businesshours`
--

CREATE TABLE `businesshours` (
  `business_hour_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `opening_time` time NOT NULL,
  `closing_time` time NOT NULL,
  `is_closed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `businesshours`
--

INSERT INTO `businesshours` (`business_hour_id`, `day_of_week`, `opening_time`, `closing_time`, `is_closed`, `created_at`, `updated_at`) VALUES
(1, 'Monday', '09:00:00', '18:00:00', 0, '2025-11-26 11:42:53', '2025-12-21 15:28:29'),
(2, 'Tuesday', '09:00:00', '18:00:00', 0, '2025-11-26 11:42:53', '2025-12-21 15:28:29'),
(3, 'Wednesday', '09:00:00', '18:00:00', 0, '2025-11-26 11:42:53', '2025-12-21 15:28:29'),
(4, 'Thursday', '09:00:00', '18:00:00', 0, '2025-11-26 11:42:53', '2025-12-21 15:28:29'),
(5, 'Friday', '09:00:00', '18:00:00', 0, '2025-11-26 11:42:53', '2025-12-21 15:28:29'),
(6, 'Saturday', '00:00:00', '00:00:00', 1, '2025-11-26 11:42:53', '2025-12-21 15:28:29'),
(7, 'Sunday', '00:00:00', '00:00:00', 1, '2025-11-26 11:42:53', '2025-12-21 15:28:29');

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `category_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Hair Services', 'Active', '2025-11-26 11:42:53', '2025-12-04 15:24:23'),
(2, 'Nail Services', 'Active', '2025-11-26 11:42:53', '2025-12-04 15:34:43'),
(3, 'Facial & Skincare', 'Active', '2025-11-26 11:42:53', '2025-12-04 15:34:57'),
(4, 'Body Treatment', 'Active', '2025-11-26 11:42:53', '2025-12-04 15:35:11'),
(5, 'Makeup & Beauty', 'Active', '2025-11-28 16:24:30', '2025-12-04 15:35:22'),
(6, 'Waxing & Hair Removal', 'Active', '2025-12-03 01:34:14', '2025-12-04 15:35:50'),
(7, 'Men’s Grooming', 'Active', '2025-12-04 15:36:48', '2025-12-04 15:36:48'),
(9, 'fvdshvjlk', 'Active', '2025-12-10 01:31:50', '2025-12-10 01:31:50'),
(12, 'Body', 'Active', '2025-12-19 01:37:48', '2025-12-19 01:37:48');

-- --------------------------------------------------------

--
-- Table structure for table `holiday`
--

CREATE TABLE `holiday` (
  `holiday_id` int(11) NOT NULL,
  `holiday_name` varchar(100) NOT NULL,
  `holiday_date` date NOT NULL,
  `is_recurring` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `holiday`
--

INSERT INTO `holiday` (`holiday_id`, `holiday_name`, `holiday_date`, `is_recurring`, `created_at`, `updated_at`) VALUES
(3, 'Christmas Day', '2025-12-25', 1, '2025-11-26 11:42:53', '2025-11-26 11:42:53'),
(4, 'New Year\'s Day', '2025-01-01', 1, '2025-11-26 11:42:53', '2025-11-26 11:42:53');

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `schedule_id` int(11) NOT NULL,
  `stylist_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `override_date` date DEFAULT NULL,
  `schedule_scope` enum('weekly','date') NOT NULL DEFAULT 'weekly',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`schedule_id`, `stylist_id`, `day_of_week`, `override_date`, `schedule_scope`, `start_time`, `end_time`, `break_start`, `break_end`, `is_available`, `created_at`, `updated_at`) VALUES
(105, 32, 'Monday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:24:41', '2025-12-19 00:24:41'),
(106, 32, 'Tuesday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:24:41', '2025-12-19 00:24:41'),
(107, 32, 'Wednesday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:24:41', '2025-12-19 00:24:41'),
(108, 32, 'Thursday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:24:41', '2025-12-19 00:24:41'),
(109, 32, 'Friday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:24:41', '2025-12-19 00:24:41'),
(110, 32, 'Saturday', NULL, 'weekly', NULL, NULL, NULL, NULL, 0, '2025-12-19 00:24:41', '2025-12-19 00:24:41'),
(111, 32, 'Sunday', NULL, 'weekly', NULL, NULL, NULL, NULL, 0, '2025-12-19 00:24:41', '2025-12-19 00:24:41'),
(112, 33, 'Monday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:26:21', '2025-12-19 00:26:21'),
(113, 33, 'Tuesday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:26:21', '2025-12-19 00:26:21'),
(114, 33, 'Wednesday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:26:21', '2025-12-19 00:26:21'),
(115, 33, 'Thursday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:26:21', '2025-12-19 00:26:21'),
(116, 33, 'Friday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:26:21', '2025-12-19 00:26:21'),
(117, 33, 'Saturday', NULL, 'weekly', NULL, NULL, NULL, NULL, 0, '2025-12-19 00:26:21', '2025-12-19 00:26:21'),
(118, 33, 'Sunday', NULL, 'weekly', NULL, NULL, NULL, NULL, 0, '2025-12-19 00:26:21', '2025-12-19 00:26:21'),
(119, 34, 'Monday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:45:53', '2025-12-19 00:45:53'),
(120, 34, 'Tuesday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:45:53', '2025-12-19 00:45:53'),
(121, 34, 'Wednesday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:45:53', '2025-12-19 00:45:53'),
(122, 34, 'Thursday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:45:53', '2025-12-19 00:45:53'),
(123, 34, 'Friday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 00:45:53', '2025-12-19 00:45:53'),
(124, 34, 'Saturday', NULL, 'weekly', NULL, NULL, NULL, NULL, 0, '2025-12-19 00:45:53', '2025-12-19 00:45:53'),
(125, 34, 'Sunday', NULL, 'weekly', NULL, NULL, NULL, NULL, 0, '2025-12-19 00:45:53', '2025-12-19 00:45:53'),
(126, 35, 'Monday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 01:34:03', '2025-12-19 01:34:03'),
(127, 35, 'Tuesday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 01:34:03', '2025-12-19 01:34:03'),
(128, 35, 'Wednesday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 01:34:03', '2025-12-19 01:34:03'),
(129, 35, 'Thursday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 01:34:03', '2025-12-19 01:34:03'),
(130, 35, 'Friday', NULL, 'weekly', '09:00:00', '18:00:00', '12:00:00', '13:00:00', 1, '2025-12-19 01:34:03', '2025-12-19 01:34:03'),
(131, 35, 'Saturday', NULL, 'weekly', NULL, NULL, NULL, NULL, 0, '2025-12-19 01:34:03', '2025-12-19 01:34:03'),
(132, 35, 'Sunday', NULL, 'weekly', NULL, NULL, NULL, NULL, 0, '2025-12-19 01:34:03', '2025-12-19 01:34:03'),
(133, 34, 'Friday', '2025-12-19', 'date', '09:00:00', '18:00:00', '13:00:00', '14:00:00', 1, '2025-12-19 01:48:19', '2025-12-19 01:50:32'),
(141, 33, 'Monday', '2025-12-22', 'date', NULL, NULL, NULL, NULL, 0, '2025-12-21 14:40:34', '2025-12-21 14:40:34'),
(142, 34, 'Monday', '2025-12-22', 'date', NULL, NULL, NULL, NULL, 0, '2025-12-21 14:43:35', '2025-12-21 15:05:03'),
(143, 32, 'Monday', '2025-12-22', 'date', '10:00:00', '16:00:00', '12:00:00', '13:00:00', 1, '2025-12-21 15:10:07', '2025-12-21 15:10:07');

-- --------------------------------------------------------

--
-- Table structure for table `service`
--

CREATE TABLE `service` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service`
--

INSERT INTO `service` (`service_id`, `service_name`, `description`, `duration_minutes`, `category_id`, `price`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Basic Haircut', 'Professional hair cutting and styling service', 45, 1, 50.00, 'Active', '2025-11-26 11:42:53', '2025-11-26 11:42:53'),
(2, 'Hair Coloring', 'Full hair coloring service with premium products', 120, 1, 150.00, 'Active', '2025-11-26 11:42:53', '2025-12-04 15:31:08'),
(3, 'Hair Treatment', 'Deep conditioning treatment for damaged hair', 60, 1, 80.00, 'Active', '2025-11-26 11:42:53', '2025-12-04 15:33:05'),
(4, 'Hair Styling', 'Special occasion hair styling', 60, 1, 70.00, 'Inactive', '2025-11-26 11:42:53', '2025-12-16 18:10:54'),
(5, 'Highlights', 'Partial highlights for a natural look', 90, 1, 120.00, 'Active', '2025-11-26 11:42:53', '2025-12-04 15:31:18'),
(10, 'Body Massage', '', 46, 4, 22.00, 'Active', '2025-11-28 15:14:41', '2025-12-18 19:20:23'),
(11, 'Shampoo & Blow Dry', '', 50, 1, 45.00, 'Active', '2025-12-04 15:29:32', '2025-12-04 15:29:32'),
(13, 'Hair Perm', '', 180, 1, 400.00, 'Active', '2025-12-04 15:32:34', '2025-12-04 15:32:34'),
(14, 'Scalp Treatment', '', 180, 1, 250.00, 'Active', '2025-12-04 15:33:35', '2025-12-04 15:33:35'),
(15, 'Hair Extensions', '', 180, 1, 500.00, 'Active', '2025-12-04 15:34:12', '2025-12-04 15:34:12'),
(16, 'Manicure', '', 90, 2, 120.00, 'Active', '2025-12-04 15:42:40', '2025-12-04 15:42:40'),
(17, 'Foot Massage', '', 30, 4, 20.00, 'Active', '2025-12-04 16:09:28', '2025-12-16 18:14:44'),
(19, 'Hot Stone Massage', '', 30, 4, 40.00, 'Active', '2025-12-04 16:10:23', '2025-12-04 16:10:23'),
(20, 'Body Scrub', '', 25, 4, 15.00, 'Active', '2025-12-04 16:10:44', '2025-12-04 16:10:44'),
(21, 'Body Wrap', '', 45, 4, 45.00, 'Active', '2025-12-04 16:11:06', '2025-12-17 17:13:44'),
(22, 'Pedicure', '', 30, 2, 40.00, 'Active', '2025-12-04 16:11:43', '2025-12-04 16:11:43'),
(23, 'Gel Nail / Gel Polish', '', 50, 2, 50.00, 'Active', '2025-12-04 16:11:57', '2025-12-04 16:11:57'),
(24, 'Acrylic Nail', '', 60, 2, 99.00, 'Active', '2025-12-04 16:12:15', '2025-12-04 16:12:15'),
(25, 'Nail Art', '', 120, 2, 150.00, 'Active', '2025-12-04 16:12:41', '2025-12-04 16:12:41'),
(26, 'Nail Repair', '', 40, 2, 30.00, 'Active', '2025-12-04 16:12:59', '2025-12-04 16:12:59'),
(27, 'Nail Removal', '', 30, 2, 20.00, 'Active', '2025-12-04 16:13:18', '2025-12-04 16:13:18'),
(28, 'Basic Facial', '', 60, 3, 45.00, 'Active', '2025-12-04 16:33:25', '2025-12-04 16:33:25'),
(29, 'Deep Cleansing Facial', '', 120, 3, 70.00, 'Active', '2025-12-04 16:34:20', '2025-12-04 16:34:20'),
(30, 'Hydrating Facial', '', 45, 3, 55.00, 'Active', '2025-12-04 16:34:40', '2025-12-04 16:34:40'),
(31, 'Anti-aging Facial', '', 70, 3, 100.00, 'Active', '2025-12-04 16:35:11', '2025-12-04 16:35:11'),
(32, 'Acne Treatment', '', 60, 3, 55.00, 'Active', '2025-12-04 16:35:24', '2025-12-04 16:35:24'),
(33, 'Whitening Treatment', '', 70, 3, 299.00, 'Active', '2025-12-04 16:36:01', '2025-12-04 16:36:01'),
(34, 'Eye Treatment', '', 30, 3, 25.00, 'Active', '2025-12-04 16:36:21', '2025-12-04 16:36:21'),
(35, 'Full Makeup', '', 60, 5, 199.00, 'Active', '2025-12-04 16:37:04', '2025-12-04 16:37:04'),
(36, 'Bridal Makeup', '', 70, 5, 299.00, 'Active', '2025-12-04 16:37:40', '2025-12-04 16:37:40'),
(37, 'Eyebrow Shaping', '', 20, 5, 15.00, 'Active', '2025-12-04 16:38:00', '2025-12-04 16:38:00'),
(38, 'Eyelash Lift / Perm', '', 30, 5, 55.00, 'Active', '2025-12-04 16:38:48', '2025-12-04 16:38:48'),
(39, 'Eyebrow Tinting', '', 40, 5, 88.00, 'Active', '2025-12-04 16:39:08', '2025-12-04 16:39:08'),
(40, 'Eyebrow Waxing', '', 30, 6, 55.00, 'Inactive', '2025-12-04 16:40:08', '2025-12-16 18:10:22'),
(41, 'Upper Lip Waxing', '', 30, 6, 30.00, 'Active', '2025-12-04 16:40:41', '2025-12-04 16:40:41'),
(42, 'Underarm Waxing', '', 30, 6, 25.00, 'Active', '2025-12-04 16:41:14', '2025-12-04 16:41:14'),
(43, 'Arm / Leg Waxing', '', 60, 6, 50.00, 'Active', '2025-12-04 16:45:18', '2025-12-04 16:45:18'),
(44, 'Men’s Haircut', '', 30, 7, 30.00, 'Inactive', '2025-12-04 16:48:08', '2025-12-16 18:10:42'),
(46, 'Shaving', '', 30, 7, 55.00, 'Active', '2025-12-04 16:48:44', '2025-12-04 16:48:44'),
(56, 'Highlight', '', 30, 9, 30.00, 'Active', '2025-12-10 01:33:06', '2025-12-10 01:33:06'),
(66, 'message', '', 30, 9, 20.00, 'Active', '2025-12-19 01:40:13', '2025-12-21 14:21:47');

-- --------------------------------------------------------

--
-- Table structure for table `stylist`
--

CREATE TABLE `stylist` (
  `stylist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `qualifications` varchar(255) DEFAULT NULL,
  `experience_years` int(11) DEFAULT 0,
  `address` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `work_type` enum('full-time','part-time') DEFAULT 'full-time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stylist`
--

INSERT INTO `stylist` (`stylist_id`, `user_id`, `photo`, `specialization`, `qualifications`, `experience_years`, `address`, `work_type`, `created_at`, `updated_at`) VALUES
(32, 34, '/salonsystem/images/stylist_1766104997_8859.jpeg', 'Hair Cut & Styling', 'Certified Professional Stylist', 4, 'Block A -32-08 , PV 9 Residence, Jalan Kampung Wira Jaya，taman melati W.P. Kuala Lumpur, W.P. Kuala Lumpur, 53100', 'full-time', '2025-12-19 00:24:41', '2025-12-19 00:43:17'),
(33, 35, '/salonsystem/images/stylist_1766104984_7964.jpeg', 'Hair Cut & Styling', 'None', 4, 'pv9', 'full-time', '2025-12-19 00:26:21', '2025-12-19 00:43:04'),
(34, 38, '/salonsystem/images/stylist_1766105153_7872.jpeg', 'Body Treatment', 'None', 2, 'Cheras', 'full-time', '2025-12-19 00:45:53', '2025-12-19 00:45:53'),
(35, 41, '/salonsystem/images/stylist_1766108043_2985.jpeg', 'Skincare', 'None', 2, 'Cheras', 'full-time', '2025-12-19 01:34:03', '2025-12-19 01:34:03');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Customer','Staff','Admin') NOT NULL DEFAULT 'Customer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `name`, `email`, `phone`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Admin', 'aping060410@gmail.com', '011-22223333', '$2y$10$HrVxctoHxN2RJCWao.7yMOZmTbwwvNJ7WXqyzBTl7AdjJBjJrjWwG', 'Admin', '2025-12-19 00:06:31'),
(34, 'Rosie', 'solp-wm24@student.tarc.edu.my', '011-25400822', '$2y$10$5noK3Z2TX8ACD/8K2O4rguZq9QV9Gu2tF.2r4Zloy9Au7L7w4K12G', 'Staff', '2025-12-19 00:24:05'),
(35, 'Jennie', 'staff2@gmail.com', '012-34567891', '$2y$10$xQdf1aFU.ZWKQLGRsZqpreji98VtAX6ayflS5sXKXX90pqdGoDBMy', 'Staff', '2025-12-19 00:25:29'),
(36, 'Xuan', 'jxlee-wm24@student.tarc.edu.my', '011-62802163', '$2y$10$Gq34w6fNYX7088qDpOa0hOP.poILJU0l7UBLX/T/5eKgA4NYy/Ia6', 'Customer', '2025-12-19 00:28:45'),
(37, 'EN', 'en15@gmail.com', '011-85859696', '$2y$10$66hLvYFIMqXUj5XblI/MBOkwVX.re6FwZBRPzGutGBdh4vmYrM9lS', 'Customer', '2025-12-19 00:29:45'),
(38, 'Lily', 'staff3@gmail.com', '016-25361425', '$2y$10$n0IKgX0SxgP/gHjUZEt75urW52Ky02dLYYWDBE/6DXewPRAF0s4Cy', 'Staff', '2025-12-19 00:43:58'),
(39, 'xuan', 'xuan@gmail.com', '012-34567891', '$2y$10$CnrJ1EiiBVi.fay8Jp75d.r2qWguoDSlX5vXJeQDNICV73NNyLjgm', 'Customer', '2025-12-19 01:27:05'),
(40, 'Admin2', 'jiaxuan061113@gmail.com', '011-85859696', '$2y$10$.LkwtAL4Ph7MukXswDrm2u9n16IIt8zX4a2pKug5Z5odVP7d51lcS', 'Admin', '2025-12-19 01:31:52'),
(41, 'YY', 'staff4@gmail.com', '011-84235632', '$2y$10$VWCrnJ3wLpWlyEZBPiCQp.bRI1U/4dBtJBRlmKrBnbRntO9JbS57q', 'Staff', '2025-12-19 01:33:37'),
(43, 'Jia', 'what@gmail.com', '011-12345679', '$2y$10$0UiGv1l1HZUl4vOt3aQ1Huk06N3nGo/fIPZMtiII/hWlUMg22udzy', 'Staff', '2025-12-21 13:54:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointment`
--
ALTER TABLE `appointment`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `idx_appointment_date` (`appointment_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_stylist_id` (`stylist_id`);

--
-- Indexes for table `appointmentitem`
--
ALTER TABLE `appointmentitem`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_appointment_id` (`appointment_id`);

--
-- Indexes for table `auditlog`
--
ALTER TABLE `auditlog`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `businesshours`
--
ALTER TABLE `businesshours`
  ADD PRIMARY KEY (`business_hour_id`),
  ADD KEY `idx_day_of_week` (`day_of_week`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `holiday`
--
ALTER TABLE `holiday`
  ADD PRIMARY KEY (`holiday_id`),
  ADD UNIQUE KEY `uniq_holiday_date_recurring` (`holiday_date`,`is_recurring`),
  ADD KEY `idx_holiday_date` (`holiday_date`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `uniq_schedule_scope` (`stylist_id`,`schedule_scope`,`day_of_week`,`override_date`),
  ADD KEY `idx_day_of_week` (`day_of_week`);

--
-- Indexes for table `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`service_id`),
  ADD UNIQUE KEY `service_name` (`service_name`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `stylist`
--
ALTER TABLE `stylist`
  ADD PRIMARY KEY (`stylist_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointment`
--
ALTER TABLE `appointment`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `appointmentitem`
--
ALTER TABLE `appointmentitem`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `auditlog`
--
ALTER TABLE `auditlog`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `businesshours`
--
ALTER TABLE `businesshours`
  MODIFY `business_hour_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `holiday`
--
ALTER TABLE `holiday`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT for table `service`
--
ALTER TABLE `service`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `stylist`
--
ALTER TABLE `stylist`
  MODIFY `stylist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointment`
--
ALTER TABLE `appointment`
  ADD CONSTRAINT `appointment_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `appointment_ibfk_2` FOREIGN KEY (`stylist_id`) REFERENCES `stylist` (`stylist_id`) ON UPDATE CASCADE;

--
-- Constraints for table `appointmentitem`
--
ALTER TABLE `appointmentitem`
  ADD CONSTRAINT `appointmentitem_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointment` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `appointmentitem_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `service` (`service_id`) ON UPDATE CASCADE;

--
-- Constraints for table `auditlog`
--
ALTER TABLE `auditlog`
  ADD CONSTRAINT `auditlog_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`stylist_id`) REFERENCES `stylist` (`stylist_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `service`
--
ALTER TABLE `service`
  ADD CONSTRAINT `service_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `stylist`
--
ALTER TABLE `stylist`
  ADD CONSTRAINT `stylist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
