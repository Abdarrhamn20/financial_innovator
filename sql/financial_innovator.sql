-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 15 أغسطس 2026 الساعة 18:02
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `financial_innovator`
--

-- --------------------------------------------------------

--
-- بنية الجدول `promotions`
--

CREATE TABLE `promotions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `from_stage_id` int(11) DEFAULT NULL,
  `to_stage_id` int(11) DEFAULT NULL,
  `promotion_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `promotions`
--

INSERT INTO `promotions` (`id`, `student_id`, `from_stage_id`, `to_stage_id`, `promotion_date`, `notes`, `created_at`) VALUES
(3, 14, 11, 12, '2026-08-15', '', '2026-08-15 14:22:04'),
(4, 12, 11, 12, '2026-08-15', '', '2026-08-15 14:23:18'),
(5, 11, 11, 12, '2026-08-15', '', '2026-08-15 14:23:40'),
(6, 3, 11, 12, '2026-08-15', '', '2026-08-15 14:23:59'),
(7, 4, 11, 12, '2026-08-15', '', '2026-08-15 14:24:13'),
(8, 10, 11, 12, '2026-08-15', '', '2026-08-15 14:24:34'),
(9, 9, 11, 12, '2026-08-15', '', '2026-08-15 14:24:56'),
(10, 13, 11, 12, '2026-08-15', '', '2026-08-15 14:25:13'),
(11, 7, 11, 12, '2026-08-15', '', '2026-08-15 14:25:27'),
(12, 5, 11, 12, '2026-08-15', '', '2026-08-15 14:25:52'),
(13, 15, 11, 12, '2026-08-15', '', '2026-08-15 14:26:09'),
(14, 6, 11, 12, '2026-08-15', '', '2026-08-15 14:26:23'),
(15, 8, 11, 12, '2026-08-15', '', '2026-08-15 14:26:37'),
(16, 16, 11, 12, '2026-08-15', '', '2026-08-15 14:27:11'),
(17, 2, 11, 12, '2026-08-15', '', '2026-08-15 14:27:30');

-- --------------------------------------------------------

