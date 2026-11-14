<?php
// app/Controllers/Admin/RoleAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;

/**
 * Yönetim: rol yetki matrisi (yalnızca role.manage). Yeni rol oluşturma YOK.
 */
final class RoleAdminController extends AdminBaseController
{
    private const PERMISSION = 'role.manage';
    private const LIST_PATH = '/yonetim/roller';
    /** Yetkileri azaltılamayan korumalı rol (roles.name sözleşmesi). */
    private const PROTECTED_ROLE = 'admin';
    /** Başka role verilirse "critical" olay üreten hassas yetkiler. */
    private const SENSITIVE = ['user.manage', 'role.manage', 'setting.manage', 'audit.view', 'order.refund', 'product.delete'];

    public function index(Request $request): Response
    {
        $this->require(self::PERMISSION);

        $roleModel = new Role();
        $userModel = new User();
        $rows = [];
        foreach ($roleModel->allWithUserCounts() as $role) {
            $id = (int) $role['id'];
            $rows[] = [
                'id' => $id,
                'name' => (string) $role['name'],
                'label' => (string) ($role['label'] ?? $role['name']),
                'description' => $role['description'] ?? null,
                'user_count' => $userModel->count(['role_id' => $id]),
                'permission_count' => count($roleModel->permissionCodes($id)),
                'is_protected' => (string) $role['name'] === self::PROTECTED_ROLE,
            ];
        }

        return $this->view('admin.roles.index', [
            'title' => 'Roller',
            'roles' => $rows,
            'totalPermissions' => count($this->permissionMap()),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->require(self::PERMISSION);
        $role = $this->loadRole($id);

        return $this->view('admin.roles.edit', [
            'title' => 'Rol yetkileri: ' . (string) ($role['label'] ?? $role['name']),
            'role' => $role,
            'groups' => (new Permission())->allGroupedByModule(),
            'selectedCodes' => (new Role())->permissionCodes($id),
            'isLocked' => (string) $role['name'] === self::PROTECTED_ROLE,
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->require(self::PERMISSION);
        $role = $this->loadRole($id);
        $back = self::LIST_PATH . '/' . $id . '/duzenle';
        $roleModel = new Role();

        // Beyaz liste: yalnızca sistemde var olan permission kimlikleri kabul edilir.
        $map = $this->permissionMap();
        $raw = $request->post('permission_ids', []);
        $submittedIds = [];
        foreach (is_array($raw) ? $raw : [] as $value) {
            $value = (string) $value;
            if (!ctype_digit($value) || !isset($map[(int) $value])) {
                SecurityEvent::record('role_invalid_permission', 'warning', Auth::id(), $request->ip(), [
                    'role_id' => $id,
                    'value' => mb_substr($value, 0, 20),
                ]);
                throw new ValidationException(['permission_ids' => 'Geçersiz yetki seçimi.']);
            }
            $submittedIds[(int) $value] = true;
        }
        $submittedIds = array_keys($submittedIds);

        $newCodes = [];
        foreach ($submittedIds as $pid) {
            $newCodes[] = (string) $map[$pid]['code'];
        }
        $currentCodes = $roleModel->permissionCodes($id);

        $added = array_values(array_diff($newCodes, $currentCodes));
        $removed = array_values(array_diff($currentCodes, $newCodes));
        sort($added);
        sort($removed);

        // Koruma: admin rolünün yetkisi azaltılamaz (kilitlenme ve yetki daraltma saldırısına karşı).
        if ((string) $role['name'] === self::PROTECTED_ROLE && $removed !== []) {
            SecurityEvent::record('admin_role_reduction_blocked', 'critical', Auth::id(), $request->ip(), [
                'role_id' => $id,
                'removed' => $removed,
            ]);
            $this->flash('danger', 'Yönetici rolünün yetkileri azaltılamaz.');

            return $this->redirect($back);
        }

        if ($added === [] && $removed === []) {
            $this->flash('info', 'Yetkilerde değişiklik yok.');

            return $this->redirect($back);
        }

        $roleModel->syncPermissions($id, $submittedIds);

        sort($currentCodes);
        sort($newCodes);
        $this->audit('role.permissions_update', 'role', $id, [
            'permissions' => $currentCodes,
        ], [
            'permissions' => $newCodes,
            'added' => $added,
            'removed' => $removed,
        ]);

        $sensitiveAdded = array_values(array_intersect($added, self::SENSITIVE));
        SecurityEvent::record(
            'role_permissions_changed',
            $sensitiveAdded !== [] ? 'critical' : 'warning',
            Auth::id(),
            $request->ip(),
            [
                'role_id' => $id,
                'role_name' => (string) $role['name'],
                'added' => $added,
                'removed' => $removed,
            ]
        );

        $this->flash('success', 'Rol yetkileri güncellendi.');

        return $this->redirect($back);
    }

    /** @return array<string,mixed> */
    private function loadRole(int $id): array
    {
        $role = (new Role())->find($id);
        if ($role === null) {
            throw new HttpException(404, 'Rol bulunamadı.');
        }

        return $role;
    }

    /** @return array<int,array<string,mixed>> permission id => satır */
    private function permissionMap(): array
    {
        $map = [];
        foreach ((new Permission())->allGroupedByModule() as $list) {
            foreach ($list as $perm) {
                $map[(int) $perm['id']] = $perm;
            }
        }

        return $map;
    }
}