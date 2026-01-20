<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Process form actions BEFORE any output
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    $product_name = $_POST['product_name'] ?? 'Unknown Product';
    $product_price = floatval($_POST['product_price'] ?? 0);
    $product_image = $_POST['product_image'] ?? '';
    
    if ($product_id > 0 && isset($_SESSION['user_id'])) {
        if ($quantity < 1) $quantity = 1;
        
        // Save to database
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, product_name, product_price, product_image, quantity) 
                                VALUES (?, ?, ?, ?, ?, ?) 
                                ON DUPLICATE KEY UPDATE quantity = quantity + ?, product_name = ?, product_price = ?, product_image = ?");
        $stmt->bind_param('iisdsissds', 
            $_SESSION['user_id'], $product_id, $product_name, $product_price, $product_image, $quantity,
            $quantity, $product_name, $product_price, $product_image);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle remove from cart
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_item'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param('ii', $_SESSION['user_id'], $product_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: /SCP/products/cart.php');
    exit();
}

// Handle update quantity
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_quantity'])) {
    $product_id = intval($_POST['product_id'] ?? 0);
    $new_quantity = intval($_POST['quantity'] ?? 1);
    
    if (isset($_SESSION['user_id']) && $new_quantity > 0) {
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param('iii', $new_quantity, $_SESSION['user_id'], $product_id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: /SCP/products/cart.php');
    exit();
}

if (isset($_POST['checkout'])) {
    if (isset($_SESSION['user_id'])) {
        // Get cart items from database
        $stmt = $conn->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($item = $result->fetch_assoc()) {
            $stmt2 = $conn->prepare("INSERT INTO purchases (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt2->bind_param('iii', $_SESSION['user_id'], $item['product_id'], $item['quantity']);
            $stmt2->execute();
            $stmt2->close();
        }
        $stmt->close();
        
        // Clear cart from database
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        
        log_action($conn, $_SESSION['user_id'], "Purchase created, awaiting staff approval");
    }
}

// Load cart from database
$cart_items = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT product_id, product_name, product_price, product_image, quantity FROM cart WHERE user_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cart_items[$row['product_id']] = [
            'name' => $row['product_name'],
            'price' => $row['product_price'],
            'image' => $row['product_image'],
            'quantity' => $row['quantity']
        ];
    }
    $stmt->close();
}

// Now include header after processing
include '../includes/header.php';
?>

<div class="page-header">
  <h2>Your Cart</h2>
</div>

<?php if (empty($cart_items)): ?>
  <div class="card">
    <div class="card-body text-center py-5">
      <p style="color: #64748b; margin-bottom: 1rem;">Your cart is empty.</p>
      <a href="/SCP/products/products.php" class="btn btn-primary">Browse Products</a>
    </div>
  </div>
<?php else: ?>
  <style>
    .quantity-control {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .quantity-control .btn-qty {
      width: 32px;
      height: 32px;
      padding: 0;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #3b82f6;
      background: white;
      color: #3b82f6;
      transition: all 0.2s ease;
      font-weight: 600;
    }
    .qty-display {
      min-width: 40px;
      text-align: center;
      font-weight: 600;
      font-size: 1.1rem;
      color: #1e293b;
    }
    .btn-remove {
      color: #dc2626;
      transition: all 0.2s ease;
    }
    .btn-remove:hover {
      color: #b91c1c;
      transform: scale(1.1);
    }
  </style>
  
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr><th>Product</th><th>Price</th><th>Quantity</th><th>Subtotal</th><th></th></tr>
      </thead>
      <tbody>
        <?php 
        $total = 0;
        foreach ($cart_items as $pid => $item):
          $name = htmlspecialchars($item['name'] ?? 'Unknown Product');
          $price = $item['price'] ?? 0;
          $qty = $item['quantity'] ?? 1;
          $image = $item['image'] ?? '';
          $subtotal = $price * $qty;
          $total += $subtotal;
        ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-3">
              <?php if ($image): ?>
              <img src="<?= htmlspecialchars($image) ?>" alt="<?= $name ?>" style="width: 60px; height: 60px; object-fit: contain; border-radius: 0.5rem; border: 1px solid #e2e8f0; padding: 0.25rem;">
              <?php endif; ?>
              <span><strong><?= $name ?></strong></span>
            </div>
          </td>
          <td><span class="text-muted">$<?= number_format($price, 2) ?></span></td>
          <td>
            <form method="post" class="quantity-control" onsubmit="return false;">
              <button type="button" class="btn btn-qty" onclick="updateQuantity(<?= $pid ?>, <?= $qty ?> - 1)">
                <i class="fas fa-minus"></i>
              </button>
              <span class="qty-display"><?= $qty ?></span>
              <button type="button" class="btn btn-qty" onclick="updateQuantity(<?= $pid ?>, <?= $qty ?> + 1)">
                <i class="fas fa-plus"></i>
              </button>
            </form>
          </td>
          <td><strong class="text-primary">$<?= number_format($subtotal, 2) ?></strong></td>
          <td>
            <form method="post" style="margin: 0;">
              <input type="hidden" name="product_id" value="<?= $pid ?>">
              <button type="submit" name="remove_item" class="btn btn-link btn-remove p-0" title="Remove from cart">
                <i class="fas fa-trash-alt fa-lg"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" class="text-end"><strong style="font-size: 1.1rem;">Total:</strong></td>
          <td colspan="2"><strong class="text-primary" style="font-size: 1.5rem;">$<?= number_format($total, 2) ?></strong></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <script>
    function updateQuantity(productId, newQty) {
      if (newQty < 1) newQty = 1;
      if (newQty > 99) newQty = 99;
      
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '';
      
      const pidInput = document.createElement('input');
      pidInput.type = 'hidden';
      pidInput.name = 'product_id';
      pidInput.value = productId;
      
      const qtyInput = document.createElement('input');
      qtyInput.type = 'hidden';
      qtyInput.name = 'quantity';
      qtyInput.value = newQty;
      
      const updateInput = document.createElement('input');
      updateInput.type = 'hidden';
      updateInput.name = 'update_quantity';
      updateInput.value = '1';
      
      form.appendChild(pidInput);
      form.appendChild(qtyInput);
      form.appendChild(updateInput);
      document.body.appendChild(form);
      form.submit();
    }
  </script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>