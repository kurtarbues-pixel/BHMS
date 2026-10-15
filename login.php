<?php
session_start(); 
include 'connect_db.php';

// If already logged in, redirect to home/dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

// Handle login form
if (isset($_POST['login'])) {
    $u = trim($_POST['username']);
    $p = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $u);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($p, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        
        header("Location: home.php");
        exit();
    } else {
        $error = "❌ Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Boarding House Management System - Login</title>
    
    <!-- Favicon - Browser Tab Icon -->
    <link rel="icon" type="image/png" sizes="32x32" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cdefs%3E%3ClinearGradient id='grad' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' style='stop-color:%23e83e8c'/%3E%3Cstop offset='100%25' style='stop-color:%23c9185c'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='100' height='100' rx='22' fill='url(%23grad)'/%3E%3Ctext x='50' y='72' font-size='55' text-anchor='middle' fill='white' font-weight='bold'%3E🌸%3C/text%3E%3C/svg%3E">
    <link rel="apple-touch-icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%23e83e8c'/%3E%3Ctext x='50' y='72' font-size='55' text-anchor='middle' fill='white'%3E🌸%3C/text%3E%3C/svg%3E">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="js/theme.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        body {
            background: linear-gradient(135deg, #fff5f7 0%, #ffe4e9 100%);
            min-height: 100vh;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 70px rgba(232,62,140,0.2);
        }
        .btn-pink {
            background: linear-gradient(135deg, #e83e8c 0%, #d6336c 100%);
            color: white;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-pink:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(232,62,140,0.4);
            color: white;
        }
        .form-control {
            border-radius: 12px;
            border: 1px solid #f0c4d4;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #e83e8c;
            box-shadow: 0 0 0 0.2rem rgba(232,62,140,0.25);
        }
        .welcome-text {
            background: linear-gradient(135deg, #e83e8c, #d6336c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="d-flex justify-content-center align-items-center vh-100">
    <button class="theme-toggle login-theme-toggle" type="button" aria-label="Switch to dark mode" title="Switch theme"><i class="bi bi-moon-stars" aria-hidden="true"></i><span class="theme-toggle-label">Dark mode</span></button>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card p-4">
                    <div class="text-center mb-4">
                        
                        <h2 class="mt-2 welcome-text fw-bold">Boarding House</h2>
                        <p class="text-muted">Management System — sign in to continue</p>
                    </div>
                    
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-person-fill"></i> Username
                            </label>
                            <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-lock-fill"></i> Password
                            </label>
                            <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-pink w-100 py-2 fw-bold">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </button>
                    </form>
                    
                    <hr class="my-4">
                    <div class="text-center">
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
