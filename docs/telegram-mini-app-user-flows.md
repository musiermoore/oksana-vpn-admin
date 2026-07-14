# Telegram Mini-App User Flows

Актуально по коду на `2026-07-14`.

Документ описывает фактически реализованные пользовательские пути в mini-app, а не только целевую state machine. Это удобно использовать как базу сценариев для AI-support бота.

## 1. Что есть в mini-app сейчас

Реализованные страницы:

- `/telegram-app/` -> `Home`
- `/telegram-app/wireguard` -> `WireGuard`
- `/telegram-app/vless` -> `VLESS`
- `/telegram-app/vless-wl` -> `VLESS White List`
- `/telegram-app/payments` -> `Payments`
- `/telegram-app/help` -> `Help`
- `/telegram-app/chats` -> `Chats`
- `/telegram-app/support` -> `Support`
- `/telegram-app/support/{ticketId}` -> `SupportShow`

Общие особенности:

- Почти каждая страница начинает с авто-авторизации через `POST /telegram-app/auth/telegram`.
- Затем страница загружает профиль через `GET /telegram-app/me`.
- На всех страницах есть нижняя навигация: `Главная`, `WG`, `VLESS`, `Подписка`, `Помощь`, `Чаты`.
- Экран `Support` не вынесен в нижнюю навигацию, но доступен из `Help` и по прямой ссылке.

## 2. Специальные входы в mini-app

### 2.1 Обычный вход

Путь:

1. Пользователь открывает mini-app.
2. Выполняется `POST /telegram-app/auth/telegram`.
3. Выполняется `GET /telegram-app/me`.
4. Пользователь попадает на `Home`.

### 2.2 Вход по тикету поддержки

Если в Telegram передан `start_param=ticket_{id}`, frontend делает редирект:

1. Пользователь открывает mini-app по deep link.
2. Идёт авторизация.
3. Срабатывает `redirectFromTelegramStartParam(...)`.
4. Пользователь сразу попадает на `/telegram-app/support/{ticketId}`.

Это отдельный важный путь для support-бота: пользователь может попасть не на главную, а сразу в чат обращения.

### 2.3 Вход по реферальной ссылке

Если передан `start_param=ref_{id}`:

1. Выполняется обычная авторизация.
2. Сессия принудительно обновляется с учётом реферального параметра.
3. Пользователь остаётся в обычном flow и попадает на `Home`.
4. На `Home` он может вручную привязать реферера, если это ещё разрешено.

Здесь нет автоматического перехода на отдельный экран.

## 3. Карта экранов и переходов

```text
BOOTSTRAP
  -> HOME
  -> SUPPORT_SHOW (если start_param=ticket_{id})

HOME
  -> WIREGUARD
  -> VLESS
  -> VLESS_WL (если доступно)
  -> PAYMENTS
  -> HELP
  -> CHATS (через нижнюю навигацию)

WIREGUARD
  list -> actions -> qr
                  -> file-sent
  actions -> VLESS
  debt -> PAYMENTS
  empty -> HOME

VLESS
  menu -> links
  menu -> qr
  menu -> VLESS_WL (если доступно)
  debt -> PAYMENTS

VLESS_WL
  menu -> links
  debt -> PAYMENTS

PAYMENTS
  overview -> packages(personal)
  overview -> packages(gift)
  overview -> code-activated
  packages -> activated
  packages -> gift-created
  packages -> payment-link
  packages -> overview

HELP
  menu -> WG help
  menu -> VLESS help
  menu -> Clients
  menu -> SUPPORT
  wg -> WG clients
  vless -> VLESS clients
  clients -> WG clients
  clients -> VLESS clients

CHATS
  -> external Telegram links

SUPPORT
  empty -> create ticket -> SUPPORT_SHOW
  list -> SUPPORT_SHOW

SUPPORT_SHOW
  -> SUPPORT
  -> send message
```

## 4. Детальные user-flow по страницам

## 4.1 Home

Файл: [Home.vue](/Users/alexandersustavov/projects/home/wireguard-vpn-app/resources/js/Pages/TelegramApp/Home.vue)

Состояния:

- `loading`
- `error`
- `ready`

Доступные действия на `ready`:

- `WireGuard` -> переход на `/telegram-app/wireguard`
- `VLESS` -> переход на `/telegram-app/vless`
- `VLESS Белые списки` -> переход на `/telegram-app/vless-wl`
  - показывается только если `user.has_vless_wl_configs === true`
