<?php
// Cron-friendly invoice consolidation (same logic as the Billing page).
include_once 'connect_db.php';
$period = $_GET['period'] ?? date('Y-m');
$result = bh_generate_monthly_invoices($conn, $period);
header('Content-Type: application/json');
echo json_encode($result);
