<?php
/**
 * Alugoro Cafe Management System - REST API
 * 
 * Professional API endpoint untuk manajemen restoran/cafe
 * Features: CRUD operations, Authentication, Cart Management, Reporting, Activity Logging
 * 
 * @author Alugoro Cafe Development Team
 * @version 1.0.0
 * @license MIT
 */

// Error reporting untuk development (nonaktifkan di production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers untuk CORS dan JSON response
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Session start
session_start();

// Database Configuration
class Database {
    private $host = "localhost";
    private $db_name = "alugoro_cafe";
    private $username = "root";
    private $password = "";
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
        }
        
        return $this->conn;
    }
}

// Response Helper Class
class ApiResponse {
    public static function success($data = null, $message = "Success", $code = 200) {
        http_response_code($code);
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    public static function error($message = "Error occurred", $code = 400, $errors = null) {
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    public static function unauthorized($message = "Unauthorized access") {
        self::error($message, 401);
    }
    
    public static function forbidden($message = "Forbidden") {
        self::error($message, 403);
    }
    
    public static function notFound($message = "Resource not found") {
        self::error($message, 404);
    }
}

// Activity Logger Class
class ActivityLogger {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function log($userId, $action, $description, $ipAddress = null) {
        try {
            $query = "INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) 
                      VALUES (:user_id, :action, :description, :ip_address, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':ip_address', $ipAddress);
            
            return $stmt->execute();
        } catch(PDOException $e) {
            error_log("Activity Log Error: " . $e->getMessage());
            return false;
        }
    }
}

// Authentication Class
class Auth {
    private $db;
    private $logger;
    
    public function __construct($db, $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    public function register($data) {
        // Validasi input
        $required = ['username', 'email', 'password', 'full_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                ApiResponse::error("Field '$field' is required", 400);
            }
        }
        
        // Validasi email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            ApiResponse::error("Invalid email format", 400);
        }
        
        // Validasi password strength
        if (strlen($data['password']) < 6) {
            ApiResponse::error("Password must be at least 6 characters", 400);
        }
        
        // Check existing user
        $query = "SELECT id FROM users WHERE username = :username OR email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            ApiResponse::error("Username or email already exists", 409);
        }
        
        // Insert new user
        $query = "INSERT INTO users (username, email, password, full_name, role, created_at) 
                  VALUES (:username, :email, :password, :full_name, :role, NOW())";
        
        $stmt = $this->db->prepare($query);
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        $role = isset($data['role']) ? $data['role'] : 'cashier';
        
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':full_name', $data['full_name']);
        $stmt->bindParam(':role', $role);
        
        if ($stmt->execute()) {
            $userId = $this->db->lastInsertId();
            $this->logger->log($userId, 'REGISTER', "New user registered: {$data['username']}", $_SERVER['REMOTE_ADDR']);
            
            ApiResponse::success([
                'user_id' => $userId,
                'username' => $data['username']
            ], "Registration successful", 201);
        } else {
            ApiResponse::error("Registration failed", 500);
        }
    }
    
    public function login($data) {
        // Validasi input
        if (empty($data['username']) || empty($data['password'])) {
            ApiResponse::error("Username and password are required", 400);
        }
        
        // Get user
        $query = "SELECT * FROM users WHERE username = :username AND status = 'active'";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $data['username']);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($data['password'], $user['password'])) {
            ApiResponse::error("Invalid username or password", 401);
        }
        
        // Update last login
        $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
        $updateStmt = $this->db->prepare($updateQuery);
        $updateStmt->bindParam(':id', $user['id']);
        $updateStmt->execute();
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        
        // Set remember me cookie if requested
        if (isset($data['remember_me']) && $data['remember_me']) {
            $token = bin2hex(random_bytes(32));
            $hashedToken = password_hash($token, PASSWORD_BCRYPT);
            
            // Save token to database
            $tokenQuery = "INSERT INTO remember_tokens (user_id, token, expires_at) 
                          VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 30 DAY))";
            $tokenStmt = $this->db->prepare($tokenQuery);
            $tokenStmt->bindParam(':user_id', $user['id']);
            $tokenStmt->bindParam(':token', $hashedToken);
            $tokenStmt->execute();
            
            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
        }
        
        $this->logger->log($user['id'], 'LOGIN', "User logged in", $_SERVER['REMOTE_ADDR']);
        
        ApiResponse::success([
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ],
            'session_id' => session_id()
        ], "Login successful");
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logger->log($_SESSION['user_id'], 'LOGOUT', "User logged out", $_SERVER['REMOTE_ADDR']);
        }
        
        // Clear remember token
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
        
        session_destroy();
        ApiResponse::success(null, "Logout successful");
    }
    
    public function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized("Please login first");
        }
        return $_SESSION['user_id'];
    }
    
    public function checkRole($allowedRoles) {
        $this->checkAuth();
        
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            ApiResponse::forbidden("You don't have permission to access this resource");
        }
    }
}

