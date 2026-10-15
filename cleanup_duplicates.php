<?php
// cleanup_duplicates.php - Fix Duplicate Guests with Auto-Increment
include_once 'auth.php';
include_once 'connect_db.php';
include_once 'header.php';

// Only admin can access this page
if (!isAdmin()) {
    echo '<div class="alert alert-danger">Access denied. Only administrators can clean up duplicates.</div>';
    include_once 'footer.php';
    exit();
}

$message = '';
$messageType = '';

// Fix auto-increment after deletion
function fixAutoIncrement($conn) {
    $result = $conn->query("SELECT MAX(id) as max_id FROM guests");
    $row = $result->fetch_assoc();
    $max_id = $row['max_id'];
    if ($max_id) {
        $conn->query("ALTER TABLE guests AUTO_INCREMENT = " . ($max_id + 1));
    }
}

// Handle duplicate cleanup
if (isset($_POST['cleanup_duplicates'])) {
    
    // 1. Clean up duplicate emails
    $sql = "SELECT email, COUNT(*) as count, GROUP_CONCAT(id) as ids, GROUP_CONCAT(first_name) as first_names, GROUP_CONCAT(last_name) as last_names
            FROM guests 
            WHERE email IS NOT NULL AND email != ''
            GROUP BY email 
            HAVING COUNT(*) > 1";
    
    $result = $conn->query($sql);
    $emailDeleted = 0;
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $ids = explode(',', $row['ids']);
            $keepId = $ids[0];
            array_shift($ids);
            $deleteIds = implode(',', $ids);
            
            if ($deleteIds) {
                // First, check if any reservations are linked to these guests
                $checkReservations = $conn->query("SELECT guest_id FROM reservations WHERE guest_id IN ($deleteIds)");
                if ($checkReservations->num_rows > 0) {
                    // Update reservations to the kept guest ID
                    $conn->query("UPDATE reservations SET guest_id = $keepId WHERE guest_id IN ($deleteIds)");
                }
                // Delete duplicate guests
                $conn->query("DELETE FROM guests WHERE id IN ($deleteIds)");
                $emailDeleted += count($ids);
            }
        }
        $message .= "✅ Deleted $emailDeleted duplicate guest(s) by email. ";
        $messageType = 'success';
    }
    
    // 2. Clean up duplicate name combinations (without email)
    $sql2 = "SELECT first_name, last_name, COUNT(*) as count, GROUP_CONCAT(id) as ids
             FROM guests 
             GROUP BY first_name, last_name 
             HAVING COUNT(*) > 1";
    
    $result2 = $conn->query($sql2);
    $nameDeleted = 0;
    
    if ($result2->num_rows > 0) {
        while ($row = $result2->fetch_assoc()) {
            $ids = explode(',', $row['ids']);
            $keepId = $ids[0];
            array_shift($ids);
            $deleteIds = implode(',', $ids);
            
            if ($deleteIds) {
                // First, check if any reservations are linked to these guests
                $checkReservations = $conn->query("SELECT guest_id FROM reservations WHERE guest_id IN ($deleteIds)");
                if ($checkReservations->num_rows > 0) {
                    // Update reservations to the kept guest ID
                    $conn->query("UPDATE reservations SET guest_id = $keepId WHERE guest_id IN ($deleteIds)");
                }
                // Delete duplicate guests
                $conn->query("DELETE FROM guests WHERE id IN ($deleteIds)");
                $nameDeleted += count($ids);
            }
        }
        $message .= "✅ Deleted $nameDeleted duplicate guest(s) by name. ";
        $messageType = 'success';
    }
    
    // Fix auto-increment
    fixAutoIncrement($conn);
    
    if ($emailDeleted == 0 && $nameDeleted == 0) {
        $message = "✨ No duplicates found! All guests are unique.";
        $messageType = 'info';
    }
    
    // Refresh the page to show updated results
    echo "<script>setTimeout(() => { window.location='cleanup_duplicates.php'; }, 1500);</script>";
}

