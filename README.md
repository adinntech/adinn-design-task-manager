# PHP 8.5.9 + Apache + MariaDB 10.4.32 local Docker setup

This package is designed for Windows + Docker Desktop and avoids conflicts with an existing XAMPP installation.

## Ports

- PHP/Apache app: http://localhost:8080
- phpMyAdmin: http://localhost:8081
- MariaDB from Windows host: 127.0.0.1:3307
- MariaDB from PHP container: db:3306

## Default database credentials

- Database: my_project
- Application user: appuser
- Application password: apppassword
- Root password: root

Change these values in docker-compose.yml before using the setup for anything other than local development.

## Plain PHP

Keep:

APACHE_DOCUMENT_ROOT: /var/www/html

## Laravel / CodeIgniter 4

Change docker-compose.yml to:

APACHE_DOCUMENT_ROOT: /var/www/html/public

Then rebuild:

docker compose down
docker compose up -d --build

## Start

docker compose up -d --build

## Verify

docker compose ps
docker compose exec app php -v
docker compose exec app php -m
docker compose exec db mariadb -uroot -proot -e "SELECT VERSION();"

## Database connection from PHP

Use:

DB_HOST=db
DB_PORT=3306
DB_DATABASE=my_project
DB_USERNAME=appuser
DB_PASSWORD=apppassword

Do not use 127.0.0.1 or localhost for DB_HOST from inside the PHP container.

## Import an existing SQL dump

Copy it:

docker cp project.sql php85_db:/tmp/project.sql

Import it:

docker compose exec db sh -c "mariadb -uappuser -papppassword my_project < /tmp/project.sql"

## Composer

If composer.json exists:

docker compose exec app composer install

## Stop

docker compose down

## Stop and delete database data

WARNING: this permanently deletes the Docker MariaDB volume:

docker compose down -v
