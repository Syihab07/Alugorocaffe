<?php
// File: index.php - Simple Version (Auto redirect to login)
session_start();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Auto redirect ke login setelah 3 detik
header("refresh:3;url=login.php");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alugoro Cafe - Welcome</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .welcome-container {
            text-align: center;
            color: white;
            animation: fadeIn 0.5s ease-in;
        }
        
        .logo {
            font-size: 120px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        h1 {
            font-size: 56px;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .tagline {
            font-size: 22px;
            margin-bottom: 40px;
            opacity: 0.9;
        }
        
        .loading {
            margin-top: 40px;
        }
        
        .spinner {
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        .redirect-text {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .redirect-link {
            color: white;
            text-decoration: underline;
            font-weight: 600;
        }
        
        .redirect-link:hover {
            opacity: 0.8;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="logo">☕</div>
        <h1>Alugoro Cafe</h1>
        <p class="tagline">Sistem Manajemen Restoran</p>
        
        <div class="loading">
            <div class="spinner"></div>
            <p class="redirect-text">
                Mengalihkan ke halaman login...<br>
                <small>Atau klik <a href="login.php" class="redirect-link">di sini</a> jika tidak otomatis</small>
            </p>
        </div>
    </div>
</body>
</html>