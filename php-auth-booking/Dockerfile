# Usar PHP 8.3 CLI (más ligero para microservicios)
FROM php:8.3-cli

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring xml zip bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos de la aplicación
COPY . .

# Instalar dependencias de PHP (regenerar composer.lock si es necesario)
RUN composer install --optimize-autoloader --no-interaction || composer update --no-dev --optimize-autoloader --no-interaction

# Crear usuario para Laravel
RUN useradd -G www-data,root -u 1000 -d /home/laravel laravel
RUN mkdir -p /home/laravel/.composer && \
    chown -R laravel:laravel /home/laravel

# Cambiar permisos
RUN chown -R laravel:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Cambiar al usuario laravel
USER laravel

# Exponer puerto 8000 (servidor de desarrollo de Laravel)
EXPOSE 8000

# Comando de inicio usando el servidor de desarrollo de Laravel
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]