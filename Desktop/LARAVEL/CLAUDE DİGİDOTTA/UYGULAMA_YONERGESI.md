# DigiDotta — Diğer Sayfalar İçin Uygulama Yönergesi
## Açık Mod, Banner Menü Ortalama, Tema Butonu

---

## 1. `style.css` Bağlantısı

Her servis sayfasının `<head>` kısmında zaten şu satır var — **değişiklik gerekmez**:
```html
<link rel="stylesheet" href="style.css">
```
Yeni `style.css` tüm renk değişkenlerini ve `:root` + `[data-theme="light"]` bloklarını içeriyor. Sayfa otomatik olarak sistemi kullanır.

---

## 2. `<html>` etiketine data-theme ekleme

Her sayfanın en üstündeki `<html>` etiketini şöyle bırakın — JS zaten `data-theme` ekleyecek:
```html
<html lang="tr">
```
*(Herhangi bir `data-theme` manuel eklemenize gerek yok, JS hallediyor.)*

---

## 3. Dil + Tema Çubuğu (lang-bar)

Her sayfada `<body>` açıldıktan hemen sonra bulunan `#lang-bar` div'ini **şununla değiştirin**:

```html
<div id="lang-bar">
  <button id="theme-btn" class="lb theme-lb" onclick="toggleTheme()" title="Tema Değiştir">
    <span id="theme-icon">☀</span>
  </button>
  <div style="width:1px;background:var(--gborder);margin:4px 2px"></div>
  <button class="lb" onclick="setLang('en')">EN</button>
  <button class="lb active" onclick="setLang('tr')">TR</button>
  <button class="lb" onclick="setLang('de')">DE</button>
</div>
```

> **Not:** TR butonu `active` — açılışta Türkçe için.

---

## 4. Nav Menüyü Ortala

Her sayfadaki `<nav>` içinde menüyü saran `<ul class="nlinks">` listesini bir `div` ile sarın:

**ÖNCE:**
```html
<nav>
  <a class="nlogo" href="digidotta.html">...</a>
  <ul class="nlinks">
    <li>...</li>
  </ul>
  <a class="cta-nav" ...>GET STARTED</a>  ← bunu silin
</nav>
```

**SONRA:**
```html
<nav>
  <a class="nlogo" href="digidotta.html">...</a>
  <div class="nlinks-wrap">
    <ul class="nlinks">
      <li>...</li>
    </ul>
  </div>
</nav>
```

> `.cta-nav` ("GET STARTED") butonunu **kaldırın** — dil butonlarının altında kalıyor.

---

## 5. JavaScript — Tema + Dil Sistemi

Her sayfanın en alt `<script>` bloğunda `let lang='...';` satırından **önce** şu kodu ekleyin:

```javascript
// ── Tema ────────────────────────────────────────────────────
let theme = localStorage.getItem('dg-theme') || 'dark';
function applyTheme(t) {
  theme = t;
  document.documentElement.setAttribute('data-theme', t === 'light' ? 'light' : 'dark');
  const icon = document.getElementById('theme-icon');
  if (icon) icon.textContent = t === 'light' ? '🌙' : '☀';
  localStorage.setItem('dg-theme', t);
}
function toggleTheme() { applyTheme(theme === 'dark' ? 'light' : 'dark'); }
applyTheme(theme);
// ── Dil ─────────────────────────────────────────────────────
```

Yani mevcut `let lang='en';` satırını şöyle hale getirin:

```javascript
// ── Tema ────────────────────────────────────────────────────
let theme = localStorage.getItem('dg-theme') || 'dark';
function applyTheme(t) {
  theme = t;
  document.documentElement.setAttribute('data-theme', t === 'light' ? 'light' : 'dark');
  const icon = document.getElementById('theme-icon');
  if (icon) icon.textContent = t === 'light' ? '🌙' : '☀';
  localStorage.setItem('dg-theme', t);
}
function toggleTheme() { applyTheme(theme === 'dark' ? 'light' : 'dark'); }
applyTheme(theme);
// ── Dil ─────────────────────────────────────────────────────
let lang = 'tr';
function setLang(l) { ... }   ← mevcut kodu koruyun
setLang('tr');                 ← bu satır zaten varsa koruyun, yoksa ekleyin
```

---

## 6. Açılış Dili TR (kontrol)

Script bloğunda şunların doğru olduğundan emin olun:
```javascript
let lang = 'tr';   // 'en' değil, 'tr' olmalı
// ...
setLang('tr');     // fonksiyon tanımından sonra çağrılmalı
```

---

## 7. Sayfa Başına Özet Kontrol Listesi

Bir servis sayfasını güncellerken sırayla yapılacaklar:

- [ ] `<link rel="stylesheet" href="style.css">` var mı? ✓ (zaten var)
- [ ] `#lang-bar` → tema butonu ekli yeni versiyonla değiştirildi mi?
- [ ] `<nav>` içinde `<ul class="nlinks">` → `<div class="nlinks-wrap">` ile sarıldı mı?
- [ ] `<a class="cta-nav">` kaldırıldı mı?
- [ ] Script'te `let lang = 'tr';` mi?
- [ ] `setLang('tr');` çağrısı var mı?
- [ ] Tema JS bloğu (`toggleTheme` vb.) eklendi mi?

---

## 8. Hızlı Test

Sayfayı tarayıcıda açtığınızda:
1. Sağ üstte ☀ (güneş) butonu görünüyor → tıklayınca arka plan açık mavi-beyaza dönüyor, ikon 🌙 oluyor
2. Sayfa yenilenince seçilen tema hatırlanıyor (localStorage)
3. Nav menüsü tam ortada
4. Varsayılan dil Türkçe

---

*style.css ve digidotta.html bu yönergeye göre güncellenmiş olarak teslim edilmiştir.*
