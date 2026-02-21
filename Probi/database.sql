-- Create the database

CREATE DATABASE IF NOT EXISTS probi_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE probi_db;



-- Create animal_objects table

CREATE TABLE IF NOT EXISTS animal_objects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulstat VARCHAR(13),
    eik_egn VARCHAR(13),
    proizvoditel VARCHAR(255),
    nov_jo VARCHAR(50) UNIQUE,
    star_jo VARCHAR(50) UNIQUE,
    oblast VARCHAR(100),
    obshtina VARCHAR(100),
    naseleno_miasto VARCHAR(100),
    telefon VARCHAR(20),
    email VARCHAR(255),
    email_mandra VARCHAR(255),
    belezhka TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Add email_mandra column if it doesn't exist

ALTER TABLE animal_objects
ADD COLUMN IF NOT EXISTS email_mandra VARCHAR(255) AFTER email;



-- Create takers table

CREATE TABLE IF NOT EXISTS takers (

    id INT AUTO_INCREMENT PRIMARY KEY,

    ime VARCHAR(255)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Create samples table

CREATE TABLE IF NOT EXISTS samples (

    id INT AUTO_INCREMENT PRIMARY KEY,

    protokol_nomer VARCHAR(50),

    data DATE,

    probovzemach_id INT,

    barkod VARCHAR(50),

    vid_mliako ENUM('овче', 'козе', 'краве', 'биволско'),

    faktura VARCHAR(50),

    plashtane VARCHAR(50),

    nov_jo VARCHAR(50),

    star_jo VARCHAR(50),

    FOREIGN KEY (probovzemach_id) REFERENCES takers(id),

    FOREIGN KEY (star_jo) REFERENCES animal_objects(star_jo)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Create users table

CREATE TABLE IF NOT EXISTS `users` (

    `id` INT PRIMARY KEY AUTO_INCREMENT,

    `username` VARCHAR(50) UNIQUE NOT NULL,

    `password_hash` VARCHAR(255) NOT NULL,

    `full_name` VARCHAR(100) NOT NULL,

    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',

    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,

    `last_login` DATETIME,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Insert default admin user (password will need to be changed on first login)

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`) 

VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin'); 