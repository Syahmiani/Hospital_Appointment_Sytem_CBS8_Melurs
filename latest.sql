-- 🏥 Hospital Systems Database Setup (Corrected) 🏥

-- 1. Create and Use the Database
DROP DATABASE IF EXISTS `hospital_systems`; -- Added DROP for clean run
CREATE DATABASE IF NOT EXISTS `hospital_systems`;
USE `hospital_systems`;

-- --------------------------------------------------------
-- 2. Table Creation
-- --------------------------------------------------------

-- Table structure for table `user`
CREATE TABLE IF NOT EXISTS `user` (
  `user_id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `user_Name` VARCHAR(50) DEFAULT NULL,
  `f_name` VARCHAR(50) DEFAULT NULL,
  `l_name` VARCHAR(50) DEFAULT NULL,
  `password` CHAR(64) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `user_type` ENUM('doctor', 'admin', 'patient') NOT NULL,
  `Phone_Number` VARCHAR(20) DEFAULT NULL,
  `gender` ENUM('Male', 'Female') DEFAULT NULL,
  `dob` DATE DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `doctor`
--
CREATE TABLE IF NOT EXISTS `doctor` (
  `doctor_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL UNIQUE,
  `specialization` VARCHAR(100) NOT NULL,
  `room_no` VARCHAR(10) NOT NULL,
  PRIMARY KEY (`doctor_id`),
  FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `patient`
--
CREATE TABLE IF NOT EXISTS `patient` (
  `patient_id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL UNIQUE,
  PRIMARY KEY (`patient_id`),
  FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `services`
--
CREATE TABLE IF NOT EXISTS `services` (
  `Service_ID` INT NOT NULL AUTO_INCREMENT,
  `Service_Name` VARCHAR(100) NOT NULL,
  `Service_Price` DECIMAL(10, 2) NOT NULL,
  `Available` ENUM('Yes', 'No') NOT NULL,
  PRIMARY KEY (`Service_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `appointment`
--
CREATE TABLE IF NOT EXISTS `appointment` (
  `App_ID` INT NOT NULL AUTO_INCREMENT,
  `patient_id` INT NOT NULL,
  `doctor_id` INT NOT NULL,
  `Service_ID` INT NOT NULL,
  `App_Date` DATE NOT NULL,
  `App_Time` TIME NOT NULL,
  `symptom` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'disapproved') NOT NULL,
  `cancellation_reason` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`App_ID`),
  FOREIGN KEY (`patient_id`) REFERENCES `patient`(`patient_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`doctor_id`) REFERENCES `doctor`(`doctor_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (`Service_ID`) REFERENCES `services`(`Service_ID`) ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE KEY `unique_appointment` (`App_Date`, `App_Time`, `doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `contact_messages`
--
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `reply` TEXT NULL,
  `replied_at` DATETIME NULL,
  `replied_by` INT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`replied_by`) REFERENCES `user`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 3. Data Insertion
-- --------------------------------------------------------

-- Insert data into `user`
INSERT INTO `user` (`user_id`, `email`, `user_Name`, `f_name`, `l_name`, `password`, `phone`, `user_type`, `Phone_Number`, `gender`, `dob`) VALUES
(1, 'doc.a@hosp.com', 'DrAlex', 'Alex', 'Smith', '202cb962ac59075b964b07152d234b70', NULL, 'doctor', '0119876543', 'Male', NULL),
(2, 'doc.b@hosp.com', 'DrSarah', 'Sarah', 'Lee', '202cb962ac59075b964b07152d234b70', NULL, 'doctor', '0165554444', 'Female', NULL),
(9, 'adm@hosp.com', 'Hospital', 'Admin', NULL, '202cb962ac59075b964b07152d234b70', NULL, 'admin', NULL, NULL, NULL),
(15, 'mansyah@gmail.com', 'Aiman', 'Aiman', 'Syahmi', 'e10adc3949ba59abbe56e057f20f883e', '01140121089', 'patient', NULL, 'Male', '2025-10-22');

--
-- Insert data into `doctor` (FIXED: user_id 3 changed to existing user_id 1)
--
INSERT INTO `doctor` (`doctor_id`, `user_id`, `specialization`, `room_no`) VALUES
(1, 2, 'Cardiology', 'B201'),
(2, 1, 'General Practice', 'A105');

--
-- Insert data into `patient`
--
INSERT INTO `patient` (`patient_id`, `user_id`) VALUES
(9, 15);

--
-- Insert data into `services`
--
INSERT INTO `services` (`Service_ID`, `Service_Name`, `Service_Price`, `Available`) VALUES
(1, 'General Consultation', 80.00, 'Yes'),
(2, 'Cardiology Checkup', 150.00, 'Yes'),
(3, 'In-house Lab Test', 50.00, 'Yes'),
(4, 'Physiotherapy', 120.00, 'No');

--
-- Insert data into `appointment`
--
INSERT INTO `appointment` (`App_ID`, `patient_id`, `doctor_id`, `Service_ID`, `App_Date`, `App_Time`, `symptom`, `status`, `cancellation_reason`, `created_at`) VALUES
(13, 9, 1, 1, '2025-10-29', '00:31:00', 'sick', 'pending', NULL, '2025-10-22 21:22:14'),
(15, 9, 1, 1, '2025-10-28', '14:38:00', 'sick again', 'approved', NULL, '2025-10-22 21:27:02');

--
-- Insert data into `contact_messages`
--
INSERT INTO `contact_messages` (`id`, `name`, `email`, `message`, `created_at`) VALUES
(2, 'Man', 'mansyah@gmail.com', 'Hi admin', '2025-10-23 02:50:44');

-- --------------------------------------------------------
-- 4. Alter Tables (Soft Delete/History Columns)
-- --------------------------------------------------------

-- For patient table
ALTER TABLE patient 
ADD COLUMN is_deleted TINYINT(1) DEFAULT 0,
ADD COLUMN deleted_at DATETIME NULL,
ADD COLUMN deleted_by INT NULL;

-- For appointment table
ALTER TABLE appointment 
ADD COLUMN is_deleted TINYINT(1) DEFAULT 0,
ADD COLUMN deleted_at DATETIME NULL,
ADD COLUMN deleted_by INT NULL;

-- For doctor table
ALTER TABLE doctor
ADD COLUMN is_deleted TINYINT(1) DEFAULT 0,
ADD COLUMN deleted_at DATETIME NULL,
ADD COLUMN deleted_by INT NULL;

-- For contact_messages table
ALTER TABLE contact_messages
ADD COLUMN is_deleted TINYINT(1) DEFAULT 0,
ADD COLUMN deleted_at DATETIME NULL,
ADD COLUMN deleted_by INT NULL;

--
-- Table structure for table `medicine`
--
CREATE TABLE IF NOT EXISTS `medicine` (
  `med_id` INT NOT NULL AUTO_INCREMENT,
  `med_name` VARCHAR(100) NOT NULL,
  `med_price` DECIMAL(10, 2) NOT NULL,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `deleted_at` DATETIME NULL,
  `deleted_by` INT NULL,
  PRIMARY KEY (`med_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `prescription`
--
CREATE TABLE IF NOT EXISTS `prescription` (
  `prescription_id` INT NOT NULL AUTO_INCREMENT,
  `App_ID` INT NOT NULL,
  `med_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`prescription_id`),
  FOREIGN KEY (`App_ID`) REFERENCES `appointment`(`App_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (`med_id`) REFERENCES `medicine`(`med_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE appointment
    ADD COLUMN payment_status ENUM('unpaid', 'pending', 'paid', 'rejected') DEFAULT 'unpaid',
    ADD COLUMN payment_proof VARCHAR(255) DEFAULT NULL,
    ADD COLUMN payment_time DATETIME DEFAULT NULL,
    ADD COLUMN payment_updated_by INT NULL,
    ADD CONSTRAINT fk_payment_updated_by FOREIGN KEY (payment_updated_by) REFERENCES `user`(`user_id`) ON DELETE SET NULL;

INSERT INTO `medicine` (med_name, med_price, is_deleted, deleted_at, deleted_by)
VALUES ('Paracetamol', 5.50, 0, NULL, NULL);

INSERT INTO `medicine` (med_name, med_price, is_deleted, deleted_at, deleted_by)
VALUES ('Ibuprofen', 8.25, 0, NULL, NULL);

INSERT INTO `medicine` (med_name, med_price, is_deleted, deleted_at, deleted_by)
VALUES ('Amoxicillin', 15.00, 0, NULL, NULL); -- Note: Antibiotics usually require a prescription

INSERT INTO `medicine` (med_name, med_price, is_deleted, deleted_at, deleted_by)
VALUES ('Antihistamine', 12.00, 0, NULL, NULL);

INSERT INTO `medicine` (med_name, med_price, is_deleted, deleted_at, deleted_by)
VALUES ('Vitamins C', 10.99, 0, NULL, NULL);