# API Sözleşmesi

API, mobil uygulama ve dış entegrasyonlar için hazırlanacaktır. Kimlik doğrulama Laravel Sanctum ile yapılır.

## Genel

- Base URL: `/api`
- Kimlik doğrulama: Bearer Token
- Yanıt formatı: JSON
- Başarılı yanıt: `200`
- Doğrulama hatası: `422`
- Yetkisiz işlem: `403`
- Bulunamadı: `404`
- Sunucu hatası: `500`

## Ürünler

### Ürün Arama

```http
GET /api/products?search=tarhana
```

Yanıt:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Bol Yoğurtlu Tarhana",
      "barcode": "8690000000011",
      "unit": "kg",
      "sale_price": "350.00",
      "stock": "100.000"
    }
  ]
}
```

### Barkodla Ürün Arama

```http
GET /api/products/barcode/{barcode}
```

## Satış

### Satış Oluşturma

```http
POST /api/sales
```

İstek:

```json
{
  "customer_id": null,
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "unit_price": 350.00,
      "discount": 0
    }
  ],
  "payments": [
    {
      "payment_type": "cash",
      "amount": 700.00
    }
  ]
}
```

Yanıt:

```json
{
  "message": "Satış başarıyla tamamlandı.",
  "sale_id": 15,
  "sale_no": "S-2026-000015",
  "total": "700.00"
}
```

## Stok

### Stok Hareketi Oluşturma

```http
POST /api/stock-movements
```

İstek:

```json
{
  "product_id": 1,
  "movement_type": "purchase",
  "quantity": 10,
  "note": "Tedarikçiden gelen ürün"
}
```
