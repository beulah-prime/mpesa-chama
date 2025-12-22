<?php
/*
 * M-Pesa Settings Configuration Page
 *
 * This page allows administrators to configure M-Pesa API credentials
 * and other payment-related settings.
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

// Check if user has admin role - only admins can access this page
if ($_SESSION['user']['role'] !== 'admin') {
    // Get current user's role for proper redirection
    $role = $_SESSION['user']['role'];
    switch($role) {
        case 'treasurer':
            // Redirect treasurers to their dashboard
            header('Location: Treasurer.php');
            break;
        case 'member':
            // Redirect members to their dashboard
            header('Location: Members.php');
            break;
        default:
            // Default redirect for any other role
            header('Location: index.php');
    }
    // Exit script after redirect
    exit();
}

// Store current user data from session for use in the page
$user = $_SESSION['user'];

// Initialize message variable for user feedback
$message = '';

// Create Settings object to access settings-related methods
$settings = new Settings();

// Process form submission when POST data is received
if ($_POST && isset($_POST['save_settings'])) {
    // Sanitize and validate input data
    $mpesa_consumer_key = trim($_POST['mpesa_consumer_key']);
    $mpesa_consumer_secret = trim($_POST['mpesa_consumer_secret']);
    $mpesa_business_shortcode = trim($_POST['mpesa_business_shortcode']);
    $mpesa_passkey = trim($_POST['mpesa_passkey']);
    $mpesa_callback_url = trim($_POST['mpesa_callback_url']);
    $mpesa_timeout_url = trim($_POST['mpesa_timeout_url']);
    $interest_rate_default = floatval($_POST['interest_rate_default']);
    $fine_amount_default = floatval($_POST['fine_amount_default']);

    // Update settings in the database
    $settings->updateSetting('mpesa_consumer_key', $mpesa_consumer_key);
    $settings->updateSetting('mpesa_consumer_secret', $mpesa_consumer_secret);
    $settings->updateSetting('mpesa_business_shortcode', $mpesa_business_shortcode);
    $settings->updateSetting('mpesa_passkey', $mpesa_passkey);
    $settings->updateSetting('mpesa_callback_url', $mpesa_callback_url);
    $settings->updateSetting('mpesa_timeout_url', $mpesa_timeout_url);
    $settings->updateSetting('interest_rate_default', $interest_rate_default);
    $settings->updateSetting('fine_amount_default', $fine_amount_default);
    
    $message = 'Settings updated successfully!';
}

// Get current settings values
$mpesa_consumer_key = $settings->getSetting('mpesa_consumer_key') ?: '';
$mpesa_consumer_secret = $settings->getSetting('mpesa_consumer_secret') ?: '';
$mpesa_business_shortcode = $settings->getSetting('mpesa_business_shortcode') ?: '';
$mpesa_passkey = $settings->getSetting('mpesa_passkey') ?: '';
$mpesa_callback_url = $settings->getSetting('mpesa_callback_url') ?: '';
$mpesa_timeout_url = $settings->getSetting('mpesa_timeout_url') ?: '';
$interest_rate_default = $settings->getSetting('interest_rate_default') ?: '10.00';
$fine_amount_default = $settings->getSetting('fine_amount_default') ?: '500.00';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa Settings - Chama Management System</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .settings-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"], input[type="password"], input[type="number"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        button {
            background-color: #00A651;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        button:hover {
            background-color: #008542;
        }
        
        .message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .info-box {
            background-color: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <h2 class="logo">Integrated</h2>

        <nav>
            <a href="Admin.php">Dashboard</a>
            <a href="Members.php">Manage Members</a>
            <a href="Contributions.php">View Contributions</a>
            <a href="Loans.php">Manage Loans</a>
            <a href="Fines.php">Manage Fines</a>
            <a href="settings.php" class="active">System Settings</a>
            <a href="logout.php">Logout</a>
        </nav>
    </aside>

    <main class="main">
        <header class="topbar">
            <h1>M-Pesa Settings</h1>

            <div class="top-actions">
                <span class="user">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
            </div>
        </header>

        <div class="settings-container">
            <h2>Configure M-Pesa Integration</h2>
            
            <div class="info-box">
                <h3>How to Get M-Pesa Credentials</h3>
                <p>To configure M-Pesa payments, you need to:</p>
                <ol>
                    <li>Go to <a href="https://developer.safaricom.co.ke/" target="_blank">Safaricom Developer Portal</a></li>
                    <li>Register for an account or log in</li>
                    <li>Create a new app to get Consumer Key and Consumer Secret</li>
                    <li>Get your Business Shortcode from the portal</li>
                    <li>Get the Passkey for your selected environment</li>
                </ol>
                <p><strong>Note:</strong> For testing, use sandbox credentials. For production, use production credentials.</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <h3>M-Pesa API Credentials</h3>
                
                <div class="form-group">
                    <label for="mpesa_consumer_key">M-Pesa Consumer Key:</label>
                    <input type="text" id="mpesa_consumer_key" name="mpesa_consumer_key" 
                           value="<?php echo htmlspecialchars($mpesa_consumer_key); ?>" 
                           placeholder="Enter your M-Pesa Consumer Key">
                </div>

                <div class="form-group">
                    <label for="mpesa_consumer_secret">M-Pesa Consumer Secret:</label>
                    <input type="password" id="mpesa_consumer_secret" name="mpesa_consumer_secret" 
                           value="<?php echo htmlspecialchars($mpesa_consumer_secret); ?>" 
                           placeholder="Enter your M-Pesa Consumer Secret">
                </div>

                <div class="form-group">
                    <label for="mpesa_business_shortcode">Business Shortcode:</label>
                    <input type="text" id="mpesa_business_shortcode" name="mpesa_business_shortcode" 
                           value="<?php echo htmlspecialchars($mpesa_business_shortcode); ?>" 
                           placeholder="Enter your Business Shortcode">
                </div>

                <div class="form-group">
                    <label for="mpesa_passkey">Passkey:</label>
                    <input type="password" id="mpesa_passkey" name="mpesa_passkey"
                           value="<?php echo htmlspecialchars($mpesa_passkey); ?>"
                           placeholder="Enter your Passkey">
                </div>

                <h3>M-Pesa Callback URLs</h3>

                <div class="form-group">
                    <label for="mpesa_callback_url">Callback URL:</label>
                    <input type="url" id="mpesa_callback_url" name="mpesa_callback_url"
                           value="<?php echo htmlspecialchars($mpesa_callback_url); ?>"
                           placeholder="https://yourdomain.com/mpesa_callback.php">
                </div>

                <div class="form-group">
                    <label for="mpesa_timeout_url">Timeout URL:</label>
                    <input type="url" id="mpesa_timeout_url" name="mpesa_timeout_url"
                           value="<?php echo htmlspecialchars($mpesa_timeout_url); ?>"
                           placeholder="https://yourdomain.com/mpesa_timeout.php">
                </div>

                <h3>Default System Settings</h3>
                
                <div class="form-group">
                    <label for="interest_rate_default">Default Interest Rate (%):</label>
                    <input type="number" id="interest_rate_default" name="interest_rate_default" 
                           value="<?php echo htmlspecialchars($interest_rate_default); ?>" 
                           step="0.01" min="0" max="100" placeholder="Default interest rate for loans">
                </div>

                <div class="form-group">
                    <label for="fine_amount_default">Default Fine Amount (KES):</label>
                    <input type="number" id="fine_amount_default" name="fine_amount_default" 
                           value="<?php echo htmlspecialchars($fine_amount_default); ?>" 
                           step="0.01" min="0" placeholder="Default amount for fines">
                </div>

                <button type="submit" name="save_settings">Save Settings</button>
            </form>
        </div>
    </main>
</body>
</html>