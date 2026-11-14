# Yarış Koşulu Testleri

Oturum: `[17-Güvenlik denetimi]`
Amaç: Stok, kupon ve PayTR callback akışlarında eşzamanlı isteklerin veritabanı durumunu bozmadığını doğrulamak.
Uyarı: Bu komutlar **test ortamında** çalıştırılmalıdır. Üretim veritabanında `UPDATE`/`DELETE` çalıştırmayın.

## Ortak Hazırlık

```bash
export BASE_URL="${BASE_URL:-http://localhost:8000}"
export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3306}"
export DB_NAME="${DB_NAME:-yoresel_market}"
export DB_USER="${DB_USER:-ym_dev}"
export DB_PASS="${DB_PASS:-}"
export VARIANT_ID="${VARIANT_ID:-1}"
export ADDRESS_A="${ADDRESS_A:-1}"
export ADDRESS_B="${ADDRESS_B:-2}"
export COUPON_GLOBAL="${COUPON_GLOBAL:-RACE-GLOBAL}"
export COUPON_USER="${COUPON_USER:-RACE-USER}"
export EMAIL_A="${EMAIL_A:-race-a@example.invalid}"
export PASS_A="${PASS_A:-TestParola123!}"
export EMAIL_B="${EMAIL_B:-race-b@example.invalid}"
export PASS_B="${PASS_B:-TestParola123!}"
export EMAIL_SAME="${EMAIL_SAME:-race-same@example.invalid}"
export PASS_SAME="${PASS_SAME:-TestParola123!}"

mysqlq() {
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" ${DB_PASS:+-p"$DB_PASS"} "$DB_NAME" -N -B -e "$1"
}

csrf_from() {
  sed -n 's/.*<meta name="csrf-token" content="\([^"]*\)".*/\1/p' "$1" | head -n1
}

login() {
  local jar="$1" email="$2" pass="$3" page token
  page=$(mktemp)
  curl -sS -c "$jar" -b "$jar" "$BASE_URL/giris" -o "$page"
  token=$(csrf_from "$page")
  curl -sS -o /dev/null -c "$jar" -b "$jar" \
    -H "X-CSRF-Token: $token" \
    --data-urlencode "_token=$token" \
    --data-urlencode "email=$email" \
    --data-urlencode "password=$pass" \
    "$BASE_URL/giris"
  rm -f "$page"
}

prepare_checkout() {
  local jar="$1" page token nonce
  page=$(mktemp)
  curl -sS -c "$jar" -b "$jar" "$BASE_URL/odeme" -o "$page"
  token=$(csrf_from "$page")
  nonce=$(sed -n 's/.*name="nonce" value="\([^"]*\)".*/\1/p' "$page" | head -n1)
  rm -f "$page"
  printf '%s|%s\n' "$token" "$nonce"
}

add_cart() {
  local jar="$1" variant="$2" qty="$3" page token
  page=$(mktemp)
  curl -sS -c "$jar" -b "$jar" "$BASE_URL/sepet" -o "$page"
  token=$(csrf_from "$page")
  rm -f "$page"
  curl -sS -o /dev/null -c "$jar" -b "$jar" \
    -H "X-CSRF-Token: $token" \
    --data-urlencode "_token=$token" \
    --data-urlencode "variant_id=$variant" \
    --data-urlencode "quantity=$qty" \
    "$BASE_URL/sepet/ekle"
}

apply_coupon() {
  local jar="$1" code="$2" page token
  page=$(mktemp)
  curl -sS -c "$jar" -b "$jar" "$BASE_URL/sepet" -o "$page"
  token=$(csrf_from "$page")
  rm -f "$page"
  curl -sS -o /dev/null -c "$jar" -b "$jar" \
    -H "X-CSRF-Token: $token" \
    --data-urlencode "_token=$token" \
    --data-urlencode "code=$code" \
    "$BASE_URL/sepet/kupon"
}

checkout() {
  local jar="$1" nonce="$2" address="$3" method="${4:-bank_transfer}" token
  token=$(csrf_from <(curl -sS -c "$jar" -b "$jar" "$BASE_URL/sepet"))
  curl -sS -o "/tmp/ym-race-$(basename "$jar").body" -w '%{http_code}' \
    -c "$jar" -b "$jar" \
    -H "X-CSRF-Token: $token" \
    --data-urlencode "_token=$token" \
    --data-urlencode "nonce=$nonce" \
    --data-urlencode "shipping_address_id=$address" \
    --data-urlencode "billing_type=individual" \
    --data-urlencode "payment_method=$method" \
    --data-urlencode "customer_note=" \
    --data-urlencode "accept_distance_sales=1" \
    --data-urlencode "accept_kvkk=1" \
    "$BASE_URL/odeme/siparis-ver"
}
```

