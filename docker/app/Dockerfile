FROM ghcr.io/sagernet/sing-box:v1.14.2 AS sing-box

FROM musiermoore/oksana-vpn-app:latest

COPY --from=sing-box /usr/local/bin/sing-box /usr/local/bin/sing-box

WORKDIR /var/www/html

COPY docker/app/Caddyfile /etc/caddy/Caddyfile
COPY docker/app/support-uploads.ini /usr/local/etc/php/conf.d/99-support-uploads.ini
