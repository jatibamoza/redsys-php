# Imagen oficial de PHP con Apache
FROM php:8.2-apache

# Copiar los archivos del proyecto al contenedor
COPY . /var/www/html/

# Establecer el directorio de trabajo
WORKDIR /var/www/html/

# Habilitar extensiones básicas si son necesarias
RUN docker-php-ext-install pdo pdo_mysql

# Exponer el puerto 8080 (Render usa 10000 internamente, pero mapea a 8080 en contenedor)
EXPOSE 8080

# Arrancar Apache
CMD ["apache2-foreground"]
