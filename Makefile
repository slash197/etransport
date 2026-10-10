export UID := $(shell id -u)
export GID := $(shell id -g)

RUN := docker compose run --rm php

.PHONY: build install validate test stan check run shell

build:
	docker compose build

install:
	$(RUN) composer install

validate:
	$(RUN) composer validate --strict

test:
	$(RUN) vendor/bin/phpunit

stan:
	$(RUN) vendor/bin/phpstan analyse --memory-limit=1G

check: validate stan test

# Runs a PHP script in the container: make run f=path/to/script.php
run:
	@test -n "$(f)" || { echo "Usage: make run f=path/to/script.php"; exit 2; }
	$(RUN) php $(f)

shell:
	$(RUN) sh
