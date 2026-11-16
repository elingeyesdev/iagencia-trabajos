# REDWORK — imagen de producción para Render (Apache + mod_php, PHP 8.3).
# Construcción: dependencias de producción desde composer.lock y package-lock.json, recursos Vite y
# fuentes de TCPDF. Arranque: docker/start.sh (cachés con las variables reales de Render, sin migraciones).
# Ver docs/despliegue-render.md.
FROM php:8.3-apache

# 1. Paquetes del sistema y Node.js 22 (Vite 8 exige Node ^20.19 o >=22.12; igual que el entorno local)
RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        gnupg \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        unzip \
        zip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Extensiones PHP (pdo_mysql para MySQL; el resto ya viene en la imagen oficial:
#    ctype, curl, dom, fileinfo, filter, hash, iconv, json, openssl, session, tokenizer, xml, zlib)
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# 3. Apache: mod_rewrite (public/.htaccess), document root en public/ y puerto de Render
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV PORT=10000
RUN a2enmod rewrite headers \
    && sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's!^Listen 80$!Listen ${PORT}!' /etc/apache2/ports.conf \
    && sed -ri -e 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!' /etc/apache2/sites-available/000-default.conf \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/redwork.conf \
    && a2enconf redwork \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php=Off\n' > "$PHP_INI_DIR/conf.d/redwork.ini"

# 4. Composer (versión 2 fijada en su rama mayor)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

# 5. Dependencias PHP de producción (capa cacheable: solo depende de composer.json/lock)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts

# 6. Fuentes de TCPDF 7 (tc-lib-pdf-font): no vienen en el paquete y vendor/ no se versiona.
#    Se generan aquí, dentro de la imagen final, y la construcción falla si no aparecen.
#    Después se eliminan solo las fuentes de origen descargadas (temporales).
RUN php vendor/tecnickcom/tc-lib-pdf-font/util/build_fonts.php \
    && test -s vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core/helvetica.json \
    && test -s vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core/helveticab.json \
    && test -s vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core/helveticai.json \
    && rm -rf vendor/tecnickcom/tc-lib-pdf-font/util/vendor

# 7. Recursos Vite (capa cacheable: solo depende de package.json/lock)
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

# 8. Código de la aplicación (.dockerignore excluye .env, vendor, node_modules, pruebas, logs y dumps)
COPY . .

# 9. Autoload optimizado, descubrimiento de paquetes de producción y compilación de Vite.
#    Sin config:cache aquí: las variables de entorno reales solo existen al arrancar en Render.
RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && php artisan package:discover --ansi \
    && npm run build \
    && test -s public/build/manifest.json \
    && rm -rf node_modules \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && chmod +x docker/start.sh

EXPOSE 10000

CMD ["docker/start.sh"]
