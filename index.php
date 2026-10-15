<?php
// Keep the legacy XAMPP project URL pointed at the active React/Laravel app.
$applicationUrl = getenv('BHMS_APP_URL') ?: 'http://127.0.0.1:8000/';
header('Location: ' . $applicationUrl, true, 302);
exit;