// Menu Management Class
class MenuAPI {
    private $db;
    private $logger;
    
    public function __construct($db, $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    public function getAll($filters = []) {
        $query = "SELECT m.*, c.name as category_name 
                  FROM menu_items m 
                  LEFT JOIN categories c ON m.category_id = c.id 
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['category_id'])) {
            $query .= " AND m.category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['status'])) {
            $query .= " AND m.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $query .= " AND (m.name LIKE :search OR m.description LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $query .= " ORDER BY m.name ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        $items = $stmt->fetchAll();
        
        ApiResponse::success([
            'items' => $items,
            'total' => count($items)
        ]);
    }
    
    public function getById($id) {
        $query = "SELECT m.*, c.name as category_name 
                  FROM menu_items m 
                  LEFT JOIN categories c ON m.category_id = c.id 
                  WHERE m.id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $item = $stmt->fetch();
        
        if (!$item) {
            ApiResponse::notFound("Menu item not found");
        }
        
        ApiResponse::success($item);
    }
    
    public function create($data) {
        $required = ['name', 'category_id', 'price'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                ApiResponse::error("Field '$field' is required", 400);
            }
        }
        
        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = $this->handleImageUpload($_FILES['image']);
        }
        
        $query = "INSERT INTO menu_items (name, description, category_id, price, image, stock, status, created_at) 
                  VALUES (:name, :description, :category_id, :price, :image, :stock, :status, NOW())";
        
        $stmt = $this->db->prepare($query);
        
        $status = isset($data['status']) ? $data['status'] : 'available';
        $stock = isset($data['stock']) ? $data['stock'] : 0;
        
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':category_id', $data['category_id']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':image', $imagePath);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':status', $status);
        
        if ($stmt->execute()) {
            $itemId = $this->db->lastInsertId();
            $this->logger->log($_SESSION['user_id'], 'CREATE_MENU', "Created menu item: {$data['name']}", $_SERVER['REMOTE_ADDR']);
            
            ApiResponse::success([
                'id' => $itemId,
                'name' => $data['name']
            ], "Menu item created successfully", 201);
        } else {
            ApiResponse::error("Failed to create menu item", 500);
        }
    }
    
    public function update($id, $data) {
        // Check if item exists
        $checkQuery = "SELECT * FROM menu_items WHERE id = :id";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            ApiResponse::notFound("Menu item not found");
        }
        
        $item = $checkStmt->fetch();
        
        // Handle image upload
        $imagePath = $item['image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = $this->handleImageUpload($_FILES['image']);
            
            // Delete old image
            if ($item['image'] && file_exists($item['image'])) {
                unlink($item['image']);
            }
        }
        
        $query = "UPDATE menu_items SET 
                  name = :name,
                  description = :description,
                  category_id = :category_id,
                  price = :price,
                  image = :image,
                  stock = :stock,
                  status = :status,
                  updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        
        $name = isset($data['name']) ? $data['name'] : $item['name'];
        $description = isset($data['description']) ? $data['description'] : $item['description'];
        $categoryId = isset($data['category_id']) ? $data['category_id'] : $item['category_id'];
        $price = isset($data['price']) ? $data['price'] : $item['price'];
        $stock = isset($data['stock']) ? $data['stock'] : $item['stock'];
        $status = isset($data['status']) ? $data['status'] : $item['status'];
        
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':category_id', $categoryId);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':image', $imagePath);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $this->logger->log($_SESSION['user_id'], 'UPDATE_MENU', "Updated menu item ID: $id", $_SERVER['REMOTE_ADDR']);
            ApiResponse::success(null, "Menu item updated successfully");
        } else {
            ApiResponse::error("Failed to update menu item", 500);
        }
    }
    
    public function delete($id) {
        // Check if item exists
        $checkQuery = "SELECT * FROM menu_items WHERE id = :id";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            ApiResponse::notFound("Menu item not found");
        }
        
        $item = $checkStmt->fetch();
        
        // Delete image file
        if ($item['image'] && file_exists($item['image'])) {
            unlink($item['image']);
        }
        
        // Delete from database
        $query = "DELETE FROM menu_items WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $this->logger->log($_SESSION['user_id'], 'DELETE_MENU', "Deleted menu item ID: $id", $_SERVER['REMOTE_ADDR']);
            ApiResponse::success(null, "Menu item deleted successfully");
        } else {
            ApiResponse::error("Failed to delete menu item", 500);
        }
    }
    
    private function handleImageUpload($file) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            ApiResponse::error("Invalid file type. Only JPG, PNG, GIF, and WEBP allowed", 400);
        }
        
        if ($file['size'] > $maxSize) {
            ApiResponse::error("File size exceeds 5MB limit", 400);
        }
        
        $uploadDir = 'uploads/menu/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return $uploadPath;
        } else {
            ApiResponse::error("Failed to upload image", 500);
        }
    }
}

