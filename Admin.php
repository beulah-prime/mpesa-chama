<?php
/*
 * Admin Dashboard for Chama Management System
 *
 * This page serves as the main dashboard for administrators.
 * It provides an overview of all users in the system and management capabilities.
 *
 * Features:
 * - Authentication and role-based access control
 * - User management interface
 * - Display of all registered users
 * - Role-based redirection for unauthorized access
 * - Secure session handling
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

// Create User object to access user-related database methods
$admin = new User();

// Retrieve all users in the system regardless of role
$all_users = $admin->getAllUsersByRole();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Chama Management System</title>
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

        .users-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .users-table th, .users-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .users-table th {
            background-color: #00A651;
            color: white;
        }

        .status-active {
            color: green;
        }

        .status-inactive {
            color: red;
        }

        .btn {
            padding: 5px 10px;
            text-decoration: none;
            border-radius: 3px;
            margin: 0 2px;
        }

        .btn-edit {
            background-color: #007BFF;
            color: white;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <span class="logo">Integrated</span>
            <ul>
                <li><a href="Admin.php">Dashboard</a></li>
                <li><a href="Members.php">Manage Members</a></li>
                <li><a href="Contributions.php">View Contributions</a></li>
                <li><a href="Loans.php">View Loans</a></li>
                <li><a href="loan_repayment.php">Loan Repayments</a></li>
                <li><a href="Fines.php">Manage Fines</a></li>
                <li><a href="settings.php">System Settings</a></li>
                <li><a href="Admin.php#pending-approvals">Pending Approvals</a></li>
                <li><a href="Admin.php#reports">Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="dashboard-header">
                <h1 class="welcome-message">Welcome, <?php echo htmlspecialchars($user['full_name']); ?> (Admin)</h1>
                <p>Manage your Chama system efficiently</p>
            </div>

            <h2>Manage Members</h2>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Member Number</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_users as $usr): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($usr['member_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($usr['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($usr['email']); ?></td>
                        <td><?php echo htmlspecialchars($usr['role']); ?></td>
                        <td class="status-<?php echo $usr['status']; ?>"><?php echo ucfirst($usr['status']); ?></td>
                        <td>
                            <a href="#" class="btn btn-edit">Edit</a>
                            <a href="#" class="btn btn-delete">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

<?php
// Create logout.php file for handling logout
if (!file_exists('logout.php')) {
    $logout_content = '<?php
session_start();
// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("location: Login.php");
exit;
?>';
    file_put_contents('logout.php', $logout_content);
}
?>