> Not: Müşteri hesaplarının e-posta doğrulaması tamamlanmış olmalıdır; aksi halde checkout `/eposta-dogrula` adresine yönlenir.

## 1. Son Stok Yarışı

### Hazırlık
```bash
mysqlq "UPDATE product_variants SET stock_quantity = 1 WHERE id = $VARIANT_ID;"
COOKIE_A=$(mktemp); COOKIE_B=$(mktemp)
login "$COOKIE_A" "$EMAIL_A" "$PASS_A"
login "$COOKIE_B" "$EMAIL_B" "$PASS_B"
add_cart "$COOKIE_A" "$VARIANT_ID" 1
add_cart "$COOKIE_B" "$VARIANT_ID" 1
IFS='|' read -r TOKEN_A NONCE_A < <(prepare_checkout "$COOKIE_A")
IFS='|' read -r TOKEN_B NONCE_B < <(prepare_checkout "$COOKIE_B")
```

### Paralel İstek
```bash
( checkout "$COOKIE_A" "$NONCE_A" "$ADDRESS_A" bank_transfer > /tmp/ym-stock-a.code ) &
( checkout "$COOKIE_B" "$NONCE_B" "$ADDRESS_B" bank_transfer > /tmp/ym-stock-b.code ) &
wait
printf 'A=%s B=%s\n' "$(cat /tmp/ym-stock-a.code)" "$(cat /tmp/ym-stock-b.code)"
```

### Beklenen
- HTTP kodlarından biri başarılı yönlendirme (`302`/`303`), diğeri stok hatası (`302` → `/sepet` veya `409`/`422`).
- Tam olarak **bir** sipariş oluşur; ikinci istek stok hareketi yazmaz.
- `product_variants.stock_quantity = 0`; negatif stok yok.
- `stock_movements` içinde bu varyant için yalnızca bir `sale` hareketi ve `quantity_change = -1` olur.

### Doğrulama
```bash
mysqlq "SELECT stock_quantity FROM product_variants WHERE id = $VARIANT_ID;"
mysqlq "SELECT COUNT(*) FROM orders o JOIN order_items oi ON oi.order_id = o.id WHERE oi.variant_id = $VARIANT_ID AND o.status <> 'cancelled';"
mysqlq "SELECT type, quantity_change, quantity_after FROM stock_movements WHERE variant_id = $VARIANT_ID ORDER BY id DESC LIMIT 5;"
```

## 2. Global Kupon Kullanım Limiti Yarışı

### Hazırlık
Kupon test kaydı: `usage_limit = 1`, `used_count = 0`, `usage_per_user = NULL`.
```bash
mysqlq "UPDATE coupons SET usage_limit = 1, used_count = 0, usage_per_user = NULL, is_active = 1, starts_at = NULL, ends_at = NULL WHERE code = '$COUPON_GLOBAL';"
COOKIE_A=$(mktemp); COOKIE_B=$(mktemp)
login "$COOKIE_A" "$EMAIL_A" "$PASS_A"
login "$COOKIE_B" "$EMAIL_B" "$PASS_B"
add_cart "$COOKIE_A" "$VARIANT_ID" 1
add_cart "$COOKIE_B" "$VARIANT_ID" 1
apply_coupon "$COOKIE_A" "$COUPON_GLOBAL"
apply_coupon "$COOKIE_B" "$COUPON_GLOBAL"
IFS='|' read -r TOKEN_A NONCE_A < <(prepare_checkout "$COOKIE_A")
IFS='|' read -r TOKEN_B NONCE_B < <(prepare_checkout "$COOKIE_B")
```

### Paralel İstek
```bash
( checkout "$COOKIE_A" "$NONCE_A" "$ADDRESS_A" bank_transfer > /tmp/ym-coupon-a.code ) &
( checkout "$COOKIE_B" "$NONCE_B" "$ADDRESS_B" bank_transfer > /tmp/ym-coupon-b.code ) &
wait
printf 'A=%s B=%s\n' "$(cat /tmp/ym-coupon-a.code)" "$(cat /tmp/ym-coupon-b.code)"
```

### Beklenen
- Yalnızca **bir** sipariş kuponlu oluşur; diğeri kupon hatası alır veya kuponsuz devam etmez.
- `coupons.used_count = 1`; `usage_limit` aşılmaz.
- `coupon_usages` tablosunda bu kupon için yalnızca **bir** satır bulunur.
- İki sipariş de oluşsa bile yalnızca birinde `orders.coupon_code` dolu olur.

