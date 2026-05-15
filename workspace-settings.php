<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (configuredAppUrl() !== '' && !requestTargetsConfiguredAppHost()) {
    header('Location: ' . appUrl('workspace-settings'));
    exit;
}

$pdo = db();
$currentUser = requireAuth();
$currentWorkspaceId = activeWorkspaceId($currentUser);
if ($currentWorkspaceId === null) {
    flash('error', 'Workspace ativo não encontrado.');
    redirectTo('');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        verifyCsrf();

        switch ($action) {
            case 'logout':
                logoutUser();
                flash('success', 'Sessão encerrada.');
                redirectTo('');

            case 'switch_workspace':
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                if ($workspaceId <= 0 || !userHasWorkspaceAccess((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Workspace inválido.');
                }

                setActiveWorkspaceId($workspaceId);
                redirectTo('workspace-settings');

            case 'create_workspace':
                if (!userCanCreateOwnedWorkspace((int) ($currentUser['id'] ?? 0))) {
                    throw new RuntimeException('Sua conta precisa de um plano proprio para criar novos workspaces.');
                }

                $workspaceName = normalizeWorkspaceName((string) ($_POST['workspace_name'] ?? ''));
                if ($workspaceName === '') {
                    throw new RuntimeException('Informe um nome para o workspace.');
                }

                $workspaceId = createWorkspace($pdo, $workspaceName, (int) $currentUser['id']);
                if ($workspaceId <= 0) {
                    throw new RuntimeException('Não foi possível criar o workspace.');
                }

                setActiveWorkspaceId($workspaceId);
                flash('success', 'Workspace criado.');
                redirectTo('workspace-settings');

            case 'workspace_update_profile':
            case 'workspace_update_name':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar o workspace.');
                }

                $canRenameWorkspace = !workspaceIsPersonal($workspaceId);
                updateWorkspaceProfile(
                    $pdo,
                    $workspaceId,
                    $canRenameWorkspace ? (string) ($_POST['workspace_name'] ?? '') : '',
                    $_FILES['avatar'] ?? [],
                    $canRenameWorkspace
                );
                flash('success', 'Dados do workspace atualizados.');
                redirectTo('workspace-settings');

            case 'workspace_update_task_statuses':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar os status.');
                }

                $statusKeys = $_POST['status_keys'] ?? [];
                $statusLabels = $_POST['status_labels'] ?? [];
                $statusColors = $_POST['status_colors'] ?? [];
                if (!is_array($statusKeys) || !is_array($statusLabels) || !is_array($statusColors)) {
                    throw new RuntimeException('Configuração de status inválida.');
                }

                $statusDefinitions = [];
                $statusCount = max(count($statusKeys), count($statusLabels), count($statusColors));
                for ($index = 0; $index < $statusCount; $index++) {
                    $statusDefinitions[] = [
                        'key' => (string) ($statusKeys[$index] ?? ''),
                        'label' => (string) ($statusLabels[$index] ?? ''),
                        'color' => (string) ($statusColors[$index] ?? ''),
                    ];
                }

                workspaceUpdateTaskStatusConfiguration(
                    $pdo,
                    $workspaceId,
                    $statusDefinitions,
                    trim((string) ($_POST['task_review_status_key'] ?? '')) !== ''
                        ? (string) $_POST['task_review_status_key']
                        : null,
                    (string) ($_POST['remove_status_key'] ?? ''),
                    (string) ($_POST['new_status_label'] ?? ''),
                    (string) ($_POST['new_status_color'] ?? '')
                );
                flash('success', 'Status do workspace atualizados.');
                redirectTo('workspace-settings');

            case 'workspace_update_sidebar_tools':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar as ferramentas do sidebar.');
                }

                $sidebarTools = $_POST['sidebar_tools'] ?? [];
                if (!is_array($sidebarTools)) {
                    $sidebarTools = [];
                }

                workspaceUpdateSidebarToolsConfiguration($pdo, $workspaceId, $sidebarTools);
                flash('success', 'Ferramentas do sidebar atualizadas.');
                redirectTo('workspace-settings');

            case 'workspace_add_sidebar_tool':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar as ferramentas do sidebar.');
                }

                $toolToAdd = normalizeWorkspaceSidebarToolKey((string) ($_POST['sidebar_tool'] ?? ''));
                if ($toolToAdd === '') {
                    throw new RuntimeException('Ferramenta inválida.');
                }

                $currentSidebarConfig = workspaceSidebarToolsConfig($workspaceId);
                $enabledOptionalTools = (array) ($currentSidebarConfig['enabled_optional'] ?? []);
                if (!in_array($toolToAdd, $enabledOptionalTools, true)) {
                    $enabledOptionalTools[] = $toolToAdd;
                }

                workspaceUpdateSidebarToolsConfiguration($pdo, $workspaceId, $enabledOptionalTools);
                flash('success', 'Ferramenta adicionada ao sidebar.');
                redirectTo('workspace-settings');

            case 'workspace_add_member':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não permite gerenciar usuários.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem adicionar usuários.');
                }

                $memberEmail = strtolower(trim((string) ($_POST['member_email'] ?? '')));
                if ($memberEmail === '' || !filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Informe um e-mail valido.');
                }

                $memberStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $memberStmt->execute([':email' => $memberEmail]);
                $memberId = (int) $memberStmt->fetchColumn();
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuário não encontrado. Cadastre a conta antes de adicionar.');
                }

                upsertWorkspaceMember($pdo, $workspaceId, $memberId, 'member');
                flash('success', 'Usuário adicionado ao workspace.');
                redirectTo('workspace-settings');

            case 'workspace_promote_member':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não permite gerenciar usuários.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar permissões.');
                }

                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuário inválido.');
                }
                if ($memberId === (int) $currentUser['id']) {
                    throw new RuntimeException('Sua conta já possui permissão de administrador.');
                }
                if (!userHasWorkspaceAccess($memberId, $workspaceId)) {
                    throw new RuntimeException('Usuário não pertence a este workspace.');
                }

                upsertWorkspaceMember($pdo, $workspaceId, $memberId, 'admin');
                flash('success', 'Permissão de administrador concedida.');
                redirectTo('workspace-settings');

            case 'workspace_demote_member':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não permite gerenciar usuários.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar permissões.');
                }

                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuário inválido.');
                }
                if ($memberId === (int) $currentUser['id']) {
                    throw new RuntimeException('Não e possível alterar a própria permissão.');
                }

                $targetRole = workspaceRoleForUser($memberId, $workspaceId);
                if ($targetRole !== 'admin') {
                    throw new RuntimeException('Este usuário não e administrador.');
                }

                updateWorkspaceMemberRole($pdo, $workspaceId, $memberId, 'member');
                flash('success', 'Permissão alterada para usuário.');
                redirectTo('workspace-settings');

            case 'workspace_remove_member':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não permite gerenciar usuários.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem remover usuários.');
                }

                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuário inválido.');
                }
                if ($memberId === (int) $currentUser['id']) {
                    throw new RuntimeException('Não e possível remover a própria conta deste workspace.');
                }

                removeWorkspaceMember($pdo, $workspaceId, $memberId);
                flash('success', 'Usuário removido do workspace.');
                redirectTo('workspace-settings');

            default:
                throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirectTo('workspace-settings');
    }
}

