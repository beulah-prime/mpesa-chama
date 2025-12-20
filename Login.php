<?php
/*
 * Login Page for Chama Management System
 *
 * This page handles user authentication for the Chama system.
 * It implements secure login functionality using the DatabaseClass OOP approach.
 *
 * Features:
 * - Session-based authentication
 * - Role-based redirection (admin, treasurer, member)
 * - Input validation
 * - Account status checking
 * - SQL injection prevention via DatabaseClass
 *
 * @author ChamaSys Development Team
 * @version 1.0
 * @since 2025
 */

// Start session to maintain user state across pages
session_start();

// Include the DatabaseClass which provides OOP database functionality
require_once 'DatabaseClass.php';

// Check if user is already logged in - prevent multiple logins
if (isset($_SESSION['user'])) {
    // Get user role from session and redirect to appropriate dashboard
    $role = $_SESSION['user']['role'];
    switch($role) {
        case 'admin':
            // Redirect admin users to admin dashboard
            header('Location: Admin.php');
            break;
        case 'treasurer':
            // Redirect treasurer users to treasurer dashboard
            header('Location: Treasurer.php');
            break;
        case 'member':
            // Redirect regular members to member dashboard
            header('Location: Members.php');
            break;
        default:
            // Default redirect for any other role
            header('Location: index.php');
    }
    // Exit script to prevent further execution after redirect
    exit();
}

// Initialize error and success message variables
$error = '';
$success = '';

// Process form submission when POST data is received
if ($_POST) {
    // Sanitize and validate user inputs
    $email = trim($_POST['email']);      // Remove whitespace from email
    $password = $_POST['password'];      // Password does not need trimming as it's validated differently

    // Perform basic validation to ensure required fields are filled
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Create User object using the DatabaseClass OOP approach
        $user = new User();

        // Attempt to authenticate user credentials using the login method
        $logged_user = $user->login($email, $password);

        if ($logged_user) {
            // Check if the user account is active before allowing access
            if ($logged_user['status'] === 'inactive') {
                // Show deactivation message for inactive accounts
                $error = 'Your account has been deactivated. Please contact the administrator.';
            } else {
                // Store user information in session for future requests
                $_SESSION['user'] = $logged_user;           // Full user object
                $_SESSION['user_id'] = $logged_user['id'];  // User ID for quick reference
                $_SESSION['role'] = $logged_user['role'];   // User role for access control

                // Redirect user to appropriate dashboard based on their role
                switch($logged_user['role']) {
                    case 'admin':
                        header('Location: Admin.php');
                        break;
                    case 'treasurer':
                        header('Location: Treasurer.php');
                        break;
                    case 'member':
                        header('Location: Members.php');
                        break;
                    default:
                        header('Location: index.php');
                }
                // Exit immediately after redirect to prevent further processing
                exit();
            }
        } else {
            // Show error if login credentials are invalid
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - Chama Management System</title>
    <link rel="stylesheet" href="login-styles.css" />
  </head>
  <body>
    <form action="" method="post" class="input">
      <h1>Welcome Back</h1>
      <p>Sign in to your Chama account</p>

      <?php if (!empty($error)): ?>
        <div style="color: red; margin: 10px 0; text-align: center;"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div style="color: green; margin: 10px 0; text-align: center;"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>

      <label for="email" class="input-type">Email or Phone Number</label>
      <input
        type="email"
        id="email"
        name="email"
        placeholder="Enter your email or phone number"
        required
        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
      />
      <br />

      <label for="password" class="input-type">Password</label>
      <input
        type="password"
        id="password"
        name="password"
        placeholder="Enter your password"
        required
      /><br />

      <button type="submit" class="mybutton">Sign In</button><br />
      <hr />

      <footer>
        <p>
          Don't have an account?
          <a href="Register.php">Create Account</a>
        </p>
        <br />

        <p>
          <small>
            Secure Chama Management System <br />
            Powered by M-Pesa Integration
          </small>
        </p>
      </footer>
    </form>
  </body>
</html>
