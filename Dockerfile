# Dokploy 部署 Dockerfile（项目根版本）
#
# dokploy Service 配置建议:
#   Build Type:        Dockerfile
#   Dockerfile Path:   Dockerfile        ← 项目根这个文件
#   Build Context:     .                 ← 项目根
#
# 构建上下文 = 项目根，所以所有路径都要带 backend/ 前缀

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

# 显式覆盖 php-fpm 监听地址（避免不同镜像默认差异）
RUN { \
        echo '[www]'; \
        echo 'user = www-data'; \
        echo 'group = www-data'; \
        echo 'listen = 127.0.0.1:9000'; \
        echo 'listen.owner = www-data'; \
        echo 'listen.group = www-data'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 20'; \
        echo 'pm.start_servers = 4'; \
        echo 'pm.min_spare_servers = 2'; \
        echo 'pm.max_spare_servers = 8'; \
        echo 'catch_workers_output = yes'; \
        echo 'decorate_workers_output = no'; \
        echo 'clear_env = no'; \
    } > /usr/local/etc/php-fpm.d/zz-www.conf

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

# 复制 nginx 配置（路径相对于 Build Context = 项目根）
COPY backend/nginx.conf /etc/nginx/conf.d/default.conf

# 复制启动脚本
COPY backend/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 工作目录
WORKDIR /app

# 复制 backend 全部源码到 /app
COPY backend/. /app/

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
