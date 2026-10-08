# Telegram Mini-App State Machine

This document describes the target mini-app state machine based on the current Telegram bot logic and current mini-app implementation.

Code status as of `2026-06-28`:

- Implemented mini-app pages: `Home`, `Payments`, `Support`, `SupportShow`.
- Implemented mini-app API: `auth/telegram`, `me`, `subscription-packages`, `payments/subscriptions`, `support/*`, `referrals/claim`.
- Not yet migrated as standalone screens: `Amnezia`, `Amnezia Config Actions`, `VLESS`, `Help`, `Help Amnezia`, `Help VLESS`, `Clients`, `Amnezia Clients`, `VLESS Clients`.
- Original bot logic still lives in `/api/users/{telegramId}/...` routes and should be adapted for mini-app UI.

## 1. State Groups

### Bootstrap And Public Auth

- `BOOTSTRAP`: validate Telegram WebApp `initData`, auto-register/link user, load profile, then go to `HOME`; if `start_param=payments`, go to `SUBSCRIPTION_OVERVIEW`; on failure go to `APP_INIT_ERROR`.
- `PUBLIC_LOGIN`: `/telegram-app/login`, `/public/login`, or public-subdomain `/login`; `POST /telegram-app/auth/login`; success stores bearer token and goes to `HOME`.
- `PUBLIC_REGISTER`: `/telegram-app/register`, `/public/register`, or public-subdomain `/register`; `POST /telegram-app/auth/register`; success stores bearer token and goes to `HOME`; optional referral code/link can be attached.

### Main User Screens

- `HOME`: main menu and bottom navigation.
- `WIREGUARD_CONFIGS`: list Amnezia/WireGuard configs.
- `WIREGUARD_CONFIG_ACTIONS`: selected config actions.
- `WIREGUARD_QR_RESULT`: QR result for selected config.
- `WIREGUARD_FILE_RESULT`: config file result.
- `EMPTY_WIREGUARD_CONFIGS`: empty WireGuard state.
- `VLESS_HOME`: Xray / VLESS app-selection screen.
- `VLESS_LINK_RESULT`: legacy deep link/manual import result (kept for compatibility).
- `VLESS_QR_RESULT`: VLESS QR result.
- `VLESS_WL_LINK_RESULT`: whitelist VLESS links.
- `SUBSCRIPTION_OVERVIEW`: balance, debt, subscription date, and warnings.
- `SUBSCRIPTION_PACKAGE_SELECT`: package selection.
- `SUBSCRIPTION_ACTIVATED`: successful immediate activation.
- `SUBSCRIPTION_PAYMENT_REDIRECT`: external payment URL.
- `PAYMENT_CANCELLED`: payment cancellation state.
- `PAYMENT_ERROR`: package/payment error state.
- `HELP_MENU`, `HELP_WG`, `HELP_VLESS`, `HELP_CLIENTS`, `HELP_WG_CLIENTS`, `HELP_VLESS_CLIENTS`: help and client screens.
- `SUPPORT`, `SUPPORT_COMPOSER`, `SUPPORT_SHOW`: support ticket list, composer, and thread.
- `GIVEAWAY`: current giveaway screen.

### System States

- `APP_INIT_ERROR`: bootstrap/auth/profile failure.
- `ACCESS_DENIED_DEBT`: no access to config output.
- `CONFIG_NOT_FOUND`: selected config no longer exists.
- `UNEXPECTED_ERROR`: generic unrecoverable error.

### Outside Mini-App UI

- `YOOKASSA_PAYMENT`: external payment page.
- Telegram bot file delivery remains available for legacy file flows.

## 2. Transition Table

