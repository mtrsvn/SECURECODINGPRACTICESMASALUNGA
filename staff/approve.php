<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
include '../includes/header.php';

if(!isset($_SESSION['role']) || ($_SESSION['role'] != 'staff_user' && $_SESSION['role'] != 'admin_sec')) {
    include '../includes/footer.php';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['purchase_id'])) {
  $pid = intval($_POST['purchase_id']);
  $stmt = $conn->prepare("UPDATE purchases SET approved=1 WHERE id=?");
  $stmt->bind_param("i", $pid);
  $stmt->execute();
  $stmt->close();

  require_once '../includes/mailer.php';

  $detailStmt = $conn->prepare(
    "SELECT p.id, p.user_id, p.product_name, p.product_price, p.quantity, u.email, u.username
     FROM purchases p
     JOIN users u ON p.user_id = u.id
     WHERE p.id = ?"
  );
  $detailStmt->bind_param('i', $pid);
  $detailStmt->execute();
  $detailRes = $detailStmt->get_result();
  $purchase = $detailRes->fetch_assoc();
  $detailStmt->close();

  if ($purchase && !empty($purchase['email'])) {
    $items = [[
      'name' => $purchase['product_name'],
      'quantity' => (int)$purchase['quantity'],
      'price' => (float)$purchase['product_price']
    ]];
    $total = (float)$purchase['product_price'] * (int)$purchase['quantity'];
    send_purchase_confirmation_email($purchase['email'], $purchase['username'] ?? $purchase['email'], $items, $total);
    if (function_exists('log_action')) {
      log_action($conn, (int)$purchase['user_id'], 'Order item approved and email sent');
    }
    $_SESSION['admin_toast'] = [
      'message' => 'Order approved and customer notified.',
      'type' => 'success'
    ];
  }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_id'])) {
  $rid = intval($_POST['reject_id']);
  $stmt = $conn->prepare("UPDATE purchases SET approved=2 WHERE id=?");
  $stmt->bind_param("i", $rid);
  $stmt->execute();
  $stmt->close();

  require_once '../includes/mailer.php';
  $detailStmt = $conn->prepare(
    "SELECT p.id, p.user_id, p.product_name, p.product_price, p.quantity, u.email, u.username
     FROM purchases p
     JOIN users u ON p.user_id = u.id
     WHERE p.id = ?"
  );
  $detailStmt->bind_param('i', $rid);
  $detailStmt->execute();
  $detailRes = $detailStmt->get_result();
  $purchase = $detailRes->fetch_assoc();
  $detailStmt->close();

  if ($purchase && !empty($purchase['email'])) {
    $items = [[
      'name' => $purchase['product_name'],
      'quantity' => (int)$purchase['quantity'],
      'price' => (float)$purchase['product_price']
    ]];
    $total = (float)$purchase['product_price'] * (int)$purchase['quantity'];
    send_purchase_rejection_email($purchase['email'], $purchase['username'] ?? $purchase['email'], $items, $total);
    if (function_exists('log_action')) {
      log_action($conn, (int)$purchase['user_id'], 'Order item rejected and email sent');
    }
    $_SESSION['admin_toast'] = [
      'message' => 'Order rejected and customer notified.',
      'type' => 'warning'
    ];
  }
}

$res = $conn->query("SELECT purchases.*, users.username, products.name AS product_name FROM purchases 
LEFT JOIN users ON purchases.user_id=users.id
LEFT JOIN products ON purchases.product_id=products.id
WHERE purchases.approved=0");
?>

<div class="page-header">
  <h2>Pending Orders</h2>
</div>

<table class="table">
  <thead>
    <tr><th>User</th><th>Product</th><th>Qty</th><th>Action</th></tr>
  </thead>
  <tbody>
    <?php while($row=$res->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($row['username']) ?></td>
      <td><?= htmlspecialchars($row['product_name']) ?></td>
      <td><?= $row['quantity'] ?></td>
      <td>
        <div class="d-flex gap-2">
          <form method="post" style="margin:0;">
            <input type="hidden" name="purchase_id" value="<?= $row['id'] ?>">
            <button type="submit" class="btn btn-primary btn-sm">Approve</button>
          </form>
          <form method="post" style="margin:0;">
            <input type="hidden" name="reject_id" value="<?= $row['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
          </form>
        </div>
      </td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<?php if (!empty($_SESSION['admin_toast'])): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      try {
        var msg = <?php echo json_encode($_SESSION['admin_toast']['message']); ?>;
        var type = <?php echo json_encode($_SESSION['admin_toast']['type']); ?>;
        if (typeof showToast === 'function') {
          showToast(msg, type);
        }
      } catch(e) {}
    });
  </script>
  <?php unset($_SESSION['admin_toast']); ?>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>