### Doğrulama
```bash
mysqlq "SELECT used_count, usage_limit FROM coupons WHERE code = '$COUPON_GLOBAL';"
mysqlq "SELECT COUNT(*) FROM coupon_usages cu JOIN coupons c ON c.id = cu.coupon_id WHERE c.code = '$COUPON_GLOBAL';"
mysqlq "SELECT COUNT(*) FROM orders WHERE coupon_code = '$COUPON_GLOBAL' AND status <> 'cancelled';"
```

## 3. Kullanıcı Başı Kupon Limiti Yarışı

### Hazırlık
Kupon test kaydı: `usage_limit = NULL`, `usage_per_user = 1`, `used_count = 0`. Aynı kullanıcıyla iki ayrı sekme/oturum kullanın; her oturumun kendi cookie jar'ı ve kendi nonce'u olmalıdır.
```bash
mysqlq "UPDATE coupons SET usage_limit = NULL, used_count = 0, usage_per_user = 1, is_active = 1, starts_at = NULL, ends_at = NULL WHERE code = '$COUPON_USER';"
COOKIE_1=$(mktemp); COOKIE_2=$(mktemp)
login "$COOKIE_1" "$EMAIL_SAME" "$PASS_SAME"
login "$COOKIE_2" "$EMAIL_SAME" "$PASS_SAME"
add_cart "$COOKIE_1" "$VARIANT_ID" 1
add_cart "$COOKIE_2" "$VARIANT_ID" 1
apply_coupon "$COOKIE_1" "$COUPON_USER"
apply_coupon "$COOKIE_2" "$COUPON_USER"
IFS='|' read -r TOKEN_1 NONCE_1 < <(prepare_checkout "$COOKIE_1")
IFS='|' read -r TOKEN_2 NONCE_2 < <(prepare_checkout "$COOKIE_2")
```

### Paralel İstek
```bash
( checkout "$COOKIE_1" "$NONCE_1" "$ADDRESS_A" bank_transfer > /tmp/ym-user-a.code ) &
( checkout "$COOKIE_2" "$NONCE_2" "$ADDRESS_A" bank_transfer > /tmp/ym-user-b.code ) &
wait
printf '1=%s 2=%s\n' "$(cat /tmp/ym-user-a.code)" "$(cat /tmp/ym-user-b.code)"
```

### Beklenen
- Aynı kullanıcı için yalnızca **bir** kupon kullanımı kaydedilir.
- `coupon_usages` içinde `(coupon_id, user_id)` için tek satır bulunur.
- `coupons.used_count = 1`; ikinci sipariş kupon indirimi almaz veya reddedilir.
- Aynı sepete iki eşzamanlı checkout'ta ikinci istek boş sepet/nonce nedeniyle de reddedilebilir; bu durumda da kupon kullanımı artmamalıdır.

### Doğrulama
```bash
USER_ID=$(mysqlq "SELECT id FROM users WHERE email = '$EMAIL_SAME' LIMIT 1;")
mysqlq "SELECT COUNT(*) FROM coupon_usages cu JOIN coupons c ON c.id = cu.coupon_id WHERE c.code = '$COUPON_USER' AND cu.user_id = $USER_ID;"
mysqlq "SELECT used_count FROM coupons WHERE code = '$COUPON_USER';"
```

## 4. PayTR Callback Yarışı

### Hazırlık
Kart ödemeli, `pending_payment` durumunda bir sipariş ve `initiated`/`pending` PayTR ödeme kaydı gerekir. `merchant_oid` yalnızca harf ve rakamlardan oluşur.
```bash
ORDER_ID=$(mysqlq "SELECT o.id FROM orders o JOIN payments p ON p.order_id = o.id WHERE p.provider = 'paytr' AND p.status IN ('initiated','pending') AND o.status = 'pending_payment' ORDER BY o.id DESC LIMIT 1;")
read -r MERCHANT_OID ORDER_TOTAL_CENTS < <(mysqlq "SELECT p.merchant_oid, CAST(ROUND(o.grand_total * 100) AS UNSIGNED) FROM payments p JOIN orders o ON o.id = p.order_id WHERE o.id = $ORDER_ID LIMIT 1;")
export PAYTR_MERCHANT_KEY="${PAYTR_MERCHANT_KEY:?PAYTR_MERCHANT_KEY gerekli}"
export PAYTR_MERCHANT_SALT="${PAYTR_MERCHANT_SALT:?PAYTR_MERCHANT_SALT gerekli}"

paytr_hash() {
  local oid="$1" status="$2" amount="$3"
  php -r 'echo base64_encode(hash_hmac("sha256", $argv[1].$argv[2].$argv[3].$argv[4], $argv[5], true));' \
    "$oid" "$PAYTR_MERCHANT_SALT" "$status" "$amount" "$PAYTR_MERCHANT_KEY"
}

HASH_OK=$(paytr_hash "$MERCHANT_OID" success "$ORDER_TOTAL_CENTS")
HASH_BAD=$(paytr_hash "$MERCHANT_OID" success "$((ORDER_TOTAL_CENTS + 1))")
```

