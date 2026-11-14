<?php // app/Services/AddressService.php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\Address;

/**
 * Adres işlemleri. Doğrulama tek yerde; her işlemde user_id sahipliği kontrol edilir (IDOR).
 */
final class AddressService
{
    private const MAX_PER_USER = 10;

    private const FIELDS = [
        'title', 'full_name', 'phone', 'city', 'district', 'neighborhood', 'address_line',
        'postal_code', 'invoice_type', 'tc_identity_no', 'company_name', 'tax_office', 'tax_number',
    ];

    private Address $addresses;

    public function __construct()
    {
        $this->addresses = new Address();
    }

    public function create(int $userId, array $input): int
    {
        $row = $this->validatePayload($input, null);
        $wantsDefault = $this->wantsDefault($input);

        return Database::instance()->transaction(function () use ($userId, $row, $wantsDefault): int {
            $count = $this->addresses->countForUser($userId);
            if ($count >= self::MAX_PER_USER) {
                throw new ValidationException(['address' => 'En fazla ' . self::MAX_PER_USER . ' adres kaydedebilirsiniz.']);
            }

            $row['user_id'] = $userId;
            $row['is_default'] = 0;
            $id = $this->addresses->create($row);

            // İlk adres otomatik varsayılan olur.
            if ($count === 0 || $wantsDefault) {
                $this->addresses->setDefault($id, $userId);
            }

            return $id;
        });
    }

    public function update(int $id, int $userId, array $input): bool
    {
        $existing = $this->addresses->findForUser($id, $userId);
        if ($existing === null) {
            return false;
        }

        $row = $this->validatePayload($input, $existing);
        $wantsDefault = $this->wantsDefault($input);

        Database::instance()->transaction(function () use ($id, $userId, $row, $wantsDefault): void {
            $this->addresses->update($id, $row);
            if ($wantsDefault) {
                $this->addresses->setDefault($id, $userId);
            }
        });

        return true;
    }

    public function delete(int $id, int $userId): bool
    {
        $existing = $this->addresses->findForUser($id, $userId);
        if ($existing === null) {
            return false;
        }

        Database::instance()->transaction(function () use ($id, $userId, $existing): void {
            $this->addresses->softDelete($id);

            // Varsayılan adres silindiyse kalan ilk adres varsayılan olur.
            if ((int) ($existing['is_default'] ?? 0) === 1) {
                $rest = $this->addresses->forUser($userId);
                if ($rest !== []) {
                    $this->addresses->setDefault((int) $rest[0]['id'], $userId);
                }
            }
        });

        return true;
    }

    public function setDefault(int $id, int $userId): void
    {
        if ($this->addresses->findForUser($id, $userId) === null) {
            throw new HttpException(404, 'Adres bulunamadı.');
        }
        $this->addresses->setDefault($id, $userId);
    }

    /**
     * İstemciye giden adres biçimi: TC kimlik no maskelenir (hassas veri ham halde sayfaya yazılmaz).
     *
     * @return array<string,mixed>
     */
    public function present(array $address): array
    {
        $tc = (string) ($address['tc_identity_no'] ?? '');

        return [
            'id' => (int) $address['id'],
            'title' => (string) $address['title'],
            'full_name' => (string) $address['full_name'],
            'phone' => (string) $address['phone'],
            'city' => (string) $address['city'],
            'district' => (string) $address['district'],
            'neighborhood' => (string) ($address['neighborhood'] ?? ''),
            'address_line' => (string) $address['address_line'],
            'postal_code' => (string) ($address['postal_code'] ?? ''),
            'is_default' => (bool) ($address['is_default'] ?? false),
            'invoice_type' => (string) $address['invoice_type'],
            'tc_identity_no' => $tc === '' ? '' : str_repeat('*', max(0, strlen($tc) - 4)) . substr($tc, -4),
            'company_name' => (string) ($address['company_name'] ?? ''),
            'tax_office' => (string) ($address['tax_office'] ?? ''),
            'tax_number' => (string) ($address['tax_number'] ?? ''),
        ];
    }

    private function wantsDefault(array $input): bool
    {
        return filter_var($input['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string,mixed> Address::create/update'e verilecek temiz satır (mass assignment yok)
     */
    private function validatePayload(array $input, ?array $existing): array
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $value = $input[$field] ?? null;
            $data[$field] = is_string($value) ? trim($value) : $value;
        }
        $data['invoice_type'] = ($data['invoice_type'] === null || $data['invoice_type'] === '') ? 'individual' : $data['invoice_type'];

        // Maskeli TC (****1234) geri gelirse mevcut değer korunur.
        if (is_string($data['tc_identity_no']) && str_contains($data['tc_identity_no'], '*')) {
            $data['tc_identity_no'] = $existing['tc_identity_no'] ?? null;
        }

        $corporate = $data['invoice_type'] === 'corporate';

        $rules = [
            'title' => 'required|string|max:60',
            'full_name' => 'required|string|max:160',
            'phone' => 'required|phone_tr',
            'city' => 'required|string|max:80',
            'district' => 'required|string|max:80',
            'neighborhood' => 'nullable|string|max:120',
            'address_line' => 'required|string|min:10|max:500',
            'postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'invoice_type' => 'required|in:individual,corporate',
        ];
        if ($corporate) {
            $rules['company_name'] = 'required|string|max:190';
            $rules['tax_office'] = 'required|string|max:120';
            $rules['tax_number'] = ['required', 'regex:/^\d{10,11}$/'];
        } else {
            $rules['tc_identity_no'] = 'nullable|tc_identity';
        }

        $validator = Validator::make($data, $rules, [
            'attributes' => [
                'title' => 'Adres başlığı',
                'full_name' => 'Ad soyad',
                'phone' => 'Telefon',
                'city' => 'İl',
                'district' => 'İlçe',
                'neighborhood' => 'Mahalle',
                'address_line' => 'Adres',
                'postal_code' => 'Posta kodu',
                'invoice_type' => 'Fatura tipi',
                'company_name' => 'Şirket adı',
                'tax_office' => 'Vergi dairesi',
                'tax_number' => 'Vergi numarası',
                'tc_identity_no' => 'TC kimlik numarası',
            ],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors(), $data);
        }

        $clean = $validator->validated();

        return [
            'title' => $clean['title'],
            'full_name' => $clean['full_name'],
            'phone' => $clean['phone'],
            'city' => $clean['city'],
            'district' => $clean['district'],
            'neighborhood' => $clean['neighborhood'] ?? null,
            'address_line' => $clean['address_line'],
            'postal_code' => $clean['postal_code'] ?? null,
            'invoice_type' => $clean['invoice_type'],
            'tc_identity_no' => $corporate ? null : (($clean['tc_identity_no'] ?? null) ?: null),
            'company_name' => $corporate ? $clean['company_name'] : null,
            'tax_office' => $corporate ? $clean['tax_office'] : null,
            'tax_number' => $corporate ? $clean['tax_number'] : null,
        ];
    }
}