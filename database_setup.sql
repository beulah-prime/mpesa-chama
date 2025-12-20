-- Database Setup for Chama Management System
-- Creates database and all required tables

-- Create the database
DROP DATABASE IF EXISTS chama_db;
CREATE DATABASE IF NOT EXISTS chama_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chama_db;

-- Table 1: users - for authentication and profiles
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    id_number VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'treasurer', 'member') DEFAULT 'member',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table 2: members - detailed member information
CREATE TABLE members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    member_number VARCHAR(50) UNIQUE,
    join_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table 3: contributions - tracking member contributions
CREATE TABLE contributions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    contribution_date DATE NOT NULL,
    payment_method ENUM('mpesa', 'cash', 'bank_transfer') DEFAULT 'mpesa',
    mpesa_code VARCHAR(50),
    status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- Table 4: loans - loan applications and management
CREATE TABLE loans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    loan_amount DECIMAL(10, 2) NOT NULL,
    interest_rate DECIMAL(5, 2) DEFAULT 10.00,
    total_repayment DECIMAL(10, 2),
    duration_months INT NOT NULL,
    date_applied DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'disbursed', 'paid') DEFAULT 'pending',
    approved_by INT,
    approved_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Table 5: loan_repayments - tracking loan repayment schedule
CREATE TABLE loan_repayments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT NOT NULL,
    installment_amount DECIMAL(10, 2) NOT NULL,
    due_date DATE NOT NULL,
    paid_date DATE,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    mpesa_code VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

-- Table 6: fines - tracking member fines
CREATE TABLE fines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    reason TEXT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    date_imposed DATE NOT NULL,
    due_date DATE,
    status ENUM('pending', 'paid', 'waived') DEFAULT 'pending',
    imposed_by INT NOT NULL,
    paid_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (imposed_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Table 7: mpesa_transactions - for M-Pesa payment tracking
CREATE TABLE mpesa_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(50) UNIQUE,
    member_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    transaction_type ENUM('contribution', 'loan_payment', 'fine_payment', 'other') NOT NULL,
    reference_id INT, -- ID of the related record (contribution, loan_repayment, fine)
    mpesa_code VARCHAR(50) NOT NULL,
    phone_number VARCHAR(20),
    transaction_date DATETIME NOT NULL,
    status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- Table 8: settings - system configuration
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (password: "admin123" - hash for testing)
INSERT INTO users (full_name, email, phone_number, id_number, password_hash, role, status) VALUES
('System Administrator', 'admin@chamasys.com', '254700000000', '12345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('system_name', 'ChamaSys', 'Name of the system'),
('currency', 'KES', 'Currency used in the system'),
('interest_rate_default', '10.00', 'Default interest rate for loans'),
('fine_amount_default', '500.00', 'Default fine amount'),
('minimum_contribution', '100.00', 'Minimum contribution amount');