<?php
/*
 * Loan Repayment Page for Chama Management System
 *
 * This page allows members to make loan repayments via M-Pesa.
 * It provides a form for selecting which loan to repay and initiating the payment process.
 *
 * Features:
 * - Secure repayment form with validation
 * - Loan selection for members with multiple loans
 * - M-Pesa STK Push initiation for repayments
 * - Repayment schedule tracking
 * - Integration with existing loan system
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

// Get all active loans for this member
$loan = new Loan();
$member_loans = $loan->getMemberLoans($member_id);

// Get upcoming loan repayments
$db->query("
    SELECT lr.*, l.loan_amount, l.total_repayment
    FROM loan_repayments lr
    JOIN loans l ON lr.loan_id = l.id
    WHERE l.member_id = :member_id 
    AND lr.status = 'pending'
    ORDER BY lr.due_date ASC
    LIMIT 10
");
$db->bind(':member_id', $member_id);
$loan_repayments = $db->resultSet();

// Initialize error and success message variables
$error = '';
$success = '';
$stk_result = null;

// Process payment form submission when POST data is received
if ($_POST && isset($_POST['initiate_repayment'])) {
    // Sanitize and validate user input
    $repayment_id = (int)$_POST['repayment_id'];
    $amount = floatval($_POST['amount']);
    
    // Validate the repayment ID and amount
    if ($repayment_id <= 0) {
        $error = 'Please select a valid repayment to make.';
    } elseif ($amount <= 0) {
        $error = 'Amount must be greater than zero.';
    } elseif ($amount < 10) {
        // M-Pesa has minimum transaction limits
        $error = 'Minimum transaction amount is KES 10.';
    } else {
        // Verify that this repayment belongs to the current member
        $db->query("
            SELECT lr.*, l.member_id
            FROM loan_repayments lr
            JOIN loans l ON lr.loan_id = l.id
            WHERE lr.id = :repayment_id AND l.member_id = :member_id
        ");
        $db->bind(':repayment_id', $repayment_id);
        $db->bind(':member_id', $member_id);
        $repayment = $db->single();
        
        if (!$repayment) {
            $error = 'Invalid repayment selected.';
        } elseif ($repayment['status'] !== 'pending') {
            $error = 'This repayment has already been processed.';
        } else {
            // Create MpesaAPI object and initiate the payment
            $mpesa = new MpesaAPI();
            
            // Check if M-Pesa is properly configured
            if (!$mpesa->isConfigured()) {
                $error = 'M-Pesa is not properly configured. Please contact the system administrator.';
            } else {
                // Initiate STK Push with the user's phone number and specified amount
                $stk_result = $mpesa->initiateStkPush($user['phone_number'], $amount, "LOAN-" . $repayment_id, 'Loan Repayment');
                
                if ($stk_result['success']) {
                    $success = 'Payment request sent to your phone. Please enter your M-Pesa PIN to complete the transaction.';
                    
                    // Update the repayment status to indicate payment initiation
                    $db->query("
                        UPDATE loan_repayments 
                        SET status = 'pending' 
                        WHERE id = :repayment_id
                    ");
                    $db->bind(':repayment_id', $repayment_id);
                    $db->execute();
                } else {
                    $error = 'Failed to initiate payment: ' . ($stk_result['message'] ?? 'Unknown error occurred');
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Repayment - Chama Management System</title>
    <link rel="stylesheet" href="loan_repayment.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Sharp" rel="stylesheet">
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
                    <span class="material-icons-sharp">dashboard</span>
                    <h3>Dashboard</h3>
                </a>
                <a href="Contributions.php">
                    <span class="material-icons-sharp">payments</span>
                    <h3>My Contributions</h3>
                </a>
                <a href="payment.php">
                    <span class="material-icons-sharp">payment</span>
                    <h3>Make Payment</h3>
                </a>
                <a href="Loans.php" class="active">
                    <span class="material-icons-sharp">receipt_long</span>
                    <h3>My Loans</h3>
                </a>
                <a href="loan_repayment.php">
                    <span class="material-icons-sharp">sync_alt</span>
                    <h3>Loan Repayment</h3>
                </a>
                <a href="Fines.php">
                    <span class="material-icons-sharp">warning</span>
                    <h3>My Fines</h3>
                </a>
                <a href="logout.php">
                    <span class="material-icons-sharp">logout</span>
                    <h3>Logout</h3>
                </a>
            </div>
        </aside>

        <!-- Mobile menu button -->
        <button class="menu-btn" id="menu-btn">☰</button>

        <main class="main-content">
            <div class="repayment-container">
            <h1>Loan Repayment</h1>
            <p>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>. Make a loan repayment using M-Pesa.</p>
            
            <div class="repayment-info">
                <h3>Repayment Information</h3>
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
                    <label for="repayment_id">Select Repayment:</label>
                    <select id="repayment_id" name="repayment_id" required>
                        <option value="">-- Select a repayment --</option>
                        <?php foreach ($loan_repayments as $repayment): ?>
                        <option value="<?php echo $repayment['id']; ?>">
                            Due: <?php echo $repayment['due_date']; ?> | Amount: KES <?php echo number_format($repayment['amount_due'], 2); ?> | Loan: <?php echo $repayment['loan_id']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="amount">Amount (KES):</label>
                    <input type="number" id="amount" name="amount" min="10" max="70000" step="10" placeholder="Enter amount to repay" required>
                </div>

                <button type="submit" name="initiate_repayment">Initiate M-Pesa Repayment</button>
            </form>
            
            <?php if ($stk_result && $stk_result['success']): ?>
            <div class="status-card">
                <h3>Repayment Initiated Successfully</h3>
                <p><strong>Checkout Request ID:</strong> <?php echo htmlspecialchars($stk_result['checkout_request_id']); ?></p>
                <p><strong>Merchant Request ID:</strong> <?php echo htmlspecialchars($stk_result['merchant_request_id']); ?></p>
                <p>Please check your phone for the M-Pesa prompt. Enter your PIN to complete the payment.</p>
            </div>
            <?php endif; ?>
            
            <div class="status-card">
                <h3>Upcoming Loan Repayments</h3>
                <?php if (!empty($loan_repayments)): ?>
                <table class="repayments-table">
                    <thead>
                        <tr>
                            <th>Loan ID</th>
                            <th>Due Date</th>
                            <th>Amount Due (KES)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loan_repayments as $repayment): ?>
                        <tr>
                            <td><?php echo $repayment['loan_id']; ?></td>
                            <td><?php echo $repayment['due_date']; ?></td>
                            <td><?php echo number_format($repayment['amount_due'], 2); ?></td>
                            <td class="status-<?php echo $repayment['status']; ?>"><?php echo ucfirst($repayment['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>No upcoming repayments found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Mobile menu toggle functionality
        const menuBtn = document.getElementById('menu-btn');
        const sidebar = document.querySelector('aside');
        const closeBtn = document.getElementById('close-btn');

        menuBtn.addEventListener('click', function() {
            sidebar.classList.add('show');
        });

        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnMenuBtn = menuBtn.contains(event.target);

            if (!isClickInsideSidebar && !isClickOnMenuBtn && window.innerWidth <= 768) {
                sidebar.classList.remove('show');
            }
        });
    </script>

    </main>
    </div>
</body>
</html>