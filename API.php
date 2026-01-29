<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include "config.php";

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

try {
    switch ($endpoint) {
        
        // ==================== MENU ENDPOINTS ====================
        case 'menu':
            handleMenu($pdo, $method);
            break;
            
        // ==================== TABLES ENDPOINTS ====================
        case 'tables':
            handleTables($pdo, $method);
            break;
            
        // ==================== ORDERS ENDPOINTS ====================
        case 'orders':
            handleOrders($pdo, $method);
            break;
            
        // ==================== ORDER DETAILS ENDPOINTS ====================
        case 'order_details':
            handleOrderDetails($pdo, $method);
            break;
            
        // ==================== USERS ENDPOINTS ====================
        case 'users':
            handleUsers($pdo, $method);
            break;
            
        // ==================== DASHBOARD/STATISTICS ====================
        case 'dashboard':
            handleDashboard($pdo, $method);
            break;
            
        default:
            echo json_encode([
                "status" => "error",
                "message" => "Endpoint tidak ditemukan. Gunakan: menu, tables, orders, order_details, users, dashboard"
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Terjadi kesalahan: " . $e->getMessage()
    ]);
}

// ==================== MENU FUNCTIONS ====================
function handleMenu($pdo, $method) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt = $pdo->prepare("SELECT * FROM menu WHERE id = ?");
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($data) {
                    echo json_encode(["status" => "success", "data" => $data]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Menu tidak ditemukan"]);
                }
            } else {
                // Filter berdasarkan kategori jika ada
                if (isset($_GET['category'])) {
                    $category = $_GET['category'];
                    $stmt = $pdo->prepare("SELECT * FROM menu WHERE category = ? ORDER BY name ASC");
                    $stmt->execute([$category]);
                } else {
                    $stmt = $pdo->query("SELECT * FROM menu ORDER BY category, name ASC");
                }
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["status" => "success", "data" => $data]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['name'], $input['price'], $input['category'])) {
                echo json_encode(["status" => "error", "message" => "Data tidak lengkap (name, price, category diperlukan)"]);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO menu (name, description, price, category, stock, image) VALUES (?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $input['name'],
                $input['description'] ?? null,
                $input['price'],
                $input['category'],
                $input['stock'] ?? 100,
                $input['image'] ?? null
            ]);
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "Menu berhasil ditambahkan" : "Gagal menambahkan menu",
                "id" => $result ? $pdo->lastInsertId() : null
            ]);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['id'])) {
                echo json_encode(["status" => "error", "message" => "ID tidak ditemukan"]);
                exit;
            }
            
            $updateFields = [];
            $params = [];
            
            if (isset($input['name'])) {
                $updateFields[] = "name = ?";
                $params[] = $input['name'];
            }
            if (isset($input['description'])) {
                $updateFields[] = "description = ?";
                $params[] = $input['description'];
            }
            if (isset($input['price'])) {
                $updateFields[] = "price = ?";
                $params[] = $input['price'];
            }
            if (isset($input['category'])) {
                $updateFields[] = "category = ?";
                $params[] = $input['category'];
            }
            if (isset($input['stock'])) {
                $updateFields[] = "stock = ?";
                $params[] = $input['stock'];
            }
            if (isset($input['image'])) {
                $updateFields[] = "image = ?";
                $params[] = $input['image'];
            }
            
            $params[] = $input['id'];
            
            $stmt = $pdo->prepare("UPDATE menu SET " . implode(", ", $updateFields) . " WHERE id = ?");
            $result = $stmt->execute($params);
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "Menu berhasil diupdate" : "Gagal mengupdate menu"
            ]);
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['id'])) {
                echo json_encode(["status" => "error", "message" => "ID tidak ditemukan"]);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM menu WHERE id = ?");
            $result = $stmt->execute([$input['id']]);
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "Menu berhasil dihapus" : "Gagal menghapus menu"
            ]);
            break;
    }
}

