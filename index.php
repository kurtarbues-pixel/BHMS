<?php
// Boarding House Management System
// Redirects root requests to the decoupled React TypeScript frontend.
$frontendUrl = getenv('FRONTEND_URL') ?: 'http://localhost:5173';
$apiUrl = getenv('BHMS_APP_URL') ?: 'http://127.0.0.1:8000';

header("Location: {$frontendUrl}", true, 302);
exit;
