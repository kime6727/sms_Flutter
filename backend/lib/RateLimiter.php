<?php
/**
 * 频率限制器
 *
 * 基于 DB 表的滑动窗口计数，用于敏感接口的限流：
 *   - 同一 email 在 N 秒内最多尝试 maxAttempts 次
 *   - 同一 IP 在 N 秒内最多尝试 maxAttempts 次
 *
 * 用法：
 *   $ok = RateLimiter::hit('forgot_password', $email, 5, 3600);  // 5次/小时
 *   if (!$ok) { apiError('操作过于频繁，请稍后再试', 429); }
 *
 * 表结构（需手动建表）:
 *   CREATE TABLE rate_limits (
 *     id BIGINT AUTO_INCREMENT PRIMARY KEY,
 *     action VARCHAR(64) NOT NULL,
 *     subject VARCHAR(128) NOT NULL,
 *     created_at DATETIME NOT NULL,
 *     INDEX idx_action_subject_time (action, subject, created_at)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

class RateLimiter {
    /**
     * 记录一次访问，返回是否允许（true=允许，false=已超限）。
     */
    public static function hit($db, $action, $subject, $maxAttempts, $windowSeconds) {
        $subject = self::normalize($subject);
        if ($subject === '') {
            // 兜底：subject 为空直接拒绝，避免绕过限流
            return false;
        }

        $now = time();
        $cutoff = date('Y-m-d H:i:s', $now - $windowSeconds);

        // 清理过期记录（轻量按 action 删，量大时建议走 cron）
        try {
            $db->query(
                "DELETE FROM rate_limits WHERE action = ? AND created_at < ?",
                [$action, $cutoff]
            );
        } catch (Exception $e) {
            error_log("RateLimiter cleanup failed: " . $e->getMessage());
        }

        $count = $db->query(
            "SELECT COUNT(*) FROM rate_limits WHERE action = ? AND subject = ? AND created_at >= ?",
            [$action, $subject, $cutoff]
        )->fetchColumn();

        if (intval($count) >= $maxAttempts) {
            return false;
        }

        $db->insert('rate_limits', [
            'action' => $action,
            'subject' => $subject,
            'created_at' => date('Y-m-d H:i:s', $now)
        ]);

        return true;
    }

    /**
     * 同时按 IP 和 subject 检查，超过任意一个就拒绝
     */
    public static function hitByIpAndSubject($db, $action, $subject, $maxPerSubject, $maxPerIp, $windowSeconds) {
        if (!self::hit($db, $action, 'sub:' . $subject, $maxPerSubject, $windowSeconds)) {
            return false;
        }
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return self::hit($db, $action, 'ip:' . $ip, $maxPerIp, $windowSeconds);
    }

    private static function normalize($s) {
        $s = strtolower(trim((string)$s));
        return substr($s, 0, 128);
    }
}
