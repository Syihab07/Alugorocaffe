<?php
// File: login.php
session_start();
require_once 'config.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

// Proses login
if (isset($_POST['login'])) {
    $username = escape($_POST['username']);
    $password = md5($_POST['password']); // Menggunakan MD5 untuk hash password
    
    $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];
        
        // Set cookie jika "Remember Me" dicentang (berlaku 30 hari)
        if (isset($_POST['remember_me'])) {
            $cookie_name = 'alugoro_user';
            $cookie_value = base64_encode($user['username'] . '|' . $user['id'] . '|' . $user['role']);
            $cookie_expire = time() + (86400 * 30); // 30 hari
            setcookie($cookie_name, $cookie_value, $cookie_expire, "/");
            
            // Cookie untuk auto-fill username
            setcookie('alugoro_username', $user['username'], $cookie_expire, "/");
        }
        
        // Log aktivitas login
        logActivity($user['id'], 'login', $user['fullname'] . ' melakukan login ke sistem');
        
        redirect('dashboard.php');
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Alugoro Cafe</title>
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
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .error-message {
            background: #ff4444;
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .info-box {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .info-box strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>☕ Alugoro Cafe</h1>
            <p>Sistem Manajemen Restoran</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($saved_username); ?>" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group" style="display: flex; align-items: center; margin-bottom: 20px;">
                <input type="checkbox" id="remember_me" name="remember_me" style="width: auto; margin-right: 8px;">
                <label for="remember_me" style="margin-bottom: 0; cursor: pointer;">
                    🔒 Remember Me (30 hari)
                </label>
            </div>
            
            <button type="submit" name="login" class="btn-login">Login</button>
        </form>
        
        <div class="info-box">
            <strong>Info Login:</strong><br>
            Admin: admin / admin123<br>
            Kasir: kasir1 / kasir123
        </div>
    </div>
</body>
</html>