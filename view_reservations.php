<?php
// view_reservations.php - View All Reservations with Auto Room Status
include_once 'auth.php';
include_once 'connect_db.php';
include_once 'header.php';
$peso = '&#8369;';
$today = date('Y-m-d');
bh_sync_room_status($conn);

// Handle reservation deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && isAdmin()) {
    $id = (int)$_GET['delete'];
    
    // Get room_id before deleting
    $res = $conn->query("SELECT room_id FROM reservations WHERE id = $id");
    $room = $res->fetch_assoc();
    $room_id = $room['room_id'];
    
    // Delete reservation
    $conn->query("UPDATE leases SET status = 'cancelled' WHERE reservation_id = $id");
    $conn->query("DELETE FROM reservations WHERE id = $id");
    
    $stillOccupied = $conn->query("
        SELECT id FROM reservations 
        WHERE room_id = $room_id 
        AND check_in <= '$today' AND check_out > '$today'
    ");
    
    if ($stillOccupied->num_rows == 0) {
        $conn->query("UPDATE rooms SET status = 'available' WHERE id = $room_id");
    }
    
    echo "<script>window.location='view_reservations.php';</script>";
}

// Handle reservation edit/update
$editMessage = '';
$editMsgType = '';

if (isset($_POST['update_reservation']) && isAdmin()) {
    $reservation_id = (int)$_POST['reservation_id'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    
    // Get room_id for this reservation
    $roomInfo = $conn->query("SELECT room_id FROM reservations WHERE id = $reservation_id")->fetch_assoc();
    $room_id = $roomInfo['room_id'];
    
    // Get room price for calculation
    $roomPrice = $conn->query("SELECT price FROM rooms WHERE id = $room_id")->fetch_assoc();
    $pricePerNight = $roomPrice['price'];
    
    // Calculate total based on nights
    $months = bh_billing_months($check_in, $check_out);
    $total = $pricePerNight * $months;
    $conflict = bh_room_conflict($conn, (int)$room_id, $check_in, $check_out, $reservation_id);
    
    if ($conflict->num_rows > 0) {
        $conflictRoom = $conflict->fetch_assoc();
        $editMessage = "❌ Date conflict! Room is already booked by {$conflictRoom['first_name']} {$conflictRoom['last_name']} from " . 
                       date('M d, Y', strtotime($conflictRoom['check_in'])) . " to " . date('M d, Y', strtotime($conflictRoom['check_out'])) . ".";
        $editMsgType = 'danger';
    } else {
        // Update reservation with calculated total
        $conn->query("
            UPDATE reservations 
            SET check_in = '$check_in', check_out = '$check_out', total = $total 
            WHERE id = $reservation_id
        ");
        $leaseStatus = (strtotime($check_out) < strtotime($today)) ? 'ended' : 'active';
        $conn->query("
            UPDATE leases SET start_date = '$check_in', end_date = '$check_out',
            monthly_rent = $pricePerNight, status = '$leaseStatus'
            WHERE reservation_id = $reservation_id
        ");
        
        if ($check_in <= $today && $check_out > $today) {
            $conn->query("UPDATE rooms SET status = 'occupied' WHERE id = $room_id AND status != 'maintenance'");
        } else {
            $stillOccupied = $conn->query("
                SELECT id FROM reservations 
                WHERE room_id = $room_id 
                AND id != $reservation_id
                AND check_in <= '$today' AND check_out > '$today'
            ");
            if ($stillOccupied->num_rows == 0) {
                $conn->query("UPDATE rooms SET status = 'available' WHERE id = $room_id");
            }
        }
        
        $editMessage = "✅ Reservation updated successfully! New total: " . $peso . number_format($total, 2);
        $editMsgType = 'success';
        echo "<script>setTimeout(() => { window.location='view_reservations.php'; }, 1500);</script>";
    }
}

$reservations = $conn->query("
    SELECT 
        r.id, g.first_name, g.last_name, g.email, g.phone,
        rm.room_number, rm.type as room_type, rm.status as room_status, rm.price as room_price,
        r.check_in, r.check_out, r.total,
        l.id as lease_id,
        (SELECT COALESCE(SUM(amount),0) FROM pending_charges pc WHERE pc.lease_id = l.id AND pc.status='PENDING') as pending_fees
    FROM reservations r 
    JOIN guests g ON r.guest_id = g.id 
    JOIN rooms rm ON r.room_id = rm.id 
    LEFT JOIN leases l ON l.reservation_id = r.id
    ORDER BY r.id DESC
");

$totalReservations = $conn->query("SELECT COUNT(*) as c FROM reservations")->fetch_assoc()['c'];
$activeReservations = $conn->query("SELECT COUNT(*) as c FROM reservations WHERE check_in <= '$today' AND check_out > '$today'")->fetch_assoc()['c'];
$occupiedRooms = $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status = 'occupied'")->fetch_assoc()['c'];
$availableRooms = $conn->query("SELECT COUNT(*) as c FROM rooms WHERE status = 'available'")->fetch_assoc()['c'];
?>

<!-- Edit Reservation Modal -->
<div class="modal fade" id="editReservationModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #e83e8c 0%, #d6336c 100%); color: white; border-radius: 20px 20px 0 0;">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i> Edit Reservation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editReservationForm">
                <div class="modal-body">
                    <input type="hidden" name="reservation_id" id="edit_reservation_id">
                    <input type="hidden" name="room_price" id="edit_room_price">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-person-badge"></i> Guest</label>
                        <input type="text" id="edit_guest_name" class="form-control" readonly disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-door-closed"></i> Room</label>
                        <input type="text" id="edit_room" class="form-control" readonly disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-tag"></i> Monthly rent</label>
                        <input type="text" id="edit_price_per_night" class="form-control" readonly disabled style="background: #f8f9fa;">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold"><i class="bi bi-calendar-plus"></i> Check-In Date</label>
                            <input type="date" name="check_in" id="edit_check_in" class="form-control" required onchange="calculateTotalPrice()">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold"><i class="bi bi-calendar-minus"></i> Check-Out Date</label>
                            <input type="date" name="check_out" id="edit_check_out" class="form-control" required onchange="calculateTotalPrice()">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><i class="bi bi-cash-stack"></i> Total Amount (₱)</label>
                        <input type="number" step="0.01" name="total" id="edit_total" class="form-control" readonly style="background: #f8f9fa; font-weight: bold; color: #e83e8c; font-size: 1.1rem;">
                        <small class="text-muted" id="nightsCalculation">-</small>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Auto-calculation:</strong> Total = months × monthly rent (30 days per month).<br>
                        Additional canteen/appliance fees are billed separately.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-pink" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_reservation" class="btn btn-pink" id="saveChangesBtn">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Message Alert -->
<?php if ($editMessage): ?>
<div class="alert alert-<?= $editMsgType ?> alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-<?= $editMsgType == 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
    <?= $editMessage ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Total Reservations</small>
                    <div class="stat-value"><?= $totalReservations ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-calendar-check fs-4 text-pink"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Active Stays</small>
                    <div class="stat-value"><?= $activeReservations ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-door-open fs-4 text-pink"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Occupied Rooms</small>
                    <div class="stat-value"><?= $occupiedRooms ?></div>
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
                    <div class="stat-value"><?= $availableRooms ?></div>
                </div>
                <div class="stat-icon"><i class="bi bi-check-circle fs-4 text-pink"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-title">
        <span><i class="bi bi-table text-pink me-2"></i> All Reservations</span>
        <a href="for_reservations.php" class="btn btn-pink btn-sm">
            <i class="bi bi-plus-circle"></i> New Reservation
        </a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Guest Name</th>
                    <th>Contact</th>
                    <th>Room</th>
                    <th>Room Status</th>
                    <th>Check-In</th>
                    <th>Check-Out</th>
                    <th>Rent total</th>
                    <th>Pending fees</th>
                    <th>Stay status</th>
                    <?php if (isAdmin()): ?>
                    <th>Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($reservations->num_rows > 0): ?>
                    <?php while($row = $reservations->fetch_assoc()): 
                        $bookingStatus = '';
                        $bookingClass = '';
                        $bookingColor = '';
                        if ($row['check_in'] <= $today && $row['check_out'] > $today) {
                            $bookingStatus = 'Active';
                            $bookingClass = '#d4edda';
                            $bookingColor = '#155724';
                        } elseif ($row['check_out'] < $today) {
                            $bookingStatus = 'Completed';
                            $bookingClass = '#e2e3e5';
                            $bookingColor = '#383d41';
                        } else {
                            $bookingStatus = 'Upcoming';
                            $bookingClass = '#fff3cd';
                            $bookingColor = '#856404';
                        }
                        
                        $roomStatusClass = $row['room_status'] == 'occupied' ? '#dc3545' : '#28a745';
                        $roomStatusColor = $row['room_status'] == 'occupied' ? '#dc3545' : '#28a745';
                    ?>
                    <tr>
                        <td>#<?= $row['id'] ?></td>
                        <td><strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong></td>
                        <td>
                            <i class="bi bi-envelope"></i> <?= htmlspecialchars($row['email'] ?? '—') ?><br>
                            <i class="bi bi-telephone"></i> <?= htmlspecialchars($row['phone'] ?? '—') ?>
                        </td>
                        <td>Room <?= $row['room_number'] ?><br><small><?= $row['room_type'] ?></small></td>
                        <td>
                            <span style="background: <?= $roomStatusClass ?>20; color: <?= $roomStatusColor ?>; padding: 4px 10px; border-radius: 20px;">
                                <?= ucfirst($row['room_status']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y', strtotime($row['check_in'])) ?></td>
                        <td><?= date('M d, Y', strtotime($row['check_out'])) ?></td>
                        <td class="fw-bold text-success"><?= $peso ?><?= number_format($row['total'], 2) ?></td>
                        <td><?= $peso ?><?= number_format((float)$row['pending_fees'], 2) ?></td>
                        <td><span style="background: <?= $bookingClass ?>; color: <?= $bookingColor ?>; padding: 4px 10px; border-radius: 20px;"><?= $bookingStatus ?></span></td>
                        <?php if (isAdmin()): ?>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-pink me-1" 
                                    onclick='openEditModal(<?= $row['id'] ?>, "<?= htmlspecialchars(addslashes($row['first_name'] . ' ' . $row['last_name'])) ?>", "<?= $row['room_number'] ?>", <?= $row['room_price'] ?>, "<?= $row['check_in'] ?>", "<?= $row['check_out'] ?>", <?= $row['total'] ?>)'>
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this reservation? This will also update room status.')">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No reservations found</td</tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let currentRoomPrice = 0;
let currentReservationId = 0;

function calculateTotalPrice() {
    let checkIn = document.getElementById('edit_check_in').value;
    let checkOut = document.getElementById('edit_check_out').value;
    let totalField = document.getElementById('edit_total');
    let nightsCalcSpan = document.getElementById('nightsCalculation');
    let pricePerNight = currentRoomPrice;
    
    if (checkIn && checkOut && pricePerNight > 0) {
        let checkInDate = new Date(checkIn);
        let checkOutDate = new Date(checkOut);
        
        if (checkOutDate > checkInDate) {
            let timeDiff = checkOutDate.getTime() - checkInDate.getTime();
            let nights = Math.ceil(timeDiff / (1000 * 3600 * 24));
            let months = Math.max(1, Math.ceil(nights / 30));
            let total = months * pricePerNight;
            
            totalField.value = total.toFixed(2);
            nightsCalcSpan.innerHTML = `${months} month(s) × ${formatPrice(pricePerNight)} = ${formatPrice(total)}`;
            nightsCalcSpan.style.color = '#e83e8c';
            nightsCalcSpan.style.fontWeight = '500';
        } else {
            totalField.value = '';
            nightsCalcSpan.innerHTML = '⚠️ Check-out must be after check-in';
            nightsCalcSpan.style.color = '#dc3545';
        }
    } else if (checkIn && checkOut) {
        totalField.value = '';
        nightsCalcSpan.innerHTML = '❌ Invalid date selection';
        nightsCalcSpan.style.color = '#dc3545';
    } else if (checkIn || checkOut) {
        totalField.value = '';
        nightsCalcSpan.innerHTML = 'Please select both dates';
        nightsCalcSpan.style.color = '#6c757d';
    } else {
        totalField.value = '';
        nightsCalcSpan.innerHTML = 'Select check-in and check-out dates to calculate price';
        nightsCalcSpan.style.color = '#6c757d';
    }
}

function formatPrice(amount) {
    return '₱' + parseFloat(amount).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function openEditModal(id, guestName, roomNumber, roomPrice, checkIn, checkOut, oldTotal) {
    currentRoomPrice = parseFloat(roomPrice);
    currentReservationId = id;
    
    document.getElementById('edit_reservation_id').value = id;
    document.getElementById('edit_guest_name').value = guestName;
    document.getElementById('edit_room').value = 'Room ' + roomNumber;
    document.getElementById('edit_price_per_night').value = formatPrice(roomPrice);
    document.getElementById('edit_room_price').value = roomPrice;
    document.getElementById('edit_check_in').value = checkIn;
    document.getElementById('edit_check_out').value = checkOut;
    
    let today = new Date().toISOString().split('T')[0];
    let checkInInput = document.getElementById('edit_check_in');
    let checkOutInput = document.getElementById('edit_check_out');
    
    checkInInput.min = today;
    checkInInput.max = '2030-12-31';
    checkOutInput.min = checkIn;
    checkOutInput.max = '2030-12-31';
    
    checkInInput.onchange = function() {
        checkOutInput.min = this.value;
        calculateTotalPrice();
    };
    checkOutInput.onchange = function() {
        calculateTotalPrice();
    };
    
    calculateTotalPrice();
    
    var modal = new bootstrap.Modal(document.getElementById('editReservationModal'));
    modal.show();
}
</script>

<?php include_once 'footer.php'; ?>