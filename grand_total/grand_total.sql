-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 09, 2026 at 04:57 PM
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
-- Database: `poultry`
--

-- --------------------------------------------------------

--
-- Table structure for table `grand_total`
--

CREATE TABLE `grand_total` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `sold` decimal(12,2) DEFAULT 0.00,
  `dead` int(11) DEFAULT 0,
  `excess_deficit` varchar(50) DEFAULT NULL,
  `finished_weight` decimal(12,2) DEFAULT 0.00,
  `feed_weight` decimal(12,2) DEFAULT 0.00,
  `company` varchar(100) DEFAULT NULL,
  `mixed` varchar(100) DEFAULT NULL,
  `feed_bag` int(11) DEFAULT 0,
  `used_feed_bags` int(11) NOT NULL,
  `feed_balance` int(11) NOT NULL,
  `medicine` decimal(12,2) DEFAULT 0.00,
  `feed` decimal(12,2) DEFAULT 0.00,
  `other_cost` decimal(10,0) NOT NULL,
  `avg_weight` decimal(10,0) NOT NULL,
  `mortality_rate` decimal(10,0) NOT NULL,
  `fcr` decimal(10,0) NOT NULL,
  `tfcr` decimal(12,2) DEFAULT 0.00,
  `comments` text DEFAULT NULL,
  `has_comment` tinyint(1) NOT NULL DEFAULT 0,
  `comment_read` tinyint(1) NOT NULL DEFAULT 0,
  `comment_author_id` int(11) DEFAULT NULL,
  `comment_created_at` timestamp NULL DEFAULT NULL,
  `page_number` int(11) NOT NULL DEFAULT 1,
  `farm_id` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `grand_total`
--

INSERT INTO `grand_total` (`id`, `name`, `type`, `quantity`, `sold`, `dead`, `excess_deficit`, `finished_weight`, `feed_weight`, `company`, `mixed`, `feed_bag`, `used_feed_bags`, `feed_balance`, `medicine`, `feed`, `other_cost`, `avg_weight`, `mortality_rate`, `fcr`, `tfcr`, `comments`, `has_comment`, `comment_read`, `comment_author_id`, `comment_created_at`, `page_number`, `farm_id`, `created_at`, `updated_at`) VALUES
(1, 'U Kaung', 'cp', 10000, 100.00, 50, '6', 1000.00, 9999.00, 'Sunjin', '6', 60, 0, 0, 67.00, 888.00, 0, 0, 0, 0, 87777.00, NULL, 0, 0, NULL, NULL, 51, 1, '2025-11-24 13:36:54', '2025-11-24 13:36:54'),
(7, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 51, 1, '2025-11-25 16:06:19', '2025-11-25 16:06:19'),
(8, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 51, 1, '2025-11-25 16:06:19', '2025-11-25 16:06:19'),
(9, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 9, 1, '2025-11-25 16:18:23', '2025-11-25 16:18:23'),
(10, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 9, 1, '2025-11-25 16:18:23', '2025-11-25 16:18:23'),
(11, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 9, 1, '2025-11-25 16:18:29', '2025-11-25 16:18:29'),
(12, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 9, 1, '2025-11-25 16:18:29', '2025-11-25 16:18:29'),
(13, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 51, 1, '2025-11-25 16:53:21', '2025-11-25 16:53:21'),
(14, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 51, 1, '2025-11-25 16:53:21', '2025-11-25 16:53:21'),
(41, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 1, 1, '2025-12-07 13:55:22', '2025-12-07 13:55:22'),
(42, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 1, 1, '2025-12-07 13:55:22', '2025-12-07 13:55:22'),
(43, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 1, 1, '2025-12-07 13:55:22', '2025-12-07 13:55:22'),
(44, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 1, 1, '2025-12-07 13:55:22', '2025-12-07 13:55:22'),
(45, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 1, 1, '2025-12-07 13:55:22', '2025-12-07 13:55:22'),
(46, '', '', 0, 0.00, 0, '', 0.00, 0.00, '', '', 0, 0, 0, 0.00, 0.00, 0, 0, 0, 0, 0.00, NULL, 0, 0, NULL, NULL, 1, 1, '2025-12-07 13:55:22', '2025-12-07 13:55:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `grand_total`
--
ALTER TABLE `grand_total`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `grand_total`
--
ALTER TABLE `grand_total`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
