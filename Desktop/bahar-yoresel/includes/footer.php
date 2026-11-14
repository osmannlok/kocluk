<?php
/**
 * includes/footer.php
 * Aşiroğlu Yöresel - Site Alt Kısmı (Footer + Script'ler)
 */
?>
</main>

<!-- FOOTER -->
<footer class="bg-dark text-light pt-5 pb-4 mt-5">
    <div class="container">
        <div class="row">

            <!-- Hakkında -->
            <div class="col-md-4 mb-4">
                <h5 class="fw-bold mb-3">Aşiroğlu Yöresel</h5>
                <p class="small text-secondary">
                    Kahramanmaraş'ın eşsiz yöresel lezzetlerini ve ürünlerini sizlerle buluşturuyoruz.
                    Doğallık ve kalite bizim önceliğimizdir.
                </p>
            </div>

            <!-- Hızlı Linkler -->
            <div class="col-md-4 mb-4">
                <h5 class="fw-bold mb-3">Hızlı Linkler</h5>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="/index.php" class="text-secondary text-decoration-none">Ana Sayfa</a></li>
                    <li class="mb-2"><a href="/product.php" class="text-secondary text-decoration-none">Ürünler</a></li>
                    <li class="mb-2"><a href="/campaigns.php" class="text-secondary text-decoration-none">Kampanyalar</a></li>
                    <li class="mb-2"><a href="/announcements.php" class="text-secondary text-decoration-none">Duyurular</a></li>
                    <li class="mb-2"><a href="/about.php" class="text-secondary text-decoration-none">Hakkımızda</a></li>
                    <li class="mb-2"><a href="/contact.php" class="text-secondary text-decoration-none">İletişim</a></li>
                </ul>
            </div>

            <!-- Sosyal Medya -->
            <div class="col-md-4 mb-4">
                <h5 class="fw-bold mb-3">Bizi Takip Edin</h5>
                <div class="d-flex gap-3 fs-4">
                    <a href="" target="_blank" rel="noopener noreferrer" class="text-light" aria-label="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="https://instagram.com/asiroglu.yoresel" target="_blank" rel="noopener noreferrer" class="text-light" aria-label="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="" target="_blank" rel="noopener noreferrer" class="text-light" aria-label="Twitter">
                        <i class="bi bi-twitter-x"></i>
                    </a>
                    <a href="https://wa.me/905467446722" target="_blank" rel="noopener noreferrer" class="text-light" aria-label="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                </div>
            </div>

        </div>

        <hr class="border-secondary">

        <div class="text-center small text-secondary">
            &copy; <?php echo date('Y'); ?> Aşiroğlu Yöresel. Tüm hakları saklıdır.
        </div>
    </div>
</footer>
<!-- /FOOTER -->

<!-- jQuery (opsiyonel) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap 5 JS Bundle (Popper dahil) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Site özel JS -->
<script src="/assets/js/main.js"></script>

</body>
</html>