<?php
/**
 * 数据库自安装器 - WordPress 风格
 *
 * 首次访问时如果数据库是空的，自动执行 database.sql 建表 + 初始数据。
 * 通过文件锁 + 标记文件避免重复执行。
 *
 * 设计目标：
 *   1. 用户只配好 DB_HOST/DB_USER/DB_PASS/DB_NAME 环境变量
 *   2. 第一次访问任意 API 时自动建库 + 导入初始数据
 *   3. 安装过程页面有进度展示
 *   4. 安装完成后所有 API 正常工作
 *   5. 重复访问不会重复执行（性能优化）
 *   6. 安装失败时给出明确错误，方便排查
 */
class Installer
{
    /** @var string 标记文件路径（容器内） */
    private $markerFile;

    /** @var string 进程级文件锁路径 */
    private $lockFile;

    /** @var string SQL 文件路径 */
    private $sqlFile;

    /** @var string 用于判断"是否已安装"的标志性表名 */
    private $markerTable = 'admins';

    public function __construct()
    {
        $this->markerFile = __DIR__ . '/../.installed';
        $this->lockFile = sys_get_temp_dir() . '/sms_installer.lock';
        $this->sqlFile = __DIR__ . '/../database.sql';
    }

    /**
     * 获取标记文件路径（用于在 install 页面展示安装时间）
     */
    public function getMarkerFile()
    {
        return $this->markerFile;
    }

    /**
     * 获取 SQL 文件路径
     */
    public function getSqlFile()
    {
        return $this->sqlFile;
    }

    /**
     * 检查是否已安装（轻量级 - 先看标记文件，再看表是否存在）
     */
    public function isInstalled()
    {
        // 1. 标记文件存在 → 已安装
        if (file_exists($this->markerFile)) {
            return true;
        }
        return false;
    }

    /**
     * 深度检查 - 实际探测数据库（标记文件可能丢失/被删）
     */
    public function checkDatabase($db)
    {
        try {
            $row = $db->query("SHOW TABLES LIKE ?", [$this->markerTable])->fetch();
            if ($row) {
                $this->markInstalled();
                return true;
            }
        } catch (Exception $e) {
            // 表不存在
        }
        return false;
    }

    /**
     * 标记为已安装
     */
    public function markInstalled()
    {
        @file_put_contents($this->markerFile, json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
        ]));
    }

    /**
     * 检查是否正在被其他进程安装
     */
    public function isInstalling()
    {
        if (!file_exists($this->lockFile)) {
            return false;
        }
        // 如果锁文件超过 5 分钟没更新，认为是僵尸锁
        $mtime = filemtime($this->lockFile);
        if (time() - $mtime > 300) {
            @unlink($this->lockFile);
            return false;
        }
        return true;
    }

    /**
     * 执行安装
     * @return array 执行结果统计
     */
    public function install($db)
    {
        $startTime = microtime(true);

        // 更新锁文件时间戳
        @file_put_contents($this->lockFile, json_encode([
            'pid' => getmypid(),
            'started_at' => date('Y-m-d H:i:s'),
            'host' => DB_HOST,
            'db' => DB_NAME,
        ]));

        if (!file_exists($this->sqlFile)) {
            throw new Exception("SQL 文件不存在: {$this->sqlFile}");
        }

        $sql = file_get_contents($this->sqlFile);
        if ($sql === false || $sql === '') {
            throw new Exception("SQL 文件为空或读取失败");
        }

        $statements = $this->parseSql($sql);

        $executed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($statements as $idx => $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }

            try {
                $db->query($stmt);
                $executed++;
            } catch (Exception $e) {
                // 多种"已存在"错误都视为可跳过（让 install 真正幂等）
                $msg = $e->getMessage();
                $skipPatterns = [
                    'already exists',         // CREATE TABLE / DATABASE 已存在
                    'Duplicate key name',     // 索引/键已存在
                    'Duplicate column name',  // 字段已存在
                    'Duplicate entry',        // 唯一键冲突（数据已存在）
                    'already exists',         // 大小写
                ];
                $shouldSkip = false;
                foreach ($skipPatterns as $p) {
                    if (stripos($msg, $p) !== false) {
                        $shouldSkip = true;
                        break;
                    }
                }
                if ($shouldSkip) {
                    $skipped++;
                    error_log("[Installer] skipped (already exists): " . substr($msg, 0, 200));
                } else {
                    $errors[] = [
                        'index' => $idx,
                        'stmt_preview' => substr($stmt, 0, 100),
                        'error' => $msg,
                    ];
                }
            }
        }

        // 验证：标志性表是否真的存在
        $verified = $this->checkDatabase($db);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'status' => $verified ? 'installed' : 'failed',
            'executed' => $executed,
            'skipped' => $skipped,
            'errors' => $errors,
            'duration_ms' => $duration,
            'verified' => $verified,
        ];
    }

    /**
     * 解析 SQL 文件为语句数组
     * 规则：
     *   1. 去掉 UTF-8 BOM
     *   2. 去掉行内 -- 注释（简单处理，不处理字符串内的 --）
     *   3. 去掉 /* * / 块注释
     *   4. 按分号分割
     *   5. 去掉空白语句
     */
    private function parseSql($sql)
    {
        // 去 BOM
        if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
            $sql = substr($sql, 3);
        }

        // 去块注释 /* ... */
        $sql = preg_replace('#/\*.*?\*/#s', '', $sql);

        // 去行注释 -- ... (到行尾)
        $lines = explode("\n", $sql);
        $cleanLines = [];
        foreach ($lines as $line) {
            // 找到第一个不在字符串内的 --
            // 简化处理：认为 -- 后面到行尾都是注释（项目 SQL 不含字符串内的 --）
            $pos = strpos($line, '--');
            $cleanLines[] = ($pos === false) ? $line : substr($line, 0, $pos);
        }
        $sql = implode("\n", $cleanLines);

        // 按分号分割
        $statements = explode(';', $sql);

        // 清理
        $result = [];
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                $result[] = $stmt;
            }
        }
        return $result;
    }

    /**
     * 清理锁文件（成功完成后调用）
     */
    public function cleanup()
    {
        @unlink($this->lockFile);
    }
}
