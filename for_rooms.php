<?php
// for_rooms.php - Room Management
header("Cache-Control: no-cache, must-revalidate, no-store, private");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Pragma: no-cache");

include_once 'auth.php';
include_once 'connect_db.php';
include_once 'header.php';
$peso = '&#8369;';
bh_sync_room_status($conn);

if (!isAdmin()): 
?>
<div class="d-flex justify-content-center align-items-center" style="min-height: 60vh;">
    <div class="table-container text-center" style="max-width: 500px;">
        <div class="mb-4">
            <div class="rounded-circle bg-danger bg-opacity-10 d-inline-flex p-4">
                <i class="bi bi-shield-lock-fill fs-1 text-danger"></i>
            </div>
        </div>
        <h4 class="fw-bold text-pink mb-3">Access Restricted</h4>
        <p class="text-muted">Only administrators can change the boarding house room inventory.</p>
        <a href="home.php" class="btn btn-pink mt-3">Return to Dashboard</a>
        <a href="for_reservations.php" class="btn btn-pink mt-3">Add New Reservations</a>
    </div>
</div>
<?php 
include_once 'footer.php'; 
exit(); 
endif;

$error = '';
$success = '';
$warning = '';

// Handle room addition
if (isset($_POST['add_room'])) {
    $rn = trim($conn->real_escape_string($_POST['room_number']));
    $tp = $conn->real_escape_string($_POST['type']);
    $pr = floatval($_POST['price']);

    $check = $conn->query("SELECT id FROM rooms WHERE room_number = '$rn'");
    if ($check->num_rows > 0) {
        $error = "❌ Room number '$rn' already exists!";
    } elseif ($pr <= 0) {
        $error = "❌ Price must be greater than 0.";
    } else {
        $conn->query("INSERT INTO rooms (room_number, type, price, status) VALUES ('$rn', '$tp', $pr, 'available')");
        $success = "✅ Room $rn added successfully!";
        echo "<script>setTimeout(() => { window.location='for_rooms.php'; }, 1000);</script>";
    }
}

// Handle room deletion - FIXED with better protection
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // First check if room has any reservations (past, present, or future)
    $checkReservations = $conn->query("
        SELECT r.id, g.first_name, g.last_name, r.check_in, r.check_out 
        FROM reservations r 
        JOIN guests g ON r.guest_id = g.id 
        WHERE r.room_id = $id
        LIMIT 1
    ");
    
    if ($checkReservations->num_rows > 0) {
        $booking = $checkReservations->fetch_assoc();
        $warning = "⚠️ Cannot delete this room because it has existing reservations!<br>
                    <small>Guest: {$booking['first_name']} {$booking['last_name']} | 
                    Dates: " . date('M d, Y', strtotime($booking['check_in'])) . " to " . date('M d, Y', strtotime($booking['check_out'])) . "</small>";
    } else {
        // Get room number for success message
        $roomInfo = $conn->query("SELECT room_number FROM rooms WHERE id = $id")->fetch_assoc();
        $roomNumber = $roomInfo['room_number'];
        
        // Delete the room
        if ($conn->query("DELETE FROM rooms WHERE id = $id")) {
            $success = "✅ Room $roomNumber has been permanently deleted!";
        } else {
            $error = "❌ Failed to delete room: " . $conn->error;
        }
    }
    
    // Redirect after 2 seconds to show message
    if (!empty($success) || !empty($warning) || !empty($error)) {
        echo "<script>setTimeout(() => { window.location='for_rooms.php'; }, 2000);</script>";
    }
}

