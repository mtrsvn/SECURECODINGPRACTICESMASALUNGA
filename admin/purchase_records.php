<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
include '../includes/header.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin_sec') {
    include '../includes/footer.php';
    exit();
}

$status = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

$statusMap = [
    'pending' => 0,
    'approved' => 1,
    'rejected' => 2,
];

$sql = "SELECT 
    u.id AS user_id,
    u.username,
    u.email,
    DATE_FORMAT(p.created_at, '%Y-%m-%d %H:%i:%s') AS order_time,
    p.approved,
    SUM(p.product_price * p.quantity) AS total_amount,
    SUM(p.quantity) AS total_qty,
    GROUP_CONCAT(CONCAT(COALESCE(prod.title, p.product_name), ' x', p.quantity) ORDER BY p.id SEPARATOR ', ') AS items
  FROM purchases p
  JOIN users u ON p.user_id = u.id
  LEFT JOIN products prod ON p.product_id = prod.id";

$params = [];
$types = '';
if ($status !== 'all') {
    $sql .= ' WHERE p.approved = ?';
    $types = 'i';
    $params[] = $statusMap[$status];
}

$sql .= ' GROUP BY u.id, u.username, u.email, order_time, p.approved ORDER BY order_time DESC';
$stmt = $conn->prepare($sql);
if ($types !== '' && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$records = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();

function render_status_badge($approved) {
    $approved = (int)$approved;
    if ($approved === 1) {
        return '<span class="badge status-badge status-approved">Approved</span>';
    } elseif ($approved === 2) {
        return '<span class="badge status-badge status-rejected">Rejected</span>';
    }
    return '<span class="badge status-badge status-pending">Pending</span>';
}
?>

<div class="page-header d-flex align-items-center justify-content-between">
  <h2>Purchase Records</h2>
  <form class="d-flex align-items-center gap-2" method="get" action="">
    <label for="status" class="form-label mb-0">Status</label>
    <select name="status" id="status" class="form-select" style="width: 180px;" onchange="this.form.submit()">
      <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
      <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
      <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
    </select>
  </form>
</div>

<?php if (empty($records)): ?>
  <div class="card">
    <div class="card-body text-center py-4">No purchase records found for this filter.</div>
  </div>
<?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th style="min-width: 150px;">Order Time</th>
          <th>User</th>
          <th>Email</th>
          <th>Items</th>
          <th class="text-center">Qty</th>
          <th class="text-end">Total</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($records as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['order_time'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['username'] ?? 'Unknown User') ?></td>
          <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['items'] ?? 'No items') ?></td>
          <td class="text-center"><?= (int)($row['total_qty'] ?? 0) ?></td>
          <td class="text-end">$<?= number_format((float)($row['total_amount'] ?? 0), 2) ?></td>
          <td><?= render_status_badge($row['approved'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<style>
.status-badge {
  border-radius: 999px;
  padding: 0.35rem 0.7rem;
  font-size: 0.85rem;
}
.status-approved {
  background: #dcfce7;
  color: #166534;
}
.status-pending {
  background: #fef3c7;
  color: #92400e;
}
.status-rejected {
  background: #fee2e2;
  color: #991b1b;
}
</style>

<?php include '../includes/footer.php'; ?>
