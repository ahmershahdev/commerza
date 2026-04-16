-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 06, 2026 at 02:00 PM
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
-- Database: `commerza`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_sessions`
--

CREATE TABLE `admin_sessions` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL COMMENT 'Secure random token (sha256 / uuid)',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','operations_manager','customer_support','marketing_website','read_only','view_only','custom') NOT NULL DEFAULT 'admin',
  `permissions_json` longtext DEFAULT NULL,
  `hidden_tabs_json` longtext DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `verification_code_hash` varchar(255) DEFAULT NULL,
  `verification_expires_at` datetime DEFAULT NULL,
  `verification_attempts` int(11) NOT NULL DEFAULT 0,
  `verification_last_sent_at` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `two_factor_code_hash` varchar(255) DEFAULT NULL,
  `two_factor_expires_at` datetime DEFAULT NULL,
  `two_factor_attempts` int(11) NOT NULL DEFAULT 0,
  `two_factor_last_sent_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `invited_by_admin_id` int(11) DEFAULT NULL,
  `suspended_until` datetime DEFAULT NULL,
  `suspended_reason` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `full_name`, `email`, `password_hash`, `role`, `is_active`, `email_verified_at`, `created_at`, `updated_at`) VALUES
(1, 'Commerza Admin', 'commerza.ahmer@gmail.com', '$2y$12$OwYPaS2VbAP2xrbqV.eJA.rh0tO9mKk6qAfoVzioOhRdwoKDprbAS', 'admin', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14', '2026-04-05 20:21:14');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `session_id` varchar(128) DEFAULT NULL COMMENT 'Guest session identifier',
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL for guest carts',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compare_items`
--

CREATE TABLE `compare_items` (
  `id` int(11) NOT NULL,
  `compare_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compare_list`
--

CREATE TABLE `compare_list` (
  `id` int(11) NOT NULL,
  `session_id` varchar(128) DEFAULT NULL COMMENT 'Guest session identifier',
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL for guests',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_blacklist`
--

CREATE TABLE `customer_blacklist` (
  `id` int(11) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by_admin_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_replied` tinyint(1) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `discount_type` enum('percent','fixed') NOT NULL DEFAULT 'fixed',
  `discount_value` decimal(10,2) NOT NULL,
  `min_order` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `per_user_limit` int(11) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupon_redemptions`
--

CREATE TABLE `coupon_redemptions` (
  `id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_manual_recipients`
--

CREATE TABLE `email_manual_recipients` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `added_by` int(11) DEFAULT NULL COMMENT 'Admin who added this recipient',
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_outbox`
--

CREATE TABLE `email_outbox` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL COMMENT 'Which admin sent it',
  `template_id` int(11) DEFAULT NULL COMMENT 'Template used (NULL = custom)',
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `recipient_count` int(11) NOT NULL DEFAULT 0,
  `source` varchar(60) DEFAULT 'all' COMMENT 'all | subscribers | customers | manual',
  `status` enum('queued','sent','failed') NOT NULL DEFAULT 'sent',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_suppressed`
--

CREATE TABLE `email_suppressed` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `reason` enum('unsubscribed','bounced','complained','manual') NOT NULL DEFAULT 'unsubscribed',
  `suppressed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'Template display name',
  `subject` varchar(255) NOT NULL COMMENT 'Email subject line',
  `body` text NOT NULL COMMENT 'Plain-text or HTML body',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'System default (non-deletable)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `name`, `subject`, `body`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 'Welcome to Commerza Circle', 'Welcome to the Commerza Circle', 'Hi there,\r\n\r\nThanks for joining the Commerza Circle. You will get early access to launches, exclusive offers, and collector stories.\r\n\r\n- The Commerza Team', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(2, 'New Arrivals Drop', 'New arrivals just landed', 'Hello,\r\n\r\nOur latest watches are live now. Explore the newest drops and find your next statement piece.\r\n\r\nShop now: https://commerza.com\r\n\r\n- The Commerza Team', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(3, 'Limited Time Offer', 'Limited-time offer inside', 'Hi,\r\n\r\nFor a limited time, enjoy exclusive pricing on selected collections. The offer ends soon, so do not miss out.\r\n\r\n- The Commerza Team', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(4, 'Back in Stock Alert', 'Back in stock: your favorites', 'Hello,\r\n\r\nGood news! Popular watches are back in stock. Quantities are limited, so grab yours soon.\r\n\r\n- The Commerza Team', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(5, 'Order Update', 'Your Commerza order update', 'Hi,\r\n\r\nWe wanted to share a quick update about your order. If you have any questions, reply to this email and our team will help.\r\n\r\n- The Commerza Team', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(6, 'Shipping Delay Notice', 'Shipping update from Commerza', 'Hello,\r\n\r\nWe are experiencing a short shipping delay due to high demand. Your order is still on the way, and we will share tracking soon.\r\n\r\n- The Commerza Team', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(7, 'VIP Early Access', 'VIP early access is live', 'Hi,\r\n\r\nAs a Commerza subscriber, you get early access to our newest collection. Take a first look before the public launch.\r\n\r\n- The Commerza Team', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(8, 'Holiday Gift Guide', 'Holiday gift picks from Commerza', 'Hello,\r\n\r\nNeed a gift that stands out? Our holiday guide highlights the best watches for every style and budget.\r\n\r\n- The Commerza Team', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(9, 'Feedback Request', 'We would love your feedback', 'Hi,\r\n\r\nYour feedback helps us improve. If you have a moment, let us know what you love and what we can do better.\r\n\r\n- The Commerza Team', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(10, 'Monthly Newsletter', 'Your Commerza monthly roundup', 'Hello,\r\n\r\nHere is your monthly roundup with new releases, staff picks, and limited offers.\r\n\r\n- The Commerza Team', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(11, 'Support Reply', 'Re: Support request', 'Hi,\r\n\r\nThanks for reaching out to Commerza support. We are looking into this and will update you shortly.\r\n\r\nIf you can share your order ID and any extra details, we can help faster.\r\n\r\n- Commerza Support', 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14');

-- --------------------------------------------------------

--
-- Table structure for table `engagement_reminders`
--

CREATE TABLE `engagement_reminders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `reminder_type` enum('cart','wishlist') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faq`
--

CREATE TABLE `faq` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faq`
--

INSERT INTO `faq` (`id`, `category_id`, `question`, `answer`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'How do I place an order?', 'Browse our collection, add your chosen timepiece to the cart, and proceed to checkout. Enter shipping details, choose Cash on Delivery or Stripe card payment, and place your order.', 1, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(2, 1, 'What payment methods do you accept?', 'Commerza currently accepts Cash on Delivery (COD) and secure Stripe card payments.', 2, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(3, 1, 'Can I modify or cancel my order after placing it?', 'Orders can be modified or cancelled within 2 hours of placement. Contact our support team immediately at support@commerza.com and we will do our best to accommodate your request.', 3, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(4, 1, 'Is my payment information secure?', 'Yes. Checkout requests are protected using industry-standard SSL/TLS, CSRF protection, and verification controls for COD order placement.', 4, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(5, 2, 'How long does standard shipping take?', 'Standard shipping typically takes 5-7 business days. Express (2-3 days) and Overnight (next business day) options are available at checkout.', 1, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(6, 2, 'Do you offer free shipping?', 'Yes - orders over Rs. 500 qualify for free standard nationwide shipping automatically. No coupon code required.', 2, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(7, 2, 'Do you ship internationally?', 'Commerza ships to most countries worldwide. Shipping rates and estimated delivery times are shown at checkout based on your destination.', 3, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(8, 2, 'How can I track my order?', 'Once your order has shipped you will receive a tracking number by email. You can also visit our Order Tracking page and enter your order number and email address.', 4, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(9, 3, 'What is your return policy?', 'We offer hassle-free returns within 30 days of delivery. Items must be unworn, in original packaging, and accompanied by proof of purchase. Visit our Returns page to submit a request.', 1, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(10, 3, 'How long does a refund take to process?', 'Once we receive and inspect the returned item, refunds are processed within 5-7 business days to your original payment method.', 2, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(11, 3, 'What warranty do Commerza watches carry?', 'All timepieces come with a minimum 2-year manufacturer warranty against defects in materials and workmanship. Some collections carry extended 5-year coverage - see individual product pages for details.', 3, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(12, 3, 'How do I file a warranty claim?', 'Visit our Warranty page, complete the claim form with your order number, product details, and a description of the issue. Our service team will respond within 2 business days.', 4, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(13, 4, 'Are all watches sold on Commerza authentic?', 'Absolutely. Every timepiece sold on Commerza is 100% authentic and sourced directly from authorised distributors or the brands themselves. We provide certificates of authenticity with every order.', 1, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(14, 4, 'Do products come with original box and papers?', 'Yes. All watches are delivered in their original manufacturer packaging complete with warranty cards, instruction manuals, and any included accessories.', 2, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(15, 4, 'Can I compare multiple watches before buying?', 'Yes. Use the Compare feature on any product page to place up to four timepieces side by side and evaluate their specifications.', 3, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(16, 4, 'Do you sell limited-edition pieces?', 'Yes. We carry a selection of limited-edition and collector pieces. These are listed with their edition numbers and available stock. We recommend signing up to our newsletter for early-access alerts.', 4, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(17, 5, 'How do I create a Commerza account?', 'Click Sign Up in the top navigation bar and fill in your name, email, phone number, and a secure password. Your account gives you order history, wishlist, saved addresses, and faster checkout.', 1, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(18, 5, 'What if I forget my password?', 'Click Forgot Password on the login page, enter your registered email address, and we will send you a password reset link valid for 1 hour.', 2, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(19, 5, 'How do I update my account details?', 'Log in and go to My Account. From there you can update your name, email, phone number, shipping addresses, and profile picture.', 3, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(20, 5, 'How is my personal data used?', 'Your data is used solely to process orders, personalise your experience, and communicate with you about your purchases. We never sell your information to third parties. See our Privacy Policy for full details.', 4, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14');

-- --------------------------------------------------------

--
-- Table structure for table `faq_categories`
--

CREATE TABLE `faq_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `icon` varchar(80) DEFAULT NULL COMMENT 'Icon class or URL',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faq_categories`
--

INSERT INTO `faq_categories` (`id`, `name`, `icon`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Orders & Payments', NULL, 1, 1, '2026-04-05 20:21:13', '2026-04-05 20:21:13'),
(2, 'Shipping & Delivery', NULL, 2, 1, '2026-04-05 20:21:13', '2026-04-05 20:21:13'),
(3, 'Returns & Warranty', NULL, 3, 1, '2026-04-05 20:21:13', '2026-04-05 20:21:13'),
(4, 'Product & Authenticity', NULL, 4, 1, '2026-04-05 20:21:13', '2026-04-05 20:21:13'),
(5, 'Account & Security', NULL, 5, 1, '2026-04-05 20:21:13', '2026-04-05 20:21:13');

-- --------------------------------------------------------

--
-- Table structure for table `live_product_viewers`
--

CREATE TABLE `live_product_viewers` (
  `id` int(11) NOT NULL,
  `session_key` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL for guests',
  `product_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `live_product_viewers`
--

INSERT INTO `live_product_viewers` (`id`, `session_key`, `user_id`, `product_id`, `ip_address`, `first_seen_at`, `last_seen_at`) VALUES
(1, 'msp46jp3a0cu3dp7aesf8dv4fs', NULL, 10, '::1', '2026-04-05 20:27:45', '2026-04-06 02:34:27'),
(3, 'msp46jp3a0cu3dp7aesf8dv4fs', NULL, 4, '::1', '2026-04-05 20:55:38', '2026-04-06 01:55:38'),
(4, 'msp46jp3a0cu3dp7aesf8dv4fs', NULL, 8, '::1', '2026-04-05 21:04:22', '2026-04-06 02:05:53');

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `source` varchar(50) DEFAULT NULL COMMENT 'modal | inline | admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `unsubscribed_at` datetime DEFAULT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL COMMENT 'NULL = broadcast to all admins',
  `type` varchar(60) NOT NULL COMMENT 'new_order | new_message | low_stock | new_user',
  `title` varchar(150) NOT NULL,
  `body` text DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL COMMENT 'Deep link inside admin panel',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(30) NOT NULL COMMENT 'Human-readable, e.g. #ORD-XXXX',
  `user_id` int(11) DEFAULT NULL COMMENT 'NULL if guest checkout',
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(150) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `address` text NOT NULL COMMENT 'Freeform shipping address from checkout textarea',
  `subtotal` decimal(10,2) NOT NULL,
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `grand_total` decimal(10,2) NOT NULL,
  `status` enum('Pending','Confirmed','Processing','Shipped','Delivered','Cancelled','Refunded') NOT NULL DEFAULT 'Pending',
  `payment_status` enum('unpaid','paid','partially_refunded','refunded') NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(50) NOT NULL DEFAULT 'Cash on Delivery (COD)' COMMENT 'Supports COD and Stripe labels',
  `notes` text DEFAULT NULL COMMENT 'Customer order notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL COMMENT 'NULL if product was later deleted',
  `product_name` varchar(255) NOT NULL COMMENT 'Name locked at order time',
  `product_img` varchar(255) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Price locked at order time',
  `quantity` int(11) NOT NULL DEFAULT 1,
  `line_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `page_content`
--

CREATE TABLE `page_content` (
  `id` int(11) NOT NULL,
  `page` varchar(100) NOT NULL COMMENT 'Filename, e.g. about.php',
  `section_key` varchar(100) NOT NULL COMMENT 'Content block identifier',
  `content` text DEFAULT NULL COMMENT 'HTML or plain-text content',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `page_meta`
--

CREATE TABLE `page_meta` (
  `id` int(11) NOT NULL,
  `page` varchar(100) NOT NULL COMMENT 'Filename, e.g. index.php or about.php',
  `meta_title` varchar(150) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `og_title` varchar(150) DEFAULT NULL,
  `og_description` varchar(255) DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `json_ld` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `page_meta`
--

INSERT INTO `page_meta` (`id`, `page`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(1, 'index.php', 'Commerza | Full-Stack Ecommerce', 'Commerza brings you premium automatic watches - crafted with elegant leather, gold dials, and modern design.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(2, 'about.php', 'About Us | Commerza', 'Learn about Commerza\'s story, vision, and team.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(3, 'contact.php', 'Contact Us | Commerza', 'Get in touch with the Commerza team.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(4, 'shop-category-a.php', 'Premium Watches | Commerza', 'Explore mechanical and smart timepieces.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(5, 'shop-category-b.php', 'Lifestyle Watches | Commerza', 'Browse minimalist and sports timepieces.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(6, 'cart.php', 'Your Cart | Commerza', 'Review and complete your Commerza order.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(7, 'wishlist.php', 'Wishlist | Commerza', 'Your saved watches on Commerza.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(8, 'order-tracking.php', 'Track Your Order | Commerza', 'Track the status of your Commerza order.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(9, 'returns.php', 'Returns & Refunds | Commerza', 'Hassle-free returns within 30 days.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(10, 'warranty.php', 'Warranty | Commerza', 'Commerza warranty information and claims.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(11, 'faq.php', 'FAQ | Commerza', 'Frequently asked questions about Commerza.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(12, 'shipping.php', 'Shipping | Commerza', 'Shipping rates and delivery information.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(13, 'account.php', 'My Account | Commerza', 'Manage your Commerza profile and orders.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(14, 'products.php', 'All Products | Commerza', 'Browse the full Commerza watch collection.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(15, 'compare.php', 'Compare Watches | Commerza', 'Compare luxury timepieces side by side.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(16, 'login.php', 'Login | Commerza', 'Sign in to your Commerza account.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(17, 'signup.php', 'Create Account | Commerza', 'Join Commerza and start your luxury journey.', '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(18, 'forgot-password.php', 'Forgot Password | Commerza', 'Reset your Commerza account password.', '2026-04-05 20:21:14', '2026-04-05 20:21:14');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sectionId` varchar(64) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(120) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `salePrice` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `movement` enum('auto','manual','quartz','smart') DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL COMMENT 'Optional product video',
  `product_code` varchar(40) DEFAULT NULL COMMENT 'Unique product support code',
  `warranty_info` varchar(120) NOT NULL DEFAULT '12-month seller warranty',
  `dispatch_info` varchar(120) NOT NULL DEFAULT 'Dispatch in 24-48 hours',
  `returns_info` varchar(140) NOT NULL DEFAULT '7-day return policy (unused items)',
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sectionId`, `name`, `slug`, `description`, `image`, `price`, `salePrice`, `stock`, `movement`, `video_url`, `product_code`, `warranty_info`, `dispatch_info`, `returns_info`, `created_at`, `updated_at`) VALUES
(1, 'featured-collection', 'Tambour Bushido Automata, Manual, 46.8 mm, Rose Gold', 'tambour-bushido-automata-manual-46-8-mm-rose-gold', 'Taking its name from the code of conduct of the Japanese Samurai, the Tambour Bushido Automata is an homage to strength, discipline and artistic expression.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358941/commerza/products/images/featured/white-gold-steel.png', 8500.00, 6200.00, 45, 'auto', NULL, 'CMRZ-00001', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 21:57:58'),
(2, 'featured-collection', 'Louis Vuitton Escale Twin Zone, Automatic, 41mm, Platinum and diamonds', 'louis-vuitton-escale-twin-zone-automatic-41mm-platinum-and-diamonds', 'The Louis Vuitton Escale Twin Zone 41mm watch in platinum and diamonds is a precious embodiment of the Art of Travel. Its unique dual time display.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358915/commerza/products/images/featured/black-white-gold.png', 9200.00, 7100.00, 32, 'auto', NULL, 'CMRZ-00002', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 21:57:58'),
(3, 'featured-collection', 'Louis Vuitton Escale, Automatic, 39mm, Rose Gold', 'louis-vuitton-escale-automatic-39mm-rose-gold', 'The Escale Automatic 39 mm in rose gold features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks. Enriched by a textured dial, this time-only timepiece is a celebration of technical precision.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358936/commerza/products/images/featured/skeleton-gold-steel.png', 11500.00, 8900.00, 28, 'auto', NULL, 'CMRZ-00003', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 21:57:58'),
(4, 'featured-collection', 'Louis Vuitton Escale, Automatic, 40mm, Yellow gold and tiger\'s eye', 'louis-vuitton-escale-automatic-40mm-yellow-gold-and-tiger-s-eye', 'Limited to 30 pieces, the Escale Automatic 40mm watch in 750/1000 yellow gold and tiger\'s eye is a singular expression of rigor, innovation, and elegance.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358919/commerza/products/images/featured/brown-gold-dial.png', 12800.00, 9600.00, 38, 'auto', NULL, 'CMRZ-00004', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 21:57:59'),
(5, 'featured-collection', 'Louis Vuitton Escale, Automatic, 39mm, Platinum', 'louis-vuitton-escale-automatic-39mm-platinum', 'Limited to 50 pieces, this Escale Automatic 39 mm in platinum features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358904/commerza/products/images/featured/black-gold-dial.png', 10200.00, 7800.00, 52, 'auto', NULL, 'CMRZ-00005', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 21:57:59'),
(6, 'featured-collection', 'Louis Vuitton Escale Voyage, Automatic, 39mm, Rose Gold', 'louis-vuitton-escale-voyage-automatic-39mm-rose-gold', 'Limited to 100 pieces and tribute to Gaston-Louis Vuitton, this Escale Automatic 39 mm in rose gold features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358924/commerza/products/images/featured/brown-premium-watch.png', 13500.00, 10200.00, 25, 'auto', NULL, 'CMRZ-00006', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 21:57:59'),
(7, 'featured-collection', 'Louis Vuitton Escale Horizon, Automatic, 39mm, Platinum', 'louis-vuitton-escale-horizon-automatic-39mm-platinum', 'The Escale Automatic 39 mm in platinum features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358933/commerza/products/images/featured/premium-black-gold.png', 16500.00, 12400.00, 18, 'auto', NULL, 'CMRZ-00007', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 21:57:59'),
(8, 'featured-collection', 'Louis Vuitton Escale Worldtime, Automatic, 40mm, Platinum', 'louis-vuitton-escale-worldtime-automatic-40mm-platinum', 'The Louis Vuitton Escale Worldtime 40mm in platinum stands as the foremost Louis Vuitton timepiece, embodying the House\'s art of travel.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358910/commerza/products/images/featured/black-minimalist.png', 11000.00, 8300.00, 41, 'auto', NULL, 'CMRZ-00008', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 21:57:59'),
(9, 'featured-collection', 'Louis Vuitton Escale Twin Zone, Automatic, 40mm, Rose Gold', 'louis-vuitton-escale-twin-zone-automatic-40mm-rose-gold', 'The Louis Vuitton Escale Twin Zone 40mm watch in 750/1000 rose gold is an elegant interpretation of the Art of Travel. Powered by the in-house automatic LFTVO15.01 movement.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358891/commerza/products/images/featured/black-feather.png', 18500.00, 14200.00, 22, 'auto', NULL, 'CMRZ-00009', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 21:57:59'),
(10, 'featured-collection', 'Louis Vuitton Escale, Automatic, 40mm, Platinum and malachite', 'louis-vuitton-escale-automatic-40mm-platinum-and-malachite', 'Limited to 30 pieces, the Escale Automatic 40mm watch in platinum and malachite is an irrepressible demonstration of hard stones craftsmanship.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358928/commerza/products/images/featured/luxury-white-gold.png', 21500.00, 16800.00, 15, 'auto', NULL, 'CMRZ-00010', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 21:57:59'),
(11, 'automatic-vault', 'Tambour Bushido Automata, Manual, 46.8 mm, Rose Gold', 'product-11', 'Taking its name from the code of conduct of the Japanese Samurai, the Tambour Bushido Automata is an homage to strength, discipline and artistic expression.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358941/commerza/products/images/featured/white-gold-steel.png', 8500.00, 6200.00, 45, 'auto', NULL, 'CMRZ-00011', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(12, 'automatic-vault', 'Louis Vuitton Escale Twin Zone, Automatic, 41mm, Platinum and diamonds', 'product-12', 'The Louis Vuitton Escale Twin Zone 41mm watch in platinum and diamonds is a precious embodiment of the Art of Travel. Its unique dual time display.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358915/commerza/products/images/featured/black-white-gold.png', 9200.00, 7100.00, 32, 'auto', NULL, 'CMRZ-00012', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(13, 'automatic-vault', 'Louis Vuitton Escale, Automatic, 39mm, Rose Gold', 'product-13', 'The Escale Automatic 39 mm in rose gold features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks. Enriched by a textured dial, this time-only timepiece is a celebration of technical precision.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358936/commerza/products/images/featured/skeleton-gold-steel.png', 11500.00, 8900.00, 28, 'auto', NULL, 'CMRZ-00013', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(14, 'automatic-vault', 'Louis Vuitton Escale, Automatic, 40mm, Yellow gold and tiger\'s eye', 'product-14', 'Limited to 30 pieces, the Escale Automatic 40mm watch in 750/1000 yellow gold and tiger\'s eye is a singular expression of rigor, innovation, and elegance.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358919/commerza/products/images/featured/brown-gold-dial.png', 12800.00, 9600.00, 38, 'auto', NULL, 'CMRZ-00014', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(15, 'automatic-vault', 'Louis Vuitton Escale, Automatic, 39mm, Platinum', 'product-15', 'Limited to 50 pieces, this Escale Automatic 39 mm in platinum features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358904/commerza/products/images/featured/black-gold-dial.png', 10200.00, 7800.00, 52, 'auto', NULL, 'CMRZ-00015', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(16, 'automatic-vault', 'Louis Vuitton Escale Voyage, Automatic, 39mm, Rose Gold', 'product-16', 'Limited to 100 pieces and tribute to Gaston-Louis Vuitton, this Escale Automatic 39 mm in rose gold features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358924/commerza/products/images/featured/brown-premium-watch.png', 13500.00, 10200.00, 25, 'auto', NULL, 'CMRZ-00016', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(17, 'automatic-vault', 'Louis Vuitton Escale Atelier, Automatic, 39mm, Platinum', 'product-17', 'The Escale Automatic 39 mm in platinum features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358933/commerza/products/images/featured/premium-black-gold.png', 16500.00, 12400.00, 18, 'auto', NULL, 'CMRZ-00017', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(18, 'automatic-vault', 'Louis Vuitton Escale Worldtime, Automatic, 40mm, Platinum', 'product-18', 'The Louis Vuitton Escale Worldtime 40mm in platinum stands as the foremost Louis Vuitton timepiece, embodying the House\'s art of travel.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358910/commerza/products/images/featured/black-minimalist.png', 11000.00, 8300.00, 41, 'auto', NULL, 'CMRZ-00018', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(19, 'smart-evolution', 'Visionary Smartwatch', 'product-19', 'The ZERO Visionary Smartwatch combines cutting-edge technology with a sleek modern design, giving you the ultimate balance of style, performance, and wellness.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358986/commerza/products/images/smart/Fajr---Hybrid-Digital-Rectangle-Watch.png', 7000.00, 5500.00, 35, 'smart', NULL, 'CMRZ-00019', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(20, 'smart-evolution', 'Crown Smartwatch', 'product-20', 'Experience technology that\'s beyond luxury with the all-new Crown Smartwatch packed with features that are never before seen in the industry with a Vegan Leather Strap.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358990/commerza/products/images/smart/Fajr---Hybrid-Digital-Round-Watch.png', 7500.00, 6500.00, 29, 'smart', NULL, 'CMRZ-00020', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(21, 'smart-evolution', 'Regal AI Smartwatch', 'product-21', 'The Regal Smartwatch is an elegant fusion of style and innovation. Its sleek, modern design and durable construction make it a versatile accessory for any occasion.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358994/commerza/products/images/smart/Hango-X---Skmei-Digital-Watch---Black.png', 11500.00, 8900.00, 24, 'smart', NULL, 'CMRZ-00021', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(22, 'smart-evolution', 'Vision Smartwatch', 'product-22', 'Experience the perfect blend of innovation and style with the Vision Smartwatch. Designed for your busy lifestyle, this smartwatch keeps you connected and efficient throughout the day.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358997/commerza/products/images/smart/I-8-Pro-Max-Smart-Watch.png', 14200.00, 10800.00, 19, 'smart', NULL, 'CMRZ-00022', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(23, 'smart-evolution', 'Elite Smartwatch', 'product-23', 'The Elite Zero Smartwatch combines high-tech features with a stylish design. Perfect for those who want both performance and elegance, this smartwatch helps you stay connected, track your health.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776359002/commerza/products/images/smart/S-8-Pro-Max-Smart-Watch.png', 18500.00, 13800.00, 14, 'smart', NULL, 'CMRZ-00023', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(24, 'smart-evolution', 'Vogue Smartwatch', 'product-24', 'The Vogue Smartwatch from ZeroLifestyle is a perfect blend of style, functionality, and durability. Designed for fitness enthusiasts and busy professionals alike, this smartwatch offers a plethora of features.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776359006/commerza/products/images/smart/T800-Ultra-2-49-mm.png', 16800.00, 12600.00, 17, 'smart', NULL, 'CMRZ-00024', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(25, 'smart-evolution', 'Luna Pro', 'product-25', 'The Luna Pro Smartwatch blends modern design with powerful health and lifestyle features, delivering a premium experience for everyday life. With advanced sensors, fitness tracking, and a customizable interface.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776359010/commerza/products/images/smart/Ultra-8-Smart-Watch.png', 20500.00, 15500.00, 12, 'smart', NULL, 'CMRZ-00025', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(26, 'signature-collection', 'TAG Heuer Aquaracer Professional 200 Date', 'product-26', 'Elegance is in every detail of this TAG Heuer Aquaracer, Blending the strength of stainless steel plated with 18K 3N yellow gold.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358945/commerza/products/images/minimal/DENIM-3---The-Minimalist-Watch.png', 7000.00, 5500.00, 48, 'quartz', NULL, 'CMRZ-00026', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(27, 'signature-collection', 'TAG Heuer Aquaracer Professional 300 Date Marine Blue', 'product-27', 'The TAG Heuer Aquaracer Professional 300 Date embodies precision, elegance, and performance, crafted for modern explorers who embrace the depths of the ocean.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358949/commerza/products/images/minimal/DI-STAR---CHAIN-WATCH-WITH-DATE-TWO-TONE.png', 7500.00, 6500.00, 36, 'quartz', NULL, 'CMRZ-00027', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(28, 'signature-collection', 'TAG Heuer Aquaracer Professional 300 Date Coral Diamond', 'product-28', 'Take a dip in the intensely refreshing waters of lagoons and coral reefs that inspire this TAG Heuer Aquaracer. The vivid, VS diamond-studded 1.40mm (0.078 ct) dial stands out and the professional, life-proof case.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358954/commerza/products/images/minimal/Fued---Tomi-Face-Gear-Dual-Leather-Straps-Watch.png', 9000.00, 8000.00, 31, 'quartz', NULL, 'CMRZ-00028', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(29, 'signature-collection', 'TAG Heuer Aquaracer Professional 300 GMT', 'product-29', 'The sea is your playground when you wear this extremely versatile TAG Heuer Aquaracer Professional 300 on your wrist. Featuring the powerful Calibre TH31-03 movement.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358958/commerza/products/images/minimal/Galcia---Round-Minimalist-Watch-WITH-DATE.png', 10000.00, 9000.00, 27, 'quartz', NULL, 'CMRZ-00029', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(30, 'signature-collection', 'TAG Heuer Aquaracer Professional 300 Date Pastel Green', 'product-30', 'With a luminous pastel green dial and rugged build, the TAG Heuer Aquaracer Professional 300 Date is ready for deep dives and sunlit shores alike.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358962/commerza/products/images/minimal/Square-Tom---Minimalist-Watch.png', 11200.00, 8500.00, 33, 'quartz', NULL, 'CMRZ-00030', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(31, 'signature-collection', 'TAG Heuer Aquaracer Professional 300 Date Reef Blue', 'product-31', 'The TAG Heuer Aquaracer Professional 300 Date is inspired by the vivid colors of coral reefs found in the depths of the ocean.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358966/commerza/products/images/minimal/TOMI-T-105---Tomi-Face-Gear-Black-Dial.png', 13200.00, 9900.00, 20, 'quartz', NULL, 'CMRZ-00031', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(32, 'signature-collection', 'TAG Heuer Aquaracer Professional 200 Date', 'product-32', 'Embrace the warm colours of autumn with this 30mm TAG Heuer Aquaracer and its intense ruby-red mother-of-pearl dial.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358969/commerza/products/images/minimal/TOMI--Round-Minimalist-Watch-WITH-DATE.png', 15800.00, 11800.00, 16, 'quartz', NULL, 'CMRZ-00032', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(33, 'signature-collection', 'TAG Heuer Aquaracer Professional 200 Solargraph', 'product-33', 'Immerse yourself in the celestial allure of the northern lights with this TAG Heuer Aquaracer, equipped with the advanced Solargraph Calibre TH50-01.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358982/commerza/products/images/minimal/X---Round-Minimalist-Watch-Half-Cut.png', 10800.00, 8200.00, 39, 'quartz', NULL, 'CMRZ-00033', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(34, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Tourbillon Extreme Sport', 'product-34', 'Embrace the fusion of luxury and performance with the TAG Heuer Carrera Chronograph Tourbillon, a striking embodiment of motorsport passion.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776359013/commerza/products/images/sports/Aura---Never-Stop-Minimal-Watch-with-Date--N905.png', 7000.00, 5500.00, 44, 'quartz', NULL, 'CMRZ-00034', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(35, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Extreme Sport', 'product-35', 'Immerse yourself in the essence of motorsport with this striking 44mm TAG Heuer Carrera Chronograph. Dressed entirely in black, it strikes a bold statement of elegance and power.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776359019/commerza/products/images/sports/Chrona---Never-Stop-Minimal-Watch---N928.png', 7500.00, 6500.00, 34, 'quartz', NULL, 'CMRZ-00035', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(36, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Extreme Sport Carbon Blue', 'product-36', 'Elevate your wrist game with the TAG Heuer Carrera Chronograph, a testament to the thrill of motorsport. With its bold design and blue accents.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776359023/commerza/products/images/sports/Dagahra--Never-Stop-Casual-sports-Watch-with-date---N911.png', 9000.00, 8000.00, 26, 'quartz', NULL, 'CMRZ-00036', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(37, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Extreme Sport Twin-Time', 'product-37', 'The TAG Heuer Carrera Chronograph Extreme Sport Twin-Time redefines performance with a motorsport-inspired design.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776359028/commerza/products/images/sports/Newmoon---Never-Stop-Chronograph-sports-Watch-with-date---N902.png', 10000.00, 9000.00, 23, 'quartz', NULL, 'CMRZ-00037', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(38, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Extreme Sport Skeleton Rose Gold', 'product-38', 'Audacity meets elegance in this one-of-a-kind TAG Heuer Carrera Chronograph. With its skeleton design and rose gold accents.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776359032/commerza/products/images/sports/RECDIS---Skmei-3-Time-Sports-Watch-With-Stainless-Steel.png', 17800.00, 13400.00, 11, 'quartz', NULL, 'CMRZ-00038', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(39, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Tourbillon Extreme Sport I F1® 75th Anniversary Limited Edition', 'product-39', 'Created to celebrate 75 years of Formula 1®, the TAG Heuer Carrera Chronograph Tourbillon Extreme Sport I F1® 75th Anniversary Limited Edition is limited to just 75 pieces', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776359036/commerza/products/images/sports/TOKDIS---Dual-Time-Sports-Watch-With-Stainless-Steel.png', 16200.00, 12200.00, 13, 'quartz', NULL, 'CMRZ-00039', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(40, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Tourbillon Extreme Sport TH-Carbonspring', 'product-40', 'Built to withstand intense performance conditions, the TAG Heuer Carrera Chronograph Tourbillon Extreme Sport TH-Carbonspring fuses high-tech materials.', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776359044/commerza/products/images/sports/Yraz---Never-Stop-Casual-sports-Watch-with-date.png', 19800.00, 15100.00, 9, 'quartz', NULL, 'CMRZ-00040', '12-month seller warranty', 'Dispatch in 24-48 hours', '7-day return policy (unused items)', '2026-04-05 20:21:12', '2026-04-05 20:21:12');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL,
  `review_text` varchar(500) NOT NULL,
  `is_verified_purchase` tinyint(1) NOT NULL DEFAULT 1,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `locked_at` datetime DEFAULT NULL,
  `locked_by_admin_id` int(11) DEFAULT NULL,
  `admin_note` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_review_images`
--

CREATE TABLE `product_review_images` (
  `id` int(11) NOT NULL,
  `review_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `image_size` int(11) NOT NULL DEFAULT 0,
  `sort_order` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_fake_reviews`
--

CREATE TABLE `product_fake_reviews` (
  `id` int(11) NOT NULL,
  `review_id` int(11) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `fake_user_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `review_text` varchar(500) NOT NULL,
  `reviewer_name` varchar(120) NOT NULL DEFAULT 'Customer',
  `reviewer_handle` varchar(80) DEFAULT NULL,
  `reviewer_visibility` enum('public','private') NOT NULL DEFAULT 'public',
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `locked_at` datetime DEFAULT NULL,
  `locked_by_admin_id` int(11) DEFAULT NULL,
  `admin_note` varchar(500) DEFAULT NULL,
  `generated_by_admin_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_trash`
--

CREATE TABLE `product_trash` (
  `id` bigint(20) NOT NULL,
  `product_id` int(11) NOT NULL,
  `section_id` varchar(64) DEFAULT NULL,
  `section_name` varchar(128) DEFAULT NULL,
  `section_page` varchar(64) DEFAULT NULL,
  `section_category` varchar(128) DEFAULT NULL,
  `section_subcategory` varchar(128) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(120) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `product_code` varchar(40) DEFAULT NULL,
  `warranty_info` varchar(120) DEFAULT NULL,
  `dispatch_info` varchar(120) DEFAULT NULL,
  `returns_info` varchar(140) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `movement` enum('auto','manual','quartz','smart') DEFAULT NULL,
  `original_created_at` datetime DEFAULT NULL,
  `original_updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `purge_after` datetime NOT NULL,
  `deleted_by_admin_id` int(11) DEFAULT NULL,
  `delete_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `scope` varchar(80) NOT NULL,
  `identifier` varchar(191) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL,
  `blocked_until` datetime DEFAULT NULL,
  `strikes` int(11) NOT NULL DEFAULT 0,
  `last_blocked_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `scope`, `identifier`, `ip_address`, `attempts`, `window_started_at`, `blocked_until`, `strikes`, `last_blocked_at`, `updated_at`) VALUES
(1, 'products_sections', 'sections_payload', '::1', 1, '2026-04-06 13:57:37', NULL, 0, NULL, '2026-04-06 11:57:37'),
(6, 'viewers_heartbeat', 'msp46jp3a0cu3dp7aesf8dv4fs', '::1', 1, '2026-04-05 23:34:27', NULL, 0, NULL, '2026-04-05 21:34:27'),
(7, 'reviews_list', 'product_10', '::1', 1, '2026-04-05 23:34:27', NULL, 0, NULL, '2026-04-05 21:34:27'),
(15, 'reviews_list', 'product_4', '::1', 1, '2026-04-05 22:55:39', NULL, 0, NULL, '2026-04-05 20:55:39'),
(20, 'reviews_list', 'product_8', '::1', 2, '2026-04-05 23:05:24', NULL, 0, NULL, '2026-04-05 21:05:53'),
(45, 'user_signup', 'syedahmershahofficial@gmail.com', '::1', 1, '2026-04-06 00:43:20', NULL, 0, NULL, '2026-04-05 22:43:20'),
(46, 'user_signup_resend', 'syedahmershahofficial@gmail.com', '::1', 2, '2026-04-06 00:44:33', NULL, 0, NULL, '2026-04-05 22:49:09');

-- --------------------------------------------------------

--
-- Table structure for table `refund_requests`
--

CREATE TABLE `refund_requests` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(500) DEFAULT NULL,
  `evidence_path` varchar(255) DEFAULT NULL,
  `evidence_name` varchar(255) DEFAULT NULL,
  `evidence_size` int(11) NOT NULL DEFAULT 0,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `sectionId` varchar(64) NOT NULL,
  `sectionName` varchar(128) NOT NULL,
  `category` varchar(128) DEFAULT NULL,
  `subcategory` varchar(128) DEFAULT NULL,
  `page` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `sectionId`, `sectionName`, `category`, `subcategory`, `page`, `created_at`, `updated_at`) VALUES
(1, 'automatic-vault', 'The Automatic Vault', 'Premium Watches', 'Mechanical & Smart Timepieces', 'shop-category-a.php', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(2, 'featured-collection', 'Featured Collection', 'Premium Watches & Accessories', 'Luxury Timepieces', 'index.php', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(3, 'signature-collection', 'The Signature Collection', 'Lifestyle & Utility Watches', 'Minimalist & Sports Timepieces', 'shop-category-b.php', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(4, 'smart-evolution', 'Smart Evolution Series', 'Premium Watches', 'Mechanical & Smart Timepieces', 'shop-category-a.php', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(5, 'sports-sales-division', 'The Sports & Sales Division', 'Lifestyle & Utility Watches', 'Minimalist & Sports Timepieces', 'shop-category-b.php', '2026-04-05 20:21:12', '2026-04-05 20:21:12');

-- --------------------------------------------------------

--
-- Table structure for table `security_events`
--

CREATE TABLE `security_events` (
  `id` bigint(20) NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'info',
  `actor_type` varchar(32) DEFAULT NULL,
  `actor_identifier` varchar(191) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_val` text DEFAULT NULL,
  `label` varchar(150) DEFAULT NULL COMMENT 'Human-readable label for the admin panel',
  `setting_group` varchar(64) DEFAULT 'general' COMMENT 'Group, e.g. general | social | seo | contact',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `setting_key`, `setting_val`, `label`, `setting_group`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'COMMERZA', 'Website Name', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(2, 'site_tagline', 'Luxury Timepieces. Unmatched Excellence.', 'Site Tagline', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(3, 'site_email', 'commerza.ahmer@gmail.com', 'Contact Email', 'contact', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(4, 'site_phone', '+92 314 8396293', 'Contact Phone', 'contact', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(5, 'site_address', 'Barrage Colony, HYD, PK', 'Business Address', 'contact', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(6, 'currency_code', 'PKR', 'Currency Code', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(7, 'currency_symbol', 'Rs.', 'Currency Symbol', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(8, 'timezone', 'Asia/Karachi', 'Timezone', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(9, 'logo_url', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358569/commerza/brand/logo/commerza_logo.png', 'Logo Image URL', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(10, 'favicon_url', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358562/commerza/brand/favicon/commerza-watches-icon.png', 'Favicon URL', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(11, 'meta_title', 'Commerza | Full-Stack Ecommerce', 'Default Meta Title', 'seo', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(12, 'meta_description', 'Commerza brings you premium automatic watches—crafted with elegant leather, gold dials, and modern design.', 'Default Meta Description', 'seo', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(13, 'maintenance_mode', '0', 'Maintenance Mode (0/1)', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(14, 'free_shipping_over', '500', 'Free Shipping Threshold', 'shipping', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(15, 'tax_rate', '0.08', 'Tax Rate (decimal)', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(16, 'instagram_url', 'https://www.instagram.com/commerza.ahmer', 'Instagram URL', 'social', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(17, 'facebook_url', 'https://www.facebook.com/commerza.ahmer', 'Facebook URL', 'social', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(18, 'twitter_url', 'https://x.com/commerza_ahmer', 'Twitter / X URL', 'social', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(19, 'youtube_url', '', 'YouTube URL', 'social', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(20, 'tiktok_url', '', 'TikTok URL', 'social', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(21, 'footer_text', '© 2026 Commerza. All rights reserved.', 'Footer Copyright Text', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(22, 'ticker_enabled', '1', 'Enable Ticker (0/1)', 'general', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(23, 'admin_reset_key', 'CHANGE-ME-IMMEDIATELY', 'Admin Reset Key', 'security', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(24, 'live_viewers_mode', 'real', 'Live Viewers Mode', 'analytics', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(25, 'live_viewers_fake_min', '120', 'Live Viewers Fake Min', 'analytics', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(26, 'live_viewers_fake_max', '165', 'Live Viewers Fake Max', 'analytics', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(27, 'live_viewers_window_seconds', '180', 'Live Viewers Window Seconds', 'analytics', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(28, 'google_oauth_client_id', '', 'Google OAuth Client ID', 'integrations', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(29, 'google_oauth_client_secret', '', 'Google OAuth Client Secret', 'integrations', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(30, 'google_oauth_redirect_uri', 'http://localhost/commerza/oauth.php?provider=google', 'Google OAuth Redirect URI', 'integrations', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(31, 'facebook_oauth_client_id', '', 'Facebook OAuth Client ID', 'integrations', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(32, 'facebook_oauth_client_secret', '', 'Facebook OAuth Client Secret', 'integrations', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(33, 'facebook_oauth_redirect_uri', 'http://localhost/commerza/oauth.php?provider=facebook', 'Facebook OAuth Redirect URI', 'integrations', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(34, 'checkout_payment_mode', 'cod_stripe', 'Checkout Payment Mode', 'integrations', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(35, 'cod_otp_threshold', '15000', 'COD OTP Threshold (PKR)', 'security', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(36, 'captcha_enabled', '0', 'Enable CAPTCHA (0/1)', 'security', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(37, 'captcha_provider', 'turnstile', 'CAPTCHA Provider', 'security', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(38, 'turnstile_site_key', '', 'Cloudflare Turnstile Site Key', 'security', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(39, 'turnstile_secret_key', '', 'Cloudflare Turnstile Secret Key', 'security', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(40, 'recaptcha_site_key', '', 'Google reCAPTCHA Site Key', 'security', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(41, 'recaptcha_secret_key', '', 'Google reCAPTCHA Secret Key', 'security', '2026-04-05 20:21:12', '2026-04-05 20:21:12'),
(42, 'account_blacklist_notice_visible', '1', 'Show account blacklist notice', 'security', '2026-04-05 20:21:12', '2026-04-05 20:21:12');

-- --------------------------------------------------------

--
-- Table structure for table `slider`
--

CREATE TABLE `slider` (
  `id` int(11) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL COMMENT 'Image alt text for SEO/accessibility',
  `video_url` varchar(255) DEFAULT NULL COMMENT 'Optional background video',
  `cta_text` varchar(80) DEFAULT NULL COMMENT 'Call-to-action button label',
  `cta_url` varchar(255) DEFAULT NULL COMMENT 'Call-to-action button link',
  `cta_text_2` varchar(80) DEFAULT NULL COMMENT 'Secondary CTA label',
  `cta_url_2` varchar(255) DEFAULT NULL COMMENT 'Secondary CTA link',
  `overlay_opacity` decimal(3,2) DEFAULT 0.40 COMMENT '0.00 - 1.00 overlay darkness',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `slider`
--

INSERT INTO `slider` (`id`, `title`, `subtitle`, `description`, `image_url`, `alt_text`, `video_url`, `cta_text`, `cta_url`, `cta_text_2`, `cta_url_2`, `overlay_opacity`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Chronograph Precision', 'Premium Collection', 'Engineered movements with dual finish cases', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358748/commerza/slider/images/watch-banner-chronograph.jpg', 'luxury chronograph watch banner premium collection', NULL, 'Explore Now', 'shop-category-a.php', NULL, NULL, 0.40, 1, 1, '2026-04-05 20:21:13', '2026-04-05 20:21:13'),
(2, 'Every Style, One Place', 'Complete Series', 'From minimalist to bold statement pieces', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358753/commerza/slider/images/watch-banner-collection.jpg', 'complete watch collection showcase all styles', NULL, 'View Collection', 'shop-category-b.php', NULL, NULL, 0.40, 2, 1, '2026-04-05 20:21:13', '2026-04-05 20:21:13'),
(3, 'Limited Editions', 'Exclusive Launch', 'Hand assembled luxury with skeleton dials', 'https://res.cloudinary.com/syedahmershah/image/upload/v1776358758/commerza/slider/images/watch-banner-premium.jpg', 'premium watches exclusive luxury timepieces', NULL, 'Shop Limited', 'shop-category-b.php', NULL, NULL, 0.45, 3, 1, '2026-04-05 20:21:13', '2026-04-05 20:21:13');

-- --------------------------------------------------------

--
-- Table structure for table `social_links`
--

CREATE TABLE `social_links` (
  `id` int(11) NOT NULL,
  `label` varchar(50) NOT NULL COMMENT 'e.g. Facebook, Instagram',
  `url` varchar(255) NOT NULL,
  `icon` varchar(80) DEFAULT NULL COMMENT 'Bootstrap icon class, e.g. bi bi-facebook',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `social_links`
--

INSERT INTO `social_links` (`id`, `label`, `url`, `icon`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Facebook', 'https://www.facebook.com/commerza.ahmer', 'bi bi-facebook', 1, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(2, 'X', 'https://x.com/commerza_ahmer', 'bi bi-twitter', 2, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14'),
(3, 'Instagram', 'https://www.instagram.com/commerza.ahmer', 'bi bi-instagram', 3, 1, '2026-04-05 20:21:14', '2026-04-05 20:21:14');

-- --------------------------------------------------------

--
-- Table structure for table `ticker`
--

CREATE TABLE `ticker` (
  `id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL COMMENT 'Optional anchor URL for the message',
  `link_text` varchar(100) DEFAULT NULL COMMENT 'Optional CTA text after the message',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by_admin_id` int(11) DEFAULT NULL,
  `updated_by_admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ticker`
--

INSERT INTO `ticker` (`id`, `message`, `link_url`, `link_text`, `sort_order`, `is_active`, `created_at`, `updated_at`, `created_by_admin_id`, `updated_by_admin_id`) VALUES
(1, 'Private drop unlocked: signature chronographs now shipping nationwide', NULL, NULL, 1, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(2, 'Members perk: free premium case with selected limited editions', NULL, NULL, 2, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(3, 'New arrival: skeleton gold steel collection is now in stock', NULL, NULL, 3, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(4, 'Private drop unlocked: signature chronographs now shipping nationwide', NULL, NULL, 4, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(5, 'Members perk: free premium case with selected limited editions', NULL, NULL, 5, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(6, 'New arrival: skeleton gold steel collection is now in stock', NULL, NULL, 6, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `collectors_speak`
--

CREATE TABLE `collectors_speak` (
  `id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `tagline` varchar(120) DEFAULT NULL,
  `quote` varchar(500) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by_admin_id` int(11) DEFAULT NULL,
  `updated_by_admin_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `collectors_speak`
--

INSERT INTO `collectors_speak` (`id`, `name`, `tagline`, `quote`, `sort_order`, `is_active`, `created_at`, `updated_at`, `created_by_admin_id`, `updated_by_admin_id`) VALUES
(1, 'A. Khan', 'Lahore', 'The Skeleton Gold Steel feels premium in every detail. The movement is smooth and the dial steals attention.', 1, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(2, 'S. Malik', 'Karachi', 'I\'ve worn the Black Gold Dial daily. It keeps time accurately and looks incredible under low light.', 2, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(3, 'R. Ahmed', 'Islamabad', 'Fast shipping and stellar packaging. The leather strap quality is beyond what I expected.', 3, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(4, 'M. Hassan', 'Rawalpindi', 'The automatic movement is mesmerizing. I can watch it for hours through the exhibition case back.', 4, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(5, 'F. Ali', 'Multan', 'Excellent build quality and attention to detail. The weight feels perfect on the wrist.', 5, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(6, 'Z. Iqbal', 'Faisalabad', 'Customer service is outstanding. They helped me choose the perfect watch for my collection.', 6, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(7, 'N. Raza', 'Peshawar', 'The luminous hands are perfect for night visibility. Absolutely love the craftsmanship.', 7, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1),
(8, 'H. Shah', 'Quetta', 'Premium materials and flawless finishing. This watch rivals luxury brands at triple the price.', 8, 1, '2026-04-05 20:21:12', '2026-04-05 20:21:12', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `username_blacklist`
--

CREATE TABLE `username_blacklist` (
  `id` int(11) NOT NULL,
  `term` varchar(64) NOT NULL,
  `category` enum('A','B','C') NOT NULL DEFAULT 'A',
  `reason` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `username_blacklist`
--

INSERT INTO `username_blacklist` (`id`, `term`, `category`, `reason`, `is_active`, `created_at`) VALUES
(1, 'admin', 'A', 'System role name', 1, '2026-04-05 20:21:12'),
(2, 'administrator', 'A', 'System role name', 1, '2026-04-05 20:21:12'),
(3, 'root', 'A', 'System role name', 1, '2026-04-05 20:21:12'),
(4, 'owner', 'A', 'System role name', 1, '2026-04-05 20:21:12'),
(5, 'support', 'A', 'System role name', 1, '2026-04-05 20:21:12'),
(6, 'system', 'A', 'System role name', 1, '2026-04-05 20:21:12'),
(7, 'security', 'A', 'System role name', 1, '2026-04-05 20:21:12'),
(8, 'api', 'A', 'System endpoint name', 1, '2026-04-05 20:21:12'),
(9, 'backend', 'A', 'System endpoint name', 1, '2026-04-05 20:21:12'),
(10, 'frontend', 'A', 'System endpoint name', 1, '2026-04-05 20:21:12'),
(11, 'abuse', 'B', 'Offensive term', 1, '2026-04-05 20:21:12'),
(12, 'scam', 'B', 'Fraud term', 1, '2026-04-05 20:21:12'),
(13, 'fraud', 'B', 'Fraud term', 1, '2026-04-05 20:21:12'),
(14, 'hacker', 'B', 'Cyber abuse term', 1, '2026-04-05 20:21:12'),
(15, 'malware', 'B', 'Malicious term', 1, '2026-04-05 20:21:12'),
(16, 'drugs', 'B', 'Illegal content term', 1, '2026-04-05 20:21:12'),
(17, 'porn', 'B', 'Explicit sexual term', 1, '2026-04-05 20:21:12'),
(18, 'fuck', 'B', 'Offensive profanity term', 1, '2026-04-05 20:21:12'),
(19, 'shit', 'B', 'Offensive profanity term', 1, '2026-04-05 20:21:12'),
(20, 'bitch', 'B', 'Offensive profanity term', 1, '2026-04-05 20:21:12'),
(21, 'index', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(22, 'home', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(23, 'about', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(24, 'products', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(25, 'cart', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(26, 'wishlist', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(27, 'compare', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(28, 'account', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(29, 'login', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(30, 'signup', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(31, 'checkout', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(32, 'oauth', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(33, 'adminpanel', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(34, 'robots', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(35, 'sitemap', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:12'),
(40, 'staff', 'A', 'System role name', 1, '2026-04-05 20:21:38'),
(44, 'moderator', 'A', 'System role name', 1, '2026-04-05 20:21:38'),
(45, 'superuser', 'A', 'System role name', 1, '2026-04-05 20:21:38'),
(46, 'help', 'A', 'System role name', 1, '2026-04-05 20:21:38'),
(54, 'hack', 'B', 'Cyber abuse term', 1, '2026-04-05 20:21:38'),
(57, 'terrorist', 'B', 'Extremist term', 1, '2026-04-05 20:21:38'),
(62, 'asshole', 'B', 'Offensive profanity term', 1, '2026-04-05 20:21:38'),
(66, 'contact', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:38'),
(75, 'invoice', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:38'),
(76, 'returns', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:38'),
(77, 'shipping', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:38'),
(78, 'privacy', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:38'),
(79, 'terms', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:38'),
(80, 'ordertracking', 'C', 'Route-like reserved name', 1, '2026-04-05 20:21:38'),
(918, 'commerza', 'A', 'Brand reserved name', 1, '2026-04-05 21:45:29'),
(941, 'hitler', 'B', 'Hateful extremist term', 1, '2026-04-05 21:45:29'),
(942, 'nazi', 'B', 'Hateful extremist term', 1, '2026-04-05 21:45:29'),
(943, 'genocide', 'B', 'Violent hateful term', 1, '2026-04-05 21:45:29'),
(944, 'sex', 'B', 'Explicit sexual term', 1, '2026-04-05 21:45:29'),
(946, 'nude', 'B', 'Explicit sexual term', 1, '2026-04-05 21:45:29'),
(947, 'penis', 'B', 'Explicit sexual term', 1, '2026-04-05 21:45:29'),
(948, 'breast', 'B', 'Explicit sexual term', 1, '2026-04-05 21:45:29'),
(949, 'boob', 'B', 'Explicit sexual term', 1, '2026-04-05 21:45:29'),
(950, 'boobs', 'B', 'Explicit sexual term', 1, '2026-04-05 21:45:29'),
(951, 'ass', 'B', 'Offensive profanity term', 1, '2026-04-05 21:45:29'),
(956, 'islam', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(957, 'muslim', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(958, 'christ', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(959, 'christian', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(960, 'christianity', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(961, 'christainity', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(962, 'jesus', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(963, 'jew', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(964, 'jews', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(965, 'hindu', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(966, 'sikh', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(967, 'buddhist', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(968, 'atheist', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(969, 'allah', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(970, 'quran', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(971, 'bible', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29'),
(972, 'torah', 'B', 'Religious targeting term', 1, '2026-04-05 21:45:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(24) NOT NULL,
  `username_slug` varchar(48) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `profile_visibility` enum('private','public') NOT NULL DEFAULT 'private',
  `username_changed_at` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL COMMENT 'Secure random token (sha256 / uuid)',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_admin_dashboard_metrics`
-- (See below for the actual view)
--
CREATE TABLE `vw_admin_dashboard_metrics` (
`total_orders` bigint(21)
,`unique_customers` bigint(21)
,`total_revenue` decimal(32,2)
,`delivered_revenue` decimal(32,2)
,`pending_orders` decimal(22,0)
,`shipped_orders` decimal(22,0)
,`delivered_orders` decimal(22,0)
,`cancelled_orders` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_cart_details`
-- (See below for the actual view)
--
CREATE TABLE `vw_cart_details` (
`cart_id` int(11)
,`session_id` varchar(128)
,`user_id` int(11)
,`cart_item_id` int(11)
,`product_id` int(11)
,`quantity` int(11)
,`product_name` varchar(255)
,`product_image` varchar(255)
,`product_price` decimal(10,2)
,`product_sale_price` decimal(10,2)
,`product_stock` int(11)
,`line_total` decimal(20,2)
,`added_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_compare_products`
-- (See below for the actual view)
--
CREATE TABLE `vw_compare_products` (
`session_id` varchar(128)
,`user_id` int(11)
,`compare_item_id` int(11)
,`product_id` int(11)
,`product_name` varchar(255)
,`product_image` varchar(255)
,`product_price` decimal(10,2)
,`product_sale_price` decimal(10,2)
,`product_stock` int(11)
,`movement` enum('auto','manual','quartz','smart')
,`product_code` varchar(40)
,`warranty_info` varchar(120)
,`dispatch_info` varchar(120)
,`description` text
,`added_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_customer_stats`
-- (See below for the actual view)
--
CREATE TABLE `vw_customer_stats` (
`customer_name` varchar(100)
,`customer_email` varchar(150)
,`customer_phone` varchar(20)
,`user_id` int(11)
,`order_count` bigint(21)
,`total_spent` decimal(32,2)
,`first_order` timestamp
,`last_order` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_email_outbox_full`
-- (See below for the actual view)
--
CREATE TABLE `vw_email_outbox_full` (
`id` int(11)
,`subject` varchar(255)
,`body` text
,`recipient_count` int(11)
,`source` varchar(60)
,`status` enum('queued','sent','failed')
,`sent_at` timestamp
,`sent_by` varchar(100)
,`template_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_faq_full`
-- (See below for the actual view)
--
CREATE TABLE `vw_faq_full` (
`id` int(11)
,`question` varchar(255)
,`answer` text
,`sort_order` int(11)
,`is_active` tinyint(1)
,`category_id` int(11)
,`category_name` varchar(100)
,`category_icon` varchar(80)
,`category_sort` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_low_stock_products`
-- (See below for the actual view)
--
CREATE TABLE `vw_low_stock_products` (
`id` int(11)
,`name` varchar(255)
,`stock` int(11)
,`image` varchar(255)
,`sectionId` varchar(64)
,`sectionName` varchar(128)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_order_details`
-- (See below for the actual view)
--
CREATE TABLE `vw_order_details` (
`order_id` int(11)
,`order_number` varchar(30)
,`user_id` int(11)
,`customer_name` varchar(100)
,`customer_email` varchar(150)
,`customer_phone` varchar(20)
,`address` text
,`subtotal` decimal(10,2)
,`shipping_cost` decimal(10,2)
,`grand_total` decimal(10,2)
,`status` enum('Pending','Confirmed','Processing','Shipped','Delivered','Cancelled','Refunded')
,`payment_status` enum('unpaid','paid','partially_refunded','refunded')
,`payment_method` varchar(50)
,`notes` text
,`order_date` timestamp
,`item_id` int(11)
,`product_id` int(11)
,`product_name` varchar(255)
,`product_img` varchar(255)
,`unit_price` decimal(10,2)
,`quantity` int(11)
,`line_total` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_order_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_order_summary` (
`order_id` int(11)
,`order_number` varchar(30)
,`user_id` int(11)
,`customer_name` varchar(100)
,`customer_email` varchar(150)
,`customer_phone` varchar(20)
,`address` text
,`subtotal` decimal(10,2)
,`shipping_cost` decimal(10,2)
,`grand_total` decimal(10,2)
,`status` enum('Pending','Confirmed','Processing','Shipped','Delivered','Cancelled','Refunded')
,`payment_status` enum('unpaid','paid','partially_refunded','refunded')
,`payment_method` varchar(50)
,`order_date` timestamp
,`total_items` bigint(21)
,`total_quantity` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_products`
-- (See below for the actual view)
--
CREATE TABLE `vw_products` (
`id` int(11)
,`sectionId` varchar(64)
,`sectionName` varchar(128)
,`category` varchar(128)
,`subcategory` varchar(128)
,`page` varchar(64)
,`name` varchar(255)
,`description` text
,`image` varchar(255)
,`price` decimal(10,2)
,`salePrice` decimal(10,2)
,`stock` int(11)
,`movement` enum('auto','manual','quartz','smart')
,`video_url` varchar(255)
,`product_code` varchar(40)
,`warranty_info` varchar(120)
,`dispatch_info` varchar(120)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_wishlist_products`
-- (See below for the actual view)
--
CREATE TABLE `vw_wishlist_products` (
`user_id` int(11)
,`wishlist_item_id` int(11)
,`product_id` int(11)
,`product_name` varchar(255)
,`product_image` varchar(255)
,`product_price` decimal(10,2)
,`product_sale_price` decimal(10,2)
,`product_stock` int(11)
,`movement` enum('auto','manual','quartz','smart')
,`product_code` varchar(40)
,`warranty_info` varchar(120)
,`dispatch_info` varchar(120)
,`added_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist`
--

CREATE TABLE `wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlist_items`
--

CREATE TABLE `wishlist_items` (
  `id` int(11) NOT NULL,
  `wishlist_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `vw_admin_dashboard_metrics`
--
DROP TABLE IF EXISTS `vw_admin_dashboard_metrics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_admin_dashboard_metrics`  AS SELECT count(0) AS `total_orders`, count(distinct `orders`.`customer_email`) AS `unique_customers`, coalesce(sum(`orders`.`grand_total`),0) AS `total_revenue`, coalesce(sum(case when `orders`.`status` = 'Delivered' then `orders`.`grand_total` else 0 end),0) AS `delivered_revenue`, sum(case when `orders`.`status` = 'Pending' then 1 else 0 end) AS `pending_orders`, sum(case when `orders`.`status` = 'Shipped' then 1 else 0 end) AS `shipped_orders`, sum(case when `orders`.`status` = 'Delivered' then 1 else 0 end) AS `delivered_orders`, sum(case when `orders`.`status` = 'Cancelled' then 1 else 0 end) AS `cancelled_orders` FROM `orders` WHERE `orders`.`created_at` >= current_timestamp() - interval 30 day ;

-- --------------------------------------------------------

--
-- Structure for view `vw_cart_details`
--
DROP TABLE IF EXISTS `vw_cart_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_cart_details`  AS SELECT `c`.`id` AS `cart_id`, `c`.`session_id` AS `session_id`, `c`.`user_id` AS `user_id`, `ci`.`id` AS `cart_item_id`, `ci`.`product_id` AS `product_id`, `ci`.`quantity` AS `quantity`, `p`.`name` AS `product_name`, `p`.`image` AS `product_image`, `p`.`price` AS `product_price`, `p`.`salePrice` AS `product_sale_price`, `p`.`stock` AS `product_stock`, coalesce(`p`.`salePrice`,`p`.`price`) * `ci`.`quantity` AS `line_total`, `ci`.`added_at` AS `added_at` FROM ((`cart` `c` join `cart_items` `ci` on(`ci`.`cart_id` = `c`.`id`)) join `products` `p` on(`ci`.`product_id` = `p`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_compare_products`
--
DROP TABLE IF EXISTS `vw_compare_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_compare_products`  AS SELECT `cl`.`session_id` AS `session_id`, `cl`.`user_id` AS `user_id`, `cit`.`id` AS `compare_item_id`, `cit`.`product_id` AS `product_id`, `p`.`name` AS `product_name`, `p`.`image` AS `product_image`, `p`.`price` AS `product_price`, `p`.`salePrice` AS `product_sale_price`, `p`.`stock` AS `product_stock`, `p`.`movement` AS `movement`, `p`.`product_code` AS `product_code`, `p`.`warranty_info` AS `warranty_info`, `p`.`dispatch_info` AS `dispatch_info`, `p`.`description` AS `description`, `cit`.`added_at` AS `added_at` FROM ((`compare_list` `cl` join `compare_items` `cit` on(`cit`.`compare_id` = `cl`.`id`)) join `products` `p` on(`cit`.`product_id` = `p`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_customer_stats`
--
DROP TABLE IF EXISTS `vw_customer_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_customer_stats`  AS SELECT `o`.`customer_name` AS `customer_name`, `o`.`customer_email` AS `customer_email`, `o`.`customer_phone` AS `customer_phone`, `o`.`user_id` AS `user_id`, count(`o`.`id`) AS `order_count`, sum(`o`.`grand_total`) AS `total_spent`, min(`o`.`created_at`) AS `first_order`, max(`o`.`created_at`) AS `last_order` FROM `orders` AS `o` GROUP BY `o`.`customer_email`, `o`.`customer_name`, `o`.`customer_phone`, `o`.`user_id` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_email_outbox_full`
--
DROP TABLE IF EXISTS `vw_email_outbox_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_email_outbox_full`  AS SELECT `eo`.`id` AS `id`, `eo`.`subject` AS `subject`, `eo`.`body` AS `body`, `eo`.`recipient_count` AS `recipient_count`, `eo`.`source` AS `source`, `eo`.`status` AS `status`, `eo`.`sent_at` AS `sent_at`, `a`.`full_name` AS `sent_by`, `et`.`name` AS `template_name` FROM ((`email_outbox` `eo` left join `admin_users` `a` on(`eo`.`admin_id` = `a`.`id`)) left join `email_templates` `et` on(`eo`.`template_id` = `et`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_faq_full`
--
DROP TABLE IF EXISTS `vw_faq_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_faq_full`  AS SELECT `f`.`id` AS `id`, `f`.`question` AS `question`, `f`.`answer` AS `answer`, `f`.`sort_order` AS `sort_order`, `f`.`is_active` AS `is_active`, `fc`.`id` AS `category_id`, `fc`.`name` AS `category_name`, `fc`.`icon` AS `category_icon`, `fc`.`sort_order` AS `category_sort` FROM (`faq` `f` left join `faq_categories` `fc` on(`f`.`category_id` = `fc`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_low_stock_products`
--
DROP TABLE IF EXISTS `vw_low_stock_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_low_stock_products`  AS SELECT `p`.`id` AS `id`, `p`.`name` AS `name`, `p`.`stock` AS `stock`, `p`.`image` AS `image`, `p`.`sectionId` AS `sectionId`, `s`.`sectionName` AS `sectionName` FROM (`products` `p` join `sections` `s` on(`p`.`sectionId` = `s`.`sectionId`)) WHERE `p`.`stock` <= 5 ;

-- --------------------------------------------------------

--
-- Structure for view `vw_order_details`
--
DROP TABLE IF EXISTS `vw_order_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_order_details`  AS SELECT `o`.`id` AS `order_id`, `o`.`order_number` AS `order_number`, `o`.`user_id` AS `user_id`, `o`.`customer_name` AS `customer_name`, `o`.`customer_email` AS `customer_email`, `o`.`customer_phone` AS `customer_phone`, `o`.`address` AS `address`, `o`.`subtotal` AS `subtotal`, `o`.`shipping_cost` AS `shipping_cost`, `o`.`grand_total` AS `grand_total`, `o`.`status` AS `status`, `o`.`payment_status` AS `payment_status`, `o`.`payment_method` AS `payment_method`, `o`.`notes` AS `notes`, `o`.`created_at` AS `order_date`, `oi`.`id` AS `item_id`, `oi`.`product_id` AS `product_id`, `oi`.`product_name` AS `product_name`, `oi`.`product_img` AS `product_img`, `oi`.`unit_price` AS `unit_price`, `oi`.`quantity` AS `quantity`, `oi`.`line_total` AS `line_total` FROM (`orders` `o` join `order_items` `oi` on(`oi`.`order_id` = `o`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_order_summary`
--
DROP TABLE IF EXISTS `vw_order_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_order_summary`  AS SELECT `o`.`id` AS `order_id`, `o`.`order_number` AS `order_number`, `o`.`user_id` AS `user_id`, `o`.`customer_name` AS `customer_name`, `o`.`customer_email` AS `customer_email`, `o`.`customer_phone` AS `customer_phone`, `o`.`address` AS `address`, `o`.`subtotal` AS `subtotal`, `o`.`shipping_cost` AS `shipping_cost`, `o`.`grand_total` AS `grand_total`, `o`.`status` AS `status`, `o`.`payment_status` AS `payment_status`, `o`.`payment_method` AS `payment_method`, `o`.`created_at` AS `order_date`, count(`oi`.`id`) AS `total_items`, sum(`oi`.`quantity`) AS `total_quantity` FROM (`orders` `o` left join `order_items` `oi` on(`oi`.`order_id` = `o`.`id`)) GROUP BY `o`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_products`
--
DROP TABLE IF EXISTS `vw_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_products`  AS SELECT `p`.`id` AS `id`, `p`.`sectionId` AS `sectionId`, `s`.`sectionName` AS `sectionName`, `s`.`category` AS `category`, `s`.`subcategory` AS `subcategory`, `s`.`page` AS `page`, `p`.`name` AS `name`, `p`.`description` AS `description`, `p`.`image` AS `image`, `p`.`price` AS `price`, `p`.`salePrice` AS `salePrice`, `p`.`stock` AS `stock`, `p`.`movement` AS `movement`, `p`.`video_url` AS `video_url`, `p`.`product_code` AS `product_code`, `p`.`warranty_info` AS `warranty_info`, `p`.`dispatch_info` AS `dispatch_info`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at` FROM (`products` `p` join `sections` `s` on(`p`.`sectionId` = `s`.`sectionId`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vw_wishlist_products`
--
DROP TABLE IF EXISTS `vw_wishlist_products`;

CREATE ALGORITHM=UNDEFINED DEFINER=`syedahmershah`@`localhost` SQL SECURITY DEFINER VIEW `vw_wishlist_products`  AS SELECT `w`.`user_id` AS `user_id`, `wi`.`id` AS `wishlist_item_id`, `wi`.`product_id` AS `product_id`, `p`.`name` AS `product_name`, `p`.`image` AS `product_image`, `p`.`price` AS `product_price`, `p`.`salePrice` AS `product_sale_price`, `p`.`stock` AS `product_stock`, `p`.`movement` AS `movement`, `p`.`product_code` AS `product_code`, `p`.`warranty_info` AS `warranty_info`, `p`.`dispatch_info` AS `dispatch_info`, `wi`.`added_at` AS `added_at` FROM ((`wishlist` `w` join `wishlist_items` `wi` on(`wi`.`wishlist_id` = `w`.`id`)) join `products` `p` on(`wi`.`product_id` = `p`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cart_product` (`cart_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `compare_items`
--
ALTER TABLE `compare_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_compare_product` (`compare_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `compare_list`
--
ALTER TABLE `compare_list`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `customer_blacklist`
--
ALTER TABLE `customer_blacklist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_blacklist_active` (`is_active`,`created_at`),
  ADD KEY `idx_customer_blacklist_email` (`email`),
  ADD KEY `idx_customer_blacklist_phone` (`phone`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_coupon_code` (`code`),
  ADD KEY `idx_coupon_active_expiry` (`is_active`,`expires_at`);

--
-- Indexes for table `coupon_redemptions`
--
ALTER TABLE `coupon_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_coupon_order` (`coupon_id`,`order_id`),
  ADD KEY `idx_coupon_user` (`coupon_id`,`user_id`),
  ADD KEY `idx_coupon_used_at` (`used_at`);

--
-- Indexes for table `email_manual_recipients`
--
ALTER TABLE `email_manual_recipients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `email_outbox`
--
ALTER TABLE `email_outbox`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `email_suppressed`
--
ALTER TABLE `email_suppressed`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `engagement_reminders`
--
ALTER TABLE `engagement_reminders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_engagement_user_product_type` (`user_id`,`product_id`,`reminder_type`),
  ADD KEY `idx_engagement_pending` (`sent_at`,`last_seen_at`);

--
-- Indexes for table `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `faq_categories`
--
ALTER TABLE `faq_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `live_product_viewers`
--
ALTER TABLE `live_product_viewers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_live_product_session` (`session_key`,`product_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `last_seen_at` (`last_seen_at`);

--
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `page_content`
--
ALTER TABLE `page_content`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_page_section` (`page`,`section_key`);

--
-- Indexes for table `page_meta`
--
ALTER TABLE `page_meta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page` (`page`),
  ADD KEY `idx_page_meta_updated` (`updated_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_products_product_code` (`product_code`),
  ADD UNIQUE KEY `uq_products_slug` (`slug`),
  ADD KEY `sectionId` (`sectionId`),
  ADD KEY `idx_products_section_updated` (`sectionId`,`updated_at`,`id`),
  ADD KEY `idx_products_movement` (`movement`),
  ADD KEY `idx_products_price` (`price`),
  ADD KEY `idx_products_sale_price` (`salePrice`),
  ADD KEY `idx_products_stock` (`stock`),
  ADD KEY `idx_products_deleted_at` (`deleted_at`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_review_product_visible` (`product_id`,`is_visible`),
  ADD KEY `idx_review_created` (`created_at`),
  ADD KEY `idx_review_locked` (`is_locked`,`updated_at`);

--
-- Indexes for table `product_review_images`
--
ALTER TABLE `product_review_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_review_images_review` (`review_id`);

--
-- Indexes for table `product_fake_reviews`
--
ALTER TABLE `product_fake_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fake_reviews_product_visibility` (`product_id`,`is_visible`),
  ADD KEY `idx_fake_reviews_visibility_updated` (`is_visible`,`updated_at`),
  ADD KEY `idx_fake_reviews_locked_updated` (`is_locked`,`updated_at`),
  ADD KEY `idx_fake_reviews_product_created` (`product_id`,`created_at`),
  ADD KEY `idx_fake_reviews_admin_created` (`generated_by_admin_id`,`created_at`);

--
-- Indexes for table `product_trash`
--
ALTER TABLE `product_trash`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_trash_product_id` (`product_id`),
  ADD KEY `idx_product_trash_purge_after` (`purge_after`),
  ADD KEY `idx_product_trash_deleted_at` (`deleted_at`),
  ADD KEY `idx_product_trash_section` (`section_id`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rate_limit_scope_identifier_ip` (`scope`,`identifier`,`ip_address`),
  ADD KEY `idx_rate_limit_blocked_until` (`blocked_until`),
  ADD KEY `idx_rate_limit_updated_at` (`updated_at`);

--
-- Indexes for table `refund_requests`
--
ALTER TABLE `refund_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_refund_order` (`order_id`),
  ADD KEY `idx_refund_user_status` (`user_id`,`status`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sectionId` (`sectionId`);

--
-- Indexes for table `security_events`
--
ALTER TABLE `security_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_security_event_type_time` (`event_type`,`created_at`),
  ADD KEY `idx_security_actor_time` (`actor_type`,`actor_identifier`,`created_at`),
  ADD KEY `idx_security_severity_time` (`severity`,`created_at`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `slider`
--
ALTER TABLE `slider`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `social_links`
--
ALTER TABLE `social_links`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `collectors_speak`
--
ALTER TABLE `collectors_speak`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_collectors_active_sort` (`is_active`,`sort_order`,`id`),
  ADD KEY `idx_collectors_created_by` (`created_by_admin_id`),
  ADD KEY `idx_collectors_updated_by` (`updated_by_admin_id`);

--
-- Indexes for table `ticker`
--
ALTER TABLE `ticker`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticker_active_sort` (`is_active`,`sort_order`,`id`),
  ADD KEY `idx_ticker_created_by` (`created_by_admin_id`),
  ADD KEY `idx_ticker_updated_by` (`updated_by_admin_id`);

--
-- Indexes for table `username_blacklist`
--
ALTER TABLE `username_blacklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `term` (`term`),
  ADD KEY `idx_username_blacklist_category_active` (`category`,`is_active`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `username_slug` (`username_slug`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `idx_users_profile_visibility` (`profile_visibility`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wishlist_product` (`wishlist_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compare_items`
--
ALTER TABLE `compare_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `compare_list`
--
ALTER TABLE `compare_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_blacklist`
--
ALTER TABLE `customer_blacklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coupon_redemptions`
--
ALTER TABLE `coupon_redemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_manual_recipients`
--
ALTER TABLE `email_manual_recipients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_outbox`
--
ALTER TABLE `email_outbox`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_suppressed`
--
ALTER TABLE `email_suppressed`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `engagement_reminders`
--
ALTER TABLE `engagement_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faq`
--
ALTER TABLE `faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `faq_categories`
--
ALTER TABLE `faq_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `live_product_viewers`
--
ALTER TABLE `live_product_viewers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `page_content`
--
ALTER TABLE `page_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `page_meta`
--
ALTER TABLE `page_meta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_trash`
--
ALTER TABLE `product_trash`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_review_images`
--
ALTER TABLE `product_review_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_fake_reviews`
--
ALTER TABLE `product_fake_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `refund_requests`
--
ALTER TABLE `refund_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `slider`
--
ALTER TABLE `slider`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `social_links`
--
ALTER TABLE `social_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `collectors_speak`
--
ALTER TABLE `collectors_speak`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ticker`
--
ALTER TABLE `ticker`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `username_blacklist`
--
ALTER TABLE `username_blacklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4383;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wishlist`
--
ALTER TABLE `wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_sessions`
--
ALTER TABLE `admin_sessions`
  ADD CONSTRAINT `fk_as_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_ci_cart` FOREIGN KEY (`cart_id`) REFERENCES `cart` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ci_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `compare_items`
--
ALTER TABLE `compare_items`
  ADD CONSTRAINT `fk_cli_compare` FOREIGN KEY (`compare_id`) REFERENCES `compare_list` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cli_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `compare_list`
--
ALTER TABLE `compare_list`
  ADD CONSTRAINT `fk_cl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_manual_recipients`
--
ALTER TABLE `email_manual_recipients`
  ADD CONSTRAINT `fk_emr_admin` FOREIGN KEY (`added_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_outbox`
--
ALTER TABLE `email_outbox`
  ADD CONSTRAINT `fk_eo_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_eo_template` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `faq`
--
ALTER TABLE `faq`
  ADD CONSTRAINT `fk_faq_cat` FOREIGN KEY (`category_id`) REFERENCES `faq_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_ord_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `collectors_speak`
--
ALTER TABLE `collectors_speak`
  ADD CONSTRAINT `fk_collectors_created_by_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_collectors_updated_by_admin` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticker`
--
ALTER TABLE `ticker`
  ADD CONSTRAINT `fk_ticker_created_by_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ticker_updated_by_admin` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`sectionId`) REFERENCES `sections` (`sectionId`) ON DELETE CASCADE;

--
-- Constraints for table `product_review_images`
--
ALTER TABLE `product_review_images`
  ADD CONSTRAINT `fk_pri_review` FOREIGN KEY (`review_id`) REFERENCES `product_reviews` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_fake_reviews`
--
ALTER TABLE `product_fake_reviews`
  ADD CONSTRAINT `fk_pfr_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pfr_admin` FOREIGN KEY (`generated_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_us_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `fk_wl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD CONSTRAINT `fk_wli_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wli_wishlist` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlist` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
