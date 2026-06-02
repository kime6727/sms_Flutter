<?php
/**
 * 数据库类
 */

class Database {
    private $pdo;
    private $stmt;
    
    public function __construct($host, $db, $user, $pass, $port = 3306) {
        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
            $this->pdo = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败');
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
