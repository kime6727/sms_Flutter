<?php
/**
 * 数据库类
 */

class Database {
    private $pdo;
    private $stmt;

    /**
     * @param string $host
     * @param string $db
     * @param string $user
     * @param string $pass
     * @param int    $port
     * @param array  $ssl  支持的键: ca (CA 证书文件路径), ca_content (CA 证书 PEM 字符串), verify (true/false, 默认 true)
     */
    public function __construct($host, $db, $user, $pass, $port = 3306, array $ssl = []) {
        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            // SSL 配置：TiDB Cloud / 阿里云 RDS / AWS RDS 等云数据库要求 SSL
            // 1) 显式传入的 $ssl 优先
            // 2) 否则从 config/database.php 定义的全局常量 DB_SSL_* 读取
            if (empty($ssl) && defined('DB_SSL_ENABLED') && DB_SSL_ENABLED) {
                $ssl = [
                    'ca' => defined('DB_SSL_CA') ? DB_SSL_CA : null,
                    'ca_content' => defined('DB_SSL_CA_CONTENT') ? DB_SSL_CA_CONTENT : null,
                    'verify' => defined('DB_SSL_VERIFY') ? DB_SSL_VERIFY : true,
                ];
            }

            if (!empty($ssl)) {
                $caPath = $ssl['ca'] ?? null;
                $caContent = $ssl['ca_content'] ?? null;
                $verify = $ssl['verify'] ?? true;

                // 1. 如果传入了 CA 证书内容（PEM 字符串），写入临时文件
                if (!$caPath && $caContent) {
                    $caPath = sys_get_temp_dir() . '/mysql_ca_' . md5($caContent) . '.pem';
                    if (!file_exists($caPath)) {
                        $pem = $caContent;
                        if (strpos($pem, '-----BEGIN') === false) {
                            $pem = base64_decode($pem);
                            if ($pem === false || strpos($pem, '-----BEGIN') === false) {
                                $pem = $caContent;
                            }
                        }
                        @file_put_contents($caPath, $pem);
                        @chmod($caPath, 0600);
                    }
                }

                // 2. 兜底：CA 路径无效时（文件不存在或为空），自动用系统 CA bundle
                $caFound = $caPath && file_exists($caPath) && filesize($caPath) > 100;
                if ($caPath && !$caFound) {
                    $systemCas = [
                        '/etc/ssl/certs/ca-certificates.crt',  // Debian/Ubuntu
                        '/etc/pki/tls/certs/ca-bundle.crt',    // CentOS/RHEL
                        '/etc/ssl/cert.pem',                   // Alpine / TiDB Cloud 提示路径
                    ];
                    foreach ($systemCas as $cand) {
                        if (file_exists($cand) && filesize($cand) > 100) {
                            error_log("[DB] CA file {$caPath} missing/empty, fallback to {$cand}");
                            $caPath = $cand;
                            $caFound = true;
                            break;
                        }
                    }
                }

                // 3. SSL 决策：
                //    - 找到有效 CA 文件 → 用 SSL（带 CA 校验）
                //    - 没找到 CA 文件 → 强制关闭 SSL（不要走默认协商，
                //      很多 MySQL 服务在客户端没传任何 SSL 选项时会拒绝连接）
                //    这样本地内嵌 MySQL（dokploy 自带 MySQL 服务）能直接连
                if ($caFound && $verify) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                    if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                    }
                } elseif ($caFound) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                } else {
                    error_log("[DB] SSL CA not available, disabling SSL (host={$host})");
                    // 不传任何 PDO::MYSQL_ATTR_SSL_* 选项 → PDO 走明文 TCP
                }
            }

            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // 详细错误日志（便于排查 SSL/网络问题）
            error_log("[DB] connect failed: host={$host} port={$port} db={$db} user={$user} ssl_enabled=" . (defined('DB_SSL_ENABLED') ? 'yes' : 'no'));
            if (defined('DB_SSL_CA')) {
                $caFile = DB_SSL_CA;
                error_log("[DB] SSL CA: {$caFile} (exists=" . (file_exists($caFile) ? 'yes' : 'no') . ", size=" . (file_exists($caFile) ? filesize($caFile) : 0) . ")");
            }
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 执行查询
     */
    public function query($sql, $params = []) {
        try {
            $this->stmt = $this->pdo->prepare($sql);
            $this->stmt->execute($params);
            return $this;
        } catch (PDOException $e) {
            error_log("Database Query Error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . json_encode($params));
            throw new Exception('数据库查询失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取所有结果
     */
    public function fetchAll() {
        return $this->stmt->fetchAll();
    }
    
    /**
     * 获取单条结果
     */
    public function fetch() {
        return $this->stmt->fetch();
    }
    
    /**
     * 获取一列
     */
    public function fetchColumn($column = 0) {
        return $this->stmt->fetchColumn($column);
    }
    
    /**
     * 插入数据
     */
    public function insert($table, $data) {
        // 如果数据中没有 id，检查表是否需要 UUID
        if (!isset($data['id'])) {
            $uuid = $this->needsUuidPrimaryKey($table);
            if ($uuid !== null) {
                $data['id'] = $uuid;
            }
        }
        
        $columns = array_map(fn($col) => "`$col`", array_keys($data));
        $placeholders = array_fill(0, count($data), '?');
        
        $sql = "INSERT INTO $table (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 检查表的主键是否为 VARCHAR(36) UUID 类型，如果是则生成 UUID
     */
    private function needsUuidPrimaryKey($table) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COLUMN_TYPE, COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'id'"
            );
            $stmt->execute([$table]);
            $col = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($col && $col['COLUMN_KEY'] === 'PRI' && strpos($col['COLUMN_TYPE'], 'varchar') !== false) {
                return sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }
        } catch (Exception $e) {
            // 忽略错误，不生成 UUID
        }
        
        return null;
    }
    
    /**
     * 更新数据
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "`$key` = ?";
        }
        
        $sql = "UPDATE $table SET " . implode(',', $set) . " WHERE $where";
        $params = array_merge(array_values($data), $whereParams);
        
        $this->query($sql, $params);
        return $this->stmt->rowCount();
    }
    
    /**
     * 删除数据
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $this->query($sql, $params);
        return $this->stmt->rowCount();
    }
    
    /**
     * 获取行数
     */
    public function rowCount() {
        return $this->stmt->rowCount();
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollBack() {
        return $this->pdo->rollBack();
    }
    
    /**
     * 获取最后插入的ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 检查是否在事务中
     */
    public function inTransaction() {
        return $this->pdo->inTransaction();
    }
}
?>
