# Telegram Mini-App State Machine

Документ описывает целевую state machine mini-app на базе текущей логики Telegram-бота и текущей реализации mini-app в проекте.

Статус по коду на 2026-06-28:
- Уже реализованы mini-app страницы: `Home`, `Payments`, `Support`, `SupportShow`.
- Уже реализованы mini-app API: `auth/telegram`, `me`, `subscription-packages`, `payments/subscriptions`, `support/*`, `referrals/claim`.
- Еще не перенесены в mini-app как отдельные экраны: `WireGuard`, `WireGuard Config Actions`, `VLESS`, `Help`, `Help WG`, `Help VLESS`, `Clients`, `WG Clients`, `VLESS Clients`.
- Исходная бот-логика продолжает жить в API-маршрутах `/api/users/{telegramId}/...` и должна быть адаптирована под mini-app UI.

## 1. Краткая карта экранов

### Bootstrap
- `BOOTSTRAP`
  - вход из Telegram Mini App
  - проверка `initData`
  - авто-регистрация / авто-привязка пользователя
  - загрузка профиля
  - переход в `HOME`
  - при ошибке переход в `APP_INIT_ERROR`

### Основные пользовательские экраны
- `HOME`
- `WIREGUARD_CONFIGS`
- `WIREGUARD_CONFIG_ACTIONS`
- `WIREGUARD_QR_RESULT`
- `WIREGUARD_FILE_RESULT`
- `VLESS_HOME`
- `VLESS_LINK_RESULT`
- `VLESS_QR_RESULT`
- `SUBSCRIPTION_OVERVIEW`
- `SUBSCRIPTION_PACKAGE_SELECT`
- `SUBSCRIPTION_ACTIVATED`
- `SUBSCRIPTION_PAYMENT_REDIRECT`
- `HELP_MENU`
- `HELP_WG`
- `HELP_VLESS`
- `HELP_CLIENTS`
- `HELP_WG_CLIENTS`
- `HELP_VLESS_CLIENTS`

### Служебные и системные состояния
- `APP_INIT_ERROR`
- `ACCESS_DENIED_DEBT`
- `EMPTY_WIREGUARD_CONFIGS`
- `VLESS_ACCESS_ERROR`
- `PAYMENT_CANCELLED`
- `PAYMENT_ERROR`

### Вне пользовательского mini-app UI
- `ADMIN_APPROVE_DEPOSIT`
- `ADMIN_DENY_DEPOSIT`

## 2. Полная таблица переходов

