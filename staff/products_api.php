<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if (!in_array($_SESSION['role'], ['staff_user','administrator','admin_sec'], true)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Ensure the products table has a display_order column for stable ordering
function ensureDisplayOrderColumn(mysqli $conn): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $colRes = $conn->query("SHOW COLUMNS FROM products LIKE 'display_order'");
    if ($colRes && $colRes->num_rows > 0) {
        return;
    }

    // Add the column and initialize with a sensible default
    $conn->query("ALTER TABLE products ADD COLUMN display_order INT NOT NULL DEFAULT 0");
    // Initialize order to current id ordering to avoid all zeros
    $conn->query("UPDATE products SET display_order = id WHERE display_order = 0 OR display_order IS NULL");
}

switch ($action) {
    case 'list':
        listProducts($conn);
        break;
    case 'get':
        getProduct($conn);
        break;
    case 'add':
        addProduct($conn, $_SESSION['user_id']);
        break;
    case 'update':
        updateProduct($conn, $_SESSION['user_id']);
        break;
    case 'delete':
        deleteProduct($conn, $_SESSION['user_id']);
        break;
    case 'reorder':
        reorderProducts($conn, $_SESSION['user_id']);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function listProducts($conn) {
    ensureDisplayOrderColumn($conn);
    // Show by configured display order, then id for stability
    $result = $conn->query("SELECT * FROM products ORDER BY display_order ASC, id ASC");
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        return;
    }
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode(['success' => true, 'products' => $products]);
}

function getProduct($conn) {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        return;
    }
    echo json_encode(['success' => true, 'product' => $product]);
}

function addProduct($conn, $user_id) {
    ensureDisplayOrderColumn($conn);
    $title = trim($_POST['title'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image = trim($_POST['image'] ?? '');

    if (empty($title) || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Title and price are required']);
        return;
    }

    // Determine display order so the new product appears at the top
    // Use the current minimum display_order and subtract 1
    $minRes = $conn->query("SELECT COALESCE(MIN(display_order), 1) AS min_order FROM products");
    $row = $minRes ? $minRes->fetch_assoc() : ['min_order' => 1];
    $nextOrder = (int)($row['min_order'] ?? 1) - 1;

    $stmt = $conn->prepare("INSERT INTO products (title, price, category, description, image, display_order) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sdsssi', $title, $price, $category, $description, $image, $nextOrder);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $stmt->close();
        log_action($conn, $user_id, "Added product: $title (ID: $new_id)");
        echo json_encode(['success' => true, 'message' => 'Product added successfully', 'product' => ['id' => $new_id, 'title' => $title, 'price' => $price, 'category' => $category, 'description' => $description, 'image' => $image]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add product: ' . $conn->error]);
        $stmt->close();
    }
}

function updateProduct($conn, $user_id) {
    $id = intval($_POST['product_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image = trim($_POST['image'] ?? '');

    if ($id <= 0 || empty($title) || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        return;
    }

    $stmt = $conn->prepare("UPDATE products SET title = ?, price = ?, category = ?, description = ?, image = ? WHERE id = ?");
    $stmt->bind_param('sdsssi', $title, $price, $category, $description, $image, $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        log_action($conn, $user_id, "Updated product ID $id: $title");
        echo json_encode(['success' => true, 'message' => 'Product updated successfully', 'product' => ['id' => $id, 'title' => $title, 'price' => $price, 'category' => $category, 'description' => $description, 'image' => $image]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update product: ' . $conn->error]);
        $stmt->close();
    }
}

function deleteProduct($conn, $user_id) {
    $id = intval($_POST['product_id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT title FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        log_action($conn, $user_id, "Deleted product ID $id: " . $product['title']);
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete product: ' . $conn->error]);
        $stmt->close();
    }
}

function reorderProducts($conn, $user_id) {
    ensureDisplayOrderColumn($conn);
    $orderJson = $_POST['order'] ?? '';
    
    $order = json_decode($orderJson, true);
    
    if (!is_array($order) || empty($order)) {
        echo json_encode(['success' => false, 'message' => 'Invalid order data']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $stmt = $conn->prepare("UPDATE products SET display_order = ? WHERE id = ?");
        
        foreach ($order as $index => $product_id) {
            $product_id = intval($product_id);
            $display_order = $index + 1;
            $stmt->bind_param('ii', $display_order, $product_id);
            $stmt->execute();
        }
        
        $stmt->close();
        $conn->commit();
        
        log_action($conn, $user_id, "Reordered products");
        echo json_encode(['success' => true, 'message' => 'Products reordered successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to reorder products: ' . $e->getMessage()]);
    }
}
?>