// Cart Management Class
class CartAPI {
    private $db;
    private $logger;
    
    public function __construct($db, $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    public function getCart($userId) {
        $query = "SELECT c.*, m.name, m.price, m.image, m.status,
                  (c.quantity * m.price) as subtotal
                  FROM cart c
                  JOIN menu_items m ON c.menu_item_id = m.id
                  WHERE c.user_id = :user_id
                  ORDER BY c.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $items = $stmt->fetchAll();
        
        $total = 0;
        foreach ($items as $item) {
            $total += $item['subtotal'];
        }
        
        ApiResponse::success([
            'items' => $items,
            'total_items' => count($items),
            'total_amount' => $total
        ]);
    }
    
    public function addToCart($userId, $data) {
        if (empty($data['menu_item_id']) || empty($data['quantity'])) {
            ApiResponse::error("Menu item ID and quantity are required", 400);
        }
        
        // Check if menu item exists and available
        $menuQuery = "SELECT * FROM menu_items WHERE id = :id AND status = 'available'";
        $menuStmt = $this->db->prepare($menuQuery);
        $menuStmt->bindParam(':id', $data['menu_item_id']);
        $menuStmt->execute();
        
        if ($menuStmt->rowCount() === 0) {
            ApiResponse::error("Menu item not available", 404);
        }
        
        $menuItem = $menuStmt->fetch();
        
        // Check stock
        if ($menuItem['stock'] < $data['quantity']) {
            ApiResponse::error("Insufficient stock. Available: " . $menuItem['stock'], 400);
        }
        
        // Check if item already in cart
        $checkQuery = "SELECT * FROM cart WHERE user_id = :user_id AND menu_item_id = :menu_item_id";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':user_id', $userId);
        $checkStmt->bindParam(':menu_item_id', $data['menu_item_id']);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            // Update quantity
            $cartItem = $checkStmt->fetch();
            $newQuantity = $cartItem['quantity'] + $data['quantity'];
            
            if ($menuItem['stock'] < $newQuantity) {
                ApiResponse::error("Insufficient stock. Available: " . $menuItem['stock'], 400);
            }
            
            $updateQuery = "UPDATE cart SET quantity = :quantity, updated_at = NOW() 
                           WHERE id = :id";
            $updateStmt = $this->db->prepare($updateQuery);
            $updateStmt->bindParam(':quantity', $newQuantity);
            $updateStmt->bindParam(':id', $cartItem['id']);
            $updateStmt->execute();
            
            $this->logger->log($userId, 'UPDATE_CART', "Updated cart item: {$menuItem['name']}", $_SERVER['REMOTE_ADDR']);
            
            ApiResponse::success(null, "Cart updated successfully");
        } else {
            // Add new item
            $insertQuery = "INSERT INTO cart (user_id, menu_item_id, quantity, created_at) 
                           VALUES (:user_id, :menu_item_id, :quantity, NOW())";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->bindParam(':user_id', $userId);
            $insertStmt->bindParam(':menu_item_id', $data['menu_item_id']);
            $insertStmt->bindParam(':quantity', $data['quantity']);
            $insertStmt->execute();
            
            $this->logger->log($userId, 'ADD_TO_CART', "Added to cart: {$menuItem['name']}", $_SERVER['REMOTE_ADDR']);
            
            ApiResponse::success(null, "Item added to cart", 201);
        }
    }
    
    public function updateCartItem($userId, $cartId, $data) {
        if (empty($data['quantity'])) {
            ApiResponse::error("Quantity is required", 400);
        }
        
        // Check if cart item belongs to user
        $checkQuery = "SELECT c.*, m.stock, m.name FROM cart c 
                      JOIN menu_items m ON c.menu_item_id = m.id
                      WHERE c.id = :id AND c.user_id = :user_id";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $cartId);
        $checkStmt->bindParam(':user_id', $userId);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            ApiResponse::notFound("Cart item not found");
        }
        
        $cartItem = $checkStmt->fetch();
        
        // Check stock
        if ($cartItem['stock'] < $data['quantity']) {
            ApiResponse::error("Insufficient stock. Available: " . $cartItem['stock'], 400);
        }
        
        $updateQuery = "UPDATE cart SET quantity = :quantity, updated_at = NOW() WHERE id = :id";
        $updateStmt = $this->db->prepare($updateQuery);
        $updateStmt->bindParam(':quantity', $data['quantity']);
        $updateStmt->bindParam(':id', $cartId);
        
        if ($updateStmt->execute()) {
            $this->logger->log($userId, 'UPDATE_CART', "Updated cart item: {$cartItem['name']}", $_SERVER['REMOTE_ADDR']);
            ApiResponse::success(null, "Cart item updated");
        } else {
            ApiResponse::error("Failed to update cart item", 500);
        }
    }
    
    public function removeFromCart($userId, $cartId) {
        // Check if cart item belongs to user
        $checkQuery = "SELECT c.*, m.name FROM cart c 
                      JOIN menu_items m ON c.menu_item_id = m.id
                      WHERE c.id = :id AND c.user_id = :user_id";
        $checkStmt = $this->db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $cartId);
        $checkStmt->bindParam(':user_id', $userId);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            ApiResponse::notFound("Cart item not found");
        }
        
        $cartItem = $checkStmt->fetch();
        
        $deleteQuery = "DELETE FROM cart WHERE id = :id";
        $deleteStmt = $this->db->prepare($deleteQuery);
        $deleteStmt->bindParam(':id', $cartId);
        
        if ($deleteStmt->execute()) {
            $this->logger->log($userId, 'REMOVE_FROM_CART', "Removed from cart: {$cartItem['name']}", $_SERVER['REMOTE_ADDR']);
            ApiResponse::success(null, "Item removed from cart");
        } else {
            ApiResponse::error("Failed to remove item", 500);
        }
    }
    
    public function clearCart($userId) {
        $query = "DELETE FROM cart WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        
        if ($stmt->execute()) {
            $this->logger->log($userId, 'CLEAR_CART', "Cleared cart", $_SERVER['REMOTE_ADDR']);
            ApiResponse::success(null, "Cart cleared");
        } else {
            ApiResponse::error("Failed to clear cart", 500);
        }
    }
}

