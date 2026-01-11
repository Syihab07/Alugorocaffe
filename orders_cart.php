<?php
// File: orders_cart.php - Pesanan dengan Multiple Items (Cart System)
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

// CREATE ORDER dengan multiple items
if (isset($_POST['create_order'])) {
    $order_number = 'ORD' . date('YmdHis');
    $table_id = $_POST['table_id'] != '' ? escape($_POST['table_id']) : 'NULL';
    $customer_name = escape($_POST['customer_name']);
    $user_id = $_SESSION['user_id'];
    $cart = json_decode($_POST['cart_data'], true);
    
    if (!empty($cart)) {
        // Calculate total
        $total_amount = 0;
        foreach ($cart as $item) {
            $total_amount += $item['subtotal'];
        }
        
        // Insert order header
        $query = "INSERT INTO orders (order_number, table_id, customer_name, user_id, total_amount) 
                  VALUES ('$order_number', " . ($table_id == 'NULL' ? 'NULL' : "'$table_id'") . ", '$customer_name', '$user_id', '$total_amount')";
        
        if (mysqli_query($conn, $query)) {
            $order_id = mysqli_insert_id($conn);
            
            // Insert order items
            foreach ($cart as $item) {
                $menu_id = escape($item['menu_id']);
                $quantity = escape($item['quantity']);
                $price = escape($item['price']);
                $subtotal = escape($item['subtotal']);
                
                $item_query = "INSERT INTO order_items (order_id, menu_id, quantity, price, subtotal) 
                               VALUES ('$order_id', '$menu_id', '$quantity', '$price', '$subtotal')";
                mysqli_query($conn, $item_query);
            }
            
            // Update table status
            if ($table_id != 'NULL') {
                mysqli_query($conn, "UPDATE tables SET status='terisi' WHERE id='$table_id'");
            }
            
            // Log activity
            logActivity($user_id, 'create_order', "Membuat pesanan $order_number untuk $customer_name dengan " . count($cart) . " item");
            
            alert('Pesanan berhasil dibuat!');
            redirect('orders_cart.php');
        }
    } else {
        alert('Keranjang kosong!');
    }
}

// UPDATE Status
if (isset($_POST['update_status'])) {
    $id = escape($_POST['id']);
    $status = escape($_POST['status']);
    
    $query = "UPDATE orders SET status='$status' WHERE id='$id'";
    
    if (mysqli_query($conn, $query)) {
        if ($status == 'selesai') {
            $order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT table_id FROM orders WHERE id='$id'"));
            if ($order['table_id']) {
                mysqli_query($conn, "UPDATE tables SET status='tersedia' WHERE id='" . $order['table_id'] . "'");
            }
        }
        
        logActivity($_SESSION['user_id'], 'update_order', "Update status pesanan menjadi $status");
        alert('Status pesanan berhasil diupdate!');
        redirect('orders_cart.php');
    }
}

// DELETE
if (isset($_GET['delete'])) {
    $id = escape($_GET['delete']);
    // Items akan auto-delete karena CASCADE
    mysqli_query($conn, "DELETE FROM orders WHERE id='$id'");
    logActivity($_SESSION['user_id'], 'delete_order', "Menghapus pesanan");
    alert('Pesanan berhasil dihapus!');
    redirect('orders_cart.php');
}

// Search & Filter
$search = isset($_GET['search']) ? escape($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? escape($_GET['status']) : '';
$filter_date = isset($_GET['date']) ? escape($_GET['date']) : '';

$where_conditions = [];

if (isKasir()) {
    $where_conditions[] = "o.user_id = " . $_SESSION['user_id'];
}

if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE '%$search%' OR o.customer_name LIKE '%$search%')";
}

if (!empty($filter_status)) {
    $where_conditions[] = "o.status = '$filter_status'";
}

