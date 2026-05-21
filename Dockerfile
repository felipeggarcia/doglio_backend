# Usa a versão oficial do PHP 8.3 pronta para linha de comando
FROM php:8.3-cli

# Instala os pacotes básicos do Linux que o Laravel precisa
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    default-mysql-client

# Instala as extensões do PHP para conectar no MySQL
RUN docker-php-ext-install pdo_mysql mbstring zip bcmath

# Puxa o Composer oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Cria a pasta onde o projeto vai ficar lá no Render
WORKDIR /app

# Copia todos os seus arquivos do computador para dentro do servidor
COPY . .

# Instala as dependências do Laravel (ignorando as de teste/dev para ficar mais leve)
RUN composer install --optimize-autoloader

# Dá permissão total para as pastas que o Laravel precisa modificar
RUN chmod -R 777 storage bootstrap/cache

# Avisa o Render que a aplicação vai rodar na porta 8000
EXPOSE 8000

# O Toque de Mestre: Ao ligar o servidor, ele roda as migrações no Aiven e inicia o Laravel!
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000