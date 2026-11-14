<?php
// app/Controllers/Admin/SettingAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Models\SecurityEvent;
use App\Models\Setting;
use InvalidArgumentException;

/**
 * Site ayarları (setting.manage; yalnızca admin).
 * Yalnızca settings tablosunda zaten var olan anahtarlar güncellenir.
 */
final class SettingAdminController extends AdminBaseController
{
    /** Sekmeler: id => [etiket, anahtar önekleri]. */
    private const TABS = [
        'general' => ['Genel', ['site.', 'mail.']],
        'contact' => ['İletişim', ['contact.']],
        'social' => ['Sosyal', ['social.']],
        'shipping' => ['Kargo', ['shipping.']],
        'payment' => ['Ödeme ve Havale', ['card.', 'cod.', 'bank_transfer.']],
        'order' => ['Sipariş', ['order.', 'stock.']],
        'seo' => ['SEO', ['seo.']],
        'legal' => ['Yasal', ['legal.', 'tax.']],
    ];

    /** Ödeme yöntemi anahtarları (en az biri açık kalmalı). */
    private const PAYMENT_KEYS = ['card.enabled', 'bank_transfer.enabled', 'cod.enabled'];

    /**
     * Alan üst verisi (yardım metni, giriş türü, sınırlar, biçim).
     * Etiket her zaman settings.label'dan gelir.
     */
    private const META = [
        'site.name' => ['input' => 'text', 'max' => 100, 'required' => true, 'help' => 'Başlıklarda ve e-postalarda görünür.'],
        'site.tagline' => ['input' => 'text', 'max' => 190, 'help' => 'Kısa slogan.'],
        'mail.from_name' => ['input' => 'text', 'max' => 100, 'required' => true, 'help' => 'E-postalarda gönderen adı.'],
        'contact.email' => ['input' => 'email', 'max' => 190, 'format' => 'email', 'required' => true, 'help' => 'Müşterilere gösterilen iletişim e-postası.'],
        'contact.phone' => ['input' => 'tel', 'max' => 30, 'format' => 'phone', 'help' => 'Örn. 0850 000 00 00'],
        'contact.whatsapp' => ['input' => 'tel', 'max' => 20, 'format' => 'whatsapp', 'help' => 'Ülke koduyla, + olmadan. Örn. 905320000000'],
        'contact.address' => ['input' => 'textarea', 'max' => 500, 'help' => 'Açık adres.'],
        'social.instagram' => ['input' => 'url', 'max' => 255, 'format' => 'url', 'help' => 'https:// ile başlayan tam adres.'],
        'social.facebook' => ['input' => 'url', 'max' => 255, 'format' => 'url', 'help' => 'https:// ile başlayan tam adres.'],
        'shipping.free_threshold' => ['help' => 'Bu tutar ve üzeri siparişlerde kargo ücretsizdir (TL). 0 = ücretsiz kargo yok sayılmaz, her sipariş ücretsiz olur; dikkatli girin.'],
        'cod.fee' => ['help' => 'Kapıda ödeme hizmet bedeli (TL).'],
        'bank_transfer.bank_name' => ['input' => 'text', 'max' => 100, 'help' => 'Banka adı.'],
        'bank_transfer.account_holder' => ['input' => 'text', 'max' => 150, 'help' => 'Hesap sahibi (unvan).'],
        'bank_transfer.iban' => ['input' => 'iban', 'max' => 42, 'format' => 'iban', 'help' => 'TR ile başlayan 26 karakterlik IBAN. Boşluklar otomatik temizlenir.'],
        'bank_transfer.note' => ['input' => 'textarea', 'max' => 500, 'help' => 'Havale ekranında ve e-postada gösterilen açıklama.'],
        'order.card_expiry_minutes' => ['min' => 5, 'maxv' => 1440, 'help' => 'Kartla ödemede bekleyen sipariş kaç dakika sonra iptal edilir (5–1440).'],
        'order.bank_expiry_hours' => ['min' => 1, 'maxv' => 720, 'help' => 'Havale bekleyen sipariş kaç saat sonra iptal edilir (1–720).'],
        'order.min_amount' => ['help' => 'Minimum sipariş tutarı (TL). 0 = sınır yok.'],
        'stock.default_low_threshold' => ['min' => 0, 'maxv' => 100000, 'help' => 'Yeni varyantlar için varsayılan kritik stok eşiği.'],
        'seo.default_title' => ['input' => 'text', 'max' => 160, 'help' => 'Başlığı olmayan sayfalar için varsayılan.'],
        'seo.default_description' => ['input' => 'textarea', 'max' => 255, 'help' => 'Varsayılan meta açıklama.'],
        'legal.company_title' => ['input' => 'text', 'max' => 190, 'help' => 'Mesafeli satış sözleşmesinde satıcı unvanı.'],
        'legal.tax_office' => ['input' => 'text', 'max' => 120, 'help' => 'Vergi dairesi.'],
        'legal.tax_number' => ['input' => 'text', 'max' => 11, 'format' => 'tax_number', 'help' => '10 (vergi) veya 11 (TC) haneli numara.'],
        'legal.mersis' => ['input' => 'text', 'max' => 16, 'format' => 'mersis', 'help' => '16 haneli MERSİS numarası.'],
        'tax.prices_include_vat' => ['help' => 'Fiyatların KDV dahil girildiğini belirtir. Fiyat hesabını etkiler; muhasebecinizle teyit etmeden değiştirmeyin.'],
        'card.enabled' => ['help' => 'Kredi/banka kartı (PayTR). Anahtarlar .env içinde tutulur.'],
        'cod.enabled' => ['help' => 'Kapıda ödeme.'],
        'bank_transfer.enabled' => ['help' => 'Havale/EFT. Açıkken banka, hesap sahibi ve IBAN zorunludur.'],
    ];

