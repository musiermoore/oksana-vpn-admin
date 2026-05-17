# Rules

## Purpose

This file captures project-specific working rules so future development stays consistent.

## Command Rules

- Use `docker compose exec app` for all PHP and Composer commands.
- Use `docker compose exec vite` for Node and npm commands when they need the container environment.
- Use the default `docker compose` files for development, and `docker compose -f docker-compose.prod.yml ...` only for production workflows.
- Prefer `rg` for searching in the codebase.

## Backend Rules

- The project uses Laravel 13 with PHP 8.3.
- Keep business logic in services or commands when it spans multiple models.
- Use repositories inside services instead of querying Eloquent models directly.
- Prefer Eloquent relations and existing query patterns used in the project.
- When changing billing logic, verify how `User::syncStoredBalance()` and approved transactions interact.

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

## Frontend Rules

- The admin UI uses Inertia + Vue 3.
- Keep forms and tables aligned with existing simple admin patterns.
- When adding new transaction fields, make sure create/edit/list screens stay in sync.

## Documentation Rules

- Update this file when the user gives new workflow or runtime conventions.
- Update `PROJECT-DOCUMENTATION.md` when the domain model or business rules change.
