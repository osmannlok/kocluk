<?php
// app/Controllers/Admin/ContactMessageAdminController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ContactMessage;

/**
 * İletişim mesajları (customer.view).
 * Mesaj içeriği kullanıcı girdisidir; yalnızca view'da e() ile basılır.
 */
final class ContactMessageAdminController extends AdminBaseController
{
    private const STATUSES = ['new', 'read', 'replied'];

    private function messages(): ContactMessage
    {
        return new ContactMessage();
    }

    public function index(Request $request): Response
    {
        $this->require('customer.view');

        $status = (string) $request->query('status', '');
        $filter = in_array($status, self::STATUSES, true) ? $status : null;

        $result = $this->messages()->adminList($filter, $this->currentPage($request), $this->perPage());

        return $this->view('admin.messages.index', [
            'title' => 'Mesajlar',
            'messages' => $result['items'],
            'pager' => $this->pager($result, $request),
            'status' => $filter ?? '',
            'newCount' => $this->messages()->countNew(),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $this->require('customer.view');

        $message = $this->messages()->find($id);
        if ($message === null) {
            throw new HttpException(404, 'Mesaj bulunamadı.');
        }

        // Açılan mesaj otomatik "okundu" olur.
        if (($message['status'] ?? '') === 'new') {
            $this->messages()->markStatus($id, 'read', Auth::id());
            $message['status'] = 'read';
        }

        $email = (string) ($message['email'] ?? '');
        $mailto = null;
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $mailto = 'mailto:' . $email . '?subject=' . rawurlencode('Re: ' . (string) ($message['subject'] ?? ''));
        }

        return $this->view('admin.messages.show', [
            'title' => 'Mesaj #' . $id,
            'message' => $message,
            'mailto' => $mailto,
            'ip' => bin_to_ip($message['ip_address'] ?? null),
        ]);
    }

    public function setStatus(Request $request, int $id): Response
    {
        $this->require('customer.view');

        $message = $this->messages()->find($id);
        if ($message === null) {
            throw new HttpException(404, 'Mesaj bulunamadı.');
        }

        $status = (string) $request->post('status', 'replied');
        if (!in_array($status, self::STATUSES, true)) {
            $this->flash('danger', 'Geçersiz durum.');
            return $this->redirect(url('/yonetim/mesajlar/' . $id));
        }

        $oldStatus = (string) ($message['status'] ?? '');
        if ($oldStatus !== $status) {
            $this->messages()->markStatus($id, $status, Auth::id());
            $this->audit('contact_message.status', 'contact_message', $id, ['status' => $oldStatus], ['status' => $status]);
        }

        $this->flash('success', 'Mesaj durumu güncellendi.');

        return $this->redirect(url('/yonetim/mesajlar/' . $id));
    }
}