<?php
/**
 * 数据库安装脚本
 * 运行一次即可创建所有表和默认管理员
 */

// 加载配置
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/Database.php';

echo "=== SMS 接码平台 数据库安装脚本 ===\n\n";

try {
    // 连接数据库（不使用具体数据库名，先创建数据库
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 创建数据库
    $dbName = DB_NAME;
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");

    echo "✓ 数据库 '$dbName' 已创建/已存在\n";

    // 创建表
    $tables = [
        // admins 表
        "CREATE TABLE IF NOT EXISTS admins (
            id VARCHAR(36) PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            role VARCHAR(20) DEFAULT 'admin',
            status VARCHAR(20) DEFAULT 'active',
            last_login DATETIME,
            login_ip VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // users 表
        "CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(36) PRIMARY KEY,
            device_id VARCHAR(255) UNIQUE,
            username VARCHAR(255) UNIQUE,
            password_hash VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(20),
            nickname VARCHAR(255),
            avatar VARCHAR(255),
            status VARCHAR(20) DEFAULT 'active',
            role VARCHAR(20) DEFAULT 'user',
            balance DECIMAL(10, 2) DEFAULT 0,
            total_spent DECIMAL(10, 2) DEFAULT 0,
            order_count INT DEFAULT 0,
            has_topup_history TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME,
            login_ip VARCHAR(45),
            register_ip VARCHAR(45),
            notes TEXT,
            INDEX idx_device_id (device_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // services 表
        "CREATE TABLE IF NOT EXISTS services (
            id INT PRIMARY KEY AUTO_INCREMENT,
            hero_service_id VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            name_en VARCHAR(255),
            name_cn VARCHAR(255),
            code VARCHAR(50),
            icon VARCHAR(255),
            description TEXT,
            active TINYINT DEFAULT 1,
            is_published TINYINT DEFAULT 0,
            is_pinned TINYINT DEFAULT 0,
            tag TINYINT DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (active),
            INDEX idx_is_published (is_published),
            INDEX idx_sort_order (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // countries 表
        "CREATE TABLE IF NOT EXISTS countries (
            id INT PRIMARY KEY AUTO_INCREMENT,
            hero_country_id VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            name_en VARCHAR(255),
            name_cn VARCHAR(255),
            code VARCHAR(10),
            flag VARCHAR(10),
            phone_code VARCHAR(10),
            active TINYINT DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (active),
            INDEX idx_sort_order (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // service_countries 表
        "CREATE TABLE IF NOT EXISTS service_countries (
            id INT PRIMARY KEY AUTO_INCREMENT,
            service_id INT NOT NULL,
            country_id INT NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            active TINYINT DEFAULT 1,
            is_published TINYINT DEFAULT 0,
            is_auto TINYINT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
            UNIQUE KEY unique_service_country (service_id, country_id),
            INDEX idx_service_id (service_id),
            INDEX idx_country_id (country_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // orders 表
        "CREATE TABLE IF NOT EXISTS orders (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            service_id INT,
            country_id INT,
            service_name VARCHAR(255),
            country_name VARCHAR(255),
            phone_number VARCHAR(20),
            status VARCHAR(20) DEFAULT 'pending',
            total_price DECIMAL(10, 2) NOT NULL,
            cost_price DECIMAL(10, 2) DEFAULT 0,
            profit DECIMAL(10, 2) DEFAULT 0,
            hero_order_id VARCHAR(50),
            hero_status VARCHAR(50),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at DATETIME,
            completed_at DATETIME,
            cancelled_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_expires_at (expires_at),
            INDEX idx_hero_order_id (hero_order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // sms_messages 表
        "CREATE TABLE IF NOT EXISTS sms_messages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            order_id VARCHAR(36) NOT NULL,
            sender VARCHAR(50),
            content TEXT NOT NULL,
            code VARCHAR(20),
            received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            INDEX idx_order_id (order_id),
            INDEX idx_received_at (received_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // payment_records 表
        "CREATE TABLE IF NOT EXISTS payment_records (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            transaction_id VARCHAR(255) UNIQUE NOT NULL,
            product_id VARCHAR(50) NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            environment VARCHAR(20),
            status VARCHAR(20) DEFAULT 'completed',
            order_id VARCHAR(36),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_transaction_id (transaction_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // credit_transactions 表
        "CREATE TABLE IF NOT EXISTS credit_transactions (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            type VARCHAR(20) NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            balance_after DECIMAL(10, 2) DEFAULT 0,
            description VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // payment_configs 表
        "CREATE TABLE IF NOT EXISTS payment_configs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            product_id VARCHAR(50) UNIQUE NOT NULL,
            min_price DECIMAL(10, 2) NOT NULL,
            max_price DECIMAL(10, 2) NOT NULL,
            display_price DECIMAL(10, 2) NOT NULL,
            credits INT DEFAULT 0,
            config_name VARCHAR(255),
            description TEXT,
            active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (active),
            INDEX idx_min_price (min_price),
            INDEX idx_max_price (max_price)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // system_settings 表
        "CREATE TABLE IF NOT EXISTS system_settings (
            `key` VARCHAR(255) PRIMARY KEY,
            value TEXT,
            type VARCHAR(20) DEFAULT 'string',
            description TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // admin_logs 表
        "CREATE TABLE IF NOT EXISTS admin_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id VARCHAR(36) NOT NULL,
            admin_username VARCHAR(255),
            action VARCHAR(255) NOT NULL,
            resource VARCHAR(255),
            resource_id VARCHAR(36),
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
            INDEX idx_admin_id (admin_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // favorites 表
        "CREATE TABLE IF NOT EXISTS favorites (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            service_id INT NOT NULL,
            country_id INT NOT NULL,
            name VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE,
            UNIQUE KEY unique_favorite (user_id, service_id, country_id),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // membership_levels 表
        "CREATE TABLE IF NOT EXISTS membership_levels (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(50) NOT NULL,
            name_en VARCHAR(50),
            name_cn VARCHAR(50),
            min_spent DECIMAL(10, 2) DEFAULT 0,
            discount DECIMAL(3, 2) DEFAULT 1.00,
            icon VARCHAR(50),
            color VARCHAR(20),
            active TINYINT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_min_spent (min_spent)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // devices 表
        "CREATE TABLE IF NOT EXISTS devices (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            device_token VARCHAR(255),
            device_type VARCHAR(20) DEFAULT 'ios',
            app_version VARCHAR(20),
            os_version VARCHAR(20),
            push_enabled TINYINT DEFAULT 1,
            last_active DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_device_token (device_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // notifications 表
        "CREATE TABLE IF NOT EXISTS notifications (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(100),
            body TEXT,
            data JSON,
            status VARCHAR(20) DEFAULT 'pending',
            sent_at DATETIME,
            read_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // service_coefficients 表
        "CREATE TABLE IF NOT EXISTS service_coefficients (
            id INT PRIMARY KEY AUTO_INCREMENT,
            service_id INT NOT NULL,
            coefficient_before DECIMAL(5, 2) DEFAULT 1.00,
            coefficient_after DECIMAL(5, 2) DEFAULT 1.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            UNIQUE KEY unique_service (service_id),
            INDEX idx_service_id (service_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($tables as $i => $sql) {
        $pdo->exec($sql);
        echo "✓ 表 " . ($i + 1) . "/" . count($tables) . " 已创建\n";
    }

    echo "\n=== 创建默认管理员账号 ===\n";

    // 检查是否已有管理员
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // 创建默认管理员
        $adminId = 'admin_001';
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (id, username, password, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$adminId, 'admin', $adminPassword, 'admin@example.com', 'admin', 'active']);
        echo "✓ 默认管理员账号已创建\n";
        echo "  用户名: admin\n";
        echo "  密码: admin123\n";
    } else {
        echo "✓ 管理员账号已存在 ($count 个)\n";
    }

    echo "\n=== 安装完成！ ===\n";
    echo "请访问: https://newsms.weburl.cloudns.be/admin/\n";
    echo "登录: admin / admin123\n";

} catch (PDOException $e) {
    echo "✗ 数据库错误: " . $e->getMessage() . "\n";
    echo "\n请确保:\n";
    echo "1. MySQL 服务已启动\n";
    echo "2. .env 文件中的数据库配置正确\n";
    echo "3. MySQL 用户有创建数据库和表的权限\n";
}
?>
