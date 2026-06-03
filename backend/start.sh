#!/bin/bash
# 自定义启动脚本
# - 启动 php-fpm 在后台
# - 用我们自己的 nginx 配置（包含 try_files 路由到 index.php）
# - 所有日志都写到 /var/log/

set -e

echo "[start.sh] starting php-fpm..."
php-fpm -y /assets/php-fpm.conf > /var/log/php-fpm.log 2>&1 &
PHP_FPM_PID=$!
echo "[start.sh] php-fpm PID: $PHP_FPM_PID"

# 等 php-fpm 完全启动
sleep 2

echo "[start.sh] checking php-fpm is listening..."
if ss -tlnp 2>/dev/null | grep -q ':9000'; then
    echo "[start.sh] php-fpm is listening on :9000 ✓"
else
    echo "[start.sh] WARNING: php-fpm may not be listening on :9000"
    ss -tlnp 2>/dev/null || netstat -tlnp 2>/dev/null || echo "[start.sh] no ss/netstat available"
fi

echo "[start.sh] verifying nginx config..."
nginx -t -c /app/nginx.nixpacks.conf 2>&1

echo "[start.sh] starting nginx (foreground)..."
exec nginx -c /app/nginx.nixpacks.conf -g 'daemon off;'
