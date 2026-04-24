<?php
declare(strict_types=1);

function handleWorkspacePostAction(PDO $pdo, string $action): bool
{
    $allowedSwitchWorkspaceRedirects = [
        appPath(),
        dashboardPath('overview'),
        dashboardPath('tasks'),
        dashboardPath('vault'),
        dashboardPath('dues'),
        dashboardPath('inventory'),
        dashboardPath('accounting'),
        dashboardPath('users'),
        appPath('#overview'),
        appPath('#tasks'),
        appPath('#vault'),
        appPath('#dues'),
        appPath('#inventory'),
        appPath('#accounting'),
        appPath('#users'),
    ];

    switch ($action) {
            case 'switch_workspace':
                $authUser = requireAuth();
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                if ($workspaceId <= 0 || !userHasWorkspaceAccess((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Workspace inválido.');
                }

                setActiveWorkspaceId($workspaceId);
                $redirectPath = appPath(trim((string) ($_POST['redirect_to'] ?? '')));
                if (!in_array($redirectPath, $allowedSwitchWorkspaceRedirects, true)) {
                    $redirectPath = appPath('#tasks');
                }
                redirectTo($redirectPath);

            case 'create_workspace':
                $authUser = requireAuth();
                $workspaceName = normalizeWorkspaceName((string) ($_POST['workspace_name'] ?? ''));
                if ($workspaceName === '') {
                    throw new RuntimeException('Informe um nome para o workspace.');
                }

                $workspaceId = createWorkspace($pdo, $workspaceName, (int) $authUser['id']);
                if ($workspaceId <= 0) {
                    throw new RuntimeException('Não foi possível criar o workspace.');
                }

                setActiveWorkspaceId($workspaceId);
                flash('success', 'Workspace criado.');
                redirectTo('index.php#tasks');

            case 'workspace_update_profile':
            case 'workspace_update_name':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar o workspace.');
                }

                $canRenameWorkspace = !workspaceIsPersonal($workspaceId);
                $workspaceNameInput = $canRenameWorkspace ? (string) ($_POST['workspace_name'] ?? '') : '';
                updateWorkspaceProfile(
                    $pdo,
                    $workspaceId,
                    $workspaceNameInput,
                    $_FILES['avatar'] ?? [],
                    $canRenameWorkspace
                );

                $workspaceUpdatedMessage = 'Dados do workspace atualizados.';
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $workspaceUpdatedMessage,
                        'workspace_name' => $canRenameWorkspace
                            ? normalizeWorkspaceName($workspaceNameInput)
                            : (string) (workspaceById($workspaceId)['name'] ?? 'Workspace'),
                    ]);
                }

                flash('success', $workspaceUpdatedMessage);
                redirectTo('index.php#users');

            case 'workspace_update_task_statuses':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
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

                $workspaceStatusesMessage = 'Status do workspace atualizados.';
                flash('success', $workspaceStatusesMessage);
                redirectTo('index.php#users');

            case 'workspace_update_sidebar_tools':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar as ferramentas do sidebar.');
                }

                $sidebarTools = $_POST['sidebar_tools'] ?? [];
                if (!is_array($sidebarTools)) {
                    $sidebarTools = [];
                }

                $sidebarConfig = workspaceUpdateSidebarToolsConfiguration($pdo, $workspaceId, $sidebarTools);
                $workspaceSidebarMessage = 'Ferramentas do sidebar atualizadas.';
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $workspaceSidebarMessage,
                        'enabled_tools' => (array) ($sidebarConfig['enabled'] ?? []),
                    ]);
                }

                flash('success', $workspaceSidebarMessage);
                redirectTo(dashboardPath('users'));

            case 'workspace_add_sidebar_tool':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
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

                $redirectPath = appPath(trim((string) ($_POST['redirect_to'] ?? '')));
                if (!in_array($redirectPath, $allowedSwitchWorkspaceRedirects, true)) {
                    $redirectPath = dashboardPath('users');
                }

                flash('success', 'Ferramenta adicionada ao sidebar.');
                redirectTo($redirectPath);

            case 'workspace_add_member':
            case 'add_workspace_member':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não permite gerenciar usuários.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem adicionar usuários ao workspace.');
                }

                $memberEmail = strtolower(trim((string) ($_POST['member_email'] ?? '')));
                if ($memberEmail === '' || !filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Informe um e-mail valido.');
                }

                $memberStmt = $pdo->prepare('SELECT id, name FROM users WHERE email = :email LIMIT 1');
                $memberStmt->execute([':email' => $memberEmail]);
                $memberRow = $memberStmt->fetch();
                if (!$memberRow) {
                    throw new RuntimeException('Usuário não encontrado. Cadastre a conta antes de adicionar.');
                }

                $memberId = (int) ($memberRow['id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuário inválido.');
                }

                upsertWorkspaceMember($pdo, $workspaceId, $memberId, 'member');

                $workspaceAddMemberMessage = 'Usuário adicionado ao workspace.';
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $workspaceAddMemberMessage,
                        'member_id' => $memberId,
                        'member_email' => $memberEmail,
                        'member_role' => 'member',
                    ]);
                }

                flash('success', $workspaceAddMemberMessage);
                redirectTo('index.php#users');

            case 'workspace_promote_member':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não permite gerenciar usuários.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar permissões.');
                }

                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuário inválido.');
                }
                if ($memberId === (int) $authUser['id']) {
                    throw new RuntimeException('Sua conta já possui permissão de administrador.');
                }
                if (!userHasWorkspaceAccess($memberId, $workspaceId)) {
                    throw new RuntimeException('Usuário não pertence a este workspace.');
                }

                upsertWorkspaceMember($pdo, $workspaceId, $memberId, 'admin');

                $workspacePromoteMemberMessage = 'Permissão de administrador concedida.';
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $workspacePromoteMemberMessage,
                        'member_id' => $memberId,
                        'member_role' => 'admin',
                    ]);
                }

                flash('success', $workspacePromoteMemberMessage);
                redirectTo('index.php#users');

            case 'workspace_demote_member':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não permite gerenciar usuários.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar permissões.');
                }

                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuário inválido.');
                }
                if ($memberId === (int) $authUser['id']) {
                    throw new RuntimeException('Não e possível alterar a própria permissão.');
                }

                $targetRole = workspaceRoleForUser($memberId, $workspaceId);
                if ($targetRole !== 'admin') {
                    throw new RuntimeException('Este usuário não e administrador.');
                }

                updateWorkspaceMemberRole($pdo, $workspaceId, $memberId, 'member');

                $workspaceDemoteMemberMessage = 'Permissão alterada para usuário.';
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $workspaceDemoteMemberMessage,
                        'member_id' => $memberId,
                        'member_role' => 'member',
                    ]);
                }

                flash('success', $workspaceDemoteMemberMessage);
                redirectTo('index.php#users');

            case 'workspace_remove_member':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal não permite gerenciar usuários.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem remover usuários.');
                }

                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuário inválido.');
                }
                if ($memberId === (int) $authUser['id']) {
                    throw new RuntimeException('Não e possível remover a própria conta deste workspace.');
                }

                removeWorkspaceMember($pdo, $workspaceId, $memberId);

                $workspaceRemoveMemberMessage = 'Usuário removido do workspace.';
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $workspaceRemoveMemberMessage,
                        'member_id' => $memberId,
                    ]);
                }

                flash('success', $workspaceRemoveMemberMessage);
                redirectTo('index.php#users');

    }

    return in_array($action, [
        'switch_workspace',
        'create_workspace',
        'workspace_update_profile',
        'workspace_update_name',
        'workspace_update_task_statuses',
        'workspace_update_sidebar_tools',
        'workspace_add_sidebar_tool',
        'workspace_add_member',
        'add_workspace_member',
        'workspace_promote_member',
        'workspace_demote_member',
        'workspace_remove_member',
    ], true);
}
