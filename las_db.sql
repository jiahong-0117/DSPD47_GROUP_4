-- NVDIA Staff Leave Application System
-- Normalized database version with role, department, superior, hr, and admin tables.
-- Import this file into phpMyAdmin / MySQL database `las_db`.

CREATE DATABASE IF NOT EXISTS `las_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `las_db`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `email_log`;
DROP TABLE IF EXISTS `leave_application`;
DROP TABLE IF EXISTS `superior`;
DROP TABLE IF EXISTS `hr`;
DROP TABLE IF EXISTS `admin`;
DROP TABLE IF EXISTS `staff`;
DROP TABLE IF EXISTS `department`;
DROP TABLE IF EXISTS `role`;

SET FOREIGN_KEY_CHECKS = 1;
START TRANSACTION;

-- --------------------------------------------------------
-- Role table: central permission reference for login access.
-- Every staff account points to one role_id, so changing role data can be shared by all users with that role.
-- --------------------------------------------------------
CREATE TABLE `role` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(30) NOT NULL,
  `role_label` varchar(60) NOT NULL,
  `role_description` varchar(255) DEFAULT NULL,
  `can_apply_leave` tinyint(1) NOT NULL DEFAULT 0,
  `can_review_leave` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_staff` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_email_log` tinyint(1) NOT NULL DEFAULT 0,
  `can_administer_system` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uk_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Department table: central department list.
-- --------------------------------------------------------
CREATE TABLE `department` (
  `department_id` int(11) NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) NOT NULL,
  `department_description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `uk_department_name` (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Staff table: one login account per staff member.
-- role_id and department_id replace old text columns role and department.
-- superior_id links a staff account to the superior/admin who can review the leave.
-- --------------------------------------------------------
CREATE TABLE `staff` (
  `staff_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `superior_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`staff_id`),
  UNIQUE KEY `uk_staff_email` (`email`),
  KEY `idx_staff_department` (`department_id`),
  KEY `idx_staff_role` (`role_id`),
  KEY `idx_staff_superior` (`superior_id`),
  CONSTRAINT `fk_staff_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_role` FOREIGN KEY (`role_id`) REFERENCES `role` (`role_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_staff_superior` FOREIGN KEY (`superior_id`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Superior table: extra identity table for users with superior role.
-- --------------------------------------------------------
CREATE TABLE `superior` (
  `superior_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  PRIMARY KEY (`superior_id`),
  UNIQUE KEY `uk_superior_staff` (`staff_id`),
  KEY `idx_superior_department` (`department_id`),
  CONSTRAINT `fk_superior_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_superior_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`department_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- HR table: extra identity table for users with HR role.
-- --------------------------------------------------------
CREATE TABLE `hr` (
  `hr_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  PRIMARY KEY (`hr_id`),
  UNIQUE KEY `uk_hr_staff` (`staff_id`),
  CONSTRAINT `fk_hr_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Admin table: extra identity table for users with admin role.
-- --------------------------------------------------------
CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `assigned_date` date NOT NULL,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uk_admin_staff` (`staff_id`),
  CONSTRAINT `fk_admin_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Leave application table.
-- --------------------------------------------------------
CREATE TABLE `leave_application` (
  `leave_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `leave_type` enum('Annual Leave','Medical Leave','Emergency Leave','Unpaid Leave') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `apply_date` date NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `decision_date` date DEFAULT NULL,
  `superior_remark` text DEFAULT NULL,
  PRIMARY KEY (`leave_id`),
  KEY `idx_leave_staff` (`staff_id`),
  KEY `idx_leave_approved_by` (`approved_by`),
  KEY `idx_leave_status` (`status`),
  CONSTRAINT `fk_leave_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Email log table.
-- --------------------------------------------------------
CREATE TABLE `email_log` (
  `email_id` int(11) NOT NULL AUTO_INCREMENT,
  `leave_id` int(11) NOT NULL,
  `receiver_email` varchar(100) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `send_date` date NOT NULL,
  PRIMARY KEY (`email_id`),
  KEY `idx_email_leave` (`leave_id`),
  CONSTRAINT `fk_email_leave` FOREIGN KEY (`leave_id`) REFERENCES `leave_application` (`leave_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Demo data
-- --------------------------------------------------------
INSERT INTO `role` (`role_id`, `role_name`, `role_label`, `role_description`, `can_apply_leave`, `can_review_leave`, `can_manage_staff`, `can_view_email_log`, `can_administer_system`) VALUES
(1, 'staff', 'Staff', 'Normal employee. Can apply leave and view own leave records.', 1, 0, 0, 0, 0),
(2, 'superior', 'Superior', 'Department superior. Can review leave applications submitted by assigned subordinates.', 0, 1, 0, 1, 0),
(3, 'hr', 'HR', 'Human Resource staff. Can manage staff accounts, roles, departments, and superior assignment.', 0, 0, 1, 0, 0),
(4, 'admin', 'Admin', 'System administrator. Can access all staff, superior, HR, and admin functions.', 1, 1, 1, 1, 1);

INSERT INTO `department` (`department_id`, `department_name`, `department_description`) VALUES
(1, 'Administration', 'General administration and system control.'),
(2, 'Information Technology', 'IT support, systems, and development.'),
(3, 'Human Resource', 'Staff records and company leave management.'),
(4, 'Finance', 'Finance and accounting department.'),
(5, 'Operations', 'Daily business operation team.');

INSERT INTO `staff` (`staff_id`, `staff_name`, `email`, `password`, `department_id`, `role_id`, `superior_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Admin User', 'admin@las.com', '$2y$12$3z69yyejFbCnbOrYxWxtueVN0tSwf6p73Kq/ltME.Ypc.1/6Nc2ki', 1, 4, NULL, 1, NOW(), NOW()),
(2, 'Daniel Tan', 'superior@las.com', '$2y$12$3LcyudE4WVuZa2ekKFlTn.OcBMhGNa/pd6FqQg5khqtUktp4lo1MG', 2, 2, NULL, 1, NOW(), NOW()),
(3, 'Helen Wong', 'hr@las.com', '$2y$12$3LcyudE4WVuZa2ekKFlTn.OcBMhGNa/pd6FqQg5khqtUktp4lo1MG', 3, 3, NULL, 1, NOW(), NOW()),
(4, 'Demo Staff', 'staff@las.com', '$2y$12$AfqtRIlw15uz1UrCbMyb4e0X6O8bhHJsAjNrpdefZvD.zdnhs7Svy', 2, 1, 2, 1, NOW(), NOW()),
(5, 'Ali Ahmad', 'ali@las.com', '$2y$12$3LcyudE4WVuZa2ekKFlTn.OcBMhGNa/pd6FqQg5khqtUktp4lo1MG', 2, 1, 2, 1, NOW(), NOW()),
(6, 'Jiahong', 'jiahong@gmail.com', '$2y$12$7ZBnjLPx6R6St865wXikpufZNLPcsco8gMJPJuDawSq4ZouDfrZgu', 2, 1, 2, 1, NOW(), NOW()),
(7, 'Kaize', 'kaize@gmail.com', '$2y$12$5M/pposrEr2Gp.5Li0UWie.SJ05GofPoNJ/OuPbyGkmej/Xjvv05.', 2, 1, 2, 1, NOW(), NOW()),
(8, 'Yiwei', 'yiwei@gmail.com', '$2y$12$2x0GoDe1i9aQIfE/e9pgvuf8JPW9GZ5ufhZjzAsk6BwCLdxkGmlz6', 2, 1, 2, 1, NOW(), NOW());

INSERT INTO `superior` (`superior_id`, `staff_id`, `department_id`, `assigned_date`) VALUES
(1, 2, 2, '2026-06-01');

INSERT INTO `hr` (`hr_id`, `staff_id`, `assigned_date`) VALUES
(1, 3, '2026-06-01');

INSERT INTO `admin` (`admin_id`, `staff_id`, `assigned_date`) VALUES
(1, 1, '2026-06-01');

INSERT INTO `leave_application` (`leave_id`, `staff_id`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `apply_date`, `approved_by`, `decision_date`, `superior_remark`) VALUES
(1, 4, 'Annual Leave', '2026-06-15', '2026-06-17', 'Family trip', 'Pending', '2026-06-10', NULL, NULL, NULL),
(2, 5, 'Medical Leave', '2026-06-05', '2026-06-05', 'Fever', 'Approved', '2026-06-03', 2, '2026-06-04', 'Approved');

INSERT INTO `email_log` (`email_id`, `leave_id`, `receiver_email`, `subject`, `message`, `send_date`) VALUES
(1, 2, 'ali@las.com', 'Leave Application Approved', 'Dear Ali Ahmad, your leave application from 2026-06-05 to 2026-06-05 has been Approved. Remark: Approved', '2026-06-04');

ALTER TABLE `role` AUTO_INCREMENT = 5;
ALTER TABLE `department` AUTO_INCREMENT = 6;
ALTER TABLE `staff` AUTO_INCREMENT = 9;
ALTER TABLE `superior` AUTO_INCREMENT = 2;
ALTER TABLE `hr` AUTO_INCREMENT = 2;
ALTER TABLE `admin` AUTO_INCREMENT = 2;
ALTER TABLE `leave_application` AUTO_INCREMENT = 3;
ALTER TABLE `email_log` AUTO_INCREMENT = 2;

COMMIT;
