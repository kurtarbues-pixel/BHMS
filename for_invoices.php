<?php
include_once 'auth.php';
include_once 'connect_db.php';
include_once 'header.php';
$peso = '&#8369;';

$message = '';
$msgType = '';

if (isset($_POST['generate_invoices'])) {
    $period = $_POST['billing_period'] ?: date('Y-m');
    $result = bh_generate_monthly_invoices($conn, $period);
    $message = "Invoices for {$result['period']}: {$result['created']} created, {$result['updated']} updated.";
    $msgType = 'success';
}

if (isset($_GET['pay']) && is_numeric($_GET['pay']) && isAdmin()) {
    bh_mark_invoice_paid($conn, (int)$_GET['pay']);
    $message = 'Invoice marked as PAID.';
    $msgType = 'success';
}

$unpaid = $conn->query("SELECT COALESCE(SUM(total_amount_due),0) AS t FROM invoices WHERE status='UNPAID'")->fetch_assoc()['t'];
$paidMonth = $conn->query("SELECT COALESCE(SUM(total_amount_due),0) AS t FROM invoices WHERE status='PAID' AND billing_period = '" . date('Y-m') . "'")->fetch_assoc()['t'];
$invoiceCount = $conn->query("SELECT COUNT(*) AS c FROM invoices")->fetch_assoc()['c'];

$invoices = $conn->query("
    SELECT i.*, g.first_name, g.last_name, rm.room_number
    FROM invoices i
    JOIN leases l ON l.id = i.lease_id
    JOIN guests g ON g.id = l.guest_id
    JOIN rooms rm ON rm.id = l.room_id
    ORDER BY i.id DESC
");
?>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show mb-4">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <small class="text-muted">All invoices</small>
            <div class="stat-value"><?= (int)$invoiceCount ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <small class="text-muted">Unpaid balance</small>
            <div class="stat-value"><?= $peso ?><?= number_format((float)$unpaid, 2) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <small class="text-muted">Paid this month</small>
            <div class="stat-value"><?= $peso ?><?= number_format((float)$paidMonth, 2) ?></div>
        </div>
    </div>
</div>

<div class="table-container mb-4">
    <div class="table-title"><span><i class="bi bi-magic text-pink me-2"></i> Generate monthly invoices</span></div>
    <form method="POST" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Billing period</label>
            <input type="month" name="billing_period" class="form-control" value="<?= date('Y-m') ?>">
        </div>
        <div class="col-md-4">
            <button type="submit" name="generate_invoices" class="btn btn-pink">
                Consolidate rent + pending charges
            </button>
        </div>
    </form>
    <p class="text-muted mt-3 mb-0">Each invoice = monthly rent + canteen/appliance fees still marked PENDING. Those fees then become BILLED.</p>
</div>

<div class="table-container">
    <div class="table-title">
        <span><i class="bi bi-file-earmark-text text-pink me-2"></i> Billing & invoices</span>
        <a href="for_charges.php" class="btn btn-pink btn-sm">Add charges</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Tenant</th>
                    <th>Room</th>
                    <th>Period</th>
                    <th>Base rent</th>
                    <th>Additional</th>
                    <th>Total due</th>
                    <th>Due date</th>
                    <th>Status</th>
                    <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($invoices && $invoices->num_rows > 0): ?>
                    <?php while ($inv = $invoices->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= (int)$inv['id'] ?></td>
                        <td><?= htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']) ?></td>
                        <td><?= htmlspecialchars($inv['room_number']) ?></td>
                        <td><?= htmlspecialchars($inv['billing_period']) ?></td>
                        <td><?= $peso ?><?= number_format((float)$inv['base_rent'], 2) ?></td>
                        <td><?= $peso ?><?= number_format((float)$inv['additional_charges_total'], 2) ?></td>
                        <td class="fw-bold"><?= $peso ?><?= number_format((float)$inv['total_amount_due'], 2) ?></td>
                        <td><?= date('M d, Y', strtotime($inv['due_date'])) ?></td>
                        <td>
                            <?php if ($inv['status'] === 'PAID'): ?>
                                <span class="badge bg-success">PAID</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">UNPAID</span>
                            <?php endif; ?>
                        </td>
                        <?php if (isAdmin()): ?>
                        <td>
                            <?php if ($inv['status'] !== 'PAID'): ?>
                                <a href="?pay=<?= (int)$inv['id'] ?>" class="btn btn-sm btn-pink" onclick="return confirm('Mark this invoice as paid?')">Mark paid</a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center text-muted py-4">No invoices yet. Generate a billing period first.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once 'footer.php'; ?>
