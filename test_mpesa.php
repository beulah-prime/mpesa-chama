<?php
/*
 * M-Pesa Integration Test Script
 *
 * This script tests the M-Pesa integration functionality to ensure
 * all components are working correctly.
 *
 * @author ChamaSys Development Team
 * @version 1.0
 * @since 2025
 */

require_once 'DatabaseClass.php';
require_once 'MpesaAPI.php';

echo "<h2>M-Pesa Integration Test</h2>\n";

// Test 1: Check if MpesaAPI class can be instantiated
echo "<h3>Test 1: MpesaAPI Class Instantiation</h3>\n";
try {
    $mpesa = new MpesaAPI();
    echo "<p style='color: green;'>✓ MpesaAPI class instantiated successfully</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Failed to instantiate MpesaAPI: " . $e->getMessage() . "</p>\n";
    exit;
}

// Test 2: Check if M-Pesa is configured
echo "<h3>Test 2: M-Pesa Configuration</h3>\n";
if ($mpesa->isConfigured()) {
    echo "<p style='color: green;'>✓ M-Pesa is properly configured</p>\n";
} else {
    echo "<p style='color: orange;'>⚠ M-Pesa is not configured. Please set up credentials in System Settings.</p>\n";
}

// Test 3: Test database tables exist
echo "<h3>Test 3: Database Tables</h3>\n";
$db = new Database();

$tables_to_check = ['mpesa_transactions', 'mpesa_stk_requests', 'api_tokens'];
foreach ($tables_to_check as $table) {
    try {
        $db->query("SELECT 1 FROM $table LIMIT 1");
        echo "<p style='color: green;'>✓ Table '$table' exists</p>\n";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Table '$table' does not exist or is not accessible</p>\n";
    }
}

// Test 4: Show environment
echo "<h3>Test 4: Environment</h3>\n";
$environment = $mpesa->getEnvironment();
echo "<p>Environment: <strong>$environment</strong> (Use 'sandbox' for testing, 'live' for production)</p>\n";

// Test 5: Test access token (only if configured)
echo "<h3>Test 5: Access Token Retrieval</h3>\n";
if ($mpesa->isConfigured()) {
    $token = $mpesa->getAccessToken();
    if ($token) {
        echo "<p style='color: green;'>✓ Successfully retrieved access token</p>\n";
        echo "<p>Token: " . substr($token, 0, 20) . "...</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to retrieve access token. Check your credentials.</p>\n";
    }
} else {
    echo "<p style='color: orange;'>⚠ Cannot test access token - M-Pesa not configured</p>\n";
}

// Test 6: Show callback URLs
echo "<h3>Test 6: Callback URLs</h3>\n";
$reflection = new ReflectionClass($mpesa);
$properties = $reflection->getProperties(ReflectionProperty::IS_PRIVATE);

$callback_url = null;
$timeout_url = null;

// Use reflection to access private properties for testing
foreach ($properties as $property) {
    $property->setAccessible(true);
    if ($property->getName() === 'callback_url') {
        $callback_url = $property->getValue($mpesa);
    }
    if ($property->getName() === 'timeout_url') {
        $timeout_url = $property->getValue($mpesa);
    }
}

echo "<p>Callback URL: <a href='$callback_url' target='_blank'>$callback_url</a></p>\n";
echo "<p>Timeout URL: <a href='$timeout_url' target='_blank'>$timeout_url</a></p>\n";

echo "<h3>Integration Summary</h3>\n";
echo "<p>Files created/modified for M-Pesa integration:</p>\n";
echo "<ul>\n";
echo "<li>MpesaAPI.php - Core M-Pesa API functionality</li>\n";
echo "<li>mpesa_callback.php - Handles payment confirmations</li>\n";
echo "<li>mpesa_timeout.php - Handles payment timeouts</li>\n";
echo "<li>payment.php - Payment processing page for members</li>\n";
echo "<li>settings.php - Configuration page for M-Pesa credentials</li>\n";
echo "<li>database_setup.sql - Updated database schema</li>\n";
echo "</ul>\n";

echo "<p><strong>Next steps:</strong></p>\n";
echo "<ol>\n";
echo "<li>Configure M-Pesa credentials in System Settings (settings.php)</li>\n";
echo "<li>Test with Safaricom's sandbox environment first</li>\n";
echo "<li>Update callback URLs in Safaricom Developer Portal to match your server URLs</li>\n";
echo "<li>Go live after successful testing</li>\n";
echo "</ol>\n";
?>