<?php
// app/Controllers/Front/AccountController.php

declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use App\Services\AddressService;
use App\Services\AuditService;
use App\Services\AuthService;

/**
 * Müşteri hesabı: pano, profil, parola, adres sayfası.
 * Tüm veri Auth::id() ile sınırlıdır; e-posta değişimi bu ekranda YOKTUR.
 */
final class AccountController extends BaseController
{
    /** Genel bakış. */
    public function dashboard(Request $request): Response
    {
        $userId = $this->userId();
        $user = $this->currentUser($userId);

        $page = (new Order())->listForUser($userId, 1, 3);
        $recentOrders = array_map(
            [OrderHistoryController::class, 'decorate'],
            $page['items']
        );

        $defaultAddress = null;
        $addresses = (new Address())->forUser($userId);
        foreach ($addresses as $address) {
            if (!empty($address['is_default'])) {
                $defaultAddress = $address;
                break;
            }
        }
        if ($defaultAddress === null && $addresses !== []) {
            $defaultAddress = $addresses[0];
        }
        if ($defaultAddress !== null) {
            // TC kimlik no maskeli gösterilir.
            $defaultAddress = (new AddressService())->present($defaultAddress);
        }

        View::share('scripts', ['account.js']);

        return $this->view('front.account.dashboard', [
            'title'           => 'Hesabım',
            'metaDescription' => 'Hesap özetiniz, son siparişleriniz ve adresiniz.',
            'user'            => $user,
            'recentOrders'    => $recentOrders,
            'defaultAddress'  => $defaultAddress,
            'emailVerified'   => !empty($user['email_verified_at']),
        ], 'layouts.account');
    }

    /** Profil + parola formları. */
    public function profile(Request $request): Response
    {
        return $this->renderProfile($this->userId(), false);
    }

    /** Ad, soyad, telefon, pazarlama izni. E-posta değişmez. */
    public function updateProfile(Request $request): Response
    {
        $userId = $this->userId();
        $user = $this->currentUser($userId);

        $data = $this->validate($request, [
            'first_name'        => 'required|min:2|max:80',
            'last_name'         => 'required|min:2|max:80',
            'phone'             => 'nullable|phone_tr',
            'marketing_consent' => 'boolean',
        ], [
            'attributes' => [
                'first_name'        => 'Ad',
                'last_name'         => 'Soyad',
                'phone'             => 'Telefon',
                'marketing_consent' => 'Pazarlama izni',
            ],
        ]);

        // Mass assignment yok: yalnızca izin verilen dört alan yazılır.
        $new = [
            'first_name'        => trim((string) $data['first_name']),
            'last_name'         => trim((string) $data['last_name']),
            'phone'             => ($data['phone'] ?? null) === null || $data['phone'] === ''
                ? null
                : (string) $data['phone'],
            'marketing_consent' => !empty($data['marketing_consent']) ? 1 : 0,
        ];

        $old = [
            'first_name'        => (string) ($user['first_name'] ?? ''),
            'last_name'         => (string) ($user['last_name'] ?? ''),
            'phone'             => $user['phone'] ?? null,
            'marketing_consent' => (int) ($user['marketing_consent'] ?? 0),
        ];

        (new User())->update($userId, $new);

        AuditService::log('user.profile_updated', 'user', $userId, $old, $new);

        $this->flash('success', 'Profil bilgileriniz güncellendi.');

        return $this->redirect('/hesabim/profil');
    }

    /** Menüdeki "Şifre" bağlantısı: profil sayfasını parola kartına odaklı gösterir. */
    public function passwordForm(Request $request): Response
    {
        return $this->renderProfile($this->userId(), true);
    }

    /** Parola değiştirme. Politika/oturum işlemleri AuthService içinde yapılır. */
    public function changePassword(Request $request): Response
    {
        $userId = $this->userId();

        $input = $request->post();
        $validator = Validator::make(is_array($input) ? $input : [], [
            'current_password'          => 'required|max:200',
            'new_password'              => 'required|max:200|confirmed',
            'new_password_confirmation' => 'required|max:200',
        ], [
            'attributes' => [
                'current_password'          => 'Mevcut parola',
                'new_password'              => 'Yeni parola',
                'new_password_confirmation' => 'Yeni parola (tekrar)',
            ],
        ]);

        if ($validator->fails()) {
            // Parola alanları old()'a geri yazılmaz: eski değer dizisi boş verilir.
            throw new ValidationException($validator->errors(), []);
        }

        $data = $validator->validated();

        $result = (new AuthService())->changePassword(
            $userId,
            (string) $data['current_password'],
            (string) $data['new_password']
        );

        if (empty($result['ok'])) {
            $this->flash('error', (string) ($result['error'] ?? 'Parola değiştirilemedi.'));
            return $this->redirect('/hesabim/profil');
        }

        // Diğer oturumları kapatma ve regenerate servis içinde yapıldı; burada tekrarlanmaz.
        $this->flash('success', 'Parolanız güncellendi.');

        return $this->redirect('/hesabim/profil');
    }

    /** Adres listesi sayfası (ekle/düzenle/sil JSON uçlarıyla çalışır). */
    public function addresses(Request $request): Response
    {
        $userId = $this->userId();

        $service = new AddressService();
        $addresses = [];
        foreach ((new Address())->forUser($userId) as $address) {
            $addresses[] = $service->present($address);
        }

        View::share('scripts', ['checkout.js', 'account.js']);

        return $this->view('front.account.addresses', [
            'title'           => 'Adreslerim',
            'metaDescription' => 'Kayıtlı teslimat ve fatura adresleriniz.',
            'addresses'       => $addresses,
            'maxAddresses'    => 10,
        ], 'layouts.account');
    }

    private function renderProfile(int $userId, bool $focusPassword): Response
    {
        $user = $this->currentUser($userId);

        View::share('scripts', ['account.js']);

        return $this->view('front.account.profile', [
            'title'           => $focusPassword ? 'Şifre' : 'Profilim',
            'metaDescription' => 'Profil bilgileriniz ve parola ayarlarınız.',
            'user'            => $user,
            'focusPassword'   => $focusPassword,
        ], 'layouts.account');
    }

    private function userId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            throw new HttpException(401, 'Oturum açmanız gerekiyor.');
        }

        return $id;
    }

    /**
     * password_hash ve son giriş IP'si içermeyen kullanıcı satırı.
     *
     * @return array<string,mixed>
     */
    private function currentUser(int $userId): array
    {
        $user = (new User())->withRole($userId);
        if ($user === null) {
            throw new HttpException(401, 'Oturum açmanız gerekiyor.');
        }

        return $user;
    }
}