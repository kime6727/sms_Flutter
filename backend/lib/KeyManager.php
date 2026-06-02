<?php
/**
 * 密钥管理工具类
 * 
 * 安全说明：
 * 1. hero-sms API 密钥等核心密钥必须存储在数据库 system_settings 表中
 * 2. 禁止在代码中明文硬编码任何密钥
 * 3. 禁止在 .env 文件中存储 hero-sms API 密钥
 * 4. 密钥通过本类从数据库安全读取
 */

require_once __DIR__ . '/Database.php';

class KeyManager {
    private static $instance = null;
    private static $cache = [];
    private static $cacheTtl = 300; // 5分钟缓存
    
    /**
     * 从数据库获取密钥
     * @param string $key 密钥名称
     * @return string|null
     */
    public static function get($key) {
        // 检查缓存
        if (isset(self::$cache[$key]) && self::$cache[$key]['expires'] > time()) {
            return self::$cache[$key]['value'];
        }
        
        try {
            $db = new Database(DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT);
            $result = $db->query(
                "SELECT value FROM system_settings WHERE `key` = ?",
                [$key]
            )->fetch();
            
            if ($result) {
                $value = $result['value'];
                // 缓存结果
                self::$cache[$key] = [
                    'value' => $value,
                    'expires' => time() + self::$cacheTtl
                ];
                return $value;
            }
        } catch (Exception $e) {
            error_log("KeyManager: Failed to get key '{$key}': " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * 获取 hero-sms API 密钥
     * @return string|null
     */
    public static function getHeroSmsApiKey() {
        return self::get('hero_sms_api_key');
    }
    
    /**
     * 清除缓存
     * @param string|null $key 指定清除某个密钥，null 则清除全部
     */
    public static function clearCache($key = null) {
        if ($key) {
            unset(self::$cache[$key]);
        } else {
            self::$cache = [];
        }
    }
}
