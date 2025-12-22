<?php
/*
 * Manage Loans Page for Chama Management System
 *
 * This page allows treasurers to manage loan applications and approvals.
 * Includes functionality to approve/reject loans and disburse funds.
 *
 * Features:
 * - View all loan applications
 * - Approve/reject loan applications
 * - Disburse approved loans
 * - Track loan status
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

// Store current user data and role for use in the page
$user = $_SESSION['user'];
$role = $user['role'];

// Only allow access to treasurers and admins
if ($role !== 'admin' && $role !== 'treasurer') {
    header('Location: Login.php');
    exit();
}

// Initialize message variable
$message = '';

// Handle loan status updates
if (isset($_GET['loan_id']) && isset($_GET['action'])) {
    $loan_id = (int)$_GET['loan_id'];
    $action = $_GET['action'];
    
    $loan = new Loan();
    
    switch ($action) {
        case 'approve':
            if ($loan->updateLoanStatus($loan_id, 'approved', $user['id'])) {
                $message = "Loan approved successfully!";
            } else {
                $message = "Failed to approve loan.";
            }
            break;
        case 'reject':
            if ($loan->updateLoanStatus($loan_id, 'rejected', $user['id'])) {
                $message = "Loan rejected successfully!";
            } else {
                $message = "Failed to reject loan.";
            }
            break;
        case 'disburse':
            if ($loan->updateLoanStatus($loan_id, 'disbursed', $user['id'])) {
                // Create repayment schedule when disbursing
                createLoanRepaymentSchedule($loan_id);
                $message = "Loan disbursed successfully!";
            } else {
                $message = "Failed to disburse loan.";
            }
            break;
        case 'mark_paid':
            if ($loan->updateLoanStatus($loan_id, 'paid', $user['id'])) {
                $message = "Loan marked as paid successfully!";
            } else {
                $message = "Failed to mark loan as paid.";
            }
            break;
    }
}

// Get all loans
$db = new Database();
$db->query("
    SELECT l.*, u.full_name, m.member_number 
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN users u ON m.user_id = u.id
    ORDER BY l.date_applied DESC
");
$all_loans = $db->resultSet();

// Calculate summary data
$total_loans = 0;
$pending_loans = 0;
$approved_loans = 0;
$disbursed_loans = 0;
$paid_loans = 0;
$total_loan_amount = 0;

foreach ($all_loans as $l) {
    $total_loan_amount += $l['loan_amount'];
    switch ($l['status']) {
        case 'pending':
            $pending_loans++;
            break;
        case 'approved':
            $approved_loans++;
            break;
        case 'disbursed':
            $disbursed_loans++;
            break;
        case 'paid':
            $paid_loans++;
            break;
    }
    $total_loans++;
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
    
    // Check if repayment schedule already exists
    $db->query("SELECT COUNT(*) as count FROM loan_repayments WHERE loan_id = :loan_id");
    $db->bind(':loan_id', $loan_id);
    $existing = $db->single();
    
    if ($existing['count'] > 0) {
        return true; // Schedule already exists
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Loans - Chama Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }

        .container {
            display: flex;
        }

        aside {
            width: 260px;
            background: linear-gradient(180deg, #00A651, #00783d);
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 3px 0 10px rgba(0,0,0,0.1);
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            background: rgba(0,0,0,0.1);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
            text-decoration: none;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            text-decoration: none;
            color: #ffffff;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.2);
            color: #ffffff;
            border-left: 4px solid #fff;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        h1 {
            color: #00A651;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .card {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .card h3 {
            margin-top: 0;
            color: #00A651;
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .amount {
            margin: 0;
            font-size: 1.8rem;
            font-weight: bold;
        }

        .amount.total { color: #00A651; }
        .amount.loans { color: #007BFF; }

        .loan-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        h2 {
            color: #00A651;
            margin: 0 0 20px 0;
            font-size: 1.5rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: linear-gradient(135deg, #00A651, #008542);
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .status-pending {
            color: #ffc107;
            font-weight: 500;
        }

        .status-approved {
            color: #007BFF;
            font-weight: 500;
        }

        .status-disbursed {
            color: #17a2b8;
            font-weight: 500;
        }

        .status-paid {
            color: #28a745;
            font-weight: 500;
        }

        .status-rejected {
            color: #dc3545;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-approve {
            background: linear-gradient(135deg, #28a745, #218838);
        }

        .btn-approve:hover {
            background: linear-gradient(135deg, #218838, #1e7e34);
        }

        .btn-reject {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .btn-reject:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
        }

        .btn-disburse {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }

        .btn-disburse:hover {
            background: linear-gradient(135deg, #138496, #117a8b);
        }

        .btn-paid {
            background: linear-gradient(135deg, #ffc107, #e0a800);
        }

        .btn-paid:hover {
            background: linear-gradient(135deg, #e0a800, #d39e00);
        }

        button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            color: white;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <aside>
            <div class="top">
                <h2 class="logo">Integrated</h2>
            </div>

            <div class="sidebar">
                <a href="Treasurer.php">
                    <span class="material-icons-sharp">dashboard</span>
                    <h3>Dashboard</h3>
                </a>
                <a href="Contributions.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>View Contributions</h3>
                </a>
                <a href="Treasurer.php#record-contributions">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Record Contributions</h3>
                </a>
                <a href="Loans.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>View Loans</h3>
                </a>
                <a href="manage_loans.php" class="active">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Manage Loans</h3>
                </a>
                <a href="fines.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Manage Fines</h3>
                </a>
                <a href="transactions.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Transactions</h3>
                </a>
                <a href="reports.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Reports</h3>
                </a>
                <a href="logout.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Logout</h3>
                </a>
            </div>
        </aside>

        <main class="main-content">
            <h1>Manage Loans</h1>
            <p>Welcome, <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst($role); ?>)</p>

            <?php if (!empty($message)): ?>
            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <div class="summary-cards">
                <div class="card">
                    <h3>Total Loans</h3>
                    <p class="amount loans"><?php echo $total_loans; ?></p>
                    <p>KES <?php echo number_format($total_loan_amount, 2); ?></p>
                </div>

                <div class="card">
                    <h3>Pending Applications</h3>
                    <p class="amount total"><?php echo $pending_loans; ?></p>
                </div>

                <div class="card">
                    <h3>Active Loans</h3>
                    <p class="amount total"><?php echo $approved_loans + $disbursed_loans; ?></p>
                </div>

                <div class="card">
                    <h3>Completed Loans</h3>
                    <p class="amount total"><?php echo $paid_loans; ?></p>
                </div>
            </div>

            <div class="loan-section">
                <h2>All Loan Applications</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date Applied</th>
                            <th>Member</th>
                            <th>Amount (KES)</th>
                            <th>Total Repayment (KES)</th>
                            <th>Duration (Months)</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($all_loans)): ?>
                            <?php foreach ($all_loans as $l): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($l['date_applied']); ?></td>
                                <td><?php echo htmlspecialchars($l['full_name'] . ' (' . $l['member_number'] . ')'); ?></td>
                                <td>KES <?php echo number_format($l['loan_amount'], 2); ?></td>
                                <td>KES <?php echo number_format($l['total_repayment'], 2); ?></td>
                                <td><?php echo $l['duration_months']; ?></td>
                                <td class="status-<?php echo $l['status']; ?>"><?php echo ucfirst(htmlspecialchars($l['status'])); ?></td>
                                <td><?php echo htmlspecialchars($l['due_date']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($l['status'] === 'pending'): ?>
                                            <a href="?loan_id=<?php echo $l['id']; ?>&action=approve" class="btn-approve">Approve</a>
                                            <a href="?loan_id=<?php echo $l['id']; ?>&action=reject" class="btn-reject">Reject</a>
                                        <?php elseif ($l['status'] === 'approved'): ?>
                                            <a href="?loan_id=<?php echo $l['id']; ?>&action=disburse" class="btn-disburse">Disburse</a>
                                        <?php elseif ($l['status'] === 'disbursed'): ?>
                                            <a href="?loan_id=<?php echo $l['id']; ?>&action=mark_paid" class="btn-paid">Mark Paid</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8">No loan applications found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>