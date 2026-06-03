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

# 等 php-fpm 起来
sleep 1

# 验证 php-fpm 监听 9000
if (echo > /dev/tcp/127.0.0.1/9000) 2>/dev/null; then
    echo "[entrypoint] php-fpm listening on 127.0.0.1:9000 ✓"
else
    echo "[entrypoint] WARNING: php-fpm NOT listening on 127.0.0.1:9000" >&2
    echo "[entrypoint] php-fpm config listen line:" >&2
    grep -h "^listen" /usr/local/etc/php-fpm.d/*.conf >&2
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
