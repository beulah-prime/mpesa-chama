<?php
/*
 * Contributions Management Page for Chama Management System
 *
 * This page displays contribution information based on user role.
 * Admins and treasurers see all contributions, members see only their own.
 * Includes summary statistics for financial tracking.
 *
 * Features:
 * - Role-based contribution visibility
 * - Contribution status tracking (pending/confirmed)
 * - Summary statistics calculation
 * - Payment method categorization
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

// Get contributions based on user role - implement role-based access control
$contribution = new Contribution();
if ($role === 'admin' || $role === 'treasurer') {
    // Admins and treasurers can see all contributions in the system
    $contributions = $contribution->getAllContributions();
} else {
    // Regular members can only see their own contributions
    $db = new Database();
    $db->query('SELECT id FROM members WHERE user_id = :user_id');
    $db->bind(':user_id', $user['id']);
    $member_result = $db->single();

    if ($member_result) {
        // Get member ID and retrieve only their contributions
        $member_id = $member_result['id'];
        $contributions = $contribution->getMemberContributions($member_id);
    } else {
        // Initialize empty array if member record not found
        $contributions = [];
    }
}

// Calculate summary data for display in summary cards
$total_contributions = 0;           // Total of all contributions (pending + confirmed)
$confirmed_contributions = 0;       // Total of confirmed contributions only
$pending_contributions = 0;         // Total of pending contributions only

foreach ($contributions as $c) {
    // Add to total contribution amount
    $total_contributions += $c['amount'];

    // Categorize contributions by status for summary statistics
    if ($c['status'] === 'confirmed') {
        // Add to confirmed contributions total
        $confirmed_contributions += $c['amount'];
    } elseif ($c['status'] === 'pending') {
        // Add to pending contributions total
        $pending_contributions += $c['amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Contributions Management - Chama Management System</title>
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
          <a href="payment.php">Make Payment</a>
          <a href="loan_repayment.php">Loan Repayment</a>
        <?php endif; ?>
        <a href="Loans.php">Loans</a>
        <a href="Contributions.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'Contributions.php') ? 'active' : ''; ?>">Contributions</a>
        <a href="Fines.php">Fines</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1>Contributions Dashboard</h1>

        <div class="top-actions">
          <button id="themeToggle" onclick="toggleTheme()">☀️</button>
          <span class="user">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
      </header>

      <!-- cards -->
      <section class="cards">
        <div class="card">
          <h3>Total Contributions</h3>
          <p class="amount success">KES <?php echo number_format($total_contributions, 2); ?></p>
        </div>

        <div class="card">
          <h3>Confirmed Contributions</h3>
          <p class="amount success">KES <?php echo number_format($confirmed_contributions, 2); ?></p>
        </div>

        <div class="card">
          <h3>Pending Contributions</h3>
          <p class="amount warning">KES <?php echo number_format($pending_contributions, 2); ?></p>
        </div>
      </section>

      <!-- tables -->
      <section class="content">
        <h2>Recent Transactions</h2>

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
            <?php if (!empty($contributions)): ?>
              <?php foreach ($contributions as $c): ?>
              <tr>
                <td><?php echo htmlspecialchars($c['contribution_date']); ?></td>
                <td><?php echo htmlspecialchars($c['full_name'] ?? $user['full_name']); ?></td>
                <td>KES <?php echo number_format($c['amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($c['payment_method']); ?></td>
                <td class="status-<?php echo $c['status']; ?>"><?php echo ucfirst($c['status']); ?></td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5">No contributions found</td>
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
    </script>
  </body>
</html>
