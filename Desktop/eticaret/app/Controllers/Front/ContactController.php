<?php
declare(strict_types=1);

// app/Controllers/Front/ContactController.php

namespace App\Controllers\Front;

use App\Controllers\BaseController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\ContactMessage;
use App\Models\SecurityEvent;
use App\Models\Setting;

/**
 * İletişim formu. Hız sınırı route düzeyinde (throttle:5,60); CSRF global middleware'de.
 */
final class ContactController extends BaseController
{
    public function show(Request $request): Response
    {
        return $this->view('front.contact', [
            'settings' => (new Setting())->publicAll(),
            'title' => 'İletişim',
            'metaDescription' => 'Sorularınız, siparişleriniz ve önerileriniz için bizimle iletişime geçin.',
            'canonical' => url('/iletisim'),
            'bodyClass' => 'page-contact',
        ]);
    }

    public function submit(Request $request): Response
    {
        // Honeypot: botlar gizli "website" alanını doldurur; kaydetmeden sessizce başarılı gösterilir.
        $honeypot = trim((string) $request->post('website', ''));
        if ($honeypot !== '') {
            SecurityEvent::record('contact_honeypot', 'info', Auth::id(), $request->ip(), []);
            $this->flash('success', 'Mesajınız alındı. En kısa sürede size dönüş yapacağız.');
            return $this->redirect('/iletisim');
        }

        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|phone_tr',
            'subject' => 'required|string|min:3|max:190',
            'message' => 'required|string|min:10|max:2000',
        ], [
            'attributes' => [
                'name' => 'Ad soyad',
                'email' => 'E-posta',
                'phone' => 'Telefon',
                'subject' => 'Konu',
                'message' => 'Mesaj',
            ],
        ]);

        // IP ham bayt olarak saklanır (VARBINARY); geçersizse sıfır adres yazılır (NOT NULL).
        $ipBin = ip_to_bin($request->ip()) ?? "\0\0\0\0";

        (new ContactMessage())->createMessage([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'ip_address' => $ipBin,
        ]);

        $this->flash('success', 'Mesajınız alındı. En kısa sürede size dönüş yapacağız.');
        return $this->redirect('/iletisim');
    }
}
