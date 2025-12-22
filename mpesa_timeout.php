<?php
/*
 * M-Pesa Timeout Handler
 *
 * This file handles timeout notifications from Safaricom's M-Pesa API.
 * When an STK Push request times out without user confirmation, this endpoint is called.
 *
 * @author ChamaSys Development Team
 * @version 1.0
 * @since 2025
 */

require_once 'DatabaseClass.php';

// Set content type to JSON for proper response
header('Content-Type: application/json');

// Log the raw input for debugging
$raw_post_data = file_get_contents('php://input');
error_log("M-Pesa Timeout Raw Data: " . $raw_post_data);

// Decode the JSON data from M-Pesa
$timeout_data = json_decode($raw_post_data, true);

if (!$timeout_data) {
    // Invalid JSON received
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data received']);
    exit();
}

// Process the timeout notification
processTimeout($timeout_data);

/**
 * Process timeout notification from M-Pesa API
 */
function processTimeout($timeout_data) {
    // Extract important information from the timeout data
    $checkout_request_id = $timeout_data['CheckoutRequestID'] ?? null;
    
    if (!$checkout_request_id) {
        error_log("Timeout notification received without CheckoutRequestID: " . print_r($timeout_data, true));
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing CheckoutRequestID']);
        exit();
    }
    
    // Log the timeout event
    error_log("M-Pesa STK Push timeout: CheckoutRequestID=$checkout_request_id");
    
    // Update the request status in the database
    $db = new Database();
    $db->query("
        UPDATE mpesa_stk_requests 
        SET status = 'Canceled', result_code = 1001, result_desc = 'Request timeout' 
        WHERE checkout_request_id = :checkout_request_id
    ");
    $db->bind(':checkout_request_id', $checkout_request_id);
    
    if ($db->execute()) {
        // Update any pending contributions associated with this request
        // Since the payment timed out, we might want to keep the contribution as pending
        // or mark it as failed depending on business logic
        $db->query("
            UPDATE contributions 
            SET status = 'pending' 
            WHERE mpesa_code = :checkout_request_id
        ");
        $db->bind(':checkout_request_id', $checkout_request_id);
        $db->execute();
        
        // Response to acknowledge successful processing
        echo json_encode(['status' => 'success', 'message' => 'Timeout processed successfully']);
    } else {
        error_log("Failed to update timeout status in database: " . $checkout_request_id);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update timeout status']);
    }
}
?>