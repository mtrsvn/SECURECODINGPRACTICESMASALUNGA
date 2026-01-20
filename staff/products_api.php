<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}
if ($_SESSION['role'] !== 'staff_user' && $_SESSION['role'] !== 'administrator') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

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
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function listProducts($conn) {
    $result = $conn->query("SELECT * FROM products ORDER BY id DESC");
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
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image = trim($_POST['image'] ?? '');

    if (empty($name) || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Name and price are required']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO products (name, price, category, description, image) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sdsss', $name, $price, $category, $description, $image);
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        $stmt->close();
        log_action($conn, $user_id, "Added product: $name (ID: $new_id)");
        echo json_encode(['success' => true, 'message' => 'Product added successfully', 'product' => ['id' => $new_id, 'name' => $name, 'price' => $price, 'category' => $category, 'description' => $description, 'image' => $image]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add product: ' . $conn->error]);
        $stmt->close();
    }
}

function updateProduct($conn, $user_id) {
    $id = intval($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image = trim($_POST['image'] ?? '');

    if ($id <= 0 || empty($name) || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        return;
    }

    $stmt = $conn->prepare("UPDATE products SET name = ?, price = ?, category = ?, description = ?, image = ? WHERE id = ?");
    $stmt->bind_param('sdsssi', $name, $price, $category, $description, $image, $id);
    
    if ($stmt->execute()) {
        $stmt->close();
        log_action($conn, $user_id, "Updated product ID $id: $name");
        echo json_encode(['success' => true, 'message' => 'Product updated successfully', 'product' => ['id' => $id, 'name' => $name, 'price' => $price, 'category' => $category, 'description' => $description, 'image' => $image]]);
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
    
    $stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
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
        log_action($conn, $user_id, "Deleted product ID $id: " . $product['name']);
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete product: ' . $conn->error]);
        $stmt->close();
    }
}
?>
