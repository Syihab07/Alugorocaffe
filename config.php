<?php
// File: config.php
// Konfigurasi koneksi database

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Sesuaikan dengan password MySQL Anda
define('DB_NAME', 'alugoro_cafe');

// Membuat koneksi
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Cek koneksi
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Set charset ke utf8
mysqli_set_charset($conn, "utf8");

// Fungsi untuk mencegah SQL Injection
function escape($string) {
    global $conn;
    return mysqli_real_escape_string($conn, $string);
}

// Fungsi untuk redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Fungsi untuk menampilkan alert
function alert($message) {
    echo "<script>alert('" . addslashes($message) . "');</script>";
}

// Fungsi untuk log aktivitas
function logActivity($user_id, $action, $description) {
    global $conn;
    $user_id = escape($user_id);
    $action = escape($action);
    $description = escape($description);
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $query = "INSERT INTO activity_logs (user_id, action, description, ip_address) 
              VALUES ('$user_id', '$action', '$description', '$ip_address')";
    mysqli_query($conn, $query);
}
?>