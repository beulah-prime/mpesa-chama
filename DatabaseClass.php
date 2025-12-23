
<?php
/*
 * Database Class for Chama Management System
 *
 * This file contains OOP database classes that provide secure and efficient
 * database operations for the Chama Management System.
 *
 * Features:
 * - PDO-based database connection with security features
 * - User management functionality (registration, login, etc.)
 * - Contribution tracking and management
 * - Loan application and management
 * - Fine tracking and management
 * - System settings management
 * - Prepared statements to prevent SQL injection
 *
 * @author ChamaSys Development Team
 * @version 1.0
 * @since 2025
 */

// Database configuration constants for connection
define('DB_HOST', 'localhost');      // Database server host
define('DB_USER', 'root');          // Database username
define('DB_PASS', '');              // Database password 
define('DB_NAME', 'chama_db');      // Database name for the Chama system

/**
 * Database class for handling all database operations
 *
 * This class provides a secure PDO-based database connection with prepared statements
 * to prevent SQL injection attacks. It implements the singleton pattern for efficient
 * database connection management.
 */
class Database {
    private $connection;      // PDO connection object
    private $stmt;           // Prepared statement object

    /**
     * Constructor - Initialize database connection
     * Automatically connects to the database when object is created
     */
    public function __construct() {
        $this->connect();
    }

