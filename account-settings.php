<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (configuredAppUrl() !== '' && !requestTargetsConfiguredAppHost()) {
    header('Location: ' . appUrl('account-settings'));
    exit;
}

$pdo = db();
$currentUser = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        verifyCsrf();

        switch ($action) {
            case 'logout':
                logoutUser();
                flash('success', 'Sessão encerrada.');
                redirectTo('');

            case 'account_update_profile':
                updateUserProfile(
                    $pdo,
                    (int) $currentUser['id'],
                    (string) ($_POST['name'] ?? ''),
                    $_FILES['avatar'] ?? []
                );
                flash('success', 'Perfil atualizado.');
                redirectTo('account-settings');

            case 'account_update_password':
                updateUserPassword(
                    $pdo,
                    (int) $currentUser['id'],
                    (string) ($_POST['current_password'] ?? ''),
                    (string) ($_POST['new_password'] ?? ''),
                    (string) ($_POST['new_password_confirm'] ?? '')
                );
                flash('success', 'Senha atualizada.');
                redirectTo('account-settings');

            case 'account_delete_workspace':
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não pode ser removido.');
                }
                deleteWorkspaceOwnedByUser($pdo, $workspaceId, (int) $currentUser['id']);
                ensureActiveWorkspaceSessionForUser((int) $currentUser['id']);
                flash('success', 'Workspace removido.');
                redirectTo('account-settings');

            case 'account_leave_workspace':
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não permite sair.');
                }
                leaveWorkspace($pdo, $workspaceId, (int) $currentUser['id']);
                ensureActiveWorkspaceSessionForUser((int) $currentUser['id']);
                flash('success', 'Você saiu do workspace.');
                redirectTo('account-settings');

            default:
                throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirectTo('account-settings');
    }
}

$currentUser = requireAuth();
$currentWorkspaceId = activeWorkspaceId($currentUser);
$currentWorkspace = $currentWorkspaceId !== null ? workspaceById((int) $currentWorkspaceId) : null;
$isPersonalWorkspace = !empty($currentWorkspace['is_personal']);
$workspaceMemberships = workspaceMembershipsDetailedForUser((int) $currentUser['id']);
$flashes = getFlashes();
$stylesAssetVersion = is_file(__DIR__ . '/assets/styles.css')
    ? (string) filemtime(__DIR__ . '/assets/styles.css')
    : '103';
$themeBexonAssetVersion = is_file(__DIR__ . '/assets/theme-bexon.css')
    ? (string) filemtime(__DIR__ . '/assets/theme-bexon.css')
    : '1';
$complianceAssetVersion = assetVersion('assets/compliance.js');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> - Configurações da Conta</title>
    <link rel="icon" type="image/png" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="shortcut icon" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(appPath('assets/styles.css?v=' . $stylesAssetVersion)) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/theme-bexon.css?v=' . $themeBexonAssetVersion)) ?>">
    <script src="<?= e(appPath('assets/compliance.js?v=' . $complianceAssetVersion)) ?>" defer></script>
