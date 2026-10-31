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

### Non-regular payments

Non-regular payments are standalone expense records for costs such as changing a server IP. Each record stores its id, amount, description, and timestamps. Reports include records created inside the selected period as expenses.

Reports also split payment costs into a 4% estimated tax and a 3.5% cash-register fee, both calculated from paid invoice revenue.

### Transaction Types

`transaction_types` is a reference table.

Seeded values:

- `deposit` -> `Top-up`
- `subscription` -> `Subscription`

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
- `is_visible_in_connect`
- `is_visible_in_connect_v2`
- `params`

`external_id` is the inbound id from 3x-ui.

`params` stores the inbound definition from 3x-ui without duplicated client lists, top-level `configs`, or `clientStats` payloads. Traffic summaries use live panel responses, so those large lists are not duplicated in the local snapshot.

`sort_order` stores the explicit `/connect` order of inbounds inside a server.

`is_visible_in_connect` and `is_visible_in_connect_v2` independently control whether an inbound appears in the matching subscription output. Both default to `true`. Amnezia mini-app config lists require the owning server and inbound to be active; subscription visibility flags do not affect those lists.

Deleted or broken 3x-ui inbounds with empty payloads are soft-deleted locally so they no longer appear on the server edit page. If a deleted inbound reappears in a later panel sync, its local record is restored.

### Xray Inbound Health Checks

Active inbounds on active, ready servers are checked every 15 minutes while checks are enabled. The `/xray-inbound-health` dashboard can pause/resume scheduled and manually queued checks; pausing preserves existing results. The first check creates one dedicated `health-check-*` client in the 3x-ui panel for that inbound; later checks reuse it. These direct inbound probes bypass app routing rules and test Discord, Telegram, Google, ChatGPT, and YouTube through the matching local client core. Xray-backed VLESS, Trojan, and Shadowsocks use Xray; Hysteria variants use sing-box. Expected probe/configuration errors are stored in health records and are not sent to the application exception reporter. Direct state, per-site outcomes, and technical client identity are stored in `xray_inbound_health_checks`.

The same scheduled run builds a marked, inactive technical user (`@OksanaVpnDebugBot`, Telegram id `000000000`) with managed local configs and no billing subscription record. Direct checks provision that user's client config for each active inbound before checking its tunnel, including tunnel types currently marked unsupported, so subscription output can still include and report those configs. It tests every eligible `/connect` config, every profile emitted by `/connect-v2` (including custom profiles and their routing), external subscription configs, and active Xray custom subscriptions through their generated Xray profiles. `/connect` and `/connect-v2` respect their normal active, public, enabled, and per-flow visibility filters. Stored external JSON receives configured global geodata assets when it does not supply its own; the probe downloads assets embedded in external profiles and selected custom geodata too, caching their hashes. When a profile references `geoip:` or `geosite:` data without declaring asset URLs, the probe uses the latest active `xray_routing_geodata` asset for local validation; this fallback affects health probes only, not generated subscriptions. Xray validation and runtime use app-managed `storage/app/xray-geodata` only when every geodata file referenced anywhere in the profile, including DNS rules, is present and matches its recorded hash; otherwise Xray uses its normal asset lookup. Direct inbound rows show whether their technical client is included in each subscription flow, and flow profile names include the inbound number for identification. UDP-only profiles are explicitly unsupported by the TCP destination probe. Per-profile states and per-site outcomes are stored in `subscription_profile_health_checks`; a compact Telegram health summary is sent every 30 minutes for results checked in the previous 30 minutes. The dashboard can also queue a Telegram summary immediately using the latest saved results. Recovery notifications are not sent. Health results never change subscription visibility. The dashboard also shows these profile results plus the count of active global JSON settings and the latest active setting used by generated profiles. Older active global JSON settings are not combined. Full WireGuard/AmneziaWG tunnel probing is not currently supported by the runner.

For direct inbound and subscription profile destination probes, all destinations reachable means healthy, some reachable means warning, and none reachable means unhealthy.

### Proxies

Proxy nodes are stored in `proxies`.

Current connect behavior assumes one proxy belongs to one final destination server through `server_id`. Proxy host endpoints are stored in ordered `proxy_hosts` rows. The first active host by `sort` is the user-facing VLESS entrypoint. Every later active row describes a cascade hop and must select the server where HAProxy is configured with `proxy_server_id` before the group can be previewed or deployed. `proxy_hosts.is_https` controls TLS for that hop; `proxies.is_https` applies only to the final destination.

`proxy_groups` groups related proxies for shared cascade configuration. The proxy group admin page previews each proxy's complete route, systemd HAProxy override, and generated frontend/backend files before an administrator confirms deployment. The deployment checks the frontend and backend templates for `{TEXT}`, checks the effective `haproxy` systemd `ExecStart`, backs up active config files, renders the group config, validates it with `haproxy -c`, restarts HAProxy, and checks for `Active: active (running)`. A failure restores backups and restarts the affected servers. Backups are removed only after all group servers restart successfully.

