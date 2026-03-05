<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

header('Content-Type: application/json; charset=utf-8');
requireMethod('GET');
checkRateLimit();

$rooms = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
apiSuccess($rooms);
