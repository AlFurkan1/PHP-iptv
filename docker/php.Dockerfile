FROM php:8.2-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ffmpeg \
    python3 \
    python3-pip \
    mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# Install yt-dlp
RUN pip3 install --break-system-packages yt-dlp

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Copy application (excluding docker/ and Dockerfile)
COPY . /var/www/iptv

# Create yt-dlp symlink for bin/ path
RUN ln -s /usr/local/bin/yt-dlp /var/www/iptv/bin/yt-dlp

# Create cache directory and set permissions
RUN mkdir -p /var/www/iptv/cache \
    && chown -R www-data:www-data /var/www/iptv/cache \
    && chmod -R 755 /var/www/iptv/cache

WORKDIR /var/www/iptv
