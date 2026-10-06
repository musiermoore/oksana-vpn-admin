# Engineering Conventions

Current for code as of `2026-07-14`.

This document defines standards for new and actively changed code. It is not a catalog of every legacy exception.

## 1. Stack And Style

- Backend: Laravel.
- Admin frontend: Inertia + Vue 3.
- Important domain logic belongs in `app/Services/*`, `app/Services/Crud/*`, and `app/Services/Api/*`.
- Use explicit DTOs, services, repositories, and resources for complex operations.

## 2. Request Pattern

Standard for new request classes:

- Use [DataFormRequest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Http/Requests/DataFormRequest.php).
- Do not pass raw `validated()` arrays deeper into write/use-case endpoints.
- Convert request input to a typed object through the established request API: preserve `toDto()` in existing callers and use `toData()` for new or migrated code.
- Expose the DTO class through the request's `laravelData()` hook. The shared `DataFormRequest` base performs the validated-payload mapping and calls the Laravel Data class.

Project state:

- Both `toDto()` and `toData()` map validated request input to the configured Laravel Data object.
- Preserve the established method in existing controllers; use `toData()` for new or migrated code.
- Legacy `FormRequest` classes should be moved to `DataFormRequest` and assigned a typed Laravel Data DTO when they are changed.

Example:

```php
final class StoreExampleRequest extends DataFormRequest
{
    protected function laravelData(): string
    {
        return ExampleData::class;
    }
}

$data = $request->toData();
```

`laravelData()` is the protected DTO-class hook used by the base request.

## 2.1 Laravel Data naming

Application DTOs extend [app/DTOs/Data.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/DTOs/Data.php).
The shared Laravel Data configuration uses `SnakeCaseMapper` for both input and output:

- Request fields such as `server_id` are mapped automatically to DTO properties such as `$serverId`.
- DTO serialization maps camelCase properties back to snake_case keys.
- Do not add per-property name mapping attributes for ordinary snake_case fields.
- Keep explicit conversion methods such as `toModelAttributes()` only when a persistence-specific shape is required.

Small domain value objects that do not extend `App\DTOs\Data` are not request DTOs and do not need this mapper.

Exceptions:

- Older `FormRequest` classes are technical debt.
- When materially changing such code, migrate it to `DataFormRequest` where practical.

## 3. DTO + Service + Repository + Resource

Preferred chain for new business scenarios:

1. `Request`
2. `DTO`
3. `Service`
4. `Repository`
5. `Resource`

Responsibilities:

- `Request`: validation and typed input collection.
- `DTO`: data transfer between layers.
- `Service`: business rules and orchestration.
- `Repository`: reusable persistence reads/writes.
- `Resource`: public UI/API response shape.

Rules:

- Keep controllers thin.
- Keep non-trivial logic out of controllers.
- Move repeated model reads/writes into repositories.
- Do not hide domain rules in `Resource` classes.
- Legacy admin controllers may query Eloquent directly; new and important domain code should use services.

## 4. Strict Typing

For new and changed PHP code:

- Add `declare(strict_types=1);`.
- Use typed arguments and return types.
- Use `private readonly` constructor dependencies where appropriate.
- Prefer typed DTOs over loosely typed arrays.
- Document structured array shapes with phpdoc.
- Put domain constants in class constants or enums instead of repeated strings.

## 5. Models And Database

- `transactions` are the source of balance and money movement.
- Subscription access is tied to `User::hasActiveAccess()`.
- Wrap multi-entity writes in `DB::transaction(...)`.
- Prefer reusable repository methods over copied queries.

Sensitive areas:

- billing
- subscriptions
- config provisioning
- payment approval
- Telegram mini-app auth/session flows

## 6. Errors And Exceptions

- Return user-facing business errors through clear `DomainException` / `RuntimeException` patterns where the project already does so.
- Do not hide low-level exceptions without a reason.
- If partial writes are possible, use transactions and think through rollback behavior.

## 7. Tests

Behavior changes require new or updated tests.

Minimum expectations:

- New business logic: Feature test.
- Command/job/listener flow: Feature test.
- Resource serialization or subscription output: exact output test.

Mandatory coverage areas:

- billing
- subscriptions
- config enable/disable
- webhook/payment approval
- `/connect` and mini-app flows

Useful examples:

- [tests/Feature/RenewSubscriptionsCommandTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/RenewSubscriptionsCommandTest.php)
- [tests/Feature/CreateDefaultConfigsForActiveSubscribersCommandTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/CreateDefaultConfigsForActiveSubscribersCommandTest.php)
- [tests/Feature/TelegramAppConnectionRoutesTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/TelegramAppConnectionRoutesTest.php)
- [tests/Feature/VlessConnectTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/VlessConnectTest.php)

## 8. Subscription And Billing Rules

When working with subscriptions:

- Access depends on an active subscription; negative balance alone does not disable access.
- Trial, paid, gift, and renewal flows must stay aligned.
- Post-activation usually means creating/dispatching default configs and running enable/disable reconciliation.
- New subscription start dates must not move into the past after a pause.

Details: [docs/subscription-flow.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/subscription-flow.md)

## 9. Mini-App And Connect

- Do not reconstruct mini-app user flow from memory; check the docs.
- Changes in `Payments`, `WireGuard`, `VLESS`, and `Support` usually require checking mini-app API and frontend state flow.
- `/connect` and external subscription changes require checking main and whitelist output.

See:

- [docs/telegram-mini-app-user-flows.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-user-flows.md)
- [docs/telegram-mini-app-state-machine.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-state-machine.md)

## 10. When To Update Docs

Update docs when these change:

- subscription flow
- billing/access logic
- mini-app screen flow
- engineering standards
- agent rules
