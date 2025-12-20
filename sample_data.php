<?php
// Sample data for Chama Management System
// This script adds sample users, members, contributions, loans, and fines for testing

require_once 'DatabaseClass.php';

try {
    $db = new Database();
    
    echo "Adding sample data to the Chama Management System...\n\n";
    
    // Add sample users (admin, treasurer, and members)
    $users = [
        ['full_name' => 'Admin User', 'email' => 'admin@example.com', 'phone_number' => '254700000001', 'id_number' => '11111111', 'password' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin'],
        ['full_name' => 'Treasurer User', 'email' => 'treasurer@example.com', 'phone_number' => '254700000002', 'id_number' => '22222222', 'password' => password_hash('treasurer123', PASSWORD_DEFAULT), 'role' => 'treasurer'],
        ['full_name' => 'John Member', 'email' => 'john@example.com', 'phone_number' => '254700000003', 'id_number' => '33333333', 'password' => password_hash('member123', PASSWORD_DEFAULT), 'role' => 'member'],
        ['full_name' => 'Jane Member', 'email' => 'jane@example.com', 'phone_number' => '254700000004', 'id_number' => '44444444', 'password' => password_hash('member123', PASSWORD_DEFAULT), 'role' => 'member'],
        ['full_name' => 'Bob Member', 'email' => 'bob@example.com', 'phone_number' => '254700000005', 'id_number' => '55555555', 'password' => password_hash('member123', PASSWORD_DEFAULT), 'role' => 'member'],
    ];
    
    foreach ($users as $user) {
        $db->query('INSERT INTO users (full_name, email, phone_number, id_number, password_hash, role) VALUES (:full_name, :email, :phone_number, :id_number, :password_hash, :role)');
        $db->bind(':full_name', $user['full_name']);
        $db->bind(':email', $user['email']);
        $db->bind(':phone_number', $user['phone_number']);
        $db->bind(':id_number', $user['id_number']);
        $db->bind(':password_hash', $user['password_hash']);
        $db->bind(':role', $user['role']);
        $db->execute();
        
        $user_id = $db->lastInsertId();
        
        // Create member record for non-admin users
        if ($user['role'] !== 'admin') {
            $member_number = 'MEM' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
            $db->query('INSERT INTO members (user_id, member_number, join_date) VALUES (:user_id, :member_number, CURDATE())');
            $db->bind(':user_id', $user_id);
            $db->bind(':member_number', $member_number);
            $db->execute();
        }
        
        echo "Added user: {$user['full_name']} ({$user['role']})\n";
    }
    
    // Add sample contributions
    $contributions = [
        ['member_id' => 2, 'amount' => 1000.00, 'payment_method' => 'mpesa', 'mpesa_code' => 'ABC123XYZ', 'status' => 'confirmed'],
        ['member_id' => 3, 'amount' => 1500.00, 'payment_method' => 'mpesa', 'mpesa_code' => 'DEF456UVW', 'status' => 'confirmed'],
        ['member_id' => 4, 'amount' => 800.00, 'payment_method' => 'cash', 'mpesa_code' => null, 'status' => 'confirmed'],
        ['member_id' => 2, 'amount' => 1200.00, 'payment_method' => 'mpesa', 'mpesa_code' => 'GHI789RST', 'status' => 'confirmed'],
        ['member_id' => 4, 'amount' => 2000.00, 'payment_method' => 'mpesa', 'mpesa_code' => 'JKL012OPQ', 'status' => 'pending'],
    ];
    
    foreach ($contributions as $contribution) {
        $db->query('INSERT INTO contributions (member_id, amount, contribution_date, payment_method, mpesa_code, status) VALUES (:member_id, :amount, DATE_SUB(CURDATE(), INTERVAL :interval DAY), :payment_method, :mpesa_code, :status)');
        
        // Use a different date for each contribution
        static $interval = 0;
        
        $db->bind(':member_id', $contribution['member_id']);
        $db->bind(':amount', $contribution['amount']);
        $db->bind(':interval', $interval);
        $db->bind(':payment_method', $contribution['payment_method']);
        $db->bind(':mpesa_code', $contribution['mpesa_code']);
        $db->bind(':status', $contribution['status']);
        $db->execute();
        
        $interval += 2; // Increment for different dates
        
        echo "Added contribution: KES {$contribution['amount']} for member ID {$contribution['member_id']}\n";
    }
    
    // Add sample loans
    $loans = [
        ['member_id' => 2, 'loan_amount' => 10000.00, 'interest_rate' => 10.00, 'total_repayment' => 11000.00, 'duration_months' => 6, 'status' => 'approved'],
        ['member_id' => 3, 'loan_amount' => 15000.00, 'interest_rate' => 12.00, 'total_repayment' => 16800.00, 'duration_months' => 8, 'status' => 'pending'],
        ['member_id' => 4, 'loan_amount' => 8000.00, 'interest_rate' => 8.00, 'total_repayment' => 8640.00, 'duration_months' => 4, 'status' => 'disbursed'],
    ];
    
    foreach ($loans as $loan) {
        $due_date = date('Y-m-d', strtotime("+{$loan['duration_months']} months"));
        
        $db->query('INSERT INTO loans (member_id, loan_amount, interest_rate, total_repayment, duration_months, date_applied, due_date, status, approved_by, approved_date) VALUES (:member_id, :loan_amount, :interest_rate, :total_repayment, :duration_months, DATE_SUB(CURDATE(), INTERVAL 10 DAY), :due_date, :status, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY))');
        
        $db->bind(':member_id', $loan['member_id']);
        $db->bind(':loan_amount', $loan['loan_amount']);
        $db->bind(':interest_rate', $loan['interest_rate']);
        $db->bind(':total_repayment', $loan['total_repayment']);
        $db->bind(':duration_months', $loan['duration_months']);
        $db->bind(':due_date', $due_date);
        $db->bind(':status', $loan['status']);
        $db->execute();
        
        echo "Added loan: KES {$loan['loan_amount']} for member ID {$loan['member_id']}, status: {$loan['status']}\n";
    }
    
    // Add sample fines
    $fines = [
        ['member_id' => 2, 'reason' => 'Late contribution payment', 'amount' => 500.00, 'status' => 'pending', 'imposed_by' => 2],
        ['member_id' => 3, 'reason' => 'Missing meeting', 'amount' => 300.00, 'status' => 'paid', 'imposed_by' => 2],
        ['member_id' => 4, 'reason' => 'Late loan repayment', 'amount' => 700.00, 'status' => 'pending', 'imposed_by' => 2],
    ];
    
    foreach ($fines as $fine) {
        $db->query('INSERT INTO fines (member_id, reason, amount, date_imposed, due_date, status, imposed_by, paid_date) VALUES (:member_id, :reason, :amount, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 25 DAY), :status, :imposed_by, CASE WHEN :status = "paid" THEN CURDATE() ELSE NULL END)');
        
        $db->bind(':member_id', $fine['member_id']);
        $db->bind(':reason', $fine['reason']);
        $db->bind(':amount', $fine['amount']);
        $db->bind(':status', $fine['status']);
        $db->bind(':imposed_by', $fine['imposed_by']);
        $db->execute();
        
        echo "Added fine: KES {$fine['amount']} for member ID {$fine['member_id']}, reason: {$fine['reason']}\n";
    }
    
    // Add sample M-Pesa transactions
    $mpesa_transactions = [
        ['member_id' => 2, 'amount' => 1000.00, 'transaction_type' => 'contribution', 'reference_id' => 1, 'mpesa_code' => 'ABC123XYZ'],
        ['member_id' => 3, 'amount' => 1500.00, 'transaction_type' => 'contribution', 'reference_id' => 2, 'mpesa_code' => 'DEF456UVW'],
        ['member_id' => 2, 'amount' => 1200.00, 'transaction_type' => 'contribution', 'reference_id' => 4, 'mpesa_code' => 'GHI789RST'],
    ];
    
    foreach ($mpesa_transactions as $transaction) {
        $db->query('INSERT INTO mpesa_transactions (transaction_id, member_id, amount, transaction_type, reference_id, mpesa_code, transaction_date, status) VALUES (UUID(), :member_id, :amount, :transaction_type, :reference_id, :mpesa_code, DATE_SUB(NOW(), INTERVAL 3 DAY), :status)');
        
        $db->bind(':member_id', $transaction['member_id']);
        $db->bind(':amount', $transaction['amount']);
        $db->bind(':transaction_type', $transaction['transaction_type']);
        $db->bind(':reference_id', $transaction['reference_id']);
        $db->bind(':mpesa_code', $transaction['mpesa_code']);
        $db->bind(':status', 'confirmed');
        $db->execute();
        
        echo "Added M-Pesa transaction: KES {$transaction['amount']} ({$transaction['transaction_type']}) for member ID {$transaction['member_id']}\n";
    }
    
    echo "\nSample data added successfully!\n";
    echo "Default login credentials:\n";
    echo "Admin: admin@example.com / admin123\n";
    echo "Treasurer: treasurer@example.com / treasurer123\n";
    echo "Member (John): john@example.com / member123\n";
    echo "Member (Jane): jane@example.com / member123\n";
    
} catch (PDOException $e) {
    echo "Error adding sample data: " . $e->getMessage() . "\n";
}
?>