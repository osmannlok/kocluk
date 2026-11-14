<?php
// app/Controllers/Admin/CouponAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Helpers\Money;
use App\Models\Coupon;
use App\Models\CouponUsage;
use DateTimeImmutable;

/**
 * Kupon yönetimi (coupon.manage).
 * Güvenlik: her metot require() ile korunur; tutarlar string -> kuruş ile doğrulanır,
 * kod beyaz liste desenine uyar, silme soft delete'tir ve her işlem audit_logs'a yazılır.
 */
final class CouponAdminController extends AdminBaseController
{
    private const CODE_PATTERN = '/^[A-Z0-9_-]{3,40}$/';
    private const AMOUNT_PATTERN = '/^\d{1,10}(\.\d{1,2})?$/';
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private function coupons(): Coupon
    {
        return new Coupon();
    }

    private function usages(): CouponUsage
    {
        return new CouponUsage();
    }

    public function index(Request $request): Response
    {
        $this->require('coupon.manage');

        $q = trim((string) $request->query('q', ''));
        if (!mb_check_encoding($q, 'UTF-8')) {
            $q = '';
        }
        $q = mb_substr($q, 0, 100);

        $status = (string) $request->query('status', '');
        $filters = ['q' => $q];
        if ($status === 'active') {
            $filters['is_active'] = 1;
        } elseif ($status === 'passive') {
            $filters['is_active'] = 0;
        } else {
            $status = '';
        }

        $result = $this->coupons()->adminList($filters, $this->currentPage($request), $this->perPage());

        return $this->view('admin.coupons.index', [
            'title' => 'Kuponlar',
            'coupons' => $result['items'],
            'pager' => $this->pager($result, $request),
            'q' => $q,
            'status' => $status,
            'now' => now(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->require('coupon.manage');

        // "Rastgele kod üret" bağlantısı (?uret=1): kod sunucuda kriptografik rastgelelikle üretilir.
        $code = '';
        if ((string) $request->query('uret', '') === '1') {
            $code = $this->generateCode();
        }

        return $this->view('admin.coupons.form', [
            'title' => 'Yeni kupon',
            'coupon' => [
                'code' => $code, 'description' => '', 'discount_type' => 'percent',
                'discount_value' => '', 'max_discount_amount' => '', 'min_cart_total' => '0.00',
                'usage_limit' => '', 'usage_per_user' => '', 'starts_at' => '', 'ends_at' => '',
                'is_active' => 1, 'used_count' => 0,
            ],
            'isEdit' => false,
            'stats' => null,
            'formAction' => url('/yonetim/kuponlar'),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->require('coupon.manage');

        $data = $this->collect($request, null);
        $data['created_by'] = Auth::id();
        $id = $this->coupons()->create($data);

        $this->audit('coupon.create', 'coupon', $id, null, $this->snapshot($data));
        $this->flash('success', 'Kupon oluşturuldu.');

        return $this->redirect(url('/yonetim/kuponlar'));
    }

    public function edit(Request $request, int $id): Response
    {
        $this->require('coupon.manage');
        $coupon = $this->findOrFail($id);

        return $this->view('admin.coupons.form', [
            'title' => 'Kuponu düzenle',
            'coupon' => $this->formValues($coupon),
            'isEdit' => true,
            'stats' => $this->stats($coupon),
            'formAction' => url('/yonetim/kuponlar/' . $id . '/guncelle'),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->require('coupon.manage');
        $coupon = $this->findOrFail($id);

        $data = $this->collect($request, $coupon);
        $this->coupons()->update($id, $data);

        $this->audit('coupon.update', 'coupon', $id, $this->snapshot($coupon), $this->snapshot($data));
        $this->flash('success', 'Kupon güncellendi.');

        return $this->redirect(url('/yonetim/kuponlar'));
    }

    public function toggle(Request $request, int $id): Response
    {
        $this->require('coupon.manage');
        $coupon = $this->findOrFail($id);

        $new = ((int) $coupon['is_active']) === 1 ? 0 : 1;
        $this->coupons()->update($id, ['is_active' => $new]);

        $this->audit('coupon.toggle', 'coupon', $id, ['is_active' => (int) $coupon['is_active']], ['is_active' => $new]);
        $this->flash('success', $new === 1 ? 'Kupon etkinleştirildi.' : 'Kupon devre dışı bırakıldı.');

        return $this->redirect(url('/yonetim/kuponlar'));
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->require('coupon.manage');
        $coupon = $this->findOrFail($id);

        // Soft delete: coupon_usages ve siparişlerdeki geçmiş kayıtlar korunur.
        $this->coupons()->softDelete($id);

        $this->audit('coupon.delete', 'coupon', $id, $this->snapshot($coupon), null);
        $this->flash('success', 'Kupon silindi.');

        return $this->redirect(url('/yonetim/kuponlar'));
    }

    /** @return array<string,mixed> */
    private function findOrFail(int $id): array
    {
        $coupon = $this->coupons()->find($id);
        if ($coupon === null) {
            throw new HttpException(404, 'Kupon bulunamadı.');
        }

        return $coupon;
    }

    /**
     * Kullanım istatistiği: used_count kupon satırından, toplam indirim coupon_usages kayıtlarından (kuruş toplamı).
     * @param array<string,mixed> $coupon
     * @return array{used_count:int,usage_rows:int,total_discount:string}
     */
    private function stats(array $coupon): array
    {
        $rows = $this->usages()->all(['coupon_id' => (int) $coupon['id']]);
        $cents = 0;
        foreach ($rows as $row) {
            $cents += Money::toCents((string) $row['discount_amount']);
        }

        return [
            'used_count' => (int) $coupon['used_count'],
            'usage_rows' => count($rows),
            'total_discount' => Money::fromCents($cents),
        ];
    }

    /**
     * Girdiyi doğrular; hata varsa ValidationException fırlatır.
     * @param array<string,mixed>|null $existing
     * @return array<string,mixed>
     */
    private function collect(Request $request, ?array $existing): array
    {
        $errors = [];

        $code = strtoupper($this->text($request, 'code', 40, 'Kupon kodu', $errors, true));
        if (!isset($errors['code'])) {
            if (preg_match(self::CODE_PATTERN, $code) !== 1) {
                $errors['code'] = 'Kupon kodu 3-40 karakter olmalı; yalnızca A-Z, 0-9, _ ve - kullanılabilir.';
            } elseif ($this->coupons()->codeExists($code, $existing !== null ? (int) $existing['id'] : null)) {
                $errors['code'] = 'Bu kupon kodu zaten kullanılıyor.';
            }
        }

        $description = $this->text($request, 'description', 255, 'Açıklama', $errors);

        $type = (string) $request->post('discount_type', '');
        if (!in_array($type, ['percent', 'fixed'], true)) {
            $errors['discount_type'] = 'İndirim türü geçersiz.';
            $type = '';
        }

        $valueRaw = $this->text($request, 'discount_value', 20, 'İndirim değeri', $errors, true);
        $valueCents = $this->parseAmount($valueRaw);
        $value = null;
        if (!isset($errors['discount_value'])) {
            if ($valueCents === null || $valueCents <= 0) {
                $errors['discount_value'] = 'İndirim değeri sıfırdan büyük geçerli bir sayı olmalı.';
            } elseif ($type === 'percent' && ($valueCents < 100 || $valueCents > 10000)) {
                $errors['discount_value'] = 'Yüzde indirim 1 ile 100 arasında olmalı.';
            } else {
                $value = Money::fromCents($valueCents);
            }
        }

        $maxRaw = $this->text($request, 'max_discount_amount', 20, 'Üst indirim sınırı', $errors);
        $maxDiscount = null;
        if ($maxRaw !== '' && !isset($errors['max_discount_amount'])) {
            $maxCents = $this->parseAmount($maxRaw);
            if ($type !== 'percent') {
                $errors['max_discount_amount'] = 'Üst indirim sınırı yalnızca yüzde kuponlarında kullanılabilir.';
            } elseif ($maxCents === null || $maxCents <= 0) {
                $errors['max_discount_amount'] = 'Üst indirim sınırı sıfırdan büyük geçerli bir tutar olmalı.';
            } else {
                $maxDiscount = Money::fromCents($maxCents);
            }
        }

        $minRaw = $this->text($request, 'min_cart_total', 20, 'Minimum sepet tutarı', $errors);
        $minCart = '0.00';
        if ($minRaw !== '' && !isset($errors['min_cart_total'])) {
            $minCents = $this->parseAmount($minRaw);
            if ($minCents === null) {
                $errors['min_cart_total'] = 'Minimum sepet tutarı geçerli bir tutar olmalı.';
            } else {
                $minCart = Money::fromCents($minCents);
            }
        }

        $usageLimit = $this->positiveInt($request, 'usage_limit', 'Toplam kullanım limiti', $errors);
        $usagePerUser = $this->positiveInt($request, 'usage_per_user', 'Kullanıcı başı limit', $errors);
        if ($usageLimit !== null && $existing !== null && $usageLimit < (int) $existing['used_count']) {
            $errors['usage_limit'] = 'Toplam kullanım limiti, mevcut kullanım sayısından (' . (int) $existing['used_count'] . ') küçük olamaz.';
        }

        $startsRaw = $this->text($request, 'starts_at', 20, 'Başlangıç tarihi', $errors);
        $endsRaw = $this->text($request, 'ends_at', 20, 'Bitiş tarihi', $errors);
        $startsAt = null;
        $endsAt = null;
        if ($startsRaw !== '' && !isset($errors['starts_at'])) {
            $startsAt = $this->parseDateTime($startsRaw);
            if ($startsAt === null) {
                $errors['starts_at'] = 'Başlangıç tarihi geçersiz.';
            }
        }
        if ($endsRaw !== '' && !isset($errors['ends_at'])) {
            $endsAt = $this->parseDateTime($endsRaw);
            if ($endsAt === null) {
                $errors['ends_at'] = 'Bitiş tarihi geçersiz.';
            }
        }
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            $errors['ends_at'] = 'Bitiş tarihi başlangıç tarihinden sonra olmalı.';
        }

        $isActive = $request->post('is_active') === '1' ? 1 : 0;

        if ($errors !== []) {
            throw new ValidationException($errors, [
                'code' => $code,
                'description' => $description,
                'discount_type' => $type,
                'discount_value' => $valueRaw,
                'max_discount_amount' => $maxRaw,
                'min_cart_total' => $minRaw,
                'usage_limit' => $usageLimit === null ? (string) $request->post('usage_limit', '') : (string) $usageLimit,
                'usage_per_user' => $usagePerUser === null ? (string) $request->post('usage_per_user', '') : (string) $usagePerUser,
                'starts_at' => $startsRaw,
                'ends_at' => $endsRaw,
                'is_active' => (string) $isActive,
            ]);
        }

        return [
            'code' => $code,
            'description' => $description !== '' ? $description : null,
            'discount_type' => $type,
            'discount_value' => $value,
            'max_discount_amount' => $maxDiscount,
            'min_cart_total' => $minCart,
            'usage_limit' => $usageLimit,
            'usage_per_user' => $usagePerUser,
            'starts_at' => $startsAt?->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
            'is_active' => $isActive,
        ];
    }

    /** @param array<string,string> $errors */
    private function text(Request $request, string $key, int $max, string $label, array &$errors, bool $required = false): string
    {
        $value = $request->post($key, '');
        $value = is_string($value) ? trim($value) : '';

        if (!mb_check_encoding($value, 'UTF-8')) {
            $errors[$key] = $label . ' geçersiz karakter içeriyor.';
            return '';
        }

        $value = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        if ($required && $value === '') {
            $errors[$key] = $label . ' zorunludur.';
        } elseif (mb_strlen($value) > $max) {
            $errors[$key] = $label . ' en çok ' . $max . ' karakter olabilir.';
        }

        return $value;
    }

    /** @param array<string,string> $errors */
    private function positiveInt(Request $request, string $key, string $label, array &$errors): ?int
    {
        $raw = $request->post($key, '');
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{1,9}$/', $raw) !== 1 || (int) $raw < 1) {
            $errors[$key] = $label . ' en az 1 olan bir tam sayı olmalı.';
            return null;
        }

        return (int) $raw;
    }

    /** Tutar metnini (virgül veya nokta) kuruşa çevirir; geçersizse null. */
    private function parseAmount(string $raw): ?int
    {
        $raw = str_replace(',', '.', trim($raw));
        if (preg_match(self::AMOUNT_PATTERN, $raw) !== 1) {
            return null;
        }

        return Money::toCents($raw);
    }

    private function parseDateTime(string $raw): ?DateTimeImmutable
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $dt = DateTimeImmutable::createFromFormat('!' . $format, $raw);
            $err = DateTimeImmutable::getLastErrors();
            if ($dt !== false && ($err === false || ($err['warning_count'] === 0 && $err['error_count'] === 0))) {
                return $dt;
            }
        }

        return null;
    }

    private function generateCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * Formda gösterilecek değerler (datetime-local biçimi).
     * @param array<string,mixed> $coupon
     * @return array<string,mixed>
     */
    private function formValues(array $coupon): array
    {
        foreach (['starts_at', 'ends_at'] as $key) {
            $raw = $coupon[$key] ?? null;
            $coupon[$key] = is_string($raw) && $raw !== ''
                ? str_replace(' ', 'T', substr($raw, 0, 16))
                : '';
        }
        foreach (['max_discount_amount', 'usage_limit', 'usage_per_user'] as $key) {
            $coupon[$key] = $coupon[$key] ?? '';
        }

        return $coupon;
    }

    /**
     * Denetim kaydı özeti.
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function snapshot(array $row): array
    {
        $keys = [
            'code', 'discount_type', 'discount_value', 'max_discount_amount', 'min_cart_total',
            'usage_limit', 'usage_per_user', 'starts_at', 'ends_at', 'is_active',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $out[$key] = $row[$key];
            }
        }

        return $out;
    }
}