$currentUser = requireAuth();
$currentWorkspaceId = activeWorkspaceId($currentUser);
if ($currentWorkspaceId === null) {
    flash('error', 'Workspace ativo não encontrado.');
    redirectTo('');
}

$currentWorkspace = activeWorkspace($currentUser);
$workspaceTaskStatusConfig = taskStatusConfig($currentWorkspaceId, $currentWorkspace);
$canManageWorkspace = userCanManageWorkspace((int) $currentUser['id'], $currentWorkspaceId);
$isPersonalWorkspace = !empty($currentWorkspace['is_personal']);
$canManageWorkspaceMembers = $canManageWorkspace && !$isPersonalWorkspace;
$workspaceMembers = workspaceMembersList($currentWorkspaceId);
$flashes = getFlashes();
$stylesAssetVersion = assetVersion('assets/styles.css', '103');
$themeBexonAssetVersion = assetVersion('assets/theme-bexon.css');
$complianceAssetVersion = assetVersion('assets/compliance.js');
$pwaAssetVersion = assetVersion('assets/pwa.js');
$manifestAssetVersion = assetVersion('manifest.webmanifest');
$pwaIcon180AssetVersion = assetVersion('assets/pwa-icon-180.png');
$pwaIcon192AssetVersion = assetVersion('assets/pwa-icon-192.png');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="application-name" content="<?= e(APP_NAME) ?>">
    <meta name="theme-color" content="#040714">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= e(APP_NAME) ?>">
    <title><?= e(APP_NAME) ?> - Configurações do Workspace</title>
    <link rel="icon" type="image/png" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="icon" sizes="192x192" href="<?= e(appPath('assets/pwa-icon-192.png?v=' . $pwaIcon192AssetVersion)) ?>">
    <link rel="shortcut icon" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="apple-touch-icon" href="<?= e(appPath('assets/pwa-icon-180.png?v=' . $pwaIcon180AssetVersion)) ?>">
    <link rel="manifest" href="<?= e(appPath('manifest.webmanifest?v=' . $manifestAssetVersion)) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(appPath('assets/styles.css?v=' . $stylesAssetVersion)) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/theme-bexon.css?v=' . $themeBexonAssetVersion)) ?>">
    <script src="<?= e(appPath('assets/compliance.js?v=' . $complianceAssetVersion)) ?>" defer></script>
    <script src="<?= e(appPath('assets/pwa.js?v=' . $pwaAssetVersion)) ?>" defer></script>
