<?php
/*
 * Loan Status Update Handler for Chama Management System
 *
 * This page handles loan status updates (approve/reject/disburse/pay).
 * It processes requests from the Loans.php page and updates the database accordingly.
 *
 * Features:
 * - Secure authentication and role-based access control
 * - Loan status validation
 * - Database transaction handling
 * - Proper error handling and redirects
 *
 * @author ChamaSys Development Team
 * @version 1.0
 * @since 2025
 */

// Start session to maintain user state across pages
session_start();

// Include the DatabaseClass which provides OOP database functionality
require_once 'DatabaseClass.php';

// Check if user is logged in by verifying session variable exists
if (!isset($_SESSION['user'])) {
    // Redirect unauthorized users to login page
    header('Location: Login.php');
    // Exit script to prevent further execution
    exit();
}

// Store current user data and role for validation
$user = $_SESSION['user'];
$role = $user['role'];

// Only admins and treasurers can update loan status
if ($role !== 'admin' && $role !== 'treasurer') {
    header('Location: Login.php');
    exit();
}

// Process loan status update request
if (isset($_GET['id']) && isset($_GET['status'])) {
    $loan_id = (int)$_GET['id'];
    $status = $_GET['status'];
    
    // Validate status - only allow valid status transitions
    $valid_statuses = ['approved', 'rejected', 'disbursed', 'paid'];
    if (!in_array($status, $valid_statuses)) {
        header('Location: Loans.php?error=Invalid status');
        exit();
    }

    // Create Loan object to access loan-related methods
    $loan = new Loan();

    // If approving a loan, set the approver
    $approver_id = ($status === 'approved') ? $user['id'] : null;

    // Attempt to update the loan status
    if ($loan->updateLoanStatus($loan_id, $status, $approver_id)) {
        // Check if we need to create loan repayment schedule when disbursing
        if ($status === 'disbursed') {
            createLoanRepaymentSchedule($loan_id);
        }
        
        header('Location: Loans.php?message=Loan status updated successfully');
    } else {
        header('Location: Loans.php?error=Failed to update loan status');
    }
} else {
    // If no valid parameters, redirect back to loans page
    header('Location: Loans.php');
    exit();
}

/**
 * Create a repayment schedule when a loan is disbursed
 */
function createLoanRepaymentSchedule($loan_id) {
    $db = new Database();
    
    // Get loan details
    $db->query("SELECT * FROM loans WHERE id = :loan_id");
    $db->bind(':loan_id', $loan_id);
    $loan = $db->single();
    
    if (!$loan) {
        return false;
    }
    
    // Calculate monthly repayment amount
    $monthly_amount = $loan['total_repayment'] / $loan['duration_months'];
    
    // Create repayment schedule
    for ($i = 1; $i <= $loan['duration_months']; $i++) {
        $due_date = date('Y-m-d', strtotime("+$i months", strtotime($loan['approved_date'] ?? date('Y-m-d'))));
        
        $db->query("
            INSERT INTO loan_repayments (loan_id, amount_due, due_date, status) 
            VALUES (:loan_id, :amount_due, :due_date, 'pending')
        ");
        $db->bind(':loan_id', $loan_id);
        $db->bind(':amount_due', $monthly_amount);
        $db->bind(':due_date', $due_date);
        $db->execute();
    }
    
    return true;
}
?>