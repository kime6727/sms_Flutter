<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/Database.php';

$db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);

try {
    $db->query("
        CREATE TABLE IF NOT EXISTS service_coefficients (
            id INT PRIMARY KEY AUTO_INCREMENT,
            service_id INT NOT NULL UNIQUE,
            coefficient_before DECIMAL(5,2) DEFAULT NULL COMMENT '充值前系数，NULL则用默认值',
            coefficient_after DECIMAL(5,2) DEFAULT NULL COMMENT '充值后系数，NULL则用默认值',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "Created service_coefficients table.\n";
} catch (Exception $e) {
    echo "Table might already exist: " . $e->getMessage() . "\n";
}

try {
    $db->query("
        INSERT IGNORE INTO system_settings (`key`, value, type, description, created_at) VALUES
        ('default_coefficient_before', '2', 'decimal', '默认充值前价格系数（用户未充值时使用，价格较低）', NOW()),
        ('default_coefficient_after', '4', 'decimal', '默认充值后价格系数（用户充值后使用，价格较高）', NOW())
    ");
    echo "Inserted default coefficient settings.\n";
} catch (Exception $e) {
    echo "Settings might already exist: " . $e->getMessage() . "\n";
}

try {
    $db->query("
        ALTER TABLE users ADD COLUMN has_topup_history TINYINT(1) DEFAULT 0 COMMENT '是否有充值记录'
    ");
    echo "Added has_topup_history to users table.\n";
} catch (Exception $e) {
    echo "Column might already exist: " . $e->getMessage() . "\n";
}

echo "Migration completed.\n";
?>
