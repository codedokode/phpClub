.RECIPEPREFIX +=

test:
  vendor/bin/phpunit

server:
  php -S 127.0.0.1:9001 -t public dev-server.php

diff:
  vendor/bin/doctrine-migrations migrations:diff

migrate:
  vendor/bin/doctrine-migrations migrations:migrate --no-interaction

migrate-test:
  APP_ENV=test vendor/bin/doctrine-migrations migrations:migrate --no-interaction

deploy:
  bin/deploy.sh