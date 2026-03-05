<?php
/**
 * DOMOGLASS PRO — Header HTML
 * Variables attendues : $pageTitle (string), $activePage (string)
 */
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

$appName   = db()->getConfig('app_name', 'DomoGlass Pro');
$palette   = db()->getConfig('theme_palette', 'midnight');
$pageTitle = $pageTitle ?? $appName;
$csrfToken = generateCsrfToken();

$unreadNotifs = db()->fetchOne(
    'SELECT COUNT(*) as c FROM notifications WHERE read = 0'
)['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($appName) ?></title>

    <!-- CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link href="/assets/css/domoglass-themes.css" rel="stylesheet">

    <!-- CSRF token pour JS -->
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">

    <!-- Config JS globale -->
    <script>
        window.DOMOGLASS = {
            version:    <?= json_encode(DOMOGLASS_VERSION) ?>,
            palette:    <?= json_encode($palette) ?>,
            mqttHost:   <?= json_encode(MQTT_HOST) ?>,
            mqttPortWs: <?= json_encode(MQTT_PORT_WS) ?>,
            mqttUser:   <?= json_encode(MQTT_USER) ?>,
            csrfToken:  <?= json_encode($csrfToken) ?>,
            haHost:     <?= json_encode(HA_HOST) ?>,
            haPort:     <?= json_encode(HA_PORT) ?>,
            haSSL:      <?= json_encode(HA_SSL) ?>,
            basePath:   '<?= BASE_URL ?>',
        };
    </script>
</head>
<body data-palette="<?= htmlspecialchars($palette) ?>" class="no-transition">
<div class="min-h-screen grid-bg" id="app-root">
