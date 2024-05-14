composer-install:
	docker run -v .:/app -t composer:2.7.6 composer --ignore-platform-req=ext-imap install
composer-update:
	docker run -v .:/app -t composer:2.7.6 composer --ignore-platform-req=ext-imap update
lint:
	docker run -v .:/app -w /app -t php:8.3-cli php vendor/bin/ecs
test:
	docker run -v .:/app -w /app -t php:8.3-cli php vendor/bin/codecept build
	docker run -v .:/app -w /app -t php:8.3-cli php vendor/bin/codecept run unit
