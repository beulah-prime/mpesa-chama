<?php
/*
 * Fines Management Page for Chama Management System
 * 
 * This page displays fine information based on user role.
 * Admins and treasurers see all fines, members see only their own fines.
 * 
 * Features:
 * - Role-based fine visibility
 * - Fine status tracking (pending/paid/waived)
 * - Fine details and payment processing
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

// Get fines based on user role - implement role-based access control
$fine = new Fine();
if ($role === 'admin' || $role === 'treasurer') {
    // Admins and treasurers can see all fines in the system
    $fines = $fine->getAllFines();
} else {
    // Regular members can only see their own fines
    $db = new Database();
    $db->query('SELECT id FROM members WHERE user_id = :user_id');
    $db->bind(':user_id', $user['id']);
    $member_result = $db->single();
    
    if ($member_result) {
        // Get member ID and retrieve only their fines
        $member_id = $member_result['id'];
        $fines = $fine->getMemberFines($member_id);
    } else {
        // Initialize empty array if member record not found
        $fines = [];
    }
}

// Calculate summary data for display
$total_fines = 0;           // Total of all fine amounts
$pending_fines = 0;         // Count of pending fines
$paid_fines = 0;            // Count of paid fines

foreach ($fines as $f) {
    $total_fines += $f['amount'];
    
    // Count fines based on status
    switch ($f['status']) {
        case 'pending':
            $pending_fines++;
            break;
        case 'paid':
            $paid_fines++;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fines Management - Chama Management System</title>
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
          <a href="Members.php">My Contributions</a>
        <?php endif; ?>
        <a href="Loans.php">Loans</a>
        <a href="Contributions.php">Contributions</a>
        <a href="Fines.php" class="<?php echo (basename($_SERVER['PHP_SELF']) === 'Fines.php') ? 'active' : ''; ?>">Fines</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <header class="topbar">
        <h1>Fines Dashboard</h1>

        <div class="top-actions">
          <button id="themeToggle" onclick="toggleTheme()">☀️</button>
          <span class="user">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
      </header>

      <!-- summary cards -->
      <section class="cards">
        <div class="card">
          <h3>Total Fines</h3>
          <p class="amount">KES <?php echo number_format($total_fines, 2); ?></p>
        </div>

        <div class="card">
          <h3>Pending Fines</h3>
          <p class="amount warning"><?php echo $pending_fines; ?></p>
        </div>

        <div class="card">
          <h3>Resolved Fines</h3>
          <p class="amount success"><?php echo $paid_fines; ?></p>
        </div>
      </section>

      <!-- fines table -->
      <section class="content">
        <h2>My Fines</h2>

        <table>
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
            <?php if (!empty($fines)): ?>
              <?php foreach ($fines as $f): ?>
              <tr>
                <td><?php echo htmlspecialchars($f['date_imposed']); ?></td>
                <td><?php echo htmlspecialchars($f['reason']); ?></td>
                <td>KES <?php echo number_format($f['amount'], 2); ?></td>
                <td class="status-<?php echo $f['status']; ?>"><?php echo ucfirst($f['status']); ?></td>
                <td><?php echo htmlspecialchars($f['due_date']); ?></td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5">No fines found</td>
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