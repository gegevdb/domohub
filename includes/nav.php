<?php
/**
 * DOMOGLASS PRO — Barre de navigation principale
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$currentUser = $currentUser ?? authCurrentUser();

$activePage   = $activePage ?? 'dashboard';
$unreadNotifs = $unreadNotifs ?? 0;
$appName      = $appName ?? 'DomoGlass Pro';

$navItems = [
    ['id' => 'dashboard',   'icon' => 'fa-th-large',       'label' => 'Dashboard',    'href' => '/'],
    ['id' => 'devices',     'icon' => 'fa-plug',            'label' => 'Appareils',    'href' => '/pages/devices.php'],
    ['id' => 'automations', 'icon' => 'fa-robot',           'label' => 'Automations',  'href' => '/pages/automations.php'],
    ['id' => 'energy',      'icon' => 'fa-bolt',            'label' => 'Énergie',      'href' => '/pages/energy.php'],
    ['id' => 'security',    'icon' => 'fa-shield-alt',      'label' => 'Sécurité',     'href' => '/pages/security.php'],
    ['id' => 'network',     'icon' => 'fa-network-wired',   'label' => 'Réseaux',      'href' => '/pages/reseaux.php'],
    ['id' => 'zigbee',      'icon' => 'fa-satellite-dish',  'label' => 'Zigbee',       'href' => '/pages/zigbee.php'],
    ['id' => 'settings',    'icon' => 'fa-cog',             'label' => 'Paramètres',   'href' => '/pages/settings/general.php'],
];
?>

<!-- Sidebar (desktop) -->
<aside id="dg-sidebar" class="hidden lg:flex fixed top-0 left-0 h-full glass px-4 py-5 flex-col z-40">
    <a href="/" class="flex items-center space-x-3 group mb-6">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform">
            <i class="fas fa-home text-white text-sm"></i>
        </div>
        <div class="leading-tight">
            <div class="font-bold text-base" style="color: var(--text-primary)"><?= htmlspecialchars($appName) ?></div>
            <div class="text-xs" style="color: var(--text-muted)">Navigation</div>
        </div>
    </a>

    <nav class="flex flex-col space-y-1">
        <?php foreach ($navItems as $item): ?>
            <a href="<?= $item['href'] ?>"
               class="flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition-all
                      <?= $activePage === $item['id'] ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-lg' : 'hover:opacity-80' ?>"
               style="text-decoration:none; <?= $activePage !== $item['id'] ? 'color: var(--text-secondary)' : '' ?>">
                <i class="fas <?= $item['icon'] ?> w-5 text-center"></i>
                <span><?= $item['label'] ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="mt-auto pt-4 space-y-2">
        <?php if ($currentUser): ?>
            <div class="text-xs px-3" style="color: var(--text-muted)">
                Connecté: <span style="color: var(--text-secondary)"><?= htmlspecialchars($currentUser['username'] ?? '') ?></span>
            </div>
            <a href="/logout.php"
               class="flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium hover:opacity-80 transition"
               style="text-decoration:none; color: var(--text-secondary)">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span>Déconnexion</span>
            </a>
        <?php endif; ?>
    </div>
</aside>

<nav class="glass sticky top-0 z-50 px-4 py-3 flex items-center justify-between" id="main-nav">

    <!-- Logo (mobile) -->
    <a href="/" class="flex items-center space-x-3 group lg:hidden">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg group-hover:scale-105 transition-transform">
            <i class="fas fa-home text-white text-sm"></i>
        </div>
        <span class="font-bold text-lg hidden sm:block" style="color: var(--text-primary)">
            <?= htmlspecialchars($appName) ?>
        </span>
    </a>

    <!-- Actions droite -->
    <div class="flex items-center space-x-3">

        <!-- Indicateur MQTT -->
        <div class="hidden sm:flex items-center space-x-2 glass px-3 py-1.5 rounded-xl text-xs font-medium">
            <div class="w-2 h-2 rounded-full bg-gray-400" id="mqtt-status-dot"></div>
            <span style="color: var(--text-muted)" id="mqtt-status-text">MQTT</span>
        </div>

        <!-- Indicateur ZHA -->
        <div class="hidden sm:flex items-center space-x-2 glass px-3 py-1.5 rounded-xl text-xs font-medium">
            <div class="w-2 h-2 rounded-full bg-gray-400" id="zha-status-dot"></div>
            <span style="color: var(--text-muted)">ZHA</span>
        </div>

        <!-- Notifications -->
        <div class="relative">
            <button onclick="toggleNotifications()"
                    class="glass p-2 rounded-xl hover:opacity-80 transition relative"
                    aria-label="Notifications" aria-haspopup="true">
                <i class="fas fa-bell" style="color: var(--text-primary)"></i>
                <?php if ($unreadNotifs > 0): ?>
                <span class="absolute -top-1 -right-1 w-5 h-5 rounded-full text-xs flex items-center justify-center text-white font-bold"
                      style="background: var(--accent-danger)" id="notif-count">
                    <?= min($unreadNotifs, 9) ?>
                </span>
                <?php endif; ?>
            </button>

            <!-- Panneau notifications -->
            <div id="notification-panel"
                 class="hidden absolute right-0 top-12 w-80 glass rounded-2xl p-4 shadow-2xl z-50"
                 role="dialog" aria-label="Notifications">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-bold text-sm" style="color: var(--text-primary)">Notifications</h3>
                    <button onclick="markAllRead()" class="text-xs hover:opacity-80 transition"
                            style="color: var(--accent-primary)">Tout lire</button>
                </div>
                <div id="notif-list" class="space-y-2 max-h-72 overflow-y-auto">
                    <p class="text-xs text-center py-4" style="color: var(--text-muted)">Aucune notification</p>
                </div>
            </div>
        </div>

        <!-- Menu mobile hamburger -->
        <button onclick="toggleMobileMenu()" class="lg:hidden glass p-2 rounded-xl hover:opacity-80"
                aria-label="Menu" aria-expanded="false" id="mobile-menu-btn">
            <i class="fas fa-bars" style="color: var(--text-primary)"></i>
        </button>
    </div>
</nav>

<!-- Menu mobile -->
<div id="mobile-menu" class="hidden lg:hidden glass m-2 rounded-2xl p-4 space-y-1">
    <?php foreach ($navItems as $item): ?>
        <a href="<?= $item['href'] ?>"
           class="flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition
                  <?= $activePage === $item['id'] ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white' : 'hover:opacity-80' ?>"
           style="<?= $activePage !== $item['id'] ? 'color: var(--text-primary)' : '' ?>">
            <i class="fas <?= $item['icon'] ?> w-5 text-center"></i>
            <span><?= $item['label'] ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Toast container -->
<div id="toast-container" class="fixed bottom-6 right-6 z-50 space-y-3" aria-live="polite"></div>