if (!empty($filter_date)) {
    $where_conditions[] = "DATE(o.order_date) = '$filter_date'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// READ with JOIN to get items
$query = "SELECT o.id, o.order_number, o.table_id, o.customer_name, o.user_id, 
          o.order_date, o.total_amount, o.status,
          t.table_number, u.fullname as kasir,
          GROUP_CONCAT(CONCAT(m.name, ' (', oi.quantity, 'x)') SEPARATOR ', ') as items,
          COUNT(oi.id) as total_items
          FROM orders o 
          LEFT JOIN tables t ON o.table_id = t.id
          JOIN users u ON o.user_id = u.id
          LEFT JOIN order_items oi ON o.id = oi.order_id
          LEFT JOIN menu m ON oi.menu_id = m.id
          $where_clause
          GROUP BY o.id, o.order_number, o.table_id, o.customer_name, o.user_id, 
                   o.order_date, o.total_amount, o.status, t.table_number, u.fullname
          ORDER BY o.order_date DESC";
$result = mysqli_query($conn, $query);

// Stats
$stats_query = "SELECT 
    COUNT(DISTINCT o.id) as total_orders,
    SUM(CASE WHEN o.status='pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN o.status='selesai' THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN o.status='dibatalkan' THEN 1 ELSE 0 END) as dibatalkan,
    SUM(CASE WHEN o.status='selesai' THEN o.total_amount ELSE 0 END) as total_revenue
    FROM orders o 
    LEFT JOIN tables t ON o.table_id = t.id
    JOIN users u ON o.user_id = u.id
    $where_clause";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $stats_query));

// Data untuk form
$menu_list = mysqli_query($conn, "SELECT * FROM menu WHERE stock > 0 ORDER BY category, name");
$table_list = mysqli_query($conn, "SELECT * FROM tables WHERE status='tersedia' ORDER BY table_number");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Alugoro Cafe</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin: 2px;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        
        .filter-card, .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-mini {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-mini h4 {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-mini .value {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status.pending { background: #fff3cd; color: #856404; }
        .status.selesai { background: #d4edda; color: #155724; }
        .status.dibatalkan { background: #f8d7da; color: #721c24; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal.active {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .menu-item {
            border: 2px solid #e0e0e0;
            padding: 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .menu-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .menu-item.selected {
            border-color: #667eea;
            background: #e7f0ff;
        }
        
        .menu-item h4 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .menu-item .price {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .menu-item .category {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-bottom: 10px;
        }
        
        .category.makanan { background: #d4edda; color: #155724; }
        .category.minuman { background: #d1ecf1; color: #0c5460; }
        .category.dessert { background: #f8d7da; color: #721c24; }
        
        .cart-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .cart-total {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            color: #667eea;
            padding-top: 15px;
            border-top: 2px solid #667eea;
            margin-top: 15px;
        }
        
        .items-preview {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>☕ Alugoro Cafe - Kelola Pesanan (Multiple Items)</h1>
        <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
    </div>
    
    <div class="container">
        <!-- Filter -->
        <div class="filter-card">
            <h3 style="margin-bottom: 15px;">🔍 Cari & Filter Pesanan</h3>
            <form method="GET">
                <div class="filter-grid">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Cari (No. Order / Nama Pelanggan)</label>
                        <input type="text" name="search" placeholder="Ketik untuk mencari..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="selesai" <?php echo $filter_status == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                            <option value="dibatalkan" <?php echo $filter_status == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Tanggal</label>
                        <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="orders_cart.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-mini">
                <h4>TOTAL PESANAN</h4>
                <div class="value"><?php echo $stats['total_orders']; ?></div>
            </div>
            <div class="stat-mini">
                <h4>PENDING</h4>
                <div class="value" style="color: #ffc107;"><?php echo $stats['pending']; ?></div>
            </div>
            <div class="stat-mini">
                <h4>SELESAI</h4>
                <div class="value" style="color: #28a745;"><?php echo $stats['selesai']; ?></div>
            </div>
            <div class="stat-mini">
                <h4>DIBATALKAN</h4>
                <div class="value" style="color: #dc3545;"><?php echo $stats['dibatalkan']; ?></div>
            </div>
            <div class="stat-mini">
                <h4>TOTAL PENDAPATAN</h4>
                <div class="value" style="font-size: 18px;">Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></div>
            </div>
        </div>
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Daftar Pesanan</h2>
            <button class="btn btn-primary" onclick="openModal()">🛒 Buat Pesanan Baru</button>
        </div>
        
        <!-- Table -->
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>No. Order</th>
                        <th>Pelanggan</th>
                        <th>Meja</th>
                        <th>Items</th>
                        <th>Total Items</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th>Kasir</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><strong><?php echo $row['order_number']; ?></strong></td>
                        <td><?php echo $row['customer_name']; ?></td>
                        <td><?php echo $row['table_number'] ?? 'Take Away'; ?></td>
                        <td><span class="items-preview"><?php echo $row['items'] ?? '-'; ?></span></td>
                        <td><strong><?php echo $row['total_items']; ?> item</strong></td>
                        <td><strong>Rp <?php echo number_format($row['total_amount'], 0, ',', '.'); ?></strong></td>
                        <td><span class="status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                        <td><?php echo $row['kasir']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['order_date'])); ?></td>
                        <td>
                            <?php if ($row['status'] == 'pending'): ?>
                            <button class="btn btn-success btn-sm" onclick='updateStatus(<?php echo $row['id']; ?>, "selesai")'>✓ Selesai</button>
                            <button class="btn btn-danger btn-sm" onclick='updateStatus(<?php echo $row['id']; ?>, "dibatalkan")'>✗ Batalkan</button>
                            <?php elseif ($row['status'] == 'selesai'): ?>
                            <span style="color: #28a745;">✓ Sudah Selesai</span>
                            <?php elseif ($row['status'] == 'dibatalkan'): ?>
                            <span style="color: #dc3545;">✗ Dibatalkan</span>
                            <?php endif; ?>
                            <br>
                            <button class="btn btn-sm" style="background: #17a2b8; color: white; margin-top: 5px;" onclick='viewDetail(<?php echo $row['id']; ?>)'>👁️ Detail</button>
                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" style="margin-top: 5px;" onclick="return confirm('Yakin hapus pesanan ini?')">🗑️ Hapus</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Create Order -->
    <div id="modalOrder" class="modal">
        <div class="modal-content">
            <h2>🛒 Buat Pesanan Baru (Multiple Items)</h2>
            <form method="POST" id="formOrder">
                <div class="form-group">
                    <label>Nama Pelanggan *</label>
                    <input type="text" name="customer_name" required>
                </div>
                
                <div class="form-group">
                    <label>Meja (Opsional - Kosongkan untuk Take Away)</label>
                    <select name="table_id">
                        <option value="">Take Away</option>
                        <?php while ($table = mysqli_fetch_assoc($table_list)): ?>
                        <option value="<?php echo $table['id']; ?>">
                            Meja <?php echo $table['table_number']; ?> (<?php echo $table['capacity']; ?> orang)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <hr style="margin: 20px 0;">
                
                <h3>Pilih Menu (Klik untuk tambah ke keranjang)</h3>
                <div class="menu-grid">
                    <?php while ($menu = mysqli_fetch_assoc($menu_list)): ?>
                    <div class="menu-item" onclick='addToCart(<?php echo json_encode($menu); ?>)'>
                        <span class="category <?php echo $menu['category']; ?>"><?php echo ucfirst($menu['category']); ?></span>
                        <h4><?php echo $menu['name']; ?></h4>
                        <div class="price">Rp <?php echo number_format($menu['price'], 0, ',', '.'); ?></div>
                        <small>Stok: <?php echo $menu['stock']; ?></small>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="cart-section">
                    <h3>🛒 Keranjang Pesanan</h3>
                    <div id="cartItems"></div>
                    <div class="cart-total" id="cartTotal">Total: Rp 0</div>
                </div>
                
                <input type="hidden" name="cart_data" id="cartData">
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="create_order" class="btn btn-success">✓ Buat Pesanan</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Form Status -->
    <form method="POST" id="formStatus" style="display:none;">
        <input type="hidden" name="id" id="status_id">
        <input type="hidden" name="status" id="status_value">
        <input type="hidden" name="update_status" value="1">
    </form>
    
    <!-- Modal Detail Pesanan -->
    <div id="modalDetail" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <h2>📋 Detail Pesanan</h2>
            <div id="detailContent"></div>
            <button type="button" class="btn btn-secondary" onclick="closeDetailModal()" style="margin-top: 20px;">Tutup</button>
        </div>
    </div>
    
    <script>
        let cart = [];
        
        function openModal() {
            cart = [];
            updateCartDisplay();
            document.getElementById('modalOrder').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('modalOrder').classList.remove('active');
        }
        
        function addToCart(menu) {
            // Check if already in cart
            const existingIndex = cart.findIndex(item => item.menu_id === menu.id);
            
            if (existingIndex >= 0) {
                // Increase quantity
                cart[existingIndex].quantity++;
                cart[existingIndex].subtotal = cart[existingIndex].quantity * cart[existingIndex].price;
            } else {
                // Add new item
                cart.push({
                    menu_id: menu.id,
                    name: menu.name,
                    price: parseFloat(menu.price),
                    quantity: 1,
                    subtotal: parseFloat(menu.price)
                });
            }
            
            updateCartDisplay();
        }
        
        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }
        
        function updateQuantity(index, change) {
            cart[index].quantity += change;
            
            if (cart[index].quantity <= 0) {
                removeFromCart(index);
            } else {
                cart[index].subtotal = cart[index].quantity * cart[index].price;
                updateCartDisplay();
            }
        }
        
        function updateCartDisplay() {
            const cartItemsDiv = document.getElementById('cartItems');
            const cartTotalDiv = document.getElementById('cartTotal');
            const cartDataInput = document.getElementById('cartData');
            
            if (cart.length === 0) {
                cartItemsDiv.innerHTML = '<p style="text-align:center; color:#999; padding:20px;">Keranjang kosong. Klik menu untuk menambah item.</p>';
                cartTotalDiv.innerHTML = 'Total: Rp 0';
                cartDataInput.value = '';
                return;
            }
            
            let html = '';
            let total = 0;
            
            cart.forEach((item, index) => {
                total += item.subtotal;
                html += `
                    <div class="cart-item">
                        <div>
                            <strong>${item.name}</strong><br>
                            <small>Rp ${item.price.toLocaleString('id-ID')} x ${item.quantity}</small>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="updateQuantity(${index}, -1)">-</button>
                            <strong>${item.quantity}</strong>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="updateQuantity(${index}, 1)">+</button>
                            <strong style="min-width: 100px; text-align: right;">Rp ${item.subtotal.toLocaleString('id-ID')}</strong>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeFromCart(${index})">×</button>
                        </div>
                    </div>
                `;
            });
            
            cartItemsDiv.innerHTML = html;
            cartTotalDiv.innerHTML = `Total: Rp ${total.toLocaleString('id-ID')}`;
            cartDataInput.value = JSON.stringify(cart);
        }
        
        function updateStatus(id, status) {
            let message = '';
            if (status === 'selesai') {
                message = 'Tandai pesanan ini sebagai SELESAI?\n\nPesanan yang sudah selesai tidak bisa diubah lagi.';
            } else if (status === 'dibatalkan') {
                message = 'Yakin MEMBATALKAN pesanan ini?\n\nPesanan yang dibatalkan tidak bisa diubah lagi.';
            }
            
            if (confirm(message)) {
                document.getElementById('status_id').value = id;
                document.getElementById('status_value').value = status;
                document.getElementById('formStatus').submit();
            }
        }
        
        function viewDetail(orderId) {
            // Fetch order detail via AJAX
            fetch('get_order_detail.php?id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = `
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 15px;">
                                <table style="width: 100%; border: none;">
                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                        <td style="padding: 10px; font-weight: 600; border: none;">No. Order:</td>
                                        <td style="padding: 10px; border: none;">${data.order.order_number}</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                        <td style="padding: 10px; font-weight: 600; border: none;">Pelanggan:</td>
                                        <td style="padding: 10px; border: none;">${data.order.customer_name}</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                        <td style="padding: 10px; font-weight: 600; border: none;">Meja:</td>
                                        <td style="padding: 10px; border: none;">${data.order.table_number || 'Take Away'}</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                        <td style="padding: 10px; font-weight: 600; border: none;">Tanggal:</td>
                                        <td style="padding: 10px; border: none;">${data.order.order_date}</td>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                        <td style="padding: 10px; font-weight: 600; border: none;">Kasir:</td>
                                        <td style="padding: 10px; border: none;">${data.order.kasir}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px; font-weight: 600; border: none;">Status:</td>
                                        <td style="padding: 10px; border: none;">
                                            <span class="status ${data.order.status}">${data.order.status_text}</span>
                                        </td>
                                    </tr>
                                </table>
                                
                                <h3 style="margin-top: 20px; margin-bottom: 10px;">🍽️ Items Pesanan:</h3>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #e9ecef;">
                                            <th style="padding: 10px; text-align: left; border: 1px solid #dee2e6;">Menu</th>
                                            <th style="padding: 10px; text-align: center; border: 1px solid #dee2e6;">Qty</th>
                                            <th style="padding: 10px; text-align: right; border: 1px solid #dee2e6;">Harga</th>
                                            <th style="padding: 10px; text-align: right; border: 1px solid #dee2e6;">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                        
                        data.items.forEach(item => {
                            html += `
                                <tr>
                                    <td style="padding: 10px; border: 1px solid #dee2e6;">${item.menu_name}</td>
                                    <td style="padding: 10px; text-align: center; border: 1px solid #dee2e6;">${item.quantity}</td>
                                    <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6;">Rp ${item.price_formatted}</td>
                                    <td style="padding: 10px; text-align: right; border: 1px solid #dee2e6;"><strong>Rp ${item.subtotal_formatted}</strong></td>
                                </tr>`;
                        });
                        
                        html += `
                                    </tbody>
                                    <tfoot>
                                        <tr style="background: #f8f9fa; font-weight: bold;">
                                            <td colspan="3" style="padding: 15px; text-align: right; border: 1px solid #dee2e6;">TOTAL:</td>
                                            <td style="padding: 15px; text-align: right; color: #667eea; font-size: 18px; border: 1px solid #dee2e6;">Rp ${data.order.total_formatted}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        `;
                        
                        document.getElementById('detailContent').innerHTML = html;
                        document.getElementById('modalDetail').classList.add('active');
                    } else {
                        alert('Gagal memuat detail pesanan');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat detail');
                });
        }
        
        function closeDetailModal() {
            document.getElementById('modalDetail').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('modalOrder');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>