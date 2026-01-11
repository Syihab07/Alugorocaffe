<?php
// File: auth.php
// Middleware untuk cek role dan akses

// Fungsi untuk cek apakah user adalah admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

// Fungsi untuk cek apakah user adalah kasir
function isKasir() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'kasir';
}

// Fungsi untuk memaksa hanya admin yang bisa akses
function requireAdmin() {
    if (!isAdmin()) {
        alert('Akses ditolak! Hanya Admin yang dapat mengakses halaman ini.');
        redirect('dashboard.php');
        exit();
    }
}

// Fungsi untuk cek minimal login (admin atau kasir)
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
        exit();
    }
}
?>