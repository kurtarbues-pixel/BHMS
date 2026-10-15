<?php
include_once 'auth.php';
include_once 'connect_db.php';
include_once 'header.php';
$peso = '&#8369;';
bh_sync_room_status($conn);

$message = '';
$msgType = '';

if (isset($_POST['add_charge'])) {
    $leaseId = (int)$_POST['lease_id'];
    $category = $_POST['category'] ?? 'OTHER';
    $description = trim($_POST['description'] ?? '');
    $amount = (float)$_POST['amount'];

    $lease = $conn->query("SELECT id, status FROM leases WHERE id = $leaseId")->fetch_assoc();
    if (!$lease) {
        $message = 'Select a valid active tenant lease.';
        $msgType = 'danger';
    } elseif ($amount <= 0) {
        $message = 'Charge amount must be greater than 0.';
        $msgType = 'danger';
    } else {
        if (bh_add_pending_charge($conn, $leaseId, $category, $description, $amount)) {
            $message = 'Charge logged as PENDING. It will be added to the next monthly invoice (no immediate payment required).';
            $msgType = 'success';
        } else {
            $message = 'Could not save the charge. Check the category and amount.';
            $msgType = 'danger';
        }
    }
}

if (isset($_POST['generate_invoices'])) {
    $period = $_POST['billing_period'] ?: date('Y-m');
    $result = bh_generate_monthly_invoices($conn, $period);
    $message = "Invoices for {$result['period']}: {$result['created']} created, {$result['updated']} updated. Pending charges were marked BILLED.";
    $msgType = 'success';
}

$leases = $conn->query("
    SELECT l.id, l.monthly_rent, l.start_date, l.end_date, l.status,
           g.first_name, g.last_name, rm.room_number
    FROM leases l
    JOIN guests g ON g.id = l.guest_id
    JOIN rooms rm ON rm.id = l.room_id
    WHERE l.status = 'active'
    ORDER BY g.first_name
");

$pending = $conn->query("
    SELECT pc.*, g.first_name, g.last_name, rm.room_number
    FROM pending_charges pc
    JOIN leases l ON l.id = pc.lease_id
    JOIN guests g ON g.id = l.guest_id
    JOIN rooms rm ON rm.id = l.room_id
    WHERE pc.status = 'PENDING'
    ORDER BY pc.id DESC
");

$pendingTotal = $conn->query("SELECT COALESCE(SUM(amount),0) AS t FROM pending_charges WHERE status='PENDING'")->fetch_assoc()['t'];
$pendingCount = $pending ? $pending->num_rows : 0;
?>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show mb-4">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <small class="text-muted">Pending ledger items</small>
            <div class="stat-value"><?= (int)$pendingCount ?></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <small class="text-muted">Unbilled additional charges</small>
            <div class="stat-value"><?= $peso ?><?= number_format((float)$pendingTotal, 2) ?></div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <strong>Accrual billing:</strong> Canteen snacks and extra appliance fees are stored as PENDING.
    Tenants are not asked to pay immediately. Use “Generate monthly invoices” to roll them into the next rent bill.
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="table-container">
            <div class="table-title"><span><i class="bi bi-plus-circle text-pink me-2"></i> Log additional charge</span></div>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tenant / lease</label>
                    <select name="lease_id" class="form-select" required>
                        <option value="">-- Choose tenant --</option>
                        <?php if ($leases): while ($l = $leases->fetch_assoc()): ?>
                            <option value="<?= (int)$l['id'] ?>">
                                <?= htmlspecialchars($l['first_name'] . ' ' . $l['last_name']) ?>
                                — Room <?= htmlspecialchars($l['room_number']) ?>
                                (<?= $peso ?><?= number_format((float)$l['monthly_rent'], 2) ?>/mo)
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Category</label>
                    <select name="category" class="form-select" required>
                        <option value="CANTEEN">Canteen</option>
                        <option value="APPLIANCE">Extra appliance (fridge / aircon)</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. snacks, extra fridge, extra aircon">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Amount (₱)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
                </div>
                <button type="submit" name="add_charge" class="btn btn-pink w-100">
                    <i class="bi bi-save"></i> Save as pending
                </button>
            </form>
        </div>

        <div class="table-container mt-4">
            <div class="table-title"><span><i class="bi bi-calendar-event text-pink me-2"></i> Roll into invoice</span></div>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Billing period</label>
                    <input type="month" name="billing_period" class="form-control" value="<?= date('Y-m') ?>">
                </div>
                <button type="submit" name="generate_invoices" class="btn btn-pink w-100">
                    <i class="bi bi-magic"></i> Generate monthly invoices
                </button>
                <a href="for_invoices.php" class="btn btn-outline-pink w-100 mt-2">Go to invoices</a>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="table-container">
            <div class="table-title">
                <span><i class="bi bi-hourglass-split text-pink me-2"></i> Pending charges</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Room</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Logged</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending && $pending->num_rows > 0): ?>
                            <?php while ($c = $pending->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></td>
                                <td><?= htmlspecialchars($c['room_number']) ?></td>
                                <td><?= htmlspecialchars($c['category']) ?></td>
                                <td><?= htmlspecialchars($c['description'] ?: '—') ?></td>
                                <td class="fw-bold"><?= $peso ?><?= number_format((float)$c['amount'], 2) ?></td>
                                <td><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No pending charges</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once 'footer.php'; ?>
