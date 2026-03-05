<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/api_helpers.php';
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Connexion';
$activePage = '';

$next = $_GET['next'] ?? '/';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $next = (string)($_POST['next'] ?? '/');

    if ($username === '' || $password === '') {
        $error = 'Identifiants invalides.';
    } else {
        $u = db()->fetchOne('SELECT * FROM users WHERE username = ? AND enabled = 1', [$username]);
        if (!$u || !password_verify($password, (string)$u['password_hash'])) {
            $error = 'Identifiants invalides.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$u['id'];
            header('Location: ' . $next);
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<main class="container mx-auto px-4 py-10 max-w-md">
    <div class="glass-card rounded-2xl p-6">
        <h1 class="text-xl font-bold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-user-lock mr-2" style="color: var(--accent-primary)"></i>
            Connexion
        </h1>

        <?php if ($error): ?>
            <div class="mb-4 px-4 py-2 rounded-xl" style="background: rgba(239,68,68,.15); color: var(--text-primary)">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">

            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Utilisateur</label>
                <input name="username" type="text" autocomplete="username" required
                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Mot de passe</label>
                <input name="password" type="password" autocomplete="current-password" required
                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
            </div>

            <button type="submit"
                    class="w-full px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl hover:from-blue-600 hover:to-purple-700">
                <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
            </button>
        </form>

        <p class="text-xs mt-4" style="color: var(--text-muted)">
            Accès limité: les modifications des paramètres nécessitent un compte admin.
        </p>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
