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

        .close {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
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

        .sidebar a span {
            margin-right: 12px;
            font-size: 1.2rem;
        }

        .dashboard-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        h1 {
            color: #00A651;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        h2 {
            color: #00A651;
            margin: 25px 0 15px 0;
            font-size: 1.5rem;
            border-bottom: 2px solid #00A651;
            padding-bottom: 8px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .summary-card {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .summary-card h3 {
            margin-top: 0;
            color: #00A651;
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .summary-card h2 {
            margin-bottom: 0;
            font-size: 2rem;
            color: #2c3e50;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .data-table th, .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .data-table th {
            background: linear-gradient(135deg, #00A651, #008542);
            color: white;
            font-weight: 600;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
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

        .status-active {
            color: #28a745;
            font-weight: 500;
        }

        .status-inactive {
            color: #dc3545;
            font-weight: 500;
        }

        .profile-section, .settings-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-top: 30px;
        }

        .profile-info p {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .profile-info p:last-child {
            border-bottom: none;
        }

        .profile-info strong {
            display: inline-block;
            width: 150px;
            color: #00A651;
        }

        #changePasswordForm {
            max-width: 500px;
            margin-top: 20px;
        }

        #changePasswordForm div {
            margin-bottom: 20px;
        }

        #changePasswordForm label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        #changePasswordForm input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        #changePasswordForm button {
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

        #changePasswordForm button:hover {
            background: linear-gradient(135deg, #008542, #006431);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 166, 81, 0.3);
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

            .close {
                display: block;
            }

            .sidebar {
                display: none;
            }

            .sidebar.active {
                display: block;
            }

            .dashboard-content {
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
                <a href="Loans.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>My Loans</h3>
                </a>
                <a href="loan_repayment.php">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Loan Repayment</h3>
                </a>
                <a href="Members.php#my-fines">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>My Fines</h3>
                </a>
                <a href="#profile">
                    <span class="material-icons-sharp">grid_view</span>
                    <h3>Profile</h3>
                </a>
                <a href="#settings">
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

        <div id="profile" class="profile-section">
            <h2>My Profile</h2>
            <div class="profile-info">
                <h3>Personal Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone_number']); ?></p>
                <p><strong>ID Number:</strong> <?php echo htmlspecialchars($user['id_number']); ?></p>
                <p><strong>Member Number:</strong> <?php echo htmlspecialchars($member_result['id'] ? 'MEM' . str_pad($member_result['id'], 6, '0', STR_PAD_LEFT) : 'N/A'); ?></p>
                <p><strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($user['role'])); ?></p>
                <p><strong>Join Date:</strong> <?php echo htmlspecialchars($user['created_at']); ?></p>
            </div>
        </div>

        <div id="settings" class="settings-section">
            <h2>Account Settings</h2>
            <div class="settings-content">
                <h3>Change Password</h3>
                <form id="changePasswordForm" method="post">
                    <div>
                        <label for="current_password">Current Password:</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div>
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div>
                        <label for="confirm_new_password">Confirm New Password:</label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                    <button type="submit">Change Password</button>
                </form>

                <h3 style="margin-top: 30px;">Account Status</h3>
                <p><strong>Status:</strong> <span class="status-<?php echo $user['status']; ?>"><?php echo ucfirst(htmlspecialchars($user['status'])); ?></span></p>
            </div>
        </div>
    </main>

    <script>
        // Close button functionality
        document.getElementById('close-btn').addEventListener('click', function() {
            document.querySelector('aside').style.display = 'none';
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();

                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);

                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Handle password change form
        document.getElementById('changePasswordForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Password change functionality would be implemented here. For now, please contact the administrator to change your password.');
        });
    </script>
</body>
</html>