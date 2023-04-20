server {
  listen 80 default_server;
  listen 443 ssl;

  server_name localhost;

  charset utf-8;

  keepalive_timeout 0;

  ssl_certificate /certs/cert.crt;
  ssl_certificate_key /certs/cert.key;
  ssl_verify_client off;

  ssl_session_cache shared:SSL:1m;
  ssl_session_timeout 5m;

  ssl_ciphers HIGH:!aNULL:!MD5;
  ssl_prefer_server_ciphers on;

  port_in_redirect off;

  root /app/docroot; # CHANGE THIS TO THE SITE ROOT FOLDER

  add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
  fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

  include fastcgi_params;

  location ~ (install|update)\.php {
    return 403;
  }

  location = /favicon.ico {
    log_not_found off;
    access_log off;
  }

  location = /app_installer/res/osx_update/install.json {
    log_not_found off;
    access_log off;
  }

  location = /robots.txt {
    allow all;
    log_not_found off;
    access_log off;
  }

  # This matters if you use drush
  location = /backup {
    deny all;
  }
  # Block all svn access
  if ($request_uri ~* ^.*\.svn.*$) {
    return 404;
  }
  # Block all git access
  if ($request_uri ~* ^.*\.git.*$) {
    return 404;
  }
  # Deny direct access to backups.
  location ~* ^/sites/.*/files/backup_migrate/ {
    access_log off;
    deny all;
  }
  # Deny direct access to backups.
  location ~* ^/sites/.*/files/nightly/ {
    access_log off;
    deny all;
  }

  # Allow txt files in site files
  location ~* /sites/.*/files/.*(txt)$ {
    allow all;
  }

  # Very rarely should these ever be accessed outside of your lan
  location ~* \.(txt|log)$ {
    deny all;
  }

  location ~ \..*/.*\.php$ {
    return 403;
  }

  # Deny some not supported URI like cgi-bin on the Nginx level.
  location ~* (?:cgi-bin|vti-bin) {
    access_log off; return
  403;
  }

  # Deny direct access to config files in Drupal 8.
  location ~* ^/sites/.*/files/config_.* {
    access_log off;
    deny all;
  }

  # Deny direct access to config files in Drupal 8.
  location ~* ^/sites/(.*/)?config/sync/.* {
    access_log off;
    deny all;
  }

  # Deny listed requests for security reasons.
  location ~* (?:delete.+from|insert.+into|select.+from|union.+select|onload|\.php.+src|system\(.+|document\.cookie|\;|\.\.) {
    return 403;
  }

  # Deny listed requests for security reasons.
  location ~* (/\..*|settings\.php$|\.(?:git|htaccess|engine|inc|info|install|module|profile|pl|po|sh|.*sql|theme|tpl(?:\.php)?|xtmpl)$|^(?:Entries.*|Repository|Root|Tag|Template))$ {
    return 403;
  }

  # Allow some known php files (like serve.php in the ad module).
  location ~* /(?:modules|libraries)/(?:contrib/)?(?:ad|tinybrowser|f?ckeditor|tinymce|wysiwyg_spellcheck|ecc|civicrm|fbconnect|radioactivity)/.*\.php$ {
    access_log off;
    try_files $uri =404;
    fastcgi_pass fpm:9000;
    fastcgi_intercept_errors on;
    fastcgi_read_timeout 120;
  }

  location / {
  # This is cool because no php is touched for static content
  # try_files $uri $uri/ @rewrite;
    try_files $uri /index.php?$query_string;
  }

  # Pass through all requests to index.php
  location @rewrite {
    rewrite ^/(.*)$ /index.php?q=$1;
  }

  # Accepted .php files
  # In a normal production environment you wouldn't allow dev.
  location ~ ^/(index|dev|cron|xmlrpc)\.php$ {
    fastcgi_pass fpm:9000;
    fastcgi_index index.php;
    fastcgi_intercept_errors on;
    fastcgi_read_timeout 120;
  }

  # Deny access to any not listed above php files.
  location ~* ^.+\.php$ {
    deny all;
  }

  # Fighting with ImageCache? This little gem is amazing.
  location ~ ^/sites/.*/files/imagecache/ {
    try_files $uri $uri/ @rewrite;
    expires 7d;
  }

  # For Drupal7 use /styles instead of /imagecache
  location ~ ^/sites/.*/files/styles/ {
    try_files $uri @rewrite;
    expires 7d;
  }

  # Need this for advagg module which needs to handle 404s, instead of nginx.
  location ~ sites/.*/files/advagg* {
  expires max;
  log_not_found off;
  access_log off;
  try_files $uri $uri/ @rewrite;
  }

  location ~* \.(js|css|png|jpg|jpeg|gif|svg|ico|mp4|json)$ {
    expires max;
    log_not_found off;
    access_log off;
    try_files $uri $uri/ @rewrite;
  }

  location ~* \.(eot|ttf|woff|woff2)$ {
    add_header Access-Control-Allow-Origin *;
    access_log off;
    expires max;
    log_not_found off;
  }

  proxy_buffer_size 512k;
  proxy_buffers 8 512k;
  client_body_buffer_size 512k;
  fastcgi_buffer_size 512k ;
  fastcgi_buffers 8 512k ;
  client_max_body_size 128M;
}
