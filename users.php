<?php
// File: users.php - Hanya Admin yang bisa akses
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireAdmin(); // HANYA ADMIN

// CREATE
if (isset($_POST['tambah'])) {
    $username = escape($_POST['username']);
    $password = md5($_POST['password']);
    $fullname = escape($_POST['fullname']);
    $role = escape($_POST['role']);
    
    $query = "INSERT INTO users (username, password, fullname, role) 
              VALUES ('$username', '$password', '$fullname', '$role')";
    
    if (mysqli_query($conn, $query)) {
        alert('User berhasil ditambahkan!');
        redirect('users.php');
    } else {
        alert('Error: Username sudah digunakan!');
    }
}

// UPDATE
if (isset($_POST['edit'])) {
    $id = escape($_POST['id']);
    $username = escape($_POST['username']);
    $fullname = escape($_POST['fullname']);
    $role = escape($_POST['role']);
    
    // Update password jika diisi
    if (!empty($_POST['password'])) {
        $password = md5($_POST['password']);
        $password_update = ", password='$password'";
    } else {
        $password_update = "";
    }
    
    $query = "UPDATE users SET username='$username', fullname='$fullname', role='$role' $password_update WHERE id='$id'";
    
    if (mysqli_query($conn, $query)) {
        alert('User berhasil diupdate!');
        redirect('users.php');
    }
}

// DELETE
if (isset($_GET['delete'])) {
    $id = escape($_GET['delete']);
    
    // Cegah hapus diri sendiri
    if ($id == $_SESSION['user_id']) {
        alert('Tidak bisa menghapus akun sendiri!');
        redirect('users.php');
    }
    
    mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
    alert('User berhasil dihapus!');
    redirect('users.php');
}

// READ
$query = "SELECT * FROM users ORDER BY role, fullname";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Alugoro Cafe</title>
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
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .alert {
            background: #ff9800;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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
        
        .role-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .role-badge.admin {
            background: #d4edda;
            color: #155724;
        }
        
        .role-badge.kasir {
            background: #d1ecf1;
            color: #0c5460;
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
        <h1>☕ Alugoro Cafe - Kelola User (Admin Only)</h1>
        <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
    </div>
    
    <div class="container">
        <div class="alert">
            <strong>⚠️ Admin Only:</strong> Hanya Administrator yang dapat mengelola user/pengguna sistem.
        </div>
        
        <div class="header">
            <h2>Daftar Pengguna</h2>
            <button class="btn btn-primary" onclick="openModal('tambah')">+ Tambah User</button>
        </div>
        
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Role</th>
                        <th>Terdaftar</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    while ($row = mysqli_fetch_assoc($result)): 
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><strong><?php echo $row['username']; ?></strong></td>
                        <td><?php echo $row['fullname']; ?></td>
                        <td><span class="role-badge <?php echo $row['role']; ?>"><?php echo ucfirst($row['role']); ?></span></td>
                        <td><?php echo date('d/m/Y', strtotime($row['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-warning" onclick='openEdit(<?php echo json_encode($row); ?>)'>Edit</button>
                            <?php if ($row['id'] != $_SESSION['user_id']): ?>
                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus user ini?')">Hapus</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Modal Tambah -->
    <div id="modalTambah" class="modal">
        <div class="modal-content">
            <h2>Tambah User Baru</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="fullname" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="kasir">Kasir</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" name="tambah" class="btn btn-success">Simpan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('tambah')">Batal</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Edit -->
    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <h2>Edit User</h2>
            <form method="POST">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="edit_username" required>
                </div>
                <div class="form-group">
                    <label>Password Baru (Kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" id="edit_password">
                </div>
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="fullname" id="edit_fullname" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit_role" required>
                        <option value="kasir">Kasir</option>
                        <option value="admin">Admin</option>
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
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_fullname').value = data.fullname;
            document.getElementById('edit_role').value = data.role;
            document.getElementById('edit_password').value = '';
            openModal('edit');
        }
    </script>
</body>
</html>