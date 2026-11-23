#!/bin/bash
# Run on the server from /root/bs (after git pull):  bash plugin/install-plugin.sh
set -e
SRC="$(cd "$(dirname "$0")" && pwd)"
WP=/var/www/html
mkdir -p $WP/wp-content/mu-plugins
rm -rf $WP/wp-content/plugins/blockshare $WP/wp-content/plugins/blockshare-onboarding-v2
cp -r "$SRC/blockshare" "$SRC/blockshare-onboarding-v2" $WP/wp-content/plugins/
cp "$SRC"/mu-plugins/*.php $WP/wp-content/mu-plugins/
chown -R www-data:www-data $WP/wp-content
cd $WP
wp plugin activate blockshare blockshare-onboarding-v2 --allow-root
wp rewrite flush --allow-root
echo "Installed. Tables:"; wp db query "SHOW TABLES LIKE '%bs_%'" --allow-root
