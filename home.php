<?php
include_once 'auth.php';
include_once 'connect_db.php';
include_once 'header.php';
bh_sync_room_status($conn);

$peso = '&#8369;';
$today = date('Y-m-d');
$period = date('Y-m');

$totalTenants = $conn->query("SELECT COUNT(*) as c FROM guests")->fetch_assoc()['c'];
$totalRooms = (int)$conn->query("SELECT COUNT(*) as c FROM rooms")->fetch_assoc()['c'];
$occupiedRooms = (int)$conn->query("SELECT COUNT(*) as c FROM rooms WHERE status = 'occupied'")->fetch_assoc()['c'];
$occupancy = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;
$pendingCharges = $conn->query("SELECT COUNT(*) as c FROM pending_charges WHERE status='PENDING'")->fetch_assoc()['c'];
$unpaidInvoices = $conn->query("SELECT COUNT(*) as c FROM invoices WHERE status='UNPAID'")->fetch_assoc()['c'];
$monthlyBilled = $conn->query("SELECT COALESCE(SUM(total_amount_due),0) as t FROM invoices WHERE billing_period='$period'")->fetch_assoc()['t'];

$popularity = $conn->query("SELECT rm.type, COUNT(res.id) as booking_count FROM rooms rm LEFT JOIN reservations res ON rm.id = res.room_id GROUP BY rm.type ORDER BY booking_count DESC");
$roomTypes = []; $bookCounts = [];
while ($p = $popularity->fetch_assoc()) {
    $roomTypes[] = $p['type'];
    $bookCounts[] = $p['booking_count'];
}

$checkedInGuests = $conn->query("
    SELECT
        g.first_name, g.last_name, g.email, g.phone,
        rm.room_number, r.check_in, r.check_out, r.total,
        DATEDIFF(r.check_out, CURDATE()) as days_remaining,
        l.id as lease_id,
        (SELECT COALESCE(SUM(amount),0) FROM pending_charges pc WHERE pc.lease_id = l.id AND pc.status='PENDING') as pending_fees
    FROM reservations r
    JOIN guests g ON r.guest_id = g.id
    JOIN rooms rm ON r.room_id = rm.id
    LEFT JOIN leases l ON l.reservation_id = r.id
    WHERE r.check_in <= '$today' AND r.check_out > '$today'
    ORDER BY r.check_out ASC
");
?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <small class="text-muted">Occupancy</small>
            <div class="stat-value"><?= $occupancy ?>%</div>
            <small class="text-muted"><?= $occupiedRooms ?> / <?= $totalRooms ?> rooms</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <small class="text-muted">Pending charges</small>
            <div class="stat-value"><?= (int)$pendingCharges ?></div>
            <small class="text-muted">Canteen / appliance ledger</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <small class="text-muted">Unpaid invoices</small>
            <div class="stat-value"><?= (int)$unpaidInvoices ?></div>
            <small class="text-muted">Awaiting collection</small>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <small class="text-muted">Billed this month</small>
            <div class="stat-value"><?= $peso ?><?= number_format((float)$monthlyBilled, 2) ?></div>
            <small class="text-muted"><?= (int)$totalTenants ?> tenants on file</small>
        </div>
    </div>
</div>

<div class="table-container mb-4">
    <div class="dash-tabs">
        <a href="for_rooms.php" class="dash-tab">Room listings</a>
        <a href="for_charges.php" class="dash-tab">Additional charges</a>
        <a href="for_invoices.php" class="dash-tab active">Billing & invoices</a>
        <a href="view_reservations.php" class="dash-tab">Occupancy</a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="chart-container">
            <h5 class="text-center">
                <i class="bi bi-pie-chart-fill text-pink me-2"></i> Room type occupancy
            </h5>
            <div class="d-flex justify-content-center">
                <canvas id="roomChart" width="300" height="300" style="max-width: 280px; max-height: 280px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-title">
        <span><i class="bi bi-door-open-fill text-pink me-2"></i> Current tenants</span>
        <span class="badge" style="background: #ffe4ee; color: #e83e8c; padding: 5px 12px; border-radius: 20px;">
            <i class="bi bi-people-fill"></i> <?= $checkedInGuests->num_rows ?> staying
        </span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tenant</th>
                    <th>Contact</th>
                    <th>Room</th>
                    <th>Move-in</th>
                    <th>Move-out</th>
                    <th>Days left</th>
                    <th>Rent total</th>
                    <th>Pending fees</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($checkedInGuests->num_rows > 0): ?>
                    <?php while($guest = $checkedInGuests->fetch_assoc()):
                        $daysLeft = $guest['days_remaining'];
                        $badgeColor = $daysLeft <= 1 ? '#dc3545' : ($daysLeft <= 2 ? '#ffc107' : '#28a745');
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']) ?></strong></td>
                            <td>
                                <i class="bi bi-envelope"></i> <?= htmlspecialchars($guest['email'] ?? 'N/A') ?><br>
                                <i class="bi bi-telephone"></i> <?= htmlspecialchars($guest['phone'] ?? 'N/A') ?>
                            </td>
                            <td>
                                <span class="badge" style="background: #ffe4ee; color: #e83e8c; padding: 5px 12px;">
                                    Room <?= $guest['room_number'] ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($guest['check_in'])) ?></td>
                            <td><?= date('M d, Y', strtotime($guest['check_out'])) ?></td>
                            <td>
                                <span style="background: <?= $badgeColor ?>15; color: <?= $badgeColor ?>; padding: 4px 12px; border-radius: 20px; font-weight: 500;">
                                    <?= $daysLeft ?> day(s)
                                </span>
                            </td>
                            <td class="fw-bold text-success"><?= $peso ?><?= number_format($guest['total'], 2) ?></td>
                            <td><?= $peso ?><?= number_format((float)$guest['pending_fees'], 2) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            No tenants currently occupying a room
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const ctx = document.getElementById('roomChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($roomTypes) ?>,
        datasets: [{
            data: <?= json_encode($bookCounts) ?>,
            backgroundColor: ['#e83e8c', '#f06292', '#f8bbd0', '#c2185b', '#ec407a', '#f48fb1'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { font: { size: 12, weight: '500' }, boxWidth: 12, padding: 15, usePointStyle: true, pointStyle: 'circle' }
            }
        },
        cutout: '55%'
    }
});
</script>

<?php include_once 'footer.php'; ?>
