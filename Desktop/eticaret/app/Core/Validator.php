<?php
// app/Core/Validator.php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use InvalidArgumentException;

final class Validator
{
    private const NAME_PATTERN = '/^[a-z0-9_]+$/';

    private const DEFAULT_MESSAGES = [
        'required' => ':attribute alanı zorunludur.',
        'string' => ':attribute metin olmalıdır.',
        'integer' => ':attribute tam sayı olmalıdır.',
        'numeric' => ':attribute sayısal olmalıdır.',
        'boolean' => ':attribute geçersiz.',
        'array' => ':attribute geçersiz.',
        'email' => 'Geçerli bir e-posta adresi giriniz.',
        'min.string' => ':attribute en az :min karakter olmalıdır.',
        'min.numeric' => ':attribute en az :min olmalıdır.',
        'min.array' => ':attribute en az :min öğe içermelidir.',
        'max.string' => ':attribute en fazla :max karakter olmalıdır.',
        'max.numeric' => ':attribute en fazla :max olmalıdır.',
        'max.array' => ':attribute en fazla :max öğe içermelidir.',
        'between.string' => ':attribute :min ile :max karakter arasında olmalıdır.',
        'between.numeric' => ':attribute :min ile :max arasında olmalıdır.',
        'between.array' => ':attribute :min ile :max öğe arasında olmalıdır.',
        'in' => 'Seçilen :attribute geçersiz.',
        'regex' => ':attribute biçimi geçersiz.',
        'confirmed' => ':attribute doğrulaması eşleşmiyor.',
        'phone_tr' => 'Geçerli bir cep telefonu numarası giriniz.',
        'tc_identity' => 'Geçerli bir T.C. kimlik numarası giriniz.',
        'date' => 'Geçerli bir tarih giriniz.',
        'url' => 'Geçerli bir bağlantı (http/https) giriniz.',
        'slug' => ':attribute yalnızca küçük harf, rakam ve tire içermelidir.',
        'unique' => 'Bu :attribute zaten kullanılıyor.',
        'exists' => 'Seçilen :attribute geçersiz.',
    ];

    /** @var array<string,mixed> */
    private array $data;
    /** @var array<string,mixed> */
    private array $rules;
    /** @var array<string,mixed> */
    private array $messages;
    /** @var array<string,string> */
    private array $errors = [];
    /** @var array<string,mixed> */
    private array $validated = [];

