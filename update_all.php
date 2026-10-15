<?php
include 'connect_db.php'; 

// 1. Generate real hashes for both passwords
$admin_hash = password_hash("admin21", PASSWORD_DEFAULT);
$staff_hash = password_hash("staff21", PASSWORD_DEFAULT);

// 2. Update the Admin account
$conn->query("UPDATE users SET password='$admin_hash' WHERE username='admin'");

// 3. Update the Staff account
$conn->query("UPDATE users SET password='$staff_hash' WHERE username='kurt'");

echo "<h3>✅ Both accounts updated successfully!</h3>";
echo "<ul>";
echo "<li><strong>Admin Login:</strong> admin / admin21</li>";
echo "<li><strong>Staff Login:</strong> staff / staff21</li>";
echo "</ul>";
?>