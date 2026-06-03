#!/bin/sh
# dokploy 通用 PHP 启动脚本
# 同时启动 php-fpm 和 nginx，前台运行

set -e

echo "[entrypoint] starting at $(date -u +%Y-%m-%dT%H:%M:%SZ)"

# 验证关键文件存在
if [ ! -f /app/index.php ]; then
    echo "[entrypoint] FATAL: /app/index.php not found!" >&2
    echo "[entrypoint] /app contents:" >&2
    ls -la /app 2>&1 | head -20 >&2
    exit 1
fi

if [ ! -f /etc/nginx/conf.d/default.conf ]; then
    echo "[entrypoint] FATAL: nginx config not found!" >&2
    ls -la /etc/nginx/conf.d/ >&2
    exit 1
fi

# PHP 上传/执行配置（如果未挂载自定义 ini）
if [ ! -f /usr/local/etc/php/conf.d/zz-app.ini ]; then
    cat > /usr/local/etc/php/conf.d/zz-app.ini <<EOF
upload_max_filesize = 8M
post_max_size = 8M
max_execution_time = 30
memory_limit = 128M
date.timezone = UTC
expose_php = Off
display_errors = Off
log_errors = On
EOF
fi

# 启动 php-fpm（后台）
mkdir -p /run/php
echo "[entrypoint] starting php-fpm..."
php-fpm -D

# 等 php-fpm 起来（最多 10 秒）
echo "[entrypoint] waiting for php-fpm..."
for i in 1 2 3 4 5 6 7 8 9 10; do
    if php-fpm -t >/dev/null 2>&1; then
        echo "[entrypoint] php-fpm ready (after ${i}s) ✓"
        break
    fi
    sleep 1
done

# 显示实际监听地址
echo "[entrypoint] php-fpm listen config:"
grep -h "^listen" /usr/local/etc/php-fpm.d/*.conf 2>/dev/null | sed 's/^/[entrypoint]   /'
ss -tlnp 2>/dev/null | grep -E ':9000|php-fpm' | sed 's/^/[entrypoint]   /' || true

# 验证 CA 证书（用于 TiDB Cloud SSL 连接）
if [ -f /etc/ssl/cert.pem ]; then
    echo "[entrypoint] /etc/ssl/cert.pem exists ($(stat -c%s /etc/ssl/cert.pem 2>/dev/null) bytes) ✓"
else
    echo "[entrypoint] WARNING: /etc/ssl/cert.pem NOT FOUND" >&2
fi

# 验证 nginx 配置
echo "[entrypoint] testing nginx config..."
if nginx -t 2>&1; then
    echo "[entrypoint] nginx config OK ✓"
else
    echo "[entrypoint] FATAL: nginx config test failed" >&2
    exit 1
fi

# 启动 nginx（前台）
echo "[entrypoint] starting nginx..."
exec nginx -g 'daemon off;'
