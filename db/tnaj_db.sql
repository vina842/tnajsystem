-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2026 at 04:34 AM
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
-- Database: `tnaj_db`
--
CREATE DATABASE IF NOT EXISTS `tnaj_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `tnaj_db`;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brand`
--

DROP TABLE IF EXISTS `brand`;
CREATE TABLE `brand` (
  `brand_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `brand`
--

INSERT INTO `brand` (`brand_id`, `name`) VALUES
(1, 'Artist\'s Choice'),
(2, 'Camel'),
(4, 'Faber-Castell'),
(3, 'Paper One');

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

DROP TABLE IF EXISTS `category`;
CREATE TABLE `category` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `name`) VALUES
(2, 'Brushes'),
(5, 'Markers'),
(1, 'Paint'),
(3, 'Paper'),
(4, 'Pencils');

-- --------------------------------------------------------

--
-- Table structure for table `customer`
--

DROP TABLE IF EXISTS `customer`;
CREATE TABLE `customer` (
  `customer_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(20) DEFAULT NULL,
  `lname` varchar(50) NOT NULL,
  `fname` varchar(50) NOT NULL,
  `addressline` varchar(100) DEFAULT NULL,
  `town` varchar(50) DEFAULT NULL,
  `zipcode` varchar(10) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `loyalty_points` int(11) NOT NULL DEFAULT 0,
  `user_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_sales`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `daily_sales`;
CREATE TABLE `daily_sales` (
`sale_date` date
,`orders` bigint(21)
,`items_sold` decimal(32,0)
,`total_sales` decimal(39,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `inventory_report`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `inventory_report`;
CREATE TABLE `inventory_report` (
`item_id` int(10) unsigned
,`description` varchar(100)
,`brand` varchar(50)
,`category` varchar(50)
,`quantity` int(11)
,`reorder_threshold` int(11)
,`stock_status` varchar(12)
);

-- --------------------------------------------------------

--
-- Table structure for table `item`
--

DROP TABLE IF EXISTS `item`;
CREATE TABLE `item` (
  `item_id` int(10) UNSIGNED NOT NULL,
  `description` varchar(100) NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `brand_id` int(10) UNSIGNED DEFAULT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `cost_price` decimal(7,2) NOT NULL DEFAULT 0.00,
  `sell_price` decimal(7,2) NOT NULL DEFAULT 0.00,
  `img_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item`
--

INSERT INTO `item` (`item_id`, `description`, `category_id`, `brand_id`, `supplier_id`, `cost_price`, `sell_price`, `img_path`) VALUES
(1, 'Acrylic Paint Set 12 colors', 1, 1, 1, 120.00, 180.00, 'images/default.jpg'),
(2, 'Watercolor Brush Set', 2, 2, 1, 60.00, 95.00, 'images/default.jpg'),
(3, 'Sketch Pad A4', 3, 3, 1, 35.00, 55.00, 'images/default.jpg'),
(4, 'Colored Pencils 24pcs', 4, 4, 1, 150.00, 220.00, 'images/default.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `lista_account`
--

DROP TABLE IF EXISTS `lista_account`;
CREATE TABLE `lista_account` (
  `lista_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `credit_limit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `lista_balances`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `lista_balances`;
CREATE TABLE `lista_balances` (
`lista_id` int(10) unsigned
,`customer_id` int(10) unsigned
,`fname` varchar(50)
,`lname` varchar(50)
,`credit_limit` decimal(10,2)
,`balance` decimal(10,2)
,`available_credit` decimal(11,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `lista_transaction`
--

DROP TABLE IF EXISTS `lista_transaction`;
CREATE TABLE `lista_transaction` (
  `lista_trans_id` int(10) UNSIGNED NOT NULL,
  `lista_id` int(10) UNSIGNED NOT NULL,
  `orderinfo_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('utang','payment') NOT NULL DEFAULT 'utang',
  `amount` decimal(10,2) NOT NULL,
  `note` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loyalty_transaction`
--

DROP TABLE IF EXISTS `loyalty_transaction`;
CREATE TABLE `loyalty_transaction` (
  `loyalty_trans_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `orderinfo_id` int(10) UNSIGNED DEFAULT NULL,
  `points` int(11) NOT NULL,
  `note` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `orderdetails`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `orderdetails`;
CREATE TABLE `orderdetails` (
`fname` varchar(100)
,`lname` varchar(50)
,`addressline` varchar(100)
,`town` varchar(50)
,`phone` varchar(20)
,`zipcode` varchar(10)
,`orderinfo_id` int(10) unsigned
,`date_placed` datetime
,`status` varchar(20)
,`description` varchar(100)
,`quantity` int(11)
,`sell_price` decimal(7,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `orderinfo`
--

DROP TABLE IF EXISTS `orderinfo`;
CREATE TABLE `orderinfo` (
  `orderinfo_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `walkin_name` varchar(100) DEFAULT NULL,
  `order_type` enum('storefront','walk_in') NOT NULL DEFAULT 'storefront',
  `payment_method` enum('cash','lista') NOT NULL DEFAULT 'cash',
  `date_placed` datetime NOT NULL,
  `date_shipped` datetime DEFAULT NULL,
  `shipping` decimal(7,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'Processing'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orderline`
--

DROP TABLE IF EXISTS `orderline`;
CREATE TABLE `orderline` (
  `orderinfo_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `salesperorder`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `salesperorder`;
CREATE TABLE `salesperorder` (
`orderId` int(10) unsigned
,`total` decimal(39,2)
,`status` varchar(20)
);

-- --------------------------------------------------------

--
-- Table structure for table `stock`
--

DROP TABLE IF EXISTS `stock`;
CREATE TABLE `stock` (
  `item_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `reorder_threshold` int(11) NOT NULL DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock`
--

INSERT INTO `stock` (`item_id`, `quantity`, `reorder_threshold`) VALUES
(1, 50, 10),
(2, 50, 10),
(3, 50, 10),
(4, 50, 10);

--
-- Triggers `stock`
--
DROP TRIGGER IF EXISTS `trg_stock_after_insert`;
DELIMITER $$
CREATE TRIGGER `trg_stock_after_insert` AFTER INSERT ON `stock` FOR EACH ROW INSERT INTO stock_movement (item_id, qty_change, qty_after, movement_type)
  SELECT NEW.item_id, NEW.quantity, NEW.quantity, 'in' FROM DUAL WHERE NEW.quantity <> 0
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_stock_after_update`;
DELIMITER $$
CREATE TRIGGER `trg_stock_after_update` AFTER UPDATE ON `stock` FOR EACH ROW INSERT INTO stock_movement (item_id, qty_change, qty_after, movement_type)
  SELECT NEW.item_id, NEW.quantity - OLD.quantity, NEW.quantity,
         IF(NEW.quantity < OLD.quantity, 'out', 'in')
  FROM DUAL WHERE NEW.quantity <> OLD.quantity
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `stock_movement`
--

DROP TABLE IF EXISTS `stock_movement`;
CREATE TABLE `stock_movement` (
  `movement_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `qty_change` int(11) NOT NULL,
  `qty_after` int(11) NOT NULL,
  `movement_type` enum('in','out') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_movement`
--

INSERT INTO `stock_movement` (`movement_id`, `item_id`, `qty_change`, `qty_after`, `movement_type`, `created_at`) VALUES
(1, 1, 50, 50, 'in', '2026-10-01 10:20:12'),
(2, 2, 50, 50, 'in', '2026-10-01 10:20:12'),
(3, 3, 50, 50, 'in', '2026-10-01 10:20:12'),
(4, 4, 50, 50, 'in', '2026-10-01 10:20:12');

-- --------------------------------------------------------

--
-- Table structure for table `supplier`
--

DROP TABLE IF EXISTS `supplier`;
CREATE TABLE `supplier` (
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supplier`
--

INSERT INTO `supplier` (`supplier_id`, `name`, `contact_name`, `phone`, `address`) VALUES
(1, 'Sample Art Supply Trading', 'Contact Person', '09170000000', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `status`, `role`, `created_at`) VALUES
(1, 'admin@joziel.com', '2y12$A.wz49vfpoXt.sXcmgE.yeNuZLOkbR0Mb1P8qe/p11VDr1weDQPI2', 'active', 'admin', '2026-10-01 10:19:44');

-- --------------------------------------------------------

--
-- Structure for view `daily_sales`
--
DROP TABLE IF EXISTS `daily_sales`;

DROP VIEW IF EXISTS `daily_sales`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_sales`  AS SELECT cast(`o`.`date_placed` as date) AS `sale_date`, count(distinct `o`.`orderinfo_id`) AS `orders`, sum(`ol`.`quantity`) AS `items_sold`, sum(`ol`.`quantity` * `i`.`sell_price`) AS `total_sales` FROM ((`orderinfo` `o` join `orderline` `ol` on(`ol`.`orderinfo_id` = `o`.`orderinfo_id`)) join `item` `i` on(`i`.`item_id` = `ol`.`item_id`)) WHERE `o`.`status` <> 'Canceled' GROUP BY cast(`o`.`date_placed` as date) ;

-- --------------------------------------------------------

--
-- Structure for view `inventory_report`
--
DROP TABLE IF EXISTS `inventory_report`;

DROP VIEW IF EXISTS `inventory_report`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `inventory_report`  AS SELECT `i`.`item_id` AS `item_id`, `i`.`description` AS `description`, `b`.`name` AS `brand`, `c`.`name` AS `category`, `s`.`quantity` AS `quantity`, `s`.`reorder_threshold` AS `reorder_threshold`, CASE WHEN `s`.`quantity` = 0 THEN 'Out of stock' WHEN `s`.`quantity` <= `s`.`reorder_threshold` THEN 'Reorder' ELSE 'OK' END AS `stock_status` FROM (((`item` `i` join `stock` `s` on(`s`.`item_id` = `i`.`item_id`)) left join `brand` `b` on(`b`.`brand_id` = `i`.`brand_id`)) left join `category` `c` on(`c`.`category_id` = `i`.`category_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `lista_balances`
--
DROP TABLE IF EXISTS `lista_balances`;

DROP VIEW IF EXISTS `lista_balances`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `lista_balances`  AS SELECT `la`.`lista_id` AS `lista_id`, `c`.`customer_id` AS `customer_id`, `c`.`fname` AS `fname`, `c`.`lname` AS `lname`, `la`.`credit_limit` AS `credit_limit`, `la`.`balance` AS `balance`, `la`.`credit_limit`- `la`.`balance` AS `available_credit` FROM (`lista_account` `la` join `customer` `c` on(`c`.`customer_id` = `la`.`customer_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `orderdetails`
--
DROP TABLE IF EXISTS `orderdetails`;

DROP VIEW IF EXISTS `orderdetails`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `orderdetails`  AS SELECT coalesce(`c`.`fname`,`oi`.`walkin_name`) AS `fname`, coalesce(`c`.`lname`,'') AS `lname`, `c`.`addressline` AS `addressline`, `c`.`town` AS `town`, `c`.`phone` AS `phone`, `c`.`zipcode` AS `zipcode`, `oi`.`orderinfo_id` AS `orderinfo_id`, `oi`.`date_placed` AS `date_placed`, `oi`.`status` AS `status`, `i`.`description` AS `description`, `ol`.`quantity` AS `quantity`, `i`.`sell_price` AS `sell_price` FROM (((`orderinfo` `oi` left join `customer` `c` on(`c`.`customer_id` = `oi`.`customer_id`)) join `orderline` `ol` on(`oi`.`orderinfo_id` = `ol`.`orderinfo_id`)) join `item` `i` on(`ol`.`item_id` = `i`.`item_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `salesperorder`
--
DROP TABLE IF EXISTS `salesperorder`;

DROP VIEW IF EXISTS `salesperorder`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `salesperorder`  AS SELECT `o`.`orderinfo_id` AS `orderId`, sum(`i`.`sell_price` * `ol`.`quantity`) AS `total`, `o`.`status` AS `status` FROM ((`orderinfo` `o` join `orderline` `ol` on(`ol`.`orderinfo_id` = `o`.`orderinfo_id`)) join `item` `i` on(`i`.`item_id` = `ol`.`item_id`)) GROUP BY `o`.`orderinfo_id`, `o`.`status` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_log_user` (`user_id`);

--
-- Indexes for table `brand`
--
ALTER TABLE `brand`
  ADD PRIMARY KEY (`brand_id`),
  ADD UNIQUE KEY `uq_brand_name` (`name`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_category_name` (`name`);

--
-- Indexes for table `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `idx_customer_user` (`user_id`);

--
-- Indexes for table `item`
--
ALTER TABLE `item`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_item_category` (`category_id`),
  ADD KEY `idx_item_brand` (`brand_id`),
  ADD KEY `idx_item_supplier` (`supplier_id`);

--
-- Indexes for table `lista_account`
--
ALTER TABLE `lista_account`
  ADD PRIMARY KEY (`lista_id`),
  ADD UNIQUE KEY `uq_lista_customer` (`customer_id`);

--
-- Indexes for table `lista_transaction`
--
ALTER TABLE `lista_transaction`
  ADD PRIMARY KEY (`lista_trans_id`),
  ADD KEY `idx_lt_lista` (`lista_id`),
  ADD KEY `idx_lt_order` (`orderinfo_id`);

--
-- Indexes for table `loyalty_transaction`
--
ALTER TABLE `loyalty_transaction`
  ADD PRIMARY KEY (`loyalty_trans_id`),
  ADD KEY `idx_loy_customer` (`customer_id`),
  ADD KEY `fk_loy_order` (`orderinfo_id`);

--
-- Indexes for table `orderinfo`
--
ALTER TABLE `orderinfo`
  ADD PRIMARY KEY (`orderinfo_id`),
  ADD KEY `idx_orderinfo_customer` (`customer_id`),
  ADD KEY `idx_orderinfo_date` (`date_placed`);

--
-- Indexes for table `orderline`
--
ALTER TABLE `orderline`
  ADD PRIMARY KEY (`orderinfo_id`,`item_id`),
  ADD KEY `idx_orderline_item` (`item_id`);

--
-- Indexes for table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `stock_movement`
--
ALTER TABLE `stock_movement`
  ADD PRIMARY KEY (`movement_id`),
  ADD KEY `idx_sm_item` (`item_id`),
  ADD KEY `idx_sm_date` (`created_at`);

--
-- Indexes for table `supplier`
--
ALTER TABLE `supplier`
  ADD PRIMARY KEY (`supplier_id`),
  ADD UNIQUE KEY `uq_supplier_name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brand`
--
ALTER TABLE `brand`
  MODIFY `brand_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customer`
--
ALTER TABLE `customer`
  MODIFY `customer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `item`
--
ALTER TABLE `item`
  MODIFY `item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `lista_account`
--
ALTER TABLE `lista_account`
  MODIFY `lista_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lista_transaction`
--
ALTER TABLE `lista_transaction`
  MODIFY `lista_trans_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loyalty_transaction`
--
ALTER TABLE `loyalty_transaction`
  MODIFY `loyalty_trans_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orderinfo`
--
ALTER TABLE `orderinfo`
  MODIFY `orderinfo_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_movement`
--
ALTER TABLE `stock_movement`
  MODIFY `movement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `supplier`
--
ALTER TABLE `supplier`
  MODIFY `supplier_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `customer`
--
ALTER TABLE `customer`
  ADD CONSTRAINT `fk_customer_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `item`
--
ALTER TABLE `item`
  ADD CONSTRAINT `fk_item_brand` FOREIGN KEY (`brand_id`) REFERENCES `brand` (`brand_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_item_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_item_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`supplier_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `lista_account`
--
ALTER TABLE `lista_account`
  ADD CONSTRAINT `fk_lista_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE;

--
-- Constraints for table `lista_transaction`
--
ALTER TABLE `lista_transaction`
  ADD CONSTRAINT `fk_lt_lista` FOREIGN KEY (`lista_id`) REFERENCES `lista_account` (`lista_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lt_order` FOREIGN KEY (`orderinfo_id`) REFERENCES `orderinfo` (`orderinfo_id`) ON DELETE SET NULL;

--
-- Constraints for table `loyalty_transaction`
--
ALTER TABLE `loyalty_transaction`
  ADD CONSTRAINT `fk_loy_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_loy_order` FOREIGN KEY (`orderinfo_id`) REFERENCES `orderinfo` (`orderinfo_id`) ON DELETE SET NULL;

--
-- Constraints for table `orderinfo`
--
ALTER TABLE `orderinfo`
  ADD CONSTRAINT `fk_orderinfo_customer` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`customer_id`);

--
-- Constraints for table `orderline`
--
ALTER TABLE `orderline`
  ADD CONSTRAINT `fk_orderline_item` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`),
  ADD CONSTRAINT `fk_orderline_order` FOREIGN KEY (`orderinfo_id`) REFERENCES `orderinfo` (`orderinfo_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `fk_stock_item` FOREIGN KEY (`item_id`) REFERENCES `item` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
