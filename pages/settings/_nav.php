<?php
declare(strict_types=1);

$settingsSection = $settingsSection ?? 'general';

$items = [
    ['id' => 'general',       'label' => 'Général',         'icon' => 'fa-home',            'href' => '/pages/settings/general.php'],
    ['id' => 'network',       'label' => 'Réseau',          'icon' => 'fa-network-wired',   'href' => '/pages/settings/network.php'],
    ['id' => 'dongles',       'label' => 'Dongles USB',     'icon' => 'fa-usb',             'href' => '/pages/settings/dongles.php'],
    ['id' => 'mqtt',          'label' => 'MQTT',            'icon' => 'fa-broadcast-tower', 'href' => '/pages/settings/mqtt.php'],
    ['id' => 'home',          'label' => 'Maison & Carte',  'icon' => 'fa-map-marked-alt',  'href' => '/pages/settings/home.php'],
    ['id' => 'homeassistant', 'label' => 'Home Assistant',  'icon' => 'fa-home',            'href' => '/pages/settings/homeassistant.php'],
    ['id' => 'security',      'label' => 'Sécurité',        'icon' => 'fa-shield-alt',      'href' => '/pages/settings/security.php'],
    ['id' => 'notifications', 'label' => 'Notifications',   'icon' => 'fa-bell',            'href' => '/pages/settings/notifications.php'],
    ['id' => 'apis',          'label' => 'APIs',            'icon' => 'fa-code',            'href' => '/pages/settings/apis.php'],
    ['id' => 'appearance',    'label' => 'Apparence',       'icon' => 'fa-palette',         'href' => '/pages/settings/appearance.php'],
    ['id' => 'energy',        'label' => 'Énergie',         'icon' => 'fa-bolt',            'href' => '/pages/settings/energy.php'],
    ['id' => 'backup',        'label' => 'Sauvegarde',      'icon' => 'fa-save',            'href' => '/pages/settings/backup.php'],
    ['id' => 'advanced',      'label' => 'Avancé',          'icon' => 'fa-tools',           'href' => '/pages/settings/advanced.php'],
];
?>

<div class="glass-card rounded-2xl p-2 mb-6">
    <div class="flex flex-wrap gap-2">
        <?php foreach ($items as $item): ?>
            <a href="<?= $item['href'] ?>"
               class="px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer"
               style="text-decoration: none; background: <?= $settingsSection === $item['id'] ? 'var(--accent-primary)20' : 'transparent' ?>; color: <?= $settingsSection === $item['id'] ? 'var(--accent-primary)' : 'var(--text-muted)' ?>">
                <i class="fas <?= $item['icon'] ?> mr-2"></i><?= $item['label'] ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
