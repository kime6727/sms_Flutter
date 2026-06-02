<?php
/**
 * 简单日志类
 *
 * 提供基本的日志记录功能。
 * 日志文件路径可通过常量 LOG_DIR 覆盖；写文件失败时会通过 error_log 上报。
 */

class Logger {
    private static $logFile;
    private static $errorLogFile;
    private static $initialized = false;

    private static function init() {
        if (self::$initialized) {
            return;
        }
        $dir = defined('LOG_DIR') ? LOG_DIR : sys_get_temp_dir() . '/sms-receiver';
        self::$logFile = $dir . '/api.log';
        self::$errorLogFile = $dir . '/error.log';
        self::$initialized = true;
    }

    /**
     * 记录API请求
     */
    public static function logRequest($method, $path, $userId, $duration, $statusCode) {
        self::init();
        $timestamp = date('Y-m-d H:i:s');
        $userStr = $userId ? "User:$userId" : "Guest";
        $message = "[$timestamp] $method $path - $userStr - {$duration}ms - HTTP:$statusCode\n";
        self::write($message, self::$logFile);
    }

    /**
     * 记录错误
     */
    public static function logError($message, $context = []) {
        self::init();
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' - ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[$timestamp] ERROR: $message$contextStr\n";
        self::write($logMessage, self::$errorLogFile);

        // 同时写入主日志
        self::write($logMessage, self::$logFile);
    }

    /**
     * 记录信息
     */
    public static function logInfo($message) {
        self::init();
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] INFO: $message\n";
        self::write($logMessage, self::$logFile);
    }

    /**
     * 记录警告
     */
    public static function logWarning($message) {
        self::init();
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] WARNING: $message\n";
        self::write($logMessage, self::$logFile);
    }

    /**
     * 记录调试信息（仅开发环境）
     */
    public static function logDebug($message) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            self::init();
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] DEBUG: $message\n";
            self::write($logMessage, self::$logFile);
        }
    }

    /**
     * 写入日志文件
     */
    private static function write($message, $file) {
        $logDir = dirname($file);
        if (!is_dir($logDir)) {
            if (!@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                error_log("[Logger] 无法创建日志目录: $logDir");
                return false;
            }
        }
        $bytes = @file_put_contents($file, $message, FILE_APPEND);
        if ($bytes === false) {
            $err = error_get_last();
            error_log("[Logger] 写日志失败 ($file): " . ($err['message'] ?? 'unknown'));
            return false;
        }
        return true;
    }

    /**
     * 设置日志文件路径
     */
    public static function setLogFile($path) {
        self::$initialized = true;
        self::$logFile = $path;
    }

    /**
     * 设置错误日志文件路径
     */
    public static function setErrorLogFile($path) {
        self::$initialized = true;
        self::$errorLogFile = $path;
    }
}
