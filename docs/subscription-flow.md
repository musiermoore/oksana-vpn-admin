# Subscription Flow

Актуально по коду на `2026-07-23`.

Документ описывает фактический end-to-end flow подписки в проекте: от выбора пакета в mini-app до создания подписки, списаний, включения конфигов и выдачи `/connect`.

## 1. Базовые доменные правила

- Доступ пользователя определяется методом `User::hasActiveAccess()`.
- Активный доступ есть только когда одновременно:
  - есть активная подписка
  - баланс пользователя не отрицательный
- Источник денежных движений: `transactions`
- Подписочные интервалы хранятся в `user_subscriptions`
- Источник цены продления: активный `PaymentPeriod`

Ключевые файлы:

- [app/Models/User.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Models/User.php)
- [app/Models/UserSubscription.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Models/UserSubscription.php)
- [app/Services/SubscriptionService.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Services/SubscriptionService.php)
- [app/Console/Commands/DisableConfigsOfOverdueDebtorsCommand.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Console/Commands/DisableConfigsOfOverdueDebtorsCommand.php)

## 2. Точки входа в подписку

В проекте сейчас есть 5 основных сценариев:

1. Пробная подписка
2. Покупка пакета с мгновенной активацией
3. Покупка пакета через external payment + подтверждение
4. Активация подарочного кода
5. Автопродление по балансу

## 3. Пробная подписка

Flow:

1. Mini-app вызывает `POST /telegram-app/payments/subscriptions` c `month=0`.
2. [ApiTransactionService](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Services/Api/ApiTransactionService.php) вызывает `SubscriptionService::activateTrialForUser()`.
3. Создаётся approved transaction с `amount=0` и `type=subscription`.
4. Создаётся запись в `user_subscriptions` со `source=trial`.
5. Обновляется `users.subscription_expires_at`.
6. После успешной активации вызываются:
   - `DispatchDefaultConfigsForUserJob`
   - `configs:disable-overdue-debtors {user_id}`

Важно:

- Trial не идёт через `TransactionApproved`, поэтому post-activation действия должны запускаться явно.
- Trial доступен только если у пользователя ещё нет подписок.

Ключевые файлы:

- [app/Services/Api/ApiTransactionService.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Services/Api/ApiTransactionService.php)
- [app/Services/SubscriptionService.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Services/SubscriptionService.php)

## 4. Покупка пакета с мгновенной активацией

Сценарий:

- пользователь выбирает платный пакет
- у него уже достаточно баланса
- внешний платёж не нужен

Flow:

1. Mini-app вызывает `POST /telegram-app/payments/subscriptions`.
2. `ApiTransactionService::purchaseSubscription()` считает quote через `SubscriptionService::buildPurchaseQuote()`.
3. Если `deposit_amount <= 0`, вызывается `SubscriptionService::activatePackageForUser()`.
4. Внутри создаётся запись в `user_subscriptions`.
5. Создаётся approved negative transaction типа `subscription`.
6. Обновляется `subscription_expires_at`.

Важно:

- Старт новой подписки не должен уходить в прошлое.
- Для этого используется `SubscriptionService::resolveNextSubscriptionStartDate()`.

## 5. Покупка через external payment и подтверждение

Сценарий:

- пользователь выбирает платный пакет
- текущего баланса недостаточно

Flow:

1. `ApiTransactionService::purchaseSubscription()` создаёт pending `deposit` transaction.
2. Создаётся invoice и external payment через YooKassa.
3. После подтверждения платежа транзакция становится approved.
4. `TransactionCrudService::approve()` диспатчит `TransactionApproved`.
5. `ActivateSubscriptionAfterTransactionApproval`:
   - активирует пакет
   - либо делает renewal для подходящего случая
   - запускает `DispatchDefaultConfigsForUserJob`
   - вызывает `configs:disable-overdue-debtors {user_id}`

Ключевые файлы:

- [app/Services/Crud/TransactionCrudService.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Services/Crud/TransactionCrudService.php)
- [app/Events/TransactionApproved.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Events/TransactionApproved.php)
- [app/Listeners/ActivateSubscriptionAfterTransactionApproval.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Listeners/ActivateSubscriptionAfterTransactionApproval.php)

## 6. Подарочные коды

Сценарии два:

### 6.1 Покупка подарочного кода

1. Пользователь покупает gift package.
2. Если денег хватает, код выдаётся сразу.
3. Иначе создаётся pending deposit и дальше работает обычный approve flow.

### 6.2 Активация подарочного кода получателем

1. Mini-app принимает код.
2. `SubscriptionCodeService::activateForUser()` валидирует код.
3. `SubscriptionService::activateGiftCodeForUser()` создаёт подписку.
4. Если после этого доступ активен, диспатчится `DispatchDefaultConfigsForUserJob`.

Важно:

- Gift activation сейчас запускает создание конфигов.
- Если изменяется логика post-activation, trial, gift и paid flow нужно держать синхронными.

## 7. Автопродление

Flow:

