FROM php:8.3-apache

# 1. Instalar dependencias del sistema y herramientas necesarias
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev

# 2. Limpiar caché para reducir el peso de la imagen
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 3. Instalar extensiones de PHP requeridas por Laravel
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd pdo_pgsql

# 4. Instalar Composer (el gestor de paquetes de PHP)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Definir el directorio de trabajo dentro del contenedor
WORKDIR /var/www/html

# 6. Copiar todo el código de tu proyecto al contenedor
COPY . /var/www/html

# 7. Configurar permisos correctos para las carpetas de Laravel (storage y caché)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 8. Apuntar el servidor Apache a la carpeta pública de Laravel (/public)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 9. Configurar Apache para que escuche en el puerto dinámico que asigna Render ($PORT)
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# 10. Instalar las dependencias de producción de Composer
RUN composer install --no-dev --optimize-autoloader