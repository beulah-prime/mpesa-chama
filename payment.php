<?php
/*
 * M-Pesa Payment Processing Page for Chama Management System
 *
 * This page allows members to make contributions via M-Pesa STK Push.
 * It provides a form for entering payment details and initiates the payment process.
 *
 * Features:
 * - Secure payment form with validation
 * - M-Pesa STK Push initiation
 * - Payment status tracking
 * - Integration with existing contribution system
 *
 * @author ChamaSys Development Team
 * @version 1.0
 * @since 2025
 */

// Start session to maintain user state across pages
session_start();

// Include the DatabaseClass and MpesaAPI which provide necessary functionality
require_once 'DatabaseClass.php';
require_once 'MpesaAPI.php';

// Check if user is logged in by verifying session variable exists
if (!isset($_SESSION['user'])) {
    // Redirect unauthorized users to login page
    header('Location: Login.php');
    // Exit script to prevent further execution
    exit();
}

// Store current user data from session for use in the page
$user = $_SESSION['user'];

// Get member ID by looking up the member record associated with this user
$db = new Database();
$db->query('SELECT id FROM members WHERE user_id = :user_id');
$db->bind(':user_id', $user['id']);
$member_result = $db->single();

if (!$member_result) {
    // Stop execution if member record is not found (should not happen in normal operation)
    die('Member record not found.');
}

// Store the member ID for use in the page
$member_id = $member_result['id'];

// Initialize error and success message variables
$error = '';
$success = '';
$stk_result = null;

// Process payment form submission when POST data is received
if ($_POST && isset($_POST['initiate_payment'])) {
    // Sanitize and validate user input
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']) ?: 'Chama Contribution';
    
    // Validate the amount
    if ($amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } elseif ($amount < 10) {
        // M-Pesa has minimum transaction limits
        $error = 'Minimum transaction amount is KES 10.';
    } else {
        // Create MpesaAPI object and initiate the payment
        $mpesa = new MpesaAPI();
        
        // Check if M-Pesa is properly configured
        if (!$mpesa->isConfigured()) {
            $error = 'M-Pesa is not properly configured. Please contact the system administrator.';
        } else {
            // Initiate STK Push with the user's phone number and specified amount
            $stk_result = $mpesa->initiateStkPush($user['phone_number'], $amount, "CHAMA-$member_id", $description);
            
            if ($stk_result['success']) {
                $success = 'Payment request sent to your phone. Please enter your M-Pesa PIN to complete the transaction.';
                
                // Add the contribution as pending in the database
                $contribution = new Contribution();
                $contribution->addContribution($member_id, $amount, 'mpesa', null); // Status will be 'pending' until callback confirms
            } else {
                $error = 'Failed to initiate payment: ' . ($stk_result['message'] ?? 'Unknown error occurred');
            }
        }
    }
}

// Process form to check payment status
if ($_POST && isset($_POST['check_status'])) {
    $checkout_request_id = $_POST['checkout_request_id'] ?? '';
    
    if (!empty($checkout_request_id)) {
        $mpesa = new MpesaAPI();
        $status_result = $mpesa->queryStkStatus($checkout_request_id);
        
        if ($status_result['success']) {
            $result = $status_result['response'];
            
            if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
                $success = 'Payment status: ' . ($result['CheckoutRequestID'] ?? 'Unknown');
            } else {
                $error = 'Status query failed: ' . ($result['errorMessage'] ?? 'Unknown error');
            }
        } else {
            $error = 'Failed to query payment status: ' . ($status_result['message'] ?? 'Unknown error');
        }
    } else {
        $error = 'Please provide a Checkout Request ID to check status.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment - Chama Management System</title>
    <link rel="stylesheet" href="dash-mod.css">
    <style>
        .payment-container {
            max-width: 600px;
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
        
        input[type="number"], input[type="text"], textarea {
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
        
        .status-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .payment-info {
            background-color: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <aside>
            <div class="top">
                <div class="logo">
                    <span class="logo">Integrated</span>
                </div>
                <div class="close" id="close-btn">
                    <span>close</span>
                </div>
            </div>

            <div class="sidebar">
                <a href="Members.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Dashboard</h3>
                </a>
                <a href="Contributions.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>My Contributions</h3>
                </a>
                <a href="payment.php" class="active">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Make Payment</h3>
                </a>
                <a href="Loans.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>My Loans</h3>
                </a>
                <a href="Fines.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>My Fines</h3>
                </a>
                <a href="logout.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Logout</h3>
                </a>
            </div>
        </aside>
    </div>

    <main class="main-content">
        <div class="payment-container">
            <h1>Make M-Pesa Payment</h1>
            <p>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>. Make a contribution to the Chama using M-Pesa.</p>
            
            <div class="payment-info">
                <h3>Payment Information</h3>
                <p><strong>Phone Number:</strong> <?php echo htmlspecialchars($user['phone_number']); ?></p>
                <p><strong>Member ID:</strong> <?php echo htmlspecialchars($member_result['id']); ?></p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="message success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="amount">Amount (KES):</label>
                    <input type="number" id="amount" name="amount" min="10" max="70000" step="10" placeholder="Enter amount to contribute" required>
                </div>

                <div class="form-group">
                    <label for="description">Description:</label>
                    <input type="text" id="description" name="description" placeholder="Payment description (optional)" maxlength="50">
                </div>

                <button type="submit" name="initiate_payment">Initiate M-Pesa Payment</button>
            </form>
            
            <?php if ($stk_result && $stk_result['success']): ?>
            <div class="status-card">
                <h3>Payment Initiated Successfully</h3>
                <p><strong>Checkout Request ID:</strong> <?php echo htmlspecialchars($stk_result['checkout_request_id']); ?></p>
                <p><strong>Merchant Request ID:</strong> <?php echo htmlspecialchars($stk_result['merchant_request_id']); ?></p>
                <p>Please check your phone for the M-Pesa prompt. Enter your PIN to complete the payment.</p>
                
                <form method="POST" action="" style="margin-top: 15px;">
                    <input type="hidden" name="checkout_request_id" value="<?php echo htmlspecialchars($stk_result['checkout_request_id']); ?>">
                    <button type="submit" name="check_status">Check Payment Status</button>
                </form>
            </div>
            <?php endif; ?>
            
            <div class="status-card">
                <h3>How to Pay</h3>
                <ol>
                    <li>Enter the amount you wish to contribute</li>
                    <li>Click "Initiate M-Pesa Payment"</li>
                    <li>Confirm the payment on your phone when prompted</li>
                    <li>Enter your M-Pesa PIN when requested</li>
                    <li>Your contribution will be recorded automatically once payment is confirmed</li>
                </ol>
            </div>
        </div>
    </main>

    <script>
        // Close button functionality
        document.getElementById('close-btn').addEventListener('click', function() {
            document.querySelector('aside').style.display = 'none';
        });
    </script>
</body>
</html>