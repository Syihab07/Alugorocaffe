<?php
// File: logs.php - Riwayat Aktivitas (Admin Only)
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireAdmin();

// Filter
$filter_user = isset($_GET['user']) ? escape($_GET['user']) : '';
$filter_action = isset($_GET['action']) ? escape($_GET['action']) : '';
$filter_date = isset($_GET['date']) ? escape($_GET['date']) : '';

// Build query
$where_conditions = [];

if (!empty($filter_user)) {
    $where_conditions[] = "l.user_id = '$filter_user'";
}

if (!empty($filter_action)) {
    $where_conditions[] = "l.action = '$filter_action'";
}

if (!empty($filter_date)) {
    $where_conditions[] = "DATE(l.created_at) = '$filter_date'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query = "SELECT l.*, u.fullname, u.username, u.role 
          FROM activity_logs l
          JOIN users u ON l.user_id = u.id
          $where_clause
          ORDER BY l.created_at DESC
          LIMIT 100";
$result = mysqli_query($conn, $query);

// Get users for filter
$users_query = mysqli_query($conn, "SELECT id, fullname, username FROM users ORDER BY fullname");

// Get action types
$actions_query = mysqli_query($conn, "SELECT DISTINCT action FROM activity_logs ORDER BY action");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Aktivitas - Alugoro Cafe</title>
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
        }
        
        .btn-secondary { background: #6c757d; color: white; }
        .btn-primary { background: #667eea; color: white; }
        
        .alert {
            background: #ff9800;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .timeline {
            position: relative;
            padding-left: 50px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -44px;
            top: 20px;
            width: 12px;
            height: 12px;
            background: #667eea;
            border-radius: 50%;
            border: 3px solid white;
        }
        
        .timeline-item .header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .timeline-item .action {
            display: inline-block;
            padding: 4px 10px;
            background: #667eea;
            color: white;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .timeline-item .user-info {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .timeline-item .description {
            color: #666;
            margin-bottom: 8px;
        }
        
        .timeline-item .meta {
            font-size: 12px;
            color: #999;
        }
        
        .role-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .role-badge.admin {
            background: #d4edda;
            color: #155724;
        }
        
        .role-badge.kasir {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>☕ Alugoro Cafe - Riwayat Aktivitas</h1>
        <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
    </div>
    
    <div class="container">
        <div class="alert">
            <strong>🔒 Admin Only:</strong> Halaman ini menampilkan semua aktivitas pengguna dalam sistem.
        </div>
        
        <!-- Filter -->
        <div class="filter-card">
            <h3 style="margin-bottom: 15px;">🔍 Filter Aktivitas</h3>
            <form method="GET">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>Pengguna</label>
                        <select name="user">
                            <option value="">Semua Pengguna</option>
                            <?php while ($user = mysqli_fetch_assoc($users_query)): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo $user['fullname']; ?> (<?php echo $user['username']; ?>)
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jenis Aktivitas</label>
                        <select name="action">
                            <option value="">Semua Aktivitas</option>
                            <?php while ($action = mysqli_fetch_assoc($actions_query)): ?>
                            <option value="<?php echo $action['action']; ?>" <?php echo $filter_action == $action['action'] ? 'selected' : ''; ?>>
                                <?php echo ucwords(str_replace('_', ' ', $action['action'])); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="logs.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2 style="margin-bottom: 20px;">📋 Timeline Aktivitas</h2>
            
            <?php if (mysqli_num_rows($result) > 0): ?>
            <div class="timeline">
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <div class="timeline-item">
                    <div class="header">
                        <div>
                            <span class="action"><?php echo ucwords(str_replace('_', ' ', $row['action'])); ?></span>
                        </div>
                        <div class="meta">
                            <?php echo date('d/m/Y H:i:s', strtotime($row['created_at'])); ?>
                        </div>
                    </div>
                    <div class="user-info">
                        👤 <?php echo $row['fullname']; ?> (<?php echo $row['username']; ?>)
                        <span class="role-badge <?php echo $row['role']; ?>"><?php echo ucfirst($row['role']); ?></span>
                    </div>
                    <div class="description">
                        <?php echo $row['description']; ?>
                    </div>
                    <div class="meta">
                        📍 IP: <?php echo $row['ip_address']; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #999;">
                <h3>📭 Tidak ada aktivitas ditemukan</h3>
                <p>Coba ubah filter pencarian</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>