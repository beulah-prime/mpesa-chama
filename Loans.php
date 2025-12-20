<?php
/*
 * Loans Management Page for Chama Management System
 *
 * This page displays loan information based on user role.
 * Admins and treasurers see all loans, members see only their own loans.
 * Includes functionality for loan approval/rejection.
 *
 * Features:
 * - Role-based loan visibility
 * - Loan status tracking
 * - Summary statistics calculation
 * - Loan approval workflow
 * - Secure data access based on user roles
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

// Get loans based on user role - implement role-based access control
$loan = new Loan();
if ($role === 'admin' || $role === 'treasurer') {
    // Admins and treasurers can see all loans in the system
    $loans = $loan->getAllLoans();
} else {
    // Regular members can only see their own loans
    $db = new Database();
    $db->query('SELECT id FROM members WHERE user_id = :user_id');
    $db->bind(':user_id', $user['id']);
    $member_result = $db->single();

    if ($member_result) {
        // Get member ID and retrieve only their loans
        $member_id = $member_result['id'];
        $loans = $loan->getMemberLoans($member_id);
    } else {
        // Initialize empty array if member record not found
        $loans = [];
    }
}

// Calculate summary data for display in summary cards
$total_loan_amount = 0;   // Total of all loan amounts
$pending_loans = 0;       // Count of loans pending approval
$approved_loans = 0;      // Count of approved loans (includes disbursed)
$disbursed_loans = 0;     // Count of loans that have been disbursed

foreach ($loans as $l) {
    // Add to total loan amount
    $total_loan_amount += $l['loan_amount'];

    // Count loans based on status
    switch ($l['status']) {
        case 'pending':
            $pending_loans++;
            break;
        case 'approved':
        case 'disbursed':
            // Both approved and disbursed are considered "active" loans
            $approved_loans++;
            break;
    }

    if ($l['status'] === 'disbursed') {
        // Count only disbursed loans separately
        $disbursed_loans++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Loans Management - Chama Management System</title>
    <link rel="stylesheet" href="dashboard.css" />
  </head>
  <body>
    <aside class="sidebar">
      <h2 class="logo">Integrated</h2>

      <nav>
        <?php if ($role === 'admin'): ?>
          <a href="Admin.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'Admin.php') ? 'active' : ''; ?>">Dashboard</a>
          <a href="Admin.php">Manage Members</a>
        <?php elseif ($role === 'treasurer'): ?>
          <a href="Treasurer.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'Treasurer.php') ? 'active' : ''; ?>">Dashboard</a>
          <a href="Treasurer.php">Record Contributions</a>
        <?php else: ?>
          <a href="Members.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'Members.php') ? 'active' : ''; ?>">Dashboard</a>
          <a href="Members.php#my-contributions">My Contributions</a>
        <?php endif; ?>
        <a href="Loans.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'Loans.php') ? 'active' : ''; ?>">Loans</a>
        <a href="Contributions.php">Contributions</a>
        <a href="Fines.php">Fines</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1>Loans Dashboard</h1>

        <div class="top-actions">
          <button id="themeToggle" onclick="toggleTheme()">☀️</button>
          <span class="user">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
      </header>

      <!-- cards -->
      <section class="cards">
        <div class="card">
          <h3>Total Loan Amount</h3>
          <p class="amount success">KES <?php echo number_format($total_loan_amount, 2); ?></p>
        </div>

        <div class="card">
          <h3>Active Loans</h3>
          <p class="amount warning"><?php echo $approved_loans; ?></p>
        </div>

        <div class="card">
          <h3>Pending Applications</h3>
          <p class="amount <?php echo $pending_loans > 0 ? 'warning' : 'success'; ?>"><?php echo $pending_loans; ?></p>
        </div>
      </section>

      <!-- tables -->
      <section class="content">
        <h2>Loan Applications</h2>

        <table>
          <thead>
            <tr>
              <th>Date Applied</th>
              <th>Member</th>
              <th>Amount (KES)</th>
              <th>Status</th>
              <?php if ($role === 'admin' || $role === 'treasurer'): ?>
              <th>Actions</th>
              <?php endif; ?>
            </tr>
          </thead>

          <tbody>
            <?php if (!empty($loans)): ?>
              <?php foreach ($loans as $l): ?>
              <tr>
                <td><?php echo htmlspecialchars($l['date_applied']); ?></td>
                <td><?php echo htmlspecialchars($l['full_name'] ?? $user['full_name']); ?></td>
                <td>KES <?php echo number_format($l['loan_amount'], 2); ?></td>
                <td class="status-<?php echo $l['status']; ?>"><?php echo ucfirst($l['status']); ?></td>
                <?php if ($role === 'admin' || $role === 'treasurer'): ?>
                <td>
                  <?php if ($l['status'] === 'pending'): ?>
                    <button onclick="updateLoan(<?php echo $l['id']; ?>, 'approved')" class="btn-approve">Approve</button>
                    <button onclick="updateLoan(<?php echo $l['id']; ?>, 'rejected')" class="btn-reject">Reject</button>
                  <?php endif; ?>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="<?php echo ($role === 'admin' || $role === 'treasurer') ? 5 : 4; ?>">No loan applications found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>
    </main>

    <script>
      // Theme toggle functionality
      function toggleTheme() {
        const html = document.documentElement;
        const button = document.getElementById('themeToggle');

        if (html.getAttribute('data-theme') === 'dark') {
          html.removeAttribute('data-theme');
          button.innerHTML = '☀️';
        } else {
          html.setAttribute('data-theme', 'dark');
          button.innerHTML = '🌙';
        }
      }

      // Loan update functionality
      function updateLoan(loanId, status) {
        if (confirm('Are you sure you want to ' + status + ' this loan?')) {
          // This would typically make an AJAX request to update the loan
          // For now, we'll just reload the page
          window.location.href = 'update_loan.php?id=' + loanId + '&status=' + status;
        }
      }
    </script>

    <style>
      .btn-approve {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 3px;
        cursor: pointer;
      }

      .btn-reject {
        background-color: #dc3545;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 3px;
        cursor: pointer;
      }

      .btn-approve:hover {
        background-color: #218838;
      }

      .btn-reject:hover {
        background-color: #c82333;
      }
    </style>
  </body>
</html>

<?php
// Create update_loan.php file for handling loan status updates
if (!file_exists('update_loan.php')) {
    $update_loan_content = '<?php
session_start();
require_once \'DatabaseClass.php\';

if (!isset($_SESSION['user'])) {
    header(\'Location: Login.php\');
    exit();
}

$user = $_SESSION[\'user\'];
$role = $user[\'role\'];

// Only admins and treasurers can update loan status
if ($role !== \'admin\' && $role !== \'treasurer\') {
    header(\'Location: Login.php\');
    exit();
}

if (isset($_GET[\'id\']) && isset($_GET[\'status\'])) {
    $loan_id = (int)$_GET[\'id\'];
    $status = $_GET[\'status\'];

    // Validate status
    $valid_statuses = [\'approved\', \'rejected\', \'disbursed\', \'paid\'];
    if (!in_array($status, $valid_statuses)) {
        die(\'Invalid status\');
    }

    $loan = new Loan();

    // If approving a loan, set the approver
    $approver_id = ($status === \'approved\') ? $user[\'id\'] : null;

    if ($loan->updateLoanStatus($loan_id, $status, $approver_id)) {
        header(\'Location: Loans.php?message=Loan status updated successfully\');
    } else {
        header(\'Location: Loans.php?error=Failed to update loan status\');
    }
} else {
    header(\'Location: Loans.php\');
}
?>';
    file_put_contents('update_loan.php', $update_loan_content);
}
?>
