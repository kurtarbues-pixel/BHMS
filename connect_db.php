<?php
$host     = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$db_name  = getenv('DB_NAME') ?: 'boarding_house_management_system';

$conn = new mysqli($host, $username, $password);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->query("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$conn->select_db($db_name);

require_once __DIR__ . '/schema_migrate.php';
bh_ensure_schema($conn, $host, $username, $password);
require_once __DIR__ . '/billing.php';
