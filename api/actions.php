<?php
/**
 * DOMOGLASS PRO — API Actions (historique)
 * GET /api/actions.php           → liste paginée
 * GET /api/actions.php?device=N  → actions d'un device
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';
header('Content-Type: application/json; charset=utf-8');
requireMethod('GET');
checkRateLimit();

$limit    = min((int)($_GET['limit'] ?? 50), 200);
$offset   = (int)($_GET['offset'] ?? 0);
$deviceId = isset($_GET['device']) ? (int)$_GET['device'] : null;
$source   = $_GET['source'] ?? null;

$sql    = 'SELECT * FROM actions WHERE 1=1';
$params = [];
if ($deviceId) { $sql .= ' AND device_id = ?'; $params[] = $deviceId; }
if ($source)   { $sql .= ' AND source = ?';    $params[] = $source; }
$sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
$params[] = $limit;
$params[] = $offset;

$rows = db()->fetchAll($sql, $params);
foreach ($rows as &$r) {
    if ($r['payload']) $r['payload'] = json_decode($r['payload'], true);
}

$total = db()->fetchOne('SELECT COUNT(*) as c FROM actions' . ($deviceId ? ' WHERE device_id = ?' : ''),
    $deviceId ? [$deviceId] : [])['c'] ?? 0;

apiSuccess(['actions' => $rows, 'total' => (int)$total, 'limit' => $limit, 'offset' => $offset]);
