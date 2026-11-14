#!/usr/bin/env bash
# opencart.local Apache vhost kurulum betiği
# Kullanim: sudo bash /home/osman/Desktop/opencart/setup-vhost.sh
set -e

CONF_SRC="/home/osman/Desktop/opencart/opencart.local.conf"
CONF_DST="/etc/apache2/sites-available/opencart.local.conf"

echo "==> Vhost kopyalaniyor"
cp "$CONF_SRC" "$CONF_DST"

echo "==> Site etkinlestiriliyor"
a2ensite opencart.local >/dev/null

echo "==> rewrite modulu kontrol ediliyor"
a2enmod rewrite >/dev/null 2>&1 || true

echo "==> /etc/hosts girdisi ekleniyor"
if ! grep -qE "[[:space:]]opencart\.local([[:space:]]|$)" /etc/hosts; then
    echo "127.0.0.1 opencart.local" >> /etc/hosts
fi

echo "==> Apache yapilandirmasi test ediliyor"
apache2ctl configtest

echo "==> Apache yeniden yukleniyor"
systemctl reload apache2

echo ""
echo "Tamam! Magaza: http://opencart.local/"
echo "Panel : http://opencart.local/admin/"
