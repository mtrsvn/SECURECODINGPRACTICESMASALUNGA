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

// Base URL for FakeStoreAPI
$FAKESTORE_BASE = 'https://fakestoreapi.com';

function fakestore_request(string $method, string $path, $payload = null) {
    global $FAKESTORE_BASE;
    $url = rtrim($FAKESTORE_BASE, '/') . '/' . ltrim($path, '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    $headers = [ 'Accept: application/json' ];
    if ($payload !== null) {
        $json = json_encode($payload);
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return [ 'ok' => false, 'status' => 0, 'error' => $err ];
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($resp, true);
    if ($status >= 200 && $status < 300) {
        return [ 'ok' => true, 'status' => $status, 'data' => $data ];
    }
    return [ 'ok' => false, 'status' => $status, 'data' => $data ];
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
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function listProducts($conn) {
    $res = fakestore_request('GET', '/products');
    if ($res['ok']) {
        echo json_encode(['success' => true, 'products' => $res['data']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch products from FakeStoreAPI', 'status' => $res['status']]);
    }
}

function getProduct($conn) {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        return;
    }
    $res = fakestore_request('GET', "/products/$id");
    if ($res['ok']) {
        echo json_encode(['success' => true, 'product' => $res['data']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found on FakeStoreAPI', 'status' => $res['status']]);
    }
}

function addProduct($conn, $user_id) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $image = trim($_POST['image'] ?? '');

    if ($name === '' || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product data']);
        return;
    }

    $payload = [
        'title' => $name,
        'price' => $price,
        'description' => $description,
        'image' => $image,
        'category' => $category
    ];
    $res = fakestore_request('POST', '/products', $payload);
    if ($res['ok']) {
        if (function_exists('log_action')) { log_action($conn, $user_id, "Added product via FakeStoreAPI: $name"); }
        echo json_encode(['success' => true, 'message' => 'Product added (FakeStoreAPI)', 'product' => $res['data']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding product via FakeStoreAPI', 'status' => $res['status'], 'details' => $res['data'] ?? null]);
    }
}

function updateProduct($conn, $user_id) {
    $id = intval($_POST['product_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $image = trim($_POST['image'] ?? '');

    if ($id <= 0 || $name === '' || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product data']);
        return;
    }

    $payload = [
        'title' => $name,
        'price' => $price,
        'description' => $description,
        'image' => $image,
        'category' => $category
    ];
    $res = fakestore_request('PUT', "/products/$id", $payload);
    if ($res['ok']) {
        if (function_exists('log_action')) { log_action($conn, $user_id, "Updated product via FakeStoreAPI: ID $id ($name)"); }
        echo json_encode(['success' => true, 'message' => 'Product updated (FakeStoreAPI)', 'product' => $res['data']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating product via FakeStoreAPI', 'status' => $res['status'], 'details' => $res['data'] ?? null]);
    }
}

function deleteProduct($conn, $user_id) {
    $id = intval($_POST['product_id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        return;
    }

    $res = fakestore_request('DELETE', "/products/$id");
    if ($res['ok']) {
        if (function_exists('log_action')) { log_action($conn, $user_id, "Deleted product via FakeStoreAPI: ID $id"); }
        echo json_encode(['success' => true, 'message' => 'Product deleted (FakeStoreAPI)', 'product' => $res['data']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting product via FakeStoreAPI', 'status' => $res['status'], 'details' => $res['data'] ?? null]);
    }
}
?>
