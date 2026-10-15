<?php
include_once 'connect_db.php';
bh_sync_room_status($conn);
echo "Room statuses updated at " . date('Y-m-d H:i:s');
