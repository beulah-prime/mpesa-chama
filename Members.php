<?php
/*
 * Member Dashboard for Chama Management System
 *
 * This page serves as the main dashboard for regular members.
 * It provides an overview of the member's contributions, loans, and fines.
 *
 * Features:
 * - Authentication and role-based access control
 * - Personal financial data display
 * - Contribution history tracking
 * - Loan application history
 * - Fine tracking and management
 * - Summary statistics calculation
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

// Check if user has member role - only members can access this page
if ($_SESSION['user']['role'] !== 'member') {
    // Get current user's role for proper redirection
    $role = $_SESSION['user']['role'];
    switch($role) {
        case 'admin':
            // Redirect admins to their dashboard
            header('Location: Admin.php');
            break;
        case 'treasurer':
            // Redirect treasurers to their dashboard
            header('Location: Treasurer.php');
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

// Get member's contributions, loans, and fines by first finding the member ID
// Note: $user['id'] is the user ID, but we need the member ID to access member-specific data
$db = new Database();
$db->query('SELECT id FROM members WHERE user_id = :user_id');
$db->bind(':user_id', $user['id']);
$member_result = $db->single();

if (!$member_result) {
    // Stop execution if member record is not found (should not happen in normal operation)
    die('Member record not found.');
}

// Store the member ID for accessing member-specific data
$member_id = $member_result['id'];

// Create Contribution object and retrieve the member's contributions
$contribution = new Contribution();
$contributions = $contribution->getMemberContributions($member_id);

// Create Loan object and retrieve the member's loans
$loan = new Loan();
$loans = $loan->getMemberLoans($member_id);

// Create Fine object and retrieve the member's fines
$fine = new Fine();
$fines = $fine->getMemberFines($member_id);

// Calculate summary data: total confirmed contributions for display
$total_contributions = 0;
foreach ($contributions as $c) {
    if ($c['status'] === 'confirmed') {
        // Only count confirmed contributions towards the total
        $total_contributions += $c['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - Chama Management System</title>
    <link rel="stylesheet" href="dash-mod.css">
    <style>
        .dashboard-content {
            padding: 20px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .summary-card h3 {
            margin-top: 0;
            color: #00A651;
        }

        .summary-card h2 {
            margin-bottom: 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .data-table th {
            background-color: #00A651;
            color: white;
        }

        .status-pending {
            color: orange;
        }

        .status-approved, .status-confirmed, .status-paid {
            color: green;
        }

        .status-rejected, .status-failed {
            color: red;
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
                <a href="Members.php" class="active">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Dashboard</h3>
                </a>
                <a href="Contributions.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>My Contributions</h3>
                </a>
                <a href="payment.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Make Payment</h3>
                </a>
                <a href="Members.php#my-loans">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>My Loans</h3>
                </a>
                <a href="Members.php#my-fines">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>My Fines</h3>
                </a>
                <a href="Members.php#profile">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Profile</h3>
                </a>
                <a href="Members.php#settings">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Settings</h3>
                </a>
                <a href="logout.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Logout</h3>
                </a>
            </div>
        </aside>
    </div>

    <main class="dashboard-content">
        <h1>Member Dashboard</h1>
        <p>Welcome, <?php echo htmlspecialchars($user['full_name']); ?> (Member)</p>

        <div class="summary-cards">
            <div class="summary-card">
                <h3>Total Contributions</h3>
                <h2>KES <?php echo number_format($total_contributions, 2); ?></h2>
            </div>

            <div class="summary-card">
                <h3>Active Loans</h3>
                <h2>
                <?php
                $active_loans = 0;
                foreach ($loans as $loan) {
                    if (in_array($loan['status'], ['approved', 'disbursed'])) {
                        $active_loans++;
                    }
                }
                echo $active_loans;
                ?>
                </h2>
            </div>

            <div class="summary-card">
                <h3>Pending Fines</h3>
                <h2>
                <?php
                $pending_fines = 0;
                foreach ($fines as $fine) {
                    if ($fine['status'] === 'pending') {
                        $pending_fines++;
                    }
                }
                echo $pending_fines;
                ?>
                </h2>
            </div>
        </div>

        <div id="my-contributions">
            <h2>My Recent Contributions</h2>
            <?php if (!empty($contributions)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Amount (KES)</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contributions as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['contribution_date']); ?></td>
                        <td><?php echo number_format($c['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($c['payment_method']); ?></td>
                        <td class="status-<?php echo $c['status']; ?>"><?php echo ucfirst($c['status']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No contributions recorded yet.</p>
            <?php endif; ?>
        </div>

        <div id="my-loans" style="margin-top: 30px;">
            <h2>My Loans</h2>
            <?php if (!empty($loans)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date Applied</th>
                        <th>Amount (KES)</th>
                        <th>Total Repayment</th>
                        <th>Status</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loans as $l): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($l['date_applied']); ?></td>
                        <td><?php echo number_format($l['loan_amount'], 2); ?></td>
                        <td><?php echo number_format($l['total_repayment'], 2); ?></td>
                        <td class="status-<?php echo $l['status']; ?>"><?php echo ucfirst($l['status']); ?></td>
                        <td><?php echo htmlspecialchars($l['due_date']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No loans applied yet.</p>
            <?php endif; ?>
        </div>

        <div id="my-fines" style="margin-top: 30px;">
            <h2>My Fines</h2>
            <?php if (!empty($fines)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date Imposed</th>
                        <th>Reason</th>
                        <th>Amount (KES)</th>
                        <th>Status</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fines as $f): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($f['date_imposed']); ?></td>
                        <td><?php echo htmlspecialchars($f['reason']); ?></td>
                        <td><?php echo number_format($f['amount'], 2); ?></td>
                        <td class="status-<?php echo $f['status']; ?>"><?php echo ucfirst($f['status']); ?></td>
                        <td><?php echo htmlspecialchars($f['due_date']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>No fines recorded.</p>
            <?php endif; ?>
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