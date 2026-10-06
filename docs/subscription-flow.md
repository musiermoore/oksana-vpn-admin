# Subscription Flow

Current for code as of `2026-07-23`.

This document is the compact source of truth for subscription behavior: mini-app purchase, activation, billing transactions, config reconciliation, and `/connect` output.

## 1. Core Rules

- Access is determined by `User::hasActiveAccess()`.
- Active access requires an active subscription and, per current agent rules, a non-negative balance.
- Money movement source: `transactions`.
- Subscription periods: `user_subscriptions`.
- Renewal price source: active `PaymentPeriod`.

Key files:

- [app/Models/User.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Models/User.php)
- [app/Models/UserSubscription.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Models/UserSubscription.php)
- [app/Services/SubscriptionService.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Services/SubscriptionService.php)
- [app/Console/Commands/DisableConfigsOfOverdueDebtorsCommand.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Console/Commands/DisableConfigsOfOverdueDebtorsCommand.php)

## 2. Subscription Entry Points

Main scenarios:

1. Trial subscription.
2. Paid package with immediate activation from balance.
3. Paid package through external payment and approval.
4. Gift code activation.
5. Automatic renewal from balance.
6. Free giveaway prize activation.

## 3. Trial

Flow:

1. Mini-app calls `POST /telegram-app/payments/subscriptions` with `month=0`.
2. [ApiTransactionService](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Services/Api/ApiTransactionService.php) calls `SubscriptionService::activateTrialForUser()`.
3. The app creates an approved `subscription` transaction with `amount=0`.
4. The app creates `user_subscriptions.source=trial`.
5. The app updates `users.subscription_expires_at`.
6. Post-activation explicitly dispatches `DispatchDefaultConfigsForUserJob` and `configs:disable-overdue-debtors {user_id}`.

Rules:

- Trial does not pass through `TransactionApproved`, so post-activation work must run explicitly.
- Trial is available only when the user has no previous subscriptions.

## 4. Paid Immediate Activation

Scenario: the user chooses a paid package and already has enough balance.

Flow:

1. Mini-app calls `POST /telegram-app/payments/subscriptions`.
2. `ApiTransactionService::purchaseSubscription()` builds a quote through `SubscriptionService::buildPurchaseQuote()`.
3. If `deposit_amount <= 0`, `SubscriptionService::activatePackageForUser()` runs.
4. The app creates a subscription period.
5. The app creates an approved negative `subscription` transaction.
6. The app updates `subscription_expires_at`.
7. If `referrer_id` exists, the first qualifying purchase schedules referral rewards: bonus days for the invited user after confirmation delay, and discount percent for the referrer.

Rules:

- New subscription start date must not move into the past.
- Start date is resolved by `SubscriptionService::resolveNextSubscriptionStartDate()`.

## 5. External Payment And Approval

Scenario: the user chooses a paid package and balance is not enough.

Flow:

1. `ApiTransactionService::purchaseSubscription()` creates a pending `deposit` transaction.
2. The app creates an invoice and YooKassa payment.
3. After payment confirmation, the transaction becomes approved.
4. `TransactionCrudService::approve()` dispatches `TransactionApproved`.
5. `ActivateSubscriptionAfterTransactionApproval` activates the package or performs renewal when applicable, schedules qualifying referral rewards, dispatches `DispatchDefaultConfigsForUserJob`, and queues `ReconcileUserAccessStateJob`.
6. `ReconcileUserAccessStateJob` runs `configs:disable-overdue-debtors {user_id}`.
7. Every payment webhook is stored in `payment_webhook_logs` with payload, invoice/transaction link, and final processing status.
8. Saved webhooks can be replayed through the internal API.

Key files:

- [app/Services/Crud/TransactionCrudService.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Services/Crud/TransactionCrudService.php)
- [app/Events/TransactionApproved.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Events/TransactionApproved.php)
- [app/Listeners/ActivateSubscriptionAfterTransactionApproval.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Listeners/ActivateSubscriptionAfterTransactionApproval.php)

## 6. Gift Codes

Purchase flow:

1. User buys a gift package.
2. If balance is enough, the code is issued immediately.
3. Otherwise the app creates a pending deposit and uses the normal approval flow.

Activation flow:

1. Mini-app accepts a code.
2. `SubscriptionCodeService::activateForUser()` validates it.
3. `SubscriptionService::activateGiftCodeForUser()` creates the subscription.
4. If `referrer_id` exists, first qualifying activation can schedule referral rewards.
5. If access is active after activation, `DispatchDefaultConfigsForUserJob` is dispatched.

Rules:

- Gift activation creates configs.
- Referral logic depends on the invited user activating a code, not on who paid for it.
- Keep trial, paid, and gift post-activation behavior aligned.

## 7. Renewal

Flow:

1. Scheduler runs `RenewSubscriptionsCommand`.
2. `SubscriptionService::renewEligibleSubscriptions()` iterates users.
3. `renewOrCreateSubscription()` checks active `PaymentPeriod`, renewal window, and balance.
4. If eligible, the app creates the next subscription period.
5. The app creates an approved negative `subscription` transaction.

