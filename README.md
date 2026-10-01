# Oksana VPN

## Setup

### Install Composer Dependencies

```shell
docker compose run --rm app composer install
```

### Configure `.env`

Copy `.env.example` to `.env`, or copy its contents into a new `.env` file.

### Disable Basic Auth

To remove the login and password, change:

```.dotenv
BASIC_AUTH_LOGIN=login
BASIC_AUTH_PASSWORD=password
```

to:

```.dotenv
BASIC_AUTH_LOGIN=
BASIC_AUTH_PASSWORD=
```

### Start Development

```shell
docker compose up -d --build
docker compose exec app php artisan optimize
docker compose exec app php artisan migrate
```

Plain `docker compose` uses the development environment by default.

HTTP is served by FrankenPHP in the `app` container, not by `php artisan serve`.

For local Redis queues:

```.dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

### Frontend

The Vite container builds scripts and styles. Watch it with:

```shell
docker compose logs -f vite
```

Styles and scripts live in `resources/css` and `resources/js`.

## Production

Production uses a separate compose file:

```shell
docker compose -f docker-compose.prod.yml up -d --build
```

In production, Vite is not a separate running container: assets are built into the production image, and Laravel is served by FrankenPHP from `app`.

If a separate Caddy reverse proxy runs in front of the app, the `app` container is available inside the Docker network at `app:8000` and does not publish a host port.

### Horizon And Production Queues

Horizon needs Redis and a separate worker process. Production compose already includes `redis` and `horizon`.

Minimum `.env` values:

```.dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

First, save the package into the project through the regular bind-mounted `app` container:

```shell
docker compose up -d app mysql
docker compose exec app composer require laravel/horizon
```

Then rebuild the production image and start Horizon:

```shell
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec app php artisan migrate
docker compose -f docker-compose.prod.yml restart horizon
```

Deployment example after checkout:

```shell
docker compose -f docker-compose.prod.yml up -d --build mysql redis app horizon
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --seed --force
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
docker compose -f docker-compose.prod.yml restart horizon
```

`vless-configs:pull` only queues work now; processing runs through the `vless-configs` queue in Horizon.
