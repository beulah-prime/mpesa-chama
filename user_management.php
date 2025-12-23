<?php
/*
 * User Management API for Chama Management System
 *
 * This file handles user management operations like editing and deleting users
 * through AJAX requests from the admin panel.
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
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if user has admin role - only admins can perform user management operations
if ($_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
    exit();
}

// Set content type to JSON for proper response
header('Content-Type: application/json');

// Create User object to access user-related database methods
$userManager = new User();

// Get the action from the request
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'edit':
            // Handle user edit request
            $user_id = $_POST['user_id'] ?? '';
            $full_name = $_POST['full_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone_number = $_POST['phone_number'] ?? '';
            $id_number = $_POST['id_number'] ?? '';
            $role = $_POST['role'] ?? '';
            $status = $_POST['status'] ?? '';

            if (empty($user_id)) {
                throw new Exception('User ID is required');
            }

            // Validate inputs
            if (empty($full_name) || empty($email) || empty($phone_number) || empty($id_number) || empty($role)) {
                throw new Exception('All fields are required');
            }

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }

            // Validate phone number format (should be 10-15 digits)
            $clean_phone = preg_replace('/[^0-9]/', '', $phone_number);
            if (strlen($clean_phone) < 10 || strlen($clean_phone) > 15) {
                throw new Exception('Invalid phone number format');
            }

            // Update the user
            $result = $userManager->updateUser($user_id, $full_name, $email, $phone_number, $id_number, $role, $status);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            } else {
                throw new Exception('Failed to update user');
            }
            break;

        case 'delete':
            // Handle user delete request
            $user_id = $_POST['user_id'] ?? '';

            if (empty($user_id)) {
                throw new Exception('User ID is required');
            }

            // Delete the user
            $result = $userManager->deleteUser($user_id);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
            } else {
                throw new Exception('Failed to delete user');
            }
            break;

        case 'get':
            // Handle user data retrieval for editing
            $user_id = $_GET['user_id'] ?? '';
            $user_id_post = $_POST['user_id'] ?? ''; // Also check POST for consistency

            $user_id = $user_id ?: $user_id_post;

            if (empty($user_id)) {
                throw new Exception('User ID is required');
            }

            // Get user data
            $user_data = $userManager->getUserById($user_id);

            if ($user_data) {
                echo json_encode(['success' => true, 'data' => $user_data]);
            } else {
                throw new Exception('User not found');
            }
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// The UserExtended class is not needed since we've added the methods to the main User class in DatabaseClass.php
// We can just use the main User class
$userManager = new User();
?>