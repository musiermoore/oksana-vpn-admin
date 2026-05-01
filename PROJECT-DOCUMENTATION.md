# Project Documentation

## Overview

This project is an internal VPN management panel for Oksana VPN.

It manages:

- users
- balances
- transactions
- subscription periods
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

### User Subscriptions

Subscriptions are stored in `user_subscriptions`.

Each subscription has:

- `user_id`
- `start_date`
- `end_date`
- `price`

The active subscription is the subscription covering today.
The latest subscription is the one with the greatest `end_date`.

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

## Frontend Areas

Important admin screens include:

- users
- transactions
- configs
- extra payments
- current payments

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
