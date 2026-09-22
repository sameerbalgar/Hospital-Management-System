-- ========================================================
-- Pharmacy and Medicine Management Module Database Schema
-- Target Database: `myhmsdb`
-- Compatible with: MySQL / MariaDB (XAMPP)
-- ========================================================

USE `myhmsdb`;

-- --------------------------------------------------------
-- Table 1: `medicinetb` (Medicine Inventory Catalog)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `medicinetb` (
  `medicine_id` INT(11) NOT NULL AUTO_INCREMENT,
  `medicine_name` VARCHAR(100) NOT NULL,
  `generic_name` VARCHAR(100) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `manufacturer` VARCHAR(100) NOT NULL,
  `batch_no` VARCHAR(50) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 0,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `supplier` VARCHAR(100) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Available',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`medicine_id`),
  INDEX `idx_med_name` (`medicine_name`),
  INDEX `idx_generic_name` (`generic_name`),
  INDEX `idx_category` (`category`),
  INDEX `idx_batch_no` (`batch_no`),
  INDEX `idx_expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------
-- Table 2: `pharmacisttb` (Pharmacist Accounts & Credentials)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pharmacisttb` (
  `pharmacist_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(50) NOT NULL UNIQUE,
  `contact` VARCHAR(15) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pharmacist_id`),
  INDEX `idx_pharma_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------
-- Table 3: `pharmacy_sales` (Medicine Dispensing Header)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pharmacy_sales` (
  `bill_id` INT(11) NOT NULL AUTO_INCREMENT,
  `pid` INT(11) NOT NULL,
  `appointment_id` INT(11) DEFAULT NULL,
  `doctor_name` VARCHAR(50) DEFAULT NULL,
  `pharmacist_username` VARCHAR(50) NOT NULL,
  `sale_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_type` VARCHAR(20) NOT NULL DEFAULT 'Cash',
  `notes` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`bill_id`),
  INDEX `idx_sales_pid` (`pid`),
  INDEX `idx_sale_date` (`sale_date`),
  CONSTRAINT `fk_sales_patreg` 
    FOREIGN KEY (`pid`) 
    REFERENCES `patreg` (`pid`) 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------
-- Table 4: `pharmacy_sale_items` (Dispensed Items Breakdown)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pharmacy_sale_items` (
  `item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `bill_id` INT(11) NOT NULL,
  `medicine_id` INT(11) NOT NULL,
  `quantity` INT(11) NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`item_id`),
  INDEX `idx_items_bill_id` (`bill_id`),
  INDEX `idx_items_medicine_id` (`medicine_id`),
  CONSTRAINT `fk_items_sales` 
    FOREIGN KEY (`bill_id`) 
    REFERENCES `pharmacy_sales` (`bill_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_items_medicine` 
    FOREIGN KEY (`medicine_id`) 
    REFERENCES `medicinetb` (`medicine_id`) 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------
-- Sample Data for `medicinetb`
-- --------------------------------------------------------
INSERT INTO `medicinetb` (`medicine_id`, `medicine_name`, `generic_name`, `category`, `manufacturer`, `batch_no`, `expiry_date`, `quantity`, `unit_price`, `supplier`, `status`) VALUES
(1, 'Paracetamol 500mg', 'Acetaminophen', 'Tablet', 'Cipla Ltd', 'BTC-2024-01', '2027-06-30', 120, 5.00, 'MedLife Pharma Dist', 'Available'),
(2, 'Amoxicillin 500mg', 'Amoxicillin Trihydrate', 'Capsule', 'Sun Pharma', 'BTC-2024-02', '2026-12-31', 60, 15.50, 'Apex Healthcare', 'Available'),
(3, 'Benadryl Cough Syrup 100ml', 'Diphenhydramine HCl', 'Syrup', 'Johnson & Johnson', 'BTC-2023-88', '2026-11-15', 35, 85.00, 'Global Meds Wholesale', 'Available'),
(4, 'Pantoprazole 40mg', 'Pantoprazole Sodium', 'Tablet', 'Alkem Labs', 'BTC-2024-09', '2027-03-20', 80, 12.00, 'MedLife Pharma Dist', 'Available'),
(5, 'Azithromycin 500mg', 'Azithromycin', 'Tablet', 'Dr. Reddys Labs', 'BTC-2024-15', '2026-10-10', 45, 22.00, 'Prime Distributors', 'Available'),
(6, 'Cetirizine 10mg', 'Cetirizine HCl', 'Tablet', 'Zydus Cadila', 'BTC-2024-21', '2027-08-15', 150, 4.00, 'MedLife Pharma Dist', 'Available'),
(7, 'Ibuprofen 400mg', 'Ibuprofen', 'Tablet', 'Abbott Healthcare', 'BTC-2024-04', '2026-09-30', 6, 8.50, 'Prime Distributors', 'Low Stock'),
(8, 'Metformin 500mg', 'Metformin HCl', 'Tablet', 'Torrent Pharma', 'BTC-2024-33', '2027-01-25', 90, 7.00, 'Apex Healthcare', 'Available'),
(9, 'Cough Reliever Expectorant', 'Guaifenesin', 'Syrup', 'Glenmark Pharma', 'BTC-2022-12', '2024-01-10', 15, 65.00, 'Global Meds Wholesale', 'Expired');

-- --------------------------------------------------------
-- Table 5: `suppliertb` (Pharmacy Medicine Suppliers)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `suppliertb` (
  `supplier_id` INT(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` VARCHAR(100) NOT NULL,
  `contact_person` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`),
  INDEX `idx_supplier_name` (`supplier_name`),
  INDEX `idx_supplier_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------
-- Table 6: `medicine_stock_movements` (Stock Ledger & History)
-- movement_type values: PURCHASE, RESTOCK, ADJUSTMENT, DISPENSE
--   * RESTOCK/PURCHASE/ADJUSTMENT are created by the Admin Restock form
--   * DISPENSE records are created automatically by the pharmacist
--     dispensing handler (quantity recorded; stock is reduced accordingly)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `medicine_stock_movements` (
  `movement_id` INT(11) NOT NULL AUTO_INCREMENT,
  `medicine_id` INT(11) NOT NULL,
  `supplier_id` INT(11) DEFAULT NULL,
  `movement_type` VARCHAR(20) NOT NULL DEFAULT 'RESTOCK',
  `quantity` INT(11) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `batch_no` VARCHAR(50) NOT NULL,
  `expiry_date` DATE NOT NULL,
  `reference_note` VARCHAR(255) DEFAULT NULL,
  `performed_by` VARCHAR(50) NOT NULL,
  `movement_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`movement_id`),
  INDEX `idx_mov_medicine` (`medicine_id`),
  INDEX `idx_mov_supplier` (`supplier_id`),
  INDEX `idx_mov_type` (`movement_type`),
  INDEX `idx_mov_date` (`movement_date`),
  CONSTRAINT `fk_mov_medicine`
    FOREIGN KEY (`medicine_id`)
    REFERENCES `medicinetb` (`medicine_id`)
    ON UPDATE CASCADE,
  CONSTRAINT `fk_mov_supplier`
    FOREIGN KEY (`supplier_id`)
    REFERENCES `suppliertb` (`supplier_id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------
-- Sample Data for `suppliertb`
-- --------------------------------------------------------
INSERT INTO `suppliertb` (`supplier_id`, `supplier_name`, `contact_person`, `email`, `phone`, `address`, `status`) VALUES
(1, 'MedLife Pharma Dist', 'Rajesh Sharma', 'orders@medlifepharma.com', '9811223344', '12 Industrial Area, Phase 1, Mumbai', 'Active'),
(2, 'Apex Healthcare', 'Suresh Patel', 'contact@apexhealth.in', '9822334455', '45 Biotech Park, Ahmedabad', 'Active'),
(3, 'Global Meds Wholesale', 'Anita Desai', 'sales@globalmeds.com', '9833445566', '78 Pharma Hub, Hyderabad', 'Active'),
(4, 'Prime Distributors', 'Vikas Gupta', 'prime.dist@gmail.com', '9844556677', '23 Wholesale Market, Delhi', 'Active');