| screen | element/button | action | api request | next screen | error state |
| --- | --- | --- | --- | --- | --- |
| `BOOTSTRAP` | auto | Валидация Telegram WebApp `initData` | `POST /telegram-app/auth/telegram` | `BOOTSTRAP_PROFILE_LOAD` | `APP_INIT_ERROR` при невалидном `hash`, истекшей сессии, пустом `telegram id`, ошибке конфигурации |
| `BOOTSTRAP_PROFILE_LOAD` | auto | Загрузка профиля после входа | `GET /telegram-app/me` | `HOME` | `APP_INIT_ERROR` при `401` или ошибке загрузки |
| `HOME` | `WireGuard` | Открыть список WireGuard-конфигов | `GET /api/users/{telegramId}/wireguard/configs` или mini-app proxy `GET /telegram-app/wireguard/configs` | `WIREGUARD_CONFIGS` | `ACCESS_DENIED_DEBT`, `EMPTY_WIREGUARD_CONFIGS`, generic error |
| `HOME` | `VLESS` | Открыть VLESS-экран | `GET /api/users/{telegramId}/vless/link` или mini-app proxy `GET /telegram-app/vless` | `VLESS_HOME` | `VLESS_ACCESS_ERROR`, `ACCESS_DENIED_DEBT`, generic error |
| `HOME` | `Подписка` | Открыть обзор подписки | `GET /telegram-app/me` | `SUBSCRIPTION_OVERVIEW` | `APP_INIT_ERROR` |
| `HOME` | `Помощь` | Открыть меню помощи | none | `HELP_MENU` | none |
| `HOME` | auto after guest bootstrap | В текущем mini-app отдельный guest menu не нужен, потому что вход сразу делает регистрацию | `POST /telegram-app/auth/telegram` | `HOME` | `APP_INIT_ERROR` |
| `WIREGUARD_CONFIGS` | auto | Загрузить список конфигов | `GET /api/users/{telegramId}/wireguard/configs` | `WIREGUARD_CONFIGS` | `ACCESS_DENIED_DEBT` при `403 { type: "debt" }`, generic error |
| `WIREGUARD_CONFIGS` | config item | Выбрать конфиг | none, локальная установка `selectedConfig` | `WIREGUARD_CONFIG_ACTIONS` | если конфиг исчез между загрузкой и действием, ошибка проявится на следующем шаге |
| `WIREGUARD_CONFIGS` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `WIREGUARD_CONFIGS` | auto when no configs | Показ пустого состояния | response `configs: []` | `EMPTY_WIREGUARD_CONFIGS` | none |
| `WIREGUARD_CONFIGS` | auto when debt | Показ экрана долга вместо конфигов | response `403` with `type=debt` | `ACCESS_DENIED_DEBT` | none |
| `EMPTY_WIREGUARD_CONFIGS` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `ACCESS_DENIED_DEBT` | `Подписка` | Перейти к подписке | `GET /telegram-app/me` | `SUBSCRIPTION_OVERVIEW` | `APP_INIT_ERROR` |
| `ACCESS_DENIED_DEBT` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `WIREGUARD_CONFIG_ACTIONS` | `QR Code` | Получить QR для выбранного конфига | `GET /api/users/{telegramId}/configs/wireguard/{configId}/qr-code` | `WIREGUARD_QR_RESULT` | `CONFIG_NOT_FOUND`, `UNEXPECTED_ERROR` |
| `WIREGUARD_CONFIG_ACTIONS` | `Файл` | Скачать конфиг-файл | `GET /api/users/{telegramId}/configs/wireguard/{configId}/download` | `WIREGUARD_FILE_RESULT` | `CONFIG_NOT_FOUND`, `UNEXPECTED_ERROR` |
| `WIREGUARD_CONFIG_ACTIONS` | `WireGuard Конфиги` | Назад к списку WireGuard-конфигов | `GET /api/users/{telegramId}/wireguard/configs` | `WIREGUARD_CONFIGS` | `ACCESS_DENIED_DEBT`, generic error |
| `WIREGUARD_CONFIG_ACTIONS` | `VLESS` | Перейти в VLESS | `GET /api/users/{telegramId}/vless/link` или mini-app proxy `GET /telegram-app/vless` | `VLESS_HOME` | `VLESS_ACCESS_ERROR`, `ACCESS_DENIED_DEBT` |
| `WIREGUARD_QR_RESULT` | `Конфиги` | Назад к списку WireGuard-конфигов | `GET /api/users/{telegramId}/wireguard/configs` | `WIREGUARD_CONFIGS` | `ACCESS_DENIED_DEBT`, generic error |
| `WIREGUARD_QR_RESULT` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `WIREGUARD_FILE_RESULT` | `Конфиги` | Назад к списку WireGuard-конфигов | `GET /api/users/{telegramId}/wireguard/configs` | `WIREGUARD_CONFIGS` | `ACCESS_DENIED_DEBT`, generic error |
| `WIREGUARD_FILE_RESULT` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `VLESS_HOME` | auto | Проверить доступ к VLESS и получить базовые ссылки | `GET /api/users/{telegramId}/vless/link` | `VLESS_HOME` | `VLESS_ACCESS_ERROR`, `ACCESS_DENIED_DEBT`, generic error |
| `VLESS_HOME` | `Link` | Показать deep links и raw link | `GET /api/users/{telegramId}/vless/link` | `VLESS_LINK_RESULT` | `VLESS_ACCESS_ERROR`, `ACCESS_DENIED_DEBT`, generic error |
| `VLESS_HOME` | `QR-Code` | Получить QR | `GET /api/users/{telegramId}/vless/qr-code` | `VLESS_QR_RESULT` | `VLESS_ACCESS_ERROR`, `ACCESS_DENIED_DEBT`, generic error |
| `VLESS_HOME` | `Белые списки` | Открыть WL-страницу сразу на шаге deep links | none, переход на `/telegram-app/vless-wl?step=links` | `VLESS_WL_LINK_RESULT` | none |
| `VLESS_HOME` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `VLESS_LINK_RESULT` | `Назад` | Вернуться на VLESS-экран | none или локальный возврат | `VLESS_HOME` | none |
| `VLESS_LINK_RESULT` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `VLESS_QR_RESULT` | `Назад` | Вернуться на VLESS-экран | none или локальный возврат | `VLESS_HOME` | none |
| `VLESS_QR_RESULT` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `SUBSCRIPTION_OVERVIEW` | auto | Показать баланс, долг, дату подписки, предупреждение | `GET /telegram-app/me` | `SUBSCRIPTION_OVERVIEW` | `APP_INIT_ERROR` |
| `SUBSCRIPTION_OVERVIEW` | `Купить подписку` | Загрузить доступные пакеты | `GET /telegram-app/subscription-packages` | `SUBSCRIPTION_PACKAGE_SELECT` | `PAYMENT_ERROR` при ошибке загрузки пакетов |
| `SUBSCRIPTION_OVERVIEW` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `SUBSCRIPTION_PACKAGE_SELECT` | package button `1 месяц - N ₽` и т.д. | Выбрать срок | none, локальная установка `selectedMonth` | `SUBSCRIPTION_PACKAGE_SELECT` | none |
| `SUBSCRIPTION_PACKAGE_SELECT` | `Оплатить` | Создать запрос на оплату | `POST /telegram-app/payments/subscriptions` body `{ month, return_url }` | `SUBSCRIPTION_ACTIVATED` или `SUBSCRIPTION_PAYMENT_REDIRECT` | `PAYMENT_ERROR` при `422/500` |
| `SUBSCRIPTION_PACKAGE_SELECT` | `Отменить` | Отмена действия | none в целевой mini-app, локальный возврат | `SUBSCRIPTION_OVERVIEW` | в старой bot-логике есть дефект возврата |
| `SUBSCRIPTION_ACTIVATED` | `К началу` | Вернуться в главное меню | `GET /telegram-app/me` желательно для рефреша | `HOME` | `APP_INIT_ERROR` |
| `SUBSCRIPTION_PAYMENT_REDIRECT` | `Перейти к оплате картой / СБП` | Переход на внешний URL оплаты | none, использовать `confirmation_url` из предыдущего ответа | external YooKassa | `PAYMENT_ERROR` если `confirmation_url` отсутствует |
| `SUBSCRIPTION_PAYMENT_REDIRECT` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `PAYMENT_CANCELLED` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `PAYMENT_ERROR` | `Повторить` | Повторно загрузить пакеты или повторить покупку | `GET /telegram-app/subscription-packages` или `POST /telegram-app/payments/subscriptions` | `SUBSCRIPTION_PACKAGE_SELECT` | повторная ошибка |
| `PAYMENT_ERROR` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `HELP_MENU` | `WG` | Открыть инструкцию WireGuard | none или локальный контент | `HELP_WG` | none |
| `HELP_MENU` | `VLESS` | Открыть инструкцию VLESS | none или локальный контент | `HELP_VLESS` | none |
| `HELP_MENU` | `Клиенты` | Открыть выбор клиентских приложений | none | `HELP_CLIENTS` | none |
| `HELP_MENU` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `HELP_WG` | `WG клиенты` | Открыть список WG-клиентов | none | `HELP_WG_CLIENTS` | none |
| `HELP_WG` | `Назад` | Вернуться в меню помощи | none | `HELP_MENU` | none |
| `HELP_WG` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `HELP_VLESS` | `VLESS клиенты` | Открыть список VLESS-клиентов | none | `HELP_VLESS_CLIENTS` | none |
| `HELP_VLESS` | `Назад` | Вернуться в меню помощи | none | `HELP_MENU` | none |
| `HELP_VLESS` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `HELP_CLIENTS` | `WG клиенты` | Открыть WG-клиенты | none | `HELP_WG_CLIENTS` | none |
| `HELP_CLIENTS` | `VLESS клиенты` | Открыть VLESS-клиенты | none | `HELP_VLESS_CLIENTS` | none |
| `HELP_CLIENTS` | `Назад` | Вернуться в меню помощи | none | `HELP_MENU` | none |
| `HELP_CLIENTS` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `HELP_WG_CLIENTS` | external links | Открыть магазин приложений или сайт | none | external link | ошибка только если Telegram/WebApp не может открыть ссылку |
| `HELP_WG_CLIENTS` | `Назад` | Вернуться в `HELP_WG` или `HELP_CLIENTS` | none | предыдущий help screen | none |
| `HELP_WG_CLIENTS` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `HELP_VLESS_CLIENTS` | external links | Открыть магазин приложений или сайт | none | external link | ошибка только если Telegram/WebApp не может открыть ссылку |
| `HELP_VLESS_CLIENTS` | `Назад` | Вернуться в `HELP_VLESS` или `HELP_CLIENTS` | none | предыдущий help screen | none |
| `HELP_VLESS_CLIENTS` | `К началу` | Вернуться в главное меню | none | `HOME` | none |
| `ADMIN_APPROVE_DEPOSIT` | `approve_deposit|{transactionId}` | Одобрить платеж | `POST /api/transactions/{transaction}/approve` | admin success state | admin error state |
| `ADMIN_DENY_DEPOSIT` | `deny_deposit|{transactionId}` | Отклонить платеж | `DELETE /api/transactions/{transaction}/decline` | admin success state | admin error state |

