# Dokploy 部署 Dockerfile (nginx + php-fpm)
# 适配 dokploy 默认 PHP-FPM 模板（document root = /app/）
# 构建:  docker build -t sms-backend .
# 运行:  docker run -p 80:80 \
#           -e DB_HOST=... -e DB_PASS=... -e DB_NAME=... \
#           -e DB_SSL_ENABLED=1 -e DB_SSL_CA=/etc/ssl/cert.pem \
#           sms-backend

FROM php:8.2-fpm-bookworm

# 系统依赖
RUN apt-get update && apt-get install -y \
        nginx \
        libzip-dev \
        unzip \
        git \
        curl \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# PHP 扩展：项目用到了 PDO/MySQL/curl/bcmath 等
RUN docker-php-ext-install pdo pdo_mysql mysqli bcmath

# PHP 上传/执行配置
RUN { \
        echo 'upload_max_filesize = 8M'; \
        echo 'post_max_size = 8M'; \
        echo 'max_execution_time = 30'; \
        echo 'memory_limit = 128M'; \
        echo 'date.timezone = UTC'; \
        echo 'expose_php = Off'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

# 清理默认 nginx 配置，启用我们的
RUN rm -f /etc/nginx/sites-enabled/default

# 把 nginx.conf 复制成默认 server block
COPY nginx.conf /etc/nginx/conf.d/default.conf

# 复制启动脚本
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 工作目录
WORKDIR /app

# 先复制全部源码到 /app
COPY . /app/

# 删掉备份/示例文件
RUN rm -f /app/index.php.bak \
    && rm -rf /app/tests /app/scripts /app/cron /app/.git \
    && find /app -name "*.bak" -delete \
    && find /app -name "*.old" -delete

# 权限：nginx + php-fpm 用户
RUN chown -R www-data:www-data /app \
    && chmod -R 755 /app \
    && mkdir -p /var/log/sms-receiver \
    && chown -R www-data:www-data /var/log/sms-receiver

# 健康检查（用项目自带的 /health）
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -fsS http://localhost/health || exit 1

EXPOSE 80

# 入口脚本：启动 php-fpm + nginx
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]
