<?php
require_once '../includes/db.php';
require_once '../includes/mailer.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = isset($_SESSION['otp_user_id']) ? $_SESSION['otp_user_id'] : null;
    
    if ($user_id) {
        $stmt = $conn->prepare("SELECT username, email FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            $otp = rand(100000, 999999);
            $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $stmt2 = $conn->prepare("UPDATE users SET otp_code=?, otp_expires=? WHERE id=?");
            $stmt2->bind_param("ssi", $otp, $otp_expires, $user_id);
            $stmt2->execute();
            $stmt2->close();
            
            $send = send_otp_email($user['email'], $user['username'], (string)$otp);
            
            if($send['success']){
                echo json_encode(['success' => true, 'message' => 'A new OTP has been sent to your email.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send OTP: ' . htmlspecialchars($send['error'] ?? 'unknown')]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Session error. Please register again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
