<?php
/*
 * M-Pesa C2B and STK Callback Handler
 *
 * This file handles callbacks from Safaricom's M-Pesa API for:
 * - STK Push payment confirmations
 * - C2B (Customer to Business) payment confirmations
 * - Payment status updates
 *
 * @author ChamaSys Development Team
 * @version 1.0
 * @since 2025
 */

require_once 'DatabaseClass.php';
require_once 'MpesaAPI.php';

// Set content type to JSON for proper response
header('Content-Type: application/json');

// Log the raw input for debugging
$raw_post_data = file_get_contents('php://input');
error_log("M-Pesa Callback Raw Data: " . $raw_post_data);

// Decode the JSON data from M-Pesa
$callback_data = json_decode($raw_post_data, true);

if (!$callback_data) {
    // Invalid JSON received
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received']);
    exit();
}

try {
    // Process the callback based on its type
    if (isset($callback_data['TransactionType'])) {
        // This is a C2B (Customer to Business) callback
        processC2BCallback($callback_data);
    } elseif (isset($callback_data['Body']['stkCallback'])) {
        // This is an STK Push callback
        processStkCallback($callback_data);
    } else {
        // Unknown callback type
        error_log("Unknown callback type received: " . print_r($callback_data, true));
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unknown callback type']);
        exit();
    }
} catch (Exception $e) {
    error_log("Error processing M-Pesa callback: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
    exit();
}

/**
 * Process C2B (Customer to Business) callback
 */
function processC2BCallback($callback_data) {
    // Extract important information from the callback
    $transaction_type = $callback_data['TransactionType'];
    $transaction_id = $callback_data['TransactionID'];
    $transaction_time = $callback_data['TransTime'];
    $transaction_amount = $callback_data['TransAmount'];
    $phone_number = $callback_data['PhoneNumber'];
    $account_number = $callback_data['AccountReference'] ?? '';
    $business_short_code = $callback_data['BusinessShortCode'];
    
    // Format the transaction time to MySQL datetime format
    $formatted_time = substr($transaction_time, 0, 4) . '-' . 
                     substr($transaction_time, 4, 2) . '-' . 
                     substr($transaction_time, 6, 2) . ' ' .
                     substr($transaction_time, 8, 2) . ':' . 
                     substr($transaction_time, 10, 2) . ':' . 
                     substr($transaction_time, 12, 2);
    
    // Log the received C2B transaction
    error_log("C2B Transaction received: ID=$transaction_id, Amount=$transaction_amount, Phone=$phone_number");
    
    // Create a database instance to save the transaction
    $db = new Database();
    
    // Check if transaction already exists to prevent duplicates
    $db->query("SELECT id FROM mpesa_transactions WHERE mpesa_code = :mpesa_code");
    $db->bind(':mpesa_code', $transaction_id);
    $existing_transaction = $db->single();
    
    if ($existing_transaction) {
        // Transaction already exists, acknowledge and exit
        echo json_encode(['status' => 'success', 'message' => 'Duplicate transaction']);
        return;
    }
    
    // Find the member associated with this phone number
    $db->query("SELECT m.id as member_id FROM members m JOIN users u ON m.user_id = u.id WHERE u.phone_number = :phone_number");
    $db->bind(':phone_number', $phone_number);
    $member = $db->single();
    
    if (!$member) {
        // If we can't find a member with this phone number, we can't process the payment
        error_log("No member found for phone number: $phone_number");
        echo json_encode(['status' => 'success', 'message' => 'Member not found']);
        return;
    }
    
    $member_id = $member['member_id'];
    
    // Determine the transaction type based on the account number or other factors
    $transaction_type_db = 'contribution'; // Default to contribution
    
    // If we can identify the account number as a specific type, set it accordingly
    if (stripos($account_number, 'FINE') !== false) {
        $transaction_type_db = 'fine_payment';
    } elseif (stripos($account_number, 'LOAN') !== false) {
        $transaction_type_db = 'loan_payment';
    }
    
    // Insert the transaction into the database
    $db->query("
        INSERT INTO mpesa_transactions (
            transaction_id, member_id, amount, transaction_type, 
            reference_id, mpesa_code, transaction_date, status
        ) VALUES (
            :transaction_id, :member_id, :amount, :transaction_type, 
            :reference_id, :mpesa_code, :transaction_date, 'confirmed'
        )
    ");
    
    $db->bind(':transaction_id', uniqid('txn_', true)); // Generate a unique transaction ID for our system
    $db->bind(':member_id', $member_id);
    $db->bind(':amount', $transaction_amount);
    $db->bind(':transaction_type', $transaction_type_db);
    $db->bind(':reference_id', 0); // We'll update this later based on the account number
    $db->bind(':mpesa_code', $transaction_id);
    $db->bind(':transaction_date', $formatted_time);
    
    if ($db->execute()) {
        // Update any pending contributions, fines, or loans that match this amount and phone number
        updateRelatedRecords($member_id, $transaction_amount, $transaction_id, $account_number);
        
        // Response to acknowledge successful processing
        echo json_encode(['status' => 'success', 'message' => 'C2B transaction processed successfully']);
    } else {
        error_log("Failed to insert C2B transaction into database: " . $transaction_id);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save transaction']);
    }
}

/**
 * Process STK Push callback
 */
function processStkCallback($callback_data) {
    $mpesa = new MpesaAPI();
    
    // Process the callback using the MpesaAPI class
    $result = $mpesa->processCallback($callback_data);
    
    if ($result) {
        // Successfully processed the callback
        echo json_encode(['status' => 'success', 'message' => 'STK callback processed successfully']);
    } else {
        error_log("Failed to process STK callback: " . print_r($callback_data, true));
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to process callback']);
    }
}

/**
 * Update related records (contributions, fines, loans) based on the M-Pesa transaction
 */
function updateRelatedRecords($member_id, $amount, $mpesa_code, $account_number) {
    $db = new Database();
    
    // First, try to match to a pending contribution
    $db->query("
        UPDATE contributions 
        SET status = 'confirmed', mpesa_code = :mpesa_code 
        WHERE member_id = :member_id AND amount = :amount AND status = 'pending'
        ORDER BY created_at DESC LIMIT 1
    ");
    $db->bind(':mpesa_code', $mpesa_code);
    $db->bind(':member_id', $member_id);
    $db->bind(':amount', $amount);
    $contribution_updated = $db->execute();
    
    if ($contribution_updated && $db->rowCount() > 0) {
        error_log("Updated contribution for member $member_id with M-Pesa code $mpesa_code");
        return;
    }
    
    // If no matching contribution, try to match to a pending fine
    $db->query("
        UPDATE fines 
        SET status = 'paid', paid_date = CURDATE() 
        WHERE member_id = :member_id AND amount = :amount AND status = 'pending'
        ORDER BY created_at DESC LIMIT 1
    ");
    $db->bind(':member_id', $member_id);
    $db->bind(':amount', $amount);
    $fine_updated = $db->execute();
    
    if ($fine_updated && $db->rowCount() > 0) {
        error_log("Updated fine for member $member_id with M-Pesa code $mpesa_code");
        return;
    }
    
    // If no matching fine, try to match to a pending loan repayment
    // First, try to match by account number if it contains a loan repayment ID
    if (!empty($account_number) && strpos($account_number, 'LOAN-') !== false) {
        // Extract the repayment ID from the account number
        $repayment_id = str_replace('LOAN-', '', $account_number);

        $db->query("
            UPDATE loan_repayments
            SET amount_paid = :amount, payment_date = CURDATE(), status = 'paid'
            WHERE id = :repayment_id AND status = 'pending'
        ");
        $db->bind(':amount', $amount);
        $db->bind(':repayment_id', $repayment_id);
        $loan_updated = $db->execute();

        if ($loan_updated && $db->rowCount() > 0) {
            error_log("Updated loan repayment ID $repayment_id with M-Pesa code $mpesa_code");
            return;
        }
    }

    // If not matched by account number, try to match by amount and member
    $db->query("
        UPDATE loan_repayments
        SET amount_paid = :amount, payment_date = CURDATE(), status = 'paid'
        WHERE member_id = :member_id AND amount_due = :amount AND status = 'pending'
        ORDER BY created_at DESC LIMIT 1
    ");
    $db->bind(':amount', $amount);
    $db->bind(':member_id', $member_id);
    $loan_updated = $db->execute();

    if ($loan_updated && $db->rowCount() > 0) {
        error_log("Updated loan repayment for member $member_id with M-Pesa code $mpesa_code");
        return;
    }
    
    // If we couldn't match to any specific record, we still have the transaction recorded
    // in the mpesa_transactions table which serves as an audit trail
    error_log("M-Pesa transaction $mpesa_code recorded but not matched to specific contribution/fine/loan");
}

?>