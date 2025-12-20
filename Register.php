
<?php
/*
 * Registration Page for Chama Management System
 *
 * This page handles user registration for the Chama system.
 * It implements secure registration functionality with comprehensive validation.
 *
 * Features:
 * - User input validation
 * - Unique email checking
 * - Password confirmation
 * - Terms and conditions agreement
 * - Role selection during registration
 * - SQL injection prevention via DatabaseClass
 *
 * @author ChamaSys Development Team
 * @version 1.0
 * @since 2025
 */

// Include the DatabaseClass which provides OOP database functionality
require_once 'DatabaseClass.php';

// Initialize error and success message variables to empty strings
$error = '';
$success = '';

// Process form submission when POST data is received
if ($_POST) {
    // Sanitize and extract user input data from the form
    $full_name = trim($_POST['name']);                 // Remove whitespace from full name
    $email = trim($_POST['email']);                    // Remove whitespace from email
    $phone_number = trim($_POST['number']);            // Remove whitespace from phone number
    $id_number = trim($_POST['id']);                   // Remove whitespace from ID number
    $role = $_POST['register'];                        // User's selected role (admin, treasurer, member)
    $password = $_POST['password'];                    // Password (will be hashed)
    $confirm_password = $_POST['confirm_password'];    // Password confirmation
    $terms_agreed = isset($_POST['terms']) ? $_POST['terms'] : '';  // Terms agreement (1 if checked)

    // Initialize array to collect validation errors
    $errors = [];

    // Validate each input field individually

    if (empty($full_name)) {
        // Check if full name is provided
        $errors[] = 'Full name is required.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Validate email format using PHP's built-in email validator
        $errors[] = 'Valid email is required.';
    }

    if (empty($phone_number)) {
        // Check if phone number is provided
        $errors[] = 'Phone number is required.';
    }

    if (empty($id_number)) {
        // Check if National ID number is provided
        $errors[] = 'ID number is required.';
    }

    if (empty($password) || strlen($password) < 6) {
        // Check if password is at least 6 characters long
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirm_password) {
        // Verify that both password fields match
        $errors[] = 'Passwords do not match.';
    }

    if (empty($terms_agreed)) {
        // Verify that user agreed to terms and conditions
        $errors[] = 'You must agree to the Terms of Service and Privacy Policy.';
    }

    // Check if the email already exists in the system to prevent duplicates
    $user = new User();
    if ($user->findByEmail($email)) {
        $errors[] = 'Email already exists. Please use a different email.';
    }

    // Process registration if no validation errors were found
    if (empty($errors)) {
        // Attempt to register the user using the DatabaseClass User method
        if ($user->register($full_name, $email, $phone_number, $id_number, $password, $role)) {
            // Successful registration message
            $success = 'Registration successful! You can now log in.';
        } else {
            // Error message if registration failed at the database level
            $error = 'Registration failed. Please try again.';
        }
    } else {
        // Combine all validation errors into a single message separated by line breaks
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register - Chama Management System</title>
    <link rel="stylesheet" href="forms.css" />
  </head>
  <body>
    <form action="" method="post" class="container">
      <span><h1>Create Account</h1></span>
      <p>Join your Chama management system</p>

      <?php if (!empty($error)): ?>
        <div style="color: red; margin: 10px 0; text-align: center;"><?php echo $error; ?></div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div style="color: green; margin: 10px 0; text-align: center;"><?php echo htmlspecialchars($success); ?></div>
        <script>
          // Redirect to login after successful registration
          setTimeout(function() {
            window.location.href = 'Login.php';
          }, 3000);
        </script>
      <?php endif; ?>

      <div class="input-type">
        <label for="name">Full Name</label><br />
        <input
          type="text"
          id="name"
          name="name"
          placeholder="Enter your full name"
          required
          value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
        />

        <label for="email" class="input-type">Email Address</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="yourname@gmail.com"
          required
          value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
        />
      </div>

      <div class="input-type">
        <label for="number" class="input-type">Phone Number</label>
        <input
          type="tel"
          id="number"
          name="number"
          placeholder="254XXXXXXXXX"
          required
          value="<?php echo isset($_POST['number']) ? htmlspecialchars($_POST['number']) : ''; ?>"
        />

        <label for="id" class="input-type">ID Number</label>
        <input type="tel" id="id" name="id" placeholder="12345678" required
          value="<?php echo isset($_POST['id']) ? htmlspecialchars($_POST['id']) : ''; ?>"
        />
      </div>
      <br />

      <label for="register">Register As</label>
      <select name="register" id="register" required>
        <option value="member" <?php echo (isset($_POST['register']) && $_POST['register'] === 'member') ? 'selected' : ''; ?>>Member</option>
        <option value="treasurer" <?php echo (isset($_POST['register']) && $_POST['register'] === 'treasurer') ? 'selected' : ''; ?>>Treasurer</option>
        <option value="admin" <?php echo (isset($_POST['register']) && $_POST['register'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
      </select>

      <div class="input-type">
        <label for="password" class="input-type">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="Enter your password"
          required
        />

        <label for="confirm_password" class="input-type"
          >Confirm Password</label
        >
        <input
          type="password"
          id="confirm_password"
          name="confirm_password"
          placeholder="Confirm your password"
          required
        />
      </div>

      <input type="checkbox" name="terms" id="terms" class="checkbox" value="1" required />
      <label for="terms"
        >I agree to the <a href="">Terms of Service</a> and
        <a href="">Privacy Policy</a>
      </label>

      <button type="submit" class="mybutton">Create Account</button>

      <footer>
        <p>Already have an account? <a href="Login.php">Sign In</a></p>
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