1. Планировщик вызывает `RenewSubscriptionsCommand`.
2. `SubscriptionService::renewEligibleSubscriptions()` проходит по пользователям.
3. `renewOrCreateSubscription()` проверяет:
   - есть ли активный `PaymentPeriod`
   - наступила ли точка продления
   - хватает ли баланса
4. Если да, создаётся следующий подписочный период.
5. Создаётся approved negative transaction типа `subscription`.

Важно:

- Продление не занимается enable/disable конфигов напрямую.
- Для доступа и конфигов есть отдельная команда reconciliation.

## 8. Включение и отключение конфигов

Команда:

- `configs:disable-overdue-debtors`

Что делает:

- отключает WireGuard и VLESS конфиги пользователям без доступа
- включает их обратно пользователям, которые снова получили доступ

Сейчас команда запускается:

- по расписанию каждые 5 минут
- вручную после успешной активации paid subscription
- вручную после trial activation

Создание отсутствующих дефолтных конфигов делает отдельная job:

- `DispatchDefaultConfigsForUserJob`

Это важно:

- `DispatchDefaultConfigsForUserJob` создаёт недостающие конфиги
- `configs:disable-overdue-debtors` включает уже существующие выключенные конфиги
- для корректной реактивации обычно нужны оба шага

## 9. Flow `/connect` и connect white list

Основная выдача:

- `/connect` собирается через [UserSubscriptionService](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Services/Subscriptions/UserSubscriptionService.php)
- в основную подписку входят:
  - обычные пользовательские VLESS-узлы
  - внешние `vless_external_subscriptions`, у которых включён `include_in_main_subscription`
- WireGuard-узлы перед выдачей в URI-подписку нормализуются повторно:
  - приватный ключ и query-параметры percent-encode'ятся
  - это нужно, чтобы символы вроде `+`, `/`, `=` в ключах и адресах не ломали импорт в клиентах
  - правило применяется и к старым локальным записям, где в `extra` уже сохранён `wireguard://...`
- `/connect-json` использует тот же набор узлов, но отдаёт JSON-массив полных Xray-style конфигов, по одному объекту на узел
- каждый объект в `/connect-json` содержит индивидуальный `remarks` и `outbounds`, а общие `dns`/`routing`/`inbounds` подмешиваются из конфигурации приложения
- текущие DNS/routing/inbounds-настройки для `/connect-json` захардкожены в `config/connect_json.php` и вынесены в отдельный provider, чтобы позже их можно было заменить значениями из админки без смены маршрута

White list выдача:

- `/connect-wl-version-2` использует `VlessExternalSubscriptionAccessService`
- туда входят внешние подписки с флагом `include_in_whitelist`

Если подписка истекла:

- в `/connect` и white list добавляется placeholder `Ваша подписка закончилась 🚨`
- при этом `is_free` внешних подписок позволяет всё равно выдавать их конфиги

## 10. Внешние VLESS подписки

Сущность:

- `vless_external_subscriptions`

Ключевые флаги:

- `include_in_main_subscription`
- `include_in_whitelist`
- `is_free`
- `is_active`
- `is_ready`

Смысл:

- `include_in_main_subscription` включает конфиги в `/connect`
- `include_in_whitelist` включает конфиги в white list подписку
- `is_free` даёт доступ к этим конфигам даже без активной подписки
- плановая и ручная синхронизация внешних подписок ставят job в очередь через явный `Bus::dispatch(new SyncVlessExternalSubscriptionJob(...))`

Дополнительное правило нейминга:

- если в источнике один конфиг, к `connect_name_prefix` не добавляется номер
- если конфигов несколько, используется формат `Prefix 1`, `Prefix 2`, ...

## 11. Mini-app экраны, связанные с подпиской

Подробные пользовательские сценарии вынесены в:

- [docs/telegram-mini-app-user-flows.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-user-flows.md)
- [docs/telegram-mini-app-state-machine.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-state-machine.md)

Особенно важные экраны:

- `Payments`
- `WireGuard`
- `VLESS`
- `VLESS White List`
- `Home`

## 12. Что обязательно проверять при изменениях

Если меняется подписка, почти всегда нужно проверить:

1. Trial flow
2. Paid immediate activation flow
3. Paid approve flow
4. Gift activation flow
5. Renewal flow
6. `DispatchDefaultConfigsForUserJob`
7. `configs:disable-overdue-debtors`
8. `/connect` и `/connect-wl-version-2`

## 13. Какие тесты обычно нужны

Минимум:

- Feature test на API сценарий активации
- Feature test на command/job, если меняется post-activation behavior
- Тест на `/connect`, если меняется subscription output

Полезные существующие ориентиры:

- [tests/Feature/TelegramAppConnectionRoutesTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/TelegramAppConnectionRoutesTest.php)
- [tests/Feature/CreateDefaultConfigsForActiveSubscribersCommandTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/CreateDefaultConfigsForActiveSubscribersCommandTest.php)
- [tests/Feature/RenewSubscriptionsCommandTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/RenewSubscriptionsCommandTest.php)
- [tests/Feature/TelegramAppSubscriptionCodeTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/TelegramAppSubscriptionCodeTest.php)
- [tests/Feature/VlessConnectTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/VlessConnectTest.php)
