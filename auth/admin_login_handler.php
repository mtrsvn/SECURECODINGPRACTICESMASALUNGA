<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        if (is_locked_out($user)) {
            $minutes = ceil((strtotime($user['lockout_until']) - time()) / 60);
            echo json_encode([
                'success' => false, 
                'message' => "This account is locked. Try again in $minutes minutes."
            ]);
            exit();
        }
        
        if (password_verify($password, $user['password_hash'])) {
            if (!in_array($user['role'], ['staff_user', 'administrator', 'admin_sec'], true)) {
                echo json_encode([
                    'success' => false, 
                    'message' => "Access denied. Only staff members and administrators can access this portal."
                ]);
                exit();
            }
            
            $stmt2 = $conn->prepare("UPDATE users SET failed_logins = 0, lockout_until = NULL WHERE id = ?");
            $stmt2->bind_param("i", $user['id']);
            $stmt2->execute();
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            
            log_action($conn, $user['id'], "Admin login - " . $user['role']);

            $redirect = '/SCP/admin/dashboard.php';
            if (in_array($user['role'], ['staff_user', 'admin_sec'], true)) {
                $redirect = '/SCP/staff/approve.php';
            }

            echo json_encode([
                'success' => true, 
                'redirect' => $redirect
            ]);
            exit();
        } else {
            $failed = $user['failed_logins'] + 1;
            
            if ($failed >= 3) {
                $lockout = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $stmt3 = $conn->prepare("UPDATE users SET failed_logins = ?, lockout_until = ? WHERE id = ?");
                $stmt3->bind_param("isi", $failed, $lockout, $user['id']);
                $stmt3->execute();
                
                echo json_encode([
                    'success' => false, 
                    'message' => "This account has been locked for 15 minutes due to 3 failed login attempts."
                ]);
                exit();
            } else {
                $stmt3 = $conn->prepare("UPDATE users SET failed_logins = ? WHERE id = ?");
                $stmt3->bind_param("ii", $failed, $user['id']);
                $stmt3->execute();
                
                echo json_encode([
                    'success' => false, 
                    'message' => "Invalid password. Failed attempt $failed of 3."
                ]);
                exit();
            }
        }
    } else {
        echo json_encode([
            'success' => false, 
            'message' => "Invalid credentials."
        ]);
        exit();
    }
    
    $stmt->close();
}
?>