// Order Management Class
class OrderAPI {
    private $db;
    private $logger;
    
    public function __construct($db, $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    public function createOrder($userId, $data) {
        try {
            $this->db->beginTransaction();
            
            // Get cart items
            $cartQuery = "SELECT c.*, m.name, m.price, m.stock 
                         FROM cart c
                         JOIN menu_items m ON c.menu_item_id = m.id
                         WHERE c.user_id = :user_id AND m.status = 'available'";
            $cartStmt = $this->db->prepare($cartQuery);
            $cartStmt->bindParam(':user_id', $userId);
            $cartStmt->execute();
            
            $cartItems = $cartStmt->fetchAll();
            
            if (count($cartItems) === 0) {
                ApiResponse::error("Cart is empty", 400);
            }
            
            // Calculate total
            $totalAmount = 0;
            foreach ($cartItems as $item) {
                // Check stock
                if ($item['stock'] < $item['quantity']) {
                    ApiResponse::error("Insufficient stock for: " . $item['name'], 400);
                }
                $totalAmount += ($item['price'] * $item['quantity']);
            }
            
            // Create order
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $paymentMethod = isset($data['payment_method']) ? $data['payment_method'] : 'cash';
            $customerName = isset($data['customer_name']) ? $data['customer_name'] : 'Guest';
            $tableNumber = isset($data['table_number']) ? $data['table_number'] : null;
            $notes = isset($data['notes']) ? $data['notes'] : null;
            
            $orderQuery = "INSERT INTO orders (order_number, user_id, customer_name, table_number, 
                          total_amount, payment_method, payment_status, order_status, notes, created_at)
                          VALUES (:order_number, :user_id, :customer_name, :table_number, 
                          :total_amount, :payment_method, 'pending', 'pending', :notes, NOW())";
            
            $orderStmt = $this->db->prepare($orderQuery);
            $orderStmt->bindParam(':order_number', $orderNumber);
            $orderStmt->bindParam(':user_id', $userId);
            $orderStmt->bindParam(':customer_name', $customerName);
            $orderStmt->bindParam(':table_number', $tableNumber);
            $orderStmt->bindParam(':total_amount', $totalAmount);
            $orderStmt->bindParam(':payment_method', $paymentMethod);
            $orderStmt->bindParam(':notes', $notes);
            $orderStmt->execute();
            
            $orderId = $this->db->lastInsertId();
            
            // Create order items and update stock
            $itemQuery = "INSERT INTO order_items (order_id, menu_item_id, quantity, price, subtotal)
                         VALUES (:order_id, :menu_item_id, :quantity, :price, :subtotal)";
            $itemStmt = $this->db->prepare($itemQuery);
            
            $stockQuery = "UPDATE menu_items SET stock = stock - :quantity WHERE id = :id";
            $stockStmt = $this->db->prepare($stockQuery);
            
            foreach ($cartItems as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                
                $itemStmt->bindParam(':order_id', $orderId);
                $itemStmt->bindParam(':menu_item_id', $item['menu_item_id']);
                $itemStmt->bindParam(':quantity', $item['quantity']);
                $itemStmt->bindParam(':price', $item['price']);
                $itemStmt->bindParam(':subtotal', $subtotal);
                $itemStmt->execute();
                
                $stockStmt->bindParam(':quantity', $item['quantity']);
                $stockStmt->bindParam(':id', $item['menu_item_id']);
                $stockStmt->execute();
            }
            
            // Clear cart
            $clearCartQuery = "DELETE FROM cart WHERE user_id = :user_id";
            $clearCartStmt = $this->db->prepare($clearCartQuery);
            $clearCartStmt->bindParam(':user_id', $userId);
            $clearCartStmt->execute();
            
            $this->db->commit();
            
            $this->logger->log($userId, 'CREATE_ORDER', "Created order: $orderNumber", $_SERVER['REMOTE_ADDR']);
            
            ApiResponse::success([
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount
            ], "Order created successfully", 201);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            ApiResponse::error("Failed to create order: " . $e->getMessage(), 500);
        }
    }
    
