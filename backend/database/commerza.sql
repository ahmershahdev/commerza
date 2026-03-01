-- ============================================================
-- Commerza Full Schema (Base + Extended)
-- Single import file for XAMPP/phpMyAdmin
-- ============================================================

-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 01, 2026 at 12:00 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `commerza`;
USE `commerza`;

--
-- Database: `commerza`
--

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `sectionId` varchar(64) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `salePrice` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `movement` enum('auto','manual','quartz','smart') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `sectionId`, `name`, `description`, `image`, `price`, `salePrice`, `stock`, `movement`) VALUES
(1, 'featured-collection', 'Tambour Bushido Automata, Manual, 46.8 mm, Rose Gold', 'Taking its name from the code of conduct of the Japanese Samurai, the Tambour Bashido Automata is an homage to strength, discipline and artistic expression.', 'frontend/assets/images/products/featured/white-gold-steel.webp', 8500.00, 6200.00, 45, 'auto'),
(2, 'featured-collection', 'Louis Vuitton Escale Twin Zone, Automatic, 41mm, Platinum and diamonds', 'The Louis Vuitton Escale Twin Zone 41mm watch in platinum and diamonds is a precious embodiement of the Art of Travel. Its unique dual time display.', 'frontend/assets/images/products/featured/black-white-gold.webp', 9200.00, 7100.00, 32, 'auto'),
(3, 'featured-collection', 'Louis Vuitton Escale, Automatic, 39mm, Rose Gold', 'The Escale Automatic 39 mm in rose gold features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks. Enriched by a textured dial, this time-only timepiece is a celebration of technical precision.', 'frontend/assets/images/products/featured/skeleton-gold-steel.webp', 11500.00, 8900.00, 28, 'auto'),
(4, 'featured-collection', 'Louis Vuitton Escale, Automatic, 40mm, Yellow gold and tiger\'s eye', 'Limited to 30 pieces, the Escale Automatic 40mm watch in 750/1000 yellow gold and tiger\'s eye is a singular expression of rigor, innovation, and elegance.', 'frontend/assets/images/products/featured/brown-gold-dial.webp', 12800.00, 9600.00, 38, 'auto'),
(5, 'featured-collection', 'Louis Vuitton Escale, Automatic, 39mm, Platinum', 'Limited to 50 pieces, this Escale Automatic 39 mm in platinum features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'frontend/assets/images/products/featured/black-gold-dial.webp', 10200.00, 7800.00, 52, 'auto'),
(6, 'featured-collection', 'Louis Vuitton Escale, Automatic, 39mm, Rose Gold', 'Limited to 100 pieces and tribute to Gaston-Louis Vuitton, this Escale Automatic 39 mm in rose gold features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'frontend/assets/images/products/featured/brown-premium-watch.webp', 13500.00, 10200.00, 25, 'auto'),
(7, 'featured-collection', 'Louis Vuitton Escale, Automatic, 39mm, Platinum', 'The Escale Automatic 39 mm in platinum features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'frontend/assets/images/products/featured/premium-black-gold.webp', 16500.00, 12400.00, 18, 'auto'),
(8, 'featured-collection', 'Louis Vuitton Escale Worldtime, Automatic, 40mm, Platinum', 'The Louis Vuitton Escale Worldtime 40mm in platinum stands as the foremost Louis Vuitton timepiece, embodying the House\'s art of travel.', 'frontend/assets/images/products/featured/black-minimalist.webp', 11000.00, 8300.00, 41, 'auto'),
(9, 'featured-collection', 'Louis Vuitton Escale Twin Zone, Automatic, 40mm, Rose Gold', 'The Louis Vuitton Escale Twin Zone 40mm watch in 750/1000 rose gold is an elegant interpretation of the Art of Travel. Powered by the in-house automatic LFTVO15.01 movement.', 'frontend/assets/images/products/featured/black-feather.webp', 18500.00, 14200.00, 22, 'auto'),
(10, 'featured-collection', 'Louis Vuitton Escale, Automatic, 40mm, Platinum and malachite', 'Limited to 30 pieces, the Escale Automatic 40mm watch in platinum and malachite is an irrepressible demonstration of hard stones craftsmanship.', 'frontend/assets/images/products/featured/luxury-white-gold.webp', 21500.00, 16800.00, 15, 'auto'),
(11, 'automatic-vault', 'Tambour Bushido Automata, Manual, 46.8 mm, Rose Gold', 'Taking its name from the code of conduct of the Japanese Samurai, the Tambour Bashido Automata is an homage to strength, discipline and artistic expression.', 'frontend/assets/images/products/featured/white-gold-steel.webp', 8500.00, 6200.00, 45, 'auto'),
(12, 'automatic-vault', 'Louis Vuitton Escale Twin Zone, Automatic, 41mm, Platinum and diamonds', 'The Louis Vuitton Escale Twin Zone 41mm watch in platinum and diamonds is a precious embodiement of the Art of Travel. Its unique dual time display.', 'frontend/assets/images/products/featured/black-white-gold.webp', 9200.00, 7100.00, 32, 'auto'),
(13, 'automatic-vault', 'Louis Vuitton Escale, Automatic, 39mm, Rose Gold', 'The Escale Automatic 39 mm in rose gold features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks. Enriched by a textured dial, this time-only timepiece is a celebration of technical precision.', 'frontend/assets/images/products/featured/skeleton-gold-steel.webp', 11500.00, 8900.00, 28, 'auto'),
(14, 'automatic-vault', 'Louis Vuitton Escale, Automatic, 40mm, Yellow gold and tiger\'s eye', 'Limited to 30 pieces, the Escale Automatic 40mm watch in 750/1000 yellow gold and tiger\'s eye is a singular expression of rigor, innovation, and elegance.', 'frontend/assets/images/products/featured/brown-gold-dial.webp', 12800.00, 9600.00, 38, 'auto'),
(15, 'automatic-vault', 'Louis Vuitton Escale, Automatic, 39mm, Platinum', 'Limited to 50 pieces, this Escale Automatic 39 mm in platinum features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'frontend/assets/images/products/featured/black-gold-dial.webp', 10200.00, 7800.00, 52, 'auto'),
(16, 'automatic-vault', 'Louis Vuitton Escale, Automatic, 39mm, Rose Gold', 'Limited to 100 pieces and tribute to Gaston-Louis Vuitton, this Escale Automatic 39 mm in rose gold features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'frontend/assets/images/products/featured/brown-premium-watch.webp', 13500.00, 10200.00, 25, 'auto'),
(17, 'automatic-vault', 'Louis Vuitton Escale, Automatic, 39mm, Platinum', 'The Escale Automatic 39 mm in platinum features intricate yet refined details that echo those found adorning the Maison\'s iconic trunks.', 'frontend/assets/images/products/featured/premium-black-gold.webp', 16500.00, 12400.00, 18, 'auto'),
(18, 'automatic-vault', 'Louis Vuitton Escale Worldtime, Automatic, 40mm, Platinum', 'The Louis Vuitton Escale Worldtime 40mm in platinum stands as the foremost Louis Vuitton timepiece, embodying the House\'s art of travel.', 'frontend/assets/images/products/featured/black-minimalist.webp', 11000.00, 8300.00, 41, 'auto'),
(19, 'smart-evolution', 'Visionary Smartwatch', 'The ZERO Visionary Smartwatch combines cutting-edge technology with a sleek modern design, giving you the ultimate balance of style, performance, and wellness.', 'frontend/assets/images/products/smart/Fajr - Hybrid Digital Rectangle Watch.webp', 7000.00, 5500.00, 35, 'smart'),
(20, 'smart-evolution', 'Crown Smartwatch', 'Experience technology that\'s beyond luxury with the all-new Crown Smartwatch packed with features that are never before seen in the industry with a Vegan Leather Strap.', 'frontend/assets/images/products/smart/Fajr - Hybrid Digital Round Watch.webp', 7500.00, 6500.00, 29, 'smart'),
(21, 'smart-evolution', 'Regal AI Smartwatch', 'The Regal Smartwatch is an elegant fusion of style and innovation. Its sleek, modern design and durable construction make it a versatile accessory for any occasion.', 'frontend/assets/images/products/smart/Hango X - Skmei Digital Watch - Black.webp', 11500.00, 8900.00, 24, 'smart'),
(22, 'smart-evolution', 'Vision Smartwatch', 'Experience the perfect blend of innovation and style with the Vision Smartwatch. Designed for your busy lifestyle, this smartwatch keeps you connected and efficient throughout the day.', 'frontend/assets/images/products/smart/I-8 Pro Max Smart Watch.webp', 14200.00, 10800.00, 19, 'smart'),
(23, 'smart-evolution', 'Elite Smartwatch', 'The Elite Zero Smartwatch combines high-tech features with a stylish design. Perfect for those who want both performance and elegance, this smartwatch helps you stay connected, track your health.', 'frontend/assets/images/products/smart/S-8 Pro Max Smart Watch.webp', 18500.00, 13800.00, 14, 'smart'),
(24, 'smart-evolution', 'Vogue Smartwatch', 'The Vogue Smartwatch from ZeroLifestyle is a perfect blend of style, functionality, and durability. Designed for fitness enthusiasts and busy professionals alike, this smartwatch offers a plethora of features.', 'frontend/assets/images/products/smart/T800 Ultra 2 49 mm.webp', 16800.00, 12600.00, 17, 'smart'),
(25, 'smart-evolution', 'Luna Pro', 'The Luna Pro Smartwatch blends modern design with powerful health and lifestyle features, delivering a premium experience for everyday life. With advanced sensors, fitness tracking, and a customizable interface.', 'frontend/assets/images/products/smart/Ultra 8 Smart Watch.webp', 20500.00, 15500.00, 12, 'smart'),
(26, 'signature-collection', 'TAG Heuer Aquaracer Professional 200 Date', 'Elegance is in every detail of this TAG Heuer Aquaracer, Blending the strength of stainless steel plated with 18K 3N yellow gold.', 'frontend/assets/images/products/minimal/DENIM 3 - The Minimalist Watch.webp', 7000.00, 5500.00, 48, 'quartz'),
(27, 'signature-collection', 'TAG Heuer Aquaracer Professional 300 Date', 'The TAG Heuer Aquaracer Professional 300 Date embodies precision, elegance, and performance, crafted for modern explorers who embrace the depths of the ocean.', 'frontend/assets/images/products/minimal/DI-STAR - CHAIN WATCH WITH DATE TWO TONE.webp', 7500.00, 6500.00, 36, 'quartz'),
(28, 'signature-collection', 'TAG Heuer Aquaracer Professional 300 Date', 'Take a dip in the intensely refreshing waters of lagoons and coral reefs that inspire this TAG Heuer Aquaracer. The vivid, VS diamond-studded 1.40mm (0.078 ct) dial stands out and the professional, life-proof case.', 'frontend/assets/images/products/minimal/Fued - Tomi Face Gear Dual Leather Straps Watch.webp', 9000.00, 8000.00, 31, 'quartz'),
(29, 'signature-collection', 'TAG Heuer Aquaracer Professional 300 GMT', 'The sea is your playground when you wear this extremely versatile TAG Heuer Aquaracer Professional 300 on your wrist. Featuring the powerful Calibre TH31-03 movement.', 'frontend/assets/images/products/minimal/Galcia - Round Minimalist Watch WITH DATE.webp', 10000.00, 9000.00, 27, 'quartz'),
(30, 'signature-collection', 'TAG Heuer Aquaracer Professional 300 Date', 'With a luminous pastel green dial and rugged build, the TAG Heuer Aquaracer Professional 300 Date is ready for deep dives and sunlit shores alike.', 'frontend/assets/images/products/minimal/Square Tom - Minimalist Watch.webp', 11200.00, 8500.00, 33, 'quartz'),
(31, 'signature-collection', 'TAG Heuer Aquaracer Professional 300 Date', 'The TAG Heuer Aquaracer Professional 300 Date is inspired by the vivid colors of coral reefs found in the depths of the ocean.', 'frontend/assets/images/products/minimal/TOMI T 105 - Tomi Face Gear Black Dial.webp', 13200.00, 9900.00, 20, 'quartz'),
(32, 'signature-collection', 'TAG Heuer Aquaracer Professional 200 Date', 'Embrace the warm colours of autumn with this 30mm TAG Heuer Aquaracer and its intense ruby-red mother-of-pearl dial.', 'frontend/assets/images/products/minimal/TOMI- Round Minimalist Watch WITH DATE.webp', 15800.00, 11800.00, 16, 'quartz'),
(33, 'signature-collection', 'TAG Heuer Aquaracer Professional 200 Solargraph', 'Immerse yourself in the celestial allure of the northern lights with this TAG Heuer Aquaracer, equipped with the advanced Solargraph Calibre TH50-01.', 'frontend/assets/images/products/minimal/X - Round Minimalist Watch (Half Cut).webp', 10800.00, 8200.00, 39, 'quartz'),
(34, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Tourbillon Extreme Sport', 'Embrace the fusion of luxury and performance with the TAG Heuer Carrera Chronograph Tourbillon, a striking embodiment of motorsport passion.', 'frontend/assets/images/products/sports/Aura - Never Stop Minimal Watch with Date -N905.webp', 7000.00, 5500.00, 44, 'quartz'),
(35, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Extreme Sport', 'Immerse yourself in the essence of motorsport with this striking 44mm TAG Heuer Carrera Chronograph. Dressed entirely in black, it strikes a bold statement of elegance and power.', 'frontend/assets/images/products/sports/Chrona - Never Stop Minimal Watch - N928.webp', 7500.00, 6500.00, 34, 'quartz'),
(36, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Extreme Sport', 'Elevate your wrist game with the TAG Heuer Carrera Chronograph, a testament to the thrill of motorsport. With its bold design and blue accents.', 'frontend/assets/images/products/sports/Dagahra- Never Stop Casual sports Watch with date - N911.webp', 9000.00, 8000.00, 26, 'quartz'),
(37, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Extreme Sport Twin-Time', 'The TAG Heuer Carrera Chronograph Extreme Sport Twin-Time redefines performance with a motorsport-inspired design.', 'frontend/assets/images/products/sports/Newmoon - Never Stop Chronograph sports Watch with date - N902.webp', 10000.00, 9000.00, 23, 'quartz'),
(38, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Extreme Sport', 'Audacity meets elegance in this one-of-a-kind TAG Heuer Carrera Chronograph. With its skeleton design and rose gold accents.', 'frontend/assets/images/products/sports/RECDIS - Skmei 3 Time Sports Watch With Stainless Steel.webp', 17800.00, 13400.00, 11, 'quartz'),
(39, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Tourbillon Extreme Sport I F1 75th Anniversary Limited Edition', 'Created to celebrate 75 years of Formula 1, the TAG Heuer Carrera Chronograph Tourbillon Extreme Sport I F1 75th Anniversary Limited Edition is limited to just 75 pieces', 'frontend/assets/images/products/sports/TOKDIS - Dual Time Sports Watch With Stainless Steel.webp', 16200.00, 12200.00, 13, 'quartz'),
(40, 'sports-sales-division', 'TAG Heuer Carrera Chronograph Tourbillon Extreme Sport TH-Carbonspring', 'Built to withstand intense performance conditions, the TAG Heuer Carrera Chronograph Tourbillon Extreme Sport TH-Carbonspring fuses high-tech materials.', 'frontend/assets/images/products/sports/Yraz - Never Stop Casual sports Watch with date.webp', 19800.00, 15100.00, 9, 'quartz');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `sectionId` varchar(64) NOT NULL,
  `sectionName` varchar(128) NOT NULL,
  `category` varchar(128) DEFAULT NULL,
  `subcategory` varchar(128) DEFAULT NULL,
  `page` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`sectionId`, `sectionName`, `category`, `subcategory`, `page`) VALUES
('automatic-vault', 'The Automatic Vault', 'Premium Watches', 'Mechanical & Smart Timepieces', 'shop-category-a.html'),
('featured-collection', 'Featured Collection', 'Premium Watches & Accessories', 'Luxury Timepieces', 'index.html'),
('signature-collection', 'The Signature Collection', 'Lifestyle & Utility Watches', 'Minimalist & Sports Timepieces', 'shop-category-b.html'),
('smart-evolution', 'Smart Evolution Series', 'Premium Watches', 'Mechanical & Smart Timepieces', 'shop-category-a.html'),
('sports-sales-division', 'The Sports & Sales Division', 'Lifestyle & Utility Watches', 'Minimalist & Sports Timepieces', 'shop-category-b.html');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(40) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sectionId` (`sectionId`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`sectionId`);

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
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`sectionId`) REFERENCES `sections` (`sectionId`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- ============================================================
-- EXTENDED SCHEMA START
-- ============================================================

-- ============================================================
-- Commerza Extended Schema
-- Additional tables matching current website features
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `commerza`;
USE `commerza`;

-- ============================================================
-- 1. SITE SETTINGS
-- Key-value store for all site-wide configuration
-- (website name, tagline, logo, contact info, social links, etc.)
-- ============================================================

CREATE TABLE `site_settings` (
  `id`          int(11)       NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100)  NOT NULL UNIQUE,
  `setting_val` text          DEFAULT NULL,
  `label`       varchar(150)  DEFAULT NULL COMMENT 'Human-readable label for the admin panel',
  `group`       varchar(64)   DEFAULT 'general' COMMENT 'Group, e.g. general | social | seo | contact',
  `updated_at`  timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default site settings
INSERT INTO `site_settings` (`setting_key`, `setting_val`, `label`, `group`) VALUES
('site_name',           'COMMERZA',                                                         'Website Name',              'general'),
('site_tagline',        'Luxury Timepieces. Unmatched Excellence.',                          'Site Tagline',              'general'),
('site_email',          'commerza.ahmer@gmail.com',                                         'Contact Email',             'contact'),
('site_phone',          '+92 314 8396293',                                                  'Contact Phone',             'contact'),
('site_address',        'Barrage Colony, HYD, PK',                                         'Business Address',          'contact'),
('currency_code',       'PKR',                                                              'Currency Code',             'general'),
('currency_symbol',     'Rs.',                                                              'Currency Symbol',           'general'),
('timezone',            'Asia/Karachi',                                                     'Timezone',                  'general'),
('logo_url',            'frontend/assets/images/logo/commerza-logo.webp',                  'Logo Image URL',            'general'),
('favicon_url',         'frontend/assets/images/favicon/commerza-watches-icon.ico',        'Favicon URL',               'general'),
('meta_title',          'Commerza | Full-Stack Ecommerce',                                 'Default Meta Title',        'seo'),
('meta_description',    'Commerza brings you premium automatic watches—crafted with elegant leather, gold dials, and modern design.', 'Default Meta Description', 'seo'),
('maintenance_mode',    '0',                                                                'Maintenance Mode (0/1)',    'general'),
('free_shipping_over',  '500',                                                              'Free Shipping Threshold',   'shipping'),
('tax_rate',            '0.08',                                                             'Tax Rate (decimal)',        'general'),
('instagram_url',       'https://www.instagram.com/commerza.ahmer',                        'Instagram URL',             'social'),
('facebook_url',        'https://www.facebook.com/commerza.ahmer',                         'Facebook URL',              'social'),
('twitter_url',         'https://x.com/commerza_ahmer',                                    'Twitter / X URL',           'social'),
('youtube_url',         '',                                                                 'YouTube URL',               'social'),
('tiktok_url',          '',                                                                 'TikTok URL',                'social'),
('footer_text',         '© 2026 Commerza. All rights reserved.',                            'Footer Copyright Text',     'general'),
('ticker_enabled',      '1',                                                                'Enable Ticker (0/1)',       'general');

-- ============================================================
-- 2. TICKER
-- Scrolling ticker messages displayed above the hero slider
-- ============================================================

CREATE TABLE `ticker` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `message`    varchar(255) NOT NULL,
  `link_url`   varchar(255) DEFAULT NULL COMMENT 'Optional anchor URL for the message',
  `link_text`  varchar(100) DEFAULT NULL COMMENT 'Optional CTA text after the message',
  `sort_order` int(11)      NOT NULL DEFAULT 0,
  `is_active`  tinyint(1)   NOT NULL DEFAULT 1,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `ticker` (`message`, `link_url`, `link_text`, `sort_order`) VALUES
('SALE IS LIVE: PREMIUM AUTOMATIC WATCHES UP TO 20% OFF',   NULL, NULL, 1),
('COLLECTION UPDATE: NEW SKELETON SERIES NOW AVAILABLE',     NULL, NULL, 2),
('FREE SHIPPING: NATIONWIDE DELIVERY ON ALL PREMIUM ORDERS', NULL, NULL, 3);

-- ============================================================
-- 3. SLIDER (Carousel)
-- Hero carousel / banner slides for the homepage
-- ============================================================

CREATE TABLE `slider` (
  `id`              int(11)      NOT NULL AUTO_INCREMENT,
  `title`           varchar(150) DEFAULT NULL,
  `subtitle`        varchar(255) DEFAULT NULL,
  `description`     text         DEFAULT NULL,
  `image_url`       varchar(255) NOT NULL,
  `video_url`       varchar(255) DEFAULT NULL COMMENT 'Optional background video',
  `cta_text`        varchar(80)  DEFAULT NULL COMMENT 'Call-to-action button label',
  `cta_url`         varchar(255) DEFAULT NULL COMMENT 'Call-to-action button link',
  `cta_text_2`      varchar(80)  DEFAULT NULL COMMENT 'Secondary CTA label',
  `cta_url_2`       varchar(255) DEFAULT NULL COMMENT 'Secondary CTA link',
  `overlay_opacity` decimal(3,2) DEFAULT 0.40 COMMENT '0.00 - 1.00 overlay darkness',
  `sort_order`      int(11)      NOT NULL DEFAULT 0,
  `is_active`       tinyint(1)   NOT NULL DEFAULT 1,
  `created_at`      timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`      timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `slider` (`title`, `subtitle`, `description`, `image_url`, `video_url`, `cta_text`, `cta_url`, `cta_text_2`, `cta_url_2`, `overlay_opacity`, `sort_order`) VALUES
('Chronograph Precision',  'Premium Collection', 'Engineered movements with dual finish cases',   'frontend/assets/images/slider/watch-banner-chronograph.webp', NULL, 'Explore Now',     'shop-category-a.html', NULL, NULL, 0.40, 1),
('Every Style, One Place', 'Complete Series',    'From minimalist to bold statement pieces',      'frontend/assets/images/slider/watch-banner-collection.webp',  NULL, 'View Collection', 'shop-category-b.html', NULL, NULL, 0.40, 2),
('Limited Editions',       'Exclusive Launch',   'Hand assembled luxury with skeleton dials',     'frontend/assets/images/slider/watch-banner-premium.webp',     NULL, 'Shop Limited',    'shop-category-b.html', NULL, NULL, 0.45, 3);

-- ============================================================
-- 4. CONTACT MESSAGES
-- Submissions from the contact form (name, email, message)
-- ============================================================

CREATE TABLE `contact_messages` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `name`       varchar(100) NOT NULL,
  `email`      varchar(150) NOT NULL,
  `message`    text         NOT NULL,
  `is_read`    tinyint(1)   NOT NULL DEFAULT 0,
  `is_replied` tinyint(1)   NOT NULL DEFAULT 0,
  `ip_address` varchar(45)  DEFAULT NULL,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 5. CART
-- One cart row per session (guest or logged-in user)
-- ============================================================

CREATE TABLE `cart` (
  `id`              int(11)      NOT NULL AUTO_INCREMENT,
  `session_id`      varchar(128) DEFAULT NULL COMMENT 'Guest session identifier',
  `user_id`         int(11)      DEFAULT NULL COMMENT 'NULL for guest carts',
  `created_at`      timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`      timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id`  (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 6. CART ITEMS
-- ============================================================

CREATE TABLE `cart_items` (
  `id`         int(11)       NOT NULL AUTO_INCREMENT,
  `cart_id`    int(11)       NOT NULL,
  `product_id` int(11)       NOT NULL,
  `quantity`   int(11)       NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Price locked at time of add',
  `added_at`   timestamp     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_product` (`cart_id`, `product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 7. ORDERS
-- Matches checkout: name, email, phone, address (textarea), COD
-- ============================================================

CREATE TABLE `orders` (
  `id`                 int(11)       NOT NULL AUTO_INCREMENT,
  `order_number`       varchar(30)   NOT NULL UNIQUE COMMENT 'Human-readable, e.g. #ORD-XXXX',
  `user_id`            int(11)       DEFAULT NULL COMMENT 'NULL if guest checkout',

  -- Customer info (captured at checkout)
  `customer_name`      varchar(100)  NOT NULL,
  `customer_email`     varchar(150)  NOT NULL,
  `customer_phone`     varchar(20)   DEFAULT NULL,
  `address`            text          NOT NULL COMMENT 'Freeform shipping address from checkout textarea',

  -- Financials
  `subtotal`           decimal(10,2) NOT NULL,
  `shipping_cost`      decimal(10,2) NOT NULL DEFAULT 0.00,
  `grand_total`        decimal(10,2) NOT NULL,

  -- Status
  `status`             enum('pending','confirmed','processing','shipped','delivered','cancelled','refunded')
                                     NOT NULL DEFAULT 'pending',
  `payment_status`     enum('unpaid','paid','partially_refunded','refunded')
                                     NOT NULL DEFAULT 'unpaid',
  `payment_method`     varchar(50)   NOT NULL DEFAULT 'cod' COMMENT 'Currently COD only',

  `notes`              text          DEFAULT NULL COMMENT 'Customer order notes',
  `created_at`         timestamp     NOT NULL DEFAULT current_timestamp(),
  `updated_at`         timestamp     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 8. ORDER ITEMS
-- Line-items for each order (product snapshot)
-- ============================================================

CREATE TABLE `order_items` (
  `id`           int(11)       NOT NULL AUTO_INCREMENT,
  `order_id`     int(11)       NOT NULL,
  `product_id`   int(11)       DEFAULT NULL COMMENT 'NULL if product was later deleted',
  `product_name` varchar(255)  NOT NULL COMMENT 'Name locked at order time',
  `product_img`  varchar(255)  DEFAULT NULL,
  `unit_price`   decimal(10,2) NOT NULL COMMENT 'Price locked at order time',
  `quantity`     int(11)       NOT NULL DEFAULT 1,
  `line_total`   decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id`   (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 9. WISHLIST
-- One wishlist per logged-in user
-- ============================================================

CREATE TABLE `wishlist` (
  `id`         int(11)   NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)   NOT NULL UNIQUE,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 10. WISHLIST ITEMS
-- ============================================================

CREATE TABLE `wishlist_items` (
  `id`          int(11)   NOT NULL AUTO_INCREMENT,
  `wishlist_id` int(11)   NOT NULL,
  `product_id`  int(11)   NOT NULL,
  `added_at`    timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wishlist_product` (`wishlist_id`, `product_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 11. NEWSLETTER SUBSCRIBERS
-- ============================================================

CREATE TABLE `newsletter_subscribers` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `email`         varchar(150) NOT NULL UNIQUE,
  `name`          varchar(100) DEFAULT NULL,
  `source`        varchar(50)  DEFAULT NULL COMMENT 'modal | inline | admin',
  `is_active`     tinyint(1)   NOT NULL DEFAULT 1,
  `unsubscribed_at` datetime   DEFAULT NULL,
  `subscribed_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 12. FAQ CATEGORIES
-- ============================================================

CREATE TABLE `faq_categories` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `name`       varchar(100) NOT NULL,
  `icon`       varchar(80)  DEFAULT NULL COMMENT 'Icon class or URL',
  `sort_order` int(11)      NOT NULL DEFAULT 0,
  `is_active`  tinyint(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `faq_categories` (`name`, `sort_order`) VALUES
('Orders & Payments', 1),
('Shipping & Delivery', 2),
('Returns & Warranty', 3),
('Product & Authenticity', 4),
('Account & Security', 5);

-- ============================================================
-- 13. FAQ
-- ============================================================

CREATE TABLE `faq` (
  `id`          int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `question`    varchar(255) NOT NULL,
  `answer`      text         NOT NULL,
  `sort_order`  int(11)      NOT NULL DEFAULT 0,
  `is_active`   tinyint(1)   NOT NULL DEFAULT 1,
  `created_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- FAQ rows: 4 per category
INSERT INTO `faq` (`category_id`, `question`, `answer`, `sort_order`) VALUES
(1, 'How do I place an order?',                          'Browse our collection, add your chosen timepiece to the cart, and proceed to checkout. You will need to provide a shipping address and payment details to complete your order.',                                                                                1),
(1, 'What payment methods do you accept?',               'Commerza accepts all major credit and debit cards (Visa, Mastercard, Amex), PayPal, and cash on delivery for eligible regions.',                                                                                                                               2),
(1, 'Can I modify or cancel my order after placing it?', 'Orders can be modified or cancelled within 2 hours of placement. Contact our support team immediately at support@commerza.com and we will do our best to accommodate your request.',                                                                   3),
(1, 'Is my payment information secure?',                 'Yes. All transactions are encrypted using industry-standard SSL/TLS protocols. Commerza never stores your full card details on our servers.',                                                                                                                4),
(2, 'How long does standard shipping take?',             'Standard shipping typically takes 5-7 business days. Express (2-3 days) and Overnight (next business day) options are available at checkout.',                                                                                                         1),
(2, 'Do you offer free shipping?',                       'Yes - orders over Rs. 500 qualify for free standard nationwide shipping automatically. No coupon code required.',                                                                                                                                      2),
(2, 'Do you ship internationally?',                      'Commerza ships to most countries worldwide. Shipping rates and estimated delivery times are shown at checkout based on your destination.',                                                                                                               3),
(2, 'How can I track my order?',                         'Once your order has shipped you will receive a tracking number by email. You can also visit our Order Tracking page and enter your order number and email address.',                                                                                4),
(3, 'What is your return policy?',                       'We offer hassle-free returns within 30 days of delivery. Items must be unworn, in original packaging, and accompanied by proof of purchase. Visit our Returns page to submit a request.',                                                           1),
(3, 'How long does a refund take to process?',           'Once we receive and inspect the returned item, refunds are processed within 5-7 business days to your original payment method.',                                                                                                                    2),
(3, 'What warranty do Commerza watches carry?',          'All timepieces come with a minimum 2-year manufacturer warranty against defects in materials and workmanship. Some collections carry extended 5-year coverage - see individual product pages for details.',                                             3),
(3, 'How do I file a warranty claim?',                   'Visit our Warranty page, complete the claim form with your order number, product details, and a description of the issue. Our service team will respond within 2 business days.',                                                                    4),
(4, 'Are all watches sold on Commerza authentic?',       'Absolutely. Every timepiece sold on Commerza is 100% authentic and sourced directly from authorised distributors or the brands themselves. We provide certificates of authenticity with every order.',                                            1),
(4, 'Do products come with original box and papers?',    'Yes. All watches are delivered in their original manufacturer packaging complete with warranty cards, instruction manuals, and any included accessories.',                                                                                             2),
(4, 'Can I compare multiple watches before buying?',     'Yes. Use the Compare feature on any product page to place up to four timepieces side by side and evaluate their specifications.',                                                                                                                   3),
(4, 'Do you sell limited-edition pieces?',               'Yes. We carry a selection of limited-edition and collector pieces. These are listed with their edition numbers and available stock. We recommend signing up to our newsletter for early-access alerts.',                                            4),
(5, 'How do I create a Commerza account?',               'Click Sign Up in the top navigation bar and fill in your name, email, phone number, and a secure password. Your account gives you order history, wishlist, saved addresses, and faster checkout.',                                            1),
(5, 'What if I forget my password?',                     'Click Forgot Password on the login page, enter your registered email address, and we will send you a password reset link valid for 1 hour.',                                                                                                          2),
(5, 'How do I update my account details?',               'Log in and go to My Account. From there you can update your name, email, phone number, shipping addresses, and profile picture.',                                                                                                                   3),
(5, 'How is my personal data used?',                     'Your data is used solely to process orders, personalise your experience, and communicate with you about your purchases. We never sell your information to third parties. See our Privacy Policy for full details.',                               4);

-- ============================================================
-- 14. ADMIN USERS
-- Separate admin accounts - completely isolated from frontend users
-- ============================================================

CREATE TABLE `admin_users` (
  `id`                  int(11)      NOT NULL AUTO_INCREMENT,
  `full_name`           varchar(100) NOT NULL,
  `email`               varchar(150) NOT NULL UNIQUE,
  `password_hash`       varchar(255) NOT NULL,
  `role`                enum('super_admin','admin','moderator') NOT NULL DEFAULT 'admin',
  `profile_picture`     varchar(255) DEFAULT NULL,
  `reset_token`         varchar(255) DEFAULT NULL,
  `reset_token_expiry`  datetime     DEFAULT NULL,
  `last_login_at`       datetime     DEFAULT NULL,
  `last_login_ip`       varchar(45)  DEFAULT NULL,
  `is_active`           tinyint(1)   NOT NULL DEFAULT 1,
  `created_at`          timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at`          timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default password: Commerza@2026  (change immediately after first login)
INSERT INTO `admin_users` (`full_name`, `email`, `password_hash`, `role`) VALUES
('Commerza Admin', 'commerza.ahmer@gmail.com', '$2b$12$OwYPaS2VbAP2xrbqV.eJA.rh0tO9mKk6qAfoVzioOhRdwoKDprbAS', 'super_admin');

-- ============================================================
-- 15. USER SESSIONS
-- Server-side session tokens for authenticated frontend users
-- ============================================================

CREATE TABLE `user_sessions` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     int(11)      NOT NULL,
  `token`       varchar(255) NOT NULL UNIQUE COMMENT 'Secure random token (sha256 / uuid)',
  `ip_address`  varchar(45)  DEFAULT NULL,
  `user_agent`  varchar(255) DEFAULT NULL,
  `expires_at`  datetime     NOT NULL,
  `created_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 16. NOTIFICATIONS
-- Admin dashboard notification feed
-- ============================================================

CREATE TABLE `notifications` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `admin_id`    int(11)      DEFAULT NULL COMMENT 'NULL = broadcast to all admins',
  `type`        varchar(60)  NOT NULL COMMENT 'new_order | new_message | low_stock | new_user',
  `title`       varchar(150) NOT NULL,
  `body`        text         DEFAULT NULL,
  `link_url`    varchar(255) DEFAULT NULL COMMENT 'Deep link inside admin panel',
  `is_read`     tinyint(1)   NOT NULL DEFAULT 0,
  `created_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 17. EMAIL TEMPLATES
-- Admin email center - reusable campaign templates
-- (matches commerza_email_templates localStorage key)
-- ============================================================

CREATE TABLE `email_templates` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `name`       varchar(100) NOT NULL COMMENT 'Template display name',
  `subject`    varchar(255) NOT NULL COMMENT 'Email subject line',
  `body`       text         NOT NULL COMMENT 'Plain-text or HTML body',
  `is_default` tinyint(1)   NOT NULL DEFAULT 0 COMMENT 'System default (non-deletable)',
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `email_templates` (`name`, `subject`, `body`, `is_default`) VALUES
('Welcome to Commerza Circle', 'Welcome to the Commerza Circle',
'Hi there,

Thanks for joining the Commerza Circle. You will get early access to launches, exclusive offers, and collector stories.

- The Commerza Team', 1),
('New Arrivals Drop', 'New arrivals just landed',
'Hello,

Our latest watches are live now. Explore the newest drops and find your next statement piece.

Shop now: https://commerza.com

- The Commerza Team', 1),
('Limited Time Offer', 'Limited-time offer inside',
'Hi,

For a limited time, enjoy exclusive pricing on selected collections. The offer ends soon, so do not miss out.

- The Commerza Team', 1),
('Back in Stock Alert', 'Back in stock: your favorites',
'Hello,

Good news! Popular watches are back in stock. Quantities are limited, so grab yours soon.

- The Commerza Team', 1),
('Order Update', 'Your Commerza order update',
'Hi,

We wanted to share a quick update about your order. If you have any questions, reply to this email and our team will help.

- The Commerza Team', 1),
('Shipping Delay Notice', 'Shipping update from Commerza',
'Hello,

We are experiencing a short shipping delay due to high demand. Your order is still on the way, and we will share tracking soon.

- The Commerza Team', 1),
('VIP Early Access', 'VIP early access is live',
'Hi,

As a Commerza subscriber, you get early access to our newest collection. Take a first look before the public launch.

- The Commerza Team', 1),
('Holiday Gift Guide', 'Holiday gift picks from Commerza',
'Hello,

Need a gift that stands out? Our holiday guide highlights the best watches for every style and budget.

- The Commerza Team', 1),
('Feedback Request', 'We would love your feedback',
'Hi,

Your feedback helps us improve. If you have a moment, let us know what you love and what we can do better.

- The Commerza Team', 1),
('Monthly Newsletter', 'Your Commerza monthly roundup',
'Hello,

Here is your monthly roundup with new releases, staff picks, and limited offers.

- The Commerza Team', 1),
('Support Reply', 'Re: Support request',
'Hi,

Thanks for reaching out to Commerza support. We are looking into this and will update you shortly.

If you can share your order ID and any extra details, we can help faster.

- Commerza Support', 1);

-- ============================================================
-- 18. EMAIL OUTBOX
-- Log of every campaign email sent by admin
-- (matches commerza_email_outbox localStorage key)
-- ============================================================

CREATE TABLE `email_outbox` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `admin_id`     int(11)      DEFAULT NULL COMMENT 'Which admin sent it',
  `template_id`  int(11)      DEFAULT NULL COMMENT 'Template used (NULL = custom)',
  `subject`      varchar(255) NOT NULL,
  `body`         text         NOT NULL,
  `recipient_count` int(11)  NOT NULL DEFAULT 0,
  `source`       varchar(60)  DEFAULT 'all' COMMENT 'all | subscribers | customers | manual',
  `status`       enum('queued','sent','failed') NOT NULL DEFAULT 'sent',
  `sent_at`      timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id`    (`admin_id`),
  KEY `template_id` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 19. EMAIL SUPPRESSED
-- Emails that have unsubscribed or been manually blocked from campaigns
-- (matches commerza_email_suppressed localStorage key)
-- ============================================================

CREATE TABLE `email_suppressed` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `email`        varchar(150) NOT NULL UNIQUE,
  `reason`       enum('unsubscribed','bounced','complained','manual') NOT NULL DEFAULT 'unsubscribed',
  `suppressed_at` timestamp   NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- 20. SOCIAL LINKS
-- Admin Website section - CRUD social media entries with icon support
-- ============================================================

CREATE TABLE `social_links` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `label`      varchar(50)  NOT NULL COMMENT 'e.g. Facebook, Instagram',
  `url`        varchar(255) NOT NULL,
  `icon`       varchar(80)  DEFAULT NULL COMMENT 'Bootstrap icon class, e.g. bi bi-facebook',
  `sort_order` int(11)      NOT NULL DEFAULT 0,
  `is_active`  tinyint(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `social_links` (`label`, `url`, `icon`, `sort_order`) VALUES
('Facebook',  'https://www.facebook.com/commerza.ahmer',  'bi bi-facebook',  1),
('X',         'https://x.com/commerza_ahmer',             'bi bi-twitter',   2),
('Instagram', 'https://www.instagram.com/commerza.ahmer', 'bi bi-instagram', 3);

-- ============================================================
-- 21. PAGE META
-- Per-page SEO title and description, managed from admin Homepage section
-- (matches commerza_page_meta localStorage key)
-- ============================================================

CREATE TABLE `page_meta` (
  `id`               int(11)      NOT NULL AUTO_INCREMENT,
  `page`             varchar(100) NOT NULL UNIQUE COMMENT 'Filename, e.g. index.html or about.html',
  `meta_title`       varchar(150) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `updated_at`       timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `page_meta` (`page`, `meta_title`, `meta_description`) VALUES
('index.html',          'Commerza | Full-Stack Ecommerce',                   'Commerza brings you premium automatic watches - crafted with elegant leather, gold dials, and modern design.'),
('about.html',          'About Us | Commerza',                               'Learn about Commerza''s story, vision, and team.'),
('contact.html',        'Contact Us | Commerza',                             'Get in touch with the Commerza team.'),
('shop-category-a.html','Premium Watches | Commerza',                        'Explore mechanical and smart timepieces.'),
('shop-category-b.html','Lifestyle Watches | Commerza',                      'Browse minimalist and sports timepieces.'),
('cart.html',           'Your Cart | Commerza',                              'Review and complete your Commerza order.'),
('wishlist.html',       'Wishlist | Commerza',                               'Your saved watches on Commerza.'),
('order-tracking.html', 'Track Your Order | Commerza',                       'Track the status of your Commerza order.'),
('returns.html',        'Returns & Refunds | Commerza',                      'Hassle-free returns within 30 days.'),
('warranty.html',       'Warranty | Commerza',                               'Commerza warranty information and claims.'),
('faq.html',            'FAQ | Commerza',                                    'Frequently asked questions about Commerza.'),
('shipping.html',       'Shipping | Commerza',                               'Shipping rates and delivery information.'),
('account.html',        'My Account | Commerza',                             'Manage your Commerza profile and orders.'),
('products.html',       'All Products | Commerza',                           'Browse the full Commerza watch collection.'),
('compare.html',        'Compare Watches | Commerza',                        'Compare luxury timepieces side by side.'),
('login.html',          'Login | Commerza',                                  'Sign in to your Commerza account.'),
('signup.html',         'Create Account | Commerza',                         'Join Commerza and start your luxury journey.'),
('forgot-password.html','Forgot Password | Commerza',                        'Reset your Commerza account password.');

-- ============================================================
-- FOREIGN KEY CONSTRAINTS (Extended Tables)
-- ============================================================

-- cart
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_user`    FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE SET NULL;

-- cart_items
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_ci_cart`      FOREIGN KEY (`cart_id`)    REFERENCES `cart`     (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ci_product`   FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

-- orders
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_ord_user`     FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE SET NULL;

-- order_items
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_oi_order`     FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_oi_product`   FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

-- wishlist
ALTER TABLE `wishlist`
  ADD CONSTRAINT `fk_wl_user`      FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE;

-- wishlist_items
ALTER TABLE `wishlist_items`
  ADD CONSTRAINT `fk_wli_wishlist` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlist` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_wli_product`  FOREIGN KEY (`product_id`)  REFERENCES `products` (`id`) ON DELETE CASCADE;

-- faq
ALTER TABLE `faq`
  ADD CONSTRAINT `fk_faq_cat`      FOREIGN KEY (`category_id`) REFERENCES `faq_categories` (`id`) ON DELETE SET NULL;

-- user_sessions
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_us_user`      FOREIGN KEY (`user_id`)    REFERENCES `users`    (`id`) ON DELETE CASCADE;

-- notifications
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_admin`  FOREIGN KEY (`admin_id`)   REFERENCES `admin_users` (`id`) ON DELETE CASCADE;

-- email_outbox
ALTER TABLE `email_outbox`
  ADD CONSTRAINT `fk_eo_admin`     FOREIGN KEY (`admin_id`)    REFERENCES `admin_users`     (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_eo_template`  FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
