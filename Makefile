.PHONY: up down build bash install migrate test composer

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

bash:
	docker compose exec php sh

install:
	docker compose exec php composer install

migrate:
	docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

test:
	docker compose exec php bin/phpunit

composer:
	docker compose exec php composer $(args)