    public function getOrders($filters = []) {
        $query = "SELECT o.*, u.username as cashier_name,
                  COUNT(oi.id) as total_items
                  FROM orders o
                  LEFT JOIN users u ON o.user_id = u.id
                  LEFT JOIN order_items oi ON o.id = oi.order_id
                  WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $query .= " AND o.user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        
        if (!empty($filters['order_status'])) {
            $query .= " AND o.order_status = :order_status";
            $params[':order_status'] = $filters['order_status'];
        }
        
        if (!empty($filters['payment_status'])) {
            $query .= " AND o.payment_status = :payment_status";
            $params[':payment_status'] = $filters['payment_status'];
        }
        
        if (!empty($filters['date_from'])) {
            $query .= " AND DATE(o.created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $query .= " AND DATE(o.created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $query .= " GROUP BY o.id ORDER BY o.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $query .= " LIMIT :limit";
        }
        
        $stmt = $this->db->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if (!empty($filters['limit'])) {
            $stmt->bindValue(':limit', (int)$filters['limit'], PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $orders = $stmt->fetchAll();
        
        ApiResponse::success([
            'orders' => $orders,
            'total' => count($orders)
        ]);
    }
    
    public function getOrderById($id) {
        $query = "SELECT o.*, u.username as cashier_name
                  FROM orders o
                  LEFT JOIN users u ON o.user_id = u.id
                  WHERE o.id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        $order = $stmt->fetch();
        
        if (!$order) {
            ApiResponse::notFound("Order not found");
        }
        
        // Get order items
        $itemsQuery = "SELECT oi.*, m.name, m.image
                      FROM order_items oi
                      JOIN menu_items m ON oi.menu_item_id = m.id
                      WHERE oi.order_id = :order_id";
        
        $itemsStmt = $this->db->prepare($itemsQuery);
        $itemsStmt->bindParam(':order_id', $id);
        $itemsStmt->execute();
        
        $order['items'] = $itemsStmt->fetchAll();
        
        ApiResponse::success($order);
    }
    
    public function updateOrderStatus($id, $data) {
        if (empty($data['order_status'])) {
            ApiResponse::error("Order status is required", 400);
        }
        
        $allowedStatuses = ['pending', 'processing', 'completed', 'cancelled'];
        if (!in_array($data['order_status'], $allowedStatuses)) {
            ApiResponse::error("Invalid order status", 400);
        }
        
        $query = "UPDATE orders SET order_status = :order_status, updated_at = NOW() 
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':order_status', $data['order_status']);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $this->logger->log($_SESSION['user_id'], 'UPDATE_ORDER_STATUS', 
                             "Updated order #$id status to: {$data['order_status']}", 
                             $_SERVER['REMOTE_ADDR']);
            ApiResponse::success(null, "Order status updated");
        } else {
            ApiResponse::error("Failed to update order status", 500);
        }
    }
    
    public function updatePaymentStatus($id, $data) {
        if (empty($data['payment_status'])) {
            ApiResponse::error("Payment status is required", 400);
        }
        
        $allowedStatuses = ['pending', 'paid', 'refunded'];
        if (!in_array($data['payment_status'], $allowedStatuses)) {
            ApiResponse::error("Invalid payment status", 400);
        }
        
        $query = "UPDATE orders SET payment_status = :payment_status, updated_at = NOW() 
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':payment_status', $data['payment_status']);
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            $this->logger->log($_SESSION['user_id'], 'UPDATE_PAYMENT_STATUS', 
                             "Updated order #$id payment status to: {$data['payment_status']}", 
                             $_SERVER['REMOTE_ADDR']);
            ApiResponse::success(null, "Payment status updated");
        } else {
            ApiResponse::error("Failed to update payment status", 500);
        }
    }
}

