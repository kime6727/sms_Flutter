<?php
/**
 * 加密解密类 - 用于敏感数据加密
 */

class Encryption {
    private $key;
    private $cipher = 'AES-256-CBC';
    
    public function __construct($key = null) {
        // 使用环境变量或配置文件中的密钥
        $this->key = $key ?: (defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'default_encryption_key_change_me');
    }
    
    /**
     * 加密数据
     */
    public function encrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // 将IV和加密数据组合，使用base64编码
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * 解密数据
     */
    public function decrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        return openssl_decrypt(
            $encrypted,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }
    
    /**
     * 加密敏感字段（如验证码）
     */
    public function encryptSensitive($text) {
        return $this->encrypt($text);
    }
    
    /**
     * 解密敏感字段
     */
    public function decryptSensitive($encrypted) {
        return $this->decrypt($encrypted);
    }
}
?>
