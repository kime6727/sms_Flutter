<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

echo "--- Table: services ---\n";
print_r($db->query("DESCRIBE services")->fetchAll());

echo "\n--- Table: users ---\n";
print_r($db->query("DESCRIBE users")->fetchAll());
?>
