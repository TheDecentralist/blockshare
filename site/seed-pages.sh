#!/bin/bash
# Registers the dynamic page slugs with the BlockShare Static Pages plugin.
# Run on the server AFTER WordPress is installed and the plugin is activated.
set -e
cd /var/www/html
wp plugin activate blockshare-static-pages --allow-root
wp option update bs_dynamic_pages "$(cat /root/site/dynamic-pages.json)" --format=json --allow-root
wp rewrite structure '/%postname%/' --allow-root
wp rewrite flush --allow-root
echo "Registered $(python3 -c 'import json;print(len(json.load(open("/root/site/dynamic-pages.json"))))') dynamic pages"