</head>
<body class="is-dashboard is-workspace-settings is-account-settings">
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
            <div class="top-nav-leading">
                <a href="<?= e(appPath('#tasks')) ?>" class="btn btn-mini btn-ghost nav-back-button" aria-label="Voltar para dashboard">
                    <span aria-hidden="true">&#8592;</span>
                    <span>Voltar</span>
                </a>
                <a href="<?= e(appPath()) ?>" class="brand" aria-label="<?= e(APP_NAME) ?>">
                    <img
                        src="<?= e(appPath('assets/Bexon - Logo Horizontal.png?v=1')) ?>"
                        alt="<?= e(APP_NAME) ?>"
                        class="brand-lockup"
                        width="116"
                        height="29"
                    >
                </a>
            </div>

            <div class="user-chip">
                <?= renderUserAvatar($currentUser) ?>
                <div>
                    <strong><?= e((string) $currentUser['name']) ?></strong>
                    <span><?= e((string) $currentUser['email']) ?></span>
                </div>
            </div>
            <div class="top-nav-actions">
                <a
                    href="<?= e(appPath('account-settings')) ?>"
                    class="icon-gear-button top-account-settings-button"
                    aria-label="Configurações da conta"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10.3 2.6h3.4l.5 2a7.8 7.8 0 0 1 1.9.8l1.8-1 2.4 2.4-1 1.8c.3.6.6 1.2.8 1.9l2 .5v3.4l-2 .5a7.8 7.8 0 0 1-.8 1.9l1 1.8-2.4 2.4-1.8-1a7.8 7.8 0 0 1-1.9.8l-.5 2h-3.4l-.5-2a7.8 7.8 0 0 1-1.9-.8l-1.8 1-2.4-2.4 1-1.8a7.8 7.8 0 0 1-.8-1.9l-2-.5v-3.4l2-.5c.2-.7.5-1.3.8-1.9l-1-1.8 2.4-2.4 1.8 1c.6-.3 1.2-.6 1.9-.8l.5-2Z"></path>
                        <circle cx="12" cy="12" r="3.2"></circle>
                    </svg>
                </a>
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
                    <h2>Configurações da conta</h2>
                    <p>Atualize seus dados e gerencie os workspaces que você participa.</p>
                </div>

                <div class="workspace-settings-grid account-settings-grid">
                    <section class="workspace-settings-card">
                        <h3>Perfil</h3>
                        <form method="post" class="workspace-settings-form account-profile-form" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="account_update_profile">
                            <div class="account-profile-photo-row">
                                <?= renderUserAvatar($currentUser, 'avatar account-profile-avatar') ?>
                                <label class="account-profile-photo-field">
                                    <span>Foto de perfil</span>
                                    <input
                                        type="file"
                                        name="avatar"
                                        accept="image/png,image/jpeg,image/webp,image/gif"
                                    >
                                </label>
                            </div>
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
                            <button type="submit" class="btn btn-mini">Salvar perfil</button>
                        </form>
                    </section>

                    <?php
                    $workspacePlanCardExtraClass = 'workspace-settings-card account-plan-card';
                    $workspacePlanCardHeading = 'Plano';
                    $workspacePlanCardContextLabel = 'Plano ativo';
                    include __DIR__ . '/partials/workspace_plan_card.php';
                    unset($workspacePlanCardExtraClass, $workspacePlanCardHeading, $workspacePlanCardContextLabel);
                    ?>

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

                    <section class="workspace-settings-card account-privacy-card">
                        <h3>Privacidade e dados</h3>
                        <p class="workspace-settings-member-empty">
                            Consulte suas opcoes de privacidade, direitos LGPD, termos e politica de cookies.
                        </p>
                        <div class="account-privacy-links">
                            <a href="<?= e(siteUrl('dados')) ?>" class="btn btn-mini">Meus dados</a>
                            <a href="<?= e(siteUrl('privacidade')) ?>" class="btn btn-mini btn-ghost">Privacidade</a>
                            <a href="<?= e(siteUrl('termos')) ?>" class="btn btn-mini btn-ghost">Termos</a>
                        </div>
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
                                $workspaceRoleLabel = workspaceRoles()[$workspaceRole] ?? 'Usuário';
                                $isOwner = (bool) ($workspaceItem['is_owner'] ?? false);
                                $isPersonalWorkspace = !empty($workspaceItem['is_personal']);
                                $isActiveWorkspace = $currentWorkspaceId === $workspaceId;
                                $memberCount = (int) ($workspaceItem['member_count'] ?? 0);
                                $creatorName = trim((string) ($workspaceItem['creator_name'] ?? ''));
                                ?>
                                <li class="workspace-settings-member-item account-workspace-item<?= $isActiveWorkspace ? ' is-active-workspace' : '' ?><?= $isPersonalWorkspace ? ' is-personal-workspace' : '' ?>">
                                    <?= renderWorkspaceAvatar($workspaceItem, 'avatar small account-workspace-avatar') ?>
                                    <div class="workspace-settings-member-meta account-workspace-meta">
                                        <div class="account-workspace-title-row">
                                            <strong><?= e($workspaceName) ?></strong>
                                            <div class="account-workspace-badges">
                                                <?php if ($isActiveWorkspace): ?>
                                                    <span class="account-workspace-badge account-workspace-badge-active">Workspace ativo</span>
                                                <?php endif; ?>
                                                <?php if ($isPersonalWorkspace): ?>
                                                    <span class="account-workspace-badge account-workspace-badge-personal">Pessoal</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="account-workspace-meta-row">
                                            <span class="workspace-member-role workspace-role-<?= e($workspaceRole) ?>"><?= e($workspaceRoleLabel) ?></span>
                                            <span class="account-workspace-meta-text">
                                            <?= $isOwner ? 'Criado por você' : ('Criado por ' . e($creatorName !== '' ? $creatorName : 'outro usuário')) ?>
                                            &middot; <?= $isPersonalWorkspace ? 'Workspace pessoal' : (e((string) $memberCount) . ' membro(s)') ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="account-workspace-actions">
                                        <?php if (!$isPersonalWorkspace && $isOwner): ?>
                                            <form method="post" class="workspace-settings-member-remove">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="account_delete_workspace">
                                                <input type="hidden" name="workspace_id" value="<?= e((string) $workspaceId) ?>">
                                                <button type="submit" class="btn btn-mini btn-danger">Remover</button>
                                            </form>
                                        <?php elseif (!$isPersonalWorkspace): ?>
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
