<?php
/*
 * Treasurer Dashboard for Chama Management System
 *
 * This page serves as the main dashboard for treasurers.
 * It provides functionality to record contributions and apply fines.
 *
 * Features:
 * - Authentication and role-based access control
 * - Contribution recording functionality
 * - Fine application functionality
 * - Member selection interface
 * - Payment method selection (M-Pesa, cash, bank transfer)
 * - Secure transaction processing
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

// Check if user has treasurer role - only treasurers can access this page
if ($_SESSION['user']['role'] !== 'treasurer') {
    // Get current user's role for proper redirection
    $role = $_SESSION['user']['role'];
    switch($role) {
        case 'admin':
            // Redirect admins to their dashboard
            header('Location: Admin.php');
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

// Create User object to access user-related database methods
$treasurer = new User();

// Get all members to populate the member selection dropdown
$members = $treasurer->getAllUsersByRole('member');

// Initialize message variable for user feedback
$message = '';

// Process form submissions when POST data is received
if ($_POST) {
    // Handle contribution recording form
    if (isset($_POST['record_contribution'])) {
        // Sanitize and validate input data
        $member_id = (int)$_POST['member_id'];                    // Cast to integer for security
        $amount = (float)$_POST['amount'];                        // Cast to float for amount
        $payment_method = $_POST['payment_method'];               // Payment method (mpesa/cash/bank)
        $mpesa_code = !empty($_POST['mpesa_code']) ? $_POST['mpesa_code'] : null;  // Optional M-Pesa code

        // Create Contribution object to access contribution-related methods
        $contribution = new Contribution();

        // Attempt to record the contribution in the database
        if ($contribution->addContribution($member_id, $amount, $payment_method, $mpesa_code)) {
            // Success message if contribution was recorded
            $message = "Contribution recorded successfully!";
        } else {
            // Error message if contribution recording failed
            $message = "Failed to record contribution.";
        }
    }

    // Handle fine application form
    if (isset($_POST['apply_fine'])) {
        // Sanitize and validate input data
        $member_id = (int)$_POST['fine_member_id'];              // Cast to integer for security
        $reason = $_POST['fine_reason'];                          // Reason for the fine
        $amount = !empty($_POST['fine_amount']) ? (float)$_POST['fine_amount'] : null;  // Optional fine amount

        // Create Fine object to access fine-related methods
        $fine = new Fine();

        // Attempt to apply the fine in the database
        // $user['id'] is passed as the person imposing the fine
        if ($fine->addFine($member_id, $reason, $amount, $user['id'])) {
            // Success message if fine was applied
            $message = "Fine applied successfully!";
        } else {
            // Error message if fine application failed
            $message = "Failed to apply fine.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treasurer Dashboard - Chama Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        .logo {
            background-color: #00A651;
            color: white;
            padding: 15px;
            text-align: center;
            display: block;
            font-size: 1.5em;
            font-weight: bold;
        }

        ul {
            list-style-type: none;
            margin: 0;
            padding: 0;
            width: 200px;
            background-color: #333;
        }

        li a {
            display: block;
            color: white;
            padding: 12px;
            text-decoration: none;
            border-bottom: 1px solid #555;
        }

        li a:hover {
            background-color: #555;
        }

        .container {
            display: flex;
        }

        .sidebar {
            height: 100vh;
            position: fixed;
            overflow: auto;
        }

        .main-content {
            flex: 1;
            margin-left: 200px;
            padding: 20px;
        }

        .dashboard-header {
            background-color: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .welcome-message {
            margin: 0;
            color: #333;
        }

        .form-container {
            background-color: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            background-color: #00A651;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background-color: #007F3E;
        }

        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <span class="logo">Integrated</span>
            <ul>
                <li><a href="Treasurer.php">Dashboard</a></li>
                <li><a href="Contributions.php">View Contributions</a></li>
                <li><a href="Treasurer.php#record-contributions">Record Contributions</a></li>
                <li><a href="Treasurer.php#manage-loans">Manage Loans</a></li>
                <li><a href="Treasurer.php#manage-fines">Manage Fines</a></li>
                <li><a href="Treasurer.php#transactions">Transactions</a></li>
                <li><a href="Treasurer.php#reports">Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="dashboard-header">
                <h1 class="welcome-message">Welcome, <?php echo htmlspecialchars($user['full_name']); ?> (Treasurer)</h1>
                <p>Manage financial transactions for your Chama</p>
            </div>

            <?php if (!empty($message)): ?>
            <div class="message success">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="form-container">
                <h2>Record Contribution</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="member_id">Select Member:</label>
                        <select name="member_id" id="member_id" required>
                            <option value="">Select a member</option>
                            <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['full_name'] . ' (' . ($member['member_number'] ?? 'N/A') . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="amount">Amount (KES):</label>
                        <input type="number" name="amount" id="amount" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="payment_method">Payment Method:</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="mpesa">M-Pesa</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="mpesa_code">M-Pesa Code (if applicable):</label>
                        <input type="text" name="mpesa_code" id="mpesa_code">
                    </div>

                    <button type="submit" name="record_contribution">Record Contribution</button>
                </form>
            </div>

            <div class="form-container">
                <h2>Apply Fine</h2>
                <form method="post">
                    <div class="form-group">
                        <label for="fine_member_id">Select Member:</label>
                        <select name="fine_member_id" id="fine_member_id" required>
                            <option value="">Select a member</option>
                            <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['full_name'] . ' (' . ($member['member_number'] ?? 'N/A') . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fine_reason">Reason for Fine:</label>
                        <input type="text" name="fine_reason" id="fine_reason" required>
                    </div>

                    <div class="form-group">
                        <label for="fine_amount">Fine Amount (KES) (Leave blank for default):</label>
                        <input type="number" name="fine_amount" id="fine_amount" step="0.01" min="0">
                    </div>

                    <button type="submit" name="apply_fine">Apply Fine</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>