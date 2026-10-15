FROM musiermoore/oksana-vpn-app:latest

WORKDIR /var/www/html

COPY docker/app/Caddyfile /etc/caddy/Caddyfile
COPY docker/app/support-uploads.ini /usr/local/etc/php/conf.d/99-support-uploads.ini
