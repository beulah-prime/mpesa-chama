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
                <li><a href="manage_loans.php">Manage Loans</a></li>
                <li><a href="fines.php">Manage Fines</a></li>
                <li><a href="transactions.php">Transactions</a></li>
                <li><a href="reports.php">Reports</a></li>
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
                    <tr data-user-id="<?php echo $usr['id']; ?>">
                        <td><?php echo htmlspecialchars($usr['member_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($usr['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($usr['email']); ?></td>
                        <td><?php echo htmlspecialchars($usr['role']); ?></td>
                        <td class="status-<?php echo $usr['status']; ?>"><?php echo ucfirst($usr['status']); ?></td>
                        <td>
                            <a href="#" class="btn btn-edit" onclick="editUser(<?php echo $usr['id']; ?>)">Edit</a>
                            <a href="#" class="btn btn-delete" onclick="deleteUser(<?php echo $usr['id']; ?>, '<?php echo addslashes(htmlspecialchars($usr['full_name'])); ?>')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pending Approvals Section -->
            <div id="pending-approvals" style="margin-top: 40px;">
                <h2>Pending Approvals</h2>

                <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                    <!-- Pending Loans Card -->
                    <div style="flex: 1; background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007BFF;">
                        <h3>Pending Loan Applications</h3>
                        <?php
                        // Count pending loans
                        $loan = new Loan();
                        $all_loans = $loan->getAllLoans();
                        $pending_loans = 0;
                        foreach ($all_loans as $l) {
                            if ($l['status'] === 'pending') {
                                $pending_loans++;
                            }
                        }
                        ?>
                        <p style="font-size: 24px; font-weight: bold; color: #007BFF;"><?php echo $pending_loans; ?></p>
                        <a href="Loans.php" style="color: #007BFF; text-decoration: none;">View Applications &rarr;</a>
                    </div>

                    <!-- Pending Contributions Card -->
                    <div style="flex: 1; background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;">
                        <h3>Pending Contributions</h3>
                        <?php
                        // Get all pending contributions with member details
                        $db = new Database();
                        $db->query('SELECT c.*, m.member_number, u.full_name FROM contributions c JOIN members m ON c.member_id = m.id JOIN users u ON m.user_id = u.id WHERE c.status = "pending" ORDER BY c.contribution_date DESC');
                        $all_contributions = $db->resultSet();
                        $pending_contributions = count($all_contributions);
                        ?>
                        <p style="font-size: 24px; font-weight: bold; color: #28a745;"><?php echo $pending_contributions; ?></p>
                        <a href="Contributions.php" style="color: #28a745; text-decoration: none;">View Contributions &rarr;</a>
                    </div>

                    <!-- Pending Fines Card -->
                    <div style="flex: 1; background-color: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                        <h3>Pending Fines</h3>
                        <?php
                        // Get all pending fines with member details
                        $db = new Database();
                        $db->query('SELECT f.*, m.member_number, u.full_name FROM fines f JOIN members m ON f.member_id = m.id JOIN users u ON m.user_id = u.id WHERE f.status = "pending" ORDER BY f.date_imposed DESC');
                        $all_fines = $db->resultSet();
                        $pending_fines = count($all_fines);
                        ?>
                        <p style="font-size: 24px; font-weight: bold; color: #ffc107;"><?php echo $pending_fines; ?></p>
                        <a href="Fines.php" style="color: #ffc107; text-decoration: none;">View Fines &rarr;</a>
                    </div>
                </div>

                <!-- Detailed Pending Loans Table -->
                <?php if ($pending_loans > 0): ?>
                <h3>Pending Loan Applications</h3>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Member Number</th>
                            <th>Loan Amount</th>
                            <th>Date Applied</th>
                            <th>Duration</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_loans as $l): ?>
                            <?php if ($l['status'] === 'pending'): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($l['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($l['member_number']); ?></td>
                                <td>KES <?php echo number_format($l['loan_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($l['date_applied']); ?></td>
                                <td><?php echo $l['duration_months']; ?> months</td>
                                <td>
                                    <button onclick="updateApproval('loan', <?php echo $l['id']; ?>, 'approve')" class="btn btn-edit">Approve</button>
                                    <button onclick="updateApproval('loan', <?php echo $l['id']; ?>, 'reject')" class="btn btn-delete">Reject</button>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Detailed Pending Contributions Table -->
                <?php if ($pending_contributions > 0): ?>
                <h3 style="margin-top: 30px;">Pending Contributions</h3>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Member Number</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Payment Method</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_contributions as $c): ?>
                            <?php if ($c['status'] === 'pending'): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($c['member_number']); ?></td>
                                <td>KES <?php echo number_format($c['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($c['contribution_date']); ?></td>
                                <td><?php echo htmlspecialchars($c['payment_method']); ?></td>
                                <td>
                                    <button onclick="updateApproval('contribution', <?php echo $c['id']; ?>, 'confirm')" class="btn btn-edit">Confirm</button>
                                    <button onclick="updateApproval('contribution', <?php echo $c['id']; ?>, 'cancel')" class="btn btn-delete">Cancel</button>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Detailed Pending Fines Table -->
                <?php if ($pending_fines > 0): ?>
                <h3 style="margin-top: 30px;">Pending Fines</h3>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Member Number</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Date Imposed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_fines as $f): ?>
                            <?php if ($f['status'] === 'pending'): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($f['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($f['member_number']); ?></td>
                                <td>KES <?php echo number_format($f['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($f['reason']); ?></td>
                                <td><?php echo htmlspecialchars($f['date_imposed']); ?></td>
                                <td>
                                    <button onclick="updateApproval('fine', <?php echo $f['id']; ?>, 'pay')" class="btn btn-edit">Mark Paid</button>
                                    <button onclick="updateApproval('fine', <?php echo $f['id']; ?>, 'waive')" class="btn btn-delete">Waive</button>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Edit User Modal -->
            <div id="editUserModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
                <div class="modal-content" style="background-color: #fefefe; margin: 2% auto; padding: 0; border: 1px solid #888; width: 90%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); max-height: 90vh; display: flex; flex-direction: column;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee;">
                        <h2 style="margin: 0; color: #333;">Edit User</h2>
                        <span onclick="closeModal()" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 1;">&times;</span>
                    </div>
                    <div style="overflow-y: auto; max-height: 60vh; padding: 20px; flex: 1;">
                        <form id="editUserForm" method="POST">
                            <input type="hidden" id="edit_user_id" name="user_id">

                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="edit_full_name" style="display: block; margin-bottom: 5px; font-weight: bold;">Full Name:</label>
                                <input type="text" id="edit_full_name" name="full_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="edit_email" style="display: block; margin-bottom: 5px; font-weight: bold;">Email:</label>
                                <input type="email" id="edit_email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="edit_phone_number" style="display: block; margin-bottom: 5px; font-weight: bold;">Phone Number:</label>
                                <input type="text" id="edit_phone_number" name="phone_number" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="edit_id_number" style="display: block; margin-bottom: 5px; font-weight: bold;">ID Number:</label>
                                <input type="text" id="edit_id_number" name="id_number" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                            </div>

                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="edit_role" style="display: block; margin-bottom: 5px; font-weight: bold;">Role:</label>
                                <select id="edit_role" name="role" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                                    <option value="admin">Admin</option>
                                    <option value="treasurer">Treasurer</option>
                                    <option value="member">Member</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="edit_status" style="display: block; margin-bottom: 5px; font-weight: bold;">Status:</label>
                                <select id="edit_status" name="status" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div style="padding: 15px 20px; border-top: 1px solid #eee; text-align: right; background-color: #f9f9f9;">
                        <button type="submit" form="editUserForm" class="btn btn-edit" style="background-color: #007BFF; color: white; padding: 12px 25px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">Update User</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to edit a user
        function editUser(userId) {
            // Show the modal
            document.getElementById('editUserModal').style.display = 'block';

            // Fetch user data
            fetch('user_management.php?action=get&user_id=' + userId, {
                method: 'GET'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const userData = data.data;
                    document.getElementById('edit_user_id').value = userData.id;
                    document.getElementById('edit_full_name').value = userData.full_name;
                    document.getElementById('edit_email').value = userData.email;
                    document.getElementById('edit_phone_number').value = userData.phone_number;
                    document.getElementById('edit_id_number').value = userData.id_number;
                    document.getElementById('edit_role').value = userData.role;
                    document.getElementById('edit_status').value = userData.status;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while fetching user data.');
            });
        }

        // Function to delete a user
        function deleteUser(userId, userName) {
            if (confirm('Are you sure you want to delete user "' + userName + '"? This action cannot be undone.')) {
                fetch('user_management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete&user_id=' + userId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        // Reload the page to update the table
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the user.');
                });
            }
        }

        // Function to close the modal
        function closeModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        // Handle form submission for editing user
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Get form data as an object
            const formData = new FormData(this);
            const params = new URLSearchParams();
            params.append('action', 'edit');

            // Append all form fields to the URLSearchParams
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }

            console.log('Sending form data:', params.toString());

            fetch('user_management.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('Network response was not ok. Status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Server response:', data);
                if (data.success) {
                    alert(data.message);
                    closeModal();
                    // Reload the page to update the table
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the user: ' + error.message);
            });
        });

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('editUserModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Function to update approval status
        function updateApproval(type, id, action) {
            const actionText = {
                'approve': 'approve',
                'reject': 'reject',
                'confirm': 'confirm',
                'cancel': 'cancel',
                'pay': 'mark as paid',
                'waive': 'waive'
            }[action] || action;

            if (confirm(`Are you sure you want to ${actionText} this ${type}?`)) {
                fetch('update_approval.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `type=${type}&action=${action}&id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        // Reload the page to update the tables
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the approval status.');
                });
            }
        }
    </script>
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