- `Подписка` -> переход на `/telegram-app/payments`
- `Помощь` -> переход на `/telegram-app/help`

Реферальные действия на `Home`:

- `Скопировать ссылку`
- `Поделиться`
- `Привязать` реферера по коду или ссылке

Support-боту полезно понимать:

- `Home` не только меню, но и экран статуса аккаунта.
- Здесь пользователь видит:
  - активна ли подписка
  - срок подписки
  - баланс
  - реферальную скидку
- Ошибки на этом экране почти всегда означают проблему авторизации mini-app или загрузки профиля.

## 4.2 WireGuard

Файл: [WireGuard.vue](/Users/alexandersustavov/projects/home/wireguard-vpn-app/resources/js/Pages/TelegramApp/WireGuard.vue)

Состояния страницы:

- `loading`
- `error`
- `debt`
- `empty`
- `ready`

Внутренние шаги при `ready`:

- `list`
- `actions`
- `qr`
- `file`

### Основной путь

1. Открыть `WireGuard` с `Home` или через нижнюю навигацию.
2. `GET /telegram-app/wireguard/configs`.
3. Если конфиги есть -> показывается список конфигов.
4. Пользователь выбирает конфиг.
5. Попадает на экран действий по конфигу.

### Ветка `debt`

1. Пользователь открывает `WireGuard`.
2. API возвращает `403` с `type=debt`.
3. Показывается экран `Доступ к конфигам закрыт`.
4. Доступны кнопки:
   - `Подписка`
   - `К началу`

### Ветка `empty`

1. Пользователь открывает `WireGuard`.
2. API возвращает пустой список конфигов.
3. Показывается `Конфиги не найдены`.
4. Доступна кнопка `К началу`.

### Ветка `actions`

После выбора конфига доступны:

- `QR Code`
  - запрашивает `GET /telegram-app/wireguard/configs/{configId}/qr-code`
  - открывает шаг `qr`
- `Отправить файл в бота`
  - вызывает `POST /telegram-app/wireguard/configs/{configId}/send-file`
  - открывает шаг `file`
- `VLESS`
  - переводит на `/telegram-app/vless`
- `Конфиги`
  - возврат к списку

### Ветка `qr`

Доступны действия:

- `Отправить в бота`
  - `POST /telegram-app/wireguard/configs/{configId}/send-qr`
- `Конфиги`
  - возврат к списку
- `К началу`

### Ветка `file`

Пользователь видит подтверждение, что файл уже отправлен в Telegram-бота.

Доступны действия:

- `Отправить ещё раз в бота`
- `Конфиги`
- `К началу`

Support-боту важно:

- В mini-app нет локальной кнопки "скачать файл в браузер", хотя backend-роут существует.
- Базовый пользовательский сценарий для WireGuard в UI сейчас такой:
  - выбрать конфиг
  - показать QR
  - или отправить файл/QR в Telegram-бота

## 4.3 VLESS

Файл: [Vless.vue](/Users/alexandersustavov/projects/home/wireguard-vpn-app/resources/js/Pages/TelegramApp/Vless.vue)

Состояния страницы:

- `loading`
- `error`
- `debt`
- `ready`

Внутренние шаги:

- `menu`
- `links`
- `qr`

### Основной путь

1. Открыть `VLESS`.
2. Выполняется `GET /telegram-app/vless/link`.
3. Если доступ есть, показывается экран `menu`.

### Ветка `menu`

Доступны действия:

- `Link` -> шаг `links`
- `QR-Code` -> шаг `qr`
- `Белые списки` -> `/telegram-app/vless-wl`
  - только если `has_vless_wl_configs === true`
- `К началу`

### Ветка `links`

Пользователь видит:

- приоритетные deep links:
  - `Happ`
  - `V2RayTun`
  - `Incy`
- raw-ссылку
- дополнительные клиенты:
  - `V2RayN`
  - `V2RayNG`
  - `V2Ray Box`
  - `Sing-box`
  - `Hiddify`

Действия:

- `Открыть` deep link
- `Скопировать raw-ссылку`
- `Открыть` дополнительный клиент
- `Назад`
- `Белые списки`
- `К началу`

### Ветка `qr`

Действия:

- `Отправить в бота`
  - `POST /telegram-app/vless/send-qr`
- `Назад`
- `К началу`

### Ветка `debt`