--
-- بنية الجدول `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('school','institute') NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `schools`
--

INSERT INTO `schools` (`id`, `name`, `type`, `address`, `phone`, `email`, `created_at`) VALUES
(2, 'معهد المستقبل المضئ المتوسط', 'institute', 'مشروع الهضبة', '0925647935', 'jehad.aljray.ly2@gmail.com', '2026-08-07 19:07:28'),
(3, 'مدرسة المستقبل المضئ', 'school', 'مشروع الهضبة', '0919648309', 'jehad.aljray.ly2@gmail.com', '2026-08-15 13:39:15');

-- --------------------------------------------------------

--
-- بنية الجدول `stages`
--

CREATE TABLE `stages` (
  `id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `order_number` int(11) DEFAULT 0,
  `fee_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `stages`
--

INSERT INTO `stages` (`id`, `school_id`, `name`, `order_number`, `fee_amount`, `description`, `created_at`) VALUES
(10, 3, 'التمهيدي', 1, 1500.00, 'التمهيدي', '2026-08-15 13:40:12'),
(11, 3, 'الصف الاول إبتدائي', 2, 1550.00, '', '2026-08-15 13:40:39'),
(12, 3, 'الصف الثاني الإبتدائي', 3, 1600.00, '', '2026-08-15 13:40:57'),
(13, 3, 'الصف الثالث الإبتدائي', 4, 1650.00, '', '2026-08-15 13:41:24'),
(14, 3, 'الصف الرابع الإبتدائي', 5, 1800.00, '', '2026-08-15 13:41:40'),
(15, 3, 'الصف الخامس الإبتدائي', 6, 1850.00, '', '2026-08-15 13:42:06'),
(16, 3, 'الصف السادس الإبتدائي', 7, 1800.00, '', '2026-08-15 13:42:41');

-- --------------------------------------------------------

--
-- بنية الجدول `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL,
  `current_stage_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `student_code` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `enrollment_date` date DEFAULT NULL,
  `status` enum('active','inactive','graduated') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `students`
--

INSERT INTO `students` (`id`, `school_id`, `current_stage_id`, `name`, `student_code`, `birth_date`, `phone`, `address`, `enrollment_date`, `status`, `created_at`) VALUES
(2, 3, 12, 'همام محمد علي الثائب خليفة', '6011622', '2019-01-15', '', 'مشروع الهضبة خط 4', '2026-08-15', 'active', '2026-08-15 13:52:46'),
(3, 3, 12, 'رزان الهادي بلال  ابوالقاسم', '6013262', '2019-02-04', '', 'مشروع الهضبة خط 6', '2026-08-15', 'active', '2026-08-15 13:54:17'),
(4, 3, 12, 'رسان نورالدين خليفة دريهيب', '6011615', '2019-12-13', '', 'مشروع الهضبة', '2026-08-15', 'active', '2026-08-15 13:55:23'),
(5, 3, 12, 'عبدالمهيمن هشام علي مسعود', '6011623', '2019-03-06', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 13:56:07'),
(6, 3, 12, 'عمر مصطفي عمر الدرويش', '6011619', '2019-07-16', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 13:56:55'),
(7, 3, 12, 'عبدالمؤمن وليد فرج الشويرف', '6011616', '2019-11-25', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 13:57:46'),
(8, 3, 12, 'غزل عثمان امحمد السميع', '6011620', '2019-03-05', '', 'مشروع الهضبة الزراعي', '2026-08-15', 'active', '2026-08-15 13:59:33'),
(9, 3, 12, 'سوهان علي مفتاح الحبشي', '6011625', '2019-07-04', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 14:03:08'),
(10, 3, 12, 'سجى سراج الدين خليفة الرتيمي', '6011624', '2019-02-11', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 14:05:04'),
(11, 3, 12, 'ابراهيم المحتار محمد غريبه', '6011612', '2019-10-24', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 14:06:19'),
(12, 3, 12, 'أنس عبدالمالك محمد علي', '6011614', '2019-08-24', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 14:07:24'),
(13, 3, 12, 'عبدالله وائل عبدالله حمد', '6011617', '2019-12-16', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 14:08:58'),
(14, 3, 12, 'أسنات عادل صالح ثومية', '6011613', '2019-08-30', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 14:09:59'),
(15, 3, 12, 'عزيزة وائل عبدالله حمد', '6011618', '2019-12-16', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 14:10:46'),
(16, 3, 12, 'محمد أمين عبدالله القاضي', '6011621', '2019-03-16', '', 'المشروع الزراعي', '2026-08-15', 'active', '2026-08-15 14:15:34');

-- --------------------------------------------------------

--
-- بنية الجدول `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `stage_id` int(11) DEFAULT NULL,
  `type` enum('income','expense') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `payment_method` enum('cash','bank','online') DEFAULT NULL,
  `status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `transactions`
--

INSERT INTO `transactions` (`id`, `student_id`, `stage_id`, `type`, `amount`, `description`, `transaction_date`, `payment_method`, `status`, `created_at`) VALUES
(6, NULL, NULL, 'expense', 3000.00, 'مصروف عام - الإيجار: اجار', '2026-08-07', 'cash', 'paid', '2026-08-07 19:53:23'),
(7, NULL, NULL, 'income', 100.00, 'إيراد عام - عوائد استثمارية: عوائد اتنثمار', '2026-08-07', 'cash', 'paid', '2026-08-07 19:57:19'),
(9, NULL, NULL, 'income', 2000.00, 'إيراد عام - عوائد استثمارية: عوائد اتنثمار', '2026-08-07', 'cash', 'paid', '2026-08-07 20:14:40'),
(14, NULL, NULL, 'expense', 50.00, 'مصروف عام - فواتير الكهرباء: فاتورة كهرباء', '2026-08-07', 'cash', 'paid', '2026-08-07 20:28:24'),
(15, NULL, NULL, 'expense', 500.00, 'مصروف عام - رواتب الموظفين: رواتب الموظفين - مرتب', '2026-08-15', 'cash', 'paid', '2026-08-15 13:33:48'),
(16, 14, 11, 'income', 1550.00, 'رسوم الفصل الدراسي', '2026-08-15', 'cash', 'paid', '2026-08-15 14:20:40'),
(17, NULL, NULL, 'expense', 50.00, 'مصروف عام - الصيانة: اصلاح شيشنة', '2026-08-15', 'cash', 'paid', '2026-08-15 14:43:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `from_stage_id` (`from_stage_id`),
  ADD KEY `to_stage_id` (`to_stage_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `stages`
--
ALTER TABLE `stages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_code` (`student_code`),
  ADD KEY `school_id` (`school_id`),
  ADD KEY `current_stage_id` (`current_stage_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `stage_id` (`stage_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stages`
--
ALTER TABLE `stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `promotions`
--
ALTER TABLE `promotions`
  ADD CONSTRAINT `promotions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `promotions_ibfk_2` FOREIGN KEY (`from_stage_id`) REFERENCES `stages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `promotions_ibfk_3` FOREIGN KEY (`to_stage_id`) REFERENCES `stages` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `stages`
--
ALTER TABLE `stages`
  ADD CONSTRAINT `stages_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`current_stage_id`) REFERENCES `stages` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
