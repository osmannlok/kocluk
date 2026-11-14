<?php
// scripts/model_smoke_b.php
declare(strict_types=1);

// SADECE CLI: web'den çağrılamaz.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use App\Core\Config;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Banner;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ContactMessage;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Page;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\ShippingRate;

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

try {
    Config::load($base);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL Config::load: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// Yalnızca SELECT yapan güvenli okuma çağrıları; hiçbir veri yazılmaz.
$checks = [
    ['Cart::findByUserId', static fn () => (new Cart())->findByUserId(0)],
    ['Cart::findByGuestHash', static fn () => (new Cart())->findByGuestHash(str_repeat('a', 64))],
    ['CartItem::forCart', static fn () => (new CartItem())->forCart(0)],
    ['CartItem::countItems', static fn () => (new CartItem())->countItems(0)],
    ['Coupon::findByCode', static fn () => (new Coupon())->findByCode('YOK-KUPON')],
    ['Coupon::codeExists', static fn () => (new Coupon())->codeExists('YOK-KUPON')],
    ['Coupon::adminList', static fn () => (new Coupon())->adminList(['q' => 'A'], 1, 5)],
    ['CouponUsage::countByUser', static fn () => (new CouponUsage())->countByUser(0, 0)],
    ['ShippingRate::priceForWeight', static fn () => (new ShippingRate())->priceForWeight(500)],
    ['ShippingRate::activeAll', static fn () => (new ShippingRate())->activeAll()],
    ['Order::findByNumber', static fn () => (new Order())->findByNumber('YM-0000-000000')],
    ['Order::findForUser', static fn () => (new Order())->findForUser('YM-0000-000000', 0)],
    ['Order::listForUser', static fn () => (new Order())->listForUser(0, 1, 5)],
    ['Order::adminList', static fn () => (new Order())->adminList(['status' => 'paid', 'q' => 'a'], 1, 5)],
    ['Order::expiredPending', static fn () => (new Order())->expiredPending(date('Y-m-d H:i:s'), 5)],
    ['Order::countByStatus', static fn () => (new Order())->countByStatus()],
    ['Order::salesByDay', static fn () => (new Order())->salesByDay(7)],
    ['OrderItem::forOrder', static fn () => (new OrderItem())->forOrder(0)],
    ['OrderItem::topProducts', static fn () => (new OrderItem())->topProducts(30, 5)],
    ['OrderStatusHistory::forOrder', static fn () => (new OrderStatusHistory())->forOrder(0)],
    ['Payment::findByMerchantOid', static fn () => (new Payment())->findByMerchantOid('YOKOID')],
    ['Payment::latestForOrder', static fn () => (new Payment())->latestForOrder(0)],
    ['Payment::forOrder', static fn () => (new Payment())->forOrder(0)],
    ['Payment::pendingBankTransfers', static fn () => (new Payment())->pendingBankTransfers(1, 5)],
    ['PaymentCallback::recent', static fn () => (new PaymentCallback())->recent(5)],
    ['Shipment::forOrder', static fn () => (new Shipment())->forOrder(0)],
    ['Banner::activeByPosition', static fn () => (new Banner())->activeByPosition('home_hero')],
    ['Banner::adminList', static fn () => (new Banner())->adminList(1, 5)],
    ['Menu::findByLocation', static fn () => (new Menu())->findByLocation('header')],
    ['Menu::allWithItemCounts', static fn () => (new Menu())->allWithItemCounts()],
    ['MenuItem::treeByMenu', static fn () => (new MenuItem())->treeByMenu(1, true)],
    ['MenuItem::descendantIds', static fn () => (new MenuItem())->descendantIds(1)],
    ['Page::findActiveBySlug', static fn () => (new Page())->findActiveBySlug('hakkimizda')],
    ['Page::findBySystemKey', static fn () => (new Page())->findBySystemKey('kvkk')],
    ['Page::footerPages', static fn () => (new Page())->footerPages()],
    ['Page::slugExists', static fn () => (new Page())->slugExists('hakkimizda')],
    ['Setting::get', static fn () => (new Setting())->get('shipping.free_threshold', '0.00')],
    ['Setting::getMany', static fn () => (new Setting())->getMany(['site.name', 'cod.enabled'])],
    ['Setting::allByGroup', static fn () => (new Setting())->allByGroup()],
    ['Setting::publicAll', static fn () => (new Setting())->publicAll()],
    ['Announcement::activeList', static fn () => (new Announcement())->activeList()],
    ['Announcement::adminList', static fn () => (new Announcement())->adminList(1, 5)],
    ['ContactMessage::adminList', static fn () => (new ContactMessage())->adminList(null, 1, 5)],
    ['ContactMessage::countNew', static fn () => (new ContactMessage())->countNew()],
    ['AuditLog::search', static fn () => (new AuditLog())->search(['action' => 'a'], 1, 5)],
    ['SecurityEvent::search', static fn () => (new SecurityEvent())->search(['severity' => 'info'], 1, 5)],
    ['SecurityEvent::countSince', static fn () => (new SecurityEvent())->countSince('csrf_fail', 60)],
];

$failed = 0;
foreach ($checks as [$label, $fn]) {
    try {
        $fn();
        echo 'OK ' . $label . PHP_EOL;
    } catch (Throwable $e) {
        $failed++;
        echo 'FAIL ' . $label . ': ' . $e->getMessage() . PHP_EOL;
    }
}
echo ($failed === 0 ? 'TÜMÜ OK' : $failed . ' HATA') . ' (' . count($checks) . ' çağrı)' . PHP_EOL;
exit($failed === 0 ? 0 : 1);