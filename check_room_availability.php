<?php
include_once 'connect_db.php';
header('Content-Type: application/json');

$room_id = (int)($_GET['room_id'] ?? 0);
$check_in = $_GET['check_in'] ?? '';
$check_out = $_GET['check_out'] ?? '';

if (!$room_id || !$check_in || !$check_out) {
    echo json_encode(['booked' => false]);
    exit;
}

$result = bh_room_conflict($conn, $room_id, $check_in, $check_out);
if ($result->num_rows > 0) {
    $booking = $result->fetch_assoc();
    echo json_encode([
        'booked' => true,
        'guest_name' => $booking['first_name'] . ' ' . $booking['last_name'],
        'check_in' => date('M d, Y', strtotime($booking['check_in'])),
        'check_out' => date('M d, Y', strtotime($booking['check_out']))
    ]);
} else {
    echo json_encode(['booked' => false]);
}
