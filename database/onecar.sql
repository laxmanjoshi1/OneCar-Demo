-- OneCar educational demo database. Currency: NPR. All demo payment rows are marked is_demo=1.
CREATE DATABASE IF NOT EXISTS onecar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE onecar;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','manager') NOT NULL DEFAULT 'admin',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(190) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_customers_phone (phone), KEY idx_customers_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vehicles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  category ENUM('Comfort Sedan','Premium SUV','Executive Class') NOT NULL,
  passengers TINYINT UNSIGNED NOT NULL DEFAULT 3,
  luggage TINYINT UNSIGNED NOT NULL DEFAULT 2,
  base_fare DECIMAL(10,2) NOT NULL DEFAULT 100,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_vehicles_active (active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pricing_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_category ENUM('Comfort Sedan','Premium SUV','Executive Class') NOT NULL UNIQUE,
  base_fare DECIMAL(10,2) NOT NULL,
  per_km DECIMAL(10,2) NOT NULL,
  airport_surcharge DECIMAL(10,2) NOT NULL DEFAULT 250,
  outstation_surcharge DECIMAL(10,2) NOT NULL DEFAULT 850,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO pricing_rules(vehicle_category,base_fare,per_km,airport_surcharge,outstation_surcharge,sort_order) VALUES
('Comfort Sedan',100,40,250,850,1),
('Premium SUV',180,58,300,1000,2),
('Executive Class',300,80,350,1200,3)
ON DUPLICATE KEY UPDATE vehicle_category=VALUES(vehicle_category);

INSERT INTO vehicles(name,category,passengers,luggage,base_fare,active)
SELECT 'OneCar Comfort Sedan','Comfort Sedan',3,2,100,1 WHERE NOT EXISTS(SELECT 1 FROM vehicles WHERE category='Comfort Sedan');
INSERT INTO vehicles(name,category,passengers,luggage,base_fare,active)
SELECT 'OneCar Premium SUV','Premium SUV',5,4,180,1 WHERE NOT EXISTS(SELECT 1 FROM vehicles WHERE category='Premium SUV');
INSERT INTO vehicles(name,category,passengers,luggage,base_fare,active)
SELECT 'OneCar Executive Class','Executive Class',3,2,300,1 WHERE NOT EXISTS(SELECT 1 FROM vehicles WHERE category='Executive Class');

CREATE TABLE IF NOT EXISTS bookings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_reference VARCHAR(40) NOT NULL UNIQUE,
  customer_id BIGINT UNSIGNED NOT NULL,
  driver_id BIGINT UNSIGNED NULL,
  ride_type ENUM('City ride','Airport','Outstation') NOT NULL DEFAULT 'City ride',
  vehicle_category ENUM('Comfort Sedan','Premium SUV','Executive Class') NOT NULL,
  pickup_location VARCHAR(255) NOT NULL,
  destination VARCHAR(255) NOT NULL,
  pickup_lat DECIMAL(10,7) NOT NULL,
  pickup_lng DECIMAL(10,7) NOT NULL,
  destination_lat DECIMAL(10,7) NOT NULL,
  destination_lng DECIMAL(10,7) NOT NULL,
  distance_km DECIMAL(10,3) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  pickup_at DATETIME NOT NULL,
  total_fare DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NPR',
  payment_method ENUM('cash','demo_online') NOT NULL,
  payment_status ENUM('pending','pending_cash','paid_demo','failed','refunded') NOT NULL DEFAULT 'pending',
  status ENUM('pending','confirmed','cancelled','completed') NOT NULL DEFAULT 'pending',
  is_demo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_customer FOREIGN KEY(customer_id) REFERENCES customers(id),
  KEY idx_bookings_status(status), KEY idx_bookings_pickup(pickup_at), KEY idx_bookings_payment(payment_status), KEY idx_bookings_created(created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(60) NOT NULL,
  method ENUM('cash','demo_online') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NPR',
  status ENUM('pending','pending_cash','paid_demo','failed','refund_pending','refunded') NOT NULL DEFAULT 'pending',
  transaction_reference VARCHAR(120) NOT NULL,
  provider_reference VARCHAR(190) NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 1,
  verified_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_booking FOREIGN KEY(booking_id) REFERENCES bookings(id),
  UNIQUE KEY uq_payment_reference(transaction_reference), KEY idx_payments_status(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NULL,
  type ENUM('payment_simulation','platform_commission','refund_simulation','cash_collection','adjustment') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NPR',
  status VARCHAR(40) NOT NULL,
  description VARCHAR(255) NOT NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transactions_booking FOREIGN KEY(booking_id) REFERENCES bookings(id),
  KEY idx_transactions_type(type), KEY idx_transactions_created(created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS driver_earnings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id BIGINT UNSIGNED NOT NULL UNIQUE,
  driver_id BIGINT UNSIGNED NULL,
  gross_fare DECIMAL(12,2) NOT NULL,
  commission_amount DECIMAL(12,2) NOT NULL,
  net_earnings DECIMAL(12,2) NOT NULL,
  status ENUM('unassigned','pending','payable','paid_demo') NOT NULL DEFAULT 'unassigned',
  is_demo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_earnings_booking FOREIGN KEY(booking_id) REFERENCES bookings(id), KEY idx_earnings_status(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payouts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_id BIGINT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'NPR',
  status ENUM('requested','processing','paid_demo','failed','cancelled') NOT NULL DEFAULT 'requested',
  payout_reference VARCHAR(120) NOT NULL UNIQUE,
  notes VARCHAR(255) NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_payouts_status(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS refunds (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  status ENUM('requested','approved_demo','rejected','refunded_demo') NOT NULL DEFAULT 'requested',
  is_demo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_refunds_payment FOREIGN KEY(payment_id) REFERENCES payments(id), KEY idx_refunds_status(status)
) ENGINE=InnoDB;