    /**
     * Private method to establish database connection
     * Uses PDO with security-focused options
     */
    private function connect() {
        try {
            // Data Source Name string for MySQL connection with UTF-8 support
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

            // Security-focused PDO options
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // Throw exceptions on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return associative arrays
                PDO::ATTR_EMULATE_PREPARES => false,              // Use actual prepared statements
            ];

            // Create new PDO connection
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Display connection error message
            echo "Connection failed: " . $e->getMessage();
        }
    }

    /**
     * Prepare an SQL statement for execution
     * @param string $sql SQL query to prepare
     */
    public function query($sql) {
        $this->stmt = $this->connection->prepare($sql);
    }

    /**
     * Bind a value to a parameter in the prepared statement
     * @param string $param Parameter identifier
     * @param mixed $value Value to bind
     * @param int|null $type Explicit data type (optional)
     */
    public function bind($param, $value, $type = null) {
        if (is_null($type)) {
            // Auto-detect data type if not specified
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
                    break;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }

    /**
     * Execute the prepared statement
     * @return bool True if execution was successful
     */
    public function execute() {
        return $this->stmt->execute();
    }

    /**
     * Get result set as array of associative arrays
     * @return array All matching records
     */
    public function resultSet() {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    /**
     * Get single record as associative array
     * @return array|false Single record or false if no record found
     */
    public function single() {
        $this->execute();
        return $this->stmt->fetch();
    }

    /**
     * Get number of rows affected by the last statement
     * @return int Number of affected rows
     */
    public function rowCount() {
        return $this->stmt->rowCount();
    }

    /**
     * Get the ID of the last inserted row
     * @return string Last inserted ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * Begin a database transaction
     * @return bool True if transaction started successfully
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit the current transaction
     * @return bool True if commit was successful
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Rollback the current transaction
     * @return bool True if rollback was successful
     */
    public function rollback() {
        return $this->connection->rollback();
    }
}

/**
 * User class for user-related operations
 *
 * This class handles all user management functions including:
 * - User registration with automatic member creation
 * - User authentication (login)
 * - User lookup by email
 * - User role management
 * - User status management
 */
class User {
    private $db;    // Database object instance

    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Register a new user in the system
     * Automatically creates a corresponding member record
     *
     * @param string $full_name Full name of the user
     * @param string $email Email address of the user
     * @param string $phone_number Phone number of the user
     * @param string $id_number National ID number of the user
     * @param string $password Plain text password (will be hashed)
     * @param string $role User role (admin, treasurer, member) - defaults to 'member'
     * @return bool True if registration was successful, false otherwise
     */
    public function register($full_name, $email, $phone_number, $id_number, $password, $role = 'member') {
        // Prepare SQL statement to insert user into database
        $this->db->query('INSERT INTO users (full_name, email, phone_number, id_number, password_hash, role) VALUES (:full_name, :email, :phone_number, :id_number, :password_hash, :role)');

        // Hash the password using PHP's built-in password hashing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Bind all parameters to prevent SQL injection
        $this->db->bind(':full_name', $full_name);
        $this->db->bind(':email', $email);
        $this->db->bind(':phone_number', $phone_number);
        $this->db->bind(':id_number', $id_number);
        $this->db->bind(':password_hash', $hashed_password);
        $this->db->bind(':role', $role);

        // Execute the query and create member record if successful
        if ($this->db->execute()) {
            // Get the ID of the newly created user
            $user_id = $this->db->lastInsertId();

            // Create a corresponding member record
            $this->createMember($user_id);
            return true;
        }

        return false;
    }

    /**
     * Create a member record linked to a user
     * This method is called automatically during user registration
     *
     * @param int $user_id The ID of the user to create a member record for
     * @return bool True if member creation was successful, false otherwise
     */
    private function createMember($user_id) {
        // Prepare SQL statement to insert member record
        $this->db->query('INSERT INTO members (user_id, member_number, join_date) VALUES (:user_id, :member_number, CURDATE())');

        // Generate unique member number based on user ID
        $member_number = 'MEM' . str_pad($user_id, 6, '0', STR_PAD_LEFT);

        // Bind parameters to prevent SQL injection
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':member_number', $member_number);

        return $this->db->execute();
    }

    /**
     * Find a user by their email address
     *
     * @param string $email Email address to search for
     * @return array|false User data array if found, false otherwise
     */
    public function findByEmail($email) {
        // Prepare SQL statement to find user by email
        $this->db->query('SELECT * FROM users WHERE email = :email');
        $this->db->bind(':email', $email);

        return $this->db->single();
    }

    /**
     * Authenticate a user during login
     * Verifies email and password combination
     *
     * @param string $email Email address provided by user
     * @param string $password Plain text password provided by user
     * @return array|false User data array if credentials are valid, false otherwise
     */
    public function login($email, $password) {
        // First, find the user by email
        $user = $this->findByEmail($email);

        // If user exists and password is valid, return user data
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }

        return false;
    }

    /**
     * Get all users, optionally filtered by role
     *
     * @param string|null $role Optional role filter (admin, treasurer, member)
     * @return array Array of user data including member numbers
     */
    public function getAllUsersByRole($role = null) {
        if ($role) {
            // Filter users by specific role
            $this->db->query('SELECT u.*, m.member_number FROM users u LEFT JOIN members m ON u.id = m.user_id WHERE u.role = :role ORDER BY u.created_at DESC');
            $this->db->bind(':role', $role);
        } else {
            // Get all users regardless of role
            $this->db->query('SELECT u.*, m.member_number FROM users u LEFT JOIN members m ON u.id = m.user_id ORDER BY u.created_at DESC');
        }

        return $this->db->resultSet();
    }

    /**
     * Update a user's status (active/inactive)
     *
     * @param int $user_id ID of the user to update
     * @param string $status New status ('active' or 'inactive')
     * @return bool True if update was successful, false otherwise
     */
    public function updateUserStatus($user_id, $status) {
        // Prepare SQL statement to update user status
        $this->db->query('UPDATE users SET status = :status WHERE id = :user_id');
        $this->db->bind(':status', $status);
        $this->db->bind(':user_id', $user_id);

        return $this->db->execute();
    }

    /**
     * Update an existing user
     *
     * @param int $user_id ID of the user to update
     * @param string $full_name Full name of the user
     * @param string $email Email address of the user
     * @param string $phone_number Phone number of the user
     * @param string $id_number National ID number of the user
     * @param string $role User role (admin, treasurer, member)
     * @param string $status User status (active, inactive)
     * @return bool True if update was successful, false otherwise
     */
    public function updateUser($user_id, $full_name, $email, $phone_number, $id_number, $role, $status) {
        // First, check if another user already has this email (excluding current user)
        $this->db->query('SELECT id FROM users WHERE email = :email AND id != :user_id');
        $this->db->bind(':email', $email);
        $this->db->bind(':user_id', $user_id);
        $existing_user = $this->db->single();

        if ($existing_user) {
            // Email already exists for another user
            return false;
        }

        // Prepare SQL statement to update user information
        $sql = 'UPDATE users SET full_name = :full_name, email = :email, phone_number = :phone_number, id_number = :id_number, role = :role, status = :status WHERE id = :user_id';
        $this->db->query($sql);

        // Bind all parameters to prevent SQL injection
        $this->db->bind(':user_id', $user_id);
        $this->db->bind(':full_name', $full_name);
        $this->db->bind(':email', $email);
        $this->db->bind(':phone_number', $phone_number);
        $this->db->bind(':id_number', $id_number);
        $this->db->bind(':role', $role);
        $this->db->bind(':status', $status);

        return $this->db->execute();
    }

    /**
     * Delete a user by ID
     * This method will delete the user and associated member record
     *
     * @param int $user_id ID of the user to delete
     * @return bool True if deletion was successful, false otherwise
     */
    public function deleteUser($user_id) {
        try {
            // Begin transaction for data integrity
            $this->db->beginTransaction();

            // First, get the user to verify they exist
            $this->db->query('SELECT id FROM users WHERE id = :user_id');
            $this->db->bind(':user_id', $user_id);
            $user = $this->db->single();

            if (!$user) {
                return false;
            }

            // Delete associated member record
            $this->db->query('DELETE FROM members WHERE user_id = :user_id');
            $this->db->bind(':user_id', $user_id);
            $this->db->execute();

            // Delete associated contributions
            // First get the member ID to delete contributions
            $this->db->query('SELECT id FROM members WHERE user_id = :user_id');
            $this->db->bind(':user_id', $user_id);
            $member_result = $this->db->single();

            if ($member_result) {
                $member_id = $member_result['id'];

                // Delete contributions for this member
                $this->db->query('DELETE FROM contributions WHERE member_id = :member_id');
                $this->db->bind(':member_id', $member_id);
                $this->db->execute();

                // Delete fines for this member
                $this->db->query('DELETE FROM fines WHERE member_id = :member_id');
                $this->db->bind(':member_id', $member_id);
                $this->db->execute();

                // Delete loans for this member
                $this->db->query('DELETE FROM loans WHERE member_id = :member_id');
                $this->db->bind(':member_id', $member_id);
                $this->db->execute();

                // Delete loan repayments for this member
                $this->db->query('DELETE FROM loan_repayments WHERE member_id = :member_id');
                $this->db->bind(':member_id', $member_id);
                $this->db->execute();

                // Delete M-Pesa transactions for this member
                $this->db->query('DELETE FROM mpesa_transactions WHERE member_id = :member_id');
                $this->db->bind(':member_id', $member_id);
                $this->db->execute();

                // Delete M-Pesa STK requests for this member
                $this->db->query('DELETE FROM mpesa_stk_requests WHERE phone_number IN (SELECT phone_number FROM users WHERE id = :user_id)');
                $this->db->bind(':user_id', $user_id);
                $this->db->execute();
            }

            // Finally, delete the user
            $this->db->query('DELETE FROM users WHERE id = :user_id');
            $this->db->bind(':user_id', $user_id);
            $result = $this->db->execute();

            // Commit the transaction if all operations were successful
            $this->db->commit();

            return $result;
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $this->db->rollback();
            error_log("Error deleting user: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a user by ID
     *
     * @param int $user_id ID of the user to retrieve
     * @return array|false User data array if found, false otherwise
     */
    public function getUserById($user_id) {
        // Prepare SQL statement to get user by ID
        $this->db->query('SELECT u.*, m.member_number FROM users u LEFT JOIN members m ON u.id = m.user_id WHERE u.id = :user_id');
        $this->db->bind(':user_id', $user_id);

        return $this->db->single();
    }
}

/**
 * Contribution class for handling contributions
 *
 * This class manages all contribution-related database operations including:
 * - Adding new contributions
 * - Retrieving member-specific contributions
 * - Retrieving all contributions with member details
 * - Automatic status assignment based on payment method
 */
class Contribution {
    private $db;    // Database object instance

    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Add a new contribution to the system
     *
     * @param int $member_id ID of the member making the contribution
     * @param float $amount Amount contributed
     * @param string $payment_method Payment method used ('mpesa', 'cash', 'bank_transfer') - defaults to 'mpesa'
     * @param string|null $mpesa_code M-Pesa transaction code (if applicable)
     * @return bool True if contribution was added successfully, false otherwise
     */
    public function addContribution($member_id, $amount, $payment_method = 'mpesa', $mpesa_code = null) {
        // Prepare SQL statement to insert contribution record
        $this->db->query('INSERT INTO contributions (member_id, amount, contribution_date, payment_method, mpesa_code, status) VALUES (:member_id, :amount, CURDATE(), :payment_method, :mpesa_code, :status)');

        // Determine status based on whether an M-Pesa code was provided
        // Contributions with M-Pesa code are automatically confirmed; others are pending
        $status = $mpesa_code ? 'confirmed' : 'pending';

        // Bind all parameters to prevent SQL injection
        $this->db->bind(':member_id', $member_id);
        $this->db->bind(':amount', $amount);
        $this->db->bind(':payment_method', $payment_method);
        $this->db->bind(':mpesa_code', $mpesa_code);
        $this->db->bind(':status', $status);

        return $this->db->execute();
    }

    /**
     * Get all contributions for a specific member
     *
     * @param int $member_id ID of the member to retrieve contributions for
     * @return array Array of contribution records for the member
     */
    public function getMemberContributions($member_id) {
        // Prepare SQL statement to get contributions for specific member, ordered by date (newest first)
        $this->db->query('SELECT * FROM contributions WHERE member_id = :member_id ORDER BY contribution_date DESC');
        $this->db->bind(':member_id', $member_id);

        return $this->db->resultSet();
    }

    /**
     * Get all contributions in the system with member details
     *
     * @return array Array of all contributions with member numbers and full names
     */
    public function getAllContributions() {
        // Prepare SQL statement to join contributions with member and user data
        $this->db->query('SELECT c.*, m.member_number, u.full_name FROM contributions c JOIN members m ON c.member_id = m.id JOIN users u ON m.user_id = u.id ORDER BY c.contribution_date DESC');

        return $this->db->resultSet();
    }
}

/**
 * Loan class for handling loans
 *
 * This class manages all loan-related database operations including:
 * - Loan application processing
 * - Loan approval workflow
 * - Loan status updates
 * - Retrieving member-specific loans
 * - Retrieving all loans with member details
 * - Automatic interest rate calculation
 */
class Loan {
    private $db;    // Database object instance

    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Apply for a loan
     * Creates a new loan application with calculated repayment amount
     *
     * @param int $member_id ID of the member applying for the loan
     * @param float $loan_amount Amount requested for the loan
     * @param int $duration_months Duration of the loan in months
     * @param float|null $interest_rate Interest rate for the loan (uses default if null)
     * @return bool True if loan application was successful, false otherwise
     */
    public function applyForLoan($member_id, $loan_amount, $duration_months, $interest_rate = null) {
        // Get default interest rate from system settings if not provided
        if ($interest_rate === null) {
            $this->db->query('SELECT setting_value FROM settings WHERE setting_key = "interest_rate_default"');
            $result = $this->db->single();
            $interest_rate = $result ? floatval($result['setting_value']) : 10.00;
        }

        // Calculate total repayment amount including interest
        $total_repayment = $loan_amount + ($loan_amount * $interest_rate / 100);

        // Calculate due date based on duration
        $due_date = date('Y-m-d', strtotime("+{$duration_months} months"));

        // Prepare SQL statement to insert loan application
        $this->db->query('INSERT INTO loans (member_id, loan_amount, interest_rate, total_repayment, duration_months, date_applied, due_date) VALUES (:member_id, :loan_amount, :interest_rate, :total_repayment, :duration_months, CURDATE(), :due_date)');

        // Bind all parameters to prevent SQL injection
        $this->db->bind(':member_id', $member_id);
        $this->db->bind(':loan_amount', $loan_amount);
        $this->db->bind(':interest_rate', $interest_rate);
        $this->db->bind(':total_repayment', $total_repayment);
        $this->db->bind(':duration_months', $duration_months);
        $this->db->bind(':due_date', $due_date);

        return $this->db->execute();
    }

    /**
     * Get all loans for a specific member
     *
     * @param int $member_id ID of the member to retrieve loans for
     * @return array Array of loan records for the member
     */
    public function getMemberLoans($member_id) {
        // Prepare SQL statement to get loans for specific member, ordered by application date (newest first)
        $this->db->query('SELECT * FROM loans WHERE member_id = :member_id ORDER BY date_applied DESC');
        $this->db->bind(':member_id', $member_id);

        return $this->db->resultSet();
    }

    /**
     * Get all loans in the system with member details
     *
     * @return array Array of all loans with member numbers and full names
     */
    public function getAllLoans() {
        // Prepare SQL statement to join loans with member and user data
        $this->db->query('SELECT l.*, m.member_number, u.full_name FROM loans l JOIN members m ON l.member_id = m.id JOIN users u ON m.user_id = u.id ORDER BY l.date_applied DESC');

        return $this->db->resultSet();
    }

    /**
     * Update loan status (pending, approved, rejected, etc.)
     * Optionally set the approver ID and approval date
     *
     * @param int $loan_id ID of the loan to update
     * @param string $status New status for the loan ('pending', 'approved', 'rejected', 'disbursed', 'paid')
     * @param int|null $approver_id ID of the user approving the loan (if applicable)
     * @return bool True if update was successful, false otherwise
     */
    public function updateLoanStatus($loan_id, $status, $approver_id = null) {
        // Initialize arrays to build dynamic SQL query
        $fields = [];
        $params = [':loan_id' => $loan_id, ':status' => $status];

        // Add status field to update
        $fields[] = 'status = :status';

        // Add approver information if provided
        if ($approver_id) {
            $fields[] = 'approved_by = :approver_id';
            $params[':approver_id'] = $approver_id;

            // Set approval date if loan is being approved
            if ($status === 'approved') {
                $fields[] = 'approved_date = CURDATE()';
            }
        }

        // Build the complete SQL statement dynamically
        $sql = 'UPDATE loans SET ' . implode(', ', $fields) . ' WHERE id = :loan_id';
        $this->db->query($sql);

        // Bind all parameters to prevent SQL injection
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }

        return $this->db->execute();
    }
}

/**
 * Fine class for handling fines
 *
 * This class manages all fine-related database operations including:
 * - Fine creation with automatic amount calculation
 * - Fine status tracking (pending, paid, waived)
 * - Fine payment processing
 * - M-Pesa transaction recording for fine payments
 * - Retrieving member-specific fines
 * - Retrieving all fines with member details
 */
class Fine {
    private $db;    // Database object instance

    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Add a fine to a member
     * Uses default fine amount from system settings if amount is not specified
     *
     * @param int $member_id ID of the member to fine
     * @param string $reason Reason for the fine
     * @param float|null $amount Amount of the fine (uses default if null)
     * @param int $imposed_by ID of the user imposing the fine
     * @return bool True if fine was added successfully, false otherwise
     */
    public function addFine($member_id, $reason, $amount = null, $imposed_by) {
        // Get default fine amount from system settings if not provided
        if ($amount === null) {
            $this->db->query('SELECT setting_value FROM settings WHERE setting_key = "fine_amount_default"');
            $result = $this->db->single();
            $amount = $result ? floatval($result['setting_value']) : 500.00;
        }

        // Prepare SQL statement to insert fine record with 30-day due date
        $this->db->query('INSERT INTO fines (member_id, reason, amount, date_imposed, due_date, imposed_by) VALUES (:member_id, :reason, :amount, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), :imposed_by)');

        // Bind all parameters to prevent SQL injection
        $this->db->bind(':member_id', $member_id);
        $this->db->bind(':reason', $reason);
        $this->db->bind(':amount', $amount);
        $this->db->bind(':imposed_by', $imposed_by);

        return $this->db->execute();
    }

    /**
     * Get all fines for a specific member
     *
     * @param int $member_id ID of the member to retrieve fines for
     * @return array Array of fine records for the member
     */
    public function getMemberFines($member_id) {
        // Prepare SQL statement to get fines for specific member, ordered by date imposed (newest first)
        $this->db->query('SELECT * FROM fines WHERE member_id = :member_id ORDER BY date_imposed DESC');
        $this->db->bind(':member_id', $member_id);

        return $this->db->resultSet();
    }

    /**
     * Get all fines in the system with member details
     *
     * @return array Array of all fines with member numbers and full names
     */
    public function getAllFines() {
        // Prepare SQL statement to join fines with member and user data
        $this->db->query('SELECT f.*, m.member_number, u.full_name FROM fines f JOIN members m ON f.member_id = m.id JOIN users u ON m.user_id = u.id ORDER BY f.date_imposed DESC');

        return $this->db->resultSet();
    }

    /**
     * Mark a fine as paid
     * Updates the fine status and records payment in M-Pesa transactions if applicable
     *
     * @param int $fine_id ID of the fine to mark as paid
     * @param string $payment_method Payment method used ('mpesa', 'cash', etc.) - defaults to 'mpesa'
     * @param string|null $mpesa_code M-Pesa transaction code (if applicable)
     * @return bool True if fine was updated successfully, false otherwise
     */
    public function markAsPaid($fine_id, $payment_method = 'mpesa', $mpesa_code = null) {
        // Prepare SQL statement to update fine status to paid
        $this->db->query('UPDATE fines SET status = "paid", paid_date = CURDATE() WHERE id = :fine_id');
        $this->db->bind(':fine_id', $fine_id);

        // Add to M-Pesa transactions if M-Pesa was used for payment
        if ($payment_method === 'mpesa' && $mpesa_code) {
            $this->recordMpesaTransaction($fine_id, 'fine_payment', $mpesa_code, $payment_method);
        }

        return $this->db->execute();
    }

    /**
     * Record an M-Pesa transaction for payment
     * This is a private method called when a payment is made via M-Pesa
     *
     * @param int $reference_id ID of the original record (fine, contribution, etc.)
     * @param string $transaction_type Type of transaction ('fine_payment', 'contribution', etc.)
     * @param string $mpesa_code M-Pesa transaction code
     * @param string $payment_method Payment method used
     */
    private function recordMpesaTransaction($reference_id, $transaction_type, $mpesa_code, $payment_method) {
        // Get the amount from the original transaction record
        $this->db->query('SELECT amount FROM ' . $transaction_type . 's WHERE id = :reference_id');
        $this->db->bind(':reference_id', $reference_id);
        $amount_result = $this->db->single();

        if ($amount_result) {
            $amount = $amount_result['amount'];

            // Need to get member_id based on transaction type for proper linking
            if ($transaction_type === 'fine_payment') {
                $this->db->query('SELECT member_id FROM fines WHERE id = :reference_id');
            } elseif ($transaction_type === 'loan_payment') {
                $this->db->query('SELECT loan_id FROM loan_repayments WHERE id = :reference_id');
                // Need to get member_id from loan
                $loan_result = $this->db->single();
                if ($loan_result) {
                    $loan_id = $loan_result['loan_id'];
                    $this->db->query('SELECT member_id FROM loans WHERE id = :loan_id');
                    $this->db->bind(':loan_id', $loan_id);
                }
            } else { // contribution
                $this->db->query('SELECT member_id FROM contributions WHERE id = :reference_id');
            }

            $this->db->bind(':reference_id', $reference_id);
            $member_result = $this->db->single();

            if ($member_result) {
                $member_id = $member_result['member_id'];

                // Insert the M-Pesa transaction record
                $this->db->query('INSERT INTO mpesa_transactions (transaction_id, member_id, amount, transaction_type, reference_id, mpesa_code, transaction_date, status) VALUES (UUID(), :member_id, :amount, :transaction_type, :reference_id, :mpesa_code, NOW(), "confirmed")');

                // Bind parameters to prevent SQL injection
                $this->db->bind(':member_id', $member_id);
                $this->db->bind(':amount', $amount);
                $this->db->bind(':transaction_type', $transaction_type);
                $this->db->bind(':reference_id', $reference_id);
                $this->db->bind(':mpesa_code', $mpesa_code);

                $this->db->execute();
            }
        }
    }
}

/**
 * Settings class for managing system settings
 *
 * This class manages system-wide configuration settings stored in the database including:
 * - System name and branding
 * - Default interest rates
 * - Default fine amounts
 * - Currency settings
 * - Other configurable parameters
 */
class Settings {
    private $db;    // Database object instance

    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Get a system setting by its key
     *
     * @param string $key Key of the setting to retrieve
     * @return string|null Value of the setting if found, null otherwise
     */
    public function getSetting($key) {
        // Prepare SQL statement to get a specific setting by key
        $this->db->query('SELECT setting_value FROM settings WHERE setting_key = :key');
        $this->db->bind(':key', $key);

        $result = $this->db->single();
        return $result ? $result['setting_value'] : null;
    }

    /**
     * Update a system setting
     *
     * @param string $key Key of the setting to update
     * @param string $value New value for the setting
     * @return bool True if update was successful, false otherwise
     */
    public function updateSetting($key, $value) {
        // Prepare SQL statement to update a setting value with timestamp
        $this->db->query('UPDATE settings SET setting_value = :value, updated_at = CURRENT_TIMESTAMP WHERE setting_key = :key');
        $this->db->bind(':key', $key);
        $this->db->bind(':value', $value);

        return $this->db->execute();
    }

    /**
     * Get all system settings
     *
     * @return array Array of all settings with keys and values
     */
    public function getAllSettings() {
        // Prepare SQL statement to get all settings
        $this->db->query('SELECT * FROM settings');

        return $this->db->resultSet();
    }
}

// Example usage:
// $user = new User();
// $user->register('John Doe', 'john@example.com', '254712345678', '12345678', 'password123', 'member');
?>