## 3. Список callback / action identifiers

### Пользовательские bot callbacks из исходной логики
- `/start`
- `wireguard`
- `vless`
- `help`
- `subscription`
- `config:{id}` или эквивалент выбора конфига
- `wireguard_qr:{id}` или эквивалент действия `QR Code`
- `wireguard_file:{id}` или эквивалент действия `Файл`
- `wireguard_configs`
- `vless_link`
- `vless_qr`
- `vless|configs`
- `help_wg`
- `help_vless`
- `help_clients`
- `help_wg_clients`
- `help_vless_clients`
- `buy_subscription`
- `submit_payment_request|{month}`
- `choose_subscription_package|{month}`
- `cancel`
- `approve_deposit|{transactionId}`
- `deny_deposit|{transactionId}`

### Идентификаторы действий в текущем mini-app
- page route: `/telegram-app/`
- page route: `/telegram-app/payments`
- page route: `/telegram-app/support`
- page route: `/telegram-app/support/{ticketId}`
- API action: `POST /telegram-app/auth/telegram`
- API action: `GET /telegram-app/me`
- API action: `GET /telegram-app/subscription-packages`
- API action: `POST /telegram-app/payments/subscriptions`
- API action: `GET /telegram-app/support/tickets`
- API action: `POST /telegram-app/support/tickets`
- API action: `GET /telegram-app/support/tickets/{ticketId}`
- API action: `POST /telegram-app/support/tickets/{ticketId}/messages`
- API action: `POST /telegram-app/referrals/claim`

