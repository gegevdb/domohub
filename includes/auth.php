<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_helpers.php';

function authCurrentUser(): array|null
{
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) return null;
    $uid = (int)$uid;
    $u = db()->fetchOne('SELECT id, username, role FROM users WHERE id = ?', [$uid]);
    return $u ?: null;
}

function authIsLoggedIn(): bool
{
    return authCurrentUser() !== null;
}

function authRequireLogin(): void
{
    if (!authIsLoggedIn()) {
        $next = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login.php?next=' . urlencode($next));
        exit;
    }
}

function authRequireAdmin(): void
{
    $u = authCurrentUser();
    if (!$u) {
        authRequireLogin();
    }
    if (($u['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'Accès refusé.';
        exit;
    }
}

function authApiRequireAdmin(): void
{
    $u = authCurrentUser();
    if (!$u) {
        apiError('Non authentifié', 401);
    }
    if (($u['role'] ?? '') !== 'admin') {
        apiError('Accès refusé', 403);
    }
}
