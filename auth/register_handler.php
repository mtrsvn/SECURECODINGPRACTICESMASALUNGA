<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit();
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
        exit();
    }
    if (!preg_match('/[A-Z]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one uppercase letter.']);
        exit();
    }
    if (!preg_match('/[a-z]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one lowercase letter.']);
        exit();
    }
    if (!preg_match('/[0-9]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one number.']);
        exit();
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        echo json_encode(['success' => false, 'message' => 'Password must contain at least one special character.']);
        exit();
    }

    $otp = rand(100000, 999999);
    $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $conn->prepare("INSERT INTO users (username,email,password_hash,role,otp_code,otp_expires) VALUES (?, ?, ?, ?, ?, ?)");
    $pw_hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'regular_user';
    $stmt->bind_param("ssssss", $username, $email, $pw_hash, $role, $otp, $otp_expires);
    if($stmt->execute()){
        $_SESSION['otp_user_id'] = $conn->insert_id;
        $_SESSION['otp_email'] = $email;
        
        $send = send_otp_email($email, $username, (string)$otp);
        if(!$send['success']){
            echo json_encode(['success' => false, 'message' => 'Registration succeeded but failed to send OTP email: ' . htmlspecialchars($send['error'] ?? 'unknown')]);
            exit();
        }
        echo json_encode(['success' => true, 'message' => 'A verification code has been sent to your email.', 'email' => $email]);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Could not register. Username or email may already be taken.']);
        exit();
    }
    $stmt->close();
    $conn->close();
}
?>
