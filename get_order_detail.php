<?php
// File: get_order_detail.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$order_id = isset($_GET['id']) ? escape($_GET['id']) : 0;

// Get order header
$query = "SELECT o.*, t.table_number, u.fullname as kasir
          FROM orders o
          LEFT JOIN tables t ON o.table_id = t.id
          JOIN users u ON o.user_id = u.id
          WHERE o.id = '$order_id'";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

$order = mysqli_fetch_assoc($result);

// Check if kasir can only see own orders
if ($_SESSION['role'] == 'kasir' && $order['user_id'] != $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get order items
$items_query = "SELECT oi.*, m.name as menu_name
                FROM order_items oi
                JOIN menu m ON oi.menu_id = m.id
                WHERE oi.order_id = '$order_id'
                ORDER BY oi.id";

$items_result = mysqli_query($conn, $items_query);
$items = [];

while ($item = mysqli_fetch_assoc($items_result)) {
    $items[] = [
        'menu_name' => $item['menu_name'],
        'quantity' => $item['quantity'],
        'price' => $item['price'],
        'price_formatted' => number_format($item['price'], 0, ',', '.'),
        'subtotal' => $item['subtotal'],
        'subtotal_formatted' => number_format($item['subtotal'], 0, ',', '.')
    ];
}

// Format order data
$status_text = [
    'pending' => 'Pending',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan'
];

$response = [
    'success' => true,
    'order' => [
        'order_number' => $order['order_number'],
        'customer_name' => $order['customer_name'],
        'table_number' => $order['table_number'],
        'order_date' => date('d/m/Y H:i', strtotime($order['order_date'])),
        'kasir' => $order['kasir'],
        'status' => $order['status'],
        'status_text' => $status_text[$order['status']],
        'total_amount' => $order['total_amount'],
        'total_formatted' => number_format($order['total_amount'], 0, ',', '.')
    ],
    'items' => $items
];

echo json_encode($response);
?>