# Oksana VPN

## Установка

### Composer Install
Для начала нужно установить все пакеты:
```shell
docker run -v ${PWD}:/dir -w /dir composer composer install --ignore-platform-reqs
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
./vendor/bin/sail up -d
./vendor/bin/sail php artisan optimize 
./vendor/bin/sail php artisan migrate 
```

### Frontend

Для запуска билда скриптов и стилей:
```shell
./vendor/bin/sail npm run dev
```

Все стили и скрипты хранятся в `resources/css` и `resources/js` 