Rules:

- Renewal does not directly enable/disable configs.
- Config access is reconciled by a separate command/job.
- Expiry reminders are sent by `subscriptions:send-expiry-reminders`.
- Reminder anti-spam is stored in `subscription_expiry_notifications` and tied to a specific `user_subscriptions` row.
- Reminder windows: 3 calendar days, 2 calendar days, 1 calendar day, and 6 hours before the end of `end_date`.

## 8. Giveaway Prize

Flow:

1. `GiveawayDrawService` stores winner and prize slot.
2. `GiveawayGrantService` calls `SubscriptionService::grantGiveawayMonths()`.
3. The app creates a free `user_subscriptions` row with `source=giveaway`.
4. No negative billing transaction is created.
5. `users.subscription_expires_at` is synced by `syncUserSubscriptionExpiry()`.

Rules:

- Prize grants do not use standard payment flow.
- Prize grants must not change renewal or billing semantics.
- If the user already has an active or future subscription, the prize is appended after the latest period.

## 9. Config Provisioning And Reconciliation

`configs:disable-overdue-debtors`:

- disables WireGuard and VLESS configs for users without access
- re-enables configs when users regain access
- runs every 5 minutes
- also runs through queued `ReconcileUserAccessStateJob` after paid/trial/gift activation

`DispatchDefaultConfigsForUserJob` creates missing default configs.

Correct reactivation usually needs both steps: create missing configs, then reconcile enabled/disabled state.

## 10. `/connect` And Whitelist Output

Main `/connect`:

- Built by [UserSubscriptionService](/Users/alexandersustavov/projects/home/wireguard-vpn-app/app/Services/Subscriptions/UserSubscriptionService.php).
- Successful requests update `user_connected_devices` by user, `User-Agent`, and route `connect`.
- `skip_connection=true` disables device tracking for admin requests.
- Output includes regular user VLESS nodes and external subscriptions with `include_in_main_subscription`.
- Ordering is explicit: `servers.sort_order`, `vless_external_subscriptions.sort_order`, `xray_inbounds.sort_order`, and `proxies.sort_order`.
- `proxies.server_id` makes proxy variants part of one server. `hide_main_node_name` makes the proxy display name stand alone.
- Soft-deleted servers are excluded.
- INCY requests receive Telegram links through `Support-Url`, `Profile-Web-Page-Url`, and `Announce-Url`: bot, news channel, and community chat.

`/connect-v2`:

- Resolves the user from the plain `users.uuid` query token.
- Lowercases `User-Agent` and redirects Incy, Happ, and V2RayTun clients to their matching deep-link scheme.
- Allows Postman only for admin users and redirects it to the normal `/connect` subscription URL.
- Returns `403` for unsupported clients or non-admin Postman requests.
- Request tracking stores the matched user, user agent, and query parameters so subscription-link scans can be audited.

WireGuard/AmneziaWG output:

- URI output normalizes links before serialization.
- The app reads both legacy-encoded and raw `wireguard://...`.
- URI subscription output keeps WireGuard links raw so keys containing `/` survive client import.
- 3x-ui `amneziawg` inbounds are stored as Xray-backed configs with `protocol=amneziawg`.
- `/connect` emits AmneziaWG as `amneziawg://{base64url-conf}` where payload is native `.conf`.
- Mini-app download/QR flows decode that URI back to native `.conf`.

JSON output:

- `/connect-json` returns full Xray-style JSON configs, one object per node.
- Objects include per-node `remarks` and `outbounds`; shared `dns`, `routing`, and `inbounds` come from app config.
- Routing rules live in `xray_routings` with `outbound=direct|proxy|blocked`, JSON `rules`, JSON `subscription_types`, order, active state, and target arrays.
- `/xray-routings` imports RoscomVPN/INCY JSON into `xray_json_settings` and generated Direct/Proxy/Block rules into `xray_routings`.
- Empty `xray_inbound_ids` and `external_subscription_config_ids` mean a rule applies nowhere.
- JSON builder filters rules per local inbound or external config.
- Imported geodata URLs are validated and cached in `storage/app/xray-geodata`; unchanged `LastUpdated` reuses cache, changed `LastUpdated` downloads again.
- Geodata replacement is atomic; failed downloads keep old active rules.
- Laravel does not run Xray Core locally. JSON output includes Xray `geodata.assets` so the client downloads `geoip.dat` and `geosite.dat`.
- `UseChunkFiles` is kept as import metadata only.
- If no active routing rules exist for a subscription type, builder falls back to `config/connect_json.php`.
- External JSON profiles are preserved for `/connect-json` and `format=json` to keep upstream routing, balancers, and profile-level settings; `remarks` is replaced with the calculated node name.
- Empty `tcpSettings: []` and HTTP inbound `settings: []` are normalized to objects for Xray compatibility.

