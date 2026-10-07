# Telegram Mini-App User Flows

This is the compact product-flow reference for the Telegram mini-app. It is intentionally shorter than the state machine doc and focuses on what users can do.

See also: [docs/telegram-mini-app-state-machine.md](/Users/alexandersustavov/projects/home/wireguard-vpn-app/docs/telegram-mini-app-state-machine.md)

## 1. Entry And Authentication

Telegram entry:

1. User opens the mini-app from Telegram.
2. Frontend sends Telegram WebApp `initData` to `POST /telegram-app/auth/telegram`.
3. Backend validates the hash, creates or links the user, and returns a bearer token.
4. Frontend loads `GET /telegram-app/me`.
5. Default destination is `Home`; `start_param=payments` opens `Payments`.

Public entry:

1. User opens `/telegram-app/login`, `/public/login`, or public-subdomain `/login`.
2. Login uses `POST /telegram-app/auth/login`.
3. Registration uses `POST /telegram-app/auth/register`.
4. Successful auth stores the mini-app bearer token and opens `Home`.

Failure behavior:

- Invalid Telegram hash, expired session, missing Telegram id, login failure, or profile load failure should show a recoverable app error state.
- Protected `/public/*` pages without a token should redirect to public login without Telegram bootstrap.

## 2. Home

Home is the main hub.

Primary actions:

- `Amnezia`: open WireGuard/Amnezia config list.
- `VLESS`: open VLESS menu.
- `Subscription`: open `Payments`.
- `Help`: open help menu.
- `Giveaway`: open current giveaway.
- Bottom navigation: `Home`, `Payments`, `Chats`.

Notes:

- `Chats` is available through bottom navigation, not as a Home card.
- `Support` is reached through `Help`, not bottom navigation.

## 3. Amnezia / WireGuard

Config list flow:

1. User opens `Home -> Amnezia`.
2. Frontend calls `GET /api/users/{telegramId}/wireguard/configs` or mini-app proxy `GET /telegram-app/wireguard/configs`.
3. If configs exist, show the list.
4. If none exist, show the empty state.
5. If backend returns debt/no-access, show the access-denied state and link to `Payments`.

Config actions:

1. User selects a config.
2. User can request QR code through `GET /api/users/{telegramId}/configs/wireguard/{configId}/qr-code`.
3. User can request file download through `GET /api/users/{telegramId}/configs/wireguard/{configId}/download`.
4. User can return to config list, go to `VLESS`, or return Home.

Important:

- If a config disappears between list load and action, show `CONFIG_NOT_FOUND`.
- AmneziaWG URI payload must be decoded back to native `.conf` for QR/download flows.

## 4. VLESS

VLESS menu flow:

1. User opens `Home -> VLESS`.
2. Frontend calls `GET /api/users/{telegramId}/vless/link` or mini-app proxy `GET /telegram-app/vless`.
3. If access is available, show direct app-selection cards for Incy, Happ, and V2RayTun.
4. If no access, show debt/no-access and link to `Payments`.

Actions:

- `Connect`: open the protected app-specific deep link from `GET /api/users/{telegramId}/vless/link`.
- `QR Code`: show the selected app-specific QR from `GET /api/users/{telegramId}/vless/qr-code?target=...`.
- `Legacy`: copy or show the old-format subscription URL for manual/unsupported-app import.
- `Whitelist`: open `/telegram-app/vless-wl?step=links`.
- `Home`: return to Home.

Notes:

- If a deep link does not open, user should be able to copy the raw link or use QR.
- Whitelist QR has backend support, but current UI does not expose a path to it.

## 5. Payments And Subscription

Overview:

1. User opens `Home -> Subscription` or bottom-nav `Payments`.
2. Frontend loads `GET /telegram-app/me`.
3. Screen shows balance, debt, subscription end date, warnings, gift-code entry, and available subscription actions.

Personal subscription purchase:

1. User chooses `Buy subscription`.
2. Frontend loads `GET /telegram-app/subscription-packages`.
3. User chooses a package.
4. Frontend sends `POST /telegram-app/payments/subscriptions` with `{ month, return_url }`.
5. If balance covers the package, backend activates immediately and frontend shows success.
6. If external payment is needed, backend returns `confirmation_url` and frontend opens the payment page.
7. After payment approval, backend activation follows the `TransactionApproved` listener path.

Trial:

- Trial uses the same purchase endpoint with `month=0`.
- Trial is allowed only for users without previous subscriptions.
- Trial must still dispatch config provisioning and access reconciliation.

Gift code purchase:

1. User chooses gift purchase mode.
2. User selects package and pays or uses balance.
3. If paid, backend issues a gift code.
4. User can open their gift-code list from Payments.

Gift code activation:

1. User enters a code.
2. Frontend calls the gift activation endpoint.
3. Backend validates the code, creates subscription period, and runs post-activation work.
4. Frontend shows activation success or validation error.

Do not mix these:

- Buying a gift code creates a code for someone else.
- Activating a gift code creates access for the current user.

## 6. Giveaway

Flow:

1. User opens `Home -> Giveaway`.
2. Frontend loads `GET /telegram-app/giveaway/current`.
3. User joins the giveaway if available.
4. Participant weight starts at `1`.
5. Eligible referrals add `+1`.

Eligibility notes:

- Referral relationship must belong to the participant.
- Referral must be attached during the current giveaway window.
- Referred user must have an active subscription at `giveaway.ends_at`.
- Prize grants create free `user_subscriptions` with source `giveaway`.

## 7. Help And Clients

Help menu actions:

- `Amnezia`: open Amnezia setup instructions.
- `VLESS`: open VLESS setup instructions.
- `Clients`: open client app choices.
- `Support`: open support tickets.
- `Home`: return Home.