// Get current statistics
$totalGuests = $conn->query("SELECT COUNT(*) as c FROM guests")->fetch_assoc()['c'];
$uniqueEmails = $conn->query("SELECT COUNT(DISTINCT email) as c FROM guests WHERE email IS NOT NULL AND email != ''")->fetch_assoc()['c'];
$duplicateEmails = $conn->query("
    SELECT COUNT(*) as c FROM (
        SELECT email FROM guests 
        WHERE email IS NOT NULL AND email != '' 
        GROUP BY email HAVING COUNT(*) > 1
    ) as duplicates
")->fetch_assoc()['c'];

$duplicateNames = $conn->query("
    SELECT COUNT(*) as c FROM (
        SELECT first_name, last_name FROM guests 
        GROUP BY first_name, last_name HAVING COUNT(*) > 1
    ) as duplicates
")->fetch_assoc()['c'];

// Check for duplicate guests
$duplicateEmailsList = $conn->query("
    SELECT email, COUNT(*) as count, GROUP_CONCAT(id) as ids, GROUP_CONCAT(first_name) as first_names, GROUP_CONCAT(last_name) as last_names
    FROM guests 
    WHERE email IS NOT NULL AND email != ''
    GROUP BY email 
    HAVING COUNT(*) > 1
");

$duplicateNamesList = $conn->query("
    SELECT first_name, last_name, COUNT(*) as count, GROUP_CONCAT(id) as ids
    FROM guests 
    GROUP BY first_name, last_name 
    HAVING COUNT(*) > 1
");
?>

<div class="row">
    <div class="col-12">
        <div class="table-container">
            <div class="table-title">
                <span><i class="bi bi-brush text-pink me-2"></i> Clean Up Duplicate Guests</span>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
                    <i class="bi bi-<?= $messageType == 'success' ? 'check-circle' : 'info-circle' ?> me-2"></i>
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <small class="text-muted">Total Guests</small>
                                <div class="stat-value"><?= $totalGuests ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-people fs-4 text-pink"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <small class="text-muted">Unique Emails</small>
                                <div class="stat-value"><?= $uniqueEmails ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-envelope fs-4 text-pink"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <small class="text-muted">Duplicate Emails</small>
                                <div class="stat-value text-warning"><?= $duplicateEmails ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-exclamation-triangle fs-4 text-warning"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="d-flex justify-content-between">
                            <div>
                                <small class="text-muted">Duplicate Names</small>
                                <div class="stat-value text-warning"><?= $duplicateNames ?></div>
                            </div>
                            <div class="stat-icon"><i class="bi bi-person-x fs-4 text-warning"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Duplicate Emails Table -->
            <h5 class="mt-4 mb-3"><i class="bi bi-envelope-exclamation me-2 text-pink"></i> Duplicate Emails Found</h5>
            <div class="table-responsive mb-4">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Count</th>
                            <th>Guest IDs</th>
                            <th>Names</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($duplicateEmailsList->num_rows > 0): ?>
                            <?php while($row = $duplicateEmailsList->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['email']) ?></strong></td>
                                    <td><span class="badge bg-warning text-dark"><?= $row['count'] ?> duplicates</span></td>
                                    <td><?= $row['ids'] ?></td>
                                    <td><?= htmlspecialchars($row['first_names']) ?><br><?= htmlspecialchars($row['last_names']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-success">✓ No duplicate emails found!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Duplicate Names Table -->
            <h5 class="mb-3"><i class="bi bi-person-exclamation me-2 text-pink"></i> Duplicate Names Found</h5>
            <div class="table-responsive mb-4">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Full Name</th>
                            <th>Count</th>
                            <th>Guest IDs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($duplicateNamesList->num_rows > 0): ?>
                            <?php while($row = $duplicateNamesList->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></strong>}</td>
                                    <td><span class="badge bg-warning text-dark"><?= $row['count'] ?> duplicates</span>}</td>
                                    <td><?= $row['ids'] ?>}</td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-success">✓ No duplicate names found!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Action Buttons -->
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                <strong>How it works:</strong> This tool will keep the first occurrence of each duplicate and delete the rest. 
                Any reservations linked to deleted guests will be reassigned to the kept guest.
            </div>
            
            <form method="POST" onsubmit="return confirm('⚠️ WARNING: This will delete duplicate guest records. This action cannot be undone! Are you sure?')">
                <div class="d-flex gap-3">
                    <button type="submit" name="cleanup_duplicates" class="btn btn-pink" <?= ($duplicateEmails == 0 && $duplicateNames == 0) ? 'disabled' : '' ?>>
                        <i class="bi bi-brush"></i> Clean Up Duplicates
                    </button>
                    <a href="for_guests.php" class="btn btn-outline-pink">
                        <i class="bi bi-arrow-left"></i> Back to Guests
                    </a>
                    <a href="home.php" class="btn btn-outline-pink">
                        <i class="bi bi-house"></i> Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 15px;
        transition: all 0.3s ease;
        border: 1px solid #ffe2ed;
    }
    .stat-value {
        font-size: 1.8rem;
        font-weight: 800;
        color: #e83e8c;
    }
    .stat-icon {
        width: 45px;
        height: 45px;
        background: #ffe4ee;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .table-container {
        background: white;
        border-radius: 24px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #ffe2ed;
    }
    .table-title {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 20px;
        color: #333;
    }
</style>

<?php include_once 'footer.php'; ?>