-- Database Setup Script for M-Pesa Chama Management System
--
-- This script creates all necessary database tables for the system including:
-- - User management tables
-- - Financial tracking tables
-- - M-Pesa integration tables
-- - System settings
--
-- @author ChamaSys Development Team
-- @version 1.0
-- @since 2025

-- Users table for authentication and basic profile information
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    id_number VARCHAR(20) UNIQUE NOT NULL COMMENT 'Kenyan National ID Number (8-10 digits)',
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'treasurer', 'member') DEFAULT 'member',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Members table for detailed member information
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    member_number VARCHAR(50) UNIQUE NOT NULL,
    join_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Contributions table for tracking member contributions
CREATE TABLE IF NOT EXISTS contributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    contribution_date DATE NOT NULL,
    payment_method ENUM('mpesa', 'cash', 'bank_transfer') DEFAULT 'mpesa',
    mpesa_code VARCHAR(50) NULL,
    status ENUM('pending', 'confirmed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- Loans table for loan management
CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    loan_amount DECIMAL(10, 2) NOT NULL,
    interest_rate DECIMAL(5, 2) NOT NULL DEFAULT 10.00,
    total_repayment DECIMAL(10, 2) NOT NULL,
    duration_months INT NOT NULL,
    date_applied DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'disbursed', 'paid') DEFAULT 'pending',
    approved_by INT NULL,
    approved_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Loan repayments table for tracking loan repayment schedules
CREATE TABLE IF NOT EXISTS loan_repayments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    amount_due DECIMAL(10, 2) NOT NULL,
    amount_paid DECIMAL(10, 2) DEFAULT 0.00,
    due_date DATE NOT NULL,
    payment_date DATE NULL,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
);

-- Fines table for tracking member fines
CREATE TABLE IF NOT EXISTS fines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    reason TEXT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    date_imposed DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('pending', 'paid', 'waived') DEFAULT 'pending',
    paid_date DATE NULL,
    imposed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (imposed_by) REFERENCES users(id) ON DELETE CASCADE
);

-- M-Pesa transactions table for tracking all M-Pesa payments
CREATE TABLE IF NOT EXISTS mpesa_transactions (
    transaction_id VARCHAR(100) PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    transaction_type ENUM('contribution', 'fine_payment', 'loan_payment') NOT NULL,
    reference_id INT NOT NULL,  -- ID of the original record (contribution, fine, etc.)
    mpesa_code VARCHAR(50) NOT NULL,
    transaction_date DATETIME NOT NULL,
    status ENUM('pending', 'confirmed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- M-Pesa STK push requests table for tracking payment initiation requests
CREATE TABLE IF NOT EXISTS mpesa_stk_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checkout_request_id VARCHAR(100) UNIQUE NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    account_number VARCHAR(100) NULL,
    description TEXT NULL,
    status ENUM('Pending', 'Completed', 'Failed', 'Canceled') DEFAULT 'Pending',
    result_code INT NULL,
    result_desc TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- System settings table for configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- API tokens table for caching access tokens
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_type VARCHAR(50) NOT NULL,
    token_value TEXT NOT NULL,
    expiry_time DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default settings if they don't exist
INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
('system_name', 'Chama Management System', 'Name of the system'),
('interest_rate_default', '10.00', 'Default interest rate for loans'),
('fine_amount_default', '500.00', 'Default fine amount'),
('currency', 'KES', 'Currency code for the system'),
('mpesa_consumer_key', '', 'M-Pesa API Consumer Key'),
('mpesa_consumer_secret', '', 'M-Pesa API Consumer Secret'),
('mpesa_business_shortcode', '', 'M-Pesa Business Shortcode'),
('mpesa_passkey', '', 'M-Pesa Passkey for generating password'),
('mpesa_callback_url', '', 'M-Pesa Callback URL for payment confirmations'),
('mpesa_timeout_url', '', 'M-Pesa Timeout URL for payment timeouts');