</head>
<body class="is-dashboard is-workspace-settings" data-workspace-id="<?= e((string) $currentWorkspaceId) ?>">
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
                    <h2>Configurações do workspace</h2>
                    <p><?= $isPersonalWorkspace ? 'Workspace pessoal: personalize nome e status do seu fluxo.' : 'Gerencie nome, status e usuários do espaco.' ?></p>
                </div>

                <div class="workspace-settings-grid">
                    <section class="workspace-settings-card">
                        <h3>Dados do workspace</h3>
                        <?php if ($canManageWorkspace): ?>
                            <form method="post" class="workspace-settings-form workspace-profile-form" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="workspace_update_profile">
                                <div class="workspace-profile-photo-row">
                                    <?= renderWorkspaceAvatar($currentWorkspace, 'avatar workspace-profile-avatar') ?>
                                    <label class="workspace-profile-photo-field">
                                        <span><?= $isPersonalWorkspace ? 'Foto do perfil' : 'Foto do workspace' ?></span>
                                        <input
                                            type="file"
                                            name="avatar"
                                            accept="image/png,image/jpeg,image/webp,image/gif"
                                        >
                                    </label>
                                </div>
                                <label>
                                    <span>Nome do workspace</span>
                                    <input
                                        type="text"
                                        name="workspace_name"
                                        maxlength="80"
                                        value="<?= e((string) ($currentWorkspace['name'] ?? 'Workspace')) ?>"
                                        <?= $isPersonalWorkspace ? 'disabled' : '' ?>
                                        <?= $isPersonalWorkspace ? '' : 'required' ?>
                                    >
                                </label>
                                <button type="submit" class="btn btn-mini">Salvar workspace</button>
                            </form>
                        <?php else: ?>
                            <p class="workspace-settings-readonly"><?= e((string) ($currentWorkspace['name'] ?? 'Workspace')) ?></p>
                        <?php endif; ?>
                    </section>

                    <section class="workspace-settings-card">
                        <h3>Usuários do workspace</h3>
                        <?php if ($isPersonalWorkspace): ?>
                            <p class="workspace-settings-readonly">Este workspace ? pessoal e não permite adicionar outros usuários.</p>
                        <?php elseif ($canManageWorkspaceMembers): ?>
                            <form method="post" class="workspace-settings-form workspace-settings-member-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="workspace_add_member">
                                <label>
                                    <span>Adicionar usuário por e-mail</span>
                                    <input type="email" name="member_email" placeholder="usuário@empresa.com" required>
                                </label>
                                <button type="submit" class="btn btn-mini">Adicionar</button>
                            </form>
                        <?php endif; ?>

                        <ul class="workspace-settings-members">
                            <?php if (!$workspaceMembers): ?>
                                <li class="workspace-settings-member-empty">Nenhum usuário cadastrado.</li>
                            <?php else: ?>
                                <?php foreach ($workspaceMembers as $workspaceMember): ?>
                                    <?php
                                    $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
                                    $memberRoleLabel = workspaceRoles()[$memberRole] ?? 'Usuário';
                                    $workspaceMemberId = (int) ($workspaceMember['id'] ?? 0);
                                    ?>
                                    <li class="workspace-settings-member-item">
                                        <?= renderUserAvatar($workspaceMember, 'avatar small') ?>
                                        <div class="workspace-settings-member-meta">
                                            <strong><?= e((string) $workspaceMember['name']) ?></strong>
                                            <span class="workspace-member-role workspace-role-<?= e((string) $memberRole) ?>"><?= e((string) $memberRoleLabel) ?></span>
                                            <span><?= e((string) $workspaceMember['email']) ?></span>
                                        </div>
                                        <?php if ($canManageWorkspaceMembers && $workspaceMemberId !== (int) $currentUser['id']): ?>
                                            <div class="workspace-settings-member-actions">
                                                <?php if ($memberRole !== 'admin'): ?>
                                                    <form method="post" class="workspace-settings-member-remove">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                        <input type="hidden" name="action" value="workspace_promote_member">
                                                        <input type="hidden" name="member_id" value="<?= e((string) $workspaceMemberId) ?>">
                                                        <button type="submit" class="btn btn-mini btn-ghost">Tornar admin</button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" class="workspace-settings-member-remove">
                                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                        <input type="hidden" name="action" value="workspace_demote_member">
                                                        <input type="hidden" name="member_id" value="<?= e((string) $workspaceMemberId) ?>">
                                                        <button type="submit" class="btn btn-mini btn-ghost">Tornar usuário</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" class="workspace-settings-member-remove">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="workspace_remove_member">
                                                    <input type="hidden" name="member_id" value="<?= e((string) $workspaceMemberId) ?>">
                                                    <button type="submit" class="btn btn-mini btn-ghost">Remover</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </section>

                    <?php include __DIR__ . '/partials/workspace_statuses_card.php'; ?>
                </div>
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