Показывается сообщение о необходимости активной подписки.

Доступны:

- `Подписка`
- `К началу`

Support-боту важно:

- Основной сценарий VLESS в UI это не скачивание, а открытие deep link в приложение клиента.
- Если deep link не сработал, fallback-сценарий:
  - открыть `Link`
  - скопировать raw-ссылку
  - или открыть `QR-Code`

## 4.4 VLESS White List

Файл: [VlessWhiteList.vue](/Users/alexandersustavov/projects/home/wireguard-vpn-app/resources/js/Pages/TelegramApp/VlessWhiteList.vue)

Состояния страницы:

- `loading`
- `error`
- `debt`
- `ready`

Внутренние шаги:

- `menu`
- `links`

Пользовательский путь:

1. Открыть `VLESS Белые списки`.
2. Выполняется `GET /telegram-app/vless-wl/link`.
3. Если доступ есть, показывается `menu`.
4. Нажать `Link`.
5. Открывается список deep links для WL-конфигов.

Доступные действия на `links`:

- открыть preferred deep link
- скопировать preferred deep link
- открыть / скопировать дополнительные deep links
- `Назад`

Важно:

- В backend для WL есть QR-роуты:
  - `GET /telegram-app/vless-wl/qr-code`
  - `POST /telegram-app/vless-wl/send-qr`
- Но в текущем UI на шаге `menu` нет кнопки `QR-Code`.
- Значит это потенциально существующий backend-путь, но не пользовательский путь текущего интерфейса.

Support-боту стоит считать `VLESS WL` link-only сценарием.

## 4.5 Payments

Файл: [Payments.vue](/Users/alexandersustavov/projects/home/wireguard-vpn-app/resources/js/Pages/TelegramApp/Payments.vue)

Состояния страницы:

- `loading`
- `error`
- `ready`

Внутренние экраны:

- `overview`
- `packages`
- `activated`
- `gift-created`
- `code-activated`
- `payment-link`

### Экран `overview`

Пользователь видит:

- статус подписки
- баланс
- долг
- предупреждение, если денег на следующий месяц не хватает
- учёт реферальной скидки
- поле активации подарочного кода
- список уже купленных подарочных кодов

Действия:

- `Купить подписку` -> `packages` с режимом `PERSONAL`
- `Купить код в подарок` -> `packages` с режимом `GIFT`
- `Активировать код`
- `К началу`
- `Копировать` у уже купленного подарочного кода

### Экран `packages`

Действия:

- выбрать пакет
- `Оплатить` или `Получить подарочный код`
- `Отменить` -> возврат в `overview`

Возможные исходы:

- `status=activated` -> экран `activated`
- `status=gift_code_created` -> экран `gift-created`
- есть `confirmation_url` -> экран `payment-link`
- ошибка -> остаться на `packages` с текстом ошибки

### Экран `activated`

Используется, когда подписка активировалась сразу, например за счёт баланса или trial.

Действия:

- `К началу`

### Экран `gift-created`

Показывает сгенерированный код.

Действия:

- `Скопировать код`
- `К моим кодам` -> возврат в `overview`

### Экран `code-activated`

Появляется после ручной активации подарочного кода.

Действия:

- `К началу`

### Экран `payment-link`

Появляется, если нужна внешняя оплата.

Действия:

- `Перейти к оплате картой / СБП`
  - открывает `confirmation_url`
- `К началу`

Support-боту важно:

- В `Payments` есть три разных бизнес-сценария:
  - продлить себе подписку
  - купить подарочный код
  - активировать уже полученный код
- Если у пользователя долг, именно здесь он увидит объяснение, почему закрыт доступ к `WireGuard` и `VLESS`.

## 4.6 Help

Файл: [Help.vue](/Users/alexandersustavov/projects/home/wireguard-vpn-app/resources/js/Pages/TelegramApp/Help.vue)

Состояния:

- `loading`
- `error`
- `ready`

Внутренние разделы:

- `menu`
- `wg`
- `vless`
- `clients`
- `wg-clients`
- `vless-clients`

### Раздел `menu`

Действия:

- `WG`
- `VLESS`
- `Клиенты`
- `Поддержка` -> `/telegram-app/support`
- `К началу`

### Раздел `wg`

Показывает текстовую инструкцию по WireGuard.

Действия:

- `WG клиенты`
- `Назад`
- `К началу`

### Раздел `vless`

