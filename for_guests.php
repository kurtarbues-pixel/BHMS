<?php
// for_guests.php - Guest Management with Pink Sidebar
include_once 'auth.php';
include_once 'connect_db.php';
include_once 'header.php';

$message = '';
$msgType = '';

// Handle guest addition with better duplicate checking and phone validation
if (isset($_POST['add_guest'])) {
    $fn = trim($conn->real_escape_string($_POST['first_name']));
    $ln = trim($conn->real_escape_string($_POST['last_name']));
    $em = trim($conn->real_escape_string($_POST['email']));
    $ph = trim($conn->real_escape_string($_POST['phone']));
    
    if (empty($fn) || empty($ln)) {
        $message = 'First Name and Last Name are required!';
        $msgType = 'danger';
    } 
    // Phone number validation - only numbers allowed
    elseif (!empty($ph) && !preg_match('/^[0-9+\-\s()]+$/', $ph)) {
        $message = '❌ Phone number can only contain numbers, spaces, dashes, plus sign, or parentheses. Letters are not allowed!';
        $msgType = 'danger';
    }
    else {
        // Check for duplicate - by email OR by full name
        $checkQuery = "SELECT id, first_name, last_name, email FROM guests WHERE ";
        $conditions = [];
        
        if (!empty($em)) {
            $conditions[] = "email = '$em'";
        }
        $conditions[] = "(first_name = '$fn' AND last_name = '$ln')";
        
        $checkQuery .= implode(" OR ", $conditions);
        $exists = $conn->query($checkQuery);
        
        if ($exists->num_rows > 0) {
            $duplicate = $exists->fetch_assoc();
            $message = '⚠️ Guest already exists! <strong>' . htmlspecialchars($duplicate['first_name'] . ' ' . $duplicate['last_name']) . '</strong> is already registered.';
            $msgType = 'warning';
        } else {
            $conn->query("INSERT INTO guests (first_name, last_name, email, phone) VALUES ('$fn','$ln','$em','$ph')");
            $message = '✅ Guest added successfully!';
            $msgType = 'success';
            echo "<script>setTimeout(() => { window.location='for_guests.php'; }, 1000);</script>";
        }
    }
}

// Delete guest (admin only)
if (isAdmin() && isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // First check if guest has any reservations
    $checkReservations = $conn->query("SELECT id FROM reservations WHERE guest_id = $id");
    
    if ($checkReservations->num_rows > 0) {
        $message = "⚠️ Cannot delete this guest because they have existing reservations. Delete the reservations first or reassign them.";
        $msgType = 'warning';
    } else {
        $conn->query("DELETE FROM guests WHERE id = $id");
        $message = "✅ Guest deleted successfully!";
        $msgType = 'success';
        echo "<script>setTimeout(() => { window.location='for_guests.php'; }, 1000);</script>";
    }
}

// Get all guests for display
$guestsList = $conn->query("SELECT * FROM guests ORDER BY id DESC");
$totalGuests = $conn->query("SELECT COUNT(*) as c FROM guests")->fetch_assoc()['c'];

// Count new this month (last 30 days)
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
$newThisMonth = $conn->query("SELECT COUNT(*) as c FROM guests WHERE created_at >= '$thirtyDaysAgo'")->fetch_assoc()['c'];
?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Total tenants</small>
                    <div class="stat-value"><?= $totalGuests ?></div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-people fs-4 text-pink"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">New This Month</small>
                    <div class="stat-value"><?= $newThisMonth ?></div>
                </div>
                <div class="stat-icon">
                    <i class="bi bi-person-plus fs-4 text-pink"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Message Alert -->
