<?php
/**
 * Ensures the boarding_house_management_system schema exists
 * and backfills leases from existing reservations.
 */
function bh_ensure_schema(mysqli $conn, string $host, string $username, string $password): void
{
    $sqls = [
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) DEFAULT NULL,
            `password` varchar(255) DEFAULT NULL,
            `role` enum('admin','staff') DEFAULT 'staff',
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `guests` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `first_name` varchar(100) NOT NULL,
            `last_name` varchar(100) NOT NULL,
            `email` varchar(150) DEFAULT NULL,
            `phone` varchar(25) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `rooms` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `room_number` varchar(10) NOT NULL,
            `type` varchar(50) DEFAULT NULL,
            `price` decimal(12,2) DEFAULT 0.00,
            `status` enum('available','occupied','maintenance') DEFAULT 'available',
            PRIMARY KEY (`id`),
            UNIQUE KEY `room_number` (`room_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `reservations` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `guest_id` int(10) UNSIGNED NOT NULL,
            `room_id` int(10) UNSIGNED NOT NULL,
            `check_in` date DEFAULT NULL,
            `check_out` date DEFAULT NULL,
            `total` decimal(12,2) DEFAULT 0.00,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `guest_id` (`guest_id`),
            KEY `room_id` (`room_id`),
            CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `leases` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `guest_id` int(10) UNSIGNED NOT NULL,
            `room_id` int(10) UNSIGNED NOT NULL,
            `reservation_id` int(10) UNSIGNED DEFAULT NULL,
            `start_date` date NOT NULL,
            `end_date` date DEFAULT NULL,
            `monthly_rent` decimal(12,2) NOT NULL DEFAULT 0.00,
            `status` enum('active','ended','cancelled') DEFAULT 'active',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `reservation_id` (`reservation_id`),
            KEY `guest_id` (`guest_id`),
            KEY `room_id` (`room_id`),
            CONSTRAINT `leases_guest_fk` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE CASCADE,
            CONSTRAINT `leases_room_fk` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
            CONSTRAINT `leases_reservation_fk` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `invoices` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `lease_id` int(10) UNSIGNED NOT NULL,
            `billing_period` varchar(7) NOT NULL,
            `base_rent` decimal(12,2) NOT NULL DEFAULT 0.00,
            `additional_charges_total` decimal(12,2) NOT NULL DEFAULT 0.00,
            `total_amount_due` decimal(12,2) NOT NULL DEFAULT 0.00,
            `status` enum('UNPAID','PAID') DEFAULT 'UNPAID',
            `due_date` date NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `lease_period` (`lease_id`,`billing_period`),
            CONSTRAINT `invoices_lease_fk` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS `pending_charges` (
            `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `lease_id` int(10) UNSIGNED NOT NULL,
            `category` enum('CANTEEN','APPLIANCE','OTHER') NOT NULL DEFAULT 'OTHER',
            `description` varchar(255) DEFAULT NULL,
            `amount` decimal(12,2) NOT NULL,
            `status` enum('PENDING','BILLED') DEFAULT 'PENDING',
            `invoice_id` int(10) UNSIGNED DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `lease_id` (`lease_id`),
            KEY `invoice_id` (`invoice_id`),
            CONSTRAINT `charges_lease_fk` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`id`) ON DELETE CASCADE,
            CONSTRAINT `charges_invoice_fk` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($sqls as $sql) {
        if (!$conn->query($sql)) {
            // Foreign-key create can fail if tables already exist without FKs; continue.
        }
    }

    @$conn->query("ALTER TABLE `rooms` MODIFY `price` decimal(12,2) DEFAULT 0.00");
    @$conn->query("ALTER TABLE `reservations` MODIFY `total` decimal(12,2) DEFAULT 0.00");

    bh_seed_from_legacy($conn, $host, $username, $password);
    bh_backfill_leases($conn);
    bh_seed_users($conn);
}

