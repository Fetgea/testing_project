CREATE DATABASE IF NOT EXISTS books CHARACTER SET utf8 COLLATE utf8_general_ci;
USE books;
SET NAMES utf8mb4;
DROP TABLE IF EXISTS book;
CREATE TABLE book(id INT PRIMARY KEY AUTO_INCREMENT, title VARCHAR(100) NOT NULL, author VARCHAR(100) NOT NULL, publication_date DATE, number_pages INTEGER);
INSERT book(title, author, publication_date, number_pages) VALUES ("Check", "me", "1994-10-12", 324), ("PROBA", "Check", "2015-12-12", 456), ("Rugrats", "Andrew Sapkovski", "2010-01-06", 300);
