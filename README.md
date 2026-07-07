# Oksana VPN

## Установка

### Composer Install
Для начала нужно установить все пакеты:
```shell
docker compose run --rm app composer install
```

### Настройка .env

Необходимо скопировать `.env.example` и переименовать `.env`, 
либо просто скопировать всё содержимое

### Отключение Basic Auth

При необходимости убрать login и password
```.dotenv
BASIC_AUTH_LOGIN=login
BASIC_AUTH_PASSWORD=password
```

В результате должно получится:
```.dotenv
BASIC_AUTH_LOGIN=
BASIC_AUTH_PASSWORD=
```

### Запуск проекта

```shell
docker compose up -d --build
docker compose exec app php artisan optimize
docker compose exec app php artisan migrate
```

`docker compose` без дополнительных флагов использует dev-окружение по умолчанию.

HTTP теперь обслуживается через FrankenPHP в контейнере `app`, а не через `php artisan serve`.

Для локальных очередей через Redis:
```.dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

### Frontend

Для запуска билда скриптов и стилей:
```shell
docker compose logs -f vite
```

Все стили и скрипты хранятся в `resources/css` и `resources/js` 

### Production

Для production используется отдельный compose-файл:
```shell
docker compose -f docker-compose.prod.yml up -d --build
```

В production Vite не запускается отдельным контейнером: ассеты собираются внутри production image, а Laravel обслуживается FrankenPHP из контейнера `app`.
Если перед приложением стоит отдельный Caddy reverse proxy, контейнер `app` доступен внутри Docker-сети на `app:8000` и не публикует порт на хост.

### Horizon и очереди в production

Для Horizon нужен Redis и отдельный worker-процесс. В production compose уже добавлены сервисы `redis` и `horizon`.

Минимальные переменные в `.env`:
```.dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

Сначала нужно сохранить пакет в проект через обычный `app` контейнер с bind mount:
```shell
docker compose up -d app mysql
docker compose exec app composer require laravel/horizon
```

После этого можно пересобрать production image и запустить Horizon:
```shell
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec app php artisan migrate
docker compose -f docker-compose.prod.yml restart horizon
```

Пример деплоя после checkout:
```shell
docker compose -f docker-compose.prod.yml up -d --build mysql redis app horizon
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --seed --force
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
docker compose -f docker-compose.prod.yml restart horizon
```

Команда `vless-configs:pull` теперь только ставит задачи в очередь, а обработка идёт через queue `vless-configs` в Horizon.
