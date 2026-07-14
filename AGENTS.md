# AGENTS

## Read First

Перед изменениями в этом проекте агент должен сначала просмотреть:

1. [RULES.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/RULES.md)
2. [PROJECT-DOCUMENTATION.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/PROJECT-DOCUMENTATION.md)
3. [docs/engineering-conventions.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/engineering-conventions.md)

Если задача связана с подпиской, оплатой, доступом, `/connect`, trial, renewal, gift code или mini-app `Payments`, дополнительно обязательно читать:

4. [docs/subscription-flow.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/subscription-flow.md)
5. [docs/telegram-mini-app-user-flows.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-user-flows.md)
6. [docs/telegram-mini-app-state-machine.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-state-machine.md)

## Project Rules For Agents

- Используй `docker compose exec app` для PHP и Composer команд, когда нужен runtime проекта.
- Для поиска по коду предпочитай `rg`.
- Не меняй billing/subscription поведение без проверки всех связанных flow.

## Architecture Rules

- Предпочтительный backend flow: `Request -> DTO/Data -> Service -> Repository -> Resource`.
- Для новых write-endpoints используй базовый request-класс [DataFormRequest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Http/Requests/DataFormRequest.php).
- Из request получай typed object через `toDto()`.
- Контроллеры должны быть тонкими, бизнес-правила выносятся в сервисы.
- Если есть повторяемая или нетривиальная persistence-логика, выноси её в repository.

## PHP Rules

- Для нового и активно изменяемого PHP-кода используй `declare(strict_types=1);`.
- Добавляй typed arguments и return types.
- Предпочитай `private readonly` зависимости в конструкторах.
- Предпочитай DTO вместо массивов, когда данные переходят между слоями.

## Subscription Rules

- Доступ пользователя зависит и от активной подписки, и от неотрицательного баланса.
- Trial, paid, gift и renewal flow должны оставаться согласованными.
- Post-activation behavior обычно включает:
  - provisioning недостающих конфигов
  - reconciliation enable/disable конфигов
- При изменении `/connect` проверяй и основную подписку, и whitelist, и free external subscriptions.

## Testing Rules

- Любое изменение поведения должно сопровождаться тестами или обновлением существующих тестов.
- Для billing/subscription/connect/mini-app сценариев тесты обязательны.
- Если меняется command/job/listener flow, нужен тест на этот orchestration path.

## Documentation Rules

- Если меняется подписочный flow, обновляй [docs/subscription-flow.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/subscription-flow.md).
- Если меняется mini-app сценарий, обновляй [docs/telegram-mini-app-user-flows.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-user-flows.md).
- Если меняются инженерные стандарты, обновляй [docs/engineering-conventions.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/engineering-conventions.md) и при необходимости [RULES.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/RULES.md).
