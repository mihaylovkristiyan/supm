-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS supmonli_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create the user if it doesn't exist
CREATE USER IF NOT EXISTS 'supmonli_admin'@'localhost' IDENTIFIED BY 'fckTT7e}UG)A';

-- Grant all privileges on the database to the user
GRANT ALL PRIVILEGES ON supmonli_db.* TO 'supmonli_admin'@'localhost';

-- Flush privileges to apply changes
FLUSH PRIVILEGES; 