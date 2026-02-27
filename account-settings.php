<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();
$currentUser = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        verifyCsrf();

        switch ($action) {
            case 'logout':
                logoutUser();
                flash('success', 'Sessao encerrada.');
                redirectTo('index.php');

            case 'account_update_name':
                updateUserDisplayName(
                    $pdo,
                    (int) $currentUser['id'],
                    (string) ($_POST['name'] ?? '')
                );
                flash('success', 'Nome atualizado.');
                redirectTo('account-settings.php');

            case 'account_update_password':
                updateUserPassword(
                    $pdo,
                    (int) $currentUser['id'],
                    (string) ($_POST['current_password'] ?? ''),
                    (string) ($_POST['new_password'] ?? ''),
                    (string) ($_POST['new_password_confirm'] ?? '')
                );
                flash('success', 'Senha atualizada.');
                redirectTo('account-settings.php');

            case 'account_delete_workspace':
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                deleteWorkspaceOwnedByUser($pdo, $workspaceId, (int) $currentUser['id']);
                ensureActiveWorkspaceSessionForUser((int) $currentUser['id']);
                flash('success', 'Workspace removido.');
                redirectTo('account-settings.php');

            case 'account_leave_workspace':
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                leaveWorkspace($pdo, $workspaceId, (int) $currentUser['id']);
                ensureActiveWorkspaceSessionForUser((int) $currentUser['id']);
                flash('success', 'Voce saiu do workspace.');
                redirectTo('account-settings.php');

            default:
                throw new RuntimeException('Acao invalida.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirectTo('account-settings.php');
    }
}

$currentUser = requireAuth();
$currentWorkspaceId = activeWorkspaceId($currentUser);
$workspaceMemberships = workspaceMembershipsDetailedForUser((int) $currentUser['id']);
$flashes = getFlashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> - Configuracoes da Conta</title>
    <link rel="icon" type="image/svg+xml" href="assets/logo-mark.svg?v=1">
    <link rel="shortcut icon" href="assets/logo-mark.svg?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css?v=40">
