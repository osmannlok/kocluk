<?php
// app/Controllers/Admin/CustomerAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Address;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;

/**
 * Admin: müşteri listesi ve müşteri detayı. SALT OKUNUR; yazma ucu yoktur.
 * Her public metot en başta customer.view'ı require() eder.
 */
final class CustomerAdminController extends AdminBaseController
{
    private const STATUSES = ['active', 'suspended', 'pending'];

    /** GET /yonetim/musteriler */
    public function index(Request $request): Response
    {
        $this->require('customer.view');

        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');
        $status = in_array($status, self::STATUSES, true) ? $status : '';

        $page = $this->currentPage($request);
        $perPage = $this->perPage();

        // Yalnızca customer rolü: searchAdmin'in mevcut role_id filtresi kullanılır (SQL controller'da yok).
        $customerRole = (new Role())->findByName('customer');

        if ($customerRole === null) {
            $result = ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'pages' => 1];
        } else {
            $filters = ['role_id' => (int) $customerRole['id']];
            if ($q !== '') {
                $filters['q'] = $q;
            }
            if ($status !== '') {
                $filters['status'] = $status;
            }
            $result = (new User())->searchAdmin($filters, $page, $perPage);
        }

        // Savunma katmanı: hassas alanlar hiçbir koşulda view'a gitmez.
        $items = array_map(static function (array $row): array {
            unset($row['password_hash'], $row['last_login_ip']);

            return $row;
        }, $result['items']);

        return $this->view('admin.customers.index', [
            'title'     => 'Müşteriler',
            'customers' => $items,
            'pager'     => $this->pager($result, $request),
            'filters'   => ['q' => $q, 'status' => $status],
            'statuses'  => self::STATUSES,
        ]);
    }

    /** GET /yonetim/musteriler/{id} */
    public function show(Request $request, int $id): Response
    {
        $this->require('customer.view');

        $customer = (new User())->withRole($id);
        // Bu ekran yöneticileri/personeli göstermez; customer değilse varlığı da sızdırılmaz.
        if ($customer === null || (string) ($customer['role_name'] ?? '') !== 'customer') {
            throw new HttpException(404, 'Müşteri bulunamadı.');
        }
        unset($customer['password_hash'], $customer['last_login_ip']);

        // TC kimlik no hassastır: controller'da maskelenir, ham değer view'a geçirilmez.
        $addresses = array_map(function (array $address): array {
            $address['tc_identity_no'] = $this->maskTc($address['tc_identity_no'] ?? null);

            return $address;
        }, (new Address())->forUser($id));

        $orders = (new Order())->listForUser($id, $this->currentPage($request), $this->perPage());
        // Adres anlık görüntüleri (ham TC içerebilir) liste satırlarından çıkarılır.
        $orderRows = array_map(static function (array $row): array {
            unset($row['shipping_address'], $row['billing_address']);

            return $row;
        }, $orders['items']);

        return $this->view('admin.customers.show', [
            'title'     => trim((string) $customer['first_name'] . ' ' . (string) $customer['last_name']),
            'customer'  => $customer,
            'addresses' => $addresses,
            'orders'    => $orderRows,
            'pager'     => $this->pager($orders, $request),
        ]);
    }

    /** İlk 3 + **** + son 2 hane; boşsa null. */
    private function maskTc(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (strlen($value) < 6) {
            return '****';
        }

        return substr($value, 0, 3) . '****' . substr($value, -2);
    }
}