Custom Xray JSON configurations:

- `xray_custom_configs` stores reusable JSON profile definitions and builds the final profile per user request.
- A custom profile can select local Xray inbounds, external subscription configs, proxies, and explicit `xray_routings`.
- Selected local and external nodes are still filtered through the requesting user's active/visible configs; the profile does not grant access to another user's configs.
- Custom profiles may select reusable global DNS settings and geodata assets and may provide additional base Xray settings such as policy, inbounds, and balancers.
- Custom profiles may define ordered outbound groups. Each group can select its own local inbounds, external configs, or proxies and is emitted as an Xray balancer with `roundRobin`, `leastPing`, `leastLoad`, or `random` strategy.
- A group can fall back to another group; the fallback group may itself contain several nodes, so ordered fallback chains and multi-node fallback pools are supported through balancers and loopback outbounds.
- Custom routes can send domains, IPs, ports, or networks to `direct`, `block`, a specific outbound, or a named group balancer. A final catch-all route sends unmatched traffic to `direct`.
- `leastPing` and `leastLoad` require the corresponding Xray observatory configuration in the custom profile's base settings; otherwise use `roundRobin` or `random`.
- Admin preview supports all available nodes or a selected user and uses the same user-specific builder.
- Custom profiles are available through `connect-custom/{slug}` with the same encrypted `tg`/`i` credentials as `/connect-json`.

Global Xray resources:

- `xray_routing_geodata` stores reusable external `geoip.dat`/`geosite.dat` URLs and cached assets.
- `xray_routing_dns_settings` stores reusable DNS server lists, query strategy, and parallel-query preference.
- Routing rules can target proxies through `xray_routings.proxy_ids` in addition to local inbounds and external subscription configs.

Whitelist `/connect-wl-version-2`:

- Uses `VlessExternalSubscriptionAccessService`.
- Tracks connected devices with route `connect-wl`, unless `skip_connection=true`.
- Includes external subscriptions with `include_in_whitelist`.
- Defaults to `format=json`; URI/base64 output is explicit through `format=uri`, `format=links`, or `format=raw`.
- External source can be `direct` or `incy`.
- For `incy`, backend follows an HTTP redirect to `incy://...` or decodes a direct `incy://...`, then continues normal sync through the decrypted subscription/direct URL.

Expired subscriptions:

- `/connect` and whitelist output include the placeholder `Your subscription has expired`.
- Free external subscriptions with `is_free` can still be returned.

## 11. External VLESS Subscriptions

Entities:

- `vless_external_subscriptions`
- `vless_external_subscription_configs`

Key flags:

- `include_in_main_subscription`: include configs in `/connect`
- `include_in_whitelist`: include configs in whitelist output
- `is_free`: allow configs without active subscription
- `is_active`: source is active
- `is_ready`: source finished sync and can be used
- `sort_order`: external group position among local servers in `/connect`

Sync rules:

- Scheduled and manual sync dispatch `SyncVlessExternalSubscriptionJob` through `Bus::dispatch(new SyncVlessExternalSubscriptionJob(...))`.
- External fetch sends INCY-like client headers so upstream sees a normal client pull.
- JSON sources store the original profile in `vless_external_subscription_configs.json` next to the normalized URI link.

Naming:

- One config: use `connect_name_prefix` as-is.
- Multiple configs: use `Prefix 1`, `Prefix 2`, and so on.

## 12. Mini-App Screens Related To Subscription

Detailed UI flows:

- [docs/telegram-mini-app-user-flows.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-user-flows.md)
- [docs/telegram-mini-app-state-machine.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-state-machine.md)

Important screens:

- `Payments`
- `WireGuard`
- `VLESS`
- `VLESS White List`
- `Home`
- `Giveaway`

## 13. Required Checks For Changes

For subscription changes, usually check:

1. Trial flow.
2. Paid immediate activation flow.
3. Paid approval flow.
4. Gift activation flow.
5. Renewal flow.
6. `DispatchDefaultConfigsForUserJob`.
7. `configs:disable-overdue-debtors`.
8. `/connect` and `/connect-wl-version-2`.

## 14. Expected Tests

Minimum:

- Feature test for activation API scenarios.
- Feature test for command/job paths when post-activation behavior changes.
- `/connect` output test when subscription output changes.

Useful examples:

- [tests/Feature/TelegramAppConnectionRoutesTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/TelegramAppConnectionRoutesTest.php)
- [tests/Feature/CreateDefaultConfigsForActiveSubscribersCommandTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/CreateDefaultConfigsForActiveSubscribersCommandTest.php)
- [tests/Feature/RenewSubscriptionsCommandTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/RenewSubscriptionsCommandTest.php)
- [tests/Feature/TelegramAppSubscriptionCodeTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/TelegramAppSubscriptionCodeTest.php)
- [tests/Feature/VlessConnectTest.php](/Users/alexandersustavov/projects/home/wireguard-vpn-app/tests/Feature/VlessConnectTest.php)
