# Общие настройки сайта staff-agent.ru. Подключается из staff-agent.conf.
# На сервере: /etc/nginx/staff-agent-site.inc (вне conf.d, чтобы nginx не подключал его сам)

root /var/www/staff-agent/public;
index index.html;
charset utf-8;
server_tokens off;
absolute_redirect off;  # /about → /about/ без смены схемы и порта

add_header X-Content-Type-Options nosniff always;
add_header Referrer-Policy strict-origin-when-cross-origin always;

gzip on;
gzip_min_length 512;
gzip_types text/css application/javascript application/json image/svg+xml application/xml text/plain;

error_page 404 /404.html;
location = /404.html { internal; }

location / {
    try_files $uri $uri/ =404;
}

# CSS подключается с ?v=хэш, поэтому можно кэшировать надолго
location /assets/ {
    expires 30d;
    access_log off;
}

location /img/ {
    expires 30d;
    access_log off;
}

location = /favicon.svg {
    expires 7d;
    access_log off;
}

# служебные файлы наружу не отдаём (/.well-known обслуживается отдельно)
location ~ /\. { return 404; }