    private function __construct(array $data, array $rules, array $messages)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    // messages: 'alan.kural' => metin, 'kural' => metin, 'attributes' => [alan => etiket]
    public static function make(array $data, array $rules, array $messages = []): self
    {
        $v = new self($data, $rules, $messages);
        $v->run();
        return $v;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> alan => ilk hata mesajı */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Yalnızca kuralı olan alanlar; tipler dönüştürülmüş (numeric string kalır: para float'a çevrilmez). */
    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $set) {
            $field = (string) $field;
            $pairs = $this->parse($set);
            $names = array_column($pairs, 0);
            $present = array_key_exists($field, $this->data);
            $value = $present ? $this->clean($field, $this->data[$field]) : null;
            $isBool = in_array('boolean', $names, true);
            $numericCtx = in_array('integer', $names, true) || in_array('numeric', $names, true);

            if ($value === null || $value === '' || $value === []) {
                if (in_array('required', $names, true)) {
                    $this->errors[$field] = $this->message($field, 'required', [], $value, $numericCtx);
                    continue;
                }
                if ($isBool) {
                    $this->validated[$field] = false; // işaretlenmemiş checkbox
                    continue;
                }
                if ($present || in_array('nullable', $names, true)) {
                    $this->validated[$field] = null;
                }
                continue;
            }

            foreach ($pairs as [$rule, $args]) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                if (!$this->check($field, $rule, $args, $value, $numericCtx)) {
                    $this->errors[$field] = $this->message($field, $rule, $args, $value, $numericCtx);
                    continue 2; // alan başına ilk hata
                }
            }
            $this->validated[$field] = $value;
        }
    }

    /** @return list<array{0:string,1:list<string>}> */
    private function parse(array|string $set): array
    {
        $items = is_array($set) ? $set : explode('|', $set); // regex içinde '|' varsa dizi biçimi kullanılmalı
        $pairs = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $pos = strpos($item, ':');
            if ($pos === false) {
                $pairs[] = [$item, []];
                continue;
            }
            $name = substr($item, 0, $pos);
            $arg = substr($item, $pos + 1);
            $pairs[] = [$name, $name === 'regex' ? [$arg] : explode(',', $arg)];
        }
        return $pairs;
    }

    private function clean(string $field, mixed $v): mixed
    {
        if (!is_string($v)) {
            return $v;
        }
        $v = str_replace("\0", '', $v); // NUL bayt temizliği
        // Parola alanları kırpılmaz: kullanıcının girdiği değer aynen korunur.
        return preg_match('/pass|parola|sifre/i', $field) === 1 ? $v : trim($v);
    }

    private function check(string $field, string $rule, array $args, mixed &$value, bool $numericCtx): bool
    {
        switch ($rule) {
            case 'string':
                return is_string($value);

            case 'integer':
                if (is_bool($value) || is_array($value)) {
                    return false;
                }
                $i = filter_var($value, FILTER_VALIDATE_INT);
                if ($i === false) {
                    return false;
                }
                $value = $i;
                return true;

            case 'numeric':
                if (is_bool($value) || is_array($value)) {
                    return false;
                }
                $s = (string) $value;
                if (preg_match('/^-?\d+(\.\d+)?$/', $s) !== 1) {
                    return false;
                }
                $value = $s;
                return true;

            case 'boolean':
                if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on') {
                    $value = true;
                    return true;
                }
                if ($value === false || $value === 0 || $value === '0' || $value === 'false' || $value === 'off') {
                    $value = false;
                    return true;
                }
                return false;

            case 'array':
                return is_array($value);

            case 'email':
                return is_string($value) && strlen($value) <= 254
                    && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            case 'min':
                return $this->size($value, $numericCtx) >= (float) $this->arg($args, 0);

            case 'max':
                return $this->size($value, $numericCtx) <= (float) $this->arg($args, 0);

            case 'between':
                $s = $this->size($value, $numericCtx);
                return $s >= (float) $this->arg($args, 0) && $s <= (float) $this->arg($args, 1);

            case 'in':
                return is_scalar($value) && in_array((string) $value, $args, true);

            case 'regex':
                $pattern = $this->arg($args, 0);
                $r = @preg_match($pattern, is_scalar($value) ? (string) $value : '');
                if ($r === false) {
                    Logger::warning('Geçersiz regex kuralı', ['field' => $field]);
                    return false;
                }
                return $r === 1;

            case 'confirmed':
                $other = $this->data[$field . '_confirmation'] ?? null;
                return is_string($value) && is_string($other) && hash_equals($value, $other);

            case 'phone_tr':
                if (!is_string($value)) {
                    return false;
                }
                $digits = (string) preg_replace('/[\s\-\(\)\.]/', '', $value);
                if (preg_match('/^(?:\+?90|0)?(5\d{9})$/', $digits, $m) !== 1) {
                    return false;
                }
                $value = '0' . $m[1];
                return true;

            case 'tc_identity':
                if (!is_string($value) && !is_int($value)) {
                    return false;
                }
                $s = (string) $value;
                if (preg_match('/^[1-9]\d{10}$/', $s) !== 1) {
                    return false;
                }
                $d = array_map('intval', str_split($s));
                $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
                $even = $d[1] + $d[3] + $d[5] + $d[7];
                if (((($odd * 7 - $even) % 10) + 10) % 10 !== $d[9]) {
                    return false;
                }
                return array_sum(array_slice($d, 0, 10)) % 10 === $d[10];

            case 'date':
                return is_string($value) && $this->normalizeDate($value, $value);

            case 'url':
                if (!is_string($value) || strlen($value) > 2048) {
                    return false;
                }
                $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                return in_array($scheme, ['http', 'https'], true)
                    && filter_var($value, FILTER_VALIDATE_URL) !== false;

            case 'slug':
                return is_string($value) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1;

            case 'unique':
            case 'exists':
                return $this->checkDatabase($rule, $args, $value);
        }

        throw new InvalidArgumentException("Bilinmeyen doğrulama kuralı: {$rule}");
    }

    private function checkDatabase(string $rule, array $args, mixed $value): bool
    {
        $table = (string) ($args[0] ?? '');
        $column = (string) ($args[1] ?? '');
        // Tablo/kolon adı parametre olamaz; yalnızca katı beyaz liste deseniyle SQL'e girer.
        if (preg_match(self::NAME_PATTERN, $table) !== 1 || preg_match(self::NAME_PATTERN, $column) !== 1) {
            throw new InvalidArgumentException('Geçersiz tablo/kolon adı.');
        }
        if (!is_scalar($value)) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :v";
        $params = ['v' => (string) $value];
        if ($rule === 'unique' && isset($args[2]) && $args[2] !== '') {
            if (!ctype_digit((string) $args[2])) {
                throw new InvalidArgumentException('unique istisna kimliği sayı olmalıdır.');
            }
            $sql .= ' AND `id` <> :except';
            $params['except'] = (int) $args[2];
        }

        $count = (int) Database::instance()->fetchValue($sql, $params);
        return $rule === 'unique' ? $count === 0 : $count > 0;
    }

    private function normalizeDate(string $in, mixed &$out): bool
    {
        $formats = [
            'Y-m-d' => true,
            'Y-m-d H:i:s' => false,
            'Y-m-d H:i' => false,
            'Y-m-d\TH:i:s' => false,
            'Y-m-d\TH:i' => false,
        ];
        foreach ($formats as $fmt => $dateOnly) {
            $dt = DateTimeImmutable::createFromFormat('!' . $fmt, $in);
            if ($dt !== false && $dt->format($fmt) === $in) {
                $out = $dt->format($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s');
                return true;
            }
        }
        return false;
    }

    private function size(mixed $v, bool $numericCtx): float|int
    {
        if ($numericCtx && is_numeric($v)) {
            return (float) $v;
        }
        if (is_array($v)) {
            return count($v);
        }
        return mb_strlen((string) $v);
    }

    private function arg(array $args, int $i): string
    {
        if (!isset($args[$i]) || $args[$i] === '') {
            throw new InvalidArgumentException('Kural parametresi eksik.');
        }
        return (string) $args[$i];
    }

    private function message(string $field, string $rule, array $args, mixed $value, bool $numericCtx): string
    {
        $custom = $this->messages[$field . '.' . $rule] ?? null;
        if ($custom === null && isset($this->messages[$rule]) && is_string($this->messages[$rule])) {
            $custom = $this->messages[$rule];
        }
        if (!is_string($custom)) {
            $key = $rule;
            if (in_array($rule, ['min', 'max', 'between'], true)) {
                $key .= '.' . ($numericCtx ? 'numeric' : (is_array($value) ? 'array' : 'string'));
            }
            $custom = self::DEFAULT_MESSAGES[$key] ?? ':attribute geçersiz.';
        }

        $attrs = $this->messages['attributes'] ?? [];
        $label = is_array($attrs) && isset($attrs[$field]) ? (string) $attrs[$field] : str_replace('_', ' ', $field);
        $min = (string) ($args[0] ?? '');
        $max = $rule === 'between' ? (string) ($args[1] ?? '') : (string) ($args[0] ?? '');

        return strtr($custom, [
            ':attribute' => $label,
            ':min' => $min,
            ':max' => $max,
            ':values' => implode(', ', $args),
        ]);
    }
}