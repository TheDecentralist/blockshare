#!/usr/bin/env bash
# Run on the server as root:  cd /root/bs/policy-decoder && bash deploy/setup.sh
set -euo pipefail
APP=/opt/policy-decoder
SRC="$(cd "$(dirname "$0")/.." && pwd)"

apt-get update -qq
apt-get install -y nodejs npm rsync
mkdir -p "$APP"
rsync -a --exclude deploy --exclude .env --exclude reports --exclude node_modules --exclude .git "$SRC/" "$APP/"
cd "$APP"
npm install --omit=dev --no-audit --no-fund
[ -f .env ] || cp "$SRC/.env.example" .env
mkdir -p reports
chown -R www-data:www-data "$APP"
chmod 600 .env

cp "$SRC/deploy/policy-decoder.service" /etc/systemd/system/
cp "$SRC/deploy/apache.conf" /etc/apache2/sites-available/policy-decoder.conf
a2enmod proxy proxy_http headers >/dev/null
a2ensite policy-decoder >/dev/null
systemctl daemon-reload
systemctl enable policy-decoder >/dev/null
apache2ctl configtest
systemctl reload apache2

if grep -Eq '^ANTHROPIC_API_KEY=.+' .env; then
  systemctl restart policy-decoder
  sleep 2
  systemctl --no-pager --lines=5 status policy-decoder || true
  curl -s -o /dev/null -w "local check: HTTP %{http_code}\n" http://127.0.0.1:3131/
else
  echo
  echo "Almost done: put the Anthropic API key in $APP/.env (ANTHROPIC_API_KEY=...), then run:"
  echo "  systemctl restart policy-decoder"
fi
echo "Next: DNS A record policy-decoder -> 134.122.33.249, then: certbot --apache -d policy-decoder.blockshare.ca"
