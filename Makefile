docker_php_tagname := php-cli-xdebug
docker_php_run := docker run -v .:/app -t $(docker_php_tagname)

build-php:
	docker build -t $(docker_php_tagname) -f docker/php/Dockerfile .

composer-install: build-php
	$(docker_php_run) composer install

composer-update: build-php
	$(docker_php_run) composer update

lint: build-php composer-install
	$(docker_php_run) php vendor/bin/ecs

test: build-php composer-install
	$(docker_php_run) php vendor/bin/codecept build
	$(docker_php_run) php vendor/bin/codecept run unit --coverage --coverage-html