### Aynı Geçerli Bildirimi 5 Kez Paralel Gönder
```bash
for i in 1 2 3 4 5; do
  curl -sS -o "/tmp/ym-paytr-$i.body" -w '%{http_code}\n' \
    --data-urlencode "merchant_oid=$MERCHANT_OID" \
    --data-urlencode "status=success" \
    --data-urlencode "total_amount=$ORDER_TOTAL_CENTS" \
    --data-urlencode "hash=$HASH_OK" \
    --data-urlencode "payment_type=card" \
    --data-urlencode "installment_count=0" \
    "$BASE_URL/odeme/paytr/bildirim" > "/tmp/ym-paytr-$i.code" &
done
wait
cat /tmp/ym-paytr-*.code
```

### Beklenen
- Tüm yanıtlar düz `OK` olabilir; ancak yalnızca **bir** callback `processed` olur, diğerleri `duplicate` olarak loglanır.
- `payments.status = 'success'`; `orders.payment_status = 'paid'`; `orders.status = 'paid'`.
- `order_status_history` içinde `pending_payment → paid` geçişi yalnızca **bir** kez bulunur.
- Stok ve kupon yalnızca bir kez düşülür/işlenir.
- `payment_callbacks` tablosunda aynı `merchant_oid` için `processed` sayısı 1, `duplicate` sayısı 4 olur.

### Yanlış Tutar
Bu test, geçerli bildirimle aynı ödeme üzerinde çalıştırılmamalıdır; ödeme `success` olduktan sonra gelen istek idempotency nedeniyle `duplicate` döner. Ayrı bir `pending` ödeme kaydı seçin.
```bash
ORDER_ID_BAD=$(mysqlq "SELECT o.id FROM orders o JOIN payments p ON p.order_id = o.id WHERE p.provider = 'paytr' AND p.status IN ('initiated','pending') AND o.status = 'pending_payment' AND o.id <> $ORDER_ID ORDER BY o.id DESC LIMIT 1;")
read -r MERCHANT_OID_BAD ORDER_TOTAL_CENTS_BAD < <(mysqlq "SELECT p.merchant_oid, CAST(ROUND(o.grand_total * 100) AS UNSIGNED) FROM payments p JOIN orders o ON o.id = p.order_id WHERE o.id = $ORDER_ID_BAD LIMIT 1;")
HASH_BAD=$(paytr_hash "$MERCHANT_OID_BAD" success "$((ORDER_TOTAL_CENTS_BAD + 1))")
curl -sS -o /tmp/ym-paytr-bad.body -w '%{http_code}\n' \
  --data-urlencode "merchant_oid=$MERCHANT_OID_BAD" \
  --data-urlencode "status=success" \
  --data-urlencode "total_amount=$((ORDER_TOTAL_CENTS_BAD + 1))" \
  --data-urlencode "hash=$HASH_BAD" \
  "$BASE_URL/odeme/paytr/bildirim"
```

### Yanlış Tutar Beklenen
- `payment_callbacks.result = 'amount_mismatch'`; `payments.status = 'failed'`; sipariş `pending_payment`/`pending` kalır.
- Stok düşmez, kupon kullanılmaz, `order_status_history` yeni `paid` kaydı almaz.
- `security_events` içinde `amount_mismatch` olayı görülür.

### Doğrulama
```bash
mysqlq "SELECT status, callback_received_at FROM payments WHERE merchant_oid = '$MERCHANT_OID';"
mysqlq "SELECT status, payment_status FROM orders WHERE id = $ORDER_ID;"
mysqlq "SELECT from_status, to_status, COUNT(*) FROM order_status_history WHERE order_id = $ORDER_ID GROUP BY from_status, to_status;"
mysqlq "SELECT result, COUNT(*) FROM payment_callbacks WHERE merchant_oid = '$MERCHANT_OID' GROUP BY result;"
mysqlq "SELECT status, failed_reason_code FROM payments WHERE merchant_oid = '$MERCHANT_OID_BAD';"
mysqlq "SELECT status, payment_status FROM orders WHERE id = $ORDER_ID_BAD;"
mysqlq "SELECT result FROM payment_callbacks WHERE merchant_oid = '$MERCHANT_OID_BAD';"
```
