-- Update genre column size to VARCHAR(100)
-- Run this in phpMyAdmin SQL tab

USE ticketix;

ALTER TABLE MOVIE MODIFY COLUMN genre VARCHAR(100);

-- Verify the change
DESCRIBE MOVIE;

