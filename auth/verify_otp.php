<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
include '../includes/header.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = isset($_SESSION['otp_user_id']) ? $_SESSION['otp_user_id'] : null;
    $code = isset($_POST['otp_code']) ? preg_replace('/\D/', '', $_POST['otp_code']) : '';

    if (strlen($code) !== 6) {
        echo "<p class='text-muted'>OTP must be exactly 6 digits.</p>";
    } elseif ($user_id) {
        $stmt = $conn->prepare("SELECT otp_code, otp_expires, username FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($db_otp, $otp_expiry, $username);

        if($stmt->fetch()) {
            if ($db_otp && $otp_expiry) {
                if($db_otp == $code && strtotime($otp_expiry) > time()) {
                    $stmt->close();
                    $stmt2 = $conn->prepare("UPDATE users SET otp_code=NULL, otp_expires=NULL, role='regular_user' WHERE id=?");
                    $stmt2->bind_param("i", $user_id);
                    $stmt2->execute();
                    $stmt2->close();
                    
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['role'] = 'regular_user';
                    $_SESSION['username'] = $username;
                    unset($_SESSION['otp_user_id']);
                    unset($_SESSION['otp_email']);

                    log_action($conn, $user_id, 'OTP verified and user promoted to regular_user');

                    echo "<p class='text-muted'>OTP verified! You can now <a href='/SCP/index.php'>continue shopping</a>.</p>";
                } else {
                    echo "<p class='text-muted'>Invalid or expired OTP.</p>";
                }
            } else {
                echo "<p class='text-muted'>No OTP to verify. Already verified?</p>";
            }
        } else {
            echo "<p class='text-muted'>User not found.</p>";
        }
        $stmt->close();
    } else {
        echo "<p class='text-muted'>Session error. Please register again.</p>";
    }
}
?>

<div class="form-card mx-auto">
  <h3 class="mb-4">Verify OTP</h3>
  <p style="color: #64748b;">Enter the 6-digit code sent to your email.</p>
  <form method="post">
    <div class="mb-4">
      <label class="form-label">OTP Code</label>
      <input type="text" class="form-control" name="otp_code" maxlength="6" required style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem;">
    </div>
    <button type="submit" class="btn btn-primary w-100">Verify</button>
  </form>
</div>

<?php include '../includes/footer.php'; ?>