// Statistics & Dashboard API
class StatsAPI {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getDashboardStats() {
        $stats = [];
        
        // Today's revenue
        $revenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as today_revenue
                        FROM orders 
                        WHERE DATE(created_at) = CURDATE() 
                        AND payment_status = 'paid'";
        $revenueStmt = $this->db->query($revenueQuery);
        $stats['today_revenue'] = $revenueStmt->fetch()['today_revenue'];
        
        // Today's orders
        $ordersQuery = "SELECT COUNT(*) as today_orders
                       FROM orders 
                       WHERE DATE(created_at) = CURDATE()";
        $ordersStmt = $this->db->query($ordersQuery);
        $stats['today_orders'] = $ordersStmt->fetch()['today_orders'];
        
        // Pending orders
        $pendingQuery = "SELECT COUNT(*) as pending_orders
                        FROM orders 
                        WHERE order_status = 'pending'";
        $pendingStmt = $this->db->query($pendingQuery);
        $stats['pending_orders'] = $pendingStmt->fetch()['pending_orders'];
        
        // Total menu items
        $menuQuery = "SELECT COUNT(*) as total_menu
                     FROM menu_items 
                     WHERE status = 'available'";
        $menuStmt = $this->db->query($menuQuery);
        $stats['total_menu'] = $menuStmt->fetch()['total_menu'];
        
