FROM ghcr.io/sagernet/sing-box:v1.14.2 AS sing-box
FROM ghcr.io/xtls/xray-core:26.7.28 AS xray

FROM musiermoore/oksana-vpn-app:latest

COPY --from=sing-box /usr/local/bin/sing-box /usr/local/bin/sing-box
COPY --from=xray /usr/local/bin/xray /usr/local/bin/xray

WORKDIR /var/www/html

COPY docker/app/Caddyfile /etc/caddy/Caddyfile
COPY docker/app/support-uploads.ini /usr/local/etc/php/conf.d/99-support-uploads.ini
