-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 29, 2025 at 07:37 PM
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
-- Database: `cms`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `name`, `email`, `password`, `created_at`) VALUES
(1, 'Awortwe Enock', 'awor@gmail.com', '$2y$10$JNN5VFsABKXFrgLj5PZg0ejmNItKHOCQ4K42f54HzK.9gUzB5XGk2', '2025-08-26 15:07:43'),
(2, 'Frank Nero', 'nero@gmail.com', '$2y$10$DzU2j5Db6HMgTSY6OvRCuO8A0I1OARbMl1RtPs7eko/WxX6v1MU9m', '2025-08-29 15:12:43');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `content` text NOT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `post_id`, `name`, `email`, `content`, `is_approved`, `created_at`) VALUES
(1, 1, 'Awortwe Enock', 'enockawor@gmail.com', 'He was a good man', 1, '2025-08-27 16:11:33');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `message_type` varchar(80) NOT NULL,
  `message_text` text NOT NULL,
  `is_first_timer` tinyint(1) NOT NULL DEFAULT 0,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `location`, `message_type`, `message_text`, `is_first_timer`, `is_read`, `created_at`, `is_deleted`) VALUES
(1, 'Awortwe Enock', 'enockawor@gmail.com', '0245227067', 'Sunyani', 'Revival request', 'We love for you to pay us a visit one day', 1, 0, '2025-08-27 16:57:43', 0);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `event_type` enum('Revival','Conference','Youth','Teaching','Other') NOT NULL,
  `status` enum('Upcoming','Ongoing','Completed','Cancelled') DEFAULT 'Upcoming',
  `action_text` varchar(50) DEFAULT 'Request Invite',
  `action_link` varchar(255) DEFAULT '#contact',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `event_date`, `event_time`, `location`, `event_type`, `status`, `action_text`, `action_link`, `created_at`, `updated_at`) VALUES
(1, 'Retreat Center Grand Opening', 'EBENEZER! WE ARE THERE......\r\nLET\'S MEET AT DOMEABRA.', '2025-08-26', '07:00:00', 'Domeabra', 'Other', 'Upcoming', 'Request Invite', '#contact', '2025-08-26 16:40:30', '2025-08-26 17:06:04'),
(2, 'Missions Conference', 'Equipping leaders for evangelism and discipleship.', '2025-10-05', '09:00:00', 'Nzema', 'Conference', 'Upcoming', 'Ask a Question', '#contact', '2025-08-26 16:40:30', '2025-08-26 16:47:19'),
(3, 'Youth Week Celebration', 'Worship, word, and mentorship for the next generation.', '2025-11-02', '17:00:00', 'Takoradi', 'Youth', 'Upcoming', 'Volunteer', '#contact', '2025-08-26 16:40:30', '2025-08-26 16:40:30'),
(4, 'Bible Teaching Week', 'Doctrinal foundations and leadership training.', '2025-07-07', '10:00:00', 'Accra', 'Teaching', 'Completed', 'Completed', '#', '2025-08-26 16:40:30', '2025-08-26 16:40:30');

-- --------------------------------------------------------

--
-- Table structure for table `home_content`
--

CREATE TABLE `home_content` (
  `id` int(11) NOT NULL,
  `title` text NOT NULL,
  `description` text NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `bullet_points` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`bullet_points`)),
  `button1_text` varchar(50) NOT NULL,
  `button1_link` varchar(255) NOT NULL,
  `button2_text` varchar(50) NOT NULL,
  `button2_link` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `home_content`
--