        // Monthly revenue
        $monthlyQuery = "SELECT COALESCE(SUM(total_amount), 0) as monthly_revenue
                        FROM orders 
                        WHERE MONTH(created_at) = MONTH(CURDATE()) 
                        AND YEAR(created_at) = YEAR(CURDATE())
                        AND payment_status = 'paid'";
        $monthlyStmt = $this->db->query($monthlyQuery);
        $stats['monthly_revenue'] = $monthlyStmt->fetch()['monthly_revenue'];
        
        // Top selling items
        $topItemsQuery = "SELECT m.name, m.image, SUM(oi.quantity) as total_sold,
                         SUM(oi.subtotal) as total_revenue
                         FROM order_items oi
                         JOIN menu_items m ON oi.menu_item_id = m.id
                         JOIN orders o ON oi.order_id = o.id
                         WHERE o.payment_status = 'paid'
                         AND DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                         GROUP BY oi.menu_item_id
                         ORDER BY total_sold DESC
                         LIMIT 5";
        $topItemsStmt = $this->db->query($topItemsQuery);
        $stats['top_items'] = $topItemsStmt->fetchAll();
        
        // Recent orders
        $recentQuery = "SELECT o.*, u.username as cashier_name
                       FROM orders o
                       LEFT JOIN users u ON o.user_id = u.id
                       ORDER BY o.created_at DESC
                       LIMIT 10";
        $recentStmt = $this->db->query($recentQuery);
        $stats['recent_orders'] = $recentStmt->fetchAll();
        
        ApiResponse::success($stats);
    }
    
    public function getSalesReport($filters = []) {
        $dateFrom = isset($filters['date_from']) ? $filters['date_from'] : date('Y-m-01');
        $dateTo = isset($filters['date_to']) ? $filters['date_to'] : date('Y-m-d');
        
        // Daily sales
        $dailyQuery = "SELECT DATE(created_at) as date, 
                      COUNT(*) as total_orders,
                      SUM(total_amount) as total_revenue
                      FROM orders
                      WHERE DATE(created_at) BETWEEN :date_from AND :date_to
                      AND payment_status = 'paid'
                      GROUP BY DATE(created_at)
                      ORDER BY date ASC";
        
        $dailyStmt = $this->db->prepare($dailyQuery);
        $dailyStmt->bindParam(':date_from', $dateFrom);
        $dailyStmt->bindParam(':date_to', $dateTo);
        $dailyStmt->execute();
        
        $report = [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'daily_sales' => $dailyStmt->fetchAll(),
            'summary' => []
        ];
        
        // Summary
        $summaryQuery = "SELECT 
                        COUNT(*) as total_orders,
                        SUM(total_amount) as total_revenue,
                        AVG(total_amount) as average_order,
                        MAX(total_amount) as highest_order,
                        MIN(total_amount) as lowest_order
                        FROM orders
                        WHERE DATE(created_at) BETWEEN :date_from AND :date_to
                        AND payment_status = 'paid'";
        
        $summaryStmt = $this->db->prepare($summaryQuery);
        $summaryStmt->bindParam(':date_from', $dateFrom);
        $summaryStmt->bindParam(':date_to', $dateTo);
        $summaryStmt->execute();
        
        $report['summary'] = $summaryStmt->fetch();
        
        ApiResponse::success($report);
    }
}

// Initialize
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    ApiResponse::error("Database connection failed", 500);
}

$logger = new ActivityLogger($db);
$auth = new Auth($db, $logger);
$menuAPI = new MenuAPI($db, $logger);
$cartAPI = new CartAPI($db, $logger);
$orderAPI = new OrderAPI($db, $logger);
$statsAPI = new StatsAPI($db);

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';
$data = json_decode(file_get_contents('php://input'), true);

// Merge with $_POST for form data
if (!empty($_POST)) {
    $data = array_merge($data ?: [], $_POST);
}

