<?php
/**
 * Accrual billing: pending canteen/appliance charges roll into monthly invoices.
 */

function bh_add_pending_charge(mysqli $conn, int $leaseId, string $category, string $description, float $amount): bool
{
    $allowed = ['CANTEEN', 'APPLIANCE', 'OTHER'];
    if (!in_array($category, $allowed, true) || $amount <= 0) {
        return false;
    }
    $stmt = $conn->prepare("
        INSERT INTO pending_charges (lease_id, category, description, amount, status)
        VALUES (?, ?, ?, ?, 'PENDING')
    ");
    $stmt->bind_param('issd', $leaseId, $category, $description, $amount);
    return $stmt->execute();
}

function bh_generate_monthly_invoices(mysqli $conn, ?string $period = null): array
{
    $period = $period ?: date('Y-m');
    $dueDate = date('Y-m-d', strtotime($period . '-01 +1 month +4 days'));
    $created = 0;
    $updated = 0;

    $leases = $conn->query("
        SELECT l.*
        FROM leases l
        WHERE l.status = 'active'
          AND l.start_date <= LAST_DAY(STR_TO_DATE(CONCAT('$period','-01'), '%Y-%m-%d'))
          AND (l.end_date IS NULL OR l.end_date >= STR_TO_DATE(CONCAT('$period','-01'), '%Y-%m-%d'))
    ");

    if (!$leases) {
        return ['created' => 0, 'updated' => 0, 'error' => $conn->error];
    }

    while ($lease = $leases->fetch_assoc()) {
        $leaseId = (int)$lease['id'];
        $pending = $conn->query("
            SELECT COALESCE(SUM(amount),0) AS total
            FROM pending_charges
            WHERE lease_id = $leaseId AND status = 'PENDING'
        ")->fetch_assoc();
        $pendingTotal = (float)$pending['total'];
        $baseRent = (float)$lease['monthly_rent'];

        $existing = $conn->query("
            SELECT * FROM invoices
            WHERE lease_id = $leaseId AND billing_period = '$period'
            LIMIT 1
        ")->fetch_assoc();

        if ($existing && $existing['status'] === 'PAID') {
            continue;
        }

        if (!$existing) {
            $total = $baseRent + $pendingTotal;
            $stmt = $conn->prepare("
                INSERT INTO invoices (lease_id, billing_period, base_rent, additional_charges_total, total_amount_due, status, due_date)
                VALUES (?, ?, ?, ?, ?, 'UNPAID', ?)
            ");
            $stmt->bind_param('isddds', $leaseId, $period, $baseRent, $pendingTotal, $total, $dueDate);
            $stmt->execute();
            $invoiceId = $stmt->insert_id;
            $created++;
        } else {
            $invoiceId = (int)$existing['id'];
            $newAdditional = (float)$existing['additional_charges_total'] + $pendingTotal;
            $newTotal = (float)$existing['base_rent'] + $newAdditional;
            $stmt = $conn->prepare("
                UPDATE invoices
                SET additional_charges_total = ?, total_amount_due = ?
                WHERE id = ?
            ");
            $stmt->bind_param('ddi', $newAdditional, $newTotal, $invoiceId);
            $stmt->execute();
            $updated++;
        }

        if ($pendingTotal > 0) {
            $upd = $conn->prepare("
                UPDATE pending_charges
                SET status = 'BILLED', invoice_id = ?
                WHERE lease_id = ? AND status = 'PENDING'
            ");
            $upd->bind_param('ii', $invoiceId, $leaseId);
            $upd->execute();
        }
    }

    return ['created' => $created, 'updated' => $updated, 'period' => $period];
}

function bh_mark_invoice_paid(mysqli $conn, int $invoiceId): bool
{
    $stmt = $conn->prepare("UPDATE invoices SET status = 'PAID' WHERE id = ?");
    $stmt->bind_param('i', $invoiceId);
    return $stmt->execute();
}
