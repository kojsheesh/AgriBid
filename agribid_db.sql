-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 22, 2026 at 10:20 AM
-- Server version: 8.0.45
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `agribid_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `buyer_rooms`
--

CREATE TABLE `buyer_rooms` (
  `room_id` int UNSIGNED NOT NULL,
  `buyer_id` int NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `barangay` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `delivery_location` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `deadline` datetime NOT NULL,
  `status` enum('Open','Awarded','Closed','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buyer_rooms`
--

INSERT INTO `buyer_rooms` (`room_id`, `buyer_id`, `title`, `description`, `city`, `barangay`, `delivery_location`, `budget`, `deadline`, `status`, `created_at`) VALUES
(3, 17, 'Kailangan ko nitoo', 'Basta fresh', 'Binangonan', '', 'Binangonan', 1000.00, '2026-10-22 18:01:00', 'Cancelled', '2026-10-21 08:33:06'),
(5, 17, 'Need ko neto', 'maliit liit lang dapat', 'Binangonan', '', 'Binangonan sa tapat ng 7/11', NULL, '2026-10-22 18:38:00', 'Awarded', '2026-10-21 09:09:09'),
(12, 17, 'PATATAAAS', 'fresh', 'Binangonan', '', 'blk 34 lot 1 ph67, Rizal, Binangonan, Pantok, 1940', NULL, '2026-10-25 14:27:00', 'Awarded', '2026-10-22 05:59:05'),
(13, 17, 'Onionnn', 'onion', 'Binangonan', '', 'blk 34 lot 1 ph67, Rizal, Binangonan, Pantok, 1940', NULL, '2026-10-23 14:41:00', 'Awarded', '2026-10-22 06:12:17'),
(14, 17, 'KANIN', 'rice', 'Binangonan', '', 'blk 34 lot 1 ph67, Rizal, Binangonan, Pantok, 1940', NULL, '2026-10-23 16:00:00', 'Open', '2026-10-22 06:37:10'),
(15, 17, 'Eggplant:>', 'eggplant', 'Binangonan', '', 'blk 34 lot 1 ph67, Rizal, Binangonan, Pantok, 1940', NULL, '2026-10-23 15:28:00', 'Open', '2026-10-22 06:59:17');

-- --------------------------------------------------------

--
-- Table structure for table `buyer_room_items`
--

