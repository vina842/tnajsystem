-- Tindahan ni Aling Jorhina - database (v3)
-- phpMyAdmin: Import tab -> choose this file -> Import.
-- Creates the database tnajart, which matches includes/config.php.
-- WARNING: importing drops and recreates every table, so existing data is wiped.

CREATE DATABASE IF NOT EXISTS tnajart
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE tnajart;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS salesperorder;
DROP VIEW  IF EXISTS orderdetails;
DROP VIEW  IF EXISTS daily_sales;
DROP VIEW  IF EXISTS inventory_report;
DROP VIEW  IF EXISTS lista_balances;
DROP TABLE IF EXISTS activity_log;
DROP TABLE IF EXISTS loyalty_transaction;
DROP TABLE IF EXISTS stock_movement;
DROP TABLE IF EXISTS lista_transaction;
DROP TABLE IF EXISTS lista_account;
DROP TABLE IF EXISTS orderline;
DROP TABLE IF EXISTS orderinfo;
DROP TABLE IF EXISTS stock;
DROP TABLE IF EXISTS item;
DROP TABLE IF EXISTS supplier;
DROP TABLE IF EXISTS brand;
DROP TABLE IF EXISTS category;
DROP TABLE IF EXISTS customer;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  user_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email      VARCHAR(100) NOT NULL,
  password   VARCHAR(255) NOT NULL,
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  role       ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE customer (
  customer_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title          VARCHAR(20)  DEFAULT NULL,
  lname          VARCHAR(50)  NOT NULL,
  fname          VARCHAR(50)  NOT NULL,
  addressline    VARCHAR(100) DEFAULT NULL,
  town           VARCHAR(50)  DEFAULT NULL,
  zipcode        VARCHAR(10)  DEFAULT NULL,
  phone          VARCHAR(20)  DEFAULT NULL,
  loyalty_points INT NOT NULL DEFAULT 0,
  user_id        INT UNSIGNED NOT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  UNIQUE KEY uq_customer_user (user_id),
  CONSTRAINT fk_customer_user FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE category (
  category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(50) NOT NULL,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_category_name (name)
) ENGINE=InnoDB;

CREATE TABLE brand (
  brand_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name     VARCHAR(50) NOT NULL,
  PRIMARY KEY (brand_id),
  UNIQUE KEY uq_brand_name (name)
) ENGINE=InnoDB;

CREATE TABLE supplier (
  supplier_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(100) NOT NULL,
  contact_name VARCHAR(100) DEFAULT NULL,
  phone        VARCHAR(20)  DEFAULT NULL,
  address      VARCHAR(150) DEFAULT NULL,
  PRIMARY KEY (supplier_id),
  UNIQUE KEY uq_supplier_name (name)
) ENGINE=InnoDB;

CREATE TABLE item (
  item_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  description VARCHAR(100) NOT NULL,
  category_id INT UNSIGNED DEFAULT NULL,
  brand_id    INT UNSIGNED DEFAULT NULL,
  supplier_id INT UNSIGNED DEFAULT NULL,
  cost_price  DECIMAL(7,2) NOT NULL DEFAULT 0.00,
  sell_price  DECIMAL(7,2) NOT NULL DEFAULT 0.00,
  img_path    VARCHAR(255) DEFAULT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (item_id),
  KEY idx_item_category (category_id),
  KEY idx_item_brand (brand_id),
  KEY idx_item_supplier (supplier_id),
  CONSTRAINT fk_item_category FOREIGN KEY (category_id) REFERENCES category (category_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_item_brand FOREIGN KEY (brand_id) REFERENCES brand (brand_id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_item_supplier FOREIGN KEY (supplier_id) REFERENCES supplier (supplier_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE stock (
  item_id           INT UNSIGNED NOT NULL,
  quantity          INT NOT NULL DEFAULT 0,
  reorder_threshold INT NOT NULL DEFAULT 10,
  PRIMARY KEY (item_id),
  CONSTRAINT fk_stock_item FOREIGN KEY (item_id) REFERENCES item (item_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE stock_movement (
  movement_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_id       INT UNSIGNED NOT NULL,
  qty_change    INT NOT NULL,
  qty_after     INT NOT NULL,
  movement_type ENUM('in','out') NOT NULL,
  reason        VARCHAR(30) NOT NULL DEFAULT 'adjustment',
  ref_id        INT UNSIGNED DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (movement_id),
  KEY idx_sm_item (item_id),
  KEY idx_sm_date (created_at)
) ENGINE=InnoDB;

CREATE TABLE orderinfo (
  orderinfo_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id    INT UNSIGNED DEFAULT NULL,
  walkin_name    VARCHAR(100) DEFAULT NULL,
  order_type     ENUM('storefront','walk_in') NOT NULL DEFAULT 'storefront',
  payment_method ENUM('cash','lista') NOT NULL DEFAULT 'cash',
  payment_status ENUM('unpaid','paid','on_lista','void') NOT NULL DEFAULT 'unpaid',
  status         VARCHAR(20) NOT NULL DEFAULT 'Pending',
  total_amount   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  note           VARCHAR(200) DEFAULT NULL,
  date_placed    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_completed DATETIME DEFAULT NULL,
  created_by     INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (orderinfo_id),
  KEY idx_orderinfo_customer (customer_id),
  KEY idx_orderinfo_date (date_placed),
  KEY idx_orderinfo_status (status),
  CONSTRAINT fk_orderinfo_customer FOREIGN KEY (customer_id) REFERENCES customer (customer_id),
  CONSTRAINT fk_orderinfo_user FOREIGN KEY (created_by) REFERENCES users (user_id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE orderline (
  orderinfo_id INT UNSIGNED NOT NULL,
  item_id      INT UNSIGNED NOT NULL,
  quantity     INT NOT NULL,
  unit_price   DECIMAL(7,2) NOT NULL,
  PRIMARY KEY (orderinfo_id, item_id),
  KEY idx_orderline_item (item_id),
  CONSTRAINT fk_orderline_order FOREIGN KEY (orderinfo_id) REFERENCES orderinfo (orderinfo_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_orderline_item FOREIGN KEY (item_id) REFERENCES item (item_id)
) ENGINE=InnoDB;

CREATE TABLE lista_account (
  lista_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id  INT UNSIGNED NOT NULL,
  credit_limit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  balance      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status       ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (lista_id),
  UNIQUE KEY uq_lista_customer (customer_id),
  CONSTRAINT fk_lista_customer FOREIGN KEY (customer_id) REFERENCES customer (customer_id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lista_transaction (
  lista_trans_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lista_id       INT UNSIGNED NOT NULL,
  orderinfo_id   INT UNSIGNED DEFAULT NULL,
  type           ENUM('utang','payment','reversal') NOT NULL DEFAULT 'utang',
  amount         DECIMAL(10,2) NOT NULL,
  balance_after  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  note           VARCHAR(150) DEFAULT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (lista_trans_id),
  KEY idx_lt_lista (lista_id),
  KEY idx_lt_order (orderinfo_id),
  CONSTRAINT fk_lt_lista FOREIGN KEY (lista_id) REFERENCES lista_account (lista_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_lt_order FOREIGN KEY (orderinfo_id) REFERENCES orderinfo (orderinfo_id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE loyalty_transaction (
  loyalty_trans_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id      INT UNSIGNED NOT NULL,
  orderinfo_id     INT UNSIGNED DEFAULT NULL,
  points           INT NOT NULL,
  note             VARCHAR(150) DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (loyalty_trans_id),
  KEY idx_loy_customer (customer_id),
  CONSTRAINT fk_loy_customer FOREIGN KEY (customer_id) REFERENCES customer (customer_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_loy_order FOREIGN KEY (orderinfo_id) REFERENCES orderinfo (orderinfo_id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE activity_log (
  log_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED DEFAULT NULL,
  action     VARCHAR(50)  NOT NULL,
  details    VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY idx_log_user (user_id),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users (user_id)
    ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TRIGGER trg_stock_after_insert AFTER INSERT ON stock
FOR EACH ROW
  INSERT INTO stock_movement (item_id, qty_change, qty_after, movement_type, reason, ref_id)
  SELECT NEW.item_id, NEW.quantity, NEW.quantity, 'in', COALESCE(@stock_reason, 'opening stock'), @stock_ref
  FROM DUAL WHERE NEW.quantity <> 0;

CREATE TRIGGER trg_stock_after_update AFTER UPDATE ON stock
FOR EACH ROW
  INSERT INTO stock_movement (item_id, qty_change, qty_after, movement_type, reason, ref_id)
  SELECT NEW.item_id, NEW.quantity - OLD.quantity, NEW.quantity,
         IF(NEW.quantity < OLD.quantity, 'out', 'in'),
         COALESCE(@stock_reason, 'adjustment'), @stock_ref
  FROM DUAL WHERE NEW.quantity <> OLD.quantity;

CREATE VIEW daily_sales AS
SELECT DATE(o.date_placed) AS sale_date,
       COUNT(DISTINCT o.orderinfo_id) AS orders,
       SUM(ol.quantity) AS items_sold,
       SUM(ol.quantity * ol.unit_price) AS total_sales
FROM orderinfo o
JOIN orderline ol ON ol.orderinfo_id = o.orderinfo_id
WHERE o.status <> 'Canceled'
GROUP BY DATE(o.date_placed);

CREATE VIEW inventory_report AS
SELECT i.item_id, i.description, i.is_active, i.cost_price, i.sell_price,
       b.name AS brand, c.name AS category,
       s.quantity, s.reorder_threshold,
       CASE WHEN s.quantity <= 0 THEN 'Out of stock'
            WHEN s.quantity <= s.reorder_threshold THEN 'Reorder'
            ELSE 'OK' END AS stock_status
FROM item i
JOIN stock s         ON s.item_id = i.item_id
LEFT JOIN brand b    ON b.brand_id = i.brand_id
LEFT JOIN category c ON c.category_id = i.category_id;

CREATE VIEW lista_balances AS
SELECT la.lista_id, c.customer_id, c.fname, c.lname, la.status,
       la.credit_limit, la.balance,
       la.credit_limit - la.balance AS available_credit
FROM lista_account la
JOIN customer c ON c.customer_id = la.customer_id;

-- Starter data
-- Admin:         admin@shop.com  /  admin123
-- Demo customer: suki@shop.com   /  suki123   (has a suki list, limit 1,500)
-- Change or delete these accounts before real use.
INSERT INTO users (email, password, status, role) VALUES
('admin@shop.com', '$2y$12$A.wz49vfpoXt.sXcmgE.yeNuZLOkbR0Mb1P8qe/p11VDr1weDQPI2', 'active', 'admin'),
('suki@shop.com',  '$2y$12$8VVQkxaBTWyDMGqXVQRRE.II5WUlxqlFIf6FSwq.28wqAHMEVqMJm', 'active', 'user');

INSERT INTO customer (fname, lname, addressline, town, zipcode, phone, user_id) VALUES
('Maria', 'Santos', '12 Mabini St.', 'Taguig', '1630', '09171234567', 2);

INSERT INTO lista_account (customer_id, credit_limit, balance, status) VALUES (1, 1500.00, 0.00, 'active');

INSERT INTO category (name) VALUES ('Paint'), ('Brushes'), ('Paper and Canvas'), ('Pencils and Charcoal'), ('Markers and Pens'), ('Tools');
INSERT INTO brand (name) VALUES ('Faber-Castell'), ('Camel'), ('Pentel'), ('Staedtler'), ('Sakura'), ('Paper One'), ('Artist''s Choice');
INSERT INTO supplier (name, contact_name, phone) VALUES ('Divisoria Art Wholesale', 'Aling Cora', '09170000000');

INSERT INTO item (description, category_id, brand_id, supplier_id, cost_price, sell_price) VALUES
('Acrylic Paint Set, 12 colors',   1, 7, 1, 120.00, 185.00),
('Watercolor Pan Set, 24 colors',  1, 5, 1, 210.00, 320.00),
('Poster Color, 6 bottles',        1, 7, 1,  70.00, 110.00),
('Round Brush Set, 5 pcs',         2, 2, 1,  55.00,  95.00),
('Flat Brush, size 12',            2, 2, 1,  18.00,  35.00),
('Sketch Pad A4, 40 sheets',       3, 6, 1,  38.00,  62.00),
('Canvas Board 8x10',              3, 7, 1,  45.00,  75.00),
('Watercolor Paper A3, 10 sheets', 3, 6, 1,  85.00, 135.00),
('Colored Pencils, 24 pcs',        4, 1, 1, 150.00, 225.00),
('Graphite Pencil Set, 12 pcs',    4, 4, 1,  95.00, 150.00),
('Vine Charcoal Sticks, 12 pcs',   4, 7, 1,  40.00,  68.00),
('Fineliner Pens, set of 6',       5, 3, 1,  75.00, 120.00),
('Brush Pen, black',               5, 3, 1,  28.00,  48.00),
('Palette Knife, 3 pcs',           6, 7, 1,  50.00,  85.00);

INSERT INTO stock (item_id, quantity, reorder_threshold)
SELECT item_id, 30, 8 FROM item;
UPDATE stock SET quantity = 6 WHERE item_id = 11;
UPDATE stock SET quantity = 0 WHERE item_id = 14;
