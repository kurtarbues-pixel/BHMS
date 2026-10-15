<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Boarding House Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='22' fill='%23e83e8c'/%3E%3Ctext x='50' y='68' font-size='48' text-anchor='middle' fill='white'%3E🏠%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/theme.js"></script>
</head>
<body>

<button class="sidebar-toggle" type="button" onclick="document.body.classList.toggle('sidebar-open')" aria-label="Open menu">
    <i class="bi bi-list"></i>
</button>
<div class="sidebar-backdrop" onclick="document.body.classList.remove('sidebar-open')"></div>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="bi bi-house-heart"></i>
        </div>
        <h3>Boarding House</h3>
        <p>MANAGEMENT SYSTEM</p>
        <div class="hotel-name">
            <i class="bi bi-geo-alt"></i> Local Boarding House
        </div>
    </div>
    <ul class="sidebar-menu">
        <li><a href="home.php" class="<?= basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li><a href="for_guests.php" class="<?= basename($_SERVER['PHP_SELF']) == 'for_guests.php' ? 'active' : '' ?>"><i class="bi bi-people"></i> Tenants</a></li>
        <li><a href="for_rooms.php" class="<?= basename($_SERVER['PHP_SELF']) == 'for_rooms.php' ? 'active' : '' ?>"><i class="bi bi-door-closed"></i> Rooms</a></li>
        <li><a href="for_reservations.php" class="<?= basename($_SERVER['PHP_SELF']) == 'for_reservations.php' ? 'active' : '' ?>"><i class="bi bi-calendar-plus"></i> New Lease</a></li>
        <li><a href="view_reservations.php" class="<?= basename($_SERVER['PHP_SELF']) == 'view_reservations.php' ? 'active' : '' ?>"><i class="bi bi-table"></i> Occupancy</a></li>
        <li><a href="for_charges.php" class="<?= basename($_SERVER['PHP_SELF']) == 'for_charges.php' ? 'active' : '' ?>"><i class="bi bi-receipt"></i> Additional Charges</a></li>
        <li><a href="for_invoices.php" class="<?= basename($_SERVER['PHP_SELF']) == 'for_invoices.php' ? 'active' : '' ?>"><i class="bi bi-file-earmark-text"></i> Billing & Invoices</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="top-bar">
       <div class="top-bar-logo">
            <div class="top-bar-icon">
                <i class="bi bi-house-heart"></i>
            </div>
            <div class="top-bar-title">
                <?php
                $role = $_SESSION['role'] ?? 'staff';
                $username = htmlspecialchars($_SESSION['username'] ?? 'Staff');
                $welcomeMessage = ($role == 'admin') ? "Welcome, Admin!" : "Welcome, " . ucfirst($username) . "!";
                ?>
                <h4><?= $welcomeMessage ?></h4>
                <p><i class="bi bi-calendar3"></i> <?= date('l, F j, Y') ?></p>
            </div>
        </div>
        <div class="top-bar-user">
            <button class="theme-toggle" type="button" aria-label="Switch to dark mode" title="Switch theme">
                <i class="bi bi-moon-stars" aria-hidden="true"></i><span class="theme-toggle-label">Dark mode</span>
            </button>
            <div class="user-info">
                <?php
                $username = htmlspecialchars($_SESSION['username'] ?? 'Staff');
                $role = $_SESSION['role'] ?? 'staff';
                $roleText = ($role == 'admin') ? 'Administrator' : 'Staff';
                ?>
                <div class="user-name"><?= ucfirst($username) ?></div>
                <div class="user-role"><?= $roleText ?></div>
            </div>
            <a href="logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
