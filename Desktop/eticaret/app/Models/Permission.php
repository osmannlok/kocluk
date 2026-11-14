<?php
// app/Models/Permission.php

declare(strict_types=1);

namespace App\Models;

final class Permission extends BaseModel
{
    protected string $table = 'permissions';
    protected bool $softDeletes = false;
    protected array $fillable = ['code', 'label', 'module'];
    protected array $extraColumns = ['created_at'];

    /**
     * @return array<string, list<array>> modül => yetki satırları
     */
    public function allGroupedByModule(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT `id`, `code`, `label`, `module` FROM `permissions` ORDER BY `module` ASC, `code` ASC'
        );

        $grouped = [];
        foreach ($rows as $row) {
            $row['id'] = (int) $row['id'];
            $grouped[(string) $row['module']][] = $row;
        }

        return $grouped;
    }

    /**
     * @param list<string> $codes
     * @return array<string, int> kod => id (bulunamayan kodlar sonuçta yer almaz)
     */
    public function idsByCodes(array $codes): array
    {
        $valid = [];
        foreach ($codes as $code) {
            if (is_string($code) && preg_match('/^[a-z0-9_.]{1,100}$/', $code) === 1) {
                $valid[$code] = $code;
            }
        }
        if ($valid === []) {
            return [];
        }

        [$in, $params] = $this->inClause('`code`', array_values($valid), 'code');
        $rows = $this->db->fetchAll('SELECT `id`, `code` FROM `permissions` WHERE ' . $in, $params);

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['code']] = (int) $row['id'];
        }

        return $map;
    }
}