<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?> alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-<?= $msgType == 'success' ? 'check-circle' : ($msgType == 'warning' ? 'exclamation-triangle' : 'info-circle') ?> me-2"></i>
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Add Guest Form & Guest List -->
<div class="row g-4">
    <div class="col-md-5">
        <div class="table-container">
            <div class="table-title">
                <span><i class="bi bi-person-plus-fill text-pink me-2"></i> Add new tenant</span>
            </div>
            <form method="POST" id="guestForm">
                <div class="mb-3">
                    <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" id="first_name" class="form-control" placeholder="Enter first name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" id="last_name" class="form-control" placeholder="Enter last name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="tenant@example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Phone Number</label>
                    <input type="tel" name="phone" id="phone" class="form-control" placeholder="+63 XXX XXX XXXX" pattern="[0-9+\-\s()]+" title="Only numbers, spaces, dashes, plus sign, and parentheses are allowed">
                    <small class="text-muted" id="phoneError" style="display: none; color: #dc3545;">
                        <i class="bi bi-exclamation-circle"></i> Only numbers, spaces, dashes, plus sign (+), and parentheses are allowed. Letters are not permitted.
                    </small>
                    <small class="text-muted" id="phoneHint"><i class="bi bi-info-circle"></i> Example: +639123456789 or 09123456789</small>
                </div>
                <button type="submit" name="add_guest" class="btn btn-pink w-100" id="submitBtn">
                    <i class="bi bi-save"></i> Save tenant
                </button>
            </form>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="table-container">
            <div class="table-title">
                <span><i class="bi bi-clock-history text-pink me-2"></i> All tenants</span>
                <span class="badge" style="background: #ffe4ee; color: #e83e8c;"><?= $guestsList->num_rows ?> Guests</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <?php if (isAdmin()): ?>
                            <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($guestsList->num_rows > 0): ?>
                            <?php while($guest = $guestsList->fetch_assoc()): ?>
                            <tr>
                                <td>#<?= $guest['id'] ?></td>
                                <td><strong><?= htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']) ?></strong></td>
                                <td><?= htmlspecialchars($guest['email'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($guest['phone'] ?? '—') ?></td>
                                <?php if (isAdmin()): ?>
                                <td>
                                    <a href="?delete=<?= $guest['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('⚠️ WARNING: This will permanently delete this guest. Are you sure?')">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No guests registered yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Cleanup Duplicates Button (Admin only) -->
<?php if (isAdmin()): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-brush text-pink me-2"></i>
                    <strong>Need to clean up duplicate guests?</strong>
                    <span class="text-muted ms-2">Use the tool below to merge duplicate entries.</span>
                </div>
                <a href="cleanup_duplicates.php" class="btn btn-outline-pink">
                    <i class="bi bi-brush"></i> Clean Up Duplicates
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Live phone number validation (client-side)
document.getElementById('phone').addEventListener('input', function(e) {
    const phoneInput = e.target;
    const phoneValue = phoneInput.value;
    const phoneError = document.getElementById('phoneError');
    const phoneHint = document.getElementById('phoneHint');
    const submitBtn = document.getElementById('submitBtn');
    
    // Regular expression to check if contains letters (a-z or A-Z)
    const hasLetters = /[a-zA-Z]/g.test(phoneValue);
    
    if (hasLetters && phoneValue.length > 0) {
        phoneError.style.display = 'block';
        phoneHint.style.display = 'none';
        phoneInput.style.borderColor = '#dc3545';
        phoneInput.style.backgroundColor = '#fff8f8';
        submitBtn.disabled = true;
    } else {
        phoneError.style.display = 'none';
        phoneHint.style.display = 'block';
        phoneInput.style.borderColor = '#ffd9e6';
        phoneInput.style.backgroundColor = 'white';
        submitBtn.disabled = false;
    }
});

// Also validate on form submit (double protection)
document.getElementById('guestForm').addEventListener('submit', function(e) {
    const phoneValue = document.getElementById('phone').value;
    const hasLetters = /[a-zA-Z]/g.test(phoneValue);
    
    if (hasLetters && phoneValue.length > 0) {
        e.preventDefault();
        alert('❌ Phone number cannot contain letters! Please use only numbers, spaces, dashes, or plus sign.');
        return false;
    }
});
</script>

<?php include_once 'footer.php'; ?>