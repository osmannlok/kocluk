<?php

declare(strict_types=1);

namespace App\Services;

class AdSlot
{
    private static ?array $cache = null;

    public static function render(string $placement): string
    {
        $slot = self::findByPlacement($placement);

        if ($slot === null || (int) $slot['is_active'] !== 1 || empty($slot['code'])) {
            return '';
        }

        return $slot['code'];
    }

    private static function findByPlacement(string $placement): ?array
    {
        if (self::$cache === null) {
            self::$cache = self::loadAll();
        }

        return self::$cache[$placement] ?? null;
    }

    private static function loadAll(): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare(
            'SELECT placement, code, is_active
             FROM ads_slots'
        );
        $stmt->execute();

        $slots = [];
        foreach ($stmt->fetchAll() as $row) {
            $slots[$row['placement']] = $row;
        }

        return $slots;
    }
}