<?php
declare(strict_types=1);

function handleWorkspacePostAction(PDO $pdo, string $action): bool
{
    switch ($action) {
            case 'switch_workspace':
                $authUser = requireAuth();
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                if ($workspaceId <= 0 || !userHasWorkspaceAccess((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Workspace invalido.');
                }

                setActiveWorkspaceId($workspaceId);
                redirectTo('index.php#tasks');

            case 'create_workspace':
                $authUser = requireAuth();
                $workspaceName = normalizeWorkspaceName((string) ($_POST['workspace_name'] ?? ''));
                if ($workspaceName === '') {
                    throw new RuntimeException('Informe um nome para o workspace.');
                }

                $workspaceId = createWorkspace($pdo, $workspaceName, (int) $authUser['id']);
                if ($workspaceId <= 0) {
                    throw new RuntimeException('Nao foi possivel criar o workspace.');
                }

                setActiveWorkspaceId($workspaceId);
                flash('success', 'Workspace criado.');
                redirectTo('index.php#tasks');

            case 'workspace_update_name':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('O nome do workspace pessoal e definido automaticamente.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar o workspace.');
                }

                $workspaceNameInput = (string) ($_POST['workspace_name'] ?? '');
                updateWorkspaceName($pdo, $workspaceId, $workspaceNameInput);

                $workspaceUpdatedMessage = 'Nome do workspace atualizado.';
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $workspaceUpdatedMessage,
                        'workspace_name' => normalizeWorkspaceName($workspaceNameInput),
                    ]);
                }

                flash('success', $workspaceUpdatedMessage);
                redirectTo('index.php#users');

            case 'workspace_update_task_statuses':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar os status.');
                }

                $statusKeys = $_POST['status_keys'] ?? [];
                $statusLabels = $_POST['status_labels'] ?? [];
                if (!is_array($statusKeys) || !is_array($statusLabels)) {
                    throw new RuntimeException('Configuracao de status invalida.');
                }

                $statusDefinitions = [];
                $statusCount = max(count($statusKeys), count($statusLabels));
                for ($index = 0; $index < $statusCount; $index++) {
                    $statusDefinitions[] = [
                        'key' => (string) ($statusKeys[$index] ?? ''),
                        'label' => (string) ($statusLabels[$index] ?? ''),
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
                    (string) ($_POST['new_status_label'] ?? '')
                );

                $workspaceStatusesMessage = 'Status do workspace atualizados.';
                flash('success', $workspaceStatusesMessage);
                redirectTo('index.php#users');

            case 'workspace_add_member':
            case 'add_workspace_member':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal nao permite gerenciar usuarios.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem adicionar usuarios ao workspace.');
                }

                $memberEmail = strtolower(trim((string) ($_POST['member_email'] ?? '')));
                if ($memberEmail === '' || !filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Informe um e-mail valido.');
                }

                $memberStmt = $pdo->prepare('SELECT id, name FROM users WHERE email = :email LIMIT 1');
                $memberStmt->execute([':email' => $memberEmail]);
                $memberRow = $memberStmt->fetch();
                if (!$memberRow) {
                    throw new RuntimeException('Usuario nao encontrado. Cadastre a conta antes de adicionar.');
                }

                $memberId = (int) ($memberRow['id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuario invalido.');
                }

                upsertWorkspaceMember($pdo, $workspaceId, $memberId, 'member');

                $workspaceAddMemberMessage = 'Usuario adicionado ao workspace.';
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
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal nao permite gerenciar usuarios.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar permissoes.');
                }

                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuario invalido.');
                }
                if ($memberId === (int) $authUser['id']) {
                    throw new RuntimeException('Sua conta ja possui permissao de administrador.');
                }
                if (!userHasWorkspaceAccess($memberId, $workspaceId)) {
                    throw new RuntimeException('Usuario nao pertence a este workspace.');
                }

                upsertWorkspaceMember($pdo, $workspaceId, $memberId, 'admin');

                $workspacePromoteMemberMessage = 'Permissao de administrador concedida.';
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
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal nao permite gerenciar usuarios.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem alterar permissoes.');
                }

                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuario invalido.');
                }
                if ($memberId === (int) $authUser['id']) {
                    throw new RuntimeException('Nao e possivel alterar a propria permissao.');
                }

                $targetRole = workspaceRoleForUser($memberId, $workspaceId);
                if ($targetRole !== 'admin') {
                    throw new RuntimeException('Este usuario nao e administrador.');
                }

                updateWorkspaceMemberRole($pdo, $workspaceId, $memberId, 'member');

                $workspaceDemoteMemberMessage = 'Permissao alterada para usuario.';
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
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (workspaceIsPersonal($workspaceId)) {
                    throw new RuntimeException('Workspace pessoal nao permite gerenciar usuarios.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem remover usuarios.');
                }

                $memberId = (int) ($_POST['member_id'] ?? 0);
                if ($memberId <= 0) {
                    throw new RuntimeException('Usuario invalido.');
                }
                if ($memberId === (int) $authUser['id']) {
                    throw new RuntimeException('Nao e possivel remover a propria conta deste workspace.');
                }

                removeWorkspaceMember($pdo, $workspaceId, $memberId);

                $workspaceRemoveMemberMessage = 'Usuario removido do workspace.';
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
        'workspace_update_name',
        'workspace_update_task_statuses',
        'workspace_add_member',
        'add_workspace_member',
        'workspace_promote_member',
        'workspace_demote_member',
        'workspace_remove_member',
    ], true);
}
