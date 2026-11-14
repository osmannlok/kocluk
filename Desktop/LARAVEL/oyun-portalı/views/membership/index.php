<?php
/**
 * @var array|null $activeMembership
 * @var array $plans
 * @var string $title
 * @var string $description
 */
ob_start();
?>
<section class="membership-page">
    <div class="container">
        <h1>Premium Üyelik</h1>
        <p class="page-intro">Premium içeriklere ve ek özelliklere erişmek için bir plan seçin.</p>

        <?php if ($activeMembership): ?>
            <div class="membership-active-card">
                <p>
                    Aktif planınız: <strong><?= htmlspecialchars($activeMembership['plan_name']) ?></strong>
                    — Bitiş: <?= htmlspecialchars(date('d.m.Y', strtotime($activeMembership['ends_at']))) ?>
                </p>
                <form method="POST" action="/uyelik/<?= (int) $activeMembership['id'] ?>/iptal">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="btn btn-outline">Üyeliği İptal Et</button>
                </form>
            </div>
        <?php else: ?>
            <?php if (empty($plans)): ?>
                <p class="empty-state">Şu anda aktif bir üyelik planı bulunmuyor.</p>
            <?php else: ?>
                <div class="membership-plans">
                    <?php foreach ($plans as $plan): ?>
                        <div class="membership-plan-card">
                            <h3><?= htmlspecialchars($plan['name']) ?></h3>
                            <?php if (!empty($plan['description'])): ?>
                                <p><?= htmlspecialchars($plan['description']) ?></p>
                            <?php endif; ?>
                            <p class="membership-price">
                                <?= number_format($plan['price_cents'] / 100, 2, ',', '.') ?> ₺
                                <span>/ <?= (int) $plan['duration_days'] ?> gün</span>
                            </p>
                            <form method="POST" action="/uyelik/<?= htmlspecialchars($plan['slug']) ?>/abone-ol">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <button type="submit" class="btn btn-primary btn-block">Planı Seç</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
require BASE_PATH . '/views/layouts/main.php';