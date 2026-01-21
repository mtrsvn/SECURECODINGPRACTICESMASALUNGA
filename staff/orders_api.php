<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';

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
        listOrders($conn);
        break;
    case 'get':
        getOrder($conn);
        break;
    case 'approve':
        approveOrder($conn, $_SESSION['user_id']);
        break;
    case 'reject':
        rejectOrder($conn, $_SESSION['user_id']);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function listOrders($conn) {
    $status = $_GET['status'] ?? 'all';
    
    if ($status === 'all') {
        $query = "SELECT po.*, u.username, u.email as user_email 
                  FROM purchase_orders po 
                  LEFT JOIN users u ON po.user_id = u.id 
                  ORDER BY po.created_at DESC";
        $result = $conn->query($query);
    } else {
        $stmt = $conn->prepare("SELECT po.*, u.username, u.email as user_email 
                                FROM purchase_orders po 
                                LEFT JOIN users u ON po.user_id = u.id 
                                WHERE po.status = ? 
                                ORDER BY po.created_at DESC");
        $stmt->bind_param("s", $status);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $items = json_decode($row['items'] ?? '[]', true);
        $row['item_count'] = is_array($items) ? count($items) : 0;
        $orders[] = $row;
    }
    
    echo json_encode(['success' => true, 'orders' => $orders]);
}

function getOrder($conn) {
    $id = intval($_GET['id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT po.*, u.username, u.email as user_email 
                            FROM purchase_orders po 
                            LEFT JOIN users u ON po.user_id = u.id 
                            WHERE po.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    
    if ($order) {
        echo json_encode(['success' => true, 'order' => $order]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
    }
}

function approveOrder($conn, $user_id) {
    $order_id = intval($_POST['order_id'] ?? 0);
    
    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT po.*, u.username, u.email 
                            FROM purchase_orders po 
                            LEFT JOIN users u ON po.user_id = u.id 
                            WHERE po.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }
    
    if ($order['status'] === 'approved') {
        echo json_encode(['success' => false, 'message' => 'Order is already approved']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE purchase_orders SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $user_id, $order_id);
    
    if ($stmt->execute()) {
        log_action($conn, $user_id, "Approved purchase order #$order_id");

        // Send approval email using helper
        $customer_email = $order['email'] ?? '';
        if (!empty($customer_email)) {
            $customer_name = $order['username'] ?? $customer_email;
            $items = [];
            $decoded = json_decode($order['items'] ?? '[]', true);
            if (is_array($decoded)) {
                foreach ($decoded as $it) {
                    $items[] = [
                        'name' => $it['name'] ?? ($it['title'] ?? 'Item'),
                        'quantity' => (int)($it['quantity'] ?? 1),
                        'price' => (float)($it['price'] ?? 0)
                    ];
                }
            }
            $total = isset($order['total_amount']) ? (float)$order['total_amount'] : 0.0;
            try {
                send_purchase_confirmation_email($customer_email, $customer_name, $items, $total);
            } catch (Exception $e) {
                error_log("Failed to send approval email: " . $e->getMessage());
            }
        }

        echo json_encode(['success' => true, 'message' => 'Order approved and customer notified']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error approving order']);
    }
}

function rejectOrder($conn, $user_id) {
    $order_id = intval($_POST['order_id'] ?? 0);

    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        return;
    }

    $stmt = $conn->prepare("SELECT po.*, u.username, u.email 
                            FROM purchase_orders po 
                            LEFT JOIN users u ON po.user_id = u.id 
                            WHERE po.id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }

    if ($order['status'] === 'rejected') {
        echo json_encode(['success' => false, 'message' => 'Order is already rejected']);
        return;
    }

    $stmt = $conn->prepare("UPDATE purchase_orders SET status = 'rejected', rejected_at = NOW(), approved_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $user_id, $order_id);

    if ($stmt->execute()) {
        log_action($conn, $user_id, "Rejected purchase order #$order_id");

        // Send rejection email using helper
        $customer_email = $order['email'] ?? '';
        if (!empty($customer_email)) {
            $customer_name = $order['username'] ?? $customer_email;
            $items = [];
            $decoded = json_decode($order['items'] ?? '[]', true);
            if (is_array($decoded)) {
                foreach ($decoded as $it) {
                    $items[] = [
                        'name' => $it['name'] ?? ($it['title'] ?? 'Item'),
                        'quantity' => (int)($it['quantity'] ?? 1),
                        'price' => (float)($it['price'] ?? 0)
                    ];
                }
            }
            $total = isset($order['total_amount']) ? (float)$order['total_amount'] : 0.0;
            $reason = trim($_POST['reason'] ?? '');
            try {
                send_purchase_rejection_email($customer_email, $customer_name, $items, $total, $reason);
            } catch (Exception $e) {
                error_log("Failed to send rejection email: " . $e->getMessage());
            }
        }

        echo json_encode(['success' => true, 'message' => 'Order rejected and customer notified']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error rejecting order']);
    }
}
?>
