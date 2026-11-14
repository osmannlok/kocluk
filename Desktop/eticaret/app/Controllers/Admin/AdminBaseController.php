<?php
// app/Controllers/Admin/AdminBaseController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\SecurityEvent;

/**
 * Tüm yönetim controller'larının tabanı.
 * Her metot en başta require() çağırır; route middleware'ine EK savunma katmanıdır.
 */
abstract class AdminBaseController extends BaseController
{
    /** Yönetim listelerinde sabit sayfa boyutu. */
    protected const PER_PAGE = 25;

    protected function view(string $name, array $d = [], string $layout = 'layouts.admin'): Response
    {
        return parent::view($name, $d, $layout);
    }

    /**
     * Yetki kontrolü. Neden: middleware atlanır/yanlış yapılandırılırsa bile
     * controller içinde varsayılan "reddet" davranışı korunur; ihlal güvenlik olayı olarak kaydedilir.
     */
    protected function require(string $permission): void
    {
        if (Auth::can($permission)) {
            return;
        }

        $request = Request::capture();
        SecurityEvent::record(
            'permission_denied',
            'warning',
            Auth::id(),
            $request->ip(),
            [
                'permission' => $permission,
                'path' => $request->path(),
                'method' => $request->method(),
            ]
        );

        throw new HttpException(403, 'Bu işlem için yetkiniz yok.');
    }

    /**
     * Yönetici işlemini denetim günlüğüne yazar (kim, ne, eski/yeni, IP).
     * Neden: iç tehdit durumunda izlenebilirlik; günlük hatası işlemi de durdurur (sessizce kaybolmaz).
     */
    protected function audit(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $old = null,
        ?array $new = null
    ): void {
        $request = Request::capture();

        (new AuditLog())->write(
            Auth::id(),
            $action,
            $entityType,
            $entityId,
            $old,
            $new,
            ip_to_bin($request->ip()),
            $request->userAgent()
        );
    }

    /** Geçerli sayfa numarası (en az 1). */
    protected function currentPage(Request $request): int
    {
        $page = $request->query('page', 1);

        return is_scalar($page) ? max(1, (int) $page) : 1;
    }

    /** Sabit sayfa boyutu. */
    protected function perPage(): int
    {
        return self::PER_PAGE;
    }

    /**
     * Model SAYFALI sonucunu partials.admin-pagination için hazırlar.
     * Sorgu parametreleri (filtreler) korunur, page çıkarılır.
     *
     * @param array{items?:array,total?:int,page?:int,per_page?:int,pages?:int} $result
     * @return array{total:int,page:int,per_page:int,pages:int,path:string,query:array}
     */
    protected function pager(array $result, Request $request): array
    {
        $query = $request->query();
        $query = is_array($query) ? $query : [];
        unset($query['page']);

        $total = (int) ($result['total'] ?? 0);
        $perPage = max(1, (int) ($result['per_page'] ?? self::PER_PAGE));

        return [
            'total' => $total,
            'page' => max(1, (int) ($result['page'] ?? 1)),
            'per_page' => $perPage,
            'pages' => max(1, (int) ($result['pages'] ?? (int) ceil($total / $perPage))),
            'path' => $request->path(),
            'query' => $query,
        ];
    }
}