Показывает текстовую инструкцию по VLESS.

Действия:

- `VLESS клиенты`
- `Назад`
- `К началу`

### Раздел `clients`

Действия:

- `WG клиенты`
- `VLESS клиенты`
- `Назад`
- `К началу`

### Разделы `wg-clients` и `vless-clients`

Открывают внешние ссылки на приложения:

- App Store
- Google Play
- сайты клиентов

Навигация:

- `Назад`
- `К началу`

Support-боту важно:

- `Help` это не просто FAQ, а реальный маршрут до `Support`.
- Если пользователь спрашивает "какое приложение поставить", это путь через `Help -> Clients`.

## 4.7 Chats

Файл: [Chats.vue](/Users/alexandersustavov/projects/home/wireguard-vpn-app/resources/js/Pages/TelegramApp/Chats.vue)

Состояния:

- `loading`
- `error`
- `ready`

Контент:

- `Новости`
- `Флуд`

Действия:

- `Открыть` ссылку на Telegram-канал или чат

Особенность:

- На `Home` прямой карточки `Chats` нет.
- Но экран всегда доступен через нижнюю навигацию.

## 4.8 Support

Файл: [Support.vue](/Users/alexandersustavov/projects/home/wireguard-vpn-app/resources/js/Pages/TelegramApp/Support.vue)

Состояния:

- `loading`
- `error`
- `ready`

Внутренние режимы:

- пустой список тикетов
- форма создания обращения
- список тикетов

### Если тикетов нет

Пользователь видит:

- сообщение `У вас пока нет обращений`
- кнопку `Создать обращение`

### Создание обращения

Пользователь вводит:

- `Тема`
- `Сообщение`

Далее:

1. `POST /telegram-app/support/tickets`
2. Если сервер вернул `ticket.id`, происходит переход на `/telegram-app/support/{ticketId}`

### Если тикеты есть

Пользователь видит список карточек обращений.

Действия:

- `Создать обращение`
- открыть существующее обращение

Важная особенность:

- Страница раз в 5 секунд переподгружает список тикетов.

## 4.9 SupportShow

Файл: [SupportShow.vue](/Users/alexandersustavov/projects/home/wireguard-vpn-app/resources/js/Pages/TelegramApp/SupportShow.vue)

Состояния:

- `loading`
- `error`
- `ready`

Действия:

- отправить сообщение в тикет
- `К списку`

Путь:

1. Открыть тикет из `Support` или по deep link `ticket_{id}`.
2. `GET /telegram-app/support/tickets/{ticketId}`
3. Пользователь видит историю сообщений.
4. Может отправить новое сообщение через `POST /telegram-app/support/tickets/{ticketId}/messages`

Особенности:

- Поллинг раз в 5 секунд.
- Сообщения админа показываются как `Оператор`.
- Сообщения пользователя показываются как `Вы`.

## 5. Полный список пользовательских переходов

Ниже список переходов в форме `откуда -> действие -> куда`.

### Главные переходы

- `BOOTSTRAP -> success -> HOME`
- `BOOTSTRAP -> start_param ticket_{id} -> SUPPORT_SHOW`
- `HOME -> WireGuard -> WIREGUARD`
- `HOME -> VLESS -> VLESS`
- `HOME -> VLESS Белые списки -> VLESS_WL`
- `HOME -> Подписка -> PAYMENTS`
- `HOME -> Помощь -> HELP`
- `любая страница с bottom nav -> Чаты -> CHATS`

### WireGuard

- `WIREGUARD(list) -> выбрать конфиг -> WIREGUARD(actions)`
- `WIREGUARD(actions) -> QR Code -> WIREGUARD(qr)`
- `WIREGUARD(actions) -> Отправить файл в бота -> WIREGUARD(file)`
- `WIREGUARD(actions) -> VLESS -> VLESS`
- `WIREGUARD(qr) -> Конфиги -> WIREGUARD(list)`
- `WIREGUARD(file) -> Конфиги -> WIREGUARD(list)`
- `WIREGUARD(debt) -> Подписка -> PAYMENTS`
- `WIREGUARD(empty) -> К началу -> HOME`

### VLESS

- `VLESS(menu) -> Link -> VLESS(links)`
- `VLESS(menu) -> QR-Code -> VLESS(qr)`
- `VLESS(menu) -> Белые списки -> VLESS_WL`
- `VLESS(links) -> Назад -> VLESS(menu)`
- `VLESS(qr) -> Назад -> VLESS(menu)`
- `VLESS(debt) -> Подписка -> PAYMENTS`

