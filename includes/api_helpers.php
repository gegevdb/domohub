<?php
/**
 * DOMOGLASS PRO — Helpers API communes
 * Réponses JSON, CSRF, rate limiting.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// ============================================================
//  Réponses JSON standardisées
// ============================================================

function apiSuccess(mixed $data = null, string $message = 'OK', int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
        'ts'      => time(),
    ]);
    exit;
}

function apiError(string $message, int $code = 400, mixed $data = null): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => $message,
        'data'    => $data,
        'ts'      => time(),
    ]);
    exit;
}

// ============================================================
//  CSRF
// ============================================================

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? $_POST['_csrf'] ?? $_GET['_csrf'] ?? '';

    if (!verifyCsrfToken($token)) {
        apiError('Token CSRF invalide', 403);
    }
}

// ============================================================
//  Rate limiting simple (par IP, stocké en SQLite)
// ============================================================

function checkRateLimit(int $limit = API_RATE_LIMIT): void
{
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = 'ratelimit:' . $ip . ':' . date('YmdHi');

    $current = (int)db()->getConfig($key, 0);
    if ($current >= $limit) {
        apiError('Trop de requêtes. Réessayez dans une minute.', 429);
    }

    db()->setConfig($key, $current + 1);
}

// ============================================================
//  Validation simple
// ============================================================

function requireMethod(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        apiError('Méthode non autorisée', 405);
    }
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (empty($raw)) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requireParam(array $data, string ...$keys): void
{
    foreach ($keys as $key) {
        if (!isset($data[$key]) || $data[$key] === '') {
            apiError("Paramètre requis manquant : $key", 422);
        }
    }
}
