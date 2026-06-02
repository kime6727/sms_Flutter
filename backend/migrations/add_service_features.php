<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

try {
    // Add is_pinned to services
    $db->query("ALTER TABLE services ADD COLUMN is_pinned TINYINT(1) DEFAULT 0 AFTER is_published");
    echo "Added is_pinned to services table.\n";
} catch (Exception $e) {
    echo "is_pinned might already exist: " . $e->getMessage() . "\n";
}

try {
    // Add tag to services (0=None, 1=Hot, 2=Recommend)
    $db->query("ALTER TABLE services ADD COLUMN tag TINYINT(1) DEFAULT 0 AFTER is_pinned");
    echo "Added tag to services table.\n";
} catch (Exception $e) {
    echo "tag might already exist: " . $e->getMessage() . "\n";
}

echo "Migration completed.\n";
?>
