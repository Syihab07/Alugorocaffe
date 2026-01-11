<?php
// File: tables.php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireAdmin(); // Hanya admin yang bisa akses

// CREATE
if (isset($_POST['tambah'])) {
    $table_number = escape($_POST['table_number']);
    $capacity = escape($_POST['capacity']);
    
    $query = "INSERT INTO tables (table_number, capacity) VALUES ('$table_number', '$capacity')";
    
    if (mysqli_query($conn, $query)) {
        alert('Meja berhasil ditambahkan!');
        redirect('tables.php');
    }
}

// UPDATE
if (isset($_POST['edit'])) {
    $id = escape($_POST['id']);
    $table_number = escape($_POST['table_number']);
    $capacity = escape($_POST['capacity']);
    $status = escape($_POST['status']);
    
    $query = "UPDATE tables SET table_number='$table_number', capacity='$capacity', status='$status' WHERE id='$id'";
    
    if (mysqli_query($conn, $query)) {
        alert('Meja berhasil diupdate!');
        redirect('tables.php');
    }
}

// DELETE
if (isset($_GET['delete'])) {
    $id = escape($_GET['delete']);
    mysqli_query($conn, "DELETE FROM tables WHERE id='$id'");
    alert('Meja berhasil dihapus!');
    redirect('tables.php');
}

// READ
$query = "SELECT * FROM tables ORDER BY table_number";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Meja - Alugoro Cafe</title>
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
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
        
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .table-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .table-card.tersedia {
            border-left: 5px solid #28a745;
        }
        
        .table-card.terisi {
            border-left: 5px solid #dc3545;
        }
        
        .table-number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .table-info {
            margin-bottom: 15px;
            color: #666;
        }
        
        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        .status-badge.tersedia {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.terisi {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
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
    </style>
</head>
<body>
    <div class="navbar">
        <h1>☕ Alugoro Cafe - Kelola Meja</h1>
        <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
    </div>
    
    <div class="container">
        <div class="header">
            <h2>Daftar Meja</h2>
            <button class="btn btn-primary" onclick="openModal('tambah')">+ Tambah Meja</button>
        </div>
        
        <div class="table-grid">
            <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <div class="table-card <?php echo $row['status']; ?>">
                <div class="table-number">🪑 <?php echo $row['table_number']; ?></div>
                <div class="table-info">Kapasitas: <?php echo $row['capacity']; ?> orang</div>
                <span class="status-badge <?php echo $row['status']; ?>">
                    <?php echo $row['status'] == 'tersedia' ? '✓ Tersedia' : '✗ Terisi'; ?>
                </span>
                <div>
                    <button class="btn btn-warning" onclick='openEdit(<?php echo json_encode($row); ?>)'>Edit</button>
                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus meja ini?')">Hapus</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    
    <!-- Modal Tambah -->
    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <h2>Tambah Meja Baru</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Nomor Meja</label>
                    <input type="text" name="table_number" placeholder="Contoh: M09" required>
                </div>
                <div class="form-group">
                    <label>Kapasitas (orang)</label>
                    <input type="number" name="capacity" min="1" required>
                </div>
                <button type="submit" name="tambah" class="btn btn-success">Simpan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('tambah')">Batal</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Edit -->
    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <h2>Edit Meja</h2>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Nomor Meja</label>
                    <input type="text" name="table_number" id="edit_table_number" required>
                </div>
                <div class="form-group">
                    <label>Kapasitas (orang)</label>
                    <input type="number" name="capacity" id="edit_capacity" min="1" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="tersedia">Tersedia</option>
                        <option value="terisi">Terisi</option>
                    </select>
                </div>
                <button type="submit" name="edit" class="btn btn-success">Update</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit')">Batal</button>
            </form>
        </div>
    </div>
    
    <script>
        function openModal(type) {
            document.getElementById('modal' + type.charAt(0).toUpperCase() + type.slice(1)).classList.add('active');
        }
        
        function closeModal(type) {
            document.getElementById('modal' + type.charAt(0).toUpperCase() + type.slice(1)).classList.remove('active');
        }
        
        function openEdit(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_table_number').value = data.table_number;
            document.getElementById('edit_capacity').value = data.capacity;
            document.getElementById('edit_status').value = data.status;
            openModal('edit');
        }
    </script>
</body>
</html>