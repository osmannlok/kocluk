<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Membership;

class MembershipController
{
    public function index(): void
    {
        $user = $this->requireAuth();

        $membershipModel = new Membership();
        $activeMembership = $membershipModel->getActiveByUser((int) $user['id']);
        $plans = $membershipModel->getPlans();

        $title = 'Premium Üyelik - Eğitim Oyun Portalı';
        $description = 'Premium üyelik planlarını görüntüleyin ve yönetin.';

        require BASE_PATH . '/views/membership/index.php';
    }

    public function subscribe(string $planSlug): void
    {
        $user = $this->requireAuth();

        if (!$this->verifyCsrf()) {
            $this->redirect('/uyelik');
        }

        $membershipModel = new Membership();
        $plan = $membershipModel->findPlanBySlug($planSlug);

        if ($plan === null) {
            $this->redirect('/uyelik');
        }

        $membershipModel->create((int) $user['id'], (int) $plan['id'], (int) $plan['duration_days']);

        $this->redirect('/uyelik');
    }

    public function cancel(int $membershipId): void
    {
        $user = $this->requireAuth();

        if (!$this->verifyCsrf()) {
            $this->redirect('/uyelik');
        }

        $membershipModel = new Membership();
        $membershipModel->cancel($membershipId, (int) $user['id']);

        $this->redirect('/uyelik');
    }

    private function requireAuth(): array
    {
        $user = $_SESSION['user'] ?? null;

        if ($user === null) {
            header('Location: /giris');
            exit;
        }

        return $user;
    }

    private function verifyCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';

        return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    private function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}