function bh_seed_from_legacy(mysqli $conn, string $host, string $username, string $password): void
{
    $guests = (int)$conn->query("SELECT COUNT(*) AS c FROM guests")->fetch_assoc()['c'];
    if ($guests > 0) {
        return;
    }

    $legacy = @new mysqli($host, $username, $password, 'arbueshotel_db');
    if ($legacy->connect_error) {
        return;
    }

    $tables = ['users', 'guests', 'rooms', 'reservations'];
    foreach ($tables as $table) {
        $check = $legacy->query("SHOW TABLES LIKE '$table'");
        if (!$check || $check->num_rows === 0) {
            continue;
        }
        $rows = $legacy->query("SELECT * FROM `$table`");
        if (!$rows) {
            continue;
        }
        while ($row = $rows->fetch_assoc()) {
            $cols = array_keys($row);
            $colSql = '`' . implode('`,`', $cols) . '`';
            $vals = [];
            foreach ($row as $v) {
                $vals[] = $v === null ? 'NULL' : "'" . $conn->real_escape_string((string)$v) . "'";
            }
            $conn->query("INSERT IGNORE INTO `$table` ($colSql) VALUES (" . implode(',', $vals) . ")");
        }
    }
    $legacy->close();
}

function bh_seed_users(mysqli $conn): void
{
    $count = (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
    if ($count > 0) {
        return;
    }
    $admin = password_hash('admin21', PASSWORD_DEFAULT);
    $staff = password_hash('staff21', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $roleAdmin = 'admin';
    $roleStaff = 'staff';
    $u1 = 'admin';
    $stmt->bind_param('sss', $u1, $admin, $roleAdmin);
    $stmt->execute();
    $u2 = 'kurt';
    $stmt->bind_param('sss', $u2, $staff, $roleStaff);
    $stmt->execute();
}

function bh_backfill_leases(mysqli $conn): void
{
    $conn->query("
        INSERT IGNORE INTO leases (guest_id, room_id, reservation_id, start_date, end_date, monthly_rent, status)
        SELECT r.guest_id, r.room_id, r.id, r.check_in, r.check_out, rm.price,
            CASE
                WHEN r.check_out < CURDATE() THEN 'ended'
                ELSE 'active'
            END
        FROM reservations r
        JOIN rooms rm ON rm.id = r.room_id
        LEFT JOIN leases l ON l.reservation_id = r.id
        WHERE l.id IS NULL
    ");

    $conn->query("
        UPDATE leases l
        JOIN reservations r ON r.id = l.reservation_id
        SET l.start_date = r.check_in,
            l.end_date = r.check_out,
            l.status = CASE WHEN r.check_out < CURDATE() THEN 'ended' ELSE 'active' END
    ");
}

function bh_sync_room_status(mysqli $conn): void
{
    $today = date('Y-m-d');
    $conn->query("
        UPDATE rooms r
        SET r.status = 'occupied'
        WHERE EXISTS (
            SELECT 1 FROM reservations res
            WHERE res.room_id = r.id
            AND res.check_in <= '$today'
            AND res.check_out > '$today'
        )
        AND r.status != 'maintenance'
    ");
    $conn->query("
        UPDATE rooms r
        SET r.status = 'available'
        WHERE NOT EXISTS (
            SELECT 1 FROM reservations res
            WHERE res.room_id = r.id
            AND res.check_in <= '$today'
            AND res.check_out > '$today'
        )
        AND r.status != 'maintenance'
    ");
}

function bh_room_conflict(mysqli $conn, int $roomId, string $checkIn, string $checkOut, int $excludeReservationId = 0)
{
    $sql = "
        SELECT r.id, g.first_name, g.last_name, r.check_in, r.check_out
        FROM reservations r
        JOIN guests g ON r.guest_id = g.id
        WHERE r.room_id = ?
        AND r.check_in < ?
        AND r.check_out > ?
    ";
    if ($excludeReservationId > 0) {
        $sql .= " AND r.id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('issi', $roomId, $checkOut, $checkIn, $excludeReservationId);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iss', $roomId, $checkOut, $checkIn);
    }
    $stmt->execute();
    return $stmt->get_result();
}

function bh_create_lease_for_reservation(mysqli $conn, int $reservationId, int $guestId, int $roomId, string $checkIn, string $checkOut, float $monthlyRent): void
{
    $status = (strtotime($checkOut) < strtotime(date('Y-m-d'))) ? 'ended' : 'active';
    $stmt = $conn->prepare("
        INSERT INTO leases (guest_id, room_id, reservation_id, start_date, end_date, monthly_rent, status)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iiissds', $guestId, $roomId, $reservationId, $checkIn, $checkOut, $monthlyRent, $status);
    $stmt->execute();
}

function bh_billing_months(string $checkIn, string $checkOut): int
{
    $days = (int)ceil((strtotime($checkOut) - strtotime($checkIn)) / 86400);
    return max(1, (int)ceil($days / 30));
}

function bh_room_types(): array
{
    return ['Bedspace', 'Single', 'Shared Twin', 'Studio', 'Family'];
}
