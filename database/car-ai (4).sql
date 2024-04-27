-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 14, 2023 at 11:11 PM
-- Server version: 10.4.25-MariaDB
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `car-ai`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounting_journals`
--

CREATE TABLE `accounting_journals` (
  `id` int(10) UNSIGNED NOT NULL,
  `ledger_id` int(10) UNSIGNED DEFAULT NULL,
  `balance` bigint(20) NOT NULL,
  `currency` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `morphed_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `morphed_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounting_journals`
--

INSERT INTO `accounting_journals` (`id`, `ledger_id`, `balance`, `currency`, `morphed_type`, `morphed_id`, `created_at`, `updated_at`) VALUES
(1, 1, 10, 'IQD', 'App\\Models\\User', 1, '2023-06-01 20:32:03', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `accounting_journal_transactions`
--

CREATE TABLE `accounting_journal_transactions` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_group` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `journal_id` int(11) NOT NULL,
  `debit` bigint(20) DEFAULT NULL,
  `credit` bigint(20) DEFAULT NULL,
  `currency` char(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `memo` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_class` char(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `post_date` datetime NOT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounting_journal_transactions`
--

INSERT INTO `accounting_journal_transactions` (`id`, `transaction_group`, `journal_id`, `debit`, `credit`, `currency`, `memo`, `tags`, `ref_class`, `ref_class_id`, `created_at`, `updated_at`, `post_date`, `deleted_at`) VALUES
('245d0890-0705-11ee-9934-2cf05dfd8971', '1', 1, 1, NULL, 'IQD', '2asd', 'sadsa', '1', 1, '2023-06-01 20:32:03', NULL, '2023-06-09 22:34:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `accounting_ledgers`
--

CREATE TABLE `accounting_ledgers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('asset','liability','equity','income','expense') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounting_ledgers`
--

INSERT INTO `accounting_ledgers` (`id`, `name`, `type`, `created_at`, `updated_at`) VALUES
(1, 'hospital', 'asset', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `appointment`
--

CREATE TABLE `appointment` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `card_id` int(11) DEFAULT NULL,
  `start` timestamp NULL DEFAULT NULL,
  `end` timestamp NULL DEFAULT NULL,
  `is_come` tinyint(4) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `car`
--

CREATE TABLE `car` (
  `id` int(11) NOT NULL,
  `name_id` int(11) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `color_id` int(11) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `image` longtext DEFAULT NULL,
  `pin` varchar(255) DEFAULT NULL,
  `price` int(11) DEFAULT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `paid_amount` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `user_purchase_id` int(11) DEFAULT NULL,
  `user_accepted` int(11) DEFAULT NULL,
  `user_rejected` int(11) DEFAULT NULL,
  `results` tinyint(11) NOT NULL DEFAULT 0,
  `no` int(11) NOT NULL,
  `deleted_at` date DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `purchase_price` int(11) DEFAULT NULL,
  `purchase_data` date DEFAULT NULL,
  `note_pay` varchar(255) DEFAULT NULL,
  `pay_data` date DEFAULT NULL,
  `pay_price` int(11) DEFAULT NULL,
  `paid_amount_pay` int(11) DEFAULT NULL,
  `erbil_exp` int(11) DEFAULT 0,
  `erbil_shipping` int(11) DEFAULT 0,
  `dubai_exp` int(11) DEFAULT 0,
  `dubai_shipping` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `card`
--

CREATE TABLE `card` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `name_en` varchar(255) NOT NULL,
  `day` int(11) DEFAULT NULL,
  `price` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `car_model`
--

CREATE TABLE `car_model` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `deleted_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `color`
--

CREATE TABLE `color` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `deleted_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `company`
--

CREATE TABLE `company` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `company_type_id` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `expenses_type`
--

CREATE TABLE `expenses_type` (
  `id` int(11) NOT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `name_ar` varchar(255) DEFAULT NULL,
  `name_kr` varchar(255) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `expenses_type`
--

INSERT INTO `expenses_type` (`id`, `name_en`, `name_ar`, `name_kr`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Dubai expenses', 'مصاريف دبي', 'Dubai expenses', 1, '2023-06-10 10:05:47', NULL, NULL),
(2, 'Erbil expenses', 'مصاريف أربيل', 'Erbil expenses', 1, '2023-06-10 10:05:47', NULL, NULL),
(3, 'erbil shipping ', 'شحن اربيل', 'Erbil shipping ', 1, '2023-06-10 13:04:19', NULL, NULL),
(4, 'dubai shipping ', 'شحن دبي', 'Dubai shipping ', 1, '2023-06-10 13:04:19', NULL, NULL),
(5, 'Complete payments', 'إكمل الدفعات', 'Complete payments', 1, '2023-06-12 12:06:01', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `info`
--

CREATE TABLE `info` (
  `id` varchar(255) NOT NULL,
  `phone` longtext DEFAULT NULL,
  `fname` longtext DEFAULT NULL,
  `lname` varchar(255) DEFAULT NULL,
  `sex` varchar(255) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `p1` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `fullname` varchar(255) DEFAULT NULL,
  `work` varchar(255) DEFAULT NULL,
  `study` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `p2` varchar(255) DEFAULT NULL,
  `p3` varchar(255) DEFAULT NULL,
  `p4` varchar(255) DEFAULT NULL,
  `date1` varchar(255) DEFAULT NULL,
  `date2` varchar(255) DEFAULT NULL,
  `p5` varchar(255) DEFAULT NULL,
  `p6` varchar(255) DEFAULT NULL,
  `p7` varchar(255) DEFAULT NULL,
  `updated_at` text DEFAULT NULL,
  `created_at` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `massage`
--

CREATE TABLE `massage` (
  `id` int(11) NOT NULL,
  `image` text DEFAULT NULL,
  `voice` text DEFAULT NULL,
  `text` text DEFAULT NULL,
  `sender_id` bigint(20) DEFAULT NULL,
  `receiver_id` bigint(20) DEFAULT NULL,
  `aes` varchar(255) DEFAULT NULL,
  `Lat` varchar(255) DEFAULT NULL,
  `lng` varchar(255) DEFAULT NULL,
  `is_download` tinyint(4) NOT NULL DEFAULT 0,
  `is_read` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `massage`
--

INSERT INTO `massage` (`id`, `image`, `voice`, `text`, `sender_id`, `receiver_id`, `aes`, `Lat`, `lng`, `is_download`, `is_read`, `created_at`, `updated_at`) VALUES
(313, '', '', '', 2, 222, 'aaaaaaaaaaaaaa', 'eyJpdiI6IjZGUUJBUUoyeCtLOWdoSWx5SVN0cEE9PSIsInZhbHVlIjoiWC9DQUtYV1BDOWE4WTdHT3dIRWszdz09IiwibWFjIjoiYWFhZTVlNjhhNTllYmRjZWU1ODAxZWU0NTc0ZThkNDI0NzQ2N2IxNmIyNDRiMjBhNTc5NWZjOWUyMDA0YTE3MiIsInRhZyI6IiJ9', 'eyJpdiI6ImNweGJUM2FNR3pvZ3ZVSnRwL3ZhSmc9PSIsInZhbHVlIjoiOG1Wa01wUDZnMXlZSWp3UkNGVjE5QT09IiwibWFjIjoiMWI3YjZmNjQ5NjY0MDcyYmYzN2E2Y2M3YjQ5NzMwMThkMzZlNjg5MzY4MDVmMTZkMWJiN2NlZTY3NmJhMDFiNCIsInRhZyI6IiJ9', 0, 0, '2022-12-08 05:17:37', '2022-12-08 05:17:37'),
(314, 'http://127.0.0.1:8000/data/image/ndgcwsRq3jiM006oosVUCLU0H0xg3sT49BZMSmTC.jpg', '', '', 2, 222, 'aaaaaaaaaaaaaa', 'eyJpdiI6IjFTSmIvSVFhckZLUUNTSEp6UWtTNkE9PSIsInZhbHVlIjoiYUpWM3h0aStWcnV1N29XTDl5QTZoQT09IiwibWFjIjoiMmFhY2FjODVjZGMwNWE2Y2VlN2Q3NjRiZThjOTNhMzZkMmMxM2UzZDcyZWU1NzkzNjAyZDk5ZGQ3OGJhMGNiZCIsInRhZyI6IiJ9', 'eyJpdiI6InhqMGprQ1Y4RW5hYThiZ1N4dnVjc2c9PSIsInZhbHVlIjoiRUtHTm84TjVldTFLamttc0xrQlYyZz09IiwibWFjIjoiM2JmNTkyMDgxZmViMWE4N2Y2OWIxN2YwZDM4M2Y3MDAxZWU3Y2ZiODE4ZjRiYjJjYzRlNjM1ZDQ1MTgyNmY4NiIsInRhZyI6IiJ9', 0, 0, '2022-12-08 05:18:09', '2022-12-08 05:18:09'),
(315, 'http://127.0.0.1:8000/data/image/yavBPDcScVkxnJCgpjSybSJZKYnUIWY9ooK5P7Si.jpg', '', '', 2, 222, 'aaaaaaaaaaaaaa', 'eyJpdiI6InhYTS9vUllsZXJkYzQ5czhBVVJoY0E9PSIsInZhbHVlIjoiblB3d0VMSm0rQUJ0ZjM0aHh2NE8zQT09IiwibWFjIjoiNzQ5N2NmMWY0YzIzOWI4Zjg3MTkyNGZjZmNkMjNhNjM1MjM2ODllOWJlYjRjMDUzNjRlZDBmM2JjYzhmNzJhYSIsInRhZyI6IiJ9', 'eyJpdiI6ImlFbXBrNHpmOFhEdzk2dmhFNS9WMHc9PSIsInZhbHVlIjoiYUJic043Ulg2Z3pkbzBJbmhoTkpMZz09IiwibWFjIjoiOTQ1OTIxMTVmZTlhOTNkOGRkOGYzMTU3N2RjYTVmZjQ2ZGIyZDA2NjhmMjIxYTliMjRmNWIwYzU2MjE4ZTlkMiIsInRhZyI6IiJ9', 0, 0, '2022-12-08 05:31:07', '2022-12-08 05:31:07'),
(316, 'http://127.0.0.1:8000/data/image/xJ7B8FSguT0HSud9J1TxZUdz6FKY7av4O8cG46s1.jpg', '', '', 2, 222, 'aaaaaaaaaaaaaa', 'eyJpdiI6IlRMTmhrdTRCQ3pTTnlOQVhOR0UzdVE9PSIsInZhbHVlIjoicXlYdmx6RTd0WnFKN2VEQXRZd2JvZz09IiwibWFjIjoiYTExMzNjNjkzY2I1NjQ4NzE3NTFlOTU2NjUxYjJiNWVjYWVlZDIwZWEwM2U4OTAzNmFjNzg2YmEyZDBlNWExYyIsInRhZyI6IiJ9', 'eyJpdiI6IldCUWdJYlUxaTB6WEUyMVZhdzRlbnc9PSIsInZhbHVlIjoiVVV0L1lKNDBrVXlPZFFrNnVWdXR0QT09IiwibWFjIjoiYTQ1ZmQ5YjQ5YzU1NWMzNGVlYzY1M2M1NTA5NTMyMDk1OWYyZTM5ZDYwNWFjM2RmY2Y2MWQ0M2E3ODZmNGZjZSIsInRhZyI6IiJ9', 0, 0, '2022-12-11 19:00:12', '2022-12-11 19:00:12');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_resets_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(10, '2016_06_01_000001_create_oauth_auth_codes_table', 2),
(11, '2016_06_01_000002_create_oauth_access_tokens_table', 2),
(12, '2016_06_01_000003_create_oauth_refresh_tokens_table', 2),
(13, '2016_06_01_000004_create_oauth_clients_table', 2),
(14, '2016_06_01_000005_create_oauth_personal_access_clients_table', 2),
(15, '2016_05_19_000000_create_accounting_journal_transactions_table', 3),
(16, '2016_05_19_000000_create_accounting_journals_table', 3),
(17, '2017_05_21_000000_create_accounting_ledgers_table', 3),
(18, '2023_05_23_025415_add_morphed_columns_to_users_table', 4);

-- --------------------------------------------------------

--
-- Table structure for table `name`
--

CREATE TABLE `name` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `name_en` varchar(255) DEFAULT NULL,
  `status` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `oauth_clients`
--

CREATE TABLE `oauth_clients` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` tinyint(1) NOT NULL,
  `password_client` tinyint(1) NOT NULL,
  `revoked` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `oauth_clients`
--

INSERT INTO `oauth_clients` (`id`, `user_id`, `name`, `secret`, `provider`, `redirect`, `personal_access_client`, `password_client`, `revoked`, `created_at`, `updated_at`) VALUES
('97e41408-1c4a-456e-9f9e-34181f4cd11b', NULL, 'Laravel Personal Access Client', 'peu951sy7QA6TmYUmfbgG0WFU5KFjS7JVbrhTItd', NULL, 'http://localhost', 1, 0, 0, '2022-12-03 17:07:24', '2022-12-03 17:07:24'),
('97e41408-2116-4379-bcbf-cdc6560c089b', NULL, 'Laravel Password Grant Client', 'LjT7SBHvnsO50r0nrXEAHvw1OfMq3j12VzG8N9Ed', 'users', 'http://localhost', 0, 1, 0, '2022-12-03 17:07:24', '2022-12-03 17:07:24');

-- --------------------------------------------------------

--
-- Table structure for table `oauth_personal_access_clients`
--

CREATE TABLE `oauth_personal_access_clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `oauth_personal_access_clients`
--

INSERT INTO `oauth_personal_access_clients` (`id`, `client_id`, `created_at`, `updated_at`) VALUES
(1, '97e41408-1c4a-456e-9f9e-34181f4cd11b', '2022-12-03 17:07:24', '2022-12-03 17:07:24');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`email`, `token`, `created_at`) VALUES
('admin@admin.com', '$2y$10$uIKz2h1TByBrOs7C2xk6XuMqz07zw0Gxz2Tq0fXsUwlr4A5l6Gnd6', '2022-12-10 17:28:47');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `id` int(20) NOT NULL,
  `husband_b` varchar(255) DEFAULT NULL,
  `husband_hb` varchar(255) DEFAULT NULL,
  `husband_mcv` varchar(255) DEFAULT NULL,
  `husband_mch` varchar(255) DEFAULT NULL,
  `husband_hemoglobin_a` varchar(255) DEFAULT NULL,
  `husband_hemoglobin_f` varchar(255) DEFAULT NULL,
  `husband_hemoglobin_a2` varchar(255) DEFAULT NULL,
  `husband_hbs` varchar(255) DEFAULT NULL,
  `husband_hcv` varchar(255) DEFAULT NULL,
  `husband_hiv` varchar(255) DEFAULT NULL,
  `husband_tb` varchar(255) DEFAULT NULL,
  `husband_syphilis` varchar(255) DEFAULT NULL,
  `husband_tpha` varchar(255) DEFAULT NULL,
  `husband_results` tinyint(4) DEFAULT 1,
  `wife_b` varchar(255) DEFAULT NULL,
  `wife_hb` varchar(255) DEFAULT NULL,
  `wife_mcv` varchar(255) DEFAULT NULL,
  `wife_mch` varchar(255) DEFAULT NULL,
  `wife_hemoglobin_a` varchar(255) DEFAULT NULL,
  `wife_hemoglobin_f` varchar(255) DEFAULT NULL,
  `wife_hemoglobin_a2` varchar(255) DEFAULT NULL,
  `wife_hbs` varchar(255) DEFAULT NULL,
  `wife_hcv` varchar(255) DEFAULT NULL,
  `wife_hiv` varchar(255) DEFAULT NULL,
  `wife_tb` varchar(255) DEFAULT NULL,
  `wife_syphilis` varchar(255) DEFAULT NULL,
  `wife_tpha` varchar(255) DEFAULT NULL,
  `wife_results` tinyint(4) DEFAULT 1,
  `user_id` bigint(20) NOT NULL,
  `profile_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

CREATE TABLE `system_config` (
  `id` int(11) NOT NULL,
  `first_title_ar` varchar(255) DEFAULT NULL,
  `first_title_kr` varchar(255) DEFAULT NULL,
  `second_title_ar` varchar(255) DEFAULT NULL,
  `second_title_kr` varchar(255) DEFAULT NULL,
  `third_title_ar` varchar(255) DEFAULT NULL,
  `third_title_kr` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`id`, `first_title_ar`, `first_title_kr`, `second_title_ar`, `second_title_kr`, `third_title_ar`, `third_title_kr`) VALUES
(1, 'العراق', 'Iraq', 'بغداد - أربيل- كركوك', 'Baghdad - Erbil - Kirkuk', 'نظام إدارة مبيعات السيارات الذكي', 'Intelligent car sale management system');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `wallet_id` bigint(20) UNSIGNED NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` double NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in',
  `is_pay` int(11) NOT NULL DEFAULT 0,
  `morphed_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `morphed_id` int(11) DEFAULT NULL,
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '$',
  `user_added` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transfers`
--

CREATE TABLE `transfers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `no` int(11) DEFAULT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `currency` varchar(255) NOT NULL DEFAULT '$',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type_id` bigint(20) DEFAULT NULL,
  `is_band` tinyint(4) NOT NULL DEFAULT 0,
  `percentage` int(11) DEFAULT 0,
  `morphed_id` bigint(20) UNSIGNED DEFAULT NULL,
  `morphed_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `type_id`, `is_band`, `percentage`, `morphed_id`, `morphed_type`, `phone`) VALUES
(60, 'admin', 'admin@admin.com', NULL, '$2y$10$E71qzt2mNzbQLHfo5tfwFeRmc2I5Zbmwx211jZASeXJKb0owxe702', 'YUocduVV0XdFPYZkbcnryoRrdclS8TBGNe2O1M6KxSSHYQfycV4pEiKNy0bg', '2022-12-10 18:31:04', '2022-12-10 18:31:04', 1, 0, 0, NULL, NULL, NULL),
(149, 'الخزينة', 'main@account.com', NULL, '$2y$10$OcSd0d7gqwoPKKkzh7au/.SwQa53J6nGYtO6Jcp7iamhnTwIhLVDq', NULL, '2023-06-10 11:10:05', '2023-06-10 11:10:05', 2, 0, 0, NULL, NULL, NULL),
(150, 'الدخل', 'in@account.com', NULL, '$2y$10$mSHZcwvEjq7CaOc/yealqOcG09VBzkHaLxfXmBW2nphD5AbJRz76.', NULL, '2023-06-10 11:24:23', '2023-06-10 11:24:23', 2, 0, 0, NULL, NULL, NULL),
(151, 'الخرج', 'out@account.com', NULL, '$2y$10$H9e0jDreSQWLNYQHoql2aeZRB67IifJtyeqNfvPt.eXyFWVKDbwW6', NULL, '2023-06-10 11:24:53', '2023-06-10 11:24:53', 2, 0, 0, NULL, NULL, NULL),
(152, 'دين', 'debt@account.com', NULL, '$2y$10$qkpi9CaA9WugK4EClWiCbOMTVy6vjP/skoownLedvIdDGv3XOyxrm', NULL, '2023-06-10 11:29:06', '2023-06-10 11:29:06', 2, 0, 0, NULL, NULL, NULL),
(153, 'الحولات', 'transfers@account.com', NULL, '$2y$10$lEifhbixHfl4Wk/uQl7wDuCDK5aE.Ve0pQD4MMA1Hb2H.MKTJYiRa', NULL, '2023-06-10 11:31:27', '2023-06-10 11:31:27', 2, 0, 0, NULL, NULL, NULL),
(156, 'مدفوعات المورد', 'supplier-out', NULL, '$2y$10$4FcjQlOQn12urOq7MR8quenUknrnH8vVqYUhR4OX.vgMbNz//2kPa', NULL, '2023-06-12 10:52:02', '2023-06-12 10:52:02', 2, 0, 0, NULL, NULL, NULL),
(157, 'دين المورد', 'supplier-debt', NULL, '$2y$10$D0.39iATmIXVgZySOYQOL.7ovQvTB8e9xEfZ.AcK4PD.TW69GdZ7S', NULL, '2023-06-12 10:53:02', '2023-06-12 10:54:43', 2, 0, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_type`
--

CREATE TABLE `user_type` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `user_type`
--

INSERT INTO `user_type` (`id`, `name`, `created_at`, `updated_at`) VALUES
(1, 'admin', '2022-12-19 12:20:12', NULL),
(2, 'account', '2023-06-10 11:05:31', NULL),
(3, 'seles', '2023-05-12 00:25:03', NULL),
(4, 'client', '2023-06-10 10:39:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `balance` float NOT NULL DEFAULT 0,
  `card` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`id`, `user_id`, `balance`, `card`, `created_at`, `updated_at`) VALUES
(27, 149, 0, 0, NULL, '2023-06-14 20:46:06'),
(28, 150, 0, 0, '2023-06-10 11:24:23', '2023-06-14 20:46:06'),
(29, 151, 0, 0, '2023-06-10 11:24:53', '2023-06-14 20:37:10'),
(30, 152, 0, 0, '2023-06-10 11:29:06', '2023-06-14 20:46:06'),
(31, 153, 0, 0, '2023-06-10 11:31:27', '2023-06-12 22:14:59'),
(34, 156, 0, 0, '2023-06-12 10:52:02', '2023-06-14 20:37:10'),
(35, 157, 0, 0, '2023-06-12 10:53:02', '2023-06-14 20:46:06');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounting_journals`
--
ALTER TABLE `accounting_journals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `accounting_journal_transactions`
--
ALTER TABLE `accounting_journal_transactions`
  ADD UNIQUE KEY `accounting_journal_transactions_id_unique` (`id`);

--
-- Indexes for table `accounting_ledgers`
--
ALTER TABLE `accounting_ledgers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `appointment`
--
ALTER TABLE `appointment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `car`
--
ALTER TABLE `car`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `card`
--
ALTER TABLE `card`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `car_model`
--
ALTER TABLE `car_model`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `color`
--
ALTER TABLE `color`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `company`
--
ALTER TABLE `company`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses_type`
--
ALTER TABLE `expenses_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `massage`
--
ALTER TABLE `massage`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `name`
--
ALTER TABLE `name`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `oauth_clients`
--
ALTER TABLE `oauth_clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `oauth_clients_user_id_index` (`user_id`);

--
-- Indexes for table `oauth_personal_access_clients`
--
ALTER TABLE `oauth_personal_access_clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD KEY `password_resets_email_index` (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transactions_wallet_id_foreign` (`wallet_id`);

--
-- Indexes for table `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `a` (`type_id`);

--
-- Indexes for table `user_type`
--
ALTER TABLE `user_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `wallets_user_id_foreign` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounting_journals`
--
ALTER TABLE `accounting_journals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `accounting_ledgers`
--
ALTER TABLE `accounting_ledgers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointment`
--
ALTER TABLE `appointment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `car`
--
ALTER TABLE `car`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `card`
--
ALTER TABLE `card`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `car_model`
--
ALTER TABLE `car_model`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `color`
--
ALTER TABLE `color`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `company`
--
ALTER TABLE `company`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `expenses_type`
--
ALTER TABLE `expenses_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `massage`
--
ALTER TABLE `massage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=317;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `name`
--
ALTER TABLE `name`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `oauth_personal_access_clients`
--
ALTER TABLE `oauth_personal_access_clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` int(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_config`
--
ALTER TABLE `system_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=315;

--
-- AUTO_INCREMENT for table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `user_type` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
