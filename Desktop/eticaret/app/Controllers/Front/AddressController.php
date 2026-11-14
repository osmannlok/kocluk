<?php // app/Controllers/Front/AddressController.php
declare(strict_types=1);

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Address;
use App\Services\AddressService;

/**
 * Adres JSON uçları (checkout ve hesap ortak). Tüm uçlar auth middleware arkasındadır.
 */
final class AddressController extends BaseController
{
    public function list(Request $request): Response
    {
        $service = new AddressService();
        $rows = (new Address())->forUser($this->userId());

        return $this->ok([
            'addresses' => array_map(static fn (array $a): array => $service->present($a), $rows),
        ]);
    }

    public function store(Request $request): Response
    {
        $service = new AddressService();
        $userId = $this->userId();

        // Doğrulama hatası ValidationException ile 422'ye çevrilir.
        $id = $service->create($userId, $this->payload($request));
        $address = (new Address())->findForUser($id, $userId);

        return $this->ok([
            'id' => $id,
            'address' => $address !== null ? $service->present($address) : null,
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $service = new AddressService();
        $userId = $this->userId();

        if (!$service->update($id, $userId, $this->payload($request))) {
            return $this->fail('not_found', 'Adres bulunamadı.', 404);
        }
        $address = (new Address())->findForUser($id, $userId);

        return $this->ok([
            'id' => $id,
            'address' => $address !== null ? $service->present($address) : null,
        ]);
    }

    public function destroy(Request $request, int $id): Response
    {
        if (!(new AddressService())->delete($id, $this->userId())) {
            return $this->fail('not_found', 'Adres bulunamadı.', 404);
        }

        return $this->ok(['id' => $id]);
    }

    public function makeDefault(Request $request, int $id): Response
    {
        (new AddressService())->setDefault($id, $this->userId());

        return $this->ok(['id' => $id]);
    }

    private function userId(): int
    {
        return (int) Auth::id();
    }

    /** JSON gövde varsa onu, yoksa form verisini kullanır. */
    private function payload(Request $request): array
    {
        $json = $request->json();

        return $json !== [] ? $json : $request->all();
    }
}