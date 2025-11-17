-- create database if not exists slotbook_db; 
USE slotbook_db;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(200) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('faculty','admin','student') NOT NULL DEFAULT 'faculty',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  email_notifications TINYINT(1) DEFAULT 1
);

CREATE TABLE IF NOT EXISTS facilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  building VARCHAR(200) NOT NULL,
  capacity INT DEFAULT 0,
  status ENUM('available', 'maintenance') NOT NULL DEFAULT 'available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  facility_id INT NOT NULL,
  date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('pending','approved','denied','cancelled') DEFAULT 'pending',
  cancelled_by INT NULL,
  cancelled_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reminder_sent TINYINT(1) DEFAULT 0,
  completion_sent TINYINT(1) DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  details TEXT DEFAULT NULL,  
  type VARCHAR(50) NOT NULL,
  related_id INT DEFAULT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- seed an admin account (change password)
INSERT INTO users (full_name, email, password_hash, role)
VALUES ('System Admin', 'admin@bpsu.edu.ph', 
        -- password 'Admin@123' hashed with PHP password_hash; you can replace later
        '$2y$10$K1YbLz6cI4d9O9qv2mWdeun6m5Ef4n2v9g9qk9oH1htvWk4mF2Y6W',
        'admin')
ON DUPLICATE KEY UPDATE email=email;