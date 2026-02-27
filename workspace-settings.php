<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();
$currentUser = requireAuth();
$currentWorkspaceId = activeWorkspaceId($currentUser);
if ($currentWorkspaceId === null) {
    flash('error', 'Workspace ativo nao encontrado.');
    redirectTo('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        verifyCsrf();

        switch ($action) {
            case 'logout':
                logoutUser();
                flash('success', 'Sessao encerrada.');
                redirectTo('index.php');

            case 'switch_workspace':
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                if ($workspaceId <= 0 || !userHasWorkspaceAccess((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Workspace invalido.');
                }

                setActiveWorkspaceId($workspaceId);
                flash('success', 'Workspace atualizado.');
                redirectTo('workspace-settings.php');

            case 'create_workspace':
                $workspaceName = normalizeWorkspaceName((string) ($_POST['workspace_name'] ?? ''));
                if ($workspaceName === '') {
                    throw new RuntimeException('Informe um nome para o workspace.');
                }

                $workspaceId = createWorkspace($pdo, $workspaceName, (int) $currentUser['id']);
                if ($workspaceId <= 0) {
                    throw new RuntimeException('Nao foi possivel criar o workspace.');
                }

                setActiveWorkspaceId($workspaceId);
                flash('success', 'Workspace criado.');
                redirectTo('workspace-settings.php');

            case 'workspace_update_name':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar o workspace.');
                }

                updateWorkspaceName($pdo, $workspaceId, (string) ($_POST['workspace_name'] ?? ''));
                flash('success', 'Nome do workspace atualizado.');
                redirectTo('workspace-settings.php');

            case 'workspace_add_member':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem adicionar usuarios.');
                }

                $memberEmail = strtolower(trim((string) ($_POST['member_email'] ?? '')));
                if ($memberEmail === '' || !filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Informe um e-mail valido.');
                }

                $memberStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $memberStmt->execute([':email' => $memberEmail]);
                $memberId = (int) $memberStmt->fetchColumn();
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuario nao encontrado. Cadastre a conta antes de adicionar.');
                }

                upsertWorkspaceMember($pdo, $workspaceId, $memberId, 'member');
                flash('success', 'Usuario adicionado ao workspace.');
                redirectTo('workspace-settings.php');

            case 'workspace_remove_member':
                $workspaceId = activeWorkspaceId($currentUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (!userCanManageWorkspace((int) $currentUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem remover usuarios.');
                }

                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuario invalido.');
                }
                if ($memberId === (int) $currentUser['id']) {
                    throw new RuntimeException('Nao e possivel remover a propria conta deste workspace.');
                }

                removeWorkspaceMember($pdo, $workspaceId, $memberId);
                flash('success', 'Usuario removido do workspace.');
                redirectTo('workspace-settings.php');

            default:
                throw new RuntimeException('Acao invalida.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirectTo('workspace-settings.php');
    }
}

$currentUser = requireAuth();
$currentWorkspaceId = activeWorkspaceId($currentUser);
if ($currentWorkspaceId === null) {
    flash('error', 'Workspace ativo nao encontrado.');
    redirectTo('index.php');
}

$currentWorkspace = activeWorkspace($currentUser);
$canManageWorkspace = userCanManageWorkspace((int) $currentUser['id'], $currentWorkspaceId);
$workspaceMembers = workspaceMembersList($currentWorkspaceId);
$flashes = getFlashes();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> - Configuracoes do Workspace</title>
    <link rel="icon" type="image/svg+xml" href="assets/logo-mark.svg?v=1">
    <link rel="shortcut icon" href="assets/logo-mark.svg?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css?v=40">
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
                    <h2>Configuracoes do workspace</h2>
                    <p>Gerencie nome e usuarios do espaco.</p>
                </div>

                <div class="workspace-settings-grid">
                    <section class="workspace-settings-card">
                        <h3>Dados do workspace</h3>
                        <?php if ($canManageWorkspace): ?>
                            <form method="post" class="workspace-settings-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="workspace_update_name">
                                <label>
                                    <span>Nome do workspace</span>
                                    <input
                                        type="text"
                                        name="workspace_name"
                                        maxlength="80"
                                        value="<?= e((string) ($currentWorkspace['name'] ?? 'Workspace')) ?>"
                                        required
                                    >
                                </label>
                                <button type="submit" class="btn btn-mini">Salvar nome</button>
                            </form>
                        <?php else: ?>
                            <p class="workspace-settings-readonly"><?= e((string) ($currentWorkspace['name'] ?? 'Workspace')) ?></p>
                        <?php endif; ?>
                    </section>

                    <section class="workspace-settings-card">
                        <h3>Usuarios do workspace</h3>
                        <?php if ($canManageWorkspace): ?>
                            <form method="post" class="workspace-settings-form workspace-settings-member-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="workspace_add_member">
                                <label>
                                    <span>Adicionar usuario por e-mail</span>
                                    <input type="email" name="member_email" placeholder="usuario@empresa.com" required>
                                </label>
                                <button type="submit" class="btn btn-mini">Adicionar</button>
                            </form>
                        <?php endif; ?>

                        <ul class="workspace-settings-members">
                            <?php if (!$workspaceMembers): ?>
                                <li class="workspace-settings-member-empty">Nenhum usuario cadastrado.</li>
                            <?php else: ?>
                                <?php foreach ($workspaceMembers as $workspaceMember): ?>
                                    <?php
                                    $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
                                    $memberRoleLabel = workspaceRoles()[$memberRole] ?? 'Usuario';
                                    $workspaceMemberId = (int) ($workspaceMember['id'] ?? 0);
                                    ?>
                                    <li class="workspace-settings-member-item">
                                        <div class="avatar small" aria-hidden="true"><?= e(strtoupper(substr((string) $workspaceMember['name'], 0, 1))) ?></div>
                                        <div class="workspace-settings-member-meta">
                                            <strong><?= e((string) $workspaceMember['name']) ?></strong>
                                            <span class="workspace-member-role workspace-role-<?= e((string) $memberRole) ?>"><?= e((string) $memberRoleLabel) ?></span>
                                            <span><?= e((string) $workspaceMember['email']) ?></span>
                                        </div>
                                        <?php if ($canManageWorkspace && $workspaceMemberId !== (int) $currentUser['id']): ?>
                                            <form method="post" class="workspace-settings-member-remove">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="workspace_remove_member">
                                                <input type="hidden" name="member_id" value="<?= e((string) $workspaceMemberId) ?>">
                                                <button type="submit" class="btn btn-mini btn-ghost">Remover</button>
                                            </form>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </section>
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
