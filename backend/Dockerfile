# Dokploy 部署 Dockerfile (PHP 8.2 + Apache)
# 构建:  docker build -t sms-backend .
# 运行:  docker run -p 80:80 --env-file .env sms-backend

FROM php:8.2-apache

# 系统依赖
RUN apt-get update && apt-get install -y \
        libzip-dev \
        unzip \
        git \
        curl \
    && rm -rf /var/lib/apt/lists/*

# PHP 扩展：项目用到了 PDO/MySQL/curl/bcmath 等
RUN docker-php-ext-install pdo pdo_mysql mysqli bcmath

# 启用 Apache mod_rewrite（项目 .htaccess 需要）
RUN a2enmod rewrite headers

# 允许 .htaccess 覆盖（默认生产配置是 AllowOverride None）
RUN { \
        echo '<Directory /var/www/html>'; \
        echo '  Options Indexes FollowSymLinks'; \
        echo '  AllowOverride All'; \
        echo '  Require all granted'; \
        echo '  AddDefaultCharset utf-8'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/app-overrides.conf \
    && a2enconf app-overrides

# DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

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

# 工作目录
WORKDIR /var/www/html

# 先复制全部源码
COPY . /var/www/html/

# 删掉备份/示例文件（防止 .bak 暴露）
RUN rm -f /var/www/html/index.php.bak \
    && rm -rf /var/www/html/tests /var/www/html/scripts /var/www/html/.git \
    && find /var/www/html -name "*.bak" -delete \
    && find /var/www/html -name "*.old" -delete

# 权限：Apache 用户
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/log/sms-receiver \
    && chown -R www-data:www-data /var/log/sms-receiver

# 健康检查（用项目自带的 /api/health）
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -fsS http://localhost/api/health || exit 1

EXPOSE 80

CMD ["apache2-foreground"]
