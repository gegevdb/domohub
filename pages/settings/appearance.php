<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — Apparence';
$activePage = 'settings';
$settingsSection = 'appearance';

$rooms = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
$unreadNotifs = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE read = 0')['c'] ?? 0);

$config = db()->fetchAll('SELECT key, value FROM config');
$configValues = [];
foreach ($config as $c) {
    $configValues[$c['key']] = $c['value'];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-4xl">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary)">
                <i class="fas fa-cog mr-3" style="color: var(--accent-primary)"></i>
                Paramètres
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">Apparence</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="glass-card rounded-2xl p-6">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-palette mr-2"></i>Personnalisation
        </h2>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Thème par défaut</label>
                <select id="default-theme" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <option value="midnight">Midnight</option>
                    <option value="ocean">Ocean</option>
                    <option value="purple">Purple</option>
                    <option value="rose">Rose</option>
                    <option value="emerald">Emerald</option>
                    <option value="catppuccin">Catppuccin</option>
                    <option value="light">Light</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Mode sombre automatique</label>
                <select id="auto-dark-mode" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <option value="disabled">Désactivé</option>
                    <option value="sunset">Au coucher du soleil</option>
                    <option value="schedule">Selon horaire</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Heure de basculement</label>
                <input type="time" id="dark-mode-time" value="20:00" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" onclick="resetAppearance()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                <i class="fas fa-undo mr-2"></i>Réinitialiser
            </button>
            <button type="button" onclick="saveAppearance()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl hover:from-blue-600 hover:to-purple-700">
                <i class="fas fa-save mr-2"></i>Enregistrer
            </button>
        </div>
    </div>

</main>

<script>
let isSyncingThemeSelect = false;

document.addEventListener('DOMContentLoaded', function() {
    // Load current settings
    loadAppearanceSettings();

    // Handle auto dark mode changes
    document.getElementById('auto-dark-mode').addEventListener('change', function() {
        const timeInput = document.getElementById('dark-mode-time').parentElement;
        if (this.value === 'disabled') {
            timeInput.style.display = 'none';
        } else {
            timeInput.style.display = 'block';
        }
    });

    // Handle theme changes
    document.getElementById('default-theme').addEventListener('change', async function() {
        if (isSyncingThemeSelect) return;
        const selectedTheme = this.value;

        // Apply theme immediately
        if (window.setPalette) {
            window.setPalette(selectedTheme);
        }

        // Save to server
        try {
            const response = await fetch('/api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.DOMOGLASS.csrfToken
                },
                body: JSON.stringify({ key: 'theme_palette', value: selectedTheme })
            });

            if (response.ok) {
                // Update active palette button
                document.querySelectorAll('.palette-btn').forEach(b => b.classList.remove('active'));
                document.querySelector(`.palette-btn[data-palette="${selectedTheme}"]`)?.classList.add('active');
            } else {
                console.error('Failed to save theme');
                showToast('Erreur lors de la sauvegarde du thème', 'error');
            }
        } catch (error) {
            console.error('Error saving theme:', error);
            showToast('Erreur réseau lors de la sauvegarde du thème', 'error');
        }
    });

    // Sync dropdown with current theme
    syncThemeDropdown();

    // Listen for theme changes to update dropdown
    window.addEventListener('paletteChanged', syncThemeDropdown);

    // Initialize time input visibility
    document.getElementById('auto-dark-mode').dispatchEvent(new Event('change'));
});

function syncThemeDropdown() {
    // Update dropdown selection to match current theme
    const currentTheme = document.body.dataset.palette;
    if (currentTheme) {
        const select = document.getElementById('default-theme');
        if (select.value === currentTheme) return;

        isSyncingThemeSelect = true;
        try {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                window.jQuery(select).val(currentTheme).trigger('change.select2');
            } else {
                select.value = currentTheme;
            }
        } finally {
            // Laisse Select2 finir son rendu avant de réactiver l'event handler
            setTimeout(function() { isSyncingThemeSelect = false; }, 0);
        }
    }
}

async function loadAppearanceSettings() {
    try {
        // Load auto dark mode setting
        const autoDarkMode = await fetch('/api/settings.php?key=auto_dark_mode').then(r => r.json());
        const autoDarkModeValue = autoDarkMode?.data?.value ?? '';
        if (autoDarkMode.success && autoDarkModeValue) {
            document.getElementById('auto-dark-mode').value = autoDarkModeValue;
        }

        // Load dark mode time
        const darkModeTime = await fetch('/api/settings.php?key=dark_mode_time').then(r => r.json());
        const darkModeTimeValue = darkModeTime?.data?.value ?? '';
        if (darkModeTime.success && darkModeTimeValue) {
            document.getElementById('dark-mode-time').value = darkModeTimeValue;
        }

        // Apply visibility after loading
        document.getElementById('auto-dark-mode').dispatchEvent(new Event('change'));
    } catch (e) {
        console.warn('Failed to load appearance settings:', e);
    }
}

async function saveAppearance() {
    const saveBtn = document.querySelector('button[onclick="saveAppearance()"]');
    const originalText = saveBtn.innerHTML;

    // Show loading state
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enregistrement...';
    saveBtn.disabled = true;

    try {
        const settings = [
            { key: 'auto_dark_mode', value: document.getElementById('auto-dark-mode').value },
            { key: 'dark_mode_time', value: document.getElementById('dark-mode-time').value }
        ];

        // Save all settings
        const promises = settings.map(setting =>
            fetch('/api/settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.DOMOGLASS.csrfToken
                },
                body: JSON.stringify(setting)
            })
        );

        const results = await Promise.all(promises);
        const failures = results.filter(r => !r.ok);

        if (failures.length === 0) {
            showToast('Paramètres d\'apparence enregistrés avec succès', 'success');

            // Reload settings to ensure consistency
            loadAppearanceSettings();
        } else {
            throw new Error('Some settings failed to save');
        }

    } catch (error) {
        console.error('Save failed:', error);
        showToast('Erreur lors de la sauvegarde', 'error');
    } finally {
        // Reset button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
}

function resetAppearance() {
    if (confirm('Réinitialiser les paramètres d\'apparence aux valeurs par défaut ?')) {
        document.getElementById('auto-dark-mode').value = 'disabled';
        document.getElementById('dark-mode-time').value = '20:00';
        document.getElementById('auto-dark-mode').dispatchEvent(new Event('change'));
    }
}

function showToast(message, type = 'info') {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 ${type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info'} mr-2"></i>${message}`;

    document.body.appendChild(toast);

    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