### Рекомендуемые mini-app action ids
- `nav.home`
- `nav.wireguard`
- `nav.vless`
- `nav.subscription`
- `nav.help`
- `wireguard.config.select`
- `wireguard.config.download`
- `wireguard.config.qr`
- `vless.link.show`
- `vless.qr.show`
- `subscription.package.select`
- `subscription.purchase.submit`
- `subscription.purchase.cancel`
- `help.section.open`
- `external.open`

## 4. API-сценарии

### 4.1 Bootstrap и авторизация mini-app
1. Telegram открывает mini-app и передает `initData`.
2. Frontend вызывает `POST /telegram-app/auth/telegram`.
3. Backend:
   - валидирует `hash`
   - проверяет TTL init-data
   - достает Telegram user payload
   - автоматически регистрирует или обновляет пользователя через `ApiUserService::register()`
   - выдает bearer token mini-app сессии
4. Frontend вызывает `GET /telegram-app/me`.
5. UI строит `HOME`.

### 4.2 Получение профиля и статуса подписки
- `GET /telegram-app/me`
- возвращает:
  - `id`
  - `name`
  - `telegram`
  - `telegram_id`
  - `balance`
  - `is_admin`
  - `has_active_access`
  - `subscription_expires_at`
  - `referral`

### 4.3 WireGuard configs
- Текущее backend-ядро:
  - `GET /api/users/{telegramId}/wireguard/configs`
  - `GET /api/users/{telegramId}/configs/wireguard/{configId}/download`
  - `GET /api/users/{telegramId}/configs/wireguard/{configId}/qr-code`
- Требуемая mini-app адаптация:
  - либо прямой вызов этих API из frontend
  - либо новый слой `/telegram-app/wireguard/*`, который прячет `telegramId` и использует mini-app bearer auth
- Нормальный ответ списка:
  - `200 { configs: [{ id, name, download_url, qr_code_url }] }`
- Долг:
  - `403 { type: "debt", message }`
- Пустое состояние:
  - `200 { configs: [] }`

### 4.4 VLESS
- Текущее backend-ядро:
  - `GET /api/users/{telegramId}/vless/link`
  - `GET /api/users/{telegramId}/vless/qr-code`
- Ответ `vless/link` содержит:
  - `link`
  - `happ_deep_link`
  - `v2raytun_deeplink`
  - дополнительные deep links: `v2rayn`, `v2rayng`, `v2raybox`, `sing_box`, `hiddify`