Client subflows:

- Amnezia help can open Amnezia clients.
- VLESS help can open VLESS clients.
- Generic clients screen can branch to either Amnezia or VLESS clients.

## 8. Support

Ticket list:

1. User opens `Help -> Support`.
2. Frontend calls `GET /telegram-app/support/tickets`.
3. Empty state offers ticket creation.
4. Non-empty state lists tickets.

Create ticket:

1. User opens composer.
2. User submits subject/message.
3. Frontend calls `POST /telegram-app/support/tickets`.
4. On success, open `SupportShow`.

Ticket thread:

1. User opens a ticket from `Support` or a `ticket_{id}` deep link.
2. Frontend shows message history.
3. User sends a message through `POST /telegram-app/support/tickets/{ticketId}/messages`.
4. Frontend polls every 5 seconds.

Display:

- Admin messages are shown as operator messages.
- User messages are shown as own messages.

## 9. Full Transition List

Main:

- `BOOTSTRAP -> HOME`
- `BOOTSTRAP(start_param=payments) -> PAYMENTS`
- `PUBLIC_LOGIN -> HOME`
- `PUBLIC_REGISTER -> HOME`
- `HOME -> Amnezia -> WIREGUARD(list)`
- `HOME -> VLESS -> VLESS(menu)`
- `HOME -> Subscription -> PAYMENTS`
- `HOME -> Help -> HELP`
- `HOME -> Giveaway -> GIVEAWAY`
- `bottom nav -> Chats -> CHATS`

WireGuard:

- `WIREGUARD(list) -> select config -> WIREGUARD(actions)`
- `WIREGUARD(actions) -> QR Code -> WIREGUARD(qr)`
- `WIREGUARD(actions) -> Send file to bot -> WIREGUARD(file)`
- `WIREGUARD(qr) -> Configs -> WIREGUARD(list)`
- `WIREGUARD(file) -> Configs -> WIREGUARD(list)`
- `WIREGUARD(debt) -> Subscription -> PAYMENTS`
- `WIREGUARD(empty) -> Home -> HOME`

VLESS:

- `VLESS(menu) -> Connect(app) -> external VPN app`
- `VLESS(menu) -> QR Code(app) -> VLESS(qr:app)`
- `VLESS(menu) -> Legacy copy/QR -> VLESS(qr:legacy)`
- `VLESS(menu) -> Whitelist -> VLESS_WL(links)`
- `VLESS(links) -> Back -> VLESS(menu)`
- `VLESS(qr) -> Back -> VLESS(menu)`
- `VLESS(debt) -> Subscription -> PAYMENTS`
- `VLESS_WL(links) -> Back -> VLESS_WL(menu)`
- `VLESS_WL(debt) -> Subscription -> PAYMENTS`

Payments:

- `PAYMENTS(overview) -> Buy subscription -> PAYMENTS(packages:PERSONAL)`
- `PAYMENTS(overview) -> Buy gift code -> PAYMENTS(packages:GIFT)`
- `PAYMENTS(overview) -> Activate code -> PAYMENTS(code-activated)`
- `PAYMENTS(packages) -> Cancel -> PAYMENTS(overview)`
- `PAYMENTS(packages) -> Pay -> PAYMENTS(activated|redirect|error)`
- `PAYMENTS(gift-created) -> My codes -> PAYMENTS(overview)`

Help:

- `HELP(menu) -> Amnezia -> HELP(wg)`
- `HELP(menu) -> VLESS -> HELP(vless)`
- `HELP(menu) -> Clients -> HELP(clients)`
- `HELP(menu) -> Support -> SUPPORT`
- `HELP(wg) -> Amnezia clients -> HELP(wg-clients)`
- `HELP(vless) -> VLESS clients -> HELP(vless-clients)`
- `HELP(clients) -> Amnezia clients -> HELP(wg-clients)`
- `HELP(clients) -> VLESS clients -> HELP(vless-clients)`

Support:

- `SUPPORT(empty) -> Create ticket -> SUPPORT(composer)`
- `SUPPORT(composer) -> Send ticket -> SUPPORT_SHOW`
- `SUPPORT(list) -> open ticket -> SUPPORT_SHOW`
- `SUPPORT_SHOW -> Back to list -> SUPPORT`
- `SUPPORT_SHOW -> Send message -> SUPPORT_SHOW`

## 10. Support Bot Intents

Recommended intents:

- subscription renewal
- payment status
- gift code purchase
- gift code activation
- Amnezia setup
- VLESS setup
- client app choice
- QR/file/link troubleshooting
- no-access or debt screen
- giveaway participation
- support ticket creation
- support ticket status

## 11. Edge Cases

- No active subscription sends Amnezia and VLESS to the no-access/debt state unless free external subscription rules apply.
- Negative balance alone should not block Amnezia, VLESS, or VLESS whitelist according to current docs; verify `User::hasActiveAccess()` before changing this area.
- `Chats` is bottom-nav only.
- `Support` is reachable through Help.
- VLESS whitelist can be available only to some users.
- Payments gift-code purchase and activation are separate flows.
- `SupportShow` can be opened from ticket list or deep link.

## 12. Quick FAQ Mapping

- Renew subscription: `Home -> Subscription`.
- Connect Amnezia: `Home -> Amnezia -> select config -> QR Code` or file delivery.
- Connect VLESS: `Home -> VLESS -> Link`.
- Deep link did not open: `VLESS -> Link -> copy raw link` or `QR Code`.
- Get client app: `Home -> Help -> Clients`.
- Contact support: `Home -> Help -> Support`.
- Find gift code: `Subscription -> My gift codes`.
- Activate code: `Subscription -> Activation code`.
