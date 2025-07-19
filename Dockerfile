# Gunakan image PHP + Apache
FROM php:8.1-apache

# Install ekstensi mysqli agar bisa konek ke MySQL
RUN docker-php-ext-install mysqli

# Salin semua file ke folder HTML Apache
COPY . /var/www/html/

# Buka port 80
EXPOSE 80