- Для mini-app лучше нормализовать UI под:
  - `Happ`
  - `V2RayTun`
  - `raw link`
  - дополнительные клиенты можно вынести в expandable block

### 4.5 Подписка и оплата
- Обзор подписки:
  - `GET /telegram-app/me`
- Список пакетов:
  - `GET /telegram-app/subscription-packages`
- Создание оплаты:
  - `POST /telegram-app/payments/subscriptions`
  - body: `{ month, return_url }`
- Варианты ответа:
  - `status=activated`
    - подписка активирована сразу за счет баланса
    - есть `message`, `end_date`, `formatted_end_date`
  - `status=deposit_required`
    - создана транзакция
    - есть `confirmation_url`
    - есть `transaction_id`, `invoice_id`, `payment_id`, `deposit_amount`

### 4.6 Help
- Сейчас help-ветка не требует API.
- Контент может храниться:
  - как статический frontend контент
  - как CMS/messages из backend, если планируется редактирование админкой

### 4.7 Admin / backoffice payment moderation
- `POST /api/transactions/{transaction}/approve`
- `DELETE /api/transactions/{transaction}/decline`
- Это не часть mini-app пользовательского интерфейса.
- Нужно держать отдельно как служебный moderation flow.

## 5. Edge cases, ошибки и нестандартные ветки

### Bootstrap / auth
- Невалидный `initData.hash` -> `APP_INIT_ERROR`
- Истекший `auth_date` -> `APP_INIT_ERROR` с предложением открыть mini-app заново
- Не настроен `TELEGRAM_BOT_TOKEN` -> системная ошибка и блок входа
- Отсутствует `user.id` в Telegram payload -> блок входа
- `401 Unauthorized` по bearer token -> silent logout и повторный bootstrap

### WireGuard
- `403 type=debt` -> не показывать список конфигов, показывать CTA в подписку
- `configs=[]` -> пустой экран `Конфиги не найдены`
- `404 config not found` при скачивании/QR -> конфиг удален или скрыт после загрузки списка
- `500` генерации QR или получения modern WireGuard config -> экран ошибки с повтором
- Для non-admin конфиги могут быть скрыты по `server.hide_configs_for_non_admins`

### VLESS
- Нет активного доступа -> `403 debt`, переход в подписку
- Ошибка генерации ссылки или QR -> error state на экране VLESS
- UI в ТЗ предполагает 2 клиента, но backend уже возвращает больше deep links

### Subscription / payment
- Ошибка загрузки пакетов -> `PAYMENT_ERROR`
- Пустой список пакетов -> отдельное empty state `Нет доступных тарифов`
- `422` при создании оплаты -> показать сообщение backend без потери выбора тарифа
- `500` при создании оплаты -> generic payment error
- `status=activated` и `confirmation_url` одновременно не ожидаются, считать это спорным состоянием
- Возврат из YooKassa по `return_url` должен триггерить рефреш `GET /telegram-app/me`

### Help
- Ошибка открытия внешней ссылки в Telegram WebApp -> fallback `window.open`
- Потеря навигационного контекста для кнопки `Назад` на экранах клиентов -> лучше хранить `originScreen`

### Общие
- Если mini-app продолжит использовать старые `/api/users/{telegramId}` маршруты, на frontend придется хранить `telegramId`; это менее безопасно и хуже для поддержки, чем mini-app scoped endpoints.

## 6. Устаревшие, мертвые и спорные ветки

### Устаревшее / legacy
- `guest menu` (`Регистрация`, `Помощь`)
  - практически вытеснен авто-регистрацией в `POST /telegram-app/auth/telegram`
  - в mini-app как отдельный экран не нужен
- `choose_subscription_package|{month}`
  - присутствует в коде как callback
  - текущий UI использует `submit_payment_request|{month}`
  - считать legacy / unused

### Мертвые / недостижимые из UI
- `vless|configs`
  - переход существует в логике
  - из текущего UI не вызывается
  - считать dead transition, пока не появится явная кнопка

### Дефекты текущей логики
- `Отменить` на оплате
  - сейчас после отмены есть сообщение `Действие отменено 👍`
  - возвратная кнопка `К началу` реализована некорректно
  - в mini-app это нужно заменить на обычный deterministic переход `SUBSCRIPTION_PACKAGE_SELECT -> SUBSCRIPTION_OVERVIEW`
- Долг сейчас обрабатывается как API-ошибка `403`
  - для mini-app лучше нормализовать в осознанный бизнес-state `ACCESS_DENIED_DEBT`, а не общий error toast
