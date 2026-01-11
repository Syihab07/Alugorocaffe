<?php
// File: laporan.php
session_start();
require_once 'config.php';
require_once 'auth.php';

requireLogin();

// Jika ada request generate PDF
if (isset($_GET['generate'])) {
    require_once('fpdf/fpdf.php');
    
    $type = $_GET['type'];
    
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, 'ALUGORO CAFE', 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, 'Sistem Manajemen Restoran', 0, 1, 'C');
            $this->Ln(5);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Halaman ' . $this->PageNo(), 0, 0, 'C');
        }
    }
    
    $pdf = new PDF();
    $pdf->AddPage();
    
    if ($type == 'menu') {
        // Laporan Menu
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'LAPORAN DATA MENU', 0, 1, 'C');
        $pdf->Ln(5);
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(10, 7, 'No', 1);
        $pdf->Cell(70, 7, 'Nama Menu', 1);
        $pdf->Cell(30, 7, 'Kategori', 1);
        $pdf->Cell(40, 7, 'Harga', 1);
        $pdf->Cell(30, 7, 'Stok', 1);
        $pdf->Ln();
        
        $query = mysqli_query($conn, "SELECT * FROM menu ORDER BY category, name");
        $no = 1;
        $pdf->SetFont('Arial', '', 9);
        
        while ($row = mysqli_fetch_assoc($query)) {
            $pdf->Cell(10, 6, $no++, 1);
            $pdf->Cell(70, 6, $row['name'], 1);
            $pdf->Cell(30, 6, ucfirst($row['category']), 1);
            $pdf->Cell(40, 6, 'Rp ' . number_format($row['price'], 0, ',', '.'), 1);
            $pdf->Cell(30, 6, $row['stock'], 1);
            $pdf->Ln();
        }
        
    } elseif ($type == 'orders') {
        // Laporan Pesanan dengan Total Pendapatan (hanya Selesai)
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'LAPORAN PESANAN', 0, 1, 'C');
        $pdf->Ln(5);
        
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(30, 7, 'No. Order', 1);
        $pdf->Cell(40, 7, 'Pelanggan', 1);
        $pdf->Cell(20, 7, 'Meja', 1);
        $pdf->Cell(15, 7, 'Items', 1);
        $pdf->Cell(35, 7, 'Total', 1);
        $pdf->Cell(25, 7, 'Status', 1);
        $pdf->Cell(25, 7, 'Tanggal', 1);
        $pdf->Ln();
        
        $query = mysqli_query($conn, "SELECT o.order_number, o.customer_name, 
                                       t.table_number, o.total_amount, o.status, o.order_date,
                                       COUNT(oi.id) as total_items
                                       FROM orders o 
                                       LEFT JOIN tables t ON o.table_id = t.id
                                       LEFT JOIN order_items oi ON o.id = oi.order_id
                                       GROUP BY o.id, o.order_number, o.customer_name, 
                                                t.table_number, o.total_amount, o.status, o.order_date
                                       ORDER BY o.order_date DESC");
        $pdf->SetFont('Arial', '', 8);
        
        $total_pendapatan = 0;
        $count_selesai = 0;
        
        while ($row = mysqli_fetch_assoc($query)) {
            $pdf->Cell(30, 6, $row['order_number'], 1);
            $pdf->Cell(40, 6, substr($row['customer_name'], 0, 25), 1);
            $pdf->Cell(20, 6, $row['table_number'] ? $row['table_number'] : 'T/A', 1);
            $pdf->Cell(15, 6, $row['total_items'], 1, 0, 'C');
            $pdf->Cell(35, 6, 'Rp ' . number_format($row['total_amount'], 0, ',', '.'), 1);
            
            // Status dengan warna berbeda
            $status_text = $row['status'] ? ucfirst($row['status']) : 'Pending';
            $pdf->Cell(25, 6, $status_text, 1);
            $pdf->Cell(25, 6, date('d/m/Y', strtotime($row['order_date'])), 1);
            $pdf->Ln();
            
            // Hanya hitung yang SELESAI
            if ($row['status'] == 'selesai') {
                $total_pendapatan += $row['total_amount'];
                $count_selesai++;
            }
        }
        
        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 7, 'Total Pesanan Selesai: ' . $count_selesai, 0, 1, 'R');
        $pdf->Cell(0, 7, 'Total Pendapatan (Selesai): Rp ' . number_format($total_pendapatan, 0, ',', '.'), 0, 1, 'R');
        
    } elseif ($type == 'tables') {
        // Laporan Meja
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'LAPORAN DATA MEJA', 0, 1, 'C');
        $pdf->Ln(5);
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(20, 7, 'No', 1);
        $pdf->Cell(60, 7, 'Nomor Meja', 1);
        $pdf->Cell(50, 7, 'Kapasitas', 1);
        $pdf->Cell(50, 7, 'Status', 1);
        $pdf->Ln();
        
        $query = mysqli_query($conn, "SELECT * FROM tables ORDER BY table_number");
        $no = 1;
        $pdf->SetFont('Arial', '', 10);
        
        while ($row = mysqli_fetch_assoc($query)) {
            $pdf->Cell(20, 6, $no++, 1);
            $pdf->Cell(60, 6, $row['table_number'], 1);
            $pdf->Cell(50, 6, $row['capacity'] . ' orang', 1);
            $pdf->Cell(50, 6, ucfirst($row['status']), 1);
            $pdf->Ln();
        }
    }
    
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 5, 'Dicetak oleh: ' . $_SESSION['fullname'], 0, 1);
    $pdf->Cell(0, 5, 'Tanggal: ' . date('d F Y, H:i'), 0, 1);
    
    $pdf->Output('D', 'Laporan_' . ucfirst($type) . '_' . date('YmdHis') . '.pdf');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Laporan - Alugoro Cafe</title>
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
            max-width: 900px;
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
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .report-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .report-card:hover {
            transform: translateY(-5px);
        }
        
        .report-card .icon {
            font-size: 50px;
            margin-bottom: 15px;
        }
        
        .report-card h3 {
            margin-bottom: 10px;
        }
        
        .info-box {
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
        <h1>☕ Alugoro Cafe - Generate Laporan PDF</h1>
        <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>📄 Generate Laporan PDF</h2>
            
            <div class="info-box">
                <strong>ℹ️ Informasi:</strong><br>
                Pilih jenis laporan yang ingin Anda generate. File PDF akan otomatis terdownload.
            </div>
            
            <div class="report-grid">
                <a href="?generate&type=menu" class="report-card">
                    <div class="icon">🍽️</div>
                    <h3>Laporan Menu</h3>
                    <p>Daftar semua menu dengan kategori, harga, dan stok</p>
                </a>
                
                <a href="?generate&type=orders" class="report-card">
                    <div class="icon">📋</div>
                    <h3>Laporan Pesanan</h3>
                    <p>Riwayat pesanan lengkap dengan total pendapatan</p>
                </a>
                
                <a href="?generate&type=tables" class="report-card">
                    <div class="icon">🪑</div>
                    <h3>Laporan Meja</h3>
                    <p>Data meja dengan kapasitas dan status</p>
                </a>
            </div>
        </div>
    </div>
</body>
</html>