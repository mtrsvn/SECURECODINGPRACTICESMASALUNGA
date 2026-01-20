<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
include '../includes/header.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin_sec') {
    include '../includes/footer.php';
    exit();
}

$messages = [];
$errors = [];

function validate_password_rules($password, &$error)
{
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
        return false;
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $error = 'Password must contain at least one special character.';
        return false;
    }
    return true;
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $errors[] = 'Username, email, and password are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirmation do not match.';
    }
    if ($password !== '') {
        $msg = '';
        if (!validate_password_rules($password, $msg)) {
            $errors[] = $msg;
        }
    }

    if (empty($errors)) {
        $check = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
        $check->bind_param('ss', $username, $email);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
          $errors[] = 'Duplicate account found.';
        }
        $check->close();
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role = 'staff_user';
        $stmt = $conn->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $username, $email, $hash, $role);
        if ($stmt->execute()) {
            $messages[] = 'Staff user created successfully.';
            if (function_exists('log_action') && isset($_SESSION['user_id'])) {
                log_action($conn, $_SESSION['user_id'], 'Created staff user: ' . $username);
            }
        } else {
            $errors[] = 'Failed to create user. The username or email might already be in use.';
        }
        $stmt->close();
    }
}

if ($action === 'update') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';

    if ($userId <= 0) {
        $errors[] = 'Invalid user ID.';
    }

    $current = null;
    if (empty($errors)) {
        $fetch = $conn->prepare("SELECT id, username, email FROM users WHERE id = ? AND role = 'staff_user' LIMIT 1");
        $fetch->bind_param('i', $userId);
        $fetch->execute();
        $result = $fetch->get_result();
        $current = $result->fetch_assoc();
        $fetch->close();
        if (!$current) {
            $errors[] = 'Staff user not found.';
        }
    }

    if ($username === '' || $email === '') {
        $errors[] = 'Username and email are required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if ($newPassword !== '') {
        $msg = '';
        if (!validate_password_rules($newPassword, $msg)) {
            $errors[] = $msg;
        }
    }

    if (empty($errors)) {
        $check = $conn->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
        $check->bind_param('ssi', $username, $email, $userId);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
          $errors[] = 'Duplicate account found.';
        }
        $check->close();
    }

    if (empty($errors)) {
        if ($newPassword !== '') {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET username = ?, email = ?, password_hash = ? WHERE id = ? AND role = "staff_user"');
            $stmt->bind_param('sssi', $username, $email, $hash, $userId);
        } else {
            $stmt = $conn->prepare('UPDATE users SET username = ?, email = ? WHERE id = ? AND role = "staff_user"');
            $stmt->bind_param('ssi', $username, $email, $userId);
        }
        if ($stmt->execute()) {
            $messages[] = 'Staff user updated successfully.';
            if (function_exists('log_action') && isset($_SESSION['user_id'])) {
                log_action($conn, $_SESSION['user_id'], 'Updated staff user: ' . $username);
            }
        } else {
            $errors[] = 'Failed to update user.';
        }
        $stmt->close();
    }
}

if ($action === 'delete') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        $errors[] = 'Invalid user ID.';
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'staff_user'");
        $stmt->bind_param('i', $userId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $messages[] = 'Staff user deleted successfully.';
            if (function_exists('log_action') && isset($_SESSION['user_id'])) {
                log_action($conn, $_SESSION['user_id'], 'Deleted staff user ID: ' . $userId);
            }
        } else {
            $errors[] = 'Failed to delete user. The account may not exist or cannot be removed.';
        }
        $stmt->close();
    }
}

$listStmt = $conn->prepare("SELECT id, username, email, role, lockout_until, failed_logins FROM users WHERE role = 'staff_user' ORDER BY id DESC");
$listStmt->execute();
$staffUsers = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$listStmt->close();
?>

<div class="page-header d-flex align-items-center justify-content-between">
  <h2>Staff User Management</h2>
</div>

<?php /* Status indicators intentionally removed per request. */ ?>

<div class="card">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="card-title mb-0">Existing Staff Users</h5>
      <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#staffCreateModal" style="font-size: 1.1rem; padding: 0.5rem 0.9rem;">
        <span aria-hidden="true" style="font-size: 1.35rem; line-height: 1;">＋</span>
        <span class="fw-semibold" style="line-height: 1;">Add</span>
      </button>
    </div>

    <div class="modal fade" id="staffCreateModal" tabindex="-1" aria-labelledby="staffCreateLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="staffCreateLabel">Add Staff User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="post">
            <div class="modal-body row g-3">
              <input type="hidden" name="action" value="create">
              <div class="col-12">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
              </div>
              <div class="col-12">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Add</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php if (empty($staffUsers)): ?>
      <div class="alert alert-info mb-0">No staff users found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($staffUsers as $user): ?>
            <tr>
              <td><?= (int)$user['id'] ?></td>
              <td>
                <input type="text" name="username" class="form-control form-control-sm" value="<?= htmlspecialchars($user['username']) ?>" placeholder="Username" required form="update-form-<?= (int)$user['id'] ?>">
              </td>
              <td>
                <input type="email" name="email" class="form-control form-control-sm" value="<?= htmlspecialchars($user['email']) ?>" placeholder="Email" required form="update-form-<?= (int)$user['id'] ?>">
              </td>
              <td>
                <div class="staff-action-row">
                  <form id="update-form-<?= (int)$user['id'] ?>" method="post" class="inline-form">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                    <input type="password" name="new_password" class="form-control password-input" placeholder="New password (optional)">
                  </form>
                  <div class="staff-button-group">
                    <button type="submit" form="update-form-<?= (int)$user['id'] ?>" class="btn staff-btn staff-btn-save">Save</button>
                    <button type="submit" form="delete-form-<?= (int)$user['id'] ?>" class="btn staff-btn staff-btn-delete" onclick="return confirm('Delete this staff user? This cannot be undone.');">Delete</button>
                  </div>
                  <form id="delete-form-<?= (int)$user['id'] ?>" method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<style>
.staff-btn {
  min-width: 96px;
  height: 40px;
  border-radius: 10px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-width: 1px;
}
.staff-btn-save {
  background-color: #2563eb;
  border-color: #2563eb;
  color: #fff;
}
.staff-btn-save:hover {
  background-color: #1d4ed8;
  border-color: #1d4ed8;
  color: #fff;
}
.staff-btn-delete {
  background-color: #dc3545;
  border-color: #dc3545;
  color: #fff;
}
.staff-btn-delete:hover {
  background-color: #bb2d3b;
  border-color: #bb2d3b;
  color: #fff;
}
.staff-action-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: nowrap;
  width: 100%;
  justify-content: flex-end;
}
.staff-action-row form {
  margin: 0;
}
.inline-form {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 6px;
  flex: 1 1 auto;
  min-width: 0;
}
.inline-form .form-control {
  flex: 1 1 120px;
  max-width: 160px;
  width: auto;
  min-width: 0;
}
.inline-form .password-input {
  flex: 1 1 220px;
  max-width: 320px;
}
.staff-button-group {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
}
@media (max-width: 992px) {
  .staff-action-row,
  .inline-form {
    flex-wrap: wrap;
  }
}
</style>