CREATE TABLE `buyer_room_items` (
  `item_id` int UNSIGNED NOT NULL,
  `room_id` int UNSIGNED NOT NULL,
  `crop_id` int UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `unit` enum('kg') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `buyer_room_items`
--

INSERT INTO `buyer_room_items` (`item_id`, `room_id`, `crop_id`, `quantity`, `unit_price`, `unit`) VALUES
(4, 3, 2, 2.00, 0.00, 'kg'),
(5, 3, 1, 1.00, 0.00, 'kg'),
(7, 5, 2, 2.00, 100.00, 'kg'),
(8, 5, 1, 1.00, 20.00, 'kg'),
(9, 12, 7, 2.00, 50.00, 'kg'),
(10, 13, 8, 2.00, 50.00, 'kg'),
(11, 14, 1, 2.00, 45.00, 'kg'),
(12, 15, 5, 2.00, 50.00, 'kg'),
(13, 15, 9, 1.00, 10.00, 'kg');

-- --------------------------------------------------------

--
-- Table structure for table `crops`
--

CREATE TABLE `crops` (
  `crop_id` int UNSIGNED NOT NULL,
  `crop_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `planting_season` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `days_to_harvest` smallint UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `crops`
--

INSERT INTO `crops` (`crop_id`, `crop_name`, `category`, `description`, `image_path`, `planting_season`, `days_to_harvest`, `is_active`) VALUES
(1, 'RICE', 'Grain', 'Rice crop.', '/agribid/Photos/rice.jpg', NULL, NULL, 1),
(2, 'CORN', 'Grain', 'Corn crop.', '/agribid/Photos/corn.webp', NULL, NULL, 1),
(3, 'LETTUCE', 'Vegetable', 'Lettuce crop.', '/agribid/Photos/lettuce.jpg', NULL, NULL, 1),
(4, 'CABBAGE', 'Vegetable', 'Cabbage crop.', '/agribid/Photos/cabbage.jpg', NULL, NULL, 1),
(5, 'EGGPLANT', 'Vegetable', 'Eggplant crop.', '/agribid/Photos/eggplant.jpg', NULL, NULL, 1),
(6, 'OKRA', 'Vegetable', 'Okra crop.', '/agribid/Photos/okra.webp', NULL, NULL, 1),
(7, 'POTATO', 'Vegetable', 'Potato crop.', '/agribid/Photos/potato.jpg', NULL, NULL, 1),
(8, 'ONION', 'Vegetable', 'Onion crop.', '/agribid/Photos/onion.jpg', NULL, NULL, 1),
(9, 'GARLIC', 'Vegetable', 'Garlic crop.', '/agribid/Photos/garlic.webp', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `farmer_harvests`
--

CREATE TABLE `farmer_harvests` (
  `harvest_id` int UNSIGNED NOT NULL,
  `farmer_id` int NOT NULL,
  `crop_id` int UNSIGNED NOT NULL,
  `expected_date` date NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` enum('kg') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `notes` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Planned','Harvested','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Planned',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `farmer_harvests`
--

INSERT INTO `farmer_harvests` (`harvest_id`, `farmer_id`, `crop_id`, `expected_date`, `quantity`, `unit`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(3, 20, 4, '2026-12-02', 10.00, 'kg', '', 'Cancelled', '2026-10-22 04:53:23', '2026-10-22 05:27:50'),
(4, 20, 4, '2026-10-22', 5.00, 'kg', '', 'Planned', '2026-10-22 05:33:58', '2026-10-22 05:33:58'),
(5, 20, 4, '2026-10-22', 1.00, 'kg', '', 'Harvested', '2026-10-22 05:34:12', '2026-10-22 05:34:37'),
(6, 21, 4, '2026-10-22', 11.98, 'kg', '', 'Harvested', '2026-10-22 05:38:46', '2026-10-22 06:22:38'),
(7, 20, 2, '2026-10-22', 10.00, 'kg', '', 'Planned', '2026-10-22 05:42:08', '2026-10-22 05:42:08'),
(8, 21, 2, '2026-10-22', 5.00, 'kg', '', 'Harvested', '2026-10-22 05:42:45', '2026-10-22 05:43:01');

-- --------------------------------------------------------

--
-- Table structure for table `farmer_offers`
--

CREATE TABLE `farmer_offers` (
  `offer_id` int UNSIGNED NOT NULL,
  `room_id` int UNSIGNED NOT NULL,
  `farmer_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `image_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Pending','Accepted','Declined','Withdrawn') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `farmer_offers`
--

INSERT INTO `farmer_offers` (`offer_id`, `room_id`, `farmer_id`, `total_amount`, `message`, `image_path`, `status`, `created_at`) VALUES
(4, 5, 20, 220.00, NULL, '/agribid/uploads/offers/117bc57a651e86bd4b5f784000bdda80.jpg', 'Accepted', '2026-10-21 09:09:58'),
(11, 12, 21, 75.00, NULL, NULL, 'Accepted', '2026-10-22 06:00:05'),
(12, 13, 21, 75.00, NULL, NULL, 'Accepted', '2026-10-22 06:12:54'),
(13, 15, 20, 117.50, NULL, NULL, 'Pending', '2026-10-22 07:04:33');

-- --------------------------------------------------------

--
-- Table structure for table `farmer_offer_items`
--

CREATE TABLE `farmer_offer_items` (
  `offer_item_id` int UNSIGNED NOT NULL,
  `offer_id` int UNSIGNED NOT NULL,
  `crop_id` int UNSIGNED NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `farmer_offer_items`
--

INSERT INTO `farmer_offer_items` (`offer_item_id`, `offer_id`, `crop_id`, `quantity`, `unit_price`) VALUES
(4, 4, 1, 1.00, 20.00),
(5, 4, 2, 2.00, 100.00),
(6, 11, 7, 1.50, 50.00),
(7, 12, 8, 1.50, 50.00),
(14, 13, 5, 2.50, 45.00),
(15, 13, 9, 1.00, 5.00);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `icon_type` varchar(10) COLLATE utf8mb4_general_ci DEFAULT '?',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notif_id`, `user_id`, `title`, `message`, `icon_type`, `is_read`, `created_at`) VALUES
(6, 17, 'Verification review complete', 'Your identity document was approved. Your account is now verified.', '?', 1, '2026-10-21 08:19:50'),
(8, 20, 'Verification review complete', 'Your identity document was approved. Your account is now verified.', '?', 1, '2026-10-21 08:46:42'),
(9, 17, 'New farmer offer', 'Paul Rabulan submitted an offer for Need ko neto.', '?', 1, '2026-10-21 09:09:58'),
(10, 20, 'Your offer was accepted', 'The buyer accepted your offer for Need ko neto. Arrange the meet-up in your orders.', '?', 1, '2026-10-21 09:10:33'),
(11, 20, 'Order updated', 'The buyer updated the meet-up details for Need ko neto.', '?', 1, '2026-10-21 09:11:02'),
(12, 17, 'New room message', 'Paul Rabulan sent a message about Need ko neto.', '?', 1, '2026-10-21 09:11:36'),
(13, 17, 'New room message', 'Paul Rabulan sent a message about Need ko neto.', '?', 1, '2026-10-21 09:11:55'),
(14, 20, 'New room message', 'There is a new message in Need ko neto.', '?', 1, '2026-10-21 09:12:42'),
(17, 20, 'Order updated', 'The buyer marked the order for Need ko neto complete.', '?', 1, '2026-10-21 09:20:09'),
(18, 17, 'New room message', 'Paul Rabulan sent a message about Need ko neto.', '?', 1, '2026-10-21 09:49:49'),
(19, 20, 'New room message', 'There is a new message in Need ko neto.', '?', 1, '2026-10-21 09:50:10'),
(20, 21, 'Verification review complete', 'Your identity document was approved. Your account is now verified.', '?', 1, '2026-10-22 05:37:36'),
(21, 17, 'New farmer offer', 'Koji Ogino submitted an offer for PATATAAAS.', '?', 1, '2026-10-22 06:00:05'),
(22, 21, 'Your offer was accepted', 'The buyer accepted your offer for PATATAAAS. Arrange the meet-up in your orders.', '?', 1, '2026-10-22 06:01:49'),
(23, 21, 'Order updated', 'The buyer updated the meet-up details for PATATAAAS.', '?', 1, '2026-10-22 06:02:49'),
(24, 21, 'New Post-Bid Chatroom message', 'There is a new message in PATATAAAS.', '?', 1, '2026-10-22 06:03:17'),
(25, 17, 'New room message', 'Koji Ogino sent a message about PATATAAAS.', '?', 1, '2026-10-22 06:03:42'),
(26, 21, 'New Post-Bid Chatroom message', 'There is a new message in PATATAAAS.', '?', 1, '2026-10-22 06:05:34'),
(27, 17, 'New farmer offer', 'Koji Ogino submitted an offer for Onionnn.', '?', 1, '2026-10-22 06:12:54'),
(28, 21, 'Your offer was accepted', 'The buyer accepted your offer for Onionnn. Arrange the meet-up in your orders.', '?', 1, '2026-10-22 06:13:09'),
(29, 21, 'Order updated', 'The buyer marked the order for PATATAAAS complete.', '?', 1, '2026-10-22 06:13:16'),
(30, 17, 'New farmer offer', 'Paul Rabulan submitted an offer for Eggplant:>.', '?', 1, '2026-10-22 07:04:33'),
(31, 17, 'New farmer offer', 'Paul Rabulan submitted an offer for Eggplant:>.', '?', 1, '2026-10-22 07:22:07'),
(32, 17, 'New farmer offer', 'Paul Rabulan submitted an offer for Eggplant:>.', '?', 1, '2026-10-22 07:22:28'),
(33, 17, 'New farmer offer', 'Paul Rabulan submitted an offer for Eggplant:>.', '?', 1, '2026-10-22 07:26:21');

-- --------------------------------------------------------

--
-- Table structure for table `room_messages`
--

CREATE TABLE `room_messages` (
  `message_id` bigint UNSIGNED NOT NULL,
  `room_id` int UNSIGNED NOT NULL,
  `sender_id` int NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_messages`
--

INSERT INTO `room_messages` (`message_id`, `room_id`, `sender_id`, `message`, `created_at`) VALUES
(2, 5, 20, 'makakapunta ka ba', '2026-10-21 09:11:36'),
(3, 5, 20, 'hahahah', '2026-10-21 09:11:55'),
(4, 5, 17, 'oo whahhaa', '2026-10-21 09:12:42'),
(15, 5, 20, 'haha', '2026-10-21 09:49:49'),
(16, 5, 17, 'wahahah', '2026-10-21 09:50:10'),
(17, 12, 17, 'tara, bukas nang alas tres', '2026-10-22 06:03:17'),
(18, 12, 21, 'sigesige', '2026-10-22 06:03:42'),
(19, 12, 17, 'a', '2026-10-22 06:05:34');

-- --------------------------------------------------------

--
-- Table structure for table `room_message_reads`
--

CREATE TABLE `room_message_reads` (
  `room_id` int UNSIGNED NOT NULL,
  `user_id` int NOT NULL,
  `last_read_message_id` bigint UNSIGNED NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_message_reads`
--

INSERT INTO `room_message_reads` (`room_id`, `user_id`, `last_read_message_id`, `updated_at`) VALUES
(5, 17, 16, '2026-10-22 05:57:39'),
(5, 20, 16, '2026-10-22 07:24:15'),
(12, 17, 19, '2026-10-22 06:05:34'),
(12, 21, 19, '2026-10-22 06:06:22');

-- --------------------------------------------------------

--
-- Table structure for table `room_orders`
--

CREATE TABLE `room_orders` (
  `order_id` int UNSIGNED NOT NULL,
  `room_id` int UNSIGNED NOT NULL,
  `offer_id` int UNSIGNED NOT NULL,
  `status` enum('Confirmed','Meet-up scheduled','Completed','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Confirmed',
  `meetup_at` datetime DEFAULT NULL,
  `meetup_location` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `buyer_completed_at` datetime DEFAULT NULL,
  `farmer_completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_orders`
--

INSERT INTO `room_orders` (`order_id`, `room_id`, `offer_id`, `status`, `meetup_at`, `meetup_location`, `buyer_completed_at`, `farmer_completed_at`, `created_at`) VALUES
(2, 5, 4, 'Completed', '2026-10-22 18:00:00', 'Binangonan sa tapat ng 7/11', '2026-10-21 17:10:33', '2026-10-21 17:10:33', '2026-10-21 09:10:33'),
(3, 12, 11, 'Completed', '2026-10-23 15:00:00', 'blk 34 lot 1 ph67, Rizal, Binangonan, Pantok, 1940', '2026-10-22 14:01:49', '2026-10-22 14:01:49', '2026-10-22 06:01:49'),
(4, 13, 12, 'Confirmed', NULL, 'blk 34 lot 1 ph67, Rizal, Binangonan, Pantok, 1940', NULL, NULL, '2026-10-22 06:13:09');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int NOT NULL,
  `default_timer_mins` int NOT NULL DEFAULT '30',
  `min_increment` decimal(8,2) NOT NULL DEFAULT '1.00',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `default_timer_mins`, `min_increment`, `updated_at`) VALUES
(1, 30, 1.00, '2026-10-21 00:43:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('Farmer / Seller','Buyer / Trader','System Administrator') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Farmer / Seller',
  `status` enum('Pending','Verified','Suspended') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password_hash`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Super Administrator', 'admin@agribid.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'Verified', '2026-10-21 00:43:19', '2026-10-21 00:43:19'),
(15, 'AgriBid Local Admin', 'admin@agribid.local', '$2y$10$nLVuNWXArD0/GRC0YFYnWOtcAxpK38tVSGOTUU4imav9AROWPiroy', 'System Administrator', 'Verified', '2026-10-21 08:04:57', '2026-10-21 08:04:57'),
(17, 'Jahred Khalil Paras', 'jk@gmail.com', '$2y$10$2maBrGaZxpJ3h7d6FZAtZ.NgZavz6pM/UUtJx51F4JZt8cA4uN1ri', 'Buyer / Trader', 'Verified', '2026-10-21 08:18:26', '2026-10-21 08:19:50'),
(20, 'Paul Rabulan', 'pol@gmail.com', '$2y$10$cF78AMQcCGezB5phB2jidujqFWV43pppMin76mPQMXNET/GDzRcg.', 'Farmer / Seller', 'Verified', '2026-10-21 08:42:11', '2026-10-21 08:46:42'),
(21, 'Koji Ogino', 'koji@gmail.com', '$2y$10$fblnwk.uIWfSbpRofYl8beKcTFzlEzYFEW6USGHGYG.8fpatpwIui', 'Farmer / Seller', 'Verified', '2026-10-22 05:35:44', '2026-10-22 05:37:36');

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `address_id` int UNSIGNED NOT NULL,
  `user_id` int NOT NULL,
  `label` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `barangay` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `full_address` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`address_id`, `user_id`, `label`, `city`, `barangay`, `full_address`, `is_default`, `created_at`) VALUES
(4, 20, 'Address', 'Binangonan', 'Pantok', 'Blk 31 lot 5 phase 4b', 1, '2026-10-22 08:10:14'),
(5, 17, 'Address', 'Binangonan', 'Bilibiran', 'Blk 32 lot 5 ph 5b', 1, '2026-10-22 08:16:53');

-- --------------------------------------------------------

--
-- Table structure for table `user_verifications`
--

CREATE TABLE `user_verifications` (
  `verification_id` int NOT NULL,
  `user_id` int NOT NULL,
  `id_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `document_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `location` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('Pending','Verified','Rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_verifications`
--

INSERT INTO `user_verifications` (`verification_id`, `user_id`, `id_type`, `document_path`, `location`, `status`, `submitted_at`, `reviewed_at`) VALUES
(3, 17, 'Government ID', '5de380eee530b97370710df5c45b8070.jpg', NULL, 'Verified', '2026-10-21 08:18:26', '2026-10-21 08:19:50'),
(5, 20, 'Government ID', 'd778f5d23cf1c36ddbf32ff35dd462a3.jpg', NULL, 'Verified', '2026-10-21 08:42:11', '2026-10-21 08:46:42'),
(6, 21, 'Government ID', 'cddcb8ad26aa748257d833c018466e02.jpg', NULL, 'Verified', '2026-10-22 05:35:44', '2026-10-22 05:37:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `buyer_rooms`
--
ALTER TABLE `buyer_rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `idx_buyer_rooms_status_deadline` (`status`,`deadline`),
  ADD KEY `idx_buyer_rooms_buyer` (`buyer_id`),
  ADD KEY `idx_buyer_rooms_city_status_deadline` (`city`,`status`,`deadline`);

--
-- Indexes for table `buyer_room_items`
--
ALTER TABLE `buyer_room_items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `uq_buyer_room_crop` (`room_id`,`crop_id`),
  ADD KEY `fk_room_items_crop` (`crop_id`);

--
-- Indexes for table `crops`
--
ALTER TABLE `crops`
  ADD PRIMARY KEY (`crop_id`),
  ADD UNIQUE KEY `uq_crops_crop_name` (`crop_name`),
  ADD KEY `idx_crops_category` (`category`),
  ADD KEY `idx_crops_is_active` (`is_active`);

--
-- Indexes for table `farmer_harvests`
--
ALTER TABLE `farmer_harvests`
  ADD PRIMARY KEY (`harvest_id`),
  ADD KEY `idx_farmer_harvests_farmer_date` (`farmer_id`,`expected_date`),
  ADD KEY `fk_farmer_harvests_crop` (`crop_id`);

--
-- Indexes for table `farmer_offers`
--
ALTER TABLE `farmer_offers`
  ADD PRIMARY KEY (`offer_id`),
  ADD UNIQUE KEY `uq_farmer_room_offer` (`room_id`,`farmer_id`),
  ADD KEY `idx_farmer_offers_farmer` (`farmer_id`);

--
-- Indexes for table `farmer_offer_items`
--
ALTER TABLE `farmer_offer_items`
  ADD PRIMARY KEY (`offer_item_id`),
  ADD UNIQUE KEY `uq_offer_crop` (`offer_id`,`crop_id`),
  ADD KEY `fk_offer_items_crop` (`crop_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `room_messages`
--
ALTER TABLE `room_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_room_messages_room` (`room_id`,`created_at`),
  ADD KEY `fk_room_messages_sender` (`sender_id`);

--
-- Indexes for table `room_message_reads`
--
ALTER TABLE `room_message_reads`
  ADD PRIMARY KEY (`room_id`,`user_id`),
  ADD KEY `fk_room_message_reads_user` (`user_id`);

--
-- Indexes for table `room_orders`
--
ALTER TABLE `room_orders`
  ADD PRIMARY KEY (`order_id`),
  ADD UNIQUE KEY `uq_order_room` (`room_id`),
  ADD UNIQUE KEY `uq_order_offer` (`offer_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`address_id`),
  ADD KEY `idx_user_addresses_user` (`user_id`);

--
-- Indexes for table `user_verifications`
--
ALTER TABLE `user_verifications`
  ADD PRIMARY KEY (`verification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `buyer_rooms`
--
ALTER TABLE `buyer_rooms`
  MODIFY `room_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `buyer_room_items`
--
ALTER TABLE `buyer_room_items`
  MODIFY `item_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `crops`
--
ALTER TABLE `crops`
  MODIFY `crop_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `farmer_harvests`
--
ALTER TABLE `farmer_harvests`
  MODIFY `harvest_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `farmer_offers`
--
ALTER TABLE `farmer_offers`
  MODIFY `offer_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `farmer_offer_items`
--
ALTER TABLE `farmer_offer_items`
  MODIFY `offer_item_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `room_messages`
--
ALTER TABLE `room_messages`
  MODIFY `message_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `room_orders`
--
ALTER TABLE `room_orders`
  MODIFY `order_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `address_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_verifications`
--
ALTER TABLE `user_verifications`
  MODIFY `verification_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `buyer_rooms`
--
ALTER TABLE `buyer_rooms`
  ADD CONSTRAINT `fk_buyer_rooms_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `buyer_room_items`
--
ALTER TABLE `buyer_room_items`
  ADD CONSTRAINT `fk_room_items_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`crop_id`),
  ADD CONSTRAINT `fk_room_items_room` FOREIGN KEY (`room_id`) REFERENCES `buyer_rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `farmer_harvests`
--
ALTER TABLE `farmer_harvests`
  ADD CONSTRAINT `fk_farmer_harvests_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`crop_id`),
  ADD CONSTRAINT `fk_farmer_harvests_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `farmer_offers`
--
ALTER TABLE `farmer_offers`
  ADD CONSTRAINT `fk_farmer_offers_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_farmer_offers_room` FOREIGN KEY (`room_id`) REFERENCES `buyer_rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `farmer_offer_items`
--
ALTER TABLE `farmer_offer_items`
  ADD CONSTRAINT `fk_offer_items_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`crop_id`),
  ADD CONSTRAINT `fk_offer_items_offer` FOREIGN KEY (`offer_id`) REFERENCES `farmer_offers` (`offer_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `room_messages`
--
ALTER TABLE `room_messages`
  ADD CONSTRAINT `fk_room_messages_room` FOREIGN KEY (`room_id`) REFERENCES `buyer_rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_room_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `room_message_reads`
--
ALTER TABLE `room_message_reads`
  ADD CONSTRAINT `fk_room_message_reads_room` FOREIGN KEY (`room_id`) REFERENCES `buyer_rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_room_message_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `room_orders`
--
ALTER TABLE `room_orders`
  ADD CONSTRAINT `fk_orders_offer` FOREIGN KEY (`offer_id`) REFERENCES `farmer_offers` (`offer_id`),
  ADD CONSTRAINT `fk_orders_room` FOREIGN KEY (`room_id`) REFERENCES `buyer_rooms` (`room_id`);

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `fk_user_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_verifications`
--
ALTER TABLE `user_verifications`
  ADD CONSTRAINT `user_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
