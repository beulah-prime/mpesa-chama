<?php
/*
 * Transactions Management Page for Chama Management System
 *
 * This page displays all financial transactions in the system for treasurers.
 * Includes contributions, loan payments, fine payments, and other financial activities.
 *
 * Features:
 * - Transaction filtering by date, member, and type
 * - Export functionality
 * - Detailed transaction view
 * - Financial summary statistics
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

// Get all transactions based on filters
$db = new Database();

// Build query with optional filters
$base_query = "SELECT t.*, u.full_name as member_name, m.member_number
               FROM contributions t
               JOIN members m ON t.member_id = m.id
               JOIN users u ON m.user_id = u.id";

$loan_query = "SELECT lr.id as id, lr.amount_paid as amount, lr.payment_date as transaction_date, 
               'loan_payment' as transaction_type, u.full_name as member_name, m.member_number
               FROM loan_repayments lr
               JOIN loans l ON lr.loan_id = l.id
               JOIN members m ON l.member_id = m.id
               JOIN users u ON m.user_id = u.id
               WHERE lr.status = 'paid'";

$fine_query = "SELECT f.id as id, f.amount as amount, f.paid_date as transaction_date,
               'fine_payment' as transaction_type, u.full_name as member_name, m.member_number
               FROM fines f
               JOIN members m ON f.member_id = m.id
               JOIN users u ON m.user_id = u.id
               WHERE f.status = 'paid'";

// Combine all transaction types
$query = "SELECT * FROM (
                    ($base_query)
                    UNION ALL
                    ($loan_query)
                    UNION ALL
                    ($fine_query)
                  ) combined_transactions 
                  ORDER BY transaction_date DESC";

$db->query($query);
$all_transactions = $db->resultSet();

// Calculate summary data
$total_contributions = 0;
$total_loan_payments = 0;
$total_fine_payments = 0;

foreach ($all_transactions as $t) {
    if ($t['transaction_type'] === 'contribution') {
        if ($t['status'] === 'confirmed') {
            $total_contributions += $t['amount'];
        }
    } elseif ($t['transaction_type'] === 'loan_payment') {
        $total_loan_payments += $t['amount'];
    } elseif ($t['transaction_type'] === 'fine_payment') {
        $total_fine_payments += $t['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Chama Management System</title>
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
            font-size: 2rem;
            font-weight: bold;
        }

        .amount.contributions { color: #00A651; }
        .amount.loans { color: #007BFF; }
        .amount.fines { color: #FFC107; }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .filters h3 {
            color: #00A651;
            margin-bottom: 15px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        button {
            background: linear-gradient(135deg, #00A651, #008542);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        button:hover {
            background: linear-gradient(135deg, #008542, #006431);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 166, 81, 0.3);
        }

        .transactions-table {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow-x: auto;
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

        .status-approved, .status-confirmed, .status-paid {
            color: #28a745;
            font-weight: 500;
        }

        .status-rejected, .status-failed {
            color: #dc3545;
            font-weight: 500;
        }

        .transaction-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .type-contribution {
            background-color: #d4edda;
            color: #155724;
        }

        .type-loan {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .type-fine {
            background-color: #fff3cd;
            color: #856404;
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
                <a href="transactions.php" class="active">
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
            <h1>All Transactions</h1>
            <p>Welcome, <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst($role); ?>)</p>

            <div class="summary-cards">
                <div class="card">
                    <h3>Total Contributions</h3>
                    <p class="amount contributions">KES <?php echo number_format($total_contributions, 2); ?></p>
                </div>

                <div class="card">
                    <h3>Total Loan Payments</h3>
                    <p class="amount loans">KES <?php echo number_format($total_loan_payments, 2); ?></p>
                </div>

                <div class="card">
                    <h3>Total Fine Payments</h3>
                    <p class="amount fines">KES <?php echo number_format($total_fine_payments, 2); ?></p>
                </div>
            </div>

            <div class="filters">
                <h3>Filter Transactions</h3>
                <form method="GET">
                    <div class="filter-row">
                        <div>
                            <label for="start_date">Start Date:</label>
                            <input type="date" id="start_date" name="start_date">
                        </div>
                        <div>
                            <label for="end_date">End Date:</label>
                            <input type="date" id="end_date" name="end_date">
                        </div>
                        <div>
                            <label for="member">Member:</label>
                            <select id="member" name="member">
                                <option value="">All Members</option>
                                <?php
                                $user_obj = new User();
                                $members = $user_obj->getAllUsersByRole('member');
                                foreach ($members as $member):
                                ?>
                                <option value="<?php echo $member['id']; ?>">
                                    <?php echo htmlspecialchars($member['full_name'] . ' (' . ($member['member_number'] ?? 'N/A') . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="type">Transaction Type:</label>
                            <select id="type" name="type">
                                <option value="">All Types</option>
                                <option value="contribution">Contribution</option>
                                <option value="loan_payment">Loan Payment</option>
                                <option value="fine_payment">Fine Payment</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit">Apply Filters</button>
                </form>
            </div>

            <div class="transactions-table">
                <h2>Transaction History</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Member</th>
                            <th>Amount (KES)</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($all_transactions)): ?>
                            <?php foreach ($all_transactions as $t): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($t['transaction_date'] ?? $t['contribution_date']); ?></td>
                                <td><?php echo htmlspecialchars($t['member_name'] ?? $t['full_name']); ?></td>
                                <td>KES <?php echo number_format($t['amount'], 2); ?></td>
                                <td>
                                    <span class="transaction-type type-<?php echo $t['transaction_type']; ?>">
                                        <?php 
                                        if ($t['transaction_type'] === 'contribution') {
                                            echo 'Contribution';
                                        } elseif ($t['transaction_type'] === 'loan_payment') {
                                            echo 'Loan Payment';
                                        } elseif ($t['transaction_type'] === 'fine_payment') {
                                            echo 'Fine Payment';
                                        } else {
                                            echo ucfirst(str_replace('_', ' ', $t['transaction_type']));
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td class="status-<?php echo $t['status'] ?? 'paid'; ?>">
                                    <?php echo ucfirst($t['status'] ?? 'paid'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">No transactions found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>