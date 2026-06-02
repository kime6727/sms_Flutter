<?php
/**
 * 认证工具类
 *
 * 提供安全的 Token 生成和验证功能
 * 使用 HMAC-SHA256 签名防止篡改
 */

class Auth {
    private static $secretKey = null;
    private static $tokenExpiry = 86400 * 30; // Token 默认有效期 30 天

    /**
     * 获取密钥（从环境变量或配置）
     */
    private static function getSecretKey() {
        if (self::$secretKey === null) {
            // 优先从环境变量读取
            $key = getenv('AUTH_SECRET_KEY');
            if (empty($key)) {
                // 从 .env 文件读取
                $envFile = __DIR__ . '/../.env';
                if (file_exists($envFile)) {
                    $env = parse_ini_file($envFile);
                    $key = $env['AUTH_SECRET_KEY'] ?? '';
                }
            }
            // 如果仍然没有，使用一个基于安装路径的确定性密钥（仅用于开发）
            if (empty($key)) {
                $key = hash('sha256', __DIR__ . 'sms_receiver_default_salt');
            }
            self::$secretKey = $key;
        }
        return self::$secretKey;
    }

    /**
     * 生成安全的 Token
     *
     * @param string $userId 用户ID
     * @param int $expiry 有效期（秒）
     * @return string Token
     */
    public static function generateToken($userId, $expiry = null) {
        $expiry = $expiry ?? self::$tokenExpiry;
        $expiresAt = time() + $expiry;
        $random = bin2hex(random_bytes(16));

        // 构建 payload: userId:expiresAt:random
        $payload = $userId . ':' . $expiresAt . ':' . $random;

        // 生成签名
        $signature = hash_hmac('sha256', $payload, self::getSecretKey());

        // 返回: payload.signature
        return base64_encode($payload . '.' . $signature);
    }

    /**
     * 验证 Token
     *
     * @param string $token Token
     * @return array|false 成功返回 ['user_id' => ..., 'expires_at' => ...]，失败返回 false
     */
    public static function verifyToken($token) {
        if (empty($token)) {
            return false;
        }

        // 解码
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        // 分割 payload 和 signature
        $lastDot = strrpos($decoded, '.');
        if ($lastDot === false) {
            return false;
        }

        $payload = substr($decoded, 0, $lastDot);
        $signature = substr($decoded, $lastDot + 1);

        // 验证签名
        $expectedSignature = hash_hmac('sha256', $payload, self::getSecretKey());
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        // 解析 payload
        $parts = explode(':', $payload);
        if (count($parts) !== 3) {
            return false;
        }

        $userId = $parts[0];
        $expiresAt = intval($parts[1]);

        // 检查是否过期
        if (time() > $expiresAt) {
            return false;
        }

        return [
            'user_id' => $userId,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * 从请求头获取 Token
     *
     * @return string|null
     */
    public static function getBearerToken() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    /**
     * 验证请求中的 Token 并返回用户ID
     *
     * @return string|false 成功返回 user_id，失败返回 false
     */
    public static function authenticate() {
        $token = self::getBearerToken();
        if (!$token) {
            return false;
        }

        $result = self::verifyToken($token);
        if ($result === false) {
            return false;
        }

        return $result['user_id'];
    }

    /**
     * 设置 Token 有效期
     *
     * @param int $seconds
     */
    public static function setTokenExpiry($seconds) {
        self::$tokenExpiry = $seconds;
    }
}
