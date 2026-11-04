FROM php:8.3-apache

# 1. Instalar dependencias del sistema, herramientas y Node.js (necesario para Vite)
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    gnupg

# Instalar Node.js LTS para compilar assets de Vite
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# 2. Limpiar caché para reducir el peso de la imagen
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# 3. Instalar extensiones de PHP requeridas por Laravel
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd pdo_pgsql

# Habilitar mod_rewrite de Apache para las rutas y login de Laravel
RUN a2enmod rewrite

# 4. Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Definir directorio de trabajo
WORKDIR /var/www/html

# 6. Copiar el código del proyecto al contenedor
COPY . /var/www/html

# 7. Configurar permisos de almacenamiento y caché
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 8. Apuntar Apache a la carpeta pública
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 9. Configurar Apache para el puerto dinámico de Render
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# 10. Instalar dependencias PHP y compilar los assets de Vite
RUN composer install --no-dev --optimize-autoloader
RUN npm install && npm run build