On every configured proxy server, retain `/etc/haproxy/frontends/frontend.cfg.template` and `/etc/haproxy/backends/backend.cfg.template`, each with a `{TEXT}` insertion point. `systemctl edit haproxy` must configure the service with:

```ini
[Service]
ExecStart=
ExecStart=/usr/sbin/haproxy -Ws \
    -f /etc/haproxy/haproxy.cfg \
    -f /etc/haproxy/frontends/frontend.cfg \
    -f /etc/haproxy/backends/backend.cfg \
    -p /run/haproxy.pid
```

Proxy `is_ready` controls availability to subscribers: non-admin subscription output filters out proxies that are not ready. Admin subscription output can still include them. It does not hide the proxy from the admin UI.

Important fields:

- `server_id`
- `proxy_group_id` (nullable)
- `xray_inbound_id`
- `hide_main_node_name`
- `sort_order`
- `proxy_hosts` stores each proxy's host, port, cascade `sort`, active flag, per-hop HTTPS setting, and optional HAProxy configuration server

`sort_order` stores the explicit position of the proxy in the mixed per-server `/connect` list alongside Xray inbounds.

`hide_main_node_name` lets a proxy replace the original server label in `/connect`, so a proxy variant can be shown as just its own name instead of `Server (Proxy Name)`.

### Server Prices

Server cost history is stored in `server_prices`.

Each price row has:

- `server_id`
- `effective_from`
- `price`

This keeps price history editable from the admin UI without replacing a whole JSON blob.

### Server Command Templates

Reusable server command templates are stored in `server_command_templates` and managed from the admin panel. Each template has a name, optional description, execution timeout, and one of two content formats:

- `script` stores a raw shell script.
- `blocks` stores JSON command blocks. A block can include a command, an optional timeout, and prompt rules with a scan pattern, answer text, and send mode.

The project uses Laravel Reverb for WebSocket transport. In Docker, the app's Caddy listener proxies `/app/*` WebSocket connections to the Reverb service; Laravel broadcasts to Reverb on the internal Compose network.

Each server has an SSH username, port, and optional encrypted password in addition to its private/public keys. The `/servers/{id}/console` admin page opens an xterm.js PTY session over phpseclib; terminal input, output, control keys, and resize events use authenticated HTTP endpoints and a user-authorized private Reverb output channel. A dedicated Redis queue worker (or Horizon supervisor in production) owns each connection for up to 15 minutes. A session accepts one queued command at a time, detects shell completion from its prompt marker, interrupts commands at their timeout, and can run saved script or block templates. Template prompt rules send their configured answer once the matching scan text appears in terminal output. Raw scripts also accept `# [{ANSWER: "y", SCAN: "y/n"}]` lines to register a prompt answer.

### Subscription Output

The main `/connect` subscription is built from normalized local and external nodes.

`/connect` and `/connect-v2` report upload and download usage in `Subscription-Userinfo` for the current monthly cycle of the active subscription. Each cycle starts on the subscription's `start_date` anniversary and ends at the next anniversary (exclusive); counter decreases are treated as panel counter resets. Usage deltas from all servers are added to one `user_daily_traffic` row per user and date. Daily totals older than 90 days are deleted. Users without an active subscription report zero usage. The reported total remains the user's configured traffic limit.

External VLESS subscriptions support multiple source formats:

- `direct` for regular links and subscription URLs
- `incy` for sources that start as `incy://...` or as an `https://...` page that redirects to `incy://...`
- JSON subscription sources keep the original profile payload on each external config so `/connect-json` can preserve upstream routing rules, balancers, and other profile-level settings.
- The whitelist subscription route `/connect-wl-version-2` defaults to JSON output; URI/base64 output remains available through explicit URI aliases such as `format=uri`.
- Xray JSON routing rules can be managed through the admin UI at `/xray-routings`. Imported RoscomVPN/INCY JSON profiles are stored in `xray_json_settings`, while generated Direct/Proxy/Block field rules are stored in `xray_routings`.
- Each routing rule chooses `direct`, `proxy`, or `blocked`, stores Xray field-rule data in JSON, and uses JSON `subscription_types` to apply independently to `connect`, `connect_v2`, `connect_wl`, or `external`. External nodes use the `external` scope wherever they appear in a generated subscription. Admins can create multiple rules and edit rule JSON, order, activity, outbound, subscription scope, local `xray_inbound_ids`, and external subscription config targets after import.
- Routing rules can also target proxy nodes through `proxy_ids`.
- Reusable global DNS and geodata resources are stored in `xray_routing_dns_settings` and `xray_routing_geodata`. Custom JSON definitions in `xray_custom_configs` select those resources and selected nodes/routings while still filtering local and external nodes per requesting user.
- `xray_custom_configs.is_public` controls profile visibility and defaults to `true` for existing configs. Public profiles appear for every user; private profiles appear only for users assigned in `xray_custom_config_user`. Admins can manage assignments from either the user edit page or the custom config editor. `xray_custom_config_user_prices` stores one-time price history with effective dates; removing an assignment removes its price history too.
- A DNS resource marked as default is used by every simple JSON config; custom JSON profiles can still select their own DNS resource.
- Routing rules can be marked global for every simple JSON config or targeted to selected local inbounds/servers. Untargeted rules remain inactive for generated profiles unless explicitly marked global.
- `xray_custom_config_outbound_groups` and `xray_custom_config_routes` extend custom configs into named, ordered balancer groups and site-specific routing. Groups support `roundRobin`, `leastPing`, `leastLoad`, and `random`; a group may fall back to another multi-node group, while unmatched traffic defaults to `direct`.
- Empty routing target arrays mean the rule is not applied anywhere unless the rule is marked global. Rules are selected per scope and target; if none match, generated profiles receive the default routing rules instead of being left without routing because another target has configured rules.
- Imported geodata URLs are validated and cached in `storage/app/xray-geodata` by URL plus `LastUpdated`; JSON subscriptions emit Xray `geodata.assets`, so the client-side Xray Core downloads `geoip.dat`/`geosite.dat` into its own resource path.