### VLESS White List

- `VLESS_WL(menu) -> Link -> VLESS_WL(links)`
- `VLESS_WL(links) -> Назад -> VLESS_WL(menu)`
- `VLESS_WL(debt) -> Подписка -> PAYMENTS`

### Payments

- `PAYMENTS(overview) -> Купить подписку -> PAYMENTS(packages:PERSONAL)`
- `PAYMENTS(overview) -> Купить код в подарок -> PAYMENTS(packages:GIFT)`
- `PAYMENTS(overview) -> Активировать код -> PAYMENTS(code-activated)` 
- `PAYMENTS(packages) -> Отменить -> PAYMENTS(overview)`
- `PAYMENTS(packages) -> activated -> PAYMENTS(activated)`
- `PAYMENTS(packages) -> gift_code_created -> PAYMENTS(gift-created)`
- `PAYMENTS(packages) -> confirmation_url -> PAYMENTS(payment-link)`
- `PAYMENTS(gift-created) -> К моим кодам -> PAYMENTS(overview)`

### Help

- `HELP(menu) -> WG -> HELP(wg)`
- `HELP(menu) -> VLESS -> HELP(vless)`
- `HELP(menu) -> Клиенты -> HELP(clients)`
- `HELP(menu) -> Поддержка -> SUPPORT`
- `HELP(wg) -> WG клиенты -> HELP(wg-clients)`
- `HELP(vless) -> VLESS клиенты -> HELP(vless-clients)`
- `HELP(clients) -> WG клиенты -> HELP(wg-clients)`
- `HELP(clients) -> VLESS клиенты -> HELP(vless-clients)`

### Support

- `SUPPORT(empty) -> Создать обращение -> SUPPORT(composer)`
- `SUPPORT(composer) -> Отправить обращение -> SUPPORT_SHOW`
- `SUPPORT(list) -> открыть тикет -> SUPPORT_SHOW`
- `SUPPORT_SHOW -> К списку -> SUPPORT`
- `SUPPORT_SHOW -> Отправить сообщение -> SUPPORT_SHOW`

## 6. Что support-боту лучше считать отдельными intents

Рекомендуемые intents:

- `open_home`
- `open_wireguard`
- `wireguard_select_config`
- `wireguard_show_qr`
- `wireguard_send_file_to_bot`
- `wireguard_send_qr_to_bot`
- `open_vless`
- `vless_open_deeplink`
- `vless_copy_raw_link`
- `vless_show_qr`
- `open_vless_wl`
- `open_payments`
- `buy_subscription_for_self`
- `buy_gift_code`
- `activate_gift_code`
- `open_help`
- `open_clients_help`
- `open_support`
- `create_support_ticket`
- `open_support_ticket`
- `reply_support_ticket`
- `open_chats`
- `claim_referrer`
- `copy_referral_link`
- `share_referral_link`

## 7. Важные edge cases

- Если у пользователя нет активной подписки, `WireGuard` и `VLESS` уходят в состояние `debt`.
- `Chats` доступны через нижнее меню, но не показаны карточкой на главной.
- `Support` доступен из `Help`, но не через нижнюю навигацию.
- `VLESS WL` может быть доступен только части пользователей.
- У `VLESS WL` есть backend-поддержка QR, но в текущем UI нет пути до неё.
- В `Payments` активация кода и покупка кода это два разных сценария, их не стоит смешивать в логике бота.
- В `SupportShow` пользователь может попасть как из списка тикетов, так и сразу по deep link.

## 8. Что можно использовать как быстрый FAQ для бота

- "Где продлить подписку?" -> `Home -> Подписка`
- "Как подключить WireGuard?" -> `Home -> WireGuard -> выбрать конфиг -> QR Code` или `Отправить файл в бота`
- "Как подключить VLESS?" -> `Home -> VLESS -> Link`
- "Что делать, если deep link не открылся?" -> `VLESS -> Link -> Скопировать raw-ссылку` или `QR-Code`
- "Где взять приложение?" -> `Home -> Помощь -> Клиенты`
- "Как написать в поддержку?" -> `Home -> Помощь -> Поддержка`
- "Где мой подарочный код?" -> `Подписка -> Мои подарочные коды`
- "Как активировать код?" -> `Подписка -> Код активации`

