<?php
// File: dashboard.php
session_start();
require_once 'config.php';
require_once 'auth.php';

// Cek login
requireLogin();

// Ambil statistik
$query_menu = "SELECT COUNT(*) as total FROM menu";
$total_menu = mysqli_fetch_assoc(mysqli_query($conn, $query_menu))['total'];

$query_orders = "SELECT COUNT(*) as total FROM orders WHERE status = 'pending'";
$total_orders = mysqli_fetch_assoc(mysqli_query($conn, $query_orders))['total'];

$query_tables = "SELECT COUNT(*) as total FROM tables WHERE status = 'tersedia'";
$total_tables = mysqli_fetch_assoc(mysqli_query($conn, $query_tables))['total'];

$query_revenue = "SELECT SUM(total_amount) as total FROM orders WHERE status = 'selesai'";
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, $query_revenue))['total'] ?? 0;

// Ambil pesanan terbaru (kasir hanya lihat pesanannya sendiri)
if (isKasir()) {
    $user_filter = "AND o.user_id = " . $_SESSION['user_id'];
} else {
    $user_filter = "";
}

$query_recent = "SELECT o.*, t.table_number, u.fullname as kasir,
                 GROUP_CONCAT(CONCAT(m.name, ' (', oi.quantity, 'x)') SEPARATOR ', ') as items
                 FROM orders o 
                 LEFT JOIN tables t ON o.table_id = t.id
                 JOIN users u ON o.user_id = u.id
                 LEFT JOIN order_items oi ON o.id = oi.order_id
                 LEFT JOIN menu m ON oi.menu_id = m.id
                 WHERE 1=1 $user_filter
                 GROUP BY o.id
                 ORDER BY o.order_date DESC LIMIT 5";
$recent_orders = mysqli_query($conn, $query_recent);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Alugoro Cafe</title>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 24px;
        }
        
        .navbar .user-info {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .role-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .menu-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
        }
        
        .menu-card.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .menu-card .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .menu-card h3 {
            margin-bottom: 5px;
            color: #667eea;
        }
        
        .menu-card .admin-only {
            background: #ff9800;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            margin-top: 5px;
            display: inline-block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        
        .recent-orders {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .recent-orders h2 {
            margin-bottom: 20px;
            color: #333;
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
            color: #666;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status.selesai {
            background: #d4edda;
            color: #155724;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-logout {
            background: #ff4444;
            color: white;
        }
        
        .info-banner {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>☕ Alugoro Cafe</h1>
        <div class="user-info">
            <span>Halo, <?php echo $_SESSION['fullname']; ?></span>
            <span class="role-badge"><?php echo strtoupper($_SESSION['role']); ?></span>
            <a href="logout.php" class="btn btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if (isKasir()): ?>
        <div class="info-banner">
            <strong>ℹ️ Info Kasir:</strong> Anda hanya dapat mengelola pesanan. Menu bertanda "Admin Only" tidak dapat diakses.
        </div>
        <?php endif; ?>
        
        <h2 style="margin-bottom: 20px;">Menu Utama</h2>
        
        <div class="menu-grid">
            <!-- Menu: Kelola Menu - HANYA ADMIN -->
            <a href="menu.php" class="menu-card <?php echo isKasir() ? 'disabled' : ''; ?>">
                <div class="icon">🍽️</div>
                <h3>Kelola Menu</h3>
                <p>Tambah, edit, hapus menu</p>
                <?php if (isKasir()): ?>
                <span class="admin-only">Admin Only</span>
                <?php endif; ?>
            </a>
            
            <!-- Menu: Kelola Pesanan - SEMUA BISA AKSES -->
            <a href="orders.php" class="menu-card">
                <div class="icon">📋</div>
                <h3>Kelola Pesanan</h3>
                <p>Atur pesanan pelanggan</p>
            </a>
            
            <!-- Menu: Kelola Meja - HANYA ADMIN -->
            <a href="tables.php" class="menu-card <?php echo isKasir() ? 'disabled' : ''; ?>">
                <div class="icon">🪑</div>
                <h3>Kelola Meja</h3>
                <p>Atur status meja</p>
                <?php if (isKasir()): ?>
                <span class="admin-only">Admin Only</span>
                <?php endif; ?>
            </a>
            
            <!-- Menu: Laporan PDF - SEMUA BISA AKSES -->
            <a href="laporan.php" class="menu-card">
                <div class="icon">📄</div>
                <h3>Laporan PDF</h3>
                <p>Generate laporan</p>
            </a>
            
            <?php if (isAdmin()): ?>
            <!-- Menu: Kelola User - HANYA ADMIN -->
            <a href="users.php" class="menu-card">
                <div class="icon">👥</div>
                <h3>Kelola User</h3>
                <p>Manajemen pengguna</p>
                <span class="admin-only">Admin Only</span>
            </a>
            
            <!-- Menu: Activity Logs - HANYA ADMIN -->
            <a href="logs.php" class="menu-card">
                <div class="icon">📜</div>
                <h3>Riwayat Aktivitas</h3>
                <p>Log aktivitas sistem</p>
                <span class="admin-only">Admin Only</span>
            </a>
            <?php endif; ?>
        </div>
        
        <h2 style="margin-bottom: 20px;">Statistik</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>TOTAL MENU</h3>
                <div class="value"><?php echo $total_menu; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>PESANAN PENDING</h3>
                <div class="value"><?php echo $total_orders; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>MEJA TERSEDIA</h3>
                <div class="value"><?php echo $total_tables; ?></div>
            </div>
            
            <div class="stat-card">
                <h3>TOTAL PENDAPATAN</h3>
                <div class="value">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></div>
            </div>
        </div>
        
        <div class="recent-orders">
            <h2><?php echo isKasir() ? 'Pesanan Saya' : 'Pesanan Terbaru'; ?></h2>
            <table>
                <thead>
                    <tr>
                        <th>No. Order</th>
                        <th>Items</th>
                        <th>Meja</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Kasir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($recent_orders)): ?>
                    <tr>
                        <td><?php echo $row['order_number']; ?></td>
                        <td style="font-size: 12px;"><?php echo $row['items'] ?? '-'; ?></td>
                        <td><?php echo $row['table_number'] ?? '-'; ?></td>
                        <td>Rp <?php echo number_format($row['total_amount'], 0, ',', '.'); ?></td>
                        <td><span class="status <?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                        <td><?php echo $row['kasir']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>