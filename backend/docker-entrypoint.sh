#!/bin/sh
# dokploy 通用 PHP 启动脚本
# 同时启动 php-fpm 和 nginx，前台运行

set -e

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

# 如果 backend 目录存在且不为空，把 /app 指向它
if [ -d /var/www/backend ] && [ "$(ls -A /var/www/backend 2>/dev/null)" ]; then
    # 软链接 /app -> /var/www/backend，方便 nginx 走固定路径
    if [ ! -e /app ]; then
        ln -s /var/www/backend /app
    fi
fi

# 启动 php-fpm 后台
mkdir -p /run/php
php-fpm -D

# 启动 nginx 前台
nginx -g 'daemon off;'