| From | Action | Effect | API | Success | Error |
| --- | --- | --- | --- | --- | --- |
| `PUBLIC_LOGIN` | Log in | Password login | `POST /telegram-app/auth/login` | `HOME` | `APP_INIT_ERROR` |
| `PUBLIC_LOGIN` | Create account | Open registration | none | `PUBLIC_REGISTER` | none |
| `PUBLIC_REGISTER` | Create account | Public registration | `POST /telegram-app/auth/register` | `HOME` | `APP_INIT_ERROR` |
| `PUBLIC_REGISTER` | Already have account | Return to login | none | `PUBLIC_LOGIN` | none |
| `BOOTSTRAP` | auto | Validate Telegram `initData` | `POST /telegram-app/auth/telegram` | `BOOTSTRAP_PROFILE_LOAD` | `APP_INIT_ERROR` |
| `BOOTSTRAP_PROFILE_LOAD` | auto | Load profile | `GET /telegram-app/me` | `HOME` or `SUBSCRIPTION_OVERVIEW` | `APP_INIT_ERROR` |
| `HOME` | Amnezia | Open config list | `GET /api/users/{telegramId}/wireguard/configs` or `GET /telegram-app/wireguard/configs` | `WIREGUARD_CONFIGS` | `ACCESS_DENIED_DEBT`, empty, generic error |
| `HOME` | VLESS | Open VLESS screen | `GET /api/users/{telegramId}/vless/link` or `GET /telegram-app/vless` | `VLESS_HOME` | `VLESS_ACCESS_ERROR`, `ACCESS_DENIED_DEBT` |
| `HOME` | Subscription | Open subscription overview | `GET /telegram-app/me` | `SUBSCRIPTION_OVERVIEW` | `APP_INIT_ERROR` |
| `HOME` | Help | Open help menu | none | `HELP_MENU` | none |
| `HOME` | Giveaway | Open giveaway | `GET /telegram-app/giveaway/current` | `GIVEAWAY` | `APP_INIT_ERROR` |
| `WIREGUARD_CONFIGS` | auto | Load configs | `GET /api/users/{telegramId}/wireguard/configs` | `WIREGUARD_CONFIGS` | `ACCESS_DENIED_DEBT`, generic error |
| `WIREGUARD_CONFIGS` | Config item | Select config locally | none | `WIREGUARD_CONFIG_ACTIONS` | next action may fail if config disappeared |
| `WIREGUARD_CONFIGS` | Home | Return home | none | `HOME` | none |
| `WIREGUARD_CONFIGS` | auto empty | Show empty state | response `configs: []` | `EMPTY_WIREGUARD_CONFIGS` | none |
| `ACCESS_DENIED_DEBT` | Subscription | Open payments | `GET /telegram-app/me` | `SUBSCRIPTION_OVERVIEW` | `APP_INIT_ERROR` |
| `WIREGUARD_CONFIG_ACTIONS` | QR Code | Get selected config QR | `GET /api/users/{telegramId}/configs/wireguard/{configId}/qr-code` | `WIREGUARD_QR_RESULT` | `CONFIG_NOT_FOUND`, `UNEXPECTED_ERROR` |
| `WIREGUARD_CONFIG_ACTIONS` | File | Download config file | `GET /api/users/{telegramId}/configs/wireguard/{configId}/download` | `WIREGUARD_FILE_RESULT` | `CONFIG_NOT_FOUND`, `UNEXPECTED_ERROR` |
| `WIREGUARD_CONFIG_ACTIONS` | Configs | Return to config list | `GET /api/users/{telegramId}/wireguard/configs` | `WIREGUARD_CONFIGS` | `ACCESS_DENIED_DEBT`, generic error |
| `WIREGUARD_CONFIG_ACTIONS` | VLESS | Open VLESS | `GET /api/users/{telegramId}/vless/link` or `GET /telegram-app/vless` | `VLESS_HOME` | `VLESS_ACCESS_ERROR`, `ACCESS_DENIED_DEBT` |
| `VLESS_HOME` | auto | Check access and load base links | `GET /api/users/{telegramId}/vless/link` | `VLESS_HOME` | `VLESS_ACCESS_ERROR`, `ACCESS_DENIED_DEBT` |
| `VLESS_HOME` | Connect app | Open protected configured `/start?token=...` link | `GET /telegram-app/vless/link` | external VPN app | `VLESS_ACCESS_ERROR`, `ACCESS_DENIED_DEBT` |
| `VLESS_HOME` | QR Code app/legacy | Get selected QR | `GET /api/users/{telegramId}/vless/qr-code?target=...` | `VLESS_QR_RESULT` | `VLESS_ACCESS_ERROR`, `ACCESS_DENIED_DEBT` |
| `VLESS_HOME` | Whitelist | Open whitelist links | local route `/telegram-app/vless-wl?step=links` | `VLESS_WL_LINK_RESULT` | none |
| `SUBSCRIPTION_OVERVIEW` | auto | Load balance, debt, subscription date | `GET /telegram-app/me` | `SUBSCRIPTION_OVERVIEW` | `APP_INIT_ERROR` |
| `SUBSCRIPTION_OVERVIEW` | Buy subscription | Load packages | `GET /telegram-app/subscription-packages` | `SUBSCRIPTION_PACKAGE_SELECT` | `PAYMENT_ERROR` |
| `SUBSCRIPTION_PACKAGE_SELECT` | Select package | Store selected month locally | none | `SUBSCRIPTION_PACKAGE_SELECT` | none |
| `SUBSCRIPTION_PACKAGE_SELECT` | Pay | Create payment request | `POST /telegram-app/payments/subscriptions` | `SUBSCRIPTION_ACTIVATED` or `SUBSCRIPTION_PAYMENT_REDIRECT` | `PAYMENT_ERROR` |
| `SUBSCRIPTION_PACKAGE_SELECT` | Cancel | Return to overview | none | `SUBSCRIPTION_OVERVIEW` | none |
| `SUBSCRIPTION_ACTIVATED` | Home | Refresh profile and return | `GET /telegram-app/me` recommended | `HOME` | `APP_INIT_ERROR` |
| `SUBSCRIPTION_PAYMENT_REDIRECT` | Pay by card/SBP | Open external URL from `confirmation_url` | none | `YOOKASSA_PAYMENT` | `PAYMENT_ERROR` |
| `PAYMENT_CANCELLED` | Home | Return home | none | `HOME` | none |
| `PAYMENT_ERROR` | Retry | Reload packages or retry purchase | `GET /telegram-app/subscription-packages` or `POST /telegram-app/payments/subscriptions` | `SUBSCRIPTION_PACKAGE_SELECT` | repeated error |
| `HELP_MENU` | Amnezia | Open Amnezia help | local content | `HELP_WG` | none |
| `HELP_MENU` | VLESS | Open VLESS help | local content | `HELP_VLESS` | none |
| `HELP_MENU` | Clients | Open client app list | none | `HELP_CLIENTS` | none |
| `HELP_MENU` | Support | Open support list | `GET /telegram-app/support/tickets` | `SUPPORT` | generic error |
| `SUPPORT` | Create ticket | Open composer | none | `SUPPORT_COMPOSER` | none |
| `SUPPORT_COMPOSER` | Send ticket | Create support ticket | `POST /telegram-app/support/tickets` | `SUPPORT_SHOW` | validation/generic error |
| `SUPPORT` | Open ticket | Open thread | `GET /telegram-app/support/tickets/{ticketId}` | `SUPPORT_SHOW` | generic error |
| `SUPPORT_SHOW` | Send message | Append message | `POST /telegram-app/support/tickets/{ticketId}/messages` | `SUPPORT_SHOW` | validation/generic error |

## 3. Access And Debt Rules

- Config screens should treat `403 { type: "debt" }` as `ACCESS_DENIED_DEBT`.
- If the user has no active subscription, Amnezia, VLESS, and whitelist VLESS should not show usable configs except for flows backed by free external subscriptions.
- Negative balance alone should not block Amnezia/VLESS according to current engineering docs, but agent rules also mention non-negative balance as part of access; check `User::hasActiveAccess()` and related tests before changing this.

## 4. Implementation Notes

- Keep Telegram bootstrap, public login, and public registration separate.
- Bottom navigation can open `HOME`, `PAYMENTS`, and `CHATS`; `Support` is reached through `Help`.
- VLESS mini-app client links and QR codes use the configured public `/start` URL directly; legacy encrypted deep-link routes remain available outside the mini-app. Whitelist has backend QR support, but current UI has no path to it.
- `SupportShow` can be reached from the ticket list or a deep link such as `ticket_{id}`.
- Poll support messages every 5 seconds.
