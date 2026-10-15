<?php
include_once 'auth.php';
include_once 'connect_db.php';
include_once 'header.php';
$peso = '&#8369;';
bh_sync_room_status($conn);

$message = '';
$msgType = '';

if (isset($_POST['add_reservation'])) {
    $guest_id = (int)$_POST['guest_id'];
    $room_id = (int)$_POST['room_id'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];

    $conn->begin_transaction();
    try {
        $roomStmt = $conn->prepare("SELECT id, room_number, price, status FROM rooms WHERE id = ? FOR UPDATE");
        $roomStmt->bind_param('i', $room_id);
        $roomStmt->execute();
        $roomInfo = $roomStmt->get_result()->fetch_assoc();
        if (!$roomInfo) {
            throw new Exception('Room not found.');
        }
        if ($roomInfo['status'] === 'maintenance') {
            throw new Exception('Room ' . $roomInfo['room_number'] . ' is under maintenance.');
        }

        $conflict = bh_room_conflict($conn, $room_id, $check_in, $check_out);
        if ($conflict->num_rows > 0) {
            $conflictRoom = $conflict->fetch_assoc();
            throw new Exception(
                "Cannot lease Room {$roomInfo['room_number']} from " . date('M d', strtotime($check_in)) .
                " to " . date('M d', strtotime($check_out)) .
                ". Already occupied by {$conflictRoom['first_name']} {$conflictRoom['last_name']}."
            );
        }

        $months = bh_billing_months($check_in, $check_out);
        $monthly = (float)$roomInfo['price'];
        $total = $monthly * $months;

        $ins = $conn->prepare("INSERT INTO reservations (guest_id, room_id, check_in, check_out, total) VALUES (?, ?, ?, ?, ?)");
        $ins->bind_param('iissd', $guest_id, $room_id, $check_in, $check_out, $total);
        $ins->execute();
        $reservationId = $ins->insert_id;

        bh_create_lease_for_reservation($conn, $reservationId, $guest_id, $room_id, $check_in, $check_out, $monthly);

        $today = date('Y-m-d');
        if ($check_in <= $today && $check_out > $today) {
            $upd = $conn->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ? AND status != 'maintenance'");
            $upd->bind_param('i', $room_id);
            $upd->execute();
        }

        $conn->commit();
        $message = "Lease confirmed for Room {$roomInfo['room_number']} ({$months} month(s)). Additional canteen/appliance fees can be added later.";
        $msgType = 'success';
        echo "<script>setTimeout(() => { window.location='for_reservations.php'; }, 1500);</script>";
    } catch (Exception $e) {
        $conn->rollback();
        $message = $e->getMessage();
        $msgType = 'danger';
    }
}

$availableRooms = $conn->query("SELECT id, room_number, type, price FROM rooms ORDER BY room_number");
$guests = $conn->query("SELECT DISTINCT id, first_name, last_name, email FROM guests ORDER BY first_name");
?>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show mb-4">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="table-container">
            <div class="table-title text-center">
                <span><i class="bi bi-calendar-check-fill text-pink me-2"></i> Create new lease</span>
            </div>

            <div class="alert alert-info">
                A room cannot be leased twice for overlapping dates. Move-out day is treated as free for the next tenant.
                Rent is billed monthly; canteen and appliance fees are added separately as pending charges.
            </div>

            <form method="POST" id="resForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select tenant</label>
                    <select name="guest_id" class="form-select" required>
                        <option value="">-- Choose tenant --</option>
                        <?php while($g = $guests->fetch_assoc()): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['first_name'] . ' ' . $g['last_name'] . ($g['email'] ? " ({$g['email']})" : '')) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Select room</label>
                    <select name="room_id" id="room_id" class="form-select" required>
                        <option value="">-- Choose room --</option>
                        <?php while($r = $availableRooms->fetch_assoc()): ?>
                            <option value="<?= $r['id'] ?>" data-price="<?= $r['price'] ?>">Room <?= htmlspecialchars($r['room_number']) ?> - <?= htmlspecialchars($r['type']) ?> (<?= $peso ?><?= number_format($r['price'], 2) ?>/month)</option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Move-in date</label>
                        <input type="date" name="check_in" id="check_in" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Move-out date</label>
                        <input type="date" name="check_out" id="check_out" class="form-control" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Estimated rent total (₱)</label>
                    <input type="number" step="0.01" name="total" id="total" class="form-control" readonly style="background: #f8f9fa; font-weight: bold;">
                    <small class="text-muted" id="rentHint">Monthly rent × months (each 30 days, minimum 1)</small>
                </div>

                <div id="availabilityWarning" class="alert alert-warning d-none mb-3">
                    <span id="warningMessage"></span>
                </div>

                <button type="submit" name="add_reservation" id="submitBtn" class="btn btn-pink w-100">
                    Confirm lease
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const roomPrices = {};
document.querySelectorAll('#room_id option').forEach(opt => { if(opt.value) roomPrices[opt.value] = parseFloat(opt.dataset.price); });

function calculateTotal() {
    let rid = document.getElementById('room_id').value;
    let cin = document.getElementById('check_in').value;
    let cout = document.getElementById('check_out').value;
    if (rid && cin && cout && new Date(cin) < new Date(cout)) {
        let nights = Math.ceil((new Date(cout) - new Date(cin)) / 86400000);
        let months = Math.max(1, Math.ceil(nights / 30));
        document.getElementById('total').value = (roomPrices[rid] * months).toFixed(2);
        document.getElementById('rentHint').textContent = months + ' month(s) × ₱' + roomPrices[rid].toFixed(2);
    } else {
        document.getElementById('total').value = '';
    }
}

async function checkAvailability() {
    let roomId = document.getElementById('room_id').value;
    let checkIn = document.getElementById('check_in').value;
    let checkOut = document.getElementById('check_out').value;
    let warningDiv = document.getElementById('availabilityWarning');
    let submitBtn = document.getElementById('submitBtn');

    if (roomId && checkIn && checkOut && new Date(checkIn) < new Date(checkOut)) {
        try {
            let response = await fetch(`check_room_availability.php?room_id=${roomId}&check_in=${checkIn}&check_out=${checkOut}`);
            let data = await response.json();
            if (data.booked) {
                warningDiv.classList.remove('d-none');
                document.getElementById('warningMessage').textContent = `This room is already leased by ${data.guest_name} from ${data.check_in} to ${data.check_out}.`;
                submitBtn.disabled = true;
            } else {
                warningDiv.classList.add('d-none');
                submitBtn.disabled = false;
            }
        } catch(e) {}
    } else {
        warningDiv.classList.add('d-none');
        submitBtn.disabled = false;
    }
}

['room_id','check_in','check_out'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => { calculateTotal(); checkAvailability(); });
});
let today = new Date().toISOString().split('T')[0];
document.getElementById('check_in').min = today;
document.getElementById('check_in').addEventListener('change', function() {
    document.getElementById('check_out').min = this.value;
});
</script>

<?php include_once 'footer.php'; ?>
