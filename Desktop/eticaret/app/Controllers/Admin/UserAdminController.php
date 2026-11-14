<?php
// app/Controllers/Admin/UserAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSession;
use App\Services\AuthService;

/**
 * Yönetim: kullanıcı yönetimi (yalnızca user.manage).
 * Parola ASLA gösterilmez/ayarlanmaz; kullanıcıya parola belirleme bağlantısı gider.
 */
final class UserAdminController extends AdminBaseController
{
    private const PERMISSION = 'user.manage';
    private const LIST_PATH = '/yonetim/kullanicilar';
    private const STATUS_LABELS = [
        'active' => 'Aktif',
        'suspended' => 'Askıda',
        'pending' => 'Beklemede',
    ];

    private ?User $userModel = null;
    private ?Role $roleModel = null;
    private ?UserSession $sessionModel = null;

    public function index(Request $request): Response
    {
        $this->require(self::PERMISSION);

        $filters = $this->readFilters($request);
        $result = $this->users()->searchAdmin($filters, $this->currentPage($request), $this->perPage());

        return $this->view('admin.users.index', [
            'title' => 'Kullanıcılar',
            'users' => $result['items'],
            'pager' => $this->pager($result, $request),
            'filters' => $filters,
            'roles' => $this->roles()->allWithUserCounts(),
            'statuses' => self::STATUS_LABELS,
            'currentUserId' => Auth::id(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->require(self::PERMISSION);

        return $this->view('admin.users.form', [
            'title' => 'Yeni kullanıcı',
            'user' => null,
            'roles' => $this->roles()->allWithUserCounts(),
            'statuses' => self::STATUS_LABELS,
            'isSelf' => false,
            'isLastPrivileged' => false,
            'isLocked' => false,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->require(self::PERMISSION);

        $data = $this->validate($request, [
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|phone_tr',
            // Rol kimliği yalnızca mevcut rollerin beyaz listesinden gelebilir.
            'role_id' => 'required|integer|in:' . implode(',', $this->roleIds()),
        ], ['attributes' => $this->attributeLabels()]);

        $email = mb_strtolower(trim((string) $data['email']));
        if ($this->users()->emailExists($email)) {
            throw new ValidationException(
                ['email' => 'Bu e-posta adresi zaten kayıtlı.'],
                [
                    'first_name' => (string) $request->post('first_name', ''),
                    'last_name' => (string) $request->post('last_name', ''),
                    'email' => $email,
                    'phone' => (string) $request->post('phone', ''),
                    'role_id' => (string) $request->post('role_id', ''),
                ]
            );
        }

        $roleId = (int) $data['role_id'];
        $role = $this->roles()->find($roleId);
        if ($role === null) {
            throw new HttpException(422, 'Geçersiz rol.');
        }

        // Rastgele parola: kimse bilmez; kullanıcı bağlantıyla kendi parolasını belirler.
        $newId = $this->users()->create([
            'role_id' => $roleId,
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'email' => $email,
            'phone' => ($data['phone'] ?? null) ?: null,
            'password_hash' => (string) password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID),
            'status' => 'pending',
            'marketing_consent' => 0,
        ]);

        $this->audit('user.create', 'user', $newId, null, [
            'email' => $email,
            'role_id' => $roleId,
            'role_name' => (string) $role['name'],
            'status' => 'pending',
        ]);

        if ($this->isPrivilegedRole($roleId)) {
            // Yetkili rolle hesap açmak iç tehdit açısından izlenir.
            SecurityEvent::record('privileged_user_created', 'warning', Auth::id(), $request->ip(), [
                'target_user_id' => $newId,
                'role_name' => (string) $role['name'],
            ]);
        }

        $this->sendLink($email, $request);
        $this->flash('success', 'Kullanıcı oluşturuldu. Parola belirleme bağlantısı e-posta adresine gönderildi.');

        return $this->redirect(self::LIST_PATH);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->require(self::PERMISSION);
        $user = $this->loadTarget($id);

        return $this->view('admin.users.form', [
            'title' => 'Kullanıcıyı düzenle',
            'user' => $user,
            'roles' => $this->roles()->allWithUserCounts(),
            'statuses' => self::STATUS_LABELS,
            'isSelf' => $id === Auth::id(),
            'isLastPrivileged' => $this->isLastActivePrivileged($user),
            'isLocked' => $this->isLockedNow($user),
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->require(self::PERMISSION);
        $target = $this->loadTarget($id);
        $back = self::LIST_PATH . '/' . $id . '/duzenle';

        $data = $this->validate($request, [
            'first_name' => 'required|string|max:80',
            'last_name' => 'required|string|max:80',
            'phone' => 'nullable|phone_tr',
            'role_id' => 'required|integer|in:' . implode(',', $this->roleIds()),
            'status' => 'required|in:active,suspended,pending',
        ], ['attributes' => $this->attributeLabels()]);

        $newRoleId = (int) $data['role_id'];
        $newStatus = (string) $data['status'];
        $oldRoleId = (int) $target['role_id'];
        $oldStatus = (string) $target['status'];
        $roleChanged = $newRoleId !== $oldRoleId;
        $statusChanged = $newStatus !== $oldStatus;

        // Koruma: yönetici kendi rolünü/durumunu düşüremez (yetki kaybı ve kilitlenme riski).
        if ($id === Auth::id() && ($roleChanged || $statusChanged)) {
            return $this->block($request, 'self_change', $id, 'Kendi rolünüzü veya durumunuzu değiştiremezsiniz.', $back);
        }

        // Koruma: sistemdeki SON aktif yetkili hesap düşürülemez.
        if (
            $this->isLastActivePrivileged($target)
            && (!$this->isPrivilegedRole($newRoleId) || $newStatus !== 'active')
        ) {
            return $this->block($request, 'last_admin_downgrade', $id, 'Son aktif yönetici hesabının rolü veya durumu değiştirilemez.', $back);
        }

        $newRole = $this->roles()->find($newRoleId);
        if ($newRole === null) {
            throw new HttpException(422, 'Geçersiz rol.');
        }

        $old = $this->snapshot($target);

        $this->users()->update($id, [
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'phone' => ($data['phone'] ?? null) ?: null,
            'role_id' => $newRoleId,
            'status' => $newStatus,
        ]);

        $new = [
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'phone' => ($data['phone'] ?? null) ?: null,
            'role_id' => $newRoleId,
            'role_name' => (string) $newRole['name'],
            'status' => $newStatus,
        ];

        // Rol/durum değişiminde eski yetkiyle açık kalmış oturumlar sonlandırılır.
        if ($roleChanged || ($statusChanged && $newStatus !== 'active')) {
            $this->sessions()->deleteByUser($id);
        }

        $this->audit('user.update', 'user', $id, $old, $new);

        if ($roleChanged) {
            SecurityEvent::record('user_role_changed', 'warning', Auth::id(), $request->ip(), [
                'target_user_id' => $id,
                'old_role' => $old['role_name'],
                'new_role' => (string) $newRole['name'],
            ]);
        }

        $this->flash('success', 'Kullanıcı güncellendi.');

        return $this->redirect($back);
    }

    public function suspend(Request $request, int $id): Response
    {
        return $this->changeStatus($request, $id, 'suspended');
    }

    public function activate(Request $request, int $id): Response
    {
        return $this->changeStatus($request, $id, 'active');
    }

    public function unlock(Request $request, int $id): Response
    {
        $this->require(self::PERMISSION);
        $target = $this->loadTarget($id);
        $back = self::LIST_PATH . '/' . $id . '/duzenle';

        $this->users()->resetFailed($id);
        $this->users()->update($id, ['locked_until' => null]);

        $this->audit('user.unlock', 'user', $id, [
            'locked_until' => $target['locked_until'] ?? null,
            'failed_login_count' => $target['failed_login_count'] ?? 0,
        ], [
            'locked_until' => null,
            'failed_login_count' => 0,
        ]);

        $this->flash('success', 'Hesap kilidi kaldırıldı.');

        return $this->redirect($back);
    }

    public function forceLogout(Request $request, int $id): Response
    {
        $this->require(self::PERMISSION);
        $this->loadTarget($id);
        $back = self::LIST_PATH . '/' . $id . '/duzenle';

        // Kendi oturumunuzu kapatıp yönetim ekranından atılmamak için mevcut oturum hariç tutulur.
        $except = $id === Auth::id() ? Session::id() : null;
        $closed = $this->sessions()->deleteByUser($id, $except);

        $this->audit('user.force_logout', 'user', $id, null, ['sessions_closed' => $closed]);
        $this->flash('success', $closed . ' oturum kapatıldı.');

        return $this->redirect($back);
    }

    public function sendResetLink(Request $request, int $id): Response
    {
        $this->require(self::PERMISSION);
        $target = $this->loadTarget($id);
        $back = self::LIST_PATH . '/' . $id . '/duzenle';

        $this->sendLink((string) $target['email'], $request);
        $this->audit('user.reset_link_sent', 'user', $id, null, ['email' => (string) $target['email']]);
        $this->flash('success', 'Parola sıfırlama bağlantısı gönderildi.');

        return $this->redirect($back);
    }

    private function changeStatus(Request $request, int $id, string $newStatus): Response
    {
        $this->require(self::PERMISSION);
        $target = $this->loadTarget($id);

        if ($newStatus === 'suspended') {
            if ($id === Auth::id()) {
                return $this->block($request, 'self_suspend', $id, 'Kendi hesabınızı askıya alamazsınız.', self::LIST_PATH);
            }
            if ($this->isLastActivePrivileged($target)) {
                return $this->block($request, 'last_admin_suspend', $id, 'Son aktif yönetici hesabı askıya alınamaz.', self::LIST_PATH);
            }
        }

        if ((string) $target['status'] === $newStatus) {
            $this->flash('info', 'Kullanıcı zaten bu durumda.');

            return $this->redirect(self::LIST_PATH);
        }

        $this->users()->update($id, ['status' => $newStatus]);

        if ($newStatus === 'suspended') {
            $this->sessions()->deleteByUser($id);
        }

        $this->audit(
            $newStatus === 'suspended' ? 'user.suspend' : 'user.activate',
            'user',
            $id,
            ['status' => (string) $target['status']],
            ['status' => $newStatus]
        );
        $this->flash('success', $newStatus === 'suspended' ? 'Kullanıcı askıya alındı.' : 'Kullanıcı aktifleştirildi.');

        return $this->redirect(self::LIST_PATH);
    }

    /** Parola belirleme/sıfırlama bağlantısı; AuthService kendi hız sınırını uygular. */
    private function sendLink(string $email, Request $request): void
    {
        try {
            $ipBin = ip_to_bin($request->ip()) ?? "\x00\x00\x00\x00";
            (new AuthService())->requestPasswordReset($email, $ipBin);
        } catch (\Throwable $e) {
            Logger::warning('Parola bağlantısı gönderilemedi', ['error' => $e->getMessage()]);
        }
    }

    private function block(Request $request, string $reason, int $targetId, string $message, string $back): Response
    {
        SecurityEvent::record('admin_guard_blocked', 'warning', Auth::id(), $request->ip(), [
            'reason' => $reason,
            'target_user_id' => $targetId,
        ]);
        $this->flash('danger', $message);

        return $this->redirect($back);
    }

    /** @return array<string,mixed> */
    private function loadTarget(int $id): array
    {
        $row = $this->users()->withRole($id);
        if ($row === null) {
            throw new HttpException(404, 'Kullanıcı bulunamadı.');
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private function snapshot(array $user): array
    {
        return [
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'phone' => $user['phone'] ?? null,
            'role_id' => (int) $user['role_id'],
            'role_name' => $user['role_name'] ?? null,
            'status' => $user['status'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    private function readFilters(Request $request): array
    {
        $filters = [];
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $filters['q'] = mb_substr($q, 0, 100);
        }
        $roleId = (string) $request->query('role_id', '');
        if (ctype_digit($roleId) && (int) $roleId > 0) {
            $filters['role_id'] = (int) $roleId;
        }
        $status = (string) $request->query('status', '');
        if (isset(self::STATUS_LABELS[$status])) {
            $filters['status'] = $status;
        }

        return $filters;
    }

    /** @return list<int> */
    private function roleIds(): array
    {
        $ids = [];
        foreach ($this->roles()->allWithUserCounts() as $role) {
            $ids[] = (int) $role['id'];
        }

        return $ids;
    }

    /** Rol adı sabitlenmez: user.manage yetkisi olan roller "yetkili" sayılır. */
    private function isPrivilegedRole(int $roleId): bool
    {
        return in_array(self::PERMISSION, $this->roles()->permissionCodes($roleId), true);
    }

    private function isLastActivePrivileged(array $user): bool
    {
        if ((string) $user['status'] !== 'active' || !$this->isPrivilegedRole((int) $user['role_id'])) {
            return false;
        }

        $active = 0;
        foreach ($this->roleIds() as $roleId) {
            if ($this->isPrivilegedRole($roleId)) {
                $active += $this->users()->count(['role_id' => $roleId, 'status' => 'active']);
            }
        }

        return $active <= 1;
    }

    private function isLockedNow(array $user): bool
    {
        return !empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time();
    }

    /** @return array<string,string> */
    private function attributeLabels(): array
    {
        return [
            'first_name' => 'Ad',
            'last_name' => 'Soyad',
            'email' => 'E-posta',
            'phone' => 'Telefon',
            'role_id' => 'Rol',
            'status' => 'Durum',
        ];
    }

    private function users(): User
    {
        return $this->userModel ??= new User();
    }

    private function roles(): Role
    {
        return $this->roleModel ??= new Role();
    }

    private function sessions(): UserSession
    {
        return $this->sessionModel ??= new UserSession();
    }
}