// ==================== TABLES FUNCTIONS ====================
function handleTables($pdo, $method) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt = $pdo->prepare("SELECT * FROM tables WHERE id = ?");
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($data) {
                    echo json_encode(["status" => "success", "data" => $data]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Meja tidak ditemukan"]);
                }
            } else {
                // Filter berdasarkan status jika ada
                if (isset($_GET['status'])) {
                    $status = $_GET['status'];
                    $stmt = $pdo->prepare("SELECT * FROM tables WHERE status = ? ORDER BY table_number ASC");
                    $stmt->execute([$status]);
                } else {
                    $stmt = $pdo->query("SELECT * FROM tables ORDER BY table_number ASC");
                }
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["status" => "success", "data" => $data]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['table_number'], $input['capacity'])) {
                echo json_encode(["status" => "error", "message" => "Data tidak lengkap (table_number, capacity diperlukan)"]);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO tables (table_number, capacity, status) VALUES (?, ?, ?)");
            $result = $stmt->execute([
                $input['table_number'],
                $input['capacity'],
                $input['status'] ?? 'available'
            ]);
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "Meja berhasil ditambahkan" : "Gagal menambahkan meja",
                "id" => $result ? $pdo->lastInsertId() : null
            ]);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['id'])) {
                echo json_encode(["status" => "error", "message" => "ID tidak ditemukan"]);
                exit;
            }
            
            $updateFields = [];
            $params = [];
            
            if (isset($input['table_number'])) {
                $updateFields[] = "table_number = ?";
                $params[] = $input['table_number'];
            }
            if (isset($input['capacity'])) {
                $updateFields[] = "capacity = ?";
                $params[] = $input['capacity'];
            }
            if (isset($input['status'])) {
                $updateFields[] = "status = ?";
                $params[] = $input['status'];
            }
            
            $params[] = $input['id'];
            
            $stmt = $pdo->prepare("UPDATE tables SET " . implode(", ", $updateFields) . " WHERE id = ?");
            $result = $stmt->execute($params);
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "Meja berhasil diupdate" : "Gagal mengupdate meja"
            ]);
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['id'])) {
                echo json_encode(["status" => "error", "message" => "ID tidak ditemukan"]);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM tables WHERE id = ?");
            $result = $stmt->execute([$input['id']]);
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "Meja berhasil dihapus" : "Gagal menghapus meja"
            ]);
            break;
    }
}

