<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kasa</h2>
    </x-slot>

    <div class="py-4" x-data="saleScreen()" x-init="init()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Mesajlar --}}
            <div x-show="successMessage" x-cloak class="rounded-md bg-green-50 border border-green-200 p-4 text-green-800">
                <p class="font-semibold" x-text="successMessage"></p>
                <p class="text-2xl font-bold mt-1" x-show="lastChange > 0" x-text="'Para üstü: ' + money(lastChange) + ' ₺'"></p>
            </div>
            <div x-show="errorMessage" x-cloak class="rounded-md bg-red-50 border border-red-200 p-4 text-red-800" x-text="errorMessage"></div>
            <div x-show="infoMessage" x-cloak class="rounded-md bg-blue-50 border border-blue-200 p-4 text-blue-800" x-text="infoMessage"></div>

            {{-- Barkod girişi (en üstte) --}}
            <div class="bg-white shadow sm:rounded-lg p-4">
                <label for="barcode" class="block text-sm font-medium text-gray-700">Barkod</label>
                <input id="barcode" x-ref="barcode" type="text" autocomplete="off" autofocus
                       x-model="barcode" @keydown.enter.prevent="addByBarcode()"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xl focus:border-green-500 focus:ring-green-500"
                       placeholder="Barkodu okutun ve Enter'a basın">
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Manuel arama --}}
                <div class="bg-white shadow sm:rounded-lg p-4 lg:col-span-1">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Manuel Ürün Arama</h3>
                    <input type="text" x-model="searchTerm" @input.debounce.300ms="searchProducts()"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                           placeholder="Ürün adı, barkod veya SKU (en az 2 harf)">

                    <ul class="mt-3 divide-y divide-gray-200 max-h-96 overflow-y-auto">
                        <template x-for="p in searchResults" :key="p.id">
                            <li>
                                <button type="button" @click="addProduct(p); clearSearch()"
                                        class="w-full text-left px-2 py-2 hover:bg-green-50 flex justify-between items-center">
                                    <span>
                                        <span class="block font-medium" x-text="p.name"></span>
                                        <span class="block text-xs" :class="p.in_stock ? 'text-gray-500' : 'text-red-600 font-semibold'"
                                              x-text="p.in_stock ? ('Stok: ' + qty(p.stock) + ' ' + p.unit) : 'Stokta yok'"></span>
                                    </span>
                                    <span class="font-semibold whitespace-nowrap" x-text="money(p.sale_price) + ' ₺'"></span>
                                </button>
                            </li>
                        </template>
                        <li x-show="searchTerm.length >= 2 && searchResults.length === 0" class="px-2 py-3 text-sm text-gray-500">
                            Ürün bulunamadı.
                        </li>
                    </ul>
                </div>

                {{-- Sepet --}}
                <div class="bg-white shadow sm:rounded-lg p-4 lg:col-span-2">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Sepet</h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Miktar</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Birim Fiyat</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">İndirim (birim ₺)</th>
                                    <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                                    <th class="px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="(item, index) in cart" :key="item.id">
                                    <tr>
                                        <td class="px-2 py-2">
                                            <span class="font-medium" x-text="item.name"></span>
                                            <span class="block text-xs text-red-600"
                                                  x-show="item.quantity > item.stock"
                                                  x-text="'Stok yetersiz (mevcut: ' + qty(item.stock) + ' ' + item.unit + ')'"></span>
                                        </td>
                                        <td class="px-2 py-2 text-right">
                                            <input type="number" min="0.001" step="0.001" x-model.number="item.quantity"
                                                   :id="'qty-' + item.id"
                                                   class="w-24 text-right rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500">
                                            <span class="text-xs text-gray-500" x-text="item.unit"></span>
                                        </td>
                                        <td class="px-2 py-2 text-right whitespace-nowrap" x-text="money(item.sale_price) + ' ₺'"></td>
                                        <td class="px-2 py-2 text-right">
                                            <input type="number" min="0" step="0.01" x-model.number="item.discount"
                                                   class="w-24 text-right rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500">
                                        </td>
                                        <td class="px-2 py-2 text-right font-semibold whitespace-nowrap" x-text="money(lineTotal(item)) + ' ₺'"></td>
                                        <td class="px-2 py-2 text-right">
                                            <button type="button" @click="removeItem(index)"
                                                    class="px-2 py-1 text-xs font-medium text-white bg-red-600 rounded-md hover:bg-red-700">Sil</button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="cart.length === 0">
                                    <td colspan="6" class="px-2 py-8 text-center text-gray-500">Sepet boş. Barkod okutun veya ürün arayın.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Toplamlar --}}
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Genel İndirim (₺)</label>
                                <input type="number" min="0" step="0.01" x-model.number="generalDiscount"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                <p class="mt-1 text-xs text-gray-500">Kasiyer toplamda en fazla %10 indirim yapabilir.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Müşteri (veresiye için zorunlu)</label>
                                <select x-model="customerId"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                                    <option value="">Müşteri seçilmedi</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}@if ($customer->phone) ({{ $customer->phone }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="text-right space-y-1">
                            <p class="text-sm text-gray-600">Ara toplam: <span x-text="money(subtotal()) + ' ₺'"></span></p>
                            <p class="text-sm text-gray-600">İndirim: <span x-text="money(discountTotal()) + ' ₺'"></span></p>
                            <p class="text-3xl font-bold text-green-700">Toplam: <span x-text="money(total()) + ' ₺'"></span></p>
                        </div>
                    </div>

                    {{-- Ödeme --}}
                    <div class="mt-4 border-t pt-4 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Alınan Nakit (₺) — para üstü için</label>
                            <input type="number" min="0" step="0.01" x-model.number="cashReceived"
                                   class="mt-1 block w-full sm:w-64 rounded-md border-gray-300 shadow-sm text-lg focus:border-green-500 focus:ring-green-500">
                            <p class="mt-2 text-2xl font-bold text-blue-700" x-show="cashReceived > total() && total() > 0"
                               x-text="'Para üstü: ' + money(cashReceived - total()) + ' ₺'"></p>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <button type="button" @click="pay('cash')" :disabled="busy || cart.length === 0"
                                    class="py-4 text-lg font-bold text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50">Nakit</button>
                            <button type="button" @click="pay('card')" :disabled="busy || cart.length === 0"
                                    class="py-4 text-lg font-bold text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50">Kart</button>
                            <button type="button" @click="pay('transfer')" :disabled="busy || cart.length === 0"
                                    class="py-4 text-lg font-bold text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50">Havale/EFT</button>
                            <button type="button" @click="pay('credit')" :disabled="busy || cart.length === 0"
                                    class="py-4 text-lg font-bold text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50">Veresiye</button>
                        </div>

                        <button type="button" @click="resetSale()" x-show="cart.length > 0"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">Sepeti Temizle</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('saleScreen', () => ({
                barcode: '',
                searchTerm: '',
                searchResults: [],
                cart: [],
                generalDiscount: 0,
                customerId: '',
                cashReceived: null,
                busy: false,
                successMessage: '',
                errorMessage: '',
                infoMessage: '',
                lastChange: 0,

                urls: {
                    barcode: @json(route('cashier.sales.barcode', ['barcode' => '__BARCODE__'])),
                    search: @json(route('cashier.sales.search')),
                    store: @json(route('cashier.sales.store')),
                },
                csrf: @json(csrf_token()),

                init() {
                    this.focusBarcode();
                },

                focusBarcode() {
                    this.$nextTick(() => this.$refs.barcode && this.$refs.barcode.focus());
                },

                // ---- Biçimlendirme ----
                money(value) {
                    return (Number(value) || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
                qty(value) {
                    return (Number(value) || 0).toLocaleString('tr-TR', { maximumFractionDigits: 3 });
                },
                round2(value) {
                    return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
                },

                // ---- Hesaplamalar (yalnızca gösterim; asıl hesap sunucuda) ----
                lineTotal(item) {
                    return this.round2((Number(item.quantity) || 0) * ((Number(item.sale_price) || 0) - (Number(item.discount) || 0)));
                },
                subtotal() {
                    return this.round2(this.cart.reduce((sum, i) => sum + (Number(i.quantity) || 0) * (Number(i.sale_price) || 0), 0));
                },
                discountTotal() {
                    const lines = this.cart.reduce((sum, i) => sum + (Number(i.quantity) || 0) * (Number(i.discount) || 0), 0);
                    return this.round2(lines + (Number(this.generalDiscount) || 0));
                },
                total() {
                    return Math.max(0, this.round2(this.subtotal() - this.discountTotal()));
                },

                // ---- Mesajlar ----
                clearMessages() {
                    this.successMessage = '';
                    this.errorMessage = '';
                    this.infoMessage = '';
                },

                // ---- Ürün ekleme ----
                async addByBarcode() {
                    const code = this.barcode.trim();
                    if (!code) return;

                    this.clearMessages();
                    this.barcode = '';

                    try {
                        const response = await fetch(this.urls.barcode.replace('__BARCODE__', encodeURIComponent(code)), {
                            headers: { 'Accept': 'application/json' },
                        });
                        const json = await response.json();

                        if (!response.ok) {
                            this.errorMessage = json.message || 'Ürün bulunamadı.';
                        } else {
                            this.addProduct(json.data);
                        }
                    } catch (e) {
                        this.errorMessage = 'Sunucuya ulaşılamadı. Bağlantıyı kontrol edin.';
                    }

                    this.focusBarcode();
                },

                addProduct(product) {
                    this.clearMessages();

                    if (!product.in_stock) {
                        this.errorMessage = product.name + ' stokta yok, satılamaz.';
                        this.focusBarcode();
                        return;
                    }

                    const existing = this.cart.find(i => i.id === product.id);

                    if (existing) {
                        // Tartılı üründe miktarı elle girmek için alana odaklan
                        if (product.is_weighed) {
                            this.$nextTick(() => document.getElementById('qty-' + product.id)?.focus());
                            return;
                        }
                        existing.quantity = this.round3(existing.quantity + 1);
                    } else {
                        this.cart.push({
                            id: product.id,
                            name: product.name,
                            unit: product.unit,
                            sale_price: Number(product.sale_price),
                            stock: Number(product.stock),
                            is_weighed: product.is_weighed,
                            quantity: 1,
                            discount: 0,
                        });

                        if (product.is_weighed) {
                            this.$nextTick(() => {
                                const el = document.getElementById('qty-' + product.id);
                                if (el) { el.focus(); el.select(); }
                            });
                            return;
                        }
                    }

                    this.focusBarcode();
                },

                round3(value) {
                    return Math.round((Number(value) + Number.EPSILON) * 1000) / 1000;
                },

                removeItem(index) {
                    this.cart.splice(index, 1);
                    this.focusBarcode();
                },

                // ---- Manuel arama ----
                async searchProducts() {
                    const term = this.searchTerm.trim();

                    if (term.length < 2) {
                        this.searchResults = [];
                        return;
                    }

                    try {
                        const response = await fetch(this.urls.search + '?q=' + encodeURIComponent(term), {
                            headers: { 'Accept': 'application/json' },
                        });
                        const json = await response.json();
                        this.searchResults = json.data || [];
                    } catch (e) {
                        this.errorMessage = 'Arama sırasında hata oluştu.';
                    }
                },

                clearSearch() {
                    this.searchTerm = '';
                    this.searchResults = [];
                },

                // ---- Ödeme ----
                async pay(type) {
                    this.clearMessages();

                    if (this.cart.length === 0) return;

                    const total = this.total();

                    if (total <= 0) {
                        this.errorMessage = 'Satış toplamı sıfır olamaz.';
                        return;
                    }

                    if (type === 'credit' && !this.customerId) {
                        this.errorMessage = 'Veresiye satışta müşteri seçimi zorunludur.';
                        return;
                    }

                    let amount = total;

                    if (type === 'cash' && Number(this.cashReceived) > 0) {
                        if (Number(this.cashReceived) < total) {
                            this.errorMessage = 'Alınan nakit satış toplamından az olamaz.';
                            return;
                        }
                        amount = this.round2(Number(this.cashReceived));
                    }

                    const payload = {
                        customer_id: this.customerId || null,
                        discount: Number(this.generalDiscount) || 0,
                        items: this.cart.map(i => ({
                            product_id: i.id,
                            quantity: Number(i.quantity),
                            discount: Number(i.discount) || 0,
                        })),
                        payments: [{ payment_type: type, amount: amount }],
                    };

                    this.busy = true;

                    try {
                        const response = await fetch(this.urls.store, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrf,
                            },
                            body: JSON.stringify(payload),
                        });

                        const json = await response.json().catch(() => ({}));

                        if (response.status === 422) {
                            const errors = json.errors ? Object.values(json.errors).flat() : [];
                            this.errorMessage = errors[0] || json.message || 'Girilen bilgiler geçersiz.';
                        } else if (response.status === 403) {
                            this.errorMessage = 'Bu işlem için yetkiniz yok.';
                        } else if (response.status === 419) {
                            this.errorMessage = 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.';
                        } else if (!response.ok) {
                            this.errorMessage = json.message || 'Satış tamamlanamadı. Lütfen tekrar deneyin.';
                        } else {
                            this.lastChange = Number(json.change_amount) || 0;
                            this.successMessage = 'Satış tamamlandı. Fiş no: ' + json.sale_no + ' — Toplam: ' + this.money(json.total) + ' ₺';
                            this.resetSale(true);
                        }
                    } catch (e) {
                        this.errorMessage = 'Sunucuya ulaşılamadı. Satış kaydedilmedi, bağlantıyı kontrol edip tekrar deneyin.';
                    } finally {
                        this.busy = false;
                        this.focusBarcode();
                    }
                },

                resetSale(keepMessages = false) {
                    this.cart = [];
                    this.generalDiscount = 0;
                    this.customerId = '';
                    this.cashReceived = null;
                    this.barcode = '';
                    this.clearSearch();
                    if (!keepMessages) this.clearMessages();
                    this.focusBarcode();
                },
            }));
        });
    </script>
</x-app-layout>