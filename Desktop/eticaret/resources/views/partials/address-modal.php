<?php // resources/views/partials/address-modal.php
// Adres ekle/düzenle modalı (checkout + hesap ortak). Gönderim public/assets/js/checkout.js ile yapılır.
$provinces = [
    'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Amasya', 'Ankara', 'Antalya', 'Artvin', 'Aydın', 'Balıkesir',
    'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum', 'Denizli',
    'Diyarbakır', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkâri',
    'Hatay', 'Isparta', 'Mersin', 'İstanbul', 'İzmir', 'Kars', 'Kastamonu', 'Kayseri', 'Kırklareli', 'Kırşehir',
    'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa', 'Kahramanmaraş', 'Mardin', 'Muğla', 'Muş', 'Nevşehir',
    'Niğde', 'Ordu', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Tekirdağ', 'Tokat',
    'Trabzon', 'Tunceli', 'Şanlıurfa', 'Uşak', 'Van', 'Yozgat', 'Zonguldak', 'Aksaray', 'Bayburt', 'Karaman',
    'Kırıkkale', 'Batman', 'Şırnak', 'Bartın', 'Ardahan', 'Iğdır', 'Yalova', 'Karabük', 'Kilis', 'Osmaniye', 'Düzce',
];
?>
<div class="modal fade" id="ymAddressModal" tabindex="-1" aria-labelledby="ymAddressModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down modal-lg">
        <div class="modal-content">
            <form id="ymAddressForm" novalidate data-no-lock>
                <div class="modal-header">
                    <h2 class="modal-title h5" id="ymAddressModalTitle" data-address-modal-title>Yeni adres</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div id="ymAddressFormError" class="alert alert-danger d-none" role="alert"></div>
                    <input type="hidden" name="id" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="ymAdTitle" class="form-label">Adres başlığı</label>
                            <input type="text" class="form-control" id="ymAdTitle" name="title" maxlength="60" placeholder="Ev, İş…" required>
                            <div class="invalid-feedback" data-error-for="title"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="ymAdName" class="form-label">Ad soyad</label>
                            <input type="text" class="form-control" id="ymAdName" name="full_name" maxlength="160" autocomplete="name" required>
                            <div class="invalid-feedback" data-error-for="full_name"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="ymAdPhone" class="form-label">Telefon</label>
                            <input type="tel" class="form-control" id="ymAdPhone" name="phone" maxlength="20" inputmode="tel" autocomplete="tel" placeholder="05XX XXX XX XX" required>
                            <div class="invalid-feedback" data-error-for="phone"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="ymAdCity" class="form-label">İl</label>
                            <select class="form-select" id="ymAdCity" name="city" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?= e($province) ?>"><?= e($province) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" data-error-for="city"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="ymAdDistrict" class="form-label">İlçe</label>
                            <input type="text" class="form-control" id="ymAdDistrict" name="district" maxlength="80" required>
                            <div class="invalid-feedback" data-error-for="district"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="ymAdNeighborhood" class="form-label">Mahalle <span class="text-muted">(opsiyonel)</span></label>
                            <input type="text" class="form-control" id="ymAdNeighborhood" name="neighborhood" maxlength="120">
                            <div class="invalid-feedback" data-error-for="neighborhood"></div>
                        </div>
                        <div class="col-12">
                            <label for="ymAdLine" class="form-label">Açık adres</label>
                            <textarea class="form-control" id="ymAdLine" name="address_line" rows="2" maxlength="500" placeholder="Sokak, bina no, daire no…" required></textarea>
                            <div class="invalid-feedback" data-error-for="address_line"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="ymAdPostal" class="form-label">Posta kodu <span class="text-muted">(opsiyonel)</span></label>
                            <input type="text" class="form-control" id="ymAdPostal" name="postal_code" maxlength="5" inputmode="numeric">
                            <div class="invalid-feedback" data-error-for="postal_code"></div>
                        </div>
                    </div>

                    <hr>
                    <p class="fw-semibold mb-2">Fatura tipi</p>
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="invoice_type" id="ymAdInvInd" value="individual" checked>
                            <label class="form-check-label" for="ymAdInvInd">Bireysel</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="invoice_type" id="ymAdInvCorp" value="corporate">
                            <label class="form-check-label" for="ymAdInvCorp">Kurumsal</label>
                        </div>
                    </div>

                    <div data-invoice-panel="individual">
                        <label for="ymAdTc" class="form-label">TC kimlik no <span class="text-muted">(opsiyonel)</span></label>
                        <input type="text" class="form-control" id="ymAdTc" name="tc_identity_no" maxlength="11" inputmode="numeric" autocomplete="off">
                        <div class="invalid-feedback" data-error-for="tc_identity_no"></div>
                    </div>
                    <div data-invoice-panel="corporate" hidden>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="ymAdCompany" class="form-label">Şirket adı</label>
                                <input type="text" class="form-control" id="ymAdCompany" name="company_name" maxlength="190">
                                <div class="invalid-feedback" data-error-for="company_name"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="ymAdOffice" class="form-label">Vergi dairesi</label>
                                <input type="text" class="form-control" id="ymAdOffice" name="tax_office" maxlength="120">
                                <div class="invalid-feedback" data-error-for="tax_office"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="ymAdTax" class="form-label">Vergi numarası</label>
                                <input type="text" class="form-control" id="ymAdTax" name="tax_number" maxlength="11" inputmode="numeric">
                                <div class="invalid-feedback" data-error-for="tax_number"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="ymAdDefault" name="is_default" value="1">
                        <label class="form-check-label" for="ymAdDefault">Varsayılan adresim olsun</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary" data-address-save>Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>