// ==================== ORDERS FUNCTIONS ====================
function handleOrders($pdo, $method) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = intval($_GET['id']);
                // Get order dengan detail items
                $stmt = $pdo->prepare("
                    SELECT o.*, t.table_number, u.username as cashier_name
                    FROM orders o
                    LEFT JOIN tables t ON o.table_id = t.id
                    LEFT JOIN users u ON o.user_id = u.id
                    WHERE o.id = ?
                ");
                $stmt->execute([$id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($order) {
                    // Get order details
                    $stmt = $pdo->prepare("
                        SELECT od.*, m.name as menu_name, m.price as menu_price
                        FROM order_details od
                        JOIN menu m ON od.menu_id = m.id
                        WHERE od.order_id = ?
                    ");
                    $stmt->execute([$id]);
                    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode(["status" => "success", "data" => $order]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Order tidak ditemukan"]);
                }
            } else {
                // Get all orders dengan filter opsional
                $query = "
                    SELECT o.*, t.table_number, u.username as cashier_name
                    FROM orders o
                    LEFT JOIN tables t ON o.table_id = t.id
                    LEFT JOIN users u ON o.user_id = u.id
                    WHERE 1=1
                ";
                $params = [];
                
                if (isset($_GET['status'])) {
                    $query .= " AND o.status = ?";
                    $params[] = $_GET['status'];
                }
                
                if (isset($_GET['date'])) {
                    $query .= " AND DATE(o.order_date) = ?";
                    $params[] = $_GET['date'];
                }
                
                $query .= " ORDER BY o.order_date DESC";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(["status" => "success", "data" => $data]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['table_id'], $input['items']) || !is_array($input['items'])) {
                echo json_encode(["status" => "error", "message" => "Data tidak lengkap (table_id, items diperlukan)"]);
                exit;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Calculate total
                $total = 0;
                foreach ($input['items'] as $item) {
                    $total += $item['price'] * $item['quantity'];
                }
                
                // Insert order
                $stmt = $pdo->prepare("
                    INSERT INTO orders (table_id, user_id, order_date, total_amount, status, payment_method)
                    VALUES (?, ?, NOW(), ?, ?, ?)
                ");
                $stmt->execute([
                    $input['table_id'],
                    $input['user_id'] ?? null,
                    $total,
                    $input['status'] ?? 'pending',
                    $input['payment_method'] ?? 'cash'
                ]);
                
                $orderId = $pdo->lastInsertId();
                
                // Insert order details
                $stmt = $pdo->prepare("
                    INSERT INTO order_details (order_id, menu_id, quantity, price, subtotal)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                foreach ($input['items'] as $item) {
                    $subtotal = $item['price'] * $item['quantity'];
                    $stmt->execute([
                        $orderId,
                        $item['menu_id'],
                        $item['quantity'],
                        $item['price'],
                        $subtotal
                    ]);
                }
                
                // Update table status
                $stmt = $pdo->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?");
                $stmt->execute([$input['table_id']]);
                
                $pdo->commit();
                
                echo json_encode([
                    "status" => "success",
                    "message" => "Order berhasil dibuat",
                    "order_id" => $orderId
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode([
                    "status" => "error",
                    "message" => "Gagal membuat order: " . $e->getMessage()
                ]);
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['id'])) {
                echo json_encode(["status" => "error", "message" => "ID tidak ditemukan"]);
                exit;
            }
            
            try {
                $pdo->beginTransaction();
                
                $updateFields = [];
                $params = [];
                
                if (isset($input['status'])) {
                    $updateFields[] = "status = ?";
                    $params[] = $input['status'];
                    
                    // Jika status completed, update table status
                    if ($input['status'] == 'completed') {
                        $stmt = $pdo->prepare("SELECT table_id FROM orders WHERE id = ?");
                        $stmt->execute([$input['id']]);
                        $order = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($order) {
                            $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
                            $stmt->execute([$order['table_id']]);
                        }
                    }
                }
                
                if (isset($input['payment_method'])) {
                    $updateFields[] = "payment_method = ?";
                    $params[] = $input['payment_method'];
                }
                
                if (isset($input['total_amount'])) {
                    $updateFields[] = "total_amount = ?";
                    $params[] = $input['total_amount'];
                }
                
                $params[] = $input['id'];
                
                if (!empty($updateFields)) {
                    $stmt = $pdo->prepare("UPDATE orders SET " . implode(", ", $updateFields) . " WHERE id = ?");
                    $stmt->execute($params);
                }
                
                $pdo->commit();
                
                echo json_encode([
                    "status" => "success",
                    "message" => "Order berhasil diupdate"
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode([
                    "status" => "error",
                    "message" => "Gagal mengupdate order: " . $e->getMessage()
                ]);
            }
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['id'])) {
                echo json_encode(["status" => "error", "message" => "ID tidak ditemukan"]);
                exit;
            }
            
            try {
                $pdo->beginTransaction();
                
                // Get table_id before delete
                $stmt = $pdo->prepare("SELECT table_id FROM orders WHERE id = ?");
                $stmt->execute([$input['id']]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Delete order details first
                $stmt = $pdo->prepare("DELETE FROM order_details WHERE order_id = ?");
                $stmt->execute([$input['id']]);
                
                // Delete order
                $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $result = $stmt->execute([$input['id']]);
                
                // Update table status
                if ($order) {
                    $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
                    $stmt->execute([$order['table_id']]);
                }
                
                $pdo->commit();
                
                echo json_encode([
                    "status" => $result ? "success" : "error",
                    "message" => $result ? "Order berhasil dihapus" : "Gagal menghapus order"
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode([
                    "status" => "error",
                    "message" => "Gagal menghapus order: " . $e->getMessage()
                ]);
            }
            break;
    }
}

// ==================== ORDER DETAILS FUNCTIONS ====================
function handleOrderDetails($pdo, $method) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['order_id'])) {
                $orderId = intval($_GET['order_id']);
                $stmt = $pdo->prepare("
                    SELECT od.*, m.name as menu_name, m.price as menu_price, m.category
                    FROM order_details od
                    JOIN menu m ON od.menu_id = m.id
                    WHERE od.order_id = ?
                ");
                $stmt->execute([$orderId]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["status" => "success", "data" => $data]);
            } else {
                echo json_encode(["status" => "error", "message" => "order_id diperlukan"]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['order_id'], $input['menu_id'], $input['quantity'], $input['price'])) {
                echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
                exit;
            }
            
            $subtotal = $input['price'] * $input['quantity'];
            
            $stmt = $pdo->prepare("
                INSERT INTO order_details (order_id, menu_id, quantity, price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $input['order_id'],
                $input['menu_id'],
                $input['quantity'],
                $input['price'],
                $subtotal
            ]);
            
            // Update total order
            if ($result) {
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET total_amount = (SELECT SUM(subtotal) FROM order_details WHERE order_id = ?)
                    WHERE id = ?
                ");
                $stmt->execute([$input['order_id'], $input['order_id']]);
            }
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "Item berhasil ditambahkan" : "Gagal menambahkan item",
                "id" => $result ? $pdo->lastInsertId() : null
            ]);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['id'], $input['quantity'])) {
                echo json_encode(["status" => "error", "message" => "ID dan quantity diperlukan"]);
                exit;
            }
            
            // Get current price
            $stmt = $pdo->prepare("SELECT price, order_id FROM order_details WHERE id = ?");
            $stmt->execute([$input['id']]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($detail) {
                $subtotal = $detail['price'] * $input['quantity'];
                
                $stmt = $pdo->prepare("UPDATE order_details SET quantity = ?, subtotal = ? WHERE id = ?");
                $result = $stmt->execute([$input['quantity'], $subtotal, $input['id']]);
                
                // Update total order
                if ($result) {
                    $stmt = $pdo->prepare("
                        UPDATE orders 
                        SET total_amount = (SELECT SUM(subtotal) FROM order_details WHERE order_id = ?)
                        WHERE id = ?
                    ");
                    $stmt->execute([$detail['order_id'], $detail['order_id']]);
                }
                
                echo json_encode([
                    "status" => $result ? "success" : "error",
                    "message" => $result ? "Item berhasil diupdate" : "Gagal mengupdate item"
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Item tidak ditemukan"]);
            }
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['id'])) {
                echo json_encode(["status" => "error", "message" => "ID tidak ditemukan"]);
                exit;
            }
            
            // Get order_id before delete
            $stmt = $pdo->prepare("SELECT order_id FROM order_details WHERE id = ?");
            $stmt->execute([$input['id']]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $pdo->prepare("DELETE FROM order_details WHERE id = ?");
            $result = $stmt->execute([$input['id']]);
            
            // Update total order
            if ($result && $detail) {
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET total_amount = (SELECT COALESCE(SUM(subtotal), 0) FROM order_details WHERE order_id = ?)
                    WHERE id = ?
                ");
                $stmt->execute([$detail['order_id'], $detail['order_id']]);
            }
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "Item berhasil dihapus" : "Gagal menghapus item"
            ]);
            break;
    }
}

// ==================== USERS FUNCTIONS ====================
function handleUsers($pdo, $method) {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $id = intval($_GET['id']);
                $stmt = $pdo->prepare("SELECT id, username, full_name, role, email, created_at FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($data) {
                    echo json_encode(["status" => "success", "data" => $data]);
                } else {
                    echo json_encode(["status" => "error", "message" => "User tidak ditemukan"]);
                }
            } else {
                if (isset($_GET['role'])) {
                    $role = $_GET['role'];
                    $stmt = $pdo->prepare("SELECT id, username, full_name, role, email, created_at FROM users WHERE role = ? ORDER BY username ASC");
                    $stmt->execute([$role]);
                } else {
                    $stmt = $pdo->query("SELECT id, username, full_name, role, email, created_at FROM users ORDER BY username ASC");
                }
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(["status" => "success", "data" => $data]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['username'], $input['password'], $input['role'])) {
                echo json_encode(["status" => "error", "message" => "Data tidak lengkap (username, password, role diperlukan)"]);
                exit;
            }
            
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$input['username']]);
            if ($stmt->fetch()) {
                echo json_encode(["status" => "error", "message" => "Username sudah digunakan"]);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, email) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $input['username'],
                password_hash($input['password'], PASSWORD_DEFAULT),
                $input['full_name'] ?? null,
                $input['role'],
                $input['email'] ?? null
            ]);
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "User berhasil ditambahkan" : "Gagal menambahkan user",
                "id" => $result ? $pdo->lastInsertId() : null
            ]);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['id'])) {
                echo json_encode(["status" => "error", "message" => "ID tidak ditemukan"]);
                exit;
            }
            
            $updateFields = [];
            $params = [];
            
            if (isset($input['username'])) {
                // Check if username already exists for other users
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$input['username'], $input['id']]);
                if ($stmt->fetch()) {
                    echo json_encode(["status" => "error", "message" => "Username sudah digunakan"]);
                    exit;
                }
                $updateFields[] = "username = ?";
                $params[] = $input['username'];
            }
            
            if (isset($input['password']) && !empty($input['password'])) {
                $updateFields[] = "password = ?";
                $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            
            if (isset($input['full_name'])) {
                $updateFields[] = "full_name = ?";
                $params[] = $input['full_name'];
            }
            
            if (isset($input['role'])) {
                $updateFields[] = "role = ?";
                $params[] = $input['role'];
            }
            
            if (isset($input['email'])) {
                $updateFields[] = "email = ?";
                $params[] = $input['email'];
            }
            
            $params[] = $input['id'];
            
            $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?");
            $result = $stmt->execute($params);
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "User berhasil diupdate" : "Gagal mengupdate user"
            ]);
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents("php://input"), true);
            if (!isset($input['id'])) {
                echo json_encode(["status" => "error", "message" => "ID tidak ditemukan"]);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$input['id']]);
            
            echo json_encode([
                "status" => $result ? "success" : "error",
                "message" => $result ? "User berhasil dihapus" : "Gagal menghapus user"
            ]);
            break;
    }
}

