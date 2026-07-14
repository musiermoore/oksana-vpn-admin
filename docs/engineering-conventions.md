# Engineering Conventions

Актуально по коду на `2026-07-14`.

Документ фиксирует рабочие инженерные правила проекта. Это не список всех исторических исключений, а стандарт для нового и активно изменяемого кода.

## 1. Основной стек и стиль

- Backend: Laravel
- Frontend admin: Inertia + Vue 3
- Значимая доменная логика живёт в `app/Services/*`, `app/Services/Crud/*`, `app/Services/Api/*`
- Для сложных операций используем явные DTO, сервисы, репозитории и ресурсы

## 2. Request pattern

Стандарт для новых request-классов:

- использовать базовый request-класс [DataFormRequest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Http/Requests/DataFormRequest.php)
- не тащить сырые массивы `validated()` дальше по цепочке, если это write/use-case endpoint
- из request получать typed object через `toDto()`

Текущее состояние кода:

- в проекте используется метод `toDto()`
- это и есть проектный стандарт для request -> DTO mapping

Практическое правило:

- новый код должен опираться на `DataFormRequest + toDto()`
- не стоит вводить параллельный стиль `toData()` без отдельной согласованной миграции по проекту

Исключения:

- старые request-классы, которые всё ещё наследуются от `FormRequest`, считаются technical debt
- при существенном изменении таких мест их стоит переводить на `DataFormRequest`

## 3. DTO + Service + Repository + Resource

Предпочтительная цепочка для новых бизнес-сценариев:

1. `Request`
2. `DTO`
3. `Service`
4. `Repository`
5. `Resource`

Роли:

- `Request`: валидация и сбор typed input
- `DTO`: перенос данных между слоями
- `Service`: бизнес-правила и orchestration
- `Repository`: запись/чтение persistence-слоя, который хочется переиспользовать
- `Resource`: публичная форма ответа для UI/API

Как применять:

- контроллеры должны быть тонкими
- нетривиальная логика не должна жить в контроллерах
- повторяющиеся записи/поиски по модели лучше выносить в репозитории
- доменные правила не прятать в `Resource`

Важно:

- в legacy admin-контроллерах встречаются прямые Eloquent query
- для нового кода и для важных доменных участков ориентируемся на сервисный слой

## 4. Строгая типизация

Стандарт для нового и изменяемого PHP-кода:

- добавлять `declare(strict_types=1);`
- указывать типы аргументов и return types
- использовать `private readonly` зависимости в конструкторах там, где это уместно
- предпочитать typed DTO вместо loosely typed arrays

Дополнительно:

- если метод возвращает структурированный массив, документировать shape через phpdoc
- доменные константы выносить в class constants / enum, а не размазывать строками

## 5. Работа с моделями и базой

- источником баланса и денег считаются `transactions`
- подписка и доступ завязаны на `User::hasActiveAccess()`
- сложные изменения нескольких сущностей выполнять в `DB::transaction(...)`
- для повторного использования prefer repository methods вместо копирования query

Особо чувствительные зоны:

- billing
- subscriptions
- config provisioning
- payment approval
- Telegram mini-app auth/session flows

## 6. Ошибки и исключения

- пользовательские бизнес-ошибки возвращать через понятные `DomainException` / `RuntimeException` там, где так уже принято
- низкоуровневые исключения не скрывать без нужды
- если есть риск частичной записи, использовать транзакцию и явно продумывать rollback behavior

## 7. Тесты обязательны

Если меняется поведение, нужно добавлять или обновлять тесты.

Минимальные ожидания:

- на новую бизнес-логику: Feature test
- на command/job/listener flow: Feature test
- на сериализацию ресурса или выдачу подписки: тест на конкретный output

Особенно обязательно покрывать тестами:

- биллинг
- подписки
- включение/отключение конфигов
- webhook/payment approval
- `/connect` и mini-app flows

Ориентиры по стилю тестов:

- [tests/Feature/RenewSubscriptionsCommandTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/RenewSubscriptionsCommandTest.php)
- [tests/Feature/CreateDefaultConfigsForActiveSubscribersCommandTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/CreateDefaultConfigsForActiveSubscribersCommandTest.php)
- [tests/Feature/TelegramAppConnectionRoutesTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/TelegramAppConnectionRoutesTest.php)
- [tests/Feature/VlessConnectTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/VlessConnectTest.php)

## 8. Подписочные и биллинговые правила проекта

При работе с подписками всегда помнить:

- доступ зависит и от подписки, и от баланса
- trial, paid, gift и renewal flow должны оставаться согласованными
- post-activation обычно означает:
  - создать/додиспатчить дефолтные конфиги
  - прогнать reconciliation включения/отключения конфигов
- start date новой подписки не должен уходить в прошлое после паузы

Подробности:

- [docs/subscription-flow.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/subscription-flow.md)

## 9. Работа с mini-app и connect

- mini-app user flow не придумывать по памяти, а сверяться с документацией
- изменения в `Payments`, `WireGuard`, `VLESS`, `Support` почти всегда требуют проверки mini-app API и frontend state flow
- изменения `/connect` и external subscriptions требуют проверки основного и whitelist output

См.:

- [docs/telegram-mini-app-user-flows.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-user-flows.md)
- [docs/telegram-mini-app-state-machine.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-state-machine.md)

## 10. Когда обновлять документацию

Обновлять документацию нужно, если меняется:

- подписочный flow
- billing/access logic
- mini-app screen flow
- инженерный стандарт проекта
- правила для агентов
