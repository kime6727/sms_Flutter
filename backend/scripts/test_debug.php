<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/helpers/functions.php';

try {
    $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
    echo "✅ DB connected\n";
    
    // Test getSetting
    $bonusMin = getSetting($db, 'register_bonus_min', '5');
    echo "✅ getSetting works: $bonusMin\n";
    
    // Test user insertion
    $email = 'test_debug_' . time() . '@example.com';
    $username = 'test_debug_' . time();
    $passwordHash = password_hash('12345678', PASSWORD_BCRYPT);
    $nickname = 'User_' . mt_rand(100000, 999999);
    
    $db->query(
        "INSERT INTO users (username, password_hash, email, nickname, balance, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
        [$username, $passwordHash, $email, $nickname, 0]
    );
    $userId = $db->lastInsertId();
    echo "✅ User created: $userId\n";
    
    // Test token generation
    $token = Auth::generateToken($userId);
    echo "✅ Token generated: " . substr($token, 0, 20) . "...\n";
    
    // Test logUserActivity
    logUserActivity($db, $userId, 'manual_register', 'auth', $userId);
    echo "✅ logUserActivity works\n";
    
    echo "\n✅ All tests passed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