$roomsList = $conn->query("SELECT * FROM rooms ORDER BY id DESC");
$totalRooms = $conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'];
$availableRooms = $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status = 'available'")->fetch_assoc()['c'];
$occupiedRooms = $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status = 'occupied'")->fetch_assoc()['c'];
$maintenanceRooms = $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status = 'maintenance'")->fetch_assoc()['c'];
?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Total Rooms</small>
                    <div class="stat-value"><?= $totalRooms ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-building fs-4 text-pink"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Available Rooms</small>
                    <div class="stat-value text-success"><?= $availableRooms ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-check-circle fs-4 text-success"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Occupied Rooms</small>
                    <div class="stat-value text-danger"><?= $occupiedRooms ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-door-closed fs-4 text-danger"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Maintenance</small>
                    <div class="stat-value text-warning"><?= $maintenanceRooms ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-tools fs-4 text-warning"></i></div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($warning): ?>
<div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $warning ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="table-container">
            <div class="table-title"><span><i class="bi bi-plus-circle text-pink me-2"></i> Add New Room</span></div>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Room Number <span class="text-danger">*</span></label>
                    <input type="text" name="room_number" class="form-control" placeholder="e.g., 101, 202" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Room Type</label>
                    <select name="type" class="form-select">
                        <option>🙆🏽‍♂️ Single</option>
                        <option>👫🏽 Double</option>
                        <option>👨‍👩‍👧‍👦 Family</option>
                        <option>🌟 Suite</option>
                        <option>✨ Deluxe</option>
                        <option>👔 Presidential</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Monthly rent (₱)</label>
                    <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required>
                </div>
                <button type="submit" name="add_room" class="btn btn-pink w-100"><i class="bi bi-save"></i> Add Room</button>
            </form>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="table-container">
            <div class="table-title">
                <span><i class="bi bi-door-closed text-pink me-2"></i> Current Rooms Inventory</span>
                <span class="badge" style="background: #ffe4ee; color: #e83e8c;"><?= $roomsList->num_rows ?> Rooms</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Room No</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($roomsList->num_rows > 0): ?>
                            <?php while($rm = $roomsList->fetch_assoc()): 
                                // Set different colors for each status
                                if ($rm['status'] == 'occupied') {
                                    $statusColor = '#dc3545';
                                    $statusBg = '#f8d7da';
                                    $statusIcon = '🔴';
                                } elseif ($rm['status'] == 'maintenance') {
                                    $statusColor = '#fd7e14';
                                    $statusBg = '#fff3e0';
                                    $statusIcon = '🟠';
                                } else {
                                    $statusColor = '#28a745';
                                    $statusBg = '#d4edda';
                                    $statusIcon = '🟢';
                                }
                                
                                // Check if room has any reservations (for delete warning tooltip)
                                $hasReservations = $conn->query("SELECT id FROM reservations WHERE room_id = {$rm['id']} LIMIT 1")->num_rows > 0;
                                $deleteWarning = $hasReservations ? "onclick='return confirm(\"⚠️ WARNING: This room has existing reservations!\\nDeleting it will also delete all associated reservations.\\nAre you absolutely sure?\")'" : "onclick='return confirm(\"⚠️ Are you sure you want to permanently delete Room {$rm['room_number']}?\\nThis action cannot be undone!\")'";
                            ?>
                            <tr>
                                <td><?= $rm['id'] ?></td>
                                <td><strong>Room <?= $rm['room_number'] ?></strong></td>
                                <td><?= $rm['type'] ?></td>
                                <td><?= $peso ?><?= number_format($rm['price'], 2) ?></td>
                                <td>
                                    <span style="background: <?= $statusBg ?>; color: <?= $statusColor ?>; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px;">
                                        <?= $statusIcon ?> <?= ucfirst($rm['status']) ?>
                                    </span>
                                  </td>
                                <td>
                                    <a href="edit_room.php?id=<?= $rm['id'] ?>" class="btn btn-sm btn-outline-pink me-1" title="Edit Room">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="?delete=<?= $rm['id'] ?>" class="btn btn-sm btn-danger" <?= $deleteWarning ?> title="Delete Room">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                  </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No rooms added yet</td</tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional styling for better visibility */
.stat-card .stat-value.text-warning {
    color: #fd7e14 !important;
}
.stat-icon .text-warning {
    color: #fd7e14 !important;
}
</style>

<?php include_once 'footer.php'; ?>