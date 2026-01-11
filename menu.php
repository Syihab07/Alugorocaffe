<?php
// File: menu.php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();
requireAdmin(); // Hanya admin yang bisa akses

// CREATE - Tambah menu
if (isset($_POST['tambah'])) {
    $name = escape($_POST['name']);
    $category = escape($_POST['category']);
    $price = escape($_POST['price']);
    $description = escape($_POST['description']);
    $stock = escape($_POST['stock']);
    
    $query = "INSERT INTO menu (name, category, price, description, stock) 
              VALUES ('$name', '$category', '$price', '$description', '$stock')";
    
    if (mysqli_query($conn, $query)) {
        alert('Menu berhasil ditambahkan!');
        redirect('menu.php');
    }
}

// UPDATE - Edit menu
if (isset($_POST['edit'])) {
    $id = escape($_POST['id']);
    $name = escape($_POST['name']);
    $category = escape($_POST['category']);
    $price = escape($_POST['price']);
    $description = escape($_POST['description']);
    $stock = escape($_POST['stock']);
    
    $query = "UPDATE menu SET name='$name', category='$category', price='$price', 
              description='$description', stock='$stock' WHERE id='$id'";
    
    if (mysqli_query($conn, $query)) {
        alert('Menu berhasil diupdate!');
        redirect('menu.php');
    }
}

// DELETE - Hapus menu
if (isset($_GET['delete'])) {
    $id = escape($_GET['delete']);
    $query = "DELETE FROM menu WHERE id='$id'";
    
    if (mysqli_query($conn, $query)) {
        alert('Menu berhasil dihapus!');
        redirect('menu.php');
    }
}

// READ - Ambil semua menu
$query = "SELECT * FROM menu ORDER BY category, name";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - Alugoro Cafe</title>
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
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .category-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .category-badge.makanan {
            background: #d4edda;
            color: #155724;
        }
        
        .category-badge.minuman {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .category-badge.dessert {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>☕ Alugoro Cafe - Kelola Menu</h1>
        <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
    </div>
    
    <div class="container">
        <div class="header">
            <h2>Daftar Menu</h2>
            <button class="btn btn-primary" onclick="openModal('tambah')">+ Tambah Menu</button>
        </div>
        
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Menu</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Stok</th>
                        <th>Deskripsi</th>
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
                        <td><?php echo $row['name']; ?></td>
                        <td><span class="category-badge <?php echo $row['category']; ?>"><?php echo ucfirst($row['category']); ?></span></td>
                        <td>Rp <?php echo number_format($row['price'], 0, ',', '.'); ?></td>
                        <td><?php echo $row['stock']; ?></td>
                        <td><?php echo substr($row['description'], 0, 50); ?>...</td>
                        <td>
                            <button class="btn btn-warning" onclick='openEdit(<?php echo json_encode($row); ?>)'>Edit</button>
                            <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus menu ini?')">Hapus</a>
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
            <h2>Tambah Menu Baru</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Nama Menu</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category" required>
                        <option value="makanan">Makanan</option>
                        <option value="minuman">Minuman</option>
                        <option value="dessert">Dessert</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Harga</label>
                    <input type="number" name="price" required>
                </div>
                <div class="form-group">
                    <label>Stok</label>
                    <input type="number" name="stock" required>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description" rows="3" required></textarea>
                </div>
                <button type="submit" name="tambah" class="btn btn-success">Simpan</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('tambah')">Batal</button>
            </form>
        </div>
    </div>
    
    <!-- Modal Edit -->
    <div id="modalEdit" class="modal">
        <div class="modal-content">
            <h2>Edit Menu</h2>
            <form method="POST" id="formEdit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label>Nama Menu</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category" id="edit_category" required>
                        <option value="makanan">Makanan</option>
                        <option value="minuman">Minuman</option>
                        <option value="dessert">Dessert</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Harga</label>
                    <input type="number" name="price" id="edit_price" required>
                </div>
                <div class="form-group">
                    <label>Stok</label>
                    <input type="number" name="stock" id="edit_stock" required>
                </div>
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="description" id="edit_description" rows="3" required></textarea>
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
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_category').value = data.category;
            document.getElementById('edit_price').value = data.price;
            document.getElementById('edit_stock').value = data.stock;
            document.getElementById('edit_description').value = data.description;
            openModal('edit');
        }
    </script>
</body>
</html>