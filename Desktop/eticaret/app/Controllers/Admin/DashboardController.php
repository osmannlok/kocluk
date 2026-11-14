<?php
// app/Controllers/Admin/DashboardController.php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Services\ReportService;

/** Yönetim paneli ana sayfası. */
final class DashboardController extends AdminBaseController
{
    public function index(Request $request): Response
    {
        // Route middleware'ine ek savunma.
        $this->require('dashboard.view');

        $reports = new ReportService();
        $stats = $reports->dashboardStats();

        // Grafik verisi data-chart özniteliğiyle JS'e taşınır (satır içi script yok).
        $chart = $reports->salesChart(14);
        $chartJson = json_encode($chart, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return $this->view('admin.dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'chartJson' => $chartJson,
            'topProducts' => $reports->topProducts(30, 5),
            'lowStock' => $reports->lowStock(8),
            'recentOrders' => $reports->recentOrders(8),
            'pageScripts' => [
                '/assets/vendor/chartjs/chart.umd.js',
                '/assets/js/admin-dashboard.js',
            ],
        ]);
    }
}