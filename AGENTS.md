# AGENTS

## Read First

Before changing this project, read:

1. [RULES.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/RULES.md)
2. [PROJECT-DOCUMENTATION.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/PROJECT-DOCUMENTATION.md)
3. [docs/engineering-conventions.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/engineering-conventions.md)

For subscription, billing, access, `/connect`, trial, renewal, gift code, or mini-app `Payments` work, also read:

4. [docs/subscription-flow.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/subscription-flow.md)
5. [docs/telegram-mini-app-user-flows.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-user-flows.md)
6. [docs/telegram-mini-app-state-machine.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-state-machine.md)

## Project Rules

- Use `docker compose exec app` for PHP and Composer commands that need the project runtime.
- Prefer `rg` for code search.
- Do not change billing or subscription behavior without checking all related flows.
- Write commit messages in English.
- For documentation translation commits, use one of these messages:
  - `Translate project documentation to english`
  - `docs: Translate project documentation to english`

## Architecture Rules

- Preferred backend flow: `Request -> DTO/Data -> Service -> Repository -> Resource`.
- For new write endpoints, use [DataFormRequest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Http/Requests/DataFormRequest.php).
- Read typed request data through `toDto()`.
- Keep controllers thin; put business rules in services.
- Move repeated or non-trivial persistence logic into repositories.

## PHP Rules

- Use `declare(strict_types=1);` in new and actively changed PHP files.
- Add typed arguments and return types.
- Prefer `private readonly` constructor dependencies.
- Prefer DTOs over arrays when data crosses layers.

## Subscription Rules

- User access depends on an active subscription and a non-negative balance.
- Trial, paid, gift, and renewal flows must stay aligned.
- Post-activation usually includes missing config provisioning and enable/disable reconciliation.
- When changing `/connect`, check main subscription, whitelist, and free external subscriptions.

## Testing Rules

- Behavior changes need tests or updated tests.
- Billing, subscription, connect, mini-app, command, job, and listener flows require coverage.

## Documentation Rules

- Update [docs/subscription-flow.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/subscription-flow.md) when subscription flow changes.
- Update [docs/telegram-mini-app-user-flows.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-user-flows.md) when mini-app scenarios change.
- Update [docs/engineering-conventions.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/engineering-conventions.md) and, when needed, [RULES.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/RULES.md) when engineering standards change.
