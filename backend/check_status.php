<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

header('Content-Type: application/json');

$stats = [
    'services_total' => $db->query("SELECT COUNT(*) FROM services")->fetchColumn(),
    'services_published' => $db->query("SELECT COUNT(*) FROM services WHERE is_published = 1")->fetchColumn(),
    'countries_total' => $db->query("SELECT COUNT(*) FROM countries")->fetchColumn(),
    'service_countries_published' => $db->query("SELECT COUNT(*) FROM service_countries WHERE is_published = 1")->fetchColumn(),
    'api_key_in_db' => $db->query("SELECT value FROM system_settings WHERE `key` = 'api_key'")->fetchColumn(),
    'users_count' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];

echo json_encode($stats, JSON_PRETTY_PRINT);