    public function index(Request $request): Response
    {
        $this->require('setting.manage');

        $tabs = [];
        foreach (self::TABS as $id => [$label]) {
            $tabs[$id] = ['id' => $id, 'label' => $label, 'fields' => []];
        }

        foreach ($this->loadRows() as $key => $row) {
            $tabs[$this->tabFor($key)]['fields'][] = $this->describe($key, $row);
        }

        return $this->view('admin.settings.index', [
            'title' => 'Site ayarları',
            'tabs' => array_values(array_filter($tabs, static fn (array $t): bool => $t['fields'] !== [])),
        ]);
    }

    public function update(Request $request): Response
    {
        $this->require('setting.manage');

        $submitted = $request->post('settings', []);
        if (!is_array($submitted)) {
            throw new ValidationException(['form' => 'Geçersiz form verisi.'], []);
        }

        $rows = $this->loadRows();

        // Yeni anahtar enjeksiyonu: tabloda olmayan anahtar gelirse reddet + güvenlik olayı yaz.
        foreach (array_keys($submitted) as $k) {
            if (!is_string($k) || !isset($rows[$k])) {
                SecurityEvent::record('setting_unknown_key', 'warning', $this->userId(), $request->ip(), [
                    'key' => substr((string) $k, 0, 100),
                ]);
                throw new ValidationException(['form' => 'Tanımsız ayar anahtarı gönderildi.'], []);
            }
        }

        $errors = [];
        $oldInput = [];
        $newValues = [];   // anahtar => normalize edilmiş yeni değer (yalnızca gönderilenler)
        $oldValues = [];   // anahtar => normalize edilmiş eski değer

        foreach ($rows as $key => $row) {
            $type = (string) ($row['value_type'] ?? 'string');
            $oldValues[$key] = $this->normalizeStored($type, $row['value'] ?? null);

            if (!array_key_exists($key, $submitted)) {
                continue; // gönderilmediyse değişmez
            }

            $raw = $submitted[$key];
            if (!is_string($raw)) {
                $errors[$key] = 'Geçersiz değer.';
                continue;
            }
            $oldInput[$key] = $raw;

            [$value, $error] = $this->validateValue($key, $type, $raw);
            if ($error !== null) {
                $errors[$key] = $error;
                continue;
            }
            $newValues[$key] = $value;
        }

        // Etkin değerler (gönderilen varsa yeni, yoksa eski).
        $effective = static fn (string $k): mixed => array_key_exists($k, $newValues) ? $newValues[$k] : ($oldValues[$k] ?? null);

        // İş kuralı: en az bir ödeme yöntemi açık kalmalı.
        $anyEnabled = false;
        foreach (self::PAYMENT_KEYS as $pk) {
            if (isset($rows[$pk]) && $effective($pk) === true) {
                $anyEnabled = true;
            }
        }
        if (!$anyEnabled && !isset($errors['card.enabled'])) {
            $errors['card.enabled'] = 'En az bir ödeme yöntemi (kart, havale veya kapıda ödeme) açık kalmalıdır.';
        }

        // Havale açıksa banka bilgileri zorunlu.
        if (isset($rows['bank_transfer.enabled']) && $effective('bank_transfer.enabled') === true) {
            foreach (['bank_transfer.bank_name', 'bank_transfer.account_holder', 'bank_transfer.iban'] as $bk) {
                if (isset($rows[$bk]) && !isset($errors[$bk]) && trim((string) $effective($bk)) === '') {
                    $errors[$bk] = 'Havale açıkken bu alan zorunludur.';
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors, ['settings' => $oldInput]);
        }

        $old = [];
        $new = [];
        foreach ($newValues as $key => $value) {
            if ($value !== $oldValues[$key]) {
                $old[$key] = $oldValues[$key];
                $new[$key] = $value;
            }
        }

        if ($new === []) {
            $this->flash('info', 'Değişiklik yapılmadı.');
            return $this->redirect(url('/yonetim/ayarlar'));
        }

        $setting = new Setting();
        $userId = $this->userId();

        // Hepsi ya da hiçbiri: kısmi güncelleme tutarsız ayar bırakmasın.
        Database::instance()->transaction(static function () use ($setting, $new, $userId): void {
            foreach ($new as $key => $value) {
                $setting->set($key, $value, $userId);
            }
        });

        $this->audit('setting.update', 'settings', null, $old, $new);
        $this->flash('success', 'Ayarlar kaydedildi (' . count($new) . ' değişiklik).');

        if ($effective('card.enabled') === true && trim((string) Config::env('PAYTR_MERCHANT_ID', '')) === '') {
            $this->flash('warning', 'Kart ödemesi açık ancak .env içinde PayTR bilgileri tanımlı görünmüyor.');
        }

        return $this->redirect(url('/yonetim/ayarlar'));
    }

    /**
     * Setting::allByGroup() çıktısını (grup=>satırlar veya düz satır listesi) anahtar=>satır haritasına çevirir.
     * @return array<string,array<string,mixed>>
     */
    private function loadRows(): array
    {
        $rows = [];
        foreach ((new Setting())->allByGroup() as $group) {
            if (!is_array($group)) {
                continue;
            }
            $list = isset($group['key_name']) ? [$group] : $group;
            foreach ($list as $row) {
                if (is_array($row) && isset($row['key_name'])) {
                    $rows[(string) $row['key_name']] = $row;
                }
            }
        }

        return $rows;
    }

    private function tabFor(string $key): string
    {
        foreach (self::TABS as $id => [, $prefixes]) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    return $id;
                }
            }
        }

        return 'general';
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function describe(string $key, array $row): array
    {
        $type = (string) ($row['value_type'] ?? 'string');
        $meta = self::META[$key] ?? [];
        $stored = $this->normalizeStored($type, $row['value'] ?? null);

        $input = $meta['input'] ?? match ($type) {
            'bool' => 'checkbox',
            'int' => 'number',
            'decimal' => 'decimal',
            default => 'text',
        };

        $value = $stored;
        if ($input === 'iban' && is_string($stored)) {
            $value = trim(chunk_split($stored, 4, ' '));
        }

        return [
            'key' => $key,
            'label' => (string) ($row['label'] ?? $key),
            'type' => $type,
            'input' => $input,
            'value' => $value,
            'help' => (string) ($meta['help'] ?? ''),
            'max' => (int) ($meta['max'] ?? 255),
            'min' => $meta['min'] ?? null,
            'maxv' => $meta['maxv'] ?? null,
            'required' => (bool) ($meta['required'] ?? false),
        ];
    }

    /** Depolanan ham değeri karşılaştırılabilir tipe çevirir. */
    private function normalizeStored(string $type, mixed $raw): mixed
    {
        switch ($type) {
            case 'bool':
                return in_array($raw, [1, '1', true, 'true', 'on'], true);
            case 'int':
                return (int) $raw;
            case 'decimal':
                $s = str_replace(',', '.', trim((string) $raw));
                try {
                    return Money::fromCents(Money::toCents($s === '' ? '0' : $s));
                } catch (InvalidArgumentException) {
                    return $s;
                }
            default:
                return (string) ($raw ?? '');
        }
    }

    /**
     * Tek değeri tipine ve anahtara özgü kurallara göre doğrular.
     * @return array{0:mixed,1:?string} [normalize değer, hata]
     */
    private function validateValue(string $key, string $type, string $raw): array
    {
        $meta = self::META[$key] ?? [];

        if (!mb_check_encoding($raw, 'UTF-8')) {
            return [null, 'Geçersiz karakter.'];
        }

        // Textarea için satır sonu korunur; diğer kontrol karakterleri atılır.
        $raw = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $raw));

        switch ($type) {
            case 'bool':
                if (!in_array($raw, ['0', '1', ''], true)) {
                    return [null, 'Geçersiz değer.'];
                }
                return [$raw === '1', null];

            case 'int':
                if (preg_match('/^\d{1,9}$/', $raw) !== 1) {
                    return [null, 'Geçerli bir tam sayı girin.'];
                }
                $int = (int) $raw;
                $min = (int) ($meta['min'] ?? 0);
                $max = (int) ($meta['maxv'] ?? 999999999);
                if ($int < $min || $int > $max) {
                    return [null, 'Değer ' . $min . ' ile ' . $max . ' arasında olmalıdır.'];
                }
                return [$int, null];

            case 'decimal':
                $dec = str_replace(',', '.', str_replace(' ', '', $raw));
                if (preg_match('/^\d{1,7}(\.\d{1,2})?$/', $dec) !== 1) {
                    return [null, 'Geçerli bir tutar girin (0 veya üzeri, en çok 2 ondalık).'];
                }
                return [Money::fromCents(Money::toCents($dec)), null];

            case 'json':
                if ($raw !== '' && json_decode($raw, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                    return [null, 'Geçerli bir JSON girin.'];
                }
                return [$raw, null];
        }

        // string
        $max = (int) ($meta['max'] ?? 255);
        if (($meta['required'] ?? false) && $raw === '') {
            return [null, 'Bu alan zorunludur.'];
        }
        if (mb_strlen($raw) > $max) {
            return [null, 'En çok ' . $max . ' karakter olabilir.'];
        }
        if ($raw === '') {
            return ['', null];
        }

        switch ($meta['format'] ?? '') {
            case 'email':
                if (filter_var($raw, FILTER_VALIDATE_EMAIL) === false) {
                    return [null, 'Geçerli bir e-posta adresi girin.'];
                }
                return [$raw, null];

            case 'url':
                $scheme = strtolower((string) parse_url($raw, PHP_URL_SCHEME));
                if (filter_var($raw, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
                    return [null, 'http:// veya https:// ile başlayan geçerli bir adres girin.'];
                }
                return [$raw, null];

            case 'phone':
                if (preg_match('/^[0-9+()\s-]{7,30}$/', $raw) !== 1) {
                    return [null, 'Geçerli bir telefon numarası girin.'];
                }
                return [$raw, null];

            case 'whatsapp':
                $digits = ltrim(str_replace([' ', '-'], '', $raw), '+');
                if (preg_match('/^\d{10,15}$/', $digits) !== 1) {
                    return [null, 'Ülke koduyla 10–15 haneli numara girin (örn. 905320000000).'];
                }
                return [$digits, null];

            case 'iban':
                $iban = strtoupper(str_replace([' ', '-'], '', $raw));
                if (preg_match('/^TR\d{24}$/', $iban) !== 1) {
                    return [null, 'IBAN, TR ile başlamalı ve toplam 26 karakter olmalıdır.'];
                }
                return [$iban, null];

            case 'tax_number':
                if (preg_match('/^\d{10,11}$/', $raw) !== 1) {
                    return [null, '10 veya 11 haneli numara girin.'];
                }
                return [$raw, null];

            case 'mersis':
                if (preg_match('/^\d{16}$/', $raw) !== 1) {
                    return [null, '16 haneli MERSİS numarası girin.'];
                }
                return [$raw, null];
        }

        return [$raw, null];
    }

    private function userId(): ?int
    {
        return \App\Core\Auth::id();
    }
}