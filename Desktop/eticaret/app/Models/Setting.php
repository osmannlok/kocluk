<?php
// app/Models/Setting.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Site ayarları (settings; PK key_name). Yeni anahtar yaratılmaz, yalnızca seed edilenler güncellenir. */
class Setting extends BaseModel
{
    protected string $table = 'settings';
    protected string $primaryKey = 'key_name';
    protected array $fillable = ['value', 'updated_by'];
    protected bool $softDeletes = false;
    protected array $sortable = ['key_name', 'group_name'];

    /** İstek içi önbellek (ayar tablosu küçüktür; tek sorguda yüklenir). */
    private static ?array $rows = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $rows = $this->loadRows();
        if (!isset($rows[$key])) {
            return $default;
        }
        $v = $this->castValue($rows[$key]['value'] !== null ? (string) $rows[$key]['value'] : null, (string) $rows[$key]['value_type']);
        return $v ?? $default;
    }

    /** anahtar => tipli değer (olmayan anahtar null). */
    public function getMany(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[(string) $k] = $this->get((string) $k, null);
        }
        return $out;
    }

    public function set(string $key, mixed $value, ?int $userId): void
    {
        $row = Database::instance()->fetchOne(
            'SELECT value_type FROM settings WHERE key_name = :k LIMIT 1',
            [':k' => $key]
        );
        if ($row === null) {
            throw new \InvalidArgumentException('Bilinmeyen ayar anahtarı: ' . $key);
        }
        $stored = $this->serialize($value, (string) $row['value_type']);
        Database::instance()->execute(
            'UPDATE settings SET value = :v, updated_by = :u WHERE key_name = :k',
            [':v' => $stored, ':u' => $userId, ':k' => $key]
        );
        self::$rows = null;
    }

    /** Yönetim ekranı için: grup => satırlar (ham değer). Gizli ayarlar da dahildir; yalnızca yönetimde kullanın. */
    public function allByGroup(): array
    {
        $out = [];
        foreach ($this->loadRows() as $r) {
            $out[(string) $r['group_name']][] = $r;
        }
        return $out;
    }

    /** Vitrine açık (is_public=1) ayarlar: anahtar => tipli değer. */
    public function publicAll(): array
    {
        $out = [];
        foreach ($this->loadRows() as $k => $r) {
            if ((int) $r['is_public'] === 1) {
                $out[$k] = $this->castValue($r['value'] !== null ? (string) $r['value'] : null, (string) $r['value_type']);
            }
        }
        return $out;
    }

    private function loadRows(): array
    {
        if (self::$rows === null) {
            self::$rows = [];
            $all = Database::instance()->fetchAll(
                'SELECT key_name, value, value_type, group_name, label, is_public FROM settings ORDER BY group_name ASC, key_name ASC'
            );
            foreach ($all as $r) {
                self::$rows[(string) $r['key_name']] = $r;
            }
        }
        return self::$rows;
    }

    /** decimal para olduğu için float'a çevrilmez; string kalır. */
    private function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        switch ($type) {
            case 'int':
                return (int) $value;
            case 'bool':
                return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
            case 'json':
                try {
                    return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    return null;
                }
            default:
                return $value;
        }
    }

    private function serialize(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }
        switch ($type) {
            case 'int':
                $i = filter_var($value, FILTER_VALIDATE_INT);
                if ($i === false) {
                    throw new \InvalidArgumentException('Tam sayı bekleniyor.');
                }
                return (string) $i;
            case 'decimal':
                if (is_float($value) || preg_match('/^-?\d{1,10}(\.\d{1,2})?$/', (string) $value) !== 1) {
                    throw new \InvalidArgumentException('Ondalık değer "1234.50" biçiminde olmalı.');
                }
                return (string) $value;
            case 'bool':
                $b = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($b === null) {
                    throw new \InvalidArgumentException('Mantıksal değer bekleniyor.');
                }
                return $b ? '1' : '0';
            case 'json':
                if (is_string($value)) {
                    json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    return $value;
                }
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            default:
                if (!is_scalar($value)) {
                    throw new \InvalidArgumentException('Metin bekleniyor.');
                }
                return (string) $value;
        }
    }
}