- VLESS и WireGuard пока не имеют mini-app собственных роутов
  - логика остается размазанной между bot/API и mini-app

### Спорные места
- Что считать главным экраном mini-app:
  - текущий `Home` больше похож на профиль/реферальную главную
  - по bot-логике `HOME` должен быть главным меню протоколов и подписки
- Нужно ли сохранять пост-результатные промежуточные экраны `WIREGUARD_QR_RESULT`, `WIREGUARD_FILE_RESULT`, `VLESS_LINK_RESULT`, `VLESS_QR_RESULT`
  - для state machine да, это полезно
  - для UI можно свернуть часть из них в modal / drawer

## 7. Рекомендуемая структура экранов mini-app

### Вариант структуры
- `/telegram-app/`
  - `HOME`
- `/telegram-app/wireguard`
  - `WIREGUARD_CONFIGS`
- `/telegram-app/wireguard/:configId`
  - `WIREGUARD_CONFIG_ACTIONS`
- `/telegram-app/wireguard/:configId/qr`
  - `WIREGUARD_QR_RESULT`
- `/telegram-app/wireguard/:configId/file`
  - `WIREGUARD_FILE_RESULT`
- `/telegram-app/vless`
  - `VLESS_HOME`
- `/telegram-app/vless/link`
  - `VLESS_LINK_RESULT`
- `/telegram-app/vless/qr`
  - `VLESS_QR_RESULT`
- `/telegram-app/subscription`
  - `SUBSCRIPTION_OVERVIEW`
- `/telegram-app/subscription/packages`
  - `SUBSCRIPTION_PACKAGE_SELECT`
- `/telegram-app/help`
  - `HELP_MENU`
- `/telegram-app/help/wg`
  - `HELP_WG`
- `/telegram-app/help/vless`
  - `HELP_VLESS`
- `/telegram-app/help/clients`
  - `HELP_CLIENTS`
- `/telegram-app/help/clients/wg`
  - `HELP_WG_CLIENTS`
- `/telegram-app/help/clients/vless`
  - `HELP_VLESS_CLIENTS`
- `/telegram-app/support`
  - оставить как отдельный блок, не часть bot state machine

### Рекомендуемая backend-структура
- Оставить текущие:
  - `POST /telegram-app/auth/telegram`
  - `GET /telegram-app/me`
  - `GET /telegram-app/subscription-packages`
  - `POST /telegram-app/payments/subscriptions`
- Добавить mini-app scoped endpoints:
  - `GET /telegram-app/wireguard/configs`
  - `GET /telegram-app/wireguard/configs/{config}/download`
  - `GET /telegram-app/wireguard/configs/{config}/qr-code`
  - `GET /telegram-app/vless/link`
  - `GET /telegram-app/vless/qr-code`
  - опционально `GET /telegram-app/help-content`

### Рекомендуемая frontend state model
- `appSession`
  - `bootState`
  - `token`
  - `user`
- `navigation`
  - `currentScreen`
  - `historyStack`
  - `originScreen`
- `wireguard`
  - `configs`
  - `selectedConfig`
  - `loading`
  - `error`
- `vless`
  - `links`
  - `qrUrl`
  - `loading`
  - `error`
- `subscription`
  - `profile`
  - `packages`
  - `selectedMonth`
  - `paymentResult`
  - `error`
- `help`
  - `currentSection`

### UX-рекомендация
- Главный экран mini-app лучше перестроить под 4 явных CTA:
  - `WireGuard`
  - `VLESS`
  - `Подписка`
  - `Помощь`
- Текущий реферальный блок и профиль можно оставить на `HOME`, но ниже основных CTA.
- Support лучше оставить отдельным продуктовым разделом mini-app, а не смешивать с bot help menu.

## 8. Что уже соответствует текущему коду

### Уже есть
- авто-регистрация mini-app через `TelegramMiniAppAuthService`
- профиль пользователя через `GET /telegram-app/me`
- покупка подписки через `GET /telegram-app/subscription-packages` и `POST /telegram-app/payments/subscriptions`
- support flow
- referral flow

### Нужно добавить для полного переноса логики бота
- mini-app страницы для WireGuard
- mini-app страницы для VLESS
- mini-app страницы для Help
- mini-app scoped endpoints для WireGuard/VLESS без `telegramId` в URL
- явные UI state-экраны `debt`, `empty`, `payment error`, `config missing`
- cleanup legacy callback веток
