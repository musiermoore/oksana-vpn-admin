# Project Documentation

## Overview

This project is an internal VPN management panel for Oksana VPN.

It manages:

- users
- balances
- transactions
- subscription periods
- giveaway campaigns
- WireGuard configs
- VLESS configs
- Telegram-driven payment flows

## Tech Stack

- Laravel 11
- PHP 8.2
- Inertia.js
- Vue 3
- Vite
- Laravel Sail / Docker Compose
- Telegram Bot SDK

## Main Business Entities

### Users

Users have:

- Telegram identity
- profile information
- active/inactive state
- stored balance
- configs
- transactions
- subscriptions
- extra payments

### Transactions

Transactions represent balance movements.

Key fields:

- `user_id`
- `type_id`
- `amount`
- `is_approved`
- `description`

Transaction types:

- `deposit` for balance top-ups
- `subscription` for subscription charges

Important note:

- deposits are positive amounts
- subscription charges are negative amounts
- only approved transactions affect stored balance

### Transaction Types

`transaction_types` is a reference table.

Seeded values:

- `deposit` -> `Пополнение`
- `subscription` -> `Подписка`

### Payment Periods

Payment periods are stored in the `current_payments` table via the `PaymentPeriod` model.

Each payment period has:

- `start_date`
- `end_date`
- `amount`

The active payment period is used as the source of truth for subscription renewal price.

### Xray Inbounds

Xray/3x-ui inbound identifiers are normalized through the `xray_inbounds` table.

Each inbound record has:

- `server_id`
- `external_id`
- `is_active`
- `is_public`
- `params`

`external_id` is the inbound id from 3x-ui.

`params` stores the raw inbound payload from 3x-ui so app logic does not depend on a rigid local schema for inbound settings.

`sort_order` stores the explicit `/connect` order of inbounds inside a server.

Deleted or broken 3x-ui inbounds with empty payloads should not stay active locally.

### Proxies

Proxy nodes are stored in `proxies`.

Current connect behavior assumes one proxy belongs to one server through `server_id`.

Important fields:

- `server_id`
- `xray_inbound_id`
- `hide_main_node_name`
- `sort_order`

`sort_order` stores the explicit position of the proxy in the mixed per-server `/connect` list alongside Xray inbounds.

`hide_main_node_name` lets a proxy replace the original server label in `/connect`, so a proxy variant can be shown as just its own name instead of `Server (Proxy Name)`.

### Server Prices

Server cost history is stored in `server_prices`.

Each price row has:

- `server_id`
- `effective_from`
- `price`

This keeps price history editable from the admin UI without replacing a whole JSON blob.

### Subscription Output

The main `/connect` subscription is built from normalized local and external nodes.

External VLESS subscriptions support multiple source formats:

- `direct` for regular links and subscription URLs
- `incy` for sources that start as `incy://...` or as an `https://...` page that redirects to `incy://...`

Its order is explicit rather than inferred from ids:

- `servers.sort_order` controls local server order
- `vless_external_subscriptions.sort_order` controls where external groups appear among local servers
- `xray_inbounds.sort_order` and `proxies.sort_order` control order inside one server
- soft-deleted servers are excluded from `/connect` by default because `servers` now use Eloquent soft delete

Important WireGuard rule:

- WireGuard URIs are normalized before output
- the app accepts both raw and legacy-encoded `wireguard://...` values during parsing
- URI subscription output keeps WireGuard keys raw so clients that import subscriptions do not break on secret keys containing `/`
- the same normalization is applied even for older records that already store a `wireguard://...` string in `vless_configs.extra`

### User Subscriptions

Subscriptions are stored in `user_subscriptions`.

Each subscription has:

- `user_id`
- `start_date`
- `end_date`
- `price`

The active subscription is the subscription covering today.
The latest subscription is the one with the greatest `end_date`.

### Giveaways

Giveaway campaigns are stored in:

- `giveaway_series`
- `giveaways`
- `giveaway_prizes`
- `giveaway_participants`
- `giveaway_winners`

Current rules:

- participation is explicit and starts only after pressing `Участвовать`
- base participant weight is `1`
- each eligible referral adds `+1`
- a referral is eligible only when:
  - the referral relationship belongs to the participant
  - the referral was attached during the current giveaway window
  - the referred user has an active subscription at `giveaway.ends_at`
- historical draw data is frozen through participant and winner snapshots
- one user can win at most one prize per giveaway
- prize grants create free `user_subscriptions` with source `giveaway`
- auto-repeat creates a brand new giveaway instance in the same series instead of mutating the old row

## Billing Model

The stored user balance is synchronized from:

- approved transactions
- extra payments
- legacy manual extra payment field on users

Current subscription spending is represented through approved negative `subscription` transactions.

This means subscription renewal should:

1. create the next subscription
2. create the matching approved negative transaction
3. let the normal balance sync recalculate the stored balance

## Access Model

A user has active access only when both conditions are true:

- the user has an active subscription
- the user does not have debt

Configs should be disabled when the user:

- has negative balance, or
- has no active subscription

Configs can be enabled when the user:

- has non-negative balance, and
- has an active subscription

## Commands

### `configs:disable-overdue-debtors`

Responsibility:

- sync balances
- check access state
- disable configs for overdue users
- enable configs for users who regained access

This command should not renew subscriptions.

### `subscriptions:renew`

Responsibility:

- sync balances
- find users eligible for renewal
- renew one day before the active subscription end date
- use the active payment period amount
- create the next subscription window
- create the approved negative subscription transaction
- sync balances again

### `vless-external-subscriptions:sync`

Responsibility:

- find active external VLESS subscriptions
- queue a sync job per active subscription
- use explicit bus dispatch for the sync job path so command and admin-triggered sync behave consistently

## Payment Flow

Current deposit flow:

1. user sends a top-up request
2. a pending `deposit` transaction is created
3. admins approve or decline it
4. once approved, the transaction affects balance

Current subscription flow:

1. user has an active subscription
2. one day before `end_date`, renewal is checked
3. if balance is sufficient, a new subscription is created
4. a negative approved `subscription` transaction is saved
5. balance is recalculated

Detailed subscription and activation scenarios are documented in:

- `docs/subscription-flow.md`
- `docs/telegram-mini-app-user-flows.md`

## Frontend Areas

Important admin screens include:

- users
- transactions
- configs
- extra payments
- current payments
- giveaways

Transactions in the admin UI should expose:

- amount
- type
- description
- approval status
- related user

## Maintenance Notes

- When changing billing behavior, check `app/Models/User.php` and `app/Services/SubscriptionService.php`.
- When changing transaction fields, update both API and admin controllers plus Vue pages.
- When changing scheduled behavior, update `routes/console.php`.
- When changing `/connect` output, verify WireGuard URI raw output and JSON builders together.
- Servers are soft-deleted, so include deleted rows only intentionally via `withTrashed()` / `onlyTrashed()`.
