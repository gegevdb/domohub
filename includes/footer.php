<?php
/**
 * DOMOGLASS PRO — Footer HTML
 */
declare(strict_types=1);
?>

</div><!-- #app-root -->

<footer class="footer-glass mt-8 px-6 py-4">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 text-xs" style="color: var(--text-muted)">
        <div class="flex items-center space-x-4">
            <span>DomoGlass Pro v<?= DOMOGLASS_VERSION ?></span>
            <span class="hidden sm:block">•</span>
            <span class="hidden sm:block"><?= date('d/m/Y H:i') ?></span>
            <span class="hidden sm:block">•</span>
            <span>gegevdb ©2026</span>
        </div>
        <div class="flex items-center space-x-1">
            <div class="w-2 h-2 rounded-full" id="status-dot" style="background: var(--status-online)"></div>
            <span id="status-text">Système opérationnel</span>
        </div>
    </div>
</footer>

<!-- Scripts JS ES modules -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    // Select2 global init
    (function() {
        function initSelect2(root) {
            if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) return;
            const $ = window.jQuery;
            const $root = root ? $(root) : $(document);

            // Évite de ré-initialiser
            $root.find('select').each(function() {
                const $sel = $(this);
                if ($sel.data('no-select2')) return;
                if ($sel.hasClass('select2-hidden-accessible')) return;

                $sel.select2({
                    width: '100%',
                    dropdownAutoWidth: true,
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initSelect2();
        });

        // Exposer pour les modals / contenus injectés
        window.DOMOGLASS_INIT_SELECT2 = initSelect2;
    })();
</script>

<script type="module" src="/js/app.js"></script>

</body>
</html>
