<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (ob_get_level() === 0) {
  ob_start();
}
$__role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$__isAdminArea = in_array($__role, ['staff_user', 'administrator', 'admin_sec'], true);
$__pageTitle = $__isAdminArea ? 'Cartify - Staff' : 'Cartify';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($__pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="/SCP/assets/store-solid-full.svg" type="image/svg+xml">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    :root {
      --primary: #2563eb;
      --primary-hover: #1d4ed8;
      --dark: #1e293b;
      --gray: #64748b;
      --light: #f8fafc;
      --border: #e2e8f0;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: var(--light);
      color: var(--dark);
    }
    .navbar {
      background: #fff !important;
      border-bottom: 1px solid var(--border);
      padding: 1rem 0;
    }
    .navbar-brand {
      display: inline-block;
      font-weight: 900;
      color: var(--dark) !important;
      font-size: 2.4rem;
      text-decoration: none;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .brand-text {
      display: inline-block;
      font-weight: 900;
      font-size: 2.4rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--dark) !important;
      margin: 0;
      line-height: 1;
    }
    .nav-link {
      color: var(--gray) !important;
      font-weight: 500;
      padding: 0.5rem 1rem !important;
      transition: color 0.2s;
    }
    .nav-link:hover { color: var(--primary) !important; }
    .nav-link.active {
      color: var(--primary) !important;
      font-weight: 600;
    }
    .nav-link.logout-link:hover { color: #dc2626 !important; }
    .btn-primary {
      background: var(--primary);
      border: none;
      padding: 0.6rem 1.5rem;
      font-weight: 500;
      border-radius: 8px;
    }
    .btn-primary:hover { background: var(--primary-hover); }
    .btn-danger {
      border: none;
      padding: 0.6rem 1.5rem;
      font-weight: 500;
      border-radius: 8px;
    }
    .btn-secondary {
      background: #fff;
      border: 1px solid var(--border);
      color: var(--dark);
      padding: 0.6rem 1.5rem;
      font-weight: 500;
      border-radius: 8px;
    }
    .btn-secondary:hover { background: var(--light); border-color: var(--gray); }
    .card {
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      background: #fff;
    }
    .card-body { padding: 1.5rem; }
    .table {
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .table th {
      background: var(--light);
      font-weight: 600;
      color: var(--gray);
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
    }
    .table td, .table th { padding: 1rem; border-color: var(--border); }
    .form-control {
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 0.6rem 1rem;
    }
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    .alert {
      border: none;
      border-radius: 10px;
      padding: 1rem 1.25rem;
    }
    h1, h2, h3 { font-weight: 700; color: var(--dark); }
    .page-header {
      margin-bottom: 2rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--border);
    }
    .hero {
      background: #fff;
      border-radius: 16px;
      padding: 3rem;
      text-align: center;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      margin-top: 2rem;
    }
    .hero h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
    .hero p { color: var(--gray); font-size: 1.1rem; }
    .form-card {
      background: #fff;
      border-radius: 16px;
      padding: 2rem;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      max-width: 420px;
    }
    .password-hint {
      background: var(--light);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      font-size: 0.85rem;
      color: var(--gray);
      margin-top: 0.5rem;
    }
    .password-wrapper {
      position: relative;
    }
    .password-wrapper input.form-control {
      padding-right: 2.5rem;
    }
    .password-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: var(--gray);
      user-select: none;
      font-size: 1rem;
      transition: color 0.2s ease;
    }
    .password-toggle:hover {
      color: var(--dark);
    }
    .password-requirements {
      background: var(--light);
      border-radius: 8px;
      padding: 0.75rem 1rem;
      font-size: 0.85rem;
      margin-top: 0.5rem;
    }
    .password-requirements div {
      margin: 0.25rem 0;
    }
    .requirement-met {
      color: #16a34a;
    }
    .requirement-unmet {
      color: var(--gray);
    }
    .otp-input { width: 56px; height: 56px; text-align: center; font-size: 1.5rem; border-radius: 12px; border: 1px solid #cbd5e1; background: #fff; color: var(--dark); }
    .otp-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
    .otp-input::placeholder { color: var(--gray); opacity: 0.6; }
    .otp-input::-webkit-outer-spin-button,
    .otp-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .otp-input { -moz-appearance: textfield; }
    #otpInputs { gap: 10px; }
  </style>
</head>
<body>
<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>
<nav class="navbar navbar-expand-lg mb-4">
  <div class="container">
    <a class="navbar-brand" href="/SCP/products/products.php">
      <span class="brand-text">CARTIFY</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav align-items-center gap-1">
        <?php if(isset($_SESSION['username'])): ?>
          <?php 
            $isAdminUser = isset($_SESSION['role']) && in_array($_SESSION['role'], ['staff_user','administrator','admin_sec']);
            $canSeeAudit = isset($_SESSION['role']) && in_array($_SESSION['role'], ['administrator','admin_sec']);
            $canManageProducts = isset($_SESSION['role']) && in_array($_SESSION['role'], ['staff_user','administrator','admin_sec']);
            $isAdminSec = isset($_SESSION['role']) && $_SESSION['role'] === 'admin_sec';
          ?>
          <?php if ($canManageProducts): ?>
            <li class="nav-item"><a class="nav-link <?= $current_page == 'products_manage.php' ? 'active' : '' ?>" href="/SCP/staff/products_manage.php">Products</a></li>
          <?php endif; ?>
          <?php if (!$isAdminUser): ?>
            <li class="nav-item"><a class="nav-link <?= ($current_page == 'products.php' || $current_page == 'index.php') ? 'active' : '' ?>" href="/SCP/products/products.php">Products</a></li>
          <?php endif; ?>
          <?php if ($isAdminSec): ?>
            <li class="nav-item"><a class="nav-link <?= $current_page == 'users_manage.php' ? 'active' : '' ?>" href="/SCP/admin/users_manage.php">Staffs</a></li>
            <li class="nav-item"><a class="nav-link <?= $current_page == 'approve.php' ? 'active' : '' ?>" href="/SCP/staff/approve.php">Orders</a></li>
            <li class="nav-item"><a class="nav-link <?= $current_page == 'purchase_records.php' ? 'active' : '' ?>" href="/SCP/admin/purchase_records.php">Purchase Records</a></li>
            <li class="nav-item"><a class="nav-link <?= $current_page == 'audit_log.php' ? 'active' : '' ?>" href="/SCP/admin/audit_log.php">Audit</a></li>
          <?php elseif ($canSeeAudit): ?>
            <li class="nav-item"><a class="nav-link <?= $current_page == 'audit_log.php' ? 'active' : '' ?>" href="/SCP/admin/audit_log.php">Audit</a></li>
          <?php elseif (!$isAdminUser): ?>
            <li class="nav-item"><a class="nav-link <?= $current_page == 'cart.php' ? 'active' : '' ?>" href="/SCP/products/cart.php">Cart</a></li>
          <?php endif; ?>
          <?php if(isset($_SESSION['role']) && $_SESSION['role']=='staff_user'): ?>
            <li class="nav-item"><a class="nav-link <?= $current_page == 'approve.php' ? 'active' : '' ?>" href="/SCP/staff/approve.php">Orders</a></li>
          <?php endif; ?>
          <li class="nav-item ms-2"><a class="nav-link logout-link" href="/SCP/auth/logout.php">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link <?= ($current_page == 'products.php' || $current_page == 'index.php') ? 'active' : '' ?>" href="/SCP/products/products.php">Products</a></li>
          <li class="nav-item"><a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#registerModal">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

 
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="loginModalLabel">Login</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="loginMessage"></div>
        <?php if(isset($_SESSION['login_error'])): ?>
          <div class="alert alert-danger"><?= $_SESSION['login_error'] ?></div>
          <?php unset($_SESSION['login_error']); ?>
        <?php endif; ?>
        <form method="post" action="/SCP/auth/login_handler.php" id="loginForm">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required>
          </div>
          <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="password-wrapper">
              <input type="password" class="form-control" name="password" id="loginPassword" required>
              <span class="password-toggle" onclick="togglePassword('loginPassword', this)"><i class="fa-regular fa-eye"></i></span>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <p class="text-center mt-3 mb-0" style="color: #64748b;">
          Don't have an account? 
          <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" data-bs-dismiss="modal">Register</a>
        </p>
      </div>
    </div>
  </div>
</div>

 
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="registerModalLabel">Create Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="registerMessage"></div>
        <?php if(isset($_SESSION['register_error'])): ?>
          <div class="alert alert-danger"><?= $_SESSION['register_error'] ?></div>
          <?php unset($_SESSION['register_error']); ?>
        <?php endif; ?>
        <?php if(isset($_SESSION['register_success'])): ?>
          <div class="alert alert-success"><?= $_SESSION['register_success'] ?></div>
          <?php unset($_SESSION['register_success']); ?>
        <?php endif; ?>
        <form method="post" action="/SCP/auth/register_handler.php" id="registerForm">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="password-wrapper">
              <input type="password" class="form-control" name="password" id="registerPassword" required onkeyup="checkPasswordStrength()">
              <span class="password-toggle" onclick="togglePassword('registerPassword', this)"><i class="fa-regular fa-eye"></i></span>
            </div>
            <small id="passwordError" class="text-danger" style="display: none;">Password must be at least 8 characters with uppercase, lowercase, number, and special character</small>
          </div>
          <div class="mb-4">
            <label class="form-label">Confirm Password</label>
            <div class="password-wrapper">
              <input type="password" class="form-control" name="confirm_password" id="registerConfirmPassword" required>
              <span class="password-toggle" onclick="togglePassword('registerConfirmPassword', this)"><i class="fa-regular fa-eye"></i></span>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>
        <p class="text-center mt-3 mb-0" style="color: #64748b;">
          Already have an account? 
          <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Login</a>
        </p>
      </div>
    </div>
  </div>
</div>

 
<div class="modal fade" id="otpModal" tabindex="-1" aria-labelledby="otpModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title" id="otpModalLabel">Verify Your Email</h5>
      </div>
      <div class="modal-body">
        <div id="otpMessage"></div>
        <p style="color: #64748b;" id="otpEmailMsg">
          A 6-digit verification code has been sent to <strong id="otpEmailDisplay"></strong>
        </p>
        <form method="post" action="/SCP/auth/verify_otp_handler.php" id="otpForm">
          <div class="d-flex justify-content-center mb-2" id="otpInputs">
            <input type="text" class="form-control otp-input" inputmode="numeric" pattern="\d*" maxlength="1" aria-label="Digit 1">
            <input type="text" class="form-control otp-input" inputmode="numeric" pattern="\d*" maxlength="1" aria-label="Digit 2">
            <input type="text" class="form-control otp-input" inputmode="numeric" pattern="\d*" maxlength="1" aria-label="Digit 3">
            <input type="text" class="form-control otp-input" inputmode="numeric" pattern="\d*" maxlength="1" aria-label="Digit 4">
            <input type="text" class="form-control otp-input" inputmode="numeric" pattern="\d*" maxlength="1" aria-label="Digit 5">
            <input type="text" class="form-control otp-input" inputmode="numeric" pattern="\d*" maxlength="1" aria-label="Digit 6">
          </div>
          <p class="text-center mb-3" style="color: #64748b; font-size: 0.9rem;">Didn't get a code? <a href="#" id="resendOtpLink">resend</a></p>
          <input type="hidden" name="otp_code" id="otpHidden">
          <button type="submit" class="btn btn-primary w-100">Verify email</button>
        </form>
        <p class="text-center mt-3 mb-0" style="color: #64748b; font-size: 0.9rem;">
          Didn't receive the code? <a href="#" id="resendOtpLink">Resend OTP</a>
        </p>
      </div>
    </div>
  </div>
</div>

<style>
  #globalToastContainer {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 1080;
  }
</style>

 
<div id="globalToastContainer" aria-live="polite" aria-atomic="true"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function showToast(message, type = 'danger'){
    const container = document.getElementById('globalToastContainer');
    const id = 'tst-' + Date.now();
    const toastHtml = `
      <div id="${id}" class="toast align-items-center text-bg-${type} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body">${message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>`;
    const wrapper = document.createElement('div');
    wrapper.innerHTML = toastHtml;
    container.appendChild(wrapper.firstElementChild);
    const bsToast = new bootstrap.Toast(document.getElementById(id), { delay: 5000 });
    bsToast.show();
    document.getElementById(id).addEventListener('hidden.bs.toast', function(){
      const el = document.getElementById(id);
      if(el) el.remove();
    });
  }

  function togglePassword(inputId, iconElement) {
    const input = document.getElementById(inputId);
    const icon = iconElement.querySelector('i');
    if (!icon) return;
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }

  function checkPasswordStrength() {
    const password = document.getElementById('registerPassword').value;
    const errorMsg = document.getElementById('passwordError');
    
    const hasLength = password.length >= 8;
    const hasUppercase = /[A-Z]/.test(password);
    const hasLowercase = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    
    const isValid = hasLength && hasUppercase && hasLowercase && hasNumber && hasSpecial;
    
    if (password.length > 0 && !isValid) {
      errorMsg.style.display = 'block';
    } else {
      errorMsg.style.display = 'none';
    }
  }

  function updateRequirement(elementId, isMet) {
    const element = document.getElementById(elementId);
    if (isMet) {
      element.className = 'requirement-met';
      element.innerHTML = '✓ ' + element.textContent.substring(2);
    } else {
      element.className = 'requirement-unmet';
      element.innerHTML = '✗ ' + element.textContent.substring(2);
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    const loginForm = document.getElementById('loginForm');
    if(loginForm){
      loginForm.addEventListener('submit', function(e){
        e.preventDefault();
        const formData = new FormData(loginForm);
        fetch(loginForm.action, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        }).then(r => r.json())
        .then(data => {
          if(data.success){
            window.location = data.redirect || '/SCP/products/products.php';
          } else if(data.require_otp) {
            const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
            if(loginModal) loginModal.hide();
            document.getElementById('otpEmailDisplay').textContent = 'your email';
            const otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
            otpModal.show();
            showToast(data.message || 'Please verify your OTP', 'warning');
          } else {
            showToast(data.message || 'Login failed', 'danger');
          }
        }).catch(err => {
          showToast('An error occurred. Please try again.', 'danger');
        });
      });
    }

    const registerForm = document.getElementById('registerForm');
    if(registerForm){
      registerForm.addEventListener('submit', function(e){
        e.preventDefault();
        const regBtn = registerForm.querySelector('button[type="submit"]');
        const originalBtnHtml = regBtn ? regBtn.innerHTML : '';
        if (regBtn) {
          regBtn.disabled = true;
          regBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Registering...';
        }
        const formData = new FormData(registerForm);
        fetch(registerForm.action, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        }).then(r => r.json())
        .then(data => {
          if(data.success){
            const regModal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
            if(regModal) regModal.hide();
            document.getElementById('otpEmailDisplay').textContent = data.email || 'your email';
            const otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
            otpModal.show();
            showToast(data.message || 'OTP sent successfully!', 'success');
          } else {
            showToast(data.message || 'Registration failed', 'danger');
          }
        }).catch(err => {
          showToast('An error occurred. Please try again.', 'danger');
        }).finally(() => {
          if (regBtn) {
            regBtn.disabled = false;
            regBtn.innerHTML = originalBtnHtml || 'Register';
          }
        });
      });
    }

    const otpForm = document.getElementById('otpForm');
    const otpInputsContainer = document.getElementById('otpInputs');
    const otpHidden = document.getElementById('otpHidden');
    if (otpInputsContainer) {
      const inputs = otpInputsContainer.querySelectorAll('.otp-input');
      inputs.forEach((input, idx) => {
        input.addEventListener('input', (e) => {
          e.target.value = e.target.value.replace(/\D/g, '');
          if (e.target.value.length === 1 && idx < inputs.length - 1) {
            inputs[idx + 1].focus();
          }
          otpHidden.value = Array.from(inputs).map(i => i.value || '').join('');
        });
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Backspace' && !e.target.value && idx > 0) {
            inputs[idx - 1].focus();
          }
        });
        input.addEventListener('paste', (e) => {
          const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
          if (text.length === inputs.length) {
            e.preventDefault();
            for (let i = 0; i < inputs.length; i++) {
              inputs[i].value = text[i];
            }
            otpHidden.value = text;
          }
        });
      });
    }
    if(otpForm){
      otpForm.addEventListener('submit', function(e){
        e.preventDefault();
        if (otpInputsContainer) {
          const inputs = otpInputsContainer.querySelectorAll('.otp-input');
          otpHidden.value = Array.from(inputs).map(i => i.value || '').join('');
        }
        if (!otpHidden.value || otpHidden.value.length !== 6 || /\D/.test(otpHidden.value)) {
          showToast('Please enter a valid 6-digit code.', 'warning');
          return;
        }
        const formData = new FormData(otpForm);
        fetch(otpForm.action, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        }).then(r => r.json())
        .then(data => {
          if(data.success){
            showToast('Email verified successfully! Redirecting...', 'success');
            setTimeout(() => {
              window.location = data.redirect || '/SCP/products/products.php';
            }, 1500);
          } else {
            showToast(data.message || 'Invalid OTP', 'danger');
          }
        }).catch(err => {
          showToast('An error occurred. Please try again.', 'danger');
        });
      });
    }

    const resendLink = document.getElementById('resendOtpLink');
    if(resendLink){
      resendLink.addEventListener('click', function(e){
        e.preventDefault();
        fetch('/SCP/auth/resend_otp.php', {
          method: 'POST',
          credentials: 'same-origin'
        }).then(r => r.json())
        .then(data => {
          if(data.success){
            showToast('OTP has been resent to your email', 'success');
          } else {
            showToast(data.message || 'Failed to resend OTP', 'danger');
          }
        }).catch(err => {
          showToast('An error occurred. Please try again.', 'danger');
        });
      });
    }

    <?php if(isset($_SESSION['login_error'])): ?>
      var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
      loginModal.show();
    <?php endif; ?>
    <?php if(isset($_SESSION['register_error'])): ?>
      var registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
      registerModal.show();
    <?php endif; ?>
    <?php if(isset($_SESSION['show_otp_modal'])): ?>
      document.getElementById('otpEmailDisplay').textContent = '<?= $_SESSION['otp_email'] ?? 'your email' ?>';
      var otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
      otpModal.show();
      <?php unset($_SESSION['show_otp_modal']); ?>
    <?php endif; ?>
  });
</script>

<div class="container">