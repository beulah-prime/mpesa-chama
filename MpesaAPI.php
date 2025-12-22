<?php
/*
 * M-Pesa API Integration Class for Chama Management System
 *
 * This class handles all M-Pesa API operations including:
 * - Authentication with Daraja API
 * - STK Push initiation for payments
 * - Transaction status queries
 * - Configuration management
 *
 * @author ChamaSys Development Team
 * @version 1.0
 * @since 2025
 */

require_once 'DatabaseClass.php';

class MpesaAPI {
    
    // Environment configuration - set to 'sandbox' for testing, 'live' for production
    private $environment = 'sandbox';  // Change to 'live' for production
    
    // Base URLs for Safaricom's API
    private $base_url_sandbox = 'https://sandbox.safaricom.co.ke';
    private $base_url_live = 'https://api.safaricom.co.ke';
    
    // API endpoints
    private $token_endpoint = '/oauth/v1/generate?grant_type=client_credentials';
    private $stk_push_endpoint = '/mpesa/stkpush/v1/processrequest';
    private $stk_status_endpoint = '/mpesa/stkpushquery/v1/query';
    
    // API credentials - These should be configured in your settings
    private $consumer_key;
    private $consumer_secret;
    private $business_short_code;  // Your PayBill number or till number
    private $passkey;              // From your test credentials or live portal
    private $callback_url;         // URL to receive payment results
    private $timeout_url;          // URL to receive timeout notifications
    
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        
        // Load M-Pesa settings from the database
        $settings = new Settings();
        $this->consumer_key = $settings->getSetting('mpesa_consumer_key');
        $this->consumer_secret = $settings->getSetting('mpesa_consumer_secret');
        $this->business_short_code = $settings->getSetting('mpesa_business_shortcode');
        $this->passkey = $settings->getSetting('mpesa_passkey');
        
