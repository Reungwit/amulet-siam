        </div><!-- /.container -->
    </main><!-- /.site-main -->

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Amulet Siam. ระบบเช่าพระและประมูลพระเครื่องยอดนิยม. All rights reserved.</p>
            <p class="text-light" style="opacity: 0.7; font-size: 0.8rem; margin-top: 0.5rem;">
                มาตรฐานระบบความปลอดภัยระดับสากล | ตรวจสอบพระแท้ 100%
            </p>
        </div>
    </footer>

    <script>
        window.FIREBASE_CONFIG = {
            apiKey: <?= json_encode(defined('FIREBASE_API_KEY') ? FIREBASE_API_KEY : '') ?>,
            authDomain: "amuletsiam.firebaseapp.com",
            projectId: "amuletsiam",
            storageBucket: "amuletsiam.firebasestorage.app",
            messagingSenderId: "880058490397",
            appId: "1:880058490397:web:af3d20e6fc2e9055f1b0f4"
        };
    </script>
    <script type="module" src="js/firebase_init.js"></script>
    <script src="js/main.js"></script>
    <?php if (isset($extra_scripts) && is_array($extra_scripts)): ?>
        <?php foreach ($extra_scripts as $script): ?>
            <?php if (str_ends_with($script, '.mjs') || str_contains($script, 'type=module')): ?>
                <script type="module" src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
            <?php else: ?>
                <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>"></script>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

