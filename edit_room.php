<?php
// edit_room.php - Edit Room with Pink Sidebar
include_once 'auth.php';
include_once 'connect_db.php';
include_once 'header.php';
$peso = '&#8369;';

if (!isAdmin()) {
    echo '<div class="alert alert-danger">Access denied</div>';
    include_once 'footer.php';
    exit();
}

$id = (int)$_GET['id'];
$room = $conn->query("SELECT * FROM rooms WHERE id = $id")->fetch_assoc();
if (!$room) {
    header("Location: for_rooms.php");
    exit();
}

$error = '';
$success = '';

if ($_POST) {
    $room_number = trim($conn->real_escape_string($_POST['room_number']));
    $type = $conn->real_escape_string($_POST['type']);
    $price = floatval($_POST['price']);
    $status = $conn->real_escape_string($_POST['status']);
    
    // Check if room number already exists (excluding current room)
    $check = $conn->query("SELECT id FROM rooms WHERE room_number = '$room_number' AND id != $id");
    if ($check->num_rows > 0) {
        $error = "❌ Room number '$room_number' already exists! Please use a different number.";
    } elseif ($price <= 0) {
        $error = "❌ Price must be greater than 0.";
    } else {
        // Update all fields including status
        $updateQuery = "UPDATE rooms SET 
                        room_number = '$room_number', 
                        type = '$type', 
                        price = '$price', 
                        status = '$status' 
                        WHERE id = $id";
        
        if ($conn->query($updateQuery)) {
            $success = "✅ Room updated successfully!";
            // Redirect after 1 second to show success message
            echo "<script>setTimeout(() => { window.location.href = 'for_rooms.php'; }, 1000);</script>";
        } else {
            $error = "❌ Database error: " . $conn->error;
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="table-container">
            <div class="table-title text-center">
                <span><i class="bi bi-pencil-square text-pink me-2"></i> Edit Room <?= htmlspecialchars($room['room_number']) ?></span>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-hash"></i> Room Number <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="room_number" class="form-control" value="<?= htmlspecialchars($room['room_number']) ?>" required placeholder="e.g., 101, 202, 305">
                    <small class="text-muted">Enter a unique room number</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-tag"></i> Room Type
                    </label>
                    <select name="type" class="form-select">
                        <?php 
                        $roomTypes = ['🙆🏽‍♂️ Single', '👫🏽 Double', '👨‍👩‍👧‍👦 Family', '🌟 Suite', '✨ Deluxe', '👔 Presidential', ];
                        foreach($roomTypes as $t): 
                            $selected = ($t == $room['type']) ? 'selected' : '';
                        ?>
                            <option value="<?= $t ?>" <?= $selected ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                 <label class="form-label fw-semibold">
                        <i class="bi bi-cash-stack"></i> Monthly rent (<?= $peso ?>) <span class="text-danger">*</span>
                </label>
                    <input type="number" step="0.01" name="price" value="<?= $room['price'] ?>" class="form-control" required min="0.01">
                    <small class="text-muted">Minimum price: ₱1.00</small>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-info-circle"></i> Status
                    </label>
                    <select name="status" class="form-select">
                        <option value="available" <?php echo ($room['status'] == 'available') ? 'selected' : ''; ?>>🟢 Available</option>
                        <option value="occupied" <?php echo ($room['status'] == 'occupied') ? 'selected' : ''; ?>>🔴 Occupied</option>
                        <option value="maintenance" <?php echo ($room['status'] == 'maintenance') ? 'selected' : ''; ?>>🟡 Maintenance</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-pink w-100">
                    <i class="bi bi-save"></i> Update Room
                </button>
                <a href="for_rooms.php" class="btn btn-outline-pink w-100 mt-2">
                    <i class="bi bi-arrow-left"></i> Cancel
                </a>
            </form>
        </div>
    </div>
</div>

<style>
    .table-container {
        background: white;
        border-radius: 24px;
        padding: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #ffe2ed;
    }
    
    .table-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 25px;
        color: #333;
        border-bottom: 2px solid #ffe2ed;
        padding-bottom: 15px;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #e83e8c;
        box-shadow: 0 0 0 3px rgba(232,62,140,0.2);
    }
</style>

<?php include_once 'footer.php'; ?>