INSERT INTO `home_content` (`id`, `title`, `description`, `image_path`, `bullet_points`, `button1_text`, `button1_link`, `button2_text`, `button2_link`, `updated_at`) VALUES
(1, 'Bro. Dr. Dan Owusu Asiamah', 'Daniel Owusu Asiamah is a Ghanaian missionary and preacher of the Churches of Christ, and founder of the Outreach Africa Vocational Institute (OAVI). Born on June 8, 1965, he studied at institutions in Ghana, South Africa, and the United States, equipping himself for ministry and leadership. He also serves as Director of Studies at Takoradi Bible College and hosts the Voice of the Church program on Adom TV, with similar broadcasts reaching audiences in the US, Canada, and Europe. ', './images/a1.jpg', '[\"Contact info +1 209-327-6586\\r\",\"WhatsApp +1 209-327-6586\\r\",\"Email - danasiamah@gmail.com\"]', 'Listen to Sermons', '#sermons', 'Upcoming Events', '#events', '2025-08-27 15:29:22');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `status` enum('Draft','Published') DEFAULT 'Published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `display_order` int(11) DEFAULT 999
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `title`, `slug`, `excerpt`, `content`, `image_path`, `status`, `created_at`, `updated_at`, `display_order`) VALUES
(1, 'EPITOME OF JUSTICE AND MERCY.........', 'epitome-of-justice-and-mercy', '', 'Yesterday, August 20, 2025, the world lost Judge Frank Caprio, a retired Chief Municipal Judge of Providence, Rhode Island, in the United States. He was 88.\r\nWith a heart of gold, this remarkable Judge made compassionate decisions daily in his courtroom. A real definition of \"tampering justice with mercy\" personified.\r\nHe did not use the law to intimidate, bully, and exploit. Rather, he chose to use the law to become \"a savior\" to countless underprivileged people.\r\nI remember he once said in his courtroom,\" Be the good you wish someone had been for you. That is how the world changes\". This dawned on me that all the kindness he showed in his courtroom daily was deliberate. What a beautiful soul!\r\nFrom afar, you have been a mentor to me since my school days in your country.\r\nYou chose to be an epitome of \"the weightier matters of the law,\" as indicated by Christ Jesus in Matthew 23:23. You made your difference in a world of hatred, discrimination, and favoritism. You will forever be remembered.\r\nIn you, the world would always be reminded that, as Zig Ziggler said, \" we were all born to help each other\". Rest well, a real citizen of the world. Through your profession, you \"let your light shine in a corrupt and dark world. I have cried my heart out over your departure. However, the consolation comes from the fact that you have successfully impacted your generation. Something you have inspired me to pursue throughout my life.\r\nI pray that Judges and magistrates around the world would imitate your life in the various courtrooms.\r\nFor those who did not know Judge Frank Caprio, you can watch some of his courtroom videos on YouTube to judge it yourself.\r\nHis legacy of kindness endures, inspiring the rest of us to embrace empathy daily. It\'s always right to imitate that which is good..3John 11.\r\nGod bless,\r\nDan', 'uploads/posts/post_68af2d56594c2.jpg', 'Published', '2025-08-27 16:07:50', '2025-08-27 16:07:50', 999);

-- --------------------------------------------------------

--
-- Table structure for table `sermon_videos`
--

CREATE TABLE `sermon_videos` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `short_video_file` varchar(255) DEFAULT NULL,
  `youtube_video_id` varchar(64) DEFAULT NULL,
  `full_video_url` varchar(255) DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sermon_videos`
--

INSERT INTO `sermon_videos` (`id`, `title`, `description`, `short_video_file`, `youtube_video_id`, `full_video_url`, `thumbnail_path`, `display_order`, `created_at`) VALUES
(1, 'My God & My Country (False Prophets)', 'Bro. Dr. Dan Owusu Asiamah (24/08/25)', 'uploads/videos/shorts/sv_68aefb9e9ade3.mp4', NULL, 'https://youtu.be/9kFbKy77p08?si=bb-Kz_szN7yMgBp7', 'uploads/thumbnails/sermons/thumb_68aefb9e97de9.jpg', 0, '2025-08-27 12:35:42'),
(2, 'My God & My Country', 'Bro. Dr. Dan Owusu Asiamah (17/08/25)', 'uploads/videos/shorts/sv_68af151f63c33.mp4', 'zi1Z0dyCtKgC3XEI', 'https://youtu.be/315IcDZfqUc?si=zi1Z0dyCtKgC3XEI', 'uploads/thumbnails/sermons/thumb_68af151f63647.jpg', 0, '2025-08-27 14:24:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `home_content`
--
ALTER TABLE `home_content`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `sermon_videos`
--
ALTER TABLE `sermon_videos`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `home_content`
--
ALTER TABLE `home_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sermon_videos`
--
ALTER TABLE `sermon_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
