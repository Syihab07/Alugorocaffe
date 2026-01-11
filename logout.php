<?php
// File: logout.php
session_start();
require_once 'config.php';

// Log aktivitas logout
if (isset($_SESSION['user_id'])) {
    logActivity($_SESSION['user_id'], 'logout', $_SESSION['fullname'] . ' melakukan logout');
}

// Hapus semua session
session_unset();
session_destroy();

// Hapus semua cookies
if (isset($_COOKIE['alugoro_user'])) {
    setcookie('alugoro_user', '', time() - 3600, "/");
}
if (isset($_COOKIE['alugoro_username'])) {
    setcookie('alugoro_username', '', time() - 3600, "/");
}
if (isset($_COOKIE['user_login'])) {
    setcookie('user_login', '', time() - 3600, "/");
}

// Redirect ke login
header("Location: login.php");
exit();
?>