Its order is explicit rather than inferred from ids:

- `servers.sort_order` controls local server order
- `vless_external_subscriptions.sort_order` controls where external groups appear among local servers
- `xray_custom_configs.sort_order` controls where active custom JSON profiles appear among local servers and external groups in JSON subscription output
- `xray_inbounds.sort_order` and `proxies.sort_order` control order inside one server
- soft-deleted servers are excluded from `/connect` by default because `servers` now use Eloquent soft delete

External VLESS subscription visibility is controlled independently per output:

- `include_in_main_subscription` controls `/connect`
- `include_in_whitelist` controls whitelist output
- `include_in_connect_v2` controls `/connect-v2`

For subscription-type sources, `filter_pattern` includes names matching its case-insensitive Unicode regular expression, then `except_pattern` excludes names matching its expression. Both fields are optional, and invalid expressions are rejected by the admin preview and save requests.

Important WireGuard/AmneziaWG rules:

- WireGuard URIs are normalized before output
- the app accepts both raw and legacy-encoded `wireguard://...` values during parsing
- URI subscription output keeps WireGuard keys raw so clients that import subscriptions do not break on secret keys containing `/`
- the same normalization is applied even for older records that already store a `wireguard://...` string in `vless_configs.extra`
- 3x-ui `amneziawg` inbounds are stored as Xray-backed `VlessConfig` records with `protocol=amneziawg`
- AmneziaWG clients are serialized as native `.conf` content and wrapped in an `amneziawg://{base64url-conf}` URI for `/connect`
- Mini-app download/QR flows decode that URI back to the native AmneziaWG `.conf` content

### User Subscriptions

Subscriptions are stored in `user_subscriptions`.

Each subscription has:

- `user_id`
- `start_date`
- `end_date`
- `price`

The active subscription is the subscription covering today.
The latest subscription is the one with the greatest `end_date`.

### User Connected Devices

Subscription-link refreshes from `/connect` and `/connect-wl-version-2` are tracked in `user_connected_devices`.

Each connected device has:

- `user_id`
- `label`
- `device`
- `user_agent`
- `user_agent_hash`
- `ip_address`
- `first_connection_at`
- `last_connection_at`
- `connection_count`
- `connection_route`
- `deleted_at`

One user agent can create separate rows per subscription route, for example one `connect` row and one `connect-wl` row for the same device.

Requests with `skip_connection=true` skip this tracking for admin workflows.

### Daily Traffic

`user_server_stats` keeps the latest cumulative panel counters per user and server. Each five-minute sync compares the current counters with that baseline, handles counter resets, and atomically adds the deltas to `user_daily_traffic`, one row per user and date across all servers. The first counter observed for a user/server establishes a baseline and is not counted as new traffic. `/connect` sums daily deltas in the active subscription's current monthly cycle. `traffic:cleanup-daily` removes records older than the 90-day retention window.

### Support Tickets

Support ticket messages can include up to five image or PDF attachments, stored privately under `storage/app/private/support-tickets/{ticket_id}`. Each file is limited to 5 MB and downloads require access to the owning ticket or an authenticated operator session. Operator replies mark tickets as answered, operator controls can close a ticket, and a user's reply reopens it. The hourly `support-tickets:cleanup` command silently closes tickets after three days without messages and deletes their attachment files one week after closure.

### Subscription Expiry Notifications

Subscription reminder logs are stored in `subscription_expiry_notifications`.

Each notification row has:

- `user_id`
- `user_subscription_id`
- `threshold_key`
- `threshold_hours`
- `subscription_end_at`
- `sent_at`

Important rule:

- reminder anti-spam is tied to a specific `user_subscriptions` record, so after renewal the old period does not continue sending follow-up reminders

### Giveaways

Giveaway campaigns are stored in:

- `giveaway_series`
- `giveaways`
- `giveaway_prizes`
- `giveaway_participants`
- `giveaway_winners`

Current rules:

- participation is explicit and starts only after pressing `Participate`
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

A user has active access when:

- the user has an active subscription

Configs should be disabled when the user has no active subscription.

Configs can be enabled when the user has an active subscription.

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
