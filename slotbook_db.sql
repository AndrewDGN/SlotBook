-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 03:55 PM
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
-- Database: `slotbook_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `building` varchar(200) NOT NULL,
  `capacity` int(11) DEFAULT 0,
  `status` enum('available','maintenance') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `name`, `building`, `capacity`, `status`, `created_at`) VALUES
(1, 'Room 202', 'Medina Lacson', 40, 'available', '2025-11-08 21:49:56'),
(2, 'Room 201', 'Medina Lacson', 40, 'available', '2025-11-11 06:05:32'),
(3, 'Smart Campus Lab', 'Medina Lacson', 30, 'available', '2025-11-11 06:05:56'),
(4, 'Room 403', 'BaComm Building', 50, 'available', '2025-11-11 12:17:49'),
(7, 'Room 404', 'BaComm Building', 50, 'available', '2025-11-11 12:45:30'),
(10, 'Multipurpose Hall', 'OSA Building', 100, 'available', '2025-11-13 13:24:58');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `details` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `details`, `type`, `related_id`, `is_read`, `created_at`) VALUES
(1, 5, 'New facility available: Room 404 in BaComm Building', 'Facility: Room 404\nBuilding: BaComm Building\nCapacity: 50', 'new_facility', 7, 1, '2025-11-11 12:45:30'),
(2, 8, 'New facility available: Room 404 in BaComm Building', 'Facility: Room 404\nBuilding: BaComm Building\nCapacity: 50', 'new_facility', 7, 1, '2025-11-11 12:45:30'),
(3, 9, 'New facility available: Room 404 in BaComm Building', 'Facility: Room 404\nBuilding: BaComm Building\nCapacity: 50', 'new_facility', 7, 1, '2025-11-11 12:45:30'),
(4, 5, 'Your reservation for Room 202 has been approved', 'Facility: Room 202\nDate: 2025-11-12\nTime: 10:00:00 - 18:00:00\nStatus: Approved', 'reservation_updated', 10, 1, '2025-11-11 12:53:16'),
(5, 5, 'Your reservation for Room 202 has been cancelled by admin', 'Facility: Room 202\nDate: 2025-11-12\nTime: 10:00:00 - 18:00:00\nCancelled by: Administrator', 'reservation_cancelled', 10, 1, '2025-11-11 13:11:29'),
(6, 5, 'Your reservation for Room 403 has been denied', 'Facility: Room 403\nDate: 2025-11-12\nTime: 07:00:00 - 10:00:00\nStatus: Denied', 'reservation_updated', 11, 1, '2025-11-11 13:13:04'),
(7, 8, 'Your reservation for Room 202 has been approved', 'Facility: Room 202\nDate: 2025-11-14\nTime: 07:00:00 - 10:00:00\nStatus: Approved', 'reservation_updated', 12, 1, '2025-11-11 13:17:02'),
(8, 8, 'Your reservation for Room 202 has been cancelled by admin', 'Facility: Room 202\nDate: 2025-11-14\nTime: 07:00:00 - 10:00:00\nCancelled by: Administrator', 'reservation_cancelled', 12, 1, '2025-11-11 14:08:13'),
(9, 5, 'New facility available: Room 404 in BaComm Building', 'Facility: Room 404\nBuilding: BaComm Building\nCapacity: 50', 'new_facility', 8, 1, '2025-11-11 14:09:53'),
(10, 8, 'New facility available: Room 404 in BaComm Building', 'Facility: Room 404\nBuilding: BaComm Building\nCapacity: 50', 'new_facility', 8, 1, '2025-11-11 14:09:53'),
(11, 9, 'New facility available: Room 404 in BaComm Building', 'Facility: Room 404\nBuilding: BaComm Building\nCapacity: 50', 'new_facility', 8, 1, '2025-11-11 14:09:53'),
(12, 5, 'Your reservation for Room 403 has been approved', 'Facility: Room 403\nDate: 2025-11-11\nTime: 16:00:00 - 18:00:00\nStatus: Approved', 'reservation_updated', 13, 1, '2025-11-11 14:45:24'),
(13, 8, 'Your reservation for Room 404 has been approved', 'Facility: Room 404\nDate: 2025-11-11\nTime: 07:00:00 - 18:00:00\nStatus: Approved', 'reservation_updated', 14, 1, '2025-11-11 15:08:33'),
(14, 9, 'Facility Maintenance: Smart Campus Lab', 'The facility \'Smart Campus Lab\' has been put under maintenance. Please check your upcoming reservations.', 'maintenance', 7, 1, '2025-11-11 15:58:05'),
(15, 9, 'Facility Maintenance: Smart Campus Lab', 'The facility \'Smart Campus Lab\' has been put under maintenance. Please check your upcoming reservations.', 'maintenance', 8, 1, '2025-11-11 15:58:05'),
(16, 9, 'Your reservation for Room 403 has been approved', 'Facility: Room 403\nDate: 2025-11-12\nTime: 10:00:00 - 12:00:00\nStatus: Approved', 'reservation_updated', 16, 1, '2025-11-11 16:29:02'),
(17, 8, 'Your reservation for Room 403 has been approved', 'Facility: Room 403\nDate: 2025-11-12\nTime: 07:00:00 - 09:00:00\nStatus: Approved', 'reservation_updated', 15, 1, '2025-11-11 16:29:04'),
(18, 9, 'Your reservation for Room 403 has been cancelled by admin', 'Facility: Room 403\nDate: 2025-11-12\nTime: 10:00:00 - 12:00:00\nCancelled by: Administrator', 'reservation_cancelled', 16, 1, '2025-11-11 18:08:41'),
(19, 9, 'Your reservation for Room 202 has been cancelled by admin', 'Facility: Room 202\nDate: 2025-11-12\nTime: 07:00:00 - 10:00:00\nCancelled by: Administrator', 'reservation_cancelled', 9, 1, '2025-11-11 18:09:01'),
(20, 8, 'Your reservation for Room 403 has been cancelled by admin', 'Facility: Room 403\nDate: 2025-11-12\nTime: 07:00:00 - 09:00:00\nCancelled by: Administrator', 'reservation_cancelled', 15, 1, '2025-11-11 18:09:17'),
(21, 5, 'New facility available: Room 405 in BaComm Building', 'Facility: Room 405\nBuilding: BaComm Building\nCapacity: 50', 'new_facility', 9, 1, '2025-11-11 18:10:40'),
(22, 8, 'New facility available: Room 405 in BaComm Building', 'Facility: Room 405\nBuilding: BaComm Building\nCapacity: 50', 'new_facility', 9, 1, '2025-11-11 18:10:40'),
(23, 9, 'New facility available: Room 405 in BaComm Building', 'Facility: Room 405\nBuilding: BaComm Building\nCapacity: 50', 'new_facility', 9, 1, '2025-11-11 18:10:40'),
(24, 11, 'Your reservation for Room 202 has been approved', 'Facility: Room 202\nDate: 2025-11-14\nTime: 07:00:00 - 11:00:00\nStatus: Approved', 'reservation_updated', 17, 1, '2025-11-13 11:30:24'),
(25, 11, 'Your reservation for Smart Campus Lab has been approved', 'Facility: Smart Campus Lab\nDate: 2025-11-14\nTime: 11:00:00 - 13:00:00\nStatus: Approved', 'reservation_updated', 18, 1, '2025-11-13 11:35:35'),
(26, 1, 'Reservation cancelled for Smart Campus Lab by Lester De Lemos', 'Facility: Smart Campus Lab\nDate: 2025-11-14\nTime: 11:00:00 - 13:00:00\nCancelled by: Lester De Lemos', 'reservation_cancelled', 18, 0, '2025-11-13 11:36:13'),
(27, 7, 'Reservation cancelled for Smart Campus Lab by Lester De Lemos', 'Facility: Smart Campus Lab\nDate: 2025-11-14\nTime: 11:00:00 - 13:00:00\nCancelled by: Lester De Lemos', 'reservation_cancelled', 18, 0, '2025-11-13 11:36:13'),
(28, 10, 'Reservation cancelled for Smart Campus Lab by Lester De Lemos', 'Facility: Smart Campus Lab\nDate: 2025-11-14\nTime: 11:00:00 - 13:00:00\nCancelled by: Lester De Lemos', 'reservation_cancelled', 18, 0, '2025-11-13 11:36:13'),
(29, 1, 'Reservation cancelled for Room 202 by Lester De Lemos', 'Facility: Room 202\nDate: 2025-11-14\nTime: 07:00:00 - 11:00:00\nCancelled by: Lester De Lemos', 'reservation_cancelled', 17, 0, '2025-11-13 11:36:37'),
(30, 7, 'Reservation cancelled for Room 202 by Lester De Lemos', 'Facility: Room 202\nDate: 2025-11-14\nTime: 07:00:00 - 11:00:00\nCancelled by: Lester De Lemos', 'reservation_cancelled', 17, 0, '2025-11-13 11:36:37'),
(31, 10, 'Reservation cancelled for Room 202 by Lester De Lemos', 'Facility: Room 202\nDate: 2025-11-14\nTime: 07:00:00 - 11:00:00\nCancelled by: Lester De Lemos', 'reservation_cancelled', 17, 0, '2025-11-13 11:36:37'),
(32, 12, 'Your reservation for Smart Campus Lab has been approved', 'Facility: Smart Campus Lab\nDate: 2025-11-17\nTime: 07:00:00 - 09:00:00\nStatus: Approved', 'reservation_updated', 19, 1, '2025-11-13 13:17:28'),
(33, 1, 'Reservation cancelled for Smart Campus Lab by Eldrick Dela Cruz', 'Facility: Smart Campus Lab\nDate: 2025-11-17\nTime: 07:00:00 - 09:00:00\nCancelled by: Eldrick Dela Cruz', 'reservation_cancelled', 19, 0, '2025-11-13 13:18:48'),
(34, 7, 'Reservation cancelled for Smart Campus Lab by Eldrick Dela Cruz', 'Facility: Smart Campus Lab\nDate: 2025-11-17\nTime: 07:00:00 - 09:00:00\nCancelled by: Eldrick Dela Cruz', 'reservation_cancelled', 19, 0, '2025-11-13 13:18:48'),
(35, 10, 'Reservation cancelled for Smart Campus Lab by Eldrick Dela Cruz', 'Facility: Smart Campus Lab\nDate: 2025-11-17\nTime: 07:00:00 - 09:00:00\nCancelled by: Eldrick Dela Cruz', 'reservation_cancelled', 19, 0, '2025-11-13 13:18:48'),
(36, 12, 'Your reservation for Room 202 has been approved', 'Facility: Room 202\nDate: 2025-11-13\nTime: 07:00:00 - 17:00:00\nStatus: Approved', 'reservation_updated', 20, 1, '2025-11-13 13:19:53'),
(37, 12, 'Your reservation for Room 403 has been denied', 'Facility: Room 403\nDate: 2025-11-14\nTime: 09:00:00 - 11:00:00\nStatus: Denied', 'reservation_updated', 21, 1, '2025-11-13 13:22:10'),
(38, 5, 'New facility available: Multipurpose Hall in OSA Building', 'Facility: Multipurpose Hall\nBuilding: OSA Building\nCapacity: 100', 'new_facility', 10, 1, '2025-11-13 13:24:58'),
(39, 8, 'New facility available: Multipurpose Hall in OSA Building', 'Facility: Multipurpose Hall\nBuilding: OSA Building\nCapacity: 100', 'new_facility', 10, 1, '2025-11-13 13:24:58'),
(40, 9, 'New facility available: Multipurpose Hall in OSA Building', 'Facility: Multipurpose Hall\nBuilding: OSA Building\nCapacity: 100', 'new_facility', 10, 0, '2025-11-13 13:24:58'),
(41, 11, 'New facility available: Multipurpose Hall in OSA Building', 'Facility: Multipurpose Hall\nBuilding: OSA Building\nCapacity: 100', 'new_facility', 10, 0, '2025-11-13 13:24:58'),
(42, 12, 'New facility available: Multipurpose Hall in OSA Building', 'Facility: Multipurpose Hall\nBuilding: OSA Building\nCapacity: 100', 'new_facility', 10, 1, '2025-11-13 13:24:58'),
(43, 12, 'Your reservation for Multipurpose Hall has been approved', 'Facility: Multipurpose Hall\nDate: 2025-11-14\nTime: 07:00:00 - 12:00:00\nStatus: Approved', 'reservation_updated', 22, 1, '2025-11-13 13:26:04'),
(44, 12, 'Facility Maintenance: Multipurpose Hall', 'The facility \'Multipurpose Hall\' has been put under maintenance. Please check your upcoming reservations.', 'maintenance', 22, 1, '2025-11-13 13:34:10');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('pending','approved','denied','cancelled') DEFAULT 'pending',
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `facility_id`, `date`, `start_time`, `end_time`, `status`, `cancelled_by`, `cancelled_at`, `created_at`) VALUES
(5, 5, 1, '2025-11-11', '07:00:00', '09:30:00', 'approved', NULL, NULL, '2025-11-11 06:00:08'),
(6, 5, 3, '2025-11-11', '07:00:00', '09:00:00', 'denied', NULL, NULL, '2025-11-11 06:06:44'),
(7, 9, 3, '2025-11-11', '16:00:00', '17:00:00', 'approved', NULL, NULL, '2025-11-11 07:31:49'),
(8, 9, 3, '2025-11-11', '17:00:00', '18:00:00', 'approved', NULL, NULL, '2025-11-11 07:42:39'),
(9, 9, 1, '2025-11-12', '07:00:00', '10:00:00', 'cancelled', 10, '2025-11-11 18:09:01', '2025-11-11 11:26:42'),
(10, 5, 1, '2025-11-12', '10:00:00', '18:00:00', 'cancelled', 7, '2025-11-11 13:11:29', '2025-11-11 12:52:20'),
(11, 5, 4, '2025-11-12', '07:00:00', '10:00:00', 'denied', NULL, NULL, '2025-11-11 13:12:43'),
(12, 8, 1, '2025-11-14', '07:00:00', '10:00:00', 'cancelled', 10, '2025-11-11 14:08:13', '2025-11-11 13:16:52'),
(13, 5, 4, '2025-11-11', '16:00:00', '18:00:00', 'approved', NULL, NULL, '2025-11-11 14:44:59'),
(14, 8, 7, '2025-11-11', '07:00:00', '18:00:00', 'approved', NULL, NULL, '2025-11-11 15:08:15'),
(15, 8, 4, '2025-11-12', '07:00:00', '09:00:00', 'cancelled', 10, '2025-11-11 18:09:17', '2025-11-11 16:28:09'),
(16, 9, 4, '2025-11-12', '10:00:00', '12:00:00', 'cancelled', 10, '2025-11-11 18:08:41', '2025-11-11 16:28:45'),
(17, 11, 1, '2025-11-14', '07:00:00', '11:00:00', 'cancelled', 11, '2025-11-13 11:36:37', '2025-11-13 11:29:53'),
(18, 11, 3, '2025-11-14', '11:00:00', '13:00:00', 'cancelled', 11, '2025-11-13 11:36:13', '2025-11-13 11:35:26'),
(19, 12, 3, '2025-11-17', '07:00:00', '09:00:00', 'cancelled', 12, '2025-11-13 13:18:48', '2025-11-13 13:16:39'),
(20, 12, 1, '2025-11-13', '07:00:00', '17:00:00', 'approved', NULL, NULL, '2025-11-13 13:19:40'),
(21, 12, 4, '2025-11-14', '09:00:00', '11:00:00', 'denied', NULL, NULL, '2025-11-13 13:21:57'),
(22, 12, 10, '2025-11-14', '07:00:00', '12:00:00', 'approved', NULL, NULL, '2025-11-13 13:25:41');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('faculty','admin','student') NOT NULL DEFAULT 'faculty',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_notifications` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `created_at`, `email_notifications`) VALUES
(1, 'System Admin', 'admin@bpsu.edu.ph', '$2y$10$K1YbLz6cI4d9O9qv2mWdeun6m5Ef4n2v9g9qk9oH1htvWk4mF2Y6W', 'admin', '2025-10-30 08:47:17', 1),
(5, 'user test', 'usertest@bpsu.edu.ph', '$2y$10$x3PgsuP8deJj/3irMowYrOF16m7QSmuV0UFmeRdRxtrceduZjHhGK', 'faculty', '2025-11-04 19:48:12', 1),
(7, 'admin test', 'admintest@bpsu.edu.ph', '$2y$10$k2qct4dz3tgaBRlL6soHleyam1Zs1qBE7fy7tke3zUGhAFx0LkZvS', 'admin', '2025-11-08 09:37:26', 1),
(8, 'Vincent Kong Rodriguez', 'vincent@bpsu.edu.ph', '$2y$10$FoQvcVKFjuLbAJxdMpMv1.w3OukI1Is9rJTaMHgVRimuDZt5Apnra', 'faculty', '2025-11-08 22:03:29', 1),
(9, 'Andrew Dugenia', 'amdugenia23@bpsu.edu.ph', '$2y$10$u69dWLxlUgsT82GgaBN3e.wTLpml.pzhKoCEDJI.V348hEaGVDiHi', 'faculty', '2025-11-11 07:26:17', 1),
(10, 'Lawrence Admin', 'lawrence@bpsu.edu.ph', '$2y$10$b0C6LorGpCOUW8xSlu7EYuAgHqd2q8JbCWPXhpwms334dUpvWEniy', 'admin', '2025-11-11 13:51:24', 1),
(11, 'Lester De Lemos', 'lesterdelemos@bpsu.edu.ph', '$2y$10$dptFR1qLYX5KdeEKianvme25Pm7.GmJyNi60/tEchcqBmBjJJ1OGO', 'faculty', '2025-11-13 11:29:14', 1),
(12, 'Eldrick Dela Cruz', 'eldrick@bpsu.edu.ph', '$2y$10$sVyMHEvvv8SliNgShRuj5eOt.fBKB6MJ.m1UBF6rhCxj0A2/LozUu', 'faculty', '2025-11-13 13:15:22', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `facility_id` (`facility_id`);

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
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
