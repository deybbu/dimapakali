-- Create Database
CREATE DATABASE IF NOT EXISTS TICKETIX;
USE TICKETIX;

-- Drop tables if they already exist (in correct order to handle foreign key constraints)
DROP TABLE IF EXISTS TICKET;
DROP TABLE IF EXISTS PAYMENT;
DROP TABLE IF EXISTS RESERVE_SEAT;
DROP TABLE IF EXISTS RESERVE;
DROP TABLE IF EXISTS SEAT;
DROP TABLE IF EXISTS MOVIE_SCHEDULE;
DROP TABLE IF EXISTS MOVIE;
DROP TABLE IF EXISTS USER_ACCOUNT;

-- 1️⃣ USER_ACCOUNT Table
CREATE TABLE USER_ACCOUNT(
    acc_id INT PRIMARY KEY AUTO_INCREMENT,
    firstName VARCHAR(50) NOT NULL,
    lastName VARCHAR(50) NOT NULL,
    contNo VARCHAR(12),
    email VARCHAR(50) UNIQUE NOT NULL,
    address VARCHAR(50),
    birthdate DATE,
    user_password VARCHAR(70),
    time_created DATETIME,
    user_status ENUM('online', 'offline') DEFAULT 'offline'
) ENGINE=InnoDB;

-- 2️⃣ MOVIE Table
CREATE TABLE MOVIE(
    movie_show_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(50),
    genre VARCHAR(100),
    duration INT,
    rating VARCHAR(20),
    movie_descrp TEXT,
    image_poster VARCHAR(100),
    now_showing BOOLEAN DEFAULT FALSE,
    coming_soon BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB;

-- 3️⃣ MOVIE_SCHEDULE Table
CREATE TABLE MOVIE_SCHEDULE(
    schedule_id INT PRIMARY KEY AUTO_INCREMENT,
    movie_show_id INT,
    show_date DATE,
    show_hour TIME,
    FOREIGN KEY (movie_show_id) REFERENCES MOVIE(movie_show_id)
) ENGINE=InnoDB;

-- 4️⃣ SEAT Table
CREATE TABLE SEAT(
    seat_id INT PRIMARY KEY AUTO_INCREMENT,
    seat_number VARCHAR(10),
    seat_type ENUM('Regular','VIP') DEFAULT 'Regular',
    seat_price DECIMAL(10,2)
) ENGINE=InnoDB;

-- 5️⃣ RESERVE Table
CREATE TABLE RESERVE(
    reservation_id INT PRIMARY KEY AUTO_INCREMENT,
    acc_id INT,
    schedule_id INT,
    reserve_date DATETIME,
    ticket_amount INT,
    sum_price DECIMAL(10,2),
    FOREIGN KEY (acc_id) REFERENCES USER_ACCOUNT(acc_id),
    FOREIGN KEY (schedule_id) REFERENCES MOVIE_SCHEDULE(schedule_id)
) ENGINE=InnoDB;

-- 6️⃣ RESERVE_SEAT Table
CREATE TABLE RESERVE_SEAT(
    reserve_seat_id INT PRIMARY KEY AUTO_INCREMENT,
    reservation_id INT,
    seat_id INT,
    FOREIGN KEY (reservation_id) REFERENCES RESERVE(reservation_id),
    FOREIGN KEY (seat_id) REFERENCES SEAT(seat_id)
) ENGINE=InnoDB;

-- 7️⃣ PAYMENT Table
CREATE TABLE PAYMENT(
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    reserve_id INT,
    payment_type ENUM('cash','credit','e-wallet'),
    amount_paid DECIMAL(10,2),
    payment_status ENUM('paid','pending','not-yet'),
    payment_date DATETIME,
    reference_number VARCHAR(100),
    FOREIGN KEY (reserve_id) REFERENCES RESERVE(reservation_id)
) ENGINE=InnoDB;

CREATE TABLE TICKET(
ticket_id INT PRIMARY KEY auto_increment,
reserve_id INT,
payment_id INT,
ticket_number VARCHAR(50),
date_issued DATETIME,
ticket_status ENUM('valid','cancelled','refuneded'),
FOREIGN KEY (payment_id) REFERENCES PAYMENT(payment_id),
FOREIGN KEY (reserve_id) REFERENCES RESERVE(reservation_id)
);

CREATE TABLE FOOD (
food_id INT PRIMARY KEY AUTO_INCREMENT,
food_name VARCHAR(50) NOT NULL,
food_price DECIMAL(10,2) DEFAULT 0.00
) ENGINE=InnoDB;

CREATE TABLE TICKET_FOOD (
ticket_id INT,
food_id INT PRIMARY KEY AUTO_INCREMENT,
quantity INT DEFAULT 1,
FOREIGN KEY (ticket_id) REFERENCES TICKET(ticket_id),
FOREIGN KEY (food_id) REFERENCES FOOD(food_id)
) ENGINE=InnoDB;

ALTER TABLE USER_ACCOUNT
ADD COLUMN reset_token_hash VARCHAR(64) NULL,
ADD COLUMN reset_token_expires_at DATETIME NULL;

ALTER TABLE TICKET
ADD COLUMN e_ticket_code VARCHAR(100) UNIQUE,
ADD COLUMN e_ticket_file VARCHAR(255);

ALTER TABLE USER_ACCOUNT ADD COLUMN role VARCHAR(50) DEFAULT 'user';
UPDATE USER_ACCOUNT 
SET role = 'admin' 
WHERE email = 'ticketix0@gmail.com';

-- Add Now Showing and Coming Soon columns to MOVIE table
-- Note: Run these separately if columns already exist to avoid errors
-- ALTER TABLE MOVIE ADD COLUMN now_showing BOOLEAN DEFAULT FALSE;
-- ALTER TABLE MOVIE ADD COLUMN coming_soon BOOLEAN DEFAULT FALSE;

-- Update genre column size for existing databases
-- ALTER TABLE MOVIE MODIFY COLUMN genre VARCHAR(100);

SELECT * FROM USER_ACCOUNT;
SELECT * FROM MOVIE;
SELECT * FROM MOVIE_SCHEDULE;
SELECT * FROM SEAT;
SELECT * FROM RESERVE;
SELECT * FROM RESERVE_SEAT;
SELECT * FROM PAYMENT;
SELECT * FROM TICKET;
SELECT * FROM FOOD;
SELECT * FROM TICKET_FOOD;