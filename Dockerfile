# syntax=docker/dockerfile:1

FROM php:8.2-apache

# 安装 SQLite 支持
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# 复制项目文件
COPY . /var/www/html

# 运行时需要写入 storage 目录
RUN chown -R www-data:www-data /var/www/html/app/storage

EXPOSE 80

CMD ["apache2-foreground"]
