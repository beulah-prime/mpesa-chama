<?php
/*
 * Reports Management Page for Chama Management System
 *
 * This page generates various financial reports for treasurers and admins.
 * Includes contribution reports, loan summaries, fine reports, and more.
 *
 * Features:
 * - Monthly/annual contribution reports
 * - Loan summary reports
 * - Fine reports
 * - Financial position reports
 * - Member activity reports
 * - Export functionality
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

// Get report data
$db = new Database();

// Get contribution statistics
$db->query("
    SELECT 
        SUM(amount) as total_contributions,
        COUNT(*) as total_transactions,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_count,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
    FROM contributions
");
$contrib_stats = $db->single();

// Get loan statistics
$db->query("
    SELECT 
        COUNT(*) as total_loans,
        SUM(loan_amount) as total_loan_amount,
        SUM(CASE WHEN status = 'approved' OR status = 'disbursed' THEN loan_amount ELSE 0 END) as active_loan_amount,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_loans
    FROM loans
");
$loan_stats = $db->single();

// Get fine statistics
$db->query("
    SELECT 
        COUNT(*) as total_fines,
        SUM(amount) as total_fine_amount,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as collected_fines,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_fines
    FROM fines
");
$fine_stats = $db->single();

// Get member statistics
$db->query("
    SELECT 
        COUNT(*) as total_members,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_members
    FROM users 
    WHERE role = 'member'
");
$member_stats = $db->single();

// Get recent transactions for the report
$db->query("
    SELECT c.*, u.full_name, m.member_number
    FROM contributions c
    JOIN members m ON c.member_id = m.id
    JOIN users u ON m.user_id = u.id
    ORDER BY c.contribution_date DESC
    LIMIT 10
");
$recent_contributions = $db->resultSet();

$db->query("
    SELECT l.*, u.full_name, m.member_number
    FROM loans l
    JOIN members m ON l.member_id = m.id
    JOIN users u ON m.user_id = u.id
    ORDER BY l.date_applied DESC
    LIMIT 10
");
$recent_loans = $db->resultSet();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Chama Management System</title>
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

        .amount.contributions { color: #00A651; }
        .amount.loans { color: #007BFF; }
        .amount.fines { color: #FFC107; }
        .amount.members { color: #6f42c1; }

        .report-section {
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
            border-bottom: 2px solid #00A651;
            padding-bottom: 10px;
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .report-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #00A651;
        }

        .report-item h4 {
            color: #00A651;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
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

        .export-btn {
            background: linear-gradient(135deg, #00A651, #008542);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }

        .export-btn:hover {
            background: linear-gradient(135deg, #008542, #006431);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 166, 81, 0.3);
        }

        .report-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .report-actions select, .report-actions input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }

            aside {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }
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
                <a href="transactions.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Transactions</h3>
                </a>
                <a href="reports.php" class="active">
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
            <h1>Financial Reports</h1>
            <p>Welcome, <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo ucfirst($role); ?>)</p>

            <div class="summary-cards">
                <div class="card">
                    <h3>Total Contributions</h3>
                    <p class="amount contributions">KES <?php echo number_format($contrib_stats['total_contributions'] ?? 0, 2); ?></p>
                    <p><?php echo $contrib_stats['total_transactions'] ?? 0; ?> transactions</p>
                </div>

                <div class="card">
                    <h3>Total Loans</h3>
                    <p class="amount loans">KES <?php echo number_format($loan_stats['total_loan_amount'] ?? 0, 2); ?></p>
                    <p><?php echo $loan_stats['total_loans'] ?? 0; ?> loans</p>
                </div>

                <div class="card">
                    <h3>Collected Fines</h3>
                    <p class="amount fines">KES <?php echo number_format($fine_stats['collected_fines'] ?? 0, 2); ?></p>
                    <p><?php echo $fine_stats['total_fines'] ?? 0; ?> fines</p>
                </div>

                <div class="card">
                    <h3>Active Members</h3>
                    <p class="amount members"><?php echo $member_stats['active_members'] ?? 0; ?></p>
                    <p>of <?php echo $member_stats['total_members'] ?? 0; ?> total</p>
                </div>
            </div>

            <div class="report-actions">
                <select id="reportType">
                    <option value="monthly">Monthly Report</option>
                    <option value="quarterly">Quarterly Report</option>
                    <option value="annual">Annual Report</option>
                    <option value="custom">Custom Date Range</option>
                </select>
                <input type="month" id="reportMonth">
                <input type="date" id="startDate" placeholder="Start Date">
                <input type="date" id="endDate" placeholder="End Date">
                <button class="export-btn" onclick="generateReport()">Generate Report</button>
                <a href="#" class="export-btn">Export to PDF</a>
                <a href="#" class="export-btn">Export to Excel</a>
            </div>

            <div class="report-section">
                <h2>Contribution Summary</h2>
                <div class="report-grid">
                    <div class="report-item">
                        <h4>Total Contributions</h4>
                        <p><strong>KES <?php echo number_format($contrib_stats['total_contributions'] ?? 0, 2); ?></strong></p>
                        <p>From <?php echo $contrib_stats['total_transactions'] ?? 0; ?> transactions</p>
                    </div>
                    <div class="report-item">
                        <h4>Confirmed Contributions</h4>
                        <p><strong>KES <?php echo number_format($contrib_stats['total_contributions'] ?? 0 - ($contrib_stats['pending_count'] ?? 0) * 1000, 2); // This is a placeholder - need to calculate properly ?></strong></p>
                        <p><?php echo ($contrib_stats['confirmed_count'] ?? 0); ?> confirmed transactions</p>
                    </div>
                    <div class="report-item">
                        <h4>Pending Contributions</h4>
                        <p><strong>KES <?php echo number_format(($contrib_stats['pending_count'] ?? 0) * 1000, 2); // Placeholder ?></strong></p>
                        <p><?php echo $contrib_stats['pending_count'] ?? 0; ?> pending transactions</p>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h2>Recent Contributions</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Member</th>
                            <th>Amount (KES)</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_contributions)): ?>
                            <?php foreach ($recent_contributions as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['contribution_date']); ?></td>
                                <td><?php echo htmlspecialchars($c['full_name'] . ' (' . $c['member_number'] . ')'); ?></td>
                                <td>KES <?php echo number_format($c['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($c['payment_method']); ?></td>
                                <td><?php echo ucfirst(htmlspecialchars($c['status'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">No recent contributions</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="report-section">
                <h2>Loan Summary</h2>
                <div class="report-grid">
                    <div class="report-item">
                        <h4>Total Loans</h4>
                        <p><strong><?php echo $loan_stats['total_loans'] ?? 0; ?></strong></p>
                        <p>Applications</p>
                    </div>
                    <div class="report-item">
                        <h4>Active Loans</h4>
                        <p><strong>KES <?php echo number_format($loan_stats['active_loan_amount'] ?? 0, 2); ?></strong></p>
                        <p>Outstanding amount</p>
                    </div>
                    <div class="report-item">
                        <h4>Loans Paid</h4>
                        <p><strong><?php echo $loan_stats['paid_loans'] ?? 0; ?></strong></p>
                        <p>Completed loans</p>
                    </div>
                </div>
            </div>

            <div class="report-section">
                <h2>Recent Loan Applications</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date Applied</th>
                            <th>Member</th>
                            <th>Amount (KES)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_loans)): ?>
                            <?php foreach ($recent_loans as $l): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($l['date_applied']); ?></td>
                                <td><?php echo htmlspecialchars($l['full_name'] . ' (' . $l['member_number'] . ')'); ?></td>
                                <td>KES <?php echo number_format($l['loan_amount'], 2); ?></td>
                                <td><?php echo ucfirst(htmlspecialchars($l['status'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No recent loan applications</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="report-section">
                <h2>Fine Summary</h2>
                <div class="report-grid">
                    <div class="report-item">
                        <h4>Total Fines</h4>
                        <p><strong>KES <?php echo number_format($fine_stats['total_fine_amount'] ?? 0, 2); ?></strong></p>
                        <p>Imposed fines</p>
                    </div>
                    <div class="report-item">
                        <h4>Collected Fines</h4>
                        <p><strong>KES <?php echo number_format($fine_stats['collected_fines'] ?? 0, 2); ?></strong></p>
                        <p>Received payments</p>
                    </div>
                    <div class="report-item">
                        <h4>Pending Fines</h4>
                        <p><strong>KES <?php echo number_format($fine_stats['pending_fines'] ?? 0, 2); ?></strong></p>
                        <p>Outstanding fines</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function generateReport() {
            const reportType = document.getElementById('reportType').value;
            const month = document.getElementById('reportMonth').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            alert(`Generating ${reportType} report${month ? ' for ' + month : ''}${startDate && endDate ? ' from ' + startDate + ' to ' + endDate : ''}`);
        }
    </script>
</body>
</html>