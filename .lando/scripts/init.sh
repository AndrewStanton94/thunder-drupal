#!/bin/bash
# Copy all the necessary files for drupal.
if [ ! -f /app/docroot/sites/default/settings.local.php ]; then
  cp /app/docroot/sites/default/settings.local.php.sample /app/docroot/sites/default/settings.local.php
fi

# If the site doesn't have a settings.php.
if [ ! -f /app/docroot/sites/default/settings.php ]; then
  cp /app/docroot/sites/default/default.settings.php /app/docroot/sites/default/settings.php
fi

if [ ! -d /app/docroot/sites/default/files ]; then
  mkdir -p /app/docroot/sites/default/files
  chmod -R 777 /app/docroot/sites/default/files
fi

if [ ! -d /app/docroot/sites/default/private ]; then
  mkdir -p /app/docroot/sites/default/private
  chmod -R 777 /app/docroot/sites/default/private
fi

chmod 755 /app/docroot/sites/default