// ==================== DASHBOARD FUNCTIONS ====================
function handleDashboard($pdo, $method) {
    if ($method !== 'GET') {
        echo json_encode(["status" => "error", "message" => "Method tidak didukung"]);
        return;
    }
    
    try {
        // Total pendapatan hari ini
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total_amount), 0) as today_revenue
            FROM orders
            WHERE DATE(order_date) = CURDATE() AND status = 'completed'
        ");
        $todayRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['today_revenue'];
        
        // Total pendapatan bulan ini
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(total_amount), 0) as month_revenue
            FROM orders
            WHERE MONTH(order_date) = MONTH(CURDATE()) 
            AND YEAR(order_date) = YEAR(CURDATE())
            AND status = 'completed'
        ");
        $monthRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['month_revenue'];
        
        // Total pesanan hari ini
        $stmt = $pdo->query("
            SELECT COUNT(*) as today_orders
            FROM orders
            WHERE DATE(order_date) = CURDATE()
        ");
        $todayOrders = $stmt->fetch(PDO::FETCH_ASSOC)['today_orders'];
        
        // Pesanan aktif
        $stmt = $pdo->query("
            SELECT COUNT(*) as active_orders
            FROM orders
            WHERE status = 'pending'
        ");
        $activeOrders = $stmt->fetch(PDO::FETCH_ASSOC)['active_orders'];
        
        // Meja tersedia
        $stmt = $pdo->query("
            SELECT COUNT(*) as available_tables
            FROM tables
            WHERE status = 'available'
        ");
        $availableTables = $stmt->fetch(PDO::FETCH_ASSOC)['available_tables'];
        
        // Menu terpopuler (bulan ini)
        $stmt = $pdo->query("
            SELECT m.name, m.category, SUM(od.quantity) as total_sold
            FROM order_details od
            JOIN menu m ON od.menu_id = m.id
            JOIN orders o ON od.order_id = o.id
            WHERE MONTH(o.order_date) = MONTH(CURDATE())
            AND YEAR(o.order_date) = YEAR(CURDATE())
            GROUP BY m.id, m.name, m.category
            ORDER BY total_sold DESC
            LIMIT 5
        ");
        $popularMenu = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pendapatan 7 hari terakhir
        $stmt = $pdo->query("
            SELECT DATE(order_date) as date, COALESCE(SUM(total_amount), 0) as revenue
            FROM orders
            WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            AND status = 'completed'
            GROUP BY DATE(order_date)
            ORDER BY date ASC
        ");
        $weeklyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "status" => "success",
            "data" => [
                "today_revenue" => $todayRevenue,
                "month_revenue" => $monthRevenue,
                "today_orders" => $todayOrders,
                "active_orders" => $activeOrders,
                "available_tables" => $availableTables,
                "popular_menu" => $popularMenu,
                "weekly_revenue" => $weeklyRevenue
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            "status" => "error",
            "message" => "Gagal mengambil data dashboard: " . $e->getMessage()
        ]);
    }
}
?>