        // Set callback URLs - adjust as needed for your domain
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $domain = $_SERVER['HTTP_HOST'];
        $this->callback_url = $protocol . '://' . $domain . '/mpesa_callback.php';
        $this->timeout_url = $protocol . '://' . $domain . '/mpesa_timeout.php';
    }
    
    /**
     * Get access token from Safaricom API
     * This is required for authenticating all other API calls
     * 
     * @return string Access token or false on failure
     */
    public function getAccessToken() {
        // Check if we have a cached token that's still valid
        $cached_token = $this->getCachedToken();
        if ($cached_token && $this->isTokenValid($cached_token)) {
            return $cached_token['access_token'];
        }
        
        // Construct the API endpoint URL
        $url = ($this->environment === 'live') ? $this->base_url_live : $this->base_url_sandbox;
        $url .= $this->token_endpoint;
        
        // Set up cURL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($this->consumer_key . ':' . $this->consumer_secret),
            'Content-Type: application/json'
        ]);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        
        // Execute the request
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        // Check if request was successful
        if ($http_code !== 200) {
            error_log("M-Pesa token request failed with HTTP code: $http_code, Response: $response");
            return false;
        }
        
        // Parse the response
        $result = json_decode($response, true);
        
        if (isset($result['access_token'])) {
            // Cache the new token
            $this->cacheToken($result['access_token'], $result['expires_in']);
            return $result['access_token'];
        }
        
        error_log("Failed to get access token from M-Pesa API. Response: " . print_r($result, true));
        return false;
    }
    
    /**
     * Cache the access token in the database
     */
    private function cacheToken($token, $expires_in) {
        $expiry_time = time() + ($expires_in - 300); // Subtract 5 minutes to refresh early
        
        $this->db->query("REPLACE INTO api_tokens (token_type, token_value, expiry_time) VALUES ('mpesa_access_token', :token, :expiry)");
        $this->db->bind(':token', $token);
        $this->db->bind(':expiry', date('Y-m-d H:i:s', $expiry_time));
        $this->db->execute();
    }
    
    /**
     * Retrieve cached token from database
     */
    private function getCachedToken() {
        $this->db->query("SELECT token_value as access_token, expiry_time FROM api_tokens WHERE token_type = 'mpesa_access_token'");
        return $this->db->single();
    }
    
    /**
     * Check if the cached token is still valid
     */
    private function isTokenValid($token_data) {
        $expiry_time = strtotime($token_data['expiry_time']);
        return time() < $expiry_time;
    }
    
    /**
     * Initiate STK Push payment
     * 
     * @param string $phone_number Customer's phone number (format: 2547XXXXXXXX)
     * @param float $amount Amount to charge
     * @param string $account_number Account number or reference (optional)
     * @param string $description Description of the transaction
     * @return array Response from the API
     */
    public function initiateStkPush($phone_number, $amount, $account_number = '', $description = 'Chama Contribution') {
        // Clean the phone number to ensure it's in the right format
        $phone_number = $this->formatPhoneNumber($phone_number);
        
        // Get access token
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return ['success' => false, 'message' => 'Failed to get access token'];
        }
        
        // Generate password for the request
        $timestamp = date('YmdHis');
        $password = base64_encode($this->business_short_code . $this->passkey . $timestamp);
        
        // Construct the API endpoint URL
        $url = ($this->environment === 'live') ? $this->base_url_live : $this->base_url_sandbox;
        $url .= $this->stk_push_endpoint;
        
        // Prepare the request payload
        $payload = [
            'BusinessShortCode' => $this->business_short_code,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',  // Or 'CustomerBuyGoodsOnline' for till numbers
            'Amount' => round($amount),
            'PartyA' => $phone_number,
            'PartyB' => $this->business_short_code,
            'PhoneNumber' => $phone_number,
            'CallBackURL' => $this->callback_url,
            'TimeoutURL' => $this->timeout_url,
            'AccountReference' => !empty($account_number) ? $account_number : 'CHAMA-' . date('Ymd'),
            'TransactionDesc' => substr($description, 0, 13) // Max 13 chars
        ];
        
        // Set up cURL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);  // Only for development
        
        // Execute the request
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        // Log the request for debugging
        error_log("M-Pesa STK Push request: " . json_encode($payload));
        error_log("M-Pesa STK Push response: " . $response);
        
        // Check if request was successful
        if ($http_code !== 200) {
            error_log("M-Pesa STK Push failed with HTTP code: $http_code, Response: $response");
            return [
                'success' => false,
                'message' => 'Request failed with HTTP code: ' . $http_code,
                'response' => $response
            ];
        }
        
        // Parse the response
        $result = json_decode($response, true);
        
        // Check if the API returned success
        if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
            // Save the request details for tracking
            $this->saveStkRequest(
                $result['CheckoutRequestID'],
                $phone_number,
                $amount,
                $account_number,
                $description
            );
            
            return [
                'success' => true,
                'checkout_request_id' => $result['CheckoutRequestID'],
                'customer_message' => $result['CustomerMessage'],
                'merchant_request_id' => $result['MerchantRequestID']
            ];
        } else {
            return [
                'success' => false,
                'message' => $result['errorMessage'] ?? 'Unknown error occurred',
                'response' => $result
            ];
        }
    }
    
    /**
     * Query the status of an STK Push request
     * 
     * @param string $checkout_request_id Checkout request ID from the original STK Push
     * @return array Response from the API
     */
    public function queryStkStatus($checkout_request_id) {
        // Get access token
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return ['success' => false, 'message' => 'Failed to get access token'];
        }
        
        // Generate password for the request
        $timestamp = date('YmdHis');
        $password = base64_encode($this->business_short_code . $this->passkey . $timestamp);
        
        // Construct the API endpoint URL
        $url = ($this->environment === 'live') ? $this->base_url_live : $this->base_url_sandbox;
        $url .= $this->stk_status_endpoint;
        
        // Prepare the request payload
        $payload = [
            'BusinessShortCode' => $this->business_short_code,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkout_request_id
        ];
        
        // Set up cURL
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);  // Only for development
        
        // Execute the request
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        // Log the request for debugging
        error_log("M-Pesa STK Query request: " . json_encode($payload));
        error_log("M-Pesa STK Query response: " . $response);
        
        // Check if request was successful
        if ($http_code !== 200) {
            error_log("M-Pesa STK Query failed with HTTP code: $http_code, Response: $response");
            return [
                'success' => false,
                'message' => 'Request failed with HTTP code: ' . $http_code,
                'response' => $response
            ];
        }
        
        // Parse the response
        $result = json_decode($response, true);
        
        return [
            'success' => true,
            'response' => $result
        ];
    }
    
    /**
     * Format phone number to M-Pesa required format (2547XXXXXXXX)
     */
    private function formatPhoneNumber($phone_number) {
        // Remove any spaces, dashes, or parentheses
        $phone_number = preg_replace('/[^0-9]/', '', $phone_number);
        
        // Handle different formats
        if (substr($phone_number, 0, 3) === '254') {
            // Already in correct format
            return $phone_number;
        } elseif (substr($phone_number, 0, 1) === '0') {
            // Convert from 07XXXXXXXX format
            return '254' . substr($phone_number, 1);
        } elseif (substr($phone_number, 0, 1) === '+') {
            // Remove + and format
            return '254' . substr($phone_number, 3);
        } else {
            // Assume it's in 7XXXXXXXX format
            return '254' . $phone_number;
        }
    }
    
    /**
     * Save STK Push request details for tracking
     */
    private function saveStkRequest($checkout_request_id, $phone_number, $amount, $account_number, $description) {
        $this->db->query("
            INSERT INTO mpesa_stk_requests (
                checkout_request_id, 
                phone_number, 
                amount, 
                account_number, 
                description, 
                status, 
                created_at
            ) VALUES (
                :checkout_request_id, 
                :phone_number, 
                :amount, 
                :account_number, 
                :description, 
                'Pending', 
                NOW()
            )
        ");
        
        $this->db->bind(':checkout_request_id', $checkout_request_id);
        $this->db->bind(':phone_number', $phone_number);
        $this->db->bind(':amount', $amount);
        $this->db->bind(':account_number', $account_number);
        $this->db->bind(':description', $description);
        
        return $this->db->execute();
    }
    
    /**
     * Update STK Push request status
     */
    public function updateStkRequestStatus($checkout_request_id, $status, $result_code = null, $result_desc = null) {
        $this->db->query("
            UPDATE mpesa_stk_requests 
            SET status = :status, result_code = :result_code, result_desc = :result_desc, updated_at = NOW()
            WHERE checkout_request_id = :checkout_request_id
        ");
        
        $this->db->bind(':status', $status);
        $this->db->bind(':result_code', $result_code);
        $this->db->bind(':result_desc', $result_desc);
        $this->db->bind(':checkout_request_id', $checkout_request_id);
        
        return $this->db->execute();
    }
    
    /**
     * Process payment callback from M-Pesa
     */
    public function processCallback($callback_data) {
        if (!isset($callback_data['Body']['stkCallback'])) {
            error_log("Invalid callback data format");
            return false;
        }
        
        $stk_callback = $callback_data['Body']['stkCallback'];
        $checkout_request_id = $stk_callback['CheckoutRequestID'];
        $result_code = $stk_callback['ResultCode'];
        $result_desc = $stk_callback['ResultDesc'];
        
        // Update the request status
        $status = ($result_code == 0) ? 'Completed' : 'Failed';
        $this->updateStkRequestStatus($checkout_request_id, $status, $result_code, $result_desc);
        
        // If payment was successful, process the transaction
        if ($result_code == 0) {
            // Extract the transaction details
            if (isset($stk_callback['CallbackMetadata']['Item'])) {
                $items = $stk_callback['CallbackMetadata']['Item'];
                
                // Look for the M-Pesa transaction code
                $mpesa_code = null;
                $amount_paid = 0;
                $phone_number = null;
                
                foreach ($items as $item) {
                    if ($item['Name'] === 'MpesaReceiptNumber') {
                        $mpesa_code = $item['Value'];
                    } elseif ($item['Name'] === 'Amount') {
                        $amount_paid = $item['Value'];
                    } elseif ($item['Name'] === 'PhoneNumber') {
                        $phone_number = $item['Value'];
                    }
                }
                
                // Update the transaction in our system
                $this->processSuccessfulPayment($checkout_request_id, $mpesa_code, $amount_paid, $phone_number);
            }
        }
        
        return true;
    }
    
    /**
     * Process successful payment by updating the contribution record
     */
    private function processSuccessfulPayment($checkout_request_id, $mpesa_code, $amount_paid, $phone_number) {
        // First, get the original request details
        $this->db->query("
            SELECT * FROM mpesa_stk_requests 
            WHERE checkout_request_id = :checkout_request_id
        ");
        $this->db->bind(':checkout_request_id', $checkout_request_id);
        $request = $this->db->single();
        
        if (!$request) {
            error_log("Could not find STK request for ID: $checkout_request_id");
            return false;
        }
        
        // In a real scenario, you'd link this to a specific contribution or fine
        // For now, I'll update the contribution record that matches the amount and phone number
        // You'll need to customize this based on your specific business logic
        
        // For this example, I'll assume we need to update a pending contribution
        // You might need to modify this to match your specific use case
        $this->db->query("
            UPDATE contributions 
            SET status = 'confirmed', mpesa_code = :mpesa_code 
            WHERE amount = :amount AND payment_method = 'mpesa' AND status = 'pending'
        ");
        $this->db->bind(':mpesa_code', $mpesa_code);
        $this->db->bind(':amount', $request['amount']);
        return $this->db->execute();
    }
    
    /**
     * Get the current environment (sandbox/live)
     */
    public function getEnvironment() {
        return $this->environment;
    }
    
    /**
     * Set the environment (sandbox/live)
     */
    public function setEnvironment($env) {
        $this->environment = $env;
    }
    
    /**
     * Validate if M-Pesa is properly configured
     */
    public function isConfigured() {
        return !empty($this->consumer_key) && 
               !empty($this->consumer_secret) && 
               !empty($this->business_short_code) && 
               !empty($this->passkey);
    }
}

// Example usage:
// $mpesa = new MpesaAPI();
// $result = $mpesa->initiateStkPush('254712345678', 100, 'ACC123', 'Chama Contribution');
// var_dump($result);
?>