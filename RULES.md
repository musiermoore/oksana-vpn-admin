# Rules

## Purpose

This file captures project-specific working rules so future development stays consistent.

## Command Rules

- Use `docker compose exec app` for all PHP and Composer commands.
- Use `docker compose exec vite` for Node and npm commands when they need the container environment.
- Use the default `docker compose` files for development, and `docker compose -f docker-compose.prod.yml ...` only for production workflows.
- The `app` service runs Laravel behind FrankenPHP.
- Prefer `rg` for searching in the codebase.

## Backend Rules

- The project uses Laravel 13 with PHP 8.5.
- Keep business logic in services or commands when it spans multiple models.
- Use repositories inside services instead of querying Eloquent models directly.
- Prefer Eloquent relations and existing query patterns used in the project.
- When changing billing logic, verify how `User::syncStoredBalance()` and approved transactions interact.
- Prefer the flow `Request -> DTO/Data -> Service -> Repository -> Resource` for new business endpoints.
- Prefer `DataFormRequest` as the base request for write endpoints.
- Prefer typed request payload mapping via `toDto()`.
- For new and actively changed PHP files, use `declare(strict_types=1);`, typed arguments, and explicit return types.
- Keep controllers thin and move orchestration to services.

## Billing Rules

- `transactions` is the source of money movement.
- `transaction_types` defines the meaning of each transaction.
- Current supported transaction type slugs:
  - `deposit`
  - `subscription`
- Subscription renewals must create a subscription record and a matching approved negative transaction.
- Existing historical transactions should be treated as `deposit`.

## Subscription Rules

- Access depends on both an active subscription and a non-negative balance.
- The command `configs:disable-overdue-debtors` only checks access and enables/disables configs.
- Subscription renewal is handled by a separate command: `subscriptions:renew`.
- Renewal should happen one day before the active subscription ends, only if the user balance is enough.
- Renewal amount must come from the active payment period's `amount`.
- Renewal start date is `current active subscription end_date + 1 day`.
- Renewal end date is `renewal start date + 1 month`.
- Trial, paid, gift-code, and renewal flows must stay behaviorally aligned.
- Post-activation changes should be checked against config provisioning and config re-enable flows.
- When changing `/connect` output, verify both main subscription and whitelist subscription behavior.

## Frontend Rules

- The admin UI uses Inertia + Vue 3.
- Keep forms and tables aligned with existing simple admin patterns.
- When adding new transaction fields, make sure create/edit/list screens stay in sync.

## Documentation Rules

- Update this file when the user gives new workflow or runtime conventions.
- Update `PROJECT-DOCUMENTATION.md` when the domain model or business rules change.
- Update `docs/subscription-flow.md` when subscription, billing, trial, gift, renewal, or `/connect` flows change.
- Update `docs/engineering-conventions.md` when engineering standards change.
- Agents should read `AGENTS.md` first, then the docs it references.

## Testing Rules

- Behavior changes require tests or updates to existing tests.
- Billing, subscriptions, `/connect`, jobs, commands, listeners, and mini-app flows should not change without test coverage.