// Route Handler
try {
    switch ($endpoint) {
        // Authentication endpoints
        case 'register':
            if ($method === 'POST') {
                $auth->register($data);
            }
            break;
            
        case 'login':
            if ($method === 'POST') {
                $auth->login($data);
            }
            break;
            
        case 'logout':
            if ($method === 'POST') {
                $auth->logout();
            }
            break;
        
        // Menu endpoints
        case 'menu':
            if ($method === 'GET') {
                $menuAPI->getAll($_GET);
            } elseif ($method === 'POST') {
                $auth->checkRole(['admin', 'manager']);
                $menuAPI->create($data);
            }
            break;
            
        case 'menu/item':
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            if (!$id) {
                ApiResponse::error("Menu item ID is required", 400);
            }
            
            if ($method === 'GET') {
                $menuAPI->getById($id);
            } elseif ($method === 'PUT') {
                $auth->checkRole(['admin', 'manager']);
                $menuAPI->update($id, $data);
            } elseif ($method === 'DELETE') {
                $auth->checkRole(['admin', 'manager']);
                $menuAPI->delete($id);
            }
            break;
        
        // Cart endpoints
        case 'cart':
            $userId = $auth->checkAuth();
            
            if ($method === 'GET') {
                $cartAPI->getCart($userId);
            } elseif ($method === 'POST') {
                $cartAPI->addToCart($userId, $data);
            } elseif ($method === 'DELETE') {
                $cartAPI->clearCart($userId);
            }
            break;
            
        case 'cart/item':
            $userId = $auth->checkAuth();
            $cartId = isset($_GET['id']) ? $_GET['id'] : null;
            
            if (!$cartId) {
                ApiResponse::error("Cart item ID is required", 400);
            }
            
            if ($method === 'PUT') {
                $cartAPI->updateCartItem($userId, $cartId, $data);
            } elseif ($method === 'DELETE') {
                $cartAPI->removeFromCart($userId, $cartId);
            }
            break;
        
        // Order endpoints
        case 'orders':
            $userId = $auth->checkAuth();
            
            if ($method === 'GET') {
                // Admin/Manager can see all orders
                if (in_array($_SESSION['role'], ['admin', 'manager'])) {
                    $orderAPI->getOrders($_GET);
                } else {
                    // Cashier only sees their own orders
                    $_GET['user_id'] = $userId;
                    $orderAPI->getOrders($_GET);
                }
            } elseif ($method === 'POST') {
                $orderAPI->createOrder($userId, $data);
            }
            break;
            
        case 'orders/item':
            $auth->checkAuth();
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            
            if (!$id) {
                ApiResponse::error("Order ID is required", 400);
            }
            
            if ($method === 'GET') {
                $orderAPI->getOrderById($id);
            }
            break;
            
        case 'orders/status':
            $auth->checkRole(['admin', 'manager', 'cashier']);
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            
            if (!$id) {
                ApiResponse::error("Order ID is required", 400);
            }
            
            if ($method === 'PUT') {
                $orderAPI->updateOrderStatus($id, $data);
            }
            break;
            
        case 'orders/payment':
            $auth->checkRole(['admin', 'manager', 'cashier']);
            $id = isset($_GET['id']) ? $_GET['id'] : null;
            
            if (!$id) {
                ApiResponse::error("Order ID is required", 400);
            }
            
            if ($method === 'PUT') {
                $orderAPI->updatePaymentStatus($id, $data);
            }
            break;
        
        // Statistics endpoints
        case 'stats/dashboard':
            $auth->checkAuth();
            if ($method === 'GET') {
                $statsAPI->getDashboardStats();
            }
            break;
            
        case 'stats/sales':
            $auth->checkRole(['admin', 'manager']);
            if ($method === 'GET') {
                $statsAPI->getSalesReport($_GET);
            }
            break;
        
        // Health check
        case 'health':
            ApiResponse::success([
                'status' => 'healthy',
                'database' => 'connected',
                'version' => '1.0.0'
            ]);
            break;
            
        default:
            ApiResponse::error("Endpoint not found: $endpoint", 404);
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    ApiResponse::error("Internal server error", 500);
}
