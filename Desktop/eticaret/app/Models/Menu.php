<?php
// app/Models/Menu.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Menüler (menus). Konum başına tek menü (UNIQUE location). */
class Menu extends BaseModel
{
    protected string $table = 'menus';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'location', 'is_active'];
    protected bool $softDeletes = false;
    protected array $sortable = ['id', 'name', 'location'];

    /** Yalnızca aktif menü döner (vitrin kullanımı); yönetim için allWithItemCounts(). */
    public function findByLocation(string $location): ?array
    {
        return Database::instance()->fetchOne(
            'SELECT * FROM menus WHERE location = :l AND is_active = 1 LIMIT 1',
            [':l' => $location]
        );
    }

    public function allWithItemCounts(): array
    {
        return Database::instance()->fetchAll(
            'SELECT m.*, (SELECT COUNT(*) FROM menu_items mi WHERE mi.menu_id = m.id) AS item_count
               FROM menus m ORDER BY m.id ASC'
        );
    }
}