</head>
<body class="is-dashboard is-workspace-settings">
    <div class="bg-layer bg-layer-one" aria-hidden="true"></div>
    <div class="bg-layer bg-layer-two" aria-hidden="true"></div>
    <div class="grain" aria-hidden="true"></div>

    <div class="app-shell">
        <?php if ($flashes): ?>
            <div class="flash-stack" aria-live="polite">
                <?php foreach ($flashes as $flash): ?>
                    <div class="flash flash-<?= e((string) ($flash['type'] ?? 'info')) ?>" data-flash>
                        <span><?= e((string) ($flash['message'] ?? '')) ?></span>
                        <button type="button" class="flash-close" data-flash-close aria-label="Fechar">&#10005;</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <header class="top-nav dashboard-nav">
            <a href="index.php" class="brand" aria-label="WorkForm">
                <img src="assets/WorkForm - Logo (Negativa).svg?v=1" alt="WorkForm" class="brand-lockup" width="116" height="29">
            </a>

            <div class="user-chip">
                <div class="avatar" aria-hidden="true"><?= e(strtoupper(substr((string) $currentUser['name'], 0, 1))) ?></div>
                <div>
                    <strong><?= e((string) $currentUser['name']) ?></strong>
                    <span><?= e((string) $currentUser['email']) ?></span>
                </div>
            </div>
            <div class="top-nav-actions">
                <a
                    href="account-settings.php"
                    class="icon-gear-button top-account-settings-button"
                    aria-label="Configuracoes da conta"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10.3 2.6h3.4l.5 2a7.8 7.8 0 0 1 1.9.8l1.8-1 2.4 2.4-1 1.8c.3.6.6 1.2.8 1.9l2 .5v3.4l-2 .5a7.8 7.8 0 0 1-.8 1.9l1 1.8-2.4 2.4-1.8-1a7.8 7.8 0 0 1-1.9.8l-.5 2h-3.4l-.5-2a7.8 7.8 0 0 1-1.9-.8l-1.8 1-2.4-2.4 1-1.8a7.8 7.8 0 0 1-.8-1.9l-2-.5v-3.4l2-.5c.2-.7.5-1.3.8-1.9l-1-1.8 2.4-2.4 1.8 1c.6-.3 1.2-.6 1.9-.8l.5-2Z"></path>
                        <circle cx="12" cy="12" r="3.2"></circle>
                    </svg>
                </a>
                <a href="index.php#tasks" class="btn btn-mini btn-ghost">Voltar</a>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-pill btn-logout"><span>Sair</span></button>
                </form>
            </div>
        </header>

        <main class="workspace-settings-page">
            <section class="panel workspace-settings-panel">
                <div class="panel-header workspace-settings-header">
                    <h2>Configuracoes da conta</h2>
                    <p>Atualize seus dados e gerencie os workspaces que voce participa.</p>
                </div>

                <div class="workspace-settings-grid account-settings-grid">
                    <section class="workspace-settings-card">
                        <h3>Perfil</h3>
                        <form method="post" class="workspace-settings-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="account_update_name">
                            <label>
                                <span>Nome</span>
                                <input
                                    type="text"
                                    name="name"
                                    maxlength="80"
                                    value="<?= e((string) $currentUser['name']) ?>"
                                    required
                                >
                            </label>
                            <button type="submit" class="btn btn-mini">Salvar nome</button>
                        </form>
                    </section>

                    <section class="workspace-settings-card">
                        <h3>Senha</h3>
                        <form method="post" class="workspace-settings-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="account_update_password">
                            <label>
                                <span>Senha atual</span>
                                <input type="password" name="current_password" autocomplete="current-password" required>
                            </label>
                            <label>
                                <span>Nova senha</span>
                                <input type="password" name="new_password" autocomplete="new-password" required>
                            </label>
                            <label>
                                <span>Confirmar nova senha</span>
                                <input type="password" name="new_password_confirm" autocomplete="new-password" required>
                            </label>
                            <button type="submit" class="btn btn-mini">Atualizar senha</button>
                        </form>
                    </section>
                </div>

                <section class="workspace-settings-card account-workspaces-card">
                    <h3>Workspaces</h3>
                    <ul class="workspace-settings-members">
                        <?php if (!$workspaceMemberships): ?>
                            <li class="workspace-settings-member-empty">Nenhum workspace encontrado.</li>
                        <?php else: ?>
                            <?php foreach ($workspaceMemberships as $workspaceItem): ?>
                                <?php
                                $workspaceId = (int) ($workspaceItem['id'] ?? 0);
                                $workspaceName = (string) ($workspaceItem['name'] ?? 'Workspace');
                                $workspaceRole = normalizeWorkspaceRole((string) ($workspaceItem['member_role'] ?? 'member'));
                                $workspaceRoleLabel = workspaceRoles()[$workspaceRole] ?? 'Usuario';
                                $isOwner = (bool) ($workspaceItem['is_owner'] ?? false);
                                $isActiveWorkspace = $currentWorkspaceId === $workspaceId;
                                $memberCount = (int) ($workspaceItem['member_count'] ?? 0);
                                $creatorName = trim((string) ($workspaceItem['creator_name'] ?? ''));
                                ?>
                                <li class="workspace-settings-member-item">
                                    <div class="avatar small" aria-hidden="true"><?= e(strtoupper(substr($workspaceName, 0, 1))) ?></div>
                                    <div class="workspace-settings-member-meta">
                                        <strong><?= e($workspaceName) ?></strong>
                                        <span class="workspace-member-role workspace-role-<?= e($workspaceRole) ?>"><?= e($workspaceRoleLabel) ?></span>
                                        <span>
                                            <?= $isOwner ? 'Criado por voce' : ('Criado por ' . e($creatorName !== '' ? $creatorName : 'outro usuario')) ?>
                                            &middot; <?= e((string) $memberCount) ?> membro(s)
                                        </span>
                                        <?php if ($isActiveWorkspace): ?>
                                            <span class="account-workspace-active">Workspace ativo</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="account-workspace-actions">
                                        <?php if ($isOwner): ?>
                                            <form method="post" class="workspace-settings-member-remove">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="account_delete_workspace">
                                                <input type="hidden" name="workspace_id" value="<?= e((string) $workspaceId) ?>">
                                                <button type="submit" class="btn btn-mini btn-danger">Remover</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" class="workspace-settings-member-remove">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="account_leave_workspace">
                                                <input type="hidden" name="workspace_id" value="<?= e((string) $workspaceId) ?>">
                                                <button type="submit" class="btn btn-mini btn-ghost">Sair</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </section>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener("click", function (event) {
            var closeButton = event.target.closest("[data-flash-close]");
            if (!closeButton) {
                return;
            }
            var flash = closeButton.closest("[data-flash]");
            if (flash) {
                flash.remove();
            }
        });
    </script>
</body>
</html>
