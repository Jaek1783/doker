# Docker & Payment API Server
# PHP 8.2 + Apache 기반 이미지

FROM php:8.2-apache

LABEL maintainer="API Server"
LABEL description="Docker Management & PortOne Payment API Server"

# 시스템 패키지 설치
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libssl-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# PHP 확장 설치
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    curl \
    zip

# Apache mod_rewrite 활성화
RUN a2enmod rewrite headers

# Apache 설정 - AllowOverride 활성화
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# PHP 설정
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# PHP 커스텀 설정
RUN echo "display_errors = Off" >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo "log_errors = On" >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo "error_log = /var/log/php_errors.log" >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo "memory_limit = 256M" >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo "max_execution_time = 60" >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo "upload_max_filesize = 10M" >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo "post_max_size = 10M" >> "$PHP_INI_DIR/conf.d/custom.ini" \
    && echo "date.timezone = Asia/Seoul" >> "$PHP_INI_DIR/conf.d/custom.ini"

# 작업 디렉토리 설정
WORKDIR /var/www/html

# 애플리케이션 코드 복사
COPY . /var/www/html/

# 권한 설정
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 로그 디렉토리 생성
RUN mkdir -p /var/log/api \
    && chown www-data:www-data /var/log/api

# 포트 노출
EXPOSE 80

# 헬스체크
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/api/v1/ || exit 1

# 시작 명령
CMD ["apache2-foreground"]
