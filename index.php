<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();
if (
    PHP_SAPI !== 'cli' &&
    extension_loaded('zlib') &&
    !headers_sent() &&
    !ini_get('zlib.output_compression')
) {
    $acceptEncoding = strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
    if (str_contains($acceptEncoding, 'gzip')) {
        header('Vary: Accept-Encoding');
        ob_start('ob_gzhandler');
    }
}

function requestExpectsJson(): bool
{
    $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($xhr === 'xmlhttprequest') {
        return true;
    }

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    return str_contains($accept, 'application/json');
}

function respondJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function dashboardSummaryPayloadForUser(int $userId, ?int $workspaceId = null): array
{
    $allTasks = allTasks($workspaceId);
    if ($workspaceId !== null && $workspaceId > 0) {
        $allTasks = array_values(array_filter(
            $allTasks,
            static function (array $task) use ($workspaceId, $userId): bool {
                $groupName = (string) ($task['group_name'] ?? 'Geral');
                return userCanViewTaskGroup($userId, $workspaceId, $groupName);
            }
        ));
    }
    $stats = dashboardStats($allTasks);
    $myOpenTasks = countMyAssignedTasks($allTasks, $userId);
    $completionRate = $stats['total'] > 0 ? (int) round(($stats['done'] / $stats['total']) * 100) : 0;

    return [
        'total' => (int) $stats['total'],
        'done' => (int) $stats['done'],
        'completion_rate' => $completionRate,
        'due_today' => (int) $stats['due_today'],
        'urgent' => (int) $stats['urgent'],
        'my_open' => (int) $myOpenTasks,
    ];
}

function tasksRedirectPathFromRequest(?array $get = null, ?array $post = null): string
{
    $get ??= $_GET;
    $post ??= $_POST;

    $groupRaw = null;
    if (isset($get['group']) && trim((string) $get['group']) !== '') {
        $groupRaw = (string) $get['group'];
    } elseif (isset($post['redirect_group']) && trim((string) $post['redirect_group']) !== '') {
        $groupRaw = (string) $post['redirect_group'];
    }

    $creatorRaw = $get['created_by'] ?? ($get['assignee'] ?? null);
    if (($creatorRaw === null || trim((string) $creatorRaw) === '') && isset($post['redirect_created_by'])) {
        $creatorRaw = $post['redirect_created_by'];
    }

    $params = [];
    if ($groupRaw !== null) {
        $params['group'] = normalizeTaskGroupName($groupRaw);
    }

    $creatorId = isset($creatorRaw) ? (int) $creatorRaw : 0;
    if ($creatorId > 0) {
        $params['created_by'] = (string) $creatorId;
    }

    $query = http_build_query($params);
    return $query !== '' ? "index.php?{$query}#tasks" : 'index.php#tasks';
}

function accountingRedirectPathFromRequest(?array $get = null, ?array $post = null): string
{
    $get ??= $_GET;
    $post ??= $_POST;

    $periodRaw = null;
    if (isset($get['accounting_period']) && trim((string) $get['accounting_period']) !== '') {
        $periodRaw = (string) $get['accounting_period'];
    } elseif (isset($post['period_key']) && trim((string) $post['period_key']) !== '') {
        $periodRaw = (string) $post['period_key'];
    }

    $periodKey = normalizeAccountingPeriodKey($periodRaw);
    $query = http_build_query([
        'accounting_period' => $periodKey,
    ]);

    return "index.php?{$query}#accounting";
}

function accountingInstallmentProgressFromRequest(?array $post = null): string
{
    $post ??= $_POST;
    $progressRaw = trim((string) ($post['installment_progress'] ?? ''));
    if ($progressRaw !== '') {
        return $progressRaw;
    }

    $installmentNumber = (int) ($post['installment_number'] ?? 0);
    $installmentTotal = (int) ($post['installment_total'] ?? 0);
    return accountingInstallmentProgressLabel($installmentNumber, $installmentTotal);
}

function workspaceRolesByUserId(array $workspaceMembers): array
{
    $rolesByUserId = [];
    foreach ($workspaceMembers as $workspaceMember) {
        $memberId = (int) ($workspaceMember['id'] ?? 0);
        if ($memberId <= 0) {
            continue;
        }
        $rolesByUserId[$memberId] = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
    }

    return $rolesByUserId;
}

function submittedGroupPermissionsByUserId(array $workspaceRolesByUserId, array $options = []): array
{
    $includeAdmins = !empty($options['include_admins']);
    $forceEnabledUserIds = $options['force_enabled_user_ids'] ?? [];
    if (!is_array($forceEnabledUserIds)) {
        $forceEnabledUserIds = [$forceEnabledUserIds];
    }
    $forceEnabledUserIds = array_values(array_filter(array_map('intval', $forceEnabledUserIds), static fn (int $id): bool => $id > 0));

    $memberIdsRaw = $_POST['member_ids'] ?? [];
    if (!is_array($memberIdsRaw)) {
        $memberIdsRaw = [$memberIdsRaw];
    }

    $permissionsRaw = $_POST['permissions'] ?? [];
    if (!is_array($permissionsRaw)) {
        $permissionsRaw = [];
    }

    $permissionsByUserId = [];
    foreach ($memberIdsRaw as $memberIdRaw) {
        $memberId = (int) $memberIdRaw;
        if ($memberId <= 0 || !isset($workspaceRolesByUserId[$memberId])) {
            continue;
        }

        $memberRole = normalizeWorkspaceRole((string) $workspaceRolesByUserId[$memberId]);
        if ($memberRole === 'admin' && !$includeAdmins) {
            $permissionsByUserId[$memberId] = ['can_view' => 1, 'can_access' => 1];
            continue;
        }

        $memberPermissions = $permissionsRaw[(string) $memberId] ?? $permissionsRaw[$memberId] ?? [];
        if (!is_array($memberPermissions)) {
            $memberPermissions = [];
        }

        if (array_key_exists('enabled', $memberPermissions)) {
            $enabled = 1;
            $permissionsByUserId[$memberId] = [
                'can_view' => $enabled,
                'can_access' => $enabled,
            ];
            continue;
        }

        $canView = array_key_exists('can_view', $memberPermissions) ? 1 : 0;
        $canAccess = array_key_exists('can_access', $memberPermissions) ? 1 : 0;
        if ($canView === 0) {
            $canAccess = 0;
        }

        $permissionsByUserId[$memberId] = [
            'can_view' => $canView,
            'can_access' => $canAccess,
        ];
    }

    foreach ($forceEnabledUserIds as $forceEnabledUserId) {
        if (!isset($workspaceRolesByUserId[$forceEnabledUserId])) {
            continue;
        }
        $permissionsByUserId[$forceEnabledUserId] = ['can_view' => 1, 'can_access' => 1];
    }

    return $permissionsByUserId;
}

require_once __DIR__ . '/handlers/post_auth.php';
require_once __DIR__ . '/handlers/post_workspace.php';

$forceAuthScreen = false;
$authInitialPanel = 'login';
$passwordResetRequest = null;
$requestedAuthPanel = trim((string) ($_GET['auth'] ?? ''));
if (in_array($requestedAuthPanel, ['login', 'register', 'forgot-password', 'reset-password'], true)) {
    $authInitialPanel = $requestedAuthPanel;
    $forceAuthScreen = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $getAction = trim((string) ($_GET['action'] ?? ''));

    if ($getAction === 'reset_password') {
        $selector = trim((string) ($_GET['selector'] ?? ''));
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($selector === '' || $token === '') {
            flash('error', 'Link de redefinicao invalido.');
            redirectTo('index.php?auth=forgot-password#forgot-password');
        }

        $passwordResetRequest = validPasswordResetRequest($selector, $token);
        if (!$passwordResetRequest) {
            flash('error', 'Este link de redefinicao e invalido ou expirou.');
            redirectTo('index.php?auth=forgot-password#forgot-password');
        }

        $passwordResetRequest['selector'] = $selector;
        $passwordResetRequest['token'] = $token;
        $authInitialPanel = 'reset-password';
        $forceAuthScreen = true;
    }

    if ($getAction === 'task_notifications_feed') {
        try {
            $authUser = currentUser();
            if (!$authUser) {
                respondJson([
                    'ok' => false,
                    'error' => 'Sessao expirada. Faca login novamente.',
                ], 401);
            }

            $workspaceId = activeWorkspaceId($authUser);
            if ($workspaceId === null) {
                throw new RuntimeException('Workspace ativo nao encontrado.');
            }

            $initialize = ((int) ($_GET['initialize'] ?? 0)) === 1;
            $sinceHistoryId = max(0, (int) ($_GET['since_id'] ?? 0));
            $limit = max(1, min(60, (int) ($_GET['limit'] ?? 24)));
            $latestHistoryId = latestTaskHistoryIdForWorkspace($workspaceId);

            if ($initialize) {
                respondJson([
                    'ok' => true,
                    'latest_history_id' => $latestHistoryId,
                    'notifications' => [],
                ]);
            }

            $notifications = taskNotificationsForUser(
                $workspaceId,
                (int) ($authUser['id'] ?? 0),
                $sinceHistoryId,
                $limit
            );

            respondJson([
                'ok' => true,
                'latest_history_id' => $latestHistoryId,
                'notifications' => $notifications,
            ]);
        } catch (Throwable $e) {
            respondJson([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $redirectPathOnError = 'index.php';

    try {
        verifyCsrf();

        switch ($action) {
            case 'register':
            case 'login':
            case 'logout':
            case 'request_password_reset':
            case 'perform_password_reset':
                if (handleAuthPostAction($pdo, $action, $redirectPathOnError)) {
                    return;
                }
                throw new RuntimeException('Acao de autenticacao invalida.');

            case 'switch_workspace':
            case 'create_workspace':
            case 'workspace_update_name':
            case 'workspace_add_member':
            case 'add_workspace_member':
            case 'workspace_promote_member':
            case 'workspace_demote_member':
            case 'workspace_remove_member':
                if (handleWorkspacePostAction($pdo, $action)) {
                    return;
                }
                throw new RuntimeException('Acao de workspace invalida.');

            case 'create_vault_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $groupName = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                if (findVaultGroupByName($groupName, $workspaceId) !== null) {
                    throw new RuntimeException('Este grupo de cofre ja existe.');
                }

                upsertVaultGroup($pdo, $groupName, (int) $authUser['id'], $workspaceId);
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $groupName,
                        'message' => 'Grupo do cofre criado.',
                    ]);
                }
                flash('success', 'Grupo do cofre criado.');
                redirectTo('index.php#vault');

            case 'rename_vault_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $oldGroupInput = normalizeVaultGroupName((string) ($_POST['old_group_name'] ?? ''));
                $newGroupName = normalizeVaultGroupName((string) ($_POST['new_group_name'] ?? ''));
                $existingOldGroupName = findVaultGroupByName($oldGroupInput, $workspaceId);
                if ($existingOldGroupName === null) {
                    throw new RuntimeException('Grupo do cofre nao encontrado.');
                }
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, $existingOldGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para gerenciar este grupo do cofre.');
                }

                $existingTargetGroupName = findVaultGroupByName($newGroupName, $workspaceId);
                if (
                    $existingTargetGroupName !== null &&
                    mb_strtolower($existingTargetGroupName) !== mb_strtolower($existingOldGroupName)
                ) {
                    throw new RuntimeException('Ja existe um grupo do cofre com este nome.');
                }

                if (mb_strtolower($existingOldGroupName) !== mb_strtolower($newGroupName)) {
                    $pdo->beginTransaction();
                    try {
                        $renameGroupStmt = $pdo->prepare(
                            'UPDATE workspace_vault_groups
                             SET name = :new_group_name
                             WHERE workspace_id = :workspace_id
                               AND name = :old_group_name'
                        );
                        $renameGroupStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);

                        $renameEntriesStmt = $pdo->prepare(
                            'UPDATE workspace_vault_entries
                             SET group_name = :new_group_name,
                                 updated_at = :updated_at
                             WHERE workspace_id = :workspace_id
                               AND group_name = :old_group_name'
                        );
                        $renameEntriesStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':updated_at' => nowIso(),
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);

                        renameVaultGroupPermissions(
                            $pdo,
                            $workspaceId,
                            $existingOldGroupName,
                            $newGroupName
                        );

                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'old_group_name' => $existingOldGroupName,
                        'group_name' => $newGroupName,
                        'message' => 'Grupo do cofre renomeado.',
                    ]);
                }

                flash('success', 'Grupo do cofre renomeado.');
                redirectTo('index.php#vault');

            case 'delete_vault_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $groupName = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findVaultGroupByName($groupName, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo do cofre nao encontrado.');
                }
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, $existingGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para remover este grupo do cofre.');
                }

                $pdo->beginTransaction();
                try {
                    $deleteEntriesStmt = $pdo->prepare(
                        'DELETE FROM workspace_vault_entries
                         WHERE workspace_id = :workspace_id
                           AND group_name = :group_name'
                    );
                    $deleteEntriesStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    $deletedEntriesCount = (int) $deleteEntriesStmt->rowCount();

                    $deleteGroupStmt = $pdo->prepare(
                        'DELETE FROM workspace_vault_groups
                         WHERE workspace_id = :workspace_id
                           AND name = :group_name'
                    );
                    $deleteGroupStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    if ($deleteGroupStmt->rowCount() <= 0) {
                        throw new RuntimeException('Nao foi possivel remover o grupo do cofre.');
                    }

                    deleteVaultGroupPermissions($pdo, $workspaceId, $existingGroupName);

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $existingGroupName,
                        'deleted_entries_count' => $deletedEntriesCount,
                        'message' => $deletedEntriesCount > 0
                            ? sprintf('Grupo do cofre removido. %d item(ns) excluido(s).', $deletedEntriesCount)
                            : 'Grupo do cofre removido.',
                    ]);
                }
                flash(
                    'success',
                    $deletedEntriesCount > 0
                        ? sprintf('Grupo do cofre removido. %d item(ns) excluido(s).', $deletedEntriesCount)
                        : 'Grupo do cofre removido.'
                );
                redirectTo('index.php#vault');

            case 'update_vault_group_permissions':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem configurar acessos por grupo.');
                }

                $groupInput = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findVaultGroupByName($groupInput, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo do cofre nao encontrado.');
                }

                $rolesByUserId = workspaceRolesByUserId(workspaceMembersList($workspaceId));
                $permissionsByUserId = submittedGroupPermissionsByUserId($rolesByUserId);
                $pdo->beginTransaction();
                try {
                    saveVaultGroupPermissions($pdo, $workspaceId, $existingGroupName, $permissionsByUserId, $rolesByUserId);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                flash('success', 'Permissoes do grupo do cofre atualizadas.');
                redirectTo('index.php#vault');

            case 'create_vault_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $groupNameInput = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findVaultGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, $groupName)) {
                    throw new RuntimeException('Voce nao possui acesso para adicionar itens neste grupo do cofre.');
                }
                $createdEntryId = createWorkspaceVaultEntry(
                    $pdo,
                    $workspaceId,
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['login_value'] ?? ''),
                    (string) ($_POST['password_value'] ?? ''),
                    $groupName,
                    (int) $authUser['id']
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $createdEntryId,
                        'message' => 'Item salvo no cofre.',
                    ]);
                }

                flash('success', 'Item salvo no cofre.');
                redirectTo('index.php#vault');

            case 'rename_vault_entry_label':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $label = (string) ($_POST['label'] ?? '');
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_vault_entries
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $entryGroupStmt->execute([
                    ':id' => $entryId,
                    ':workspace_id' => $workspaceId,
                ]);
                $entryGroupName = $entryGroupStmt->fetchColumn();
                if (!is_string($entryGroupName) || trim($entryGroupName) === '') {
                    throw new RuntimeException('Registro nao encontrado.');
                }
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, (string) $entryGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para editar este item do cofre.');
                }
                updateWorkspaceVaultEntryLabel($pdo, $workspaceId, $entryId, $label);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'label' => normalizeVaultEntryLabel($label),
                    ]);
                }

                flash('success', 'Nome do acesso atualizado.');
                redirectTo('index.php#vault');

            case 'update_vault_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $groupNameInput = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findVaultGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_vault_entries
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $entryGroupStmt->execute([
                    ':id' => $entryId,
                    ':workspace_id' => $workspaceId,
                ]);
                $entryGroupName = $entryGroupStmt->fetchColumn();
                if (!is_string($entryGroupName) || trim($entryGroupName) === '') {
                    throw new RuntimeException('Registro nao encontrado.');
                }
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, (string) $entryGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para editar este item do cofre.');
                }
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, $groupName)) {
                    throw new RuntimeException('Voce nao possui acesso ao grupo de destino.');
                }
                updateWorkspaceVaultEntry(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['login_value'] ?? ''),
                    (string) ($_POST['password_value'] ?? ''),
                    $groupName
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Item do cofre atualizado.',
                    ]);
                }

                flash('success', 'Item do cofre atualizado.');
                redirectTo('index.php#vault');

            case 'delete_vault_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_vault_entries
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $entryGroupStmt->execute([
                    ':id' => $entryId,
                    ':workspace_id' => $workspaceId,
                ]);
                $entryGroupName = $entryGroupStmt->fetchColumn();
                if (!is_string($entryGroupName) || trim($entryGroupName) === '') {
                    throw new RuntimeException('Registro nao encontrado.');
                }
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, (string) $entryGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para excluir este item do cofre.');
                }
                deleteWorkspaceVaultEntry($pdo, $workspaceId, $entryId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Item removido do cofre.',
                    ]);
                }

                flash('success', 'Item removido do cofre.');
                redirectTo('index.php#vault');

            case 'create_due_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $groupName = normalizeDueGroupName((string) ($_POST['group_name'] ?? ''));
                if (findDueGroupByName($groupName, $workspaceId) !== null) {
                    throw new RuntimeException('Este grupo de vencimentos ja existe.');
                }

                upsertDueGroup($pdo, $groupName, (int) $authUser['id'], $workspaceId);
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $groupName,
                        'message' => 'Grupo de vencimentos criado.',
                    ]);
                }
                flash('success', 'Grupo de vencimentos criado.');
                redirectTo('index.php#dues');

            case 'rename_due_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $oldGroupInput = normalizeDueGroupName((string) ($_POST['old_group_name'] ?? ''));
                $newGroupName = normalizeDueGroupName((string) ($_POST['new_group_name'] ?? ''));
                $existingOldGroupName = findDueGroupByName($oldGroupInput, $workspaceId);
                if ($existingOldGroupName === null) {
                    throw new RuntimeException('Grupo de vencimentos nao encontrado.');
                }
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, $existingOldGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para gerenciar este grupo de vencimentos.');
                }

                $existingTargetGroupName = findDueGroupByName($newGroupName, $workspaceId);
                if (
                    $existingTargetGroupName !== null &&
                    mb_strtolower($existingTargetGroupName) !== mb_strtolower($existingOldGroupName)
                ) {
                    throw new RuntimeException('Ja existe um grupo de vencimentos com este nome.');
                }

                if (mb_strtolower($existingOldGroupName) !== mb_strtolower($newGroupName)) {
                    $pdo->beginTransaction();
                    try {
                        $renameGroupStmt = $pdo->prepare(
                            'UPDATE workspace_due_groups
                             SET name = :new_group_name
                             WHERE workspace_id = :workspace_id
                               AND name = :old_group_name'
                        );
                        $renameGroupStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);

                        $renameEntriesStmt = $pdo->prepare(
                            'UPDATE workspace_due_entries
                             SET group_name = :new_group_name,
                                 updated_at = :updated_at
                             WHERE workspace_id = :workspace_id
                               AND group_name = :old_group_name'
                        );
                        $renameEntriesStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':updated_at' => nowIso(),
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);

                        renameDueGroupPermissions(
                            $pdo,
                            $workspaceId,
                            $existingOldGroupName,
                            $newGroupName
                        );

                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'old_group_name' => $existingOldGroupName,
                        'group_name' => $newGroupName,
                        'message' => 'Grupo de vencimentos renomeado.',
                    ]);
                }

                flash('success', 'Grupo de vencimentos renomeado.');
                redirectTo('index.php#dues');

            case 'delete_due_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $groupName = normalizeDueGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findDueGroupByName($groupName, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo de vencimentos nao encontrado.');
                }
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, $existingGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para remover este grupo de vencimentos.');
                }

                $pdo->beginTransaction();
                try {
                    $deleteEntriesStmt = $pdo->prepare(
                        'DELETE FROM workspace_due_entries
                         WHERE workspace_id = :workspace_id
                           AND group_name = :group_name'
                    );
                    $deleteEntriesStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    $deletedEntriesCount = (int) $deleteEntriesStmt->rowCount();

                    $deleteGroupStmt = $pdo->prepare(
                        'DELETE FROM workspace_due_groups
                         WHERE workspace_id = :workspace_id
                           AND name = :group_name'
                    );
                    $deleteGroupStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    if ($deleteGroupStmt->rowCount() <= 0) {
                        throw new RuntimeException('Nao foi possivel remover o grupo de vencimentos.');
                    }

                    deleteDueGroupPermissions($pdo, $workspaceId, $existingGroupName);

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $existingGroupName,
                        'deleted_entries_count' => $deletedEntriesCount,
                        'message' => $deletedEntriesCount > 0
                            ? sprintf('Grupo de vencimentos removido. %d item(ns) excluido(s).', $deletedEntriesCount)
                            : 'Grupo de vencimentos removido.',
                    ]);
                }
                flash(
                    'success',
                    $deletedEntriesCount > 0
                        ? sprintf('Grupo de vencimentos removido. %d item(ns) excluido(s).', $deletedEntriesCount)
                        : 'Grupo de vencimentos removido.'
                );
                redirectTo('index.php#dues');

            case 'update_due_group_permissions':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem configurar acessos por grupo.');
                }

                $groupInput = normalizeDueGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findDueGroupByName($groupInput, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo de vencimentos nao encontrado.');
                }

                $rolesByUserId = workspaceRolesByUserId(workspaceMembersList($workspaceId));
                $permissionsByUserId = submittedGroupPermissionsByUserId($rolesByUserId);
                $pdo->beginTransaction();
                try {
                    saveDueGroupPermissions($pdo, $workspaceId, $existingGroupName, $permissionsByUserId, $rolesByUserId);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                flash('success', 'Permissoes do grupo de vencimentos atualizadas.');
                redirectTo('index.php#dues');

            case 'create_due_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $groupNameInput = normalizeDueGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findDueGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, $groupName)) {
                    throw new RuntimeException('Voce nao possui acesso para adicionar itens neste grupo de vencimentos.');
                }

                $createdEntryId = createWorkspaceDueEntry(
                    $pdo,
                    $workspaceId,
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['due_date'] ?? ''),
                    $groupName,
                    '',
                    (string) ($_POST['amount_value'] ?? ''),
                    (int) $authUser['id'],
                    (string) ($_POST['recurrence_type'] ?? 'monthly'),
                    $_POST['monthly_day'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $createdEntryId,
                        'message' => 'Vencimento criado.',
                    ]);
                }

                flash('success', 'Vencimento criado.');
                redirectTo('index.php#dues');

            case 'update_due_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $groupNameInput = normalizeDueGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findDueGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_due_entries
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $entryGroupStmt->execute([
                    ':id' => $entryId,
                    ':workspace_id' => $workspaceId,
                ]);
                $entryGroupName = $entryGroupStmt->fetchColumn();
                if (!is_string($entryGroupName) || trim($entryGroupName) === '') {
                    throw new RuntimeException('Registro nao encontrado.');
                }
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, (string) $entryGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para editar este vencimento.');
                }
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, $groupName)) {
                    throw new RuntimeException('Voce nao possui acesso ao grupo de destino.');
                }

                updateWorkspaceDueEntry(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['due_date'] ?? ''),
                    $groupName,
                    '',
                    (string) ($_POST['amount_value'] ?? ''),
                    (string) ($_POST['recurrence_type'] ?? 'monthly'),
                    $_POST['monthly_day'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Vencimento atualizado.',
                    ]);
                }

                flash('success', 'Vencimento atualizado.');
                redirectTo('index.php#dues');

            case 'delete_due_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_due_entries
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $entryGroupStmt->execute([
                    ':id' => $entryId,
                    ':workspace_id' => $workspaceId,
                ]);
                $entryGroupName = $entryGroupStmt->fetchColumn();
                if (!is_string($entryGroupName) || trim($entryGroupName) === '') {
                    throw new RuntimeException('Registro nao encontrado.');
                }
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, (string) $entryGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para excluir este vencimento.');
                }

                deleteWorkspaceDueEntry($pdo, $workspaceId, $entryId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Vencimento removido.',
                    ]);
                }

                flash('success', 'Vencimento removido.');
                redirectTo('index.php#dues');

            case 'create_inventory_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $groupName = normalizeInventoryGroupName((string) ($_POST['group_name'] ?? ''));
                if (findInventoryGroupByName($groupName, $workspaceId) !== null) {
                    throw new RuntimeException('Este grupo de estoque ja existe.');
                }

                upsertInventoryGroup($pdo, $groupName, (int) $authUser['id'], $workspaceId);
                flash('success', 'Grupo de estoque criado.');
                redirectTo('index.php#inventory');

            case 'rename_inventory_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $oldGroupInput = normalizeInventoryGroupName((string) ($_POST['old_group_name'] ?? ''));
                $newGroupName = normalizeInventoryGroupName((string) ($_POST['new_group_name'] ?? ''));
                $existingOldGroupName = findInventoryGroupByName($oldGroupInput, $workspaceId);
                if ($existingOldGroupName === null) {
                    throw new RuntimeException('Grupo de estoque nao encontrado.');
                }

                $existingTargetGroupName = findInventoryGroupByName($newGroupName, $workspaceId);
                if (
                    $existingTargetGroupName !== null &&
                    mb_strtolower($existingTargetGroupName) !== mb_strtolower($existingOldGroupName)
                ) {
                    throw new RuntimeException('Ja existe um grupo de estoque com este nome.');
                }

                if (mb_strtolower($existingOldGroupName) !== mb_strtolower($newGroupName)) {
                    $pdo->beginTransaction();
                    try {
                        $renameGroupStmt = $pdo->prepare(
                            'UPDATE workspace_inventory_groups
                             SET name = :new_group_name
                             WHERE workspace_id = :workspace_id
                               AND name = :old_group_name'
                        );
                        $renameGroupStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);

                        $renameEntriesStmt = $pdo->prepare(
                            'UPDATE workspace_inventory_entries
                             SET group_name = :new_group_name,
                                 updated_at = :updated_at
                             WHERE workspace_id = :workspace_id
                               AND group_name = :old_group_name'
                        );
                        $renameEntriesStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':updated_at' => nowIso(),
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);

                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                }

                flash('success', 'Grupo de estoque renomeado.');
                redirectTo('index.php#inventory');

            case 'delete_inventory_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $groupName = normalizeInventoryGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findInventoryGroupByName($groupName, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo de estoque nao encontrado.');
                }

                $pdo->beginTransaction();
                try {
                    $deleteEntriesStmt = $pdo->prepare(
                        'DELETE FROM workspace_inventory_entries
                         WHERE workspace_id = :workspace_id
                           AND group_name = :group_name'
                    );
                    $deleteEntriesStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    $deletedEntriesCount = (int) $deleteEntriesStmt->rowCount();

                    $deleteGroupStmt = $pdo->prepare(
                        'DELETE FROM workspace_inventory_groups
                         WHERE workspace_id = :workspace_id
                           AND name = :group_name'
                    );
                    $deleteGroupStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    if ($deleteGroupStmt->rowCount() <= 0) {
                        throw new RuntimeException('Nao foi possivel remover o grupo de estoque.');
                    }

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                flash(
                    'success',
                    $deletedEntriesCount > 0
                        ? sprintf('Grupo de estoque removido. %d item(ns) excluido(s).', $deletedEntriesCount)
                        : 'Grupo de estoque removido.'
                );
                redirectTo('index.php#inventory');

            case 'create_inventory_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $groupNameInput = normalizeInventoryGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findInventoryGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;

                $createdEntryId = createWorkspaceInventoryEntry(
                    $pdo,
                    $workspaceId,
                    (string) ($_POST['label'] ?? ''),
                    $_POST['quantity_value'] ?? null,
                    (string) ($_POST['unit_label'] ?? 'un'),
                    $groupName,
                    $_POST['min_quantity_value'] ?? null,
                    (string) ($_POST['notes'] ?? ''),
                    (int) $authUser['id']
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $createdEntryId,
                        'message' => 'Item de estoque criado.',
                    ]);
                }

                flash('success', 'Item de estoque criado.');
                redirectTo('index.php#inventory');

            case 'update_inventory_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $groupNameInput = normalizeInventoryGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findInventoryGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_inventory_entries
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $entryGroupStmt->execute([
                    ':id' => $entryId,
                    ':workspace_id' => $workspaceId,
                ]);
                $entryGroupName = $entryGroupStmt->fetchColumn();
                if (!is_string($entryGroupName) || trim($entryGroupName) === '') {
                    throw new RuntimeException('Registro nao encontrado.');
                }

                updateWorkspaceInventoryEntry(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['label'] ?? ''),
                    $_POST['quantity_value'] ?? null,
                    (string) ($_POST['unit_label'] ?? 'un'),
                    $groupName,
                    $_POST['min_quantity_value'] ?? null,
                    (string) ($_POST['notes'] ?? '')
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Item de estoque atualizado.',
                    ]);
                }

                flash('success', 'Item de estoque atualizado.');
                redirectTo('index.php#inventory');

            case 'update_inventory_entry_quantity':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                updateWorkspaceInventoryEntryQuantity(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    $_POST['quantity_value'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Quantidade atualizada.',
                    ]);
                }

                flash('success', 'Quantidade atualizada.');
                redirectTo('index.php#inventory');

            case 'delete_inventory_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_inventory_entries
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $entryGroupStmt->execute([
                    ':id' => $entryId,
                    ':workspace_id' => $workspaceId,
                ]);
                $entryGroupName = $entryGroupStmt->fetchColumn();
                if (!is_string($entryGroupName) || trim($entryGroupName) === '') {
                    throw new RuntimeException('Registro nao encontrado.');
                }

                deleteWorkspaceInventoryEntry($pdo, $workspaceId, $entryId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Item de estoque removido.',
                    ]);
                }

                flash('success', 'Item de estoque removido.');
                redirectTo('index.php#inventory');

            case 'set_accounting_opening_balance':
                throw new RuntimeException('O saldo atual e calculado automaticamente pelo fechamento do mes anterior.');

            case 'create_accounting_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $periodKey = normalizeAccountingPeriodKey((string) ($_POST['period_key'] ?? ''));
                $entryType = normalizeAccountingEntryType((string) ($_POST['entry_type'] ?? 'expense'));
                $isSettled = array_key_exists('is_settled', $_POST) ? 1 : 0;
                $isInstallment = $entryType === 'expense' && ((string) ($_POST['is_installment'] ?? '0')) === '1' ? 1 : 0;

                createWorkspaceAccountingEntry(
                    $pdo,
                    $workspaceId,
                    $periodKey,
                    $entryType,
                    (string) ($_POST['label'] ?? ''),
                    $_POST['amount_value'] ?? null,
                    $isSettled,
                    (int) ($authUser['id'] ?? 0),
                    $isInstallment,
                    accountingInstallmentProgressFromRequest($_POST),
                    $_POST['total_amount_value'] ?? null,
                    $_POST['installment_number'] ?? null,
                    $_POST['installment_total'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $entryType === 'income' ? 'Entrada adicionada.' : 'Conta adicionada.',
                    ]);
                }

                flash('success', $entryType === 'income' ? 'Entrada adicionada.' : 'Conta adicionada.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'update_accounting_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro invalido.');
                }

                $entryWorkspaceStmt = $pdo->prepare(
                    'SELECT workspace_id, entry_type
                     FROM workspace_accounting_entries
                     WHERE id = :id
                     LIMIT 1'
                );
                $entryWorkspaceStmt->execute([':id' => $entryId]);
                $entryRow = $entryWorkspaceStmt->fetch(PDO::FETCH_ASSOC);
                $entryWorkspaceId = (int) ($entryRow['workspace_id'] ?? 0);
                if ($entryWorkspaceId <= 0 || $entryWorkspaceId !== $workspaceId) {
                    throw new RuntimeException('Registro nao encontrado.');
                }

                $isSettled = array_key_exists('is_settled', $_POST) ? 1 : 0;
                $entryType = normalizeAccountingEntryType((string) ($entryRow['entry_type'] ?? 'expense'));
                $isInstallment = $entryType === 'expense' && ((string) ($_POST['is_installment'] ?? '0')) === '1' ? 1 : 0;
                updateWorkspaceAccountingEntry(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['label'] ?? ''),
                    $_POST['amount_value'] ?? null,
                    $isSettled,
                    $isInstallment,
                    accountingInstallmentProgressFromRequest($_POST),
                    $_POST['total_amount_value'] ?? null,
                    $_POST['installment_number'] ?? null,
                    $_POST['installment_total'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Registro atualizado.',
                    ]);
                }

                flash('success', 'Registro atualizado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'delete_accounting_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro invalido.');
                }

                $entryWorkspaceStmt = $pdo->prepare(
                    'SELECT workspace_id
                     FROM workspace_accounting_entries
                     WHERE id = :id
                     LIMIT 1'
                );
                $entryWorkspaceStmt->execute([':id' => $entryId]);
                $entryWorkspaceId = (int) $entryWorkspaceStmt->fetchColumn();
                if ($entryWorkspaceId <= 0 || $entryWorkspaceId !== $workspaceId) {
                    throw new RuntimeException('Registro nao encontrado.');
                }

                deleteWorkspaceAccountingEntry($pdo, $workspaceId, $entryId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Registro removido.',
                    ]);
                }

                flash('success', 'Registro removido.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'create_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                $groupName = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));

                if (findTaskGroupByName($groupName, $workspaceId) !== null) {
                    throw new RuntimeException('Este grupo já existe.');
                }

                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem criar grupos.');
                }

                $rolesByUserId = workspaceRolesByUserId(workspaceMembersList($workspaceId));
                $permissionsByUserId = submittedGroupPermissionsByUserId($rolesByUserId, [
                    'include_admins' => true,
                    'force_enabled_user_ids' => [(int) $authUser['id']],
                ]);

                $pdo->beginTransaction();
                try {
                    upsertTaskGroup($pdo, $groupName, (int) $authUser['id'], $workspaceId);
                    saveTaskGroupPermissions(
                        $pdo,
                        $workspaceId,
                        $groupName,
                        $permissionsByUserId,
                        $rolesByUserId
                    );
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                flash('success', 'Grupo criado.');
                redirectTo('index.php#tasks');

            case 'rename_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                $oldGroupInput = normalizeTaskGroupName((string) ($_POST['old_group_name'] ?? ''));
                $newGroupName = normalizeTaskGroupName((string) ($_POST['new_group_name'] ?? ''));
                $existingOldGroupName = findTaskGroupByName($oldGroupInput, $workspaceId);

                if ($existingOldGroupName === null) {
                    throw new RuntimeException('Grupo nao encontrado.');
                }
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $existingOldGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para gerenciar este grupo.');
                }

                $existingTargetGroupName = findTaskGroupByName($newGroupName, $workspaceId);
                if (
                    $existingTargetGroupName !== null &&
                    mb_strtolower($existingTargetGroupName) !== mb_strtolower($existingOldGroupName)
                ) {
                    throw new RuntimeException('Ja existe um grupo com este nome.');
                }

                $taskCountStmt = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM tasks
                     WHERE workspace_id = :workspace_id
                       AND group_name = :group_name'
                );
                $taskCountStmt->execute([
                    ':workspace_id' => $workspaceId,
                    ':group_name' => $existingOldGroupName,
                ]);
                $affectedTaskCount = (int) $taskCountStmt->fetchColumn();
                $affectedTaskIds = [];
                $renameUpdatedAt = nowIso();

                if ($affectedTaskCount > 0 && $existingOldGroupName !== $newGroupName) {
                    $taskIdsStmt = $pdo->prepare(
                        'SELECT id
                         FROM tasks
                         WHERE workspace_id = :workspace_id
                           AND group_name = :group_name'
                    );
                    $taskIdsStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingOldGroupName,
                    ]);
                    $affectedTaskIds = array_map(
                        'intval',
                        array_column($taskIdsStmt->fetchAll(), 'id')
                    );
                }

                $pdo->beginTransaction();
                try {
                    if ($existingOldGroupName !== $newGroupName) {
                        $renameGroupStmt = $pdo->prepare(
                            'UPDATE task_groups
                             SET name = :new_group_name
                             WHERE workspace_id = :workspace_id
                               AND name = :old_group_name'
                        );
                        $renameGroupStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);
                        renameTaskGroupPermissions($pdo, $workspaceId, $existingOldGroupName, $newGroupName);
                    }

                    if ($affectedTaskCount > 0 && $existingOldGroupName !== $newGroupName) {
                        $renameTasksStmt = $pdo->prepare(
                            'UPDATE tasks
                             SET group_name = :new_group_name, updated_at = :updated_at
                             WHERE workspace_id = :workspace_id
                               AND group_name = :old_group_name'
                        );
                        $renameTasksStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':updated_at' => $renameUpdatedAt,
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);

                        foreach ($affectedTaskIds as $affectedTaskId) {
                            if ($affectedTaskId <= 0) {
                                continue;
                            }

                            logTaskHistory(
                                $pdo,
                                $affectedTaskId,
                                'group_changed',
                                ['old' => $existingOldGroupName, 'new' => $newGroupName],
                                (int) $authUser['id'],
                                $renameUpdatedAt
                            );
                        }
                    }

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'old_group_name' => $existingOldGroupName,
                        'group_name' => $newGroupName,
                        'affected_task_count' => $affectedTaskCount,
                    ]);
                }

                flash('success', 'Grupo renomeado.');
                redirectTo('index.php#tasks');

            case 'delete_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                $groupName = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findTaskGroupByName($groupName, $workspaceId);

                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo nao encontrado.');
                }
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $existingGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para remover este grupo.');
                }

                $taskIdsStmt = $pdo->prepare(
                    'SELECT id
                     FROM tasks
                     WHERE workspace_id = :workspace_id
                       AND LOWER(TRIM(COALESCE(group_name, \'\'))) = LOWER(TRIM(:group_name))'
                );
                $taskIdsStmt->execute([
                    ':workspace_id' => $workspaceId,
                    ':group_name' => $existingGroupName,
                ]);
                $taskIds = array_map('intval', array_column($taskIdsStmt->fetchAll(), 'id'));
                $taskCount = count($taskIds);

                $pdo->beginTransaction();
                try {
                    if ($taskCount > 0) {
                        $taskPlaceholders = [];
                        $taskParams = [];
                        foreach ($taskIds as $index => $taskIdValue) {
                            $paramName = ':task_id_' . $index;
                            $taskPlaceholders[] = $paramName;
                            $taskParams[$paramName] = $taskIdValue;
                        }

                        $deleteHistorySql = 'DELETE FROM task_history WHERE task_id IN (' . implode(', ', $taskPlaceholders) . ')';
                        $deleteHistoryStmt = $pdo->prepare($deleteHistorySql);
                        foreach ($taskParams as $paramName => $paramValue) {
                            $deleteHistoryStmt->bindValue($paramName, $paramValue, PDO::PARAM_INT);
                        }
                        $deleteHistoryStmt->execute();
                    }

                    $deleteTasksStmt = $pdo->prepare(
                        'DELETE FROM tasks
                         WHERE workspace_id = :workspace_id
                           AND LOWER(TRIM(COALESCE(group_name, \'\'))) = LOWER(TRIM(:group_name))'
                    );
                    $deleteTasksStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);

                    $deleteGroupStmt = $pdo->prepare(
                        'DELETE FROM task_groups
                         WHERE workspace_id = :workspace_id
                           AND LOWER(TRIM(COALESCE(name, \'\'))) = LOWER(TRIM(:name))'
                    );
                    $deleteGroupStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':name' => $existingGroupName,
                    ]);

                    if ($deleteGroupStmt->rowCount() <= 0) {
                        throw new RuntimeException('Nao foi possivel remover o grupo.');
                    }

                    deleteTaskGroupPermissions($pdo, $workspaceId, $existingGroupName);

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $existingGroupName,
                        'deleted_task_count' => $taskCount,
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                    ]);
                }

                flash(
                    'success',
                    $taskCount > 0
                        ? sprintf('Grupo removido. %d tarefa(s) excluida(s).', $taskCount)
                        : 'Grupo removido.'
                );
                redirectTo('index.php#tasks');

            case 'update_task_group_permissions':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem configurar acessos por grupo.');
                }

                $groupInput = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findTaskGroupByName($groupInput, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo nao encontrado.');
                }

                $rolesByUserId = workspaceRolesByUserId(workspaceMembersList($workspaceId));
                $permissionsByUserId = submittedGroupPermissionsByUserId($rolesByUserId, [
                    'include_admins' => true,
                    'force_enabled_user_ids' => [(int) $authUser['id']],
                ]);
                $pdo->beginTransaction();
                try {
                    saveTaskGroupPermissions($pdo, $workspaceId, $existingGroupName, $permissionsByUserId, $rolesByUserId);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                flash('success', 'Permissoes do grupo atualizadas.');
                redirectTo('index.php#tasks');

            case 'create_task':
            case 'update_task':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                $actorUserId = (int) $authUser['id'];
                $isAutosave = $action === 'update_task' && (string) ($_POST['autosave'] ?? '') === '1';
                $usersById = usersMapById($workspaceId);
                $taskId = (int) ($_POST['task_id'] ?? 0);
                $title = normalizeTaskTitle((string) ($_POST['title'] ?? ''));
                $titleTag = normalizeTaskTitleTag((string) ($_POST['title_tag'] ?? ''));
                $titleTagColor = normalizeTaskTitleTagColor((string) ($_POST['title_tag_color'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $referenceLinksPosted = array_key_exists('reference_links_json', $_POST);
                $referenceImagesPosted = array_key_exists('reference_images_json', $_POST);
                $subtasksPosted = array_key_exists('subtasks_json', $_POST);
                $subtasksDependencyPosted = array_key_exists('subtasks_dependency_enabled', $_POST);
                $subtasksDependencyEnabled = $subtasksDependencyPosted
                    ? normalizePermissionFlag($_POST['subtasks_dependency_enabled'] ?? 0)
                    : null;
                $referenceLinks = $referenceLinksPosted
                    ? decodeReferenceUrlList((string) ($_POST['reference_links_json'] ?? '[]'))
                    : null;
                $referenceImages = $referenceImagesPosted
                    ? decodeReferenceImageList((string) ($_POST['reference_images_json'] ?? '[]'))
                    : null;
                $subtasks = $subtasksPosted
                    ? decodeTaskSubtasks(
                        (string) ($_POST['subtasks_json'] ?? '[]'),
                        ($subtasksDependencyEnabled ?? 0) === 1
                    )
                    : null;
                $submittedHasActiveRevision = ((int) ($_POST['has_active_revision'] ?? 0)) === 1;
                $overdueFlagPosted = array_key_exists('overdue_flag', $_POST);
                $overdueFlag = $overdueFlagPosted
                    ? (((int) ($_POST['overdue_flag'] ?? 0)) === 1 ? 1 : 0)
                    : null;
                $overdueSinceDate = dueDateForStorage((string) ($_POST['overdue_since_date'] ?? ''));
                $status = normalizeTaskStatus((string) ($_POST['status'] ?? 'todo'));
                $priority = normalizeTaskPriority((string) ($_POST['priority'] ?? 'medium'));
                $dueDate = dueDateForStorage($_POST['due_date'] ?? null);
                if ($action === 'create_task' && $dueDate === null) {
                    $dueDate = (new DateTimeImmutable('today'))->format('Y-m-d');
                }
                $groupInputRaw = trim((string) ($_POST['group_name'] ?? ''));
                $groupName = $groupInputRaw === ''
                    ? defaultTaskGroupName($workspaceId)
                    : normalizeTaskGroupName($groupInputRaw);
                $existingGroupName = findTaskGroupByName($groupName, $workspaceId);
                if ($existingGroupName !== null) {
                    $groupName = $existingGroupName;
                }
                $rawAssigneeValues = $_POST['assigned_to'] ?? [];
                if (!is_array($rawAssigneeValues)) {
                    $rawAssigneeValues = [$rawAssigneeValues];
                }
                $submittedAssigneeIds = normalizeAssigneeIds($rawAssigneeValues);
                $assigneeIds = normalizeAssigneeIds($rawAssigneeValues, $usersById);
                $assignedTo = $assigneeIds[0] ?? null;
                $assigneeIdsJson = encodeAssigneeIds($assigneeIds);

                if ($title === '') {
                    throw new RuntimeException('O titulo da tarefa e obrigatorio.');
                }
                if (mb_strlen($title) > 140) {
                    throw new RuntimeException('O titulo deve ter no maximo 140 caracteres.');
                }
                if (count($submittedAssigneeIds) !== count($assigneeIds)) {
                    throw new RuntimeException('Um ou mais responsaveis selecionados sao invalidos.');
                }

                if ($action === 'create_task') {
                    if (!userCanAccessTaskGroup($actorUserId, $workspaceId, $groupName)) {
                        throw new RuntimeException('Voce nao possui acesso para criar tarefas neste grupo.');
                    }
                    upsertTaskGroup($pdo, $groupName, $actorUserId, $workspaceId);

                    $normalized = normalizeTaskOverdueState(
                        $status,
                        $priority,
                        $dueDate,
                        $overdueFlag ?? 0,
                        $overdueSinceDate
                    );
                    $status = $normalized['status'];
                    $priority = $normalized['priority'];
                    $dueDate = $normalized['due_date'];
                    $overdueFlag = $normalized['overdue_flag'];
                    $overdueSinceDate = $normalized['overdue_since_date'];
                    $referenceLinks ??= [];
                    $referenceImages ??= [];
                    $subtasksDependencyEnabled ??= 0;
                    $subtasks ??= [];
                    $status = applyTaskSubtasksCompletionStatus($status, $subtasks);
                    $stmt = $pdo->prepare(
                        'INSERT INTO tasks (workspace_id, title, title_tag, description, status, priority, due_date, overdue_flag, overdue_since_date, created_by, assigned_to, assignee_ids_json, reference_links_json, reference_images_json, subtasks_json, subtasks_dependency_enabled, group_name, created_at, updated_at)
                         VALUES (:workspace_id, :t, :tt, :d, :s, :p, :dd, :of, :osd, :cb, :at, :aj, :rl, :ri, :sj, :sde, :g, :c, :u)'
                    );
                    $now = nowIso();
                    $stmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':t' => $title,
                        ':tt' => $titleTag,
                        ':d' => $description,
                        ':s' => $status,
                        ':p' => $priority,
                        ':dd' => $dueDate,
                        ':of' => $overdueFlag,
                        ':osd' => $overdueSinceDate,
                        ':cb' => $actorUserId,
                        ':at' => $assignedTo,
                        ':aj' => $assigneeIdsJson,
                        ':rl' => encodeReferenceUrlList($referenceLinks),
                        ':ri' => encodeReferenceImageList($referenceImages),
                        ':sj' => encodeTaskSubtasks($subtasks, $subtasksDependencyEnabled === 1),
                        ':sde' => $subtasksDependencyEnabled,
                        ':g' => $groupName,
                        ':c' => $now,
                        ':u' => $now,
                    ]);
                    $createdTaskId = (int) $pdo->lastInsertId();
                    $taskTitleTagColors = taskTitleTagColorsByWorkspace($workspaceId, $pdo);
                    if ($titleTag !== '') {
                        $taskTitleTagColors = setTaskTitleTagColorForWorkspace(
                            $pdo,
                            $workspaceId,
                            $titleTag,
                            $titleTagColor
                        );
                    }
                    $titleTagColor = taskTitleTagColorForTag($titleTag, $taskTitleTagColors);
                    if ($createdTaskId > 0) {
                        logTaskHistory(
                            $pdo,
                            $createdTaskId,
                            'created',
                            [
                                'title' => $title,
                                'title_tag' => $titleTag,
                                'status' => $status,
                                'priority' => $priority,
                                'due_date' => $dueDate,
                            ],
                            $actorUserId,
                            $now
                        );

                        if ($overdueFlag === 1) {
                            logTaskHistory(
                                $pdo,
                                $createdTaskId,
                                'overdue_started',
                                [
                                    'previous_due_date' => $dueDate,
                                    'new_due_date' => $dueDate,
                                    'overdue_since_date' => $overdueSinceDate,
                                    'overdue_days' => taskOverdueDays($overdueSinceDate),
                                ],
                                $actorUserId,
                                $now
                            );
                        }
                    }
                    flash('success', 'Tarefa criada.');
                    redirectTo(tasksRedirectPathFromRequest());
                }

                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa invalida.');
                }
                $existingTaskStmt = $pdo->prepare(
                    'SELECT title, title_tag, description, status, priority, due_date, overdue_flag, overdue_since_date, assignee_ids_json, group_name, reference_links_json, reference_images_json, subtasks_json, subtasks_dependency_enabled
                     FROM tasks
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $existingTaskStmt->execute([
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);
                $existingTaskRow = $existingTaskStmt->fetch();
                if (!$existingTaskRow) {
                    throw new RuntimeException('Tarefa invalida.');
                }
                $taskTitleTagColors = taskTitleTagColorsByWorkspace($workspaceId, $pdo);
                $existingTaskGroupName = normalizeTaskGroupName((string) ($existingTaskRow['group_name'] ?? 'Geral'));
                if (!userCanAccessTaskGroup($actorUserId, $workspaceId, $existingTaskGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para editar tarefas deste grupo.');
                }
                if (!userCanAccessTaskGroup($actorUserId, $workspaceId, $groupName)) {
                    throw new RuntimeException('Voce nao possui acesso ao grupo de destino.');
                }
                upsertTaskGroup($pdo, $groupName, $actorUserId, $workspaceId);

                if ($referenceLinks === null) {
                    $referenceLinks = decodeReferenceUrlList($existingTaskRow['reference_links_json'] ?? null);
                }
                if ($referenceImages === null) {
                    $referenceImages = decodeReferenceImageList($existingTaskRow['reference_images_json'] ?? null);
                }
                if ($subtasksDependencyEnabled === null) {
                    $subtasksDependencyEnabled = normalizePermissionFlag(
                        $existingTaskRow['subtasks_dependency_enabled'] ?? 0
                    );
                }
                if ($subtasks === null) {
                    $subtasks = decodeTaskSubtasks(
                        $existingTaskRow['subtasks_json'] ?? null,
                        $subtasksDependencyEnabled === 1
                    );
                } else {
                    $subtasks = decodeTaskSubtasks($subtasks, $subtasksDependencyEnabled === 1);
                }
                if ($overdueFlag === null) {
                    $overdueFlag = ((int) ($existingTaskRow['overdue_flag'] ?? 0)) === 1 ? 1 : 0;
                }
                if ($overdueSinceDate === null) {
                    $overdueSinceDate = dueDateForStorage((string) ($existingTaskRow['overdue_since_date'] ?? ''));
                }

                $normalized = normalizeTaskOverdueState(
                    $status,
                    $priority,
                    $dueDate,
                    $overdueFlag ?? 0,
                    $overdueSinceDate
                );
                $status = $normalized['status'];
                $priority = $normalized['priority'];
                $dueDate = $normalized['due_date'];
                $overdueFlag = $normalized['overdue_flag'];
                $overdueSinceDate = $normalized['overdue_since_date'];
                $overdueDays = (int) ($normalized['overdue_days'] ?? 0);
                $status = applyTaskSubtasksCompletionStatus($status, $subtasks);

                $stmt = $pdo->prepare(
                    'UPDATE tasks
                     SET title = :t,
                         title_tag = :tt,
                         description = :d,
                         status = :s,
                         priority = :p,
                         due_date = :dd,
                         overdue_flag = :of,
                         overdue_since_date = :osd,
                         assigned_to = :at,
                         assignee_ids_json = :aj,
                         reference_links_json = :rl,
                         reference_images_json = :ri,
                         subtasks_json = :sj,
                         subtasks_dependency_enabled = :sde,
                         group_name = :g,
                         updated_at = :u
                     WHERE id = :id
                       AND workspace_id = :workspace_id'
                );
                $updatedAt = nowIso();
                $stmt->execute([
                    ':t' => $title,
                    ':tt' => $titleTag,
                    ':d' => $description,
                    ':s' => $status,
                    ':p' => $priority,
                    ':dd' => $dueDate,
                    ':of' => $overdueFlag,
                    ':osd' => $overdueSinceDate,
                    ':at' => $assignedTo,
                    ':aj' => $assigneeIdsJson,
                    ':rl' => encodeReferenceUrlList($referenceLinks ?? []),
                    ':ri' => encodeReferenceImageList($referenceImages ?? []),
                    ':sj' => encodeTaskSubtasks($subtasks ?? [], $subtasksDependencyEnabled === 1),
                    ':sde' => $subtasksDependencyEnabled,
                    ':g' => $groupName,
                    ':u' => $updatedAt,
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);

                $existingStatus = normalizeTaskStatus((string) ($existingTaskRow['status'] ?? 'todo'));
                $existingPriority = normalizeTaskPriority((string) ($existingTaskRow['priority'] ?? 'medium'));
                $existingTitle = normalizeTaskTitle((string) ($existingTaskRow['title'] ?? ''));
                $existingTitleTag = normalizeTaskTitleTag((string) ($existingTaskRow['title_tag'] ?? ''));
                $existingDescription = trim((string) ($existingTaskRow['description'] ?? ''));
                $existingDueDate = dueDateForStorage((string) ($existingTaskRow['due_date'] ?? ''));
                $existingGroup = normalizeTaskGroupName((string) ($existingTaskRow['group_name'] ?? 'Geral'));
                $existingOverdueFlag = ((int) ($existingTaskRow['overdue_flag'] ?? 0)) === 1 ? 1 : 0;
                $existingOverdueSinceDate = dueDateForStorage((string) ($existingTaskRow['overdue_since_date'] ?? ''));
                $existingAssigneeIds = decodeAssigneeIds($existingTaskRow['assignee_ids_json'] ?? null);
                $existingSubtasksDependencyEnabled = normalizePermissionFlag(
                    $existingTaskRow['subtasks_dependency_enabled'] ?? 0
                );
                $existingSubtasks = decodeTaskSubtasks(
                    $existingTaskRow['subtasks_json'] ?? null,
                    $existingSubtasksDependencyEnabled === 1
                );
                $statusOptions = taskStatuses();
                $priorityOptions = taskPriorities();
                if ($titleTag !== '') {
                    $taskTitleTagColors = setTaskTitleTagColorForWorkspace(
                        $pdo,
                        $workspaceId,
                        $titleTag,
                        $titleTagColor
                    );
                }
                $titleTagColor = taskTitleTagColorForTag($titleTag, $taskTitleTagColors);

                if ($existingTitle !== $title) {
                    logTaskHistory(
                        $pdo,
                        $taskId,
                        'title_changed',
                        ['old' => $existingTitle, 'new' => $title],
                        $actorUserId,
                        $updatedAt
                    );
                }
                if ($existingTitleTag !== $titleTag) {
                    logTaskHistory(
                        $pdo,
                        $taskId,
                        'title_tag_changed',
                        ['old' => $existingTitleTag, 'new' => $titleTag],
                        $actorUserId,
                        $updatedAt
                    );
                }
                if ($existingStatus !== $status) {
                    logTaskHistory(
                        $pdo,
                        $taskId,
                        'status_changed',
                        [
                            'old' => $existingStatus,
                            'new' => $status,
                            'old_label' => $statusOptions[$existingStatus] ?? $existingStatus,
                            'new_label' => $statusOptions[$status] ?? $status,
                        ],
                        $actorUserId,
                        $updatedAt
                    );
                }
                if ($existingPriority !== $priority) {
                    logTaskHistory(
                        $pdo,
                        $taskId,
                        'priority_changed',
                        [
                            'old' => $existingPriority,
                            'new' => $priority,
                            'old_label' => $priorityOptions[$existingPriority] ?? $existingPriority,
                            'new_label' => $priorityOptions[$priority] ?? $priority,
                        ],
                        $actorUserId,
                        $updatedAt
                    );
                }
                if ($existingDueDate !== $dueDate) {
                    logTaskHistory(
                        $pdo,
                        $taskId,
                        'due_date_changed',
                        ['old' => $existingDueDate, 'new' => $dueDate],
                        $actorUserId,
                        $updatedAt
                    );
                }
                if ($existingGroup !== $groupName) {
                    logTaskHistory(
                        $pdo,
                        $taskId,
                        'group_changed',
                        ['old' => $existingGroup, 'new' => $groupName],
                        $actorUserId,
                        $updatedAt
                    );
                }
                if ($existingAssigneeIds !== $assigneeIds) {
                    logTaskHistory(
                        $pdo,
                        $taskId,
                        'assignees_changed',
                        ['old' => $existingAssigneeIds, 'new' => $assigneeIds],
                        $actorUserId,
                        $updatedAt
                    );
                }
                if ($existingSubtasks !== $subtasks) {
                    $existingProgress = taskSubtasksProgress(
                        $existingSubtasks,
                        $existingSubtasksDependencyEnabled === 1
                    );
                    $nextProgress = taskSubtasksProgress(
                        $subtasks ?? [],
                        $subtasksDependencyEnabled === 1
                    );
                    logTaskHistory(
                        $pdo,
                        $taskId,
                        'subtasks_changed',
                        [
                            'old_total' => (int) ($existingProgress['total'] ?? 0),
                            'new_total' => (int) ($nextProgress['total'] ?? 0),
                            'old_completed' => (int) ($existingProgress['completed'] ?? 0),
                            'new_completed' => (int) ($nextProgress['completed'] ?? 0),
                        ],
                        $actorUserId,
                        $updatedAt
                    );
                }
                if ($existingOverdueFlag !== $overdueFlag) {
                    if ($overdueFlag === 1) {
                        logTaskHistory(
                            $pdo,
                            $taskId,
                            'overdue_started',
                            [
                                'previous_due_date' => $existingDueDate,
                                'new_due_date' => $dueDate,
                                'overdue_since_date' => $overdueSinceDate,
                                'overdue_days' => $overdueDays,
                            ],
                            $actorUserId,
                            $updatedAt
                        );
                    } else {
                        logTaskHistory(
                            $pdo,
                            $taskId,
                            'overdue_cleared',
                            [
                                'previous_overdue_since_date' => $existingOverdueSinceDate,
                                'previous_overdue_days' => taskOverdueDays($existingOverdueSinceDate),
                            ],
                            $actorUserId,
                            $updatedAt
                        );
                    }
                }

                $includeHistory = !empty($_POST['include_history']);
                $shouldResolveHistory = $includeHistory || $description !== $existingDescription;
                $taskHistory = [];
                if ($shouldResolveHistory) {
                    $taskHistory = taskHistoryList($taskId, 40);
                }
                $hasActiveRevision = $shouldResolveHistory
                    ? taskHasActiveRevisionRequest($description, $taskHistory)
                    : $submittedHasActiveRevision;
                if ($isAutosave && requestExpectsJson()) {
                    $taskPayload = [
                        'id' => $taskId,
                        'group_name' => $groupName,
                        'title_tag' => $titleTag,
                        'title_tag_color' => $titleTagColor,
                        'due_date' => $dueDate,
                        'status' => $status,
                        'priority' => $priority,
                        'overdue_flag' => $overdueFlag,
                        'overdue_since_date' => $overdueSinceDate,
                        'overdue_days' => $overdueDays,
                        'subtasks_json' => encodeTaskSubtasks(
                            $subtasks ?? [],
                            $subtasksDependencyEnabled === 1
                        ),
                        'subtasks_dependency_enabled' => $subtasksDependencyEnabled,
                        'reference_links_json' => encodeReferenceUrlList($referenceLinks ?? []),
                        'has_active_revision' => $hasActiveRevision ? 1 : 0,
                        'updated_at' => $updatedAt,
                        'updated_at_label' => (new DateTimeImmutable($updatedAt))->format('d/m H:i'),
                    ];
                    if ($referenceImagesPosted) {
                        $taskPayload['reference_images_json'] = encodeReferenceImageList($referenceImages ?? []);
                    }
                    if ($includeHistory) {
                        $taskPayload['history'] = $taskHistory;
                    }

                    respondJson([
                        'ok' => true,
                        'task' => $taskPayload,
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                    ]);
                }
                if (!$isAutosave) {
                    flash('success', 'Tarefa atualizada.');
                }
                redirectTo('index.php#task-' . $taskId);

            case 'set_task_title_tag_color':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $titleTag = normalizeTaskTitleTag((string) ($_POST['title_tag'] ?? ''));
                if ($titleTag === '') {
                    throw new RuntimeException('Tag invalida.');
                }

                $titleTagColor = normalizeTaskTitleTagColor((string) ($_POST['title_tag_color'] ?? ''));
                $taskTitleTagColors = setTaskTitleTagColorForWorkspace(
                    $pdo,
                    $workspaceId,
                    $titleTag,
                    $titleTagColor
                );
                $resolvedColor = taskTitleTagColorForTag($titleTag, $taskTitleTagColors);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'title_tag' => $titleTag,
                        'title_tag_color' => $resolvedColor,
                    ]);
                }

                flash('success', 'Cor da tag atualizada.');
                redirectTo('index.php#tasks');

            case 'request_task_revision':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa invalida.');
                }

                $newDescription = trim((string) ($_POST['revision_description'] ?? ''));
                if ($newDescription === '') {
                    throw new RuntimeException('A nova descricao e obrigatoria.');
                }
                if (mb_strlen($newDescription) > 8000) {
                    throw new RuntimeException('A nova descricao deve ter no maximo 8000 caracteres.');
                }

                $taskStmt = $pdo->prepare(
                    'SELECT status, group_name, description
                     FROM tasks
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $taskStmt->execute([
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);
                $taskRow = $taskStmt->fetch();
                if (!$taskRow) {
                    throw new RuntimeException('Tarefa invalida.');
                }

                $taskGroupName = normalizeTaskGroupName((string) ($taskRow['group_name'] ?? 'Geral'));
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $taskGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para atualizar esta tarefa.');
                }

                $taskStatus = normalizeTaskStatus((string) ($taskRow['status'] ?? 'todo'));
                if ($taskStatus !== 'review') {
                    throw new RuntimeException('A solicitacao de ajuste so pode ser feita em tarefas em revisao.');
                }

                $previousDescription = trim((string) ($taskRow['description'] ?? ''));
                if ($previousDescription === $newDescription) {
                    throw new RuntimeException('A nova descricao precisa ser diferente da descricao atual.');
                }

                $updatedAt = nowIso();
                $updateStmt = $pdo->prepare(
                    'UPDATE tasks
                     SET description = :description,
                         updated_at = :updated_at
                     WHERE id = :id
                       AND workspace_id = :workspace_id'
                );
                $updateStmt->execute([
                    ':description' => $newDescription,
                    ':updated_at' => $updatedAt,
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);

                logTaskHistory(
                    $pdo,
                    $taskId,
                    'revision_requested',
                    [
                        'previous_description' => $previousDescription,
                        'new_description' => $newDescription,
                    ],
                    (int) $authUser['id'],
                    $updatedAt
                );

                $taskHistory = taskHistoryList($taskId, 40);
                $hasActiveRevision = taskHasActiveRevisionRequest($newDescription, $taskHistory);
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'task' => [
                            'id' => $taskId,
                            'description' => $newDescription,
                            'status' => $taskStatus,
                            'history' => $taskHistory,
                            'has_active_revision' => $hasActiveRevision ? 1 : 0,
                            'updated_at' => $updatedAt,
                            'updated_at_label' => (new DateTimeImmutable($updatedAt))->format('d/m H:i'),
                        ],
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                    ]);
                }

                flash('success', 'Ajuste solicitado na tarefa.');
                redirectTo('index.php#task-' . $taskId);

            case 'remove_task_revision':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa invalida.');
                }

                $taskStmt = $pdo->prepare(
                    'SELECT status, group_name, description
                     FROM tasks
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $taskStmt->execute([
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);
                $taskRow = $taskStmt->fetch();
                if (!$taskRow) {
                    throw new RuntimeException('Tarefa invalida.');
                }

                $taskGroupName = normalizeTaskGroupName((string) ($taskRow['group_name'] ?? 'Geral'));
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $taskGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para atualizar esta tarefa.');
                }

                $taskStatus = normalizeTaskStatus((string) ($taskRow['status'] ?? 'todo'));

                $currentDescription = trim((string) ($taskRow['description'] ?? ''));
                if ($currentDescription === '') {
                    throw new RuntimeException('A tarefa nao possui uma solicitacao ativa para remover.');
                }

                $historyEntries = taskHistoryList($taskId, 250);
                $activeRevision = null;
                foreach ($historyEntries as $historyEntry) {
                    if ((string) ($historyEntry['event_type'] ?? '') !== 'revision_requested') {
                        continue;
                    }

                    $payload = is_array($historyEntry['payload'] ?? null)
                        ? $historyEntry['payload']
                        : [];
                    $previousDescription = trim((string) ($payload['previous_description'] ?? ''));
                    $newDescription = trim((string) ($payload['new_description'] ?? ''));
                    if ($previousDescription === '' || $newDescription === '') {
                        continue;
                    }
                    if ($newDescription !== $currentDescription) {
                        continue;
                    }

                    $activeRevision = [
                        'previous_description' => $previousDescription,
                        'new_description' => $newDescription,
                    ];
                    break;
                }

                if ($activeRevision === null) {
                    throw new RuntimeException('Nao ha solicitacao de ajuste ativa para remover.');
                }

                $restoredDescription = trim((string) ($activeRevision['previous_description'] ?? ''));
                if ($restoredDescription === '') {
                    throw new RuntimeException('Nao foi possivel restaurar a descricao anterior.');
                }

                $updatedAt = nowIso();
                $updateStmt = $pdo->prepare(
                    'UPDATE tasks
                     SET description = :description,
                         updated_at = :updated_at
                     WHERE id = :id
                       AND workspace_id = :workspace_id'
                );
                $updateStmt->execute([
                    ':description' => $restoredDescription,
                    ':updated_at' => $updatedAt,
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);

                logTaskHistory(
                    $pdo,
                    $taskId,
                    'revision_removed',
                    [
                        'removed_description' => $currentDescription,
                        'restored_description' => $restoredDescription,
                    ],
                    (int) $authUser['id'],
                    $updatedAt
                );

                $taskHistory = taskHistoryList($taskId, 40);
                $hasActiveRevision = taskHasActiveRevisionRequest($restoredDescription, $taskHistory);
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'task' => [
                            'id' => $taskId,
                            'description' => $restoredDescription,
                            'status' => $taskStatus,
                            'history' => $taskHistory,
                            'has_active_revision' => $hasActiveRevision ? 1 : 0,
                            'updated_at' => $updatedAt,
                            'updated_at_label' => (new DateTimeImmutable($updatedAt))->format('d/m H:i'),
                        ],
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                    ]);
                }

                flash('success', 'Solicitacao de ajuste removida.');
                redirectTo('index.php#task-' . $taskId);

            case 'move_task':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa invalida.');
                }

                $existingTaskStmt = $pdo->prepare(
                    'SELECT status, overdue_flag, overdue_since_date, group_name
                     FROM tasks
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $existingTaskStmt->execute([
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);
                $existingTaskRow = $existingTaskStmt->fetch();
                if (!$existingTaskRow) {
                    throw new RuntimeException('Tarefa invalida.');
                }
                $existingTaskGroupName = normalizeTaskGroupName((string) ($existingTaskRow['group_name'] ?? 'Geral'));
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $existingTaskGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para atualizar esta tarefa.');
                }

                $existingStatus = normalizeTaskStatus((string) ($existingTaskRow['status'] ?? 'todo'));
                $existingOverdueFlag = ((int) ($existingTaskRow['overdue_flag'] ?? 0)) === 1 ? 1 : 0;
                $existingOverdueSinceDate = dueDateForStorage((string) ($existingTaskRow['overdue_since_date'] ?? ''));
                $status = normalizeTaskStatus((string) ($_POST['status'] ?? 'todo'));
                $updatedAt = nowIso();
                $stmt = $pdo->prepare(
                    'UPDATE tasks
                     SET status = :s,
                         overdue_flag = CASE WHEN :s = :done THEN 0 ELSE overdue_flag END,
                         overdue_since_date = CASE WHEN :s = :done THEN NULL ELSE overdue_since_date END,
                         updated_at = :u
                     WHERE id = :id
                       AND workspace_id = :workspace_id'
                );
                $stmt->execute([
                    ':s' => $status,
                    ':done' => 'done',
                    ':u' => $updatedAt,
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);

                $statusOptions = taskStatuses();
                if ($existingStatus !== $status) {
                    logTaskHistory(
                        $pdo,
                        $taskId,
                        'status_changed',
                        [
                            'old' => $existingStatus,
                            'new' => $status,
                            'old_label' => $statusOptions[$existingStatus] ?? $existingStatus,
                            'new_label' => $statusOptions[$status] ?? $status,
                        ],
                        (int) $authUser['id'],
                        $updatedAt
                    );
                }

                if ($status === 'done' && $existingOverdueFlag === 1) {
                    logTaskHistory(
                        $pdo,
                        $taskId,
                        'overdue_cleared',
                        [
                            'previous_overdue_since_date' => $existingOverdueSinceDate,
                            'previous_overdue_days' => taskOverdueDays($existingOverdueSinceDate),
                        ],
                        (int) $authUser['id'],
                        $updatedAt
                    );
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'task_id' => $taskId,
                        'status' => $status,
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                    ]);
                }
                flash('success', 'Status atualizado.');
                redirectTo('index.php#task-' . $taskId);

            case 'load_task_detail':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa invalida.');
                }

                $taskStmt = $pdo->prepare(
                    'SELECT id, group_name, description, reference_links_json, reference_images_json, subtasks_json, subtasks_dependency_enabled
                     FROM tasks
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $taskStmt->execute([
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);
                $taskRow = $taskStmt->fetch();
                if (!$taskRow) {
                    throw new RuntimeException('Tarefa invalida.');
                }

                $taskGroupName = normalizeTaskGroupName((string) ($taskRow['group_name'] ?? 'Geral'));
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $taskGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso a esta tarefa.');
                }

                $taskHistory = taskHistoryList($taskId, 40);
                $taskDescription = trim((string) ($taskRow['description'] ?? ''));
                $hasActiveRevision = taskHasActiveRevisionRequest($taskDescription, $taskHistory);

                if (requestExpectsJson()) {
                    $subtasksDependencyEnabled = normalizePermissionFlag(
                        $taskRow['subtasks_dependency_enabled'] ?? 0
                    );
                    respondJson([
                        'ok' => true,
                        'task' => [
                            'id' => $taskId,
                            'reference_links_json' => encodeReferenceUrlList(
                                decodeReferenceUrlList($taskRow['reference_links_json'] ?? null)
                            ),
                            'reference_images_json' => encodeReferenceImageList(
                                decodeReferenceImageList($taskRow['reference_images_json'] ?? null)
                            ),
                            'subtasks_json' => encodeTaskSubtasks(
                                decodeTaskSubtasks(
                                    $taskRow['subtasks_json'] ?? null,
                                    $subtasksDependencyEnabled === 1
                                ),
                                $subtasksDependencyEnabled === 1
                            ),
                            'subtasks_dependency_enabled' => $subtasksDependencyEnabled,
                            'history' => $taskHistory,
                            'has_active_revision' => $hasActiveRevision ? 1 : 0,
                        ],
                    ]);
                }
                redirectTo('index.php#task-' . $taskId);

            case 'delete_task':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa invalida.');
                }
                $taskGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM tasks
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $taskGroupStmt->execute([
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);
                $taskGroupName = $taskGroupStmt->fetchColumn();
                if (!is_string($taskGroupName) || trim($taskGroupName) === '') {
                    throw new RuntimeException('Tarefa invalida.');
                }
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $taskGroupName)) {
                    throw new RuntimeException('Voce nao possui acesso para remover esta tarefa.');
                }
                $stmt = $pdo->prepare(
                    'DELETE FROM tasks
                     WHERE id = :id
                       AND workspace_id = :workspace_id'
                );
                $stmt->execute([
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'task_id' => $taskId,
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                    ]);
                }
                flash('success', 'Tarefa removida.');
                redirectTo('index.php#tasks');

            default:
                throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $e) {
        if (requestExpectsJson()) {
            respondJson([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
        flash('error', $e->getMessage());
        redirectTo($redirectPathOnError);
    }
}

$currentUser = currentUser();
$renderAuthScreen = !$currentUser || $forceAuthScreen;
$currentWorkspaceId = $currentUser ? activeWorkspaceId($currentUser) : null;
$currentWorkspace = ($currentUser && $currentWorkspaceId !== null) ? activeWorkspace($currentUser) : null;
$userWorkspaces = $currentUser ? workspacesForUser((int) $currentUser['id']) : [];
$flashes = getFlashes();
$statusOptions = taskStatuses();
$priorityOptions = taskPriorities();
$users = ($currentUser && $currentWorkspaceId !== null) ? usersList($currentWorkspaceId) : [];
$workspaceMembers = ($currentUser && $currentWorkspaceId !== null) ? workspaceMembersList($currentWorkspaceId) : [];
$canManageWorkspace = ($currentUser && $currentWorkspaceId !== null)
    ? userCanManageWorkspace((int) $currentUser['id'], $currentWorkspaceId)
    : false;
$isPersonalWorkspace = !empty($currentWorkspace['is_personal']);
$showUsersDashboardTab = !$isPersonalWorkspace;
$taskGroupsAll = ($currentUser && $currentWorkspaceId !== null) ? taskGroupsList($currentWorkspaceId) : ['Geral'];
$vaultGroupsAll = ($currentUser && $currentWorkspaceId !== null) ? vaultGroupsList($currentWorkspaceId) : ['Geral'];
$dueGroupsAll = ($currentUser && $currentWorkspaceId !== null) ? dueGroupsList($currentWorkspaceId) : ['Geral'];
$inventoryGroupsAll = ($currentUser && $currentWorkspaceId !== null) ? inventoryGroupsList($currentWorkspaceId) : ['Geral'];

$taskGroupPermissions = [];
$taskGroupPermissionsByUserMap = [];
$taskGroups = [];
$taskGroupsWithAccess = [];

$vaultGroupPermissions = [];
$vaultGroupPermissionsByUserMap = [];
$vaultGroups = [];
$vaultGroupsWithAccess = [];

$dueGroupPermissions = [];
$dueGroupPermissionsByUserMap = [];
$dueGroups = [];
$dueGroupsWithAccess = [];

$inventoryGroups = [];
$inventoryGroupsWithAccess = [];

if ($currentUser && $currentWorkspaceId !== null) {
    $currentUserId = (int) $currentUser['id'];

    foreach ($taskGroupsAll as $taskGroupName) {
        $taskGroupName = normalizeTaskGroupName((string) $taskGroupName);
        $permission = taskGroupPermissionForUser($currentWorkspaceId, $taskGroupName, $currentUserId);
        $taskGroupPermissions[$taskGroupName] = $permission;

        if (!empty($permission['can_view'])) {
            $taskGroups[] = $taskGroupName;
        }
        if (!empty($permission['can_access'])) {
            $taskGroupsWithAccess[] = $taskGroupName;
        }

        if ($canManageWorkspace) {
            $taskGroupPermissionsByUserMap[$taskGroupName] = taskGroupPermissionsByUser($currentWorkspaceId, $taskGroupName);
        }
    }

    foreach ($vaultGroupsAll as $vaultGroupName) {
        $vaultGroupName = normalizeVaultGroupName((string) $vaultGroupName);
        $permission = vaultGroupPermissionForUser($currentWorkspaceId, $vaultGroupName, $currentUserId);
        $vaultGroupPermissions[$vaultGroupName] = $permission;

        if (!empty($permission['can_view'])) {
            $vaultGroups[] = $vaultGroupName;
        }
        if (!empty($permission['can_access'])) {
            $vaultGroupsWithAccess[] = $vaultGroupName;
        }

        if ($canManageWorkspace) {
            $vaultGroupPermissionsByUserMap[$vaultGroupName] = vaultGroupPermissionsByUser($currentWorkspaceId, $vaultGroupName);
        }
    }

    foreach ($dueGroupsAll as $dueGroupName) {
        $dueGroupName = normalizeDueGroupName((string) $dueGroupName);
        $permission = dueGroupPermissionForUser($currentWorkspaceId, $dueGroupName, $currentUserId);
        $dueGroupPermissions[$dueGroupName] = $permission;

        if (!empty($permission['can_view'])) {
            $dueGroups[] = $dueGroupName;
        }
        if (!empty($permission['can_access'])) {
            $dueGroupsWithAccess[] = $dueGroupName;
        }

        if ($canManageWorkspace) {
            $dueGroupPermissionsByUserMap[$dueGroupName] = dueGroupPermissionsByUser($currentWorkspaceId, $dueGroupName);
        }
    }
} else {
    $taskGroups = $taskGroupsAll;
    $taskGroupsWithAccess = $taskGroupsAll;
    $vaultGroups = $vaultGroupsAll;
    $vaultGroupsWithAccess = $vaultGroupsAll;
    $dueGroups = $dueGroupsAll;
    $dueGroupsWithAccess = $dueGroupsAll;
}

$inventoryGroups = $inventoryGroupsAll;
$inventoryGroupsWithAccess = $inventoryGroupsAll;

$vaultVisibleKeys = [];
foreach ($vaultGroups as $vaultGroupName) {
    $vaultVisibleKeys[mb_strtolower(normalizeVaultGroupName($vaultGroupName))] = true;
}
$vaultEntries = ($currentUser && $currentWorkspaceId !== null) ? workspaceVaultEntriesList($currentWorkspaceId) : [];
$vaultEntries = array_values(array_filter(
    $vaultEntries,
    static function (array $entry) use ($vaultVisibleKeys): bool {
        $groupKey = mb_strtolower(normalizeVaultGroupName((string) ($entry['group_name'] ?? 'Geral')));
        return isset($vaultVisibleKeys[$groupKey]);
    }
));
$vaultEntriesByGroup = $currentUser ? vaultEntriesByGroup($vaultEntries, $vaultGroups) : [];

$dueVisibleKeys = [];
foreach ($dueGroups as $dueGroupName) {
    $dueVisibleKeys[mb_strtolower(normalizeDueGroupName($dueGroupName))] = true;
}
$dueEntries = ($currentUser && $currentWorkspaceId !== null) ? workspaceDueEntriesList($currentWorkspaceId) : [];
$dueEntries = array_values(array_filter(
    $dueEntries,
    static function (array $entry) use ($dueVisibleKeys): bool {
        $groupKey = mb_strtolower(normalizeDueGroupName((string) ($entry['group_name'] ?? 'Geral')));
        return isset($dueVisibleKeys[$groupKey]);
    }
));
$dueEntriesByGroup = $currentUser ? dueEntriesByGroup($dueEntries, $dueGroups) : [];
$inventoryEntries = ($currentUser && $currentWorkspaceId !== null) ? workspaceInventoryEntriesList($currentWorkspaceId) : [];
$inventoryEntriesByGroup = $currentUser ? inventoryEntriesByGroup($inventoryEntries, $inventoryGroups) : [];
$accountingPeriod = normalizeAccountingPeriodKey((string) ($_GET['accounting_period'] ?? ''));
$accountingPeriodLabel = accountingMonthLabel($accountingPeriod);
$accountingPeriodDate = DateTimeImmutable::createFromFormat('!Y-m', $accountingPeriod) ?: new DateTimeImmutable('first day of this month');
$accountingPreviousPeriod = $accountingPeriodDate->modify('-1 month')->format('Y-m');
$accountingNextPeriod = $accountingPeriodDate->modify('+1 month')->format('Y-m');
$accountingPreviousPeriodPath = accountingRedirectPathFromRequest(['accounting_period' => $accountingPreviousPeriod], []);
$accountingNextPeriodPath = accountingRedirectPathFromRequest(['accounting_period' => $accountingNextPeriod], []);
$accountingEntries = ($currentUser && $currentWorkspaceId !== null)
    ? workspaceAccountingEntriesList($currentWorkspaceId, $accountingPeriod)
    : [];
$accountingEntriesByType = workspaceAccountingEntriesByType($accountingEntries);
$accountingExpenseEntries = $accountingEntriesByType['expense'] ?? [];
$accountingIncomeEntries = $accountingEntriesByType['income'] ?? [];
$accountingOpeningBalanceCents = ($currentUser && $currentWorkspaceId !== null)
    ? workspaceAccountingOpeningBalanceCents($currentWorkspaceId, $accountingPeriod)
    : 0;
$accountingSummary = accountingSummary($accountingEntries, $accountingOpeningBalanceCents);
$stylesAssetVersion = is_file(__DIR__ . '/assets/styles.css')
    ? (string) filemtime(__DIR__ . '/assets/styles.css')
    : '1';
$appAssetVersion = is_file(__DIR__ . '/assets/app.js')
    ? (string) filemtime(__DIR__ . '/assets/app.js')
    : '1';
$groupFilter = isset($_GET['group']) && trim((string) $_GET['group']) !== ''
    ? normalizeTaskGroupName((string) $_GET['group'])
    : null;
if ($groupFilter !== null && !in_array($groupFilter, $taskGroups, true)) {
    $groupFilter = null;
}
$creatorFilterRaw = $_GET['created_by'] ?? ($_GET['assignee'] ?? null);
$creatorFilterId = isset($creatorFilterRaw) ? (int) $creatorFilterRaw : null;
$creatorFilterId = $creatorFilterId && $creatorFilterId > 0 ? $creatorFilterId : null;

$taskVisibleKeys = [];
foreach ($taskGroups as $taskGroupName) {
    $taskVisibleKeys[mb_strtolower(normalizeTaskGroupName($taskGroupName))] = true;
}
$allTasks = ($currentUser && $currentWorkspaceId !== null) ? allTasks($currentWorkspaceId) : [];
$allTasks = array_values(array_filter(
    $allTasks,
    static function (array $task) use ($taskVisibleKeys): bool {
        $groupKey = mb_strtolower(normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral')));
        return isset($taskVisibleKeys[$groupKey]);
    }
));
$tasks = $currentUser ? filterTasks($allTasks, $groupFilter, $creatorFilterId) : [];
$showEmptyGroups = $currentUser && $groupFilter === null && $creatorFilterId === null;
$groupingSource = null;
if ($showEmptyGroups) {
    $groupingSource = $taskGroups;
} elseif ($groupFilter !== null) {
    $groupingSource = [$groupFilter];
}
$tasksGroupedByGroup = $currentUser ? tasksByGroup($tasks, $groupingSource) : [];
$stats = $currentUser ? dashboardStats($allTasks) : ['total' => 0, 'done' => 0, 'due_today' => 0, 'urgent' => 0];
$myOpenTasks = $currentUser ? countMyAssignedTasks($allTasks, (int) $currentUser['id']) : 0;
$completionRate = $stats['total'] > 0 ? (int) round(($stats['done'] / $stats['total']) * 100) : 0;

$globalDashboardOverview = [
    'workspace_count' => 0,
    'due_window_days' => 7,
    'accounting_period_label' => accountingMonthLabel(normalizeAccountingPeriodKey((new DateTimeImmutable('today'))->format('Y-m'))),
    'tasks_today_total' => 0,
    'urgent_tasks_today_total' => 0,
    'priority_tasks_today_total' => 0,
    'due_soon_total' => 0,
    'due_today_total' => 0,
    'due_tomorrow_total' => 0,
    'low_stock_total' => 0,
    'critical_workspace_total' => 0,
    'attention_workspace_total' => 0,
    'executive_focus_total' => 0,
    'executive_status_tone' => 'stable',
    'executive_status_label' => 'Operacao estavel',
    'executive_status_note' => 'Nenhum ponto critico no curto prazo.',
    'balance_total_cents' => 0,
    'balance_month_movement_cents' => 0,
    'balance_total_display' => dueAmountLabelFromSignedCents(0),
    'balance_month_movement_display' => dueAmountLabelFromSignedCents(0),
    'tasks_today' => [],
    'due_soon' => [],
    'low_stock' => [],
    'workspace_summaries' => [],
];

if ($currentUser) {
    $overviewUserId = (int) ($currentUser['id'] ?? 0);
    $overviewToday = new DateTimeImmutable('today');
    $overviewTodayIso = $overviewToday->format('Y-m-d');
    $overviewDueWindowDays = (int) ($globalDashboardOverview['due_window_days'] ?? 7);
    $overviewDueLimitIso = $overviewToday->modify('+' . $overviewDueWindowDays . ' days')->format('Y-m-d');
    $overviewAccountingPeriod = normalizeAccountingPeriodKey($overviewToday->format('Y-m'));
    $globalDashboardOverview['accounting_period_label'] = accountingMonthLabel($overviewAccountingPeriod);
    $globalDashboardOverview['workspace_count'] = count($userWorkspaces);

    foreach ($userWorkspaces as $workspaceOption) {
        $workspaceOptionId = (int) ($workspaceOption['id'] ?? 0);
        if ($workspaceOptionId <= 0) {
            continue;
        }

        $workspaceOptionName = (string) ($workspaceOption['name'] ?? 'Workspace');
        $workspaceOptionRole = normalizeWorkspaceRole((string) ($workspaceOption['member_role'] ?? 'member'));
        $workspaceOptionRoleLabel = workspaceRoles()[$workspaceOptionRole] ?? 'Usuario';

        $workspaceTasks = allTasks($workspaceOptionId);
        $workspaceTasks = array_values(array_filter(
            $workspaceTasks,
            static function (array $task) use ($overviewUserId, $workspaceOptionId): bool {
                $groupName = normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral'));
                return userCanViewTaskGroup($overviewUserId, $workspaceOptionId, $groupName);
            }
        ));

        $workspaceTasksToday = [];
        $workspaceUrgentTasksTodayCount = 0;
        $workspacePriorityTasksTodayCount = 0;
        foreach ($workspaceTasks as $workspaceTask) {
            $dueDate = dueDateForStorage((string) ($workspaceTask['due_date'] ?? ''));
            if ($dueDate !== $overviewTodayIso) {
                continue;
            }

            $statusKey = normalizeTaskStatus((string) ($workspaceTask['status'] ?? 'todo'));
            if ($statusKey === 'done') {
                continue;
            }

            $taskAssigneeIds = is_array($workspaceTask['assignee_ids'] ?? null)
                ? $workspaceTask['assignee_ids']
                : [];
            $isAssignedToCurrentUser = in_array($overviewUserId, $taskAssigneeIds, true);
            $isOwnedByCurrentUserWithoutAssignee = !$taskAssigneeIds
                && (int) ($workspaceTask['created_by'] ?? 0) === $overviewUserId;
            if (!$isAssignedToCurrentUser && !$isOwnedByCurrentUserWithoutAssignee) {
                continue;
            }

            $priorityKey = normalizeTaskPriority((string) ($workspaceTask['priority'] ?? 'medium'));
            if ($priorityKey === 'urgent') {
                $workspaceUrgentTasksTodayCount++;
            }
            if ($priorityKey === 'urgent' || $priorityKey === 'high') {
                $workspacePriorityTasksTodayCount++;
            }
            $workspaceTasksToday[] = [
                'workspace_id' => $workspaceOptionId,
                'workspace_name' => $workspaceOptionName,
                'task_id' => (int) ($workspaceTask['id'] ?? 0),
                'title' => (string) ($workspaceTask['title'] ?? ''),
                'group_name' => normalizeTaskGroupName((string) ($workspaceTask['group_name'] ?? 'Geral')),
                'priority' => $priorityKey,
                'priority_label' => taskPriorities()[$priorityKey] ?? 'Media',
            ];
        }

        $workspaceDueEntries = workspaceDueEntriesList($workspaceOptionId);
        $workspaceDueEntries = array_values(array_filter(
            $workspaceDueEntries,
            static function (array $entry) use ($overviewUserId, $workspaceOptionId): bool {
                $groupName = normalizeDueGroupName((string) ($entry['group_name'] ?? 'Geral'));
                return userCanViewDueGroup($overviewUserId, $workspaceOptionId, $groupName);
            }
        ));

        $workspaceDueSoon = [];
        $workspaceDueTodayCount = 0;
        $workspaceDueTomorrowCount = 0;
        foreach ($workspaceDueEntries as $workspaceDueEntry) {
            $nextDueDateIso = dueDateForStorage((string) ($workspaceDueEntry['next_due_date'] ?? ''));
            if ($nextDueDateIso === null) {
                continue;
            }
            if ($nextDueDateIso < $overviewTodayIso || $nextDueDateIso > $overviewDueLimitIso) {
                continue;
            }

            $daysUntil = null;
            $daysUntilLabel = '';
            try {
                $daysUntil = (int) $overviewToday->diff(new DateTimeImmutable($nextDueDateIso))->format('%a');
            } catch (Throwable $e) {
                $daysUntil = null;
            }

            if ($daysUntil === 0) {
                $daysUntilLabel = 'Hoje';
                $workspaceDueTodayCount++;
            } elseif ($daysUntil === 1) {
                $daysUntilLabel = 'Amanha';
                $workspaceDueTomorrowCount++;
            } elseif ($daysUntil !== null) {
                $daysUntilLabel = 'Em ' . $daysUntil . ' dias';
            } else {
                $daysUntilLabel = (new DateTimeImmutable($nextDueDateIso))->format('d/m');
            }

            $workspaceDueSoon[] = [
                'workspace_id' => $workspaceOptionId,
                'workspace_name' => $workspaceOptionName,
                'label' => (string) ($workspaceDueEntry['label'] ?? ''),
                'group_name' => normalizeDueGroupName((string) ($workspaceDueEntry['group_name'] ?? 'Geral')),
                'amount_cents' => (int) ($workspaceDueEntry['amount_cents'] ?? 0),
                'amount_display' => (string) ($workspaceDueEntry['amount_display'] ?? dueAmountLabelFromCents(0)),
                'next_due_date' => $nextDueDateIso,
                'next_due_display' => (new DateTimeImmutable($nextDueDateIso))->format('d/m'),
                'days_until' => $daysUntil,
                'days_until_label' => $daysUntilLabel,
            ];
        }

        $workspaceInventoryEntries = workspaceInventoryEntriesList($workspaceOptionId);
        $workspaceLowStockEntries = [];
        foreach ($workspaceInventoryEntries as $inventoryEntry) {
            if (((int) ($inventoryEntry['is_low_stock'] ?? 0)) !== 1) {
                continue;
            }

            $quantityValue = (float) ($inventoryEntry['quantity_value'] ?? 0);
            $minQuantityValue = $inventoryEntry['min_quantity_value'] !== null
                ? (float) $inventoryEntry['min_quantity_value']
                : null;
            $deficitQuantity = 0.0;
            if ($minQuantityValue !== null && $quantityValue <= $minQuantityValue) {
                $deficitQuantity = $minQuantityValue - $quantityValue;
            }

            $workspaceLowStockEntries[] = [
                'workspace_id' => $workspaceOptionId,
                'workspace_name' => $workspaceOptionName,
                'label' => (string) ($inventoryEntry['label'] ?? ''),
                'group_name' => normalizeInventoryGroupName((string) ($inventoryEntry['group_name'] ?? 'Geral')),
                'quantity_display' => (string) ($inventoryEntry['quantity_display'] ?? '0'),
                'min_quantity_display' => (string) ($inventoryEntry['min_quantity_display'] ?? '0'),
                'unit_label' => normalizeInventoryUnitLabel((string) ($inventoryEntry['unit_label'] ?? 'un')),
                'deficit_quantity' => $deficitQuantity,
            ];
        }

        $workspaceAccountingEntries = workspaceAccountingEntriesList($workspaceOptionId, $overviewAccountingPeriod);
        $workspaceAccountingOpeningBalance = workspaceAccountingOpeningBalanceCents(
            $workspaceOptionId,
            $overviewAccountingPeriod
        );
        $workspaceAccountingSummary = accountingSummary($workspaceAccountingEntries, $workspaceAccountingOpeningBalance);
        $workspaceCurrentBalanceCents = (int) ($workspaceAccountingSummary['current_balance_cents'] ?? 0);
        $workspaceFinalBalanceCents = (int) ($workspaceAccountingSummary['final_balance_cents'] ?? 0);

        $globalDashboardOverview['tasks_today'] = array_merge(
            $globalDashboardOverview['tasks_today'],
            $workspaceTasksToday
        );
        $globalDashboardOverview['due_soon'] = array_merge(
            $globalDashboardOverview['due_soon'],
            $workspaceDueSoon
        );
        $globalDashboardOverview['low_stock'] = array_merge(
            $globalDashboardOverview['low_stock'],
            $workspaceLowStockEntries
        );
        $globalDashboardOverview['balance_month_movement_cents'] += $workspaceCurrentBalanceCents;
        $globalDashboardOverview['balance_total_cents'] += $workspaceFinalBalanceCents;

        $workspaceAttentionScore = ($workspaceUrgentTasksTodayCount * 4)
            + ($workspaceDueTodayCount * 4)
            + ($workspacePriorityTasksTodayCount * 2)
            + ($workspaceDueTomorrowCount * 2)
            + count($workspaceLowStockEntries);
        $workspaceAttentionTone = 'stable';
        $workspaceAttentionLabel = 'Estavel';
        $workspaceAttentionNote = 'Sem pendencias imediatas.';

        if ($workspaceUrgentTasksTodayCount > 0 || $workspaceDueTodayCount > 0) {
            $workspaceAttentionTone = 'critical';
            $workspaceAttentionLabel = 'Foco imediato';
            if ($workspaceUrgentTasksTodayCount > 0 && $workspaceDueTodayCount > 0) {
                $workspaceAttentionNote = 'Urgencias e vencimentos hoje.';
            } elseif ($workspaceUrgentTasksTodayCount > 0) {
                $workspaceAttentionNote = 'Tarefas urgentes para hoje.';
            } else {
                $workspaceAttentionNote = 'Contas vencem hoje.';
            }
        } elseif ($workspacePriorityTasksTodayCount > 0 || $workspaceDueTomorrowCount > 0 || count($workspaceLowStockEntries) > 0) {
            $workspaceAttentionTone = 'attention';
            $workspaceAttentionLabel = 'Monitorar';
            if ($workspaceDueTomorrowCount > 0) {
                $workspaceAttentionNote = 'Ha vencimentos previstos para amanha.';
            } elseif (count($workspaceLowStockEntries) > 0) {
                $workspaceAttentionNote = 'Itens abaixo do minimo precisam reposicao.';
            } else {
                $workspaceAttentionNote = 'Tarefas de alta prioridade para hoje.';
            }
        }

        $globalDashboardOverview['workspace_summaries'][] = [
            'workspace_id' => $workspaceOptionId,
            'workspace_name' => $workspaceOptionName,
            'workspace_role' => $workspaceOptionRole,
            'workspace_role_label' => $workspaceOptionRoleLabel,
            'tasks_today_count' => count($workspaceTasksToday),
            'urgent_tasks_today_count' => $workspaceUrgentTasksTodayCount,
            'priority_tasks_today_count' => $workspacePriorityTasksTodayCount,
            'due_soon_count' => count($workspaceDueSoon),
            'due_today_count' => $workspaceDueTodayCount,
            'due_tomorrow_count' => $workspaceDueTomorrowCount,
            'low_stock_count' => count($workspaceLowStockEntries),
            'attention_score' => $workspaceAttentionScore,
            'attention_tone' => $workspaceAttentionTone,
            'attention_label' => $workspaceAttentionLabel,
            'attention_note' => $workspaceAttentionNote,
            'month_movement_cents' => $workspaceCurrentBalanceCents,
            'month_movement_display' => dueAmountLabelFromSignedCents($workspaceCurrentBalanceCents),
            'balance_total_cents' => $workspaceFinalBalanceCents,
            'balance_total_display' => dueAmountLabelFromSignedCents($workspaceFinalBalanceCents),
        ];
    }

    $priorityOrder = [
        'urgent' => 0,
        'high' => 1,
        'medium' => 2,
        'low' => 3,
    ];
    usort(
        $globalDashboardOverview['tasks_today'],
        static function (array $a, array $b) use ($priorityOrder): int {
            $priorityA = $priorityOrder[normalizeTaskPriority((string) ($a['priority'] ?? 'medium'))] ?? 99;
            $priorityB = $priorityOrder[normalizeTaskPriority((string) ($b['priority'] ?? 'medium'))] ?? 99;
            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            $workspaceCompare = strcasecmp(
                (string) ($a['workspace_name'] ?? ''),
                (string) ($b['workspace_name'] ?? '')
            );
            if ($workspaceCompare !== 0) {
                return $workspaceCompare;
            }

            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        }
    );

    usort(
        $globalDashboardOverview['due_soon'],
        static function (array $a, array $b): int {
            $dueA = dueDateForStorage((string) ($a['next_due_date'] ?? '')) ?? '9999-12-31';
            $dueB = dueDateForStorage((string) ($b['next_due_date'] ?? '')) ?? '9999-12-31';
            if ($dueA !== $dueB) {
                return strcmp($dueA, $dueB);
            }

            $amountA = (int) ($a['amount_cents'] ?? 0);
            $amountB = (int) ($b['amount_cents'] ?? 0);
            if ($amountA !== $amountB) {
                return $amountB <=> $amountA;
            }

            return strcasecmp((string) ($a['workspace_name'] ?? ''), (string) ($b['workspace_name'] ?? ''));
        }
    );

    usort(
        $globalDashboardOverview['low_stock'],
        static function (array $a, array $b): int {
            $deficitA = (float) ($a['deficit_quantity'] ?? 0);
            $deficitB = (float) ($b['deficit_quantity'] ?? 0);
            if ($deficitA !== $deficitB) {
                return $deficitB <=> $deficitA;
            }

            $workspaceCompare = strcasecmp(
                (string) ($a['workspace_name'] ?? ''),
                (string) ($b['workspace_name'] ?? '')
            );
            if ($workspaceCompare !== 0) {
                return $workspaceCompare;
            }

            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        }
    );

    usort(
        $globalDashboardOverview['workspace_summaries'],
        static function (array $a, array $b): int {
            $scoreA = (int) ($a['attention_score'] ?? 0);
            $scoreB = (int) ($b['attention_score'] ?? 0);
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            $balanceA = abs((int) ($a['balance_total_cents'] ?? 0));
            $balanceB = abs((int) ($b['balance_total_cents'] ?? 0));
            if ($balanceA !== $balanceB) {
                return $balanceB <=> $balanceA;
            }

            return strcasecmp((string) ($a['workspace_name'] ?? ''), (string) ($b['workspace_name'] ?? ''));
        }
    );

    $globalDashboardOverview['tasks_today_total'] = count($globalDashboardOverview['tasks_today']);
    $globalDashboardOverview['urgent_tasks_today_total'] = count(array_filter(
        $globalDashboardOverview['tasks_today'],
        static function (array $taskToday): bool {
            return normalizeTaskPriority((string) ($taskToday['priority'] ?? 'medium')) === 'urgent';
        }
    ));
    $globalDashboardOverview['priority_tasks_today_total'] = count(array_filter(
        $globalDashboardOverview['tasks_today'],
        static function (array $taskToday): bool {
            $priority = normalizeTaskPriority((string) ($taskToday['priority'] ?? 'medium'));
            return $priority === 'urgent' || $priority === 'high';
        }
    ));
    $globalDashboardOverview['due_soon_total'] = count($globalDashboardOverview['due_soon']);
    $globalDashboardOverview['due_today_total'] = count(array_filter(
        $globalDashboardOverview['due_soon'],
        static function (array $dueSoon): bool {
            return (int) ($dueSoon['days_until'] ?? -1) === 0;
        }
    ));
    $globalDashboardOverview['due_tomorrow_total'] = count(array_filter(
        $globalDashboardOverview['due_soon'],
        static function (array $dueSoon): bool {
            return (int) ($dueSoon['days_until'] ?? -1) === 1;
        }
    ));
    $globalDashboardOverview['low_stock_total'] = count($globalDashboardOverview['low_stock']);
    $globalDashboardOverview['critical_workspace_total'] = count(array_filter(
        $globalDashboardOverview['workspace_summaries'],
        static function (array $workspaceSummary): bool {
            return (string) ($workspaceSummary['attention_tone'] ?? 'stable') === 'critical';
        }
    ));
    $globalDashboardOverview['attention_workspace_total'] = count(array_filter(
        $globalDashboardOverview['workspace_summaries'],
        static function (array $workspaceSummary): bool {
            return (string) ($workspaceSummary['attention_tone'] ?? 'stable') === 'attention';
        }
    ));
    $globalDashboardOverview['executive_focus_total'] = (int) (
        $globalDashboardOverview['urgent_tasks_today_total']
        + $globalDashboardOverview['due_today_total']
        + $globalDashboardOverview['critical_workspace_total']
    );
    if ($globalDashboardOverview['executive_focus_total'] > 0) {
        $globalDashboardOverview['executive_status_tone'] = 'critical';
        $globalDashboardOverview['executive_status_label'] = 'Foco imediato';
        $globalDashboardOverview['executive_status_note'] = 'Urgencias do dia e vencimentos de hoje merecem decisao primeiro.';
    } elseif (
        (int) ($globalDashboardOverview['priority_tasks_today_total'] ?? 0) > 0
        || (int) ($globalDashboardOverview['due_tomorrow_total'] ?? 0) > 0
        || (int) ($globalDashboardOverview['low_stock_total'] ?? 0) > 0
        || (int) ($globalDashboardOverview['attention_workspace_total'] ?? 0) > 0
    ) {
        $globalDashboardOverview['executive_status_tone'] = 'attention';
        $globalDashboardOverview['executive_status_label'] = 'Monitoramento';
        $globalDashboardOverview['executive_status_note'] = 'A operacao esta controlada, mas ha itens proximos exigindo acompanhamento.';
    }
    $globalDashboardOverview['balance_total_display'] = dueAmountLabelFromSignedCents(
        (int) $globalDashboardOverview['balance_total_cents']
    );
    $globalDashboardOverview['balance_month_movement_display'] = dueAmountLabelFromSignedCents(
        (int) $globalDashboardOverview['balance_month_movement_cents']
    );

    $globalDashboardOverview['tasks_today'] = array_slice($globalDashboardOverview['tasks_today'], 0, 8);
    $globalDashboardOverview['due_soon'] = array_slice($globalDashboardOverview['due_soon'], 0, 8);
    $globalDashboardOverview['low_stock'] = array_slice($globalDashboardOverview['low_stock'], 0, 8);
}

$defaultTaskGroupName = $taskGroups[0] ?? 'Geral';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/WorkForm - Símbolo.svg?v=1">
    <link rel="shortcut icon" href="assets/WorkForm - Símbolo.svg?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css?v=<?= e($stylesAssetVersion) ?>">
    <script src="assets/app.js?v=<?= e($appAssetVersion) ?>" defer></script>
</head>
<body
    class="<?= $renderAuthScreen ? 'is-auth' : 'is-dashboard' ?>"
    data-default-group-name="<?= e((string) $defaultTaskGroupName) ?>"
    data-workspace-id="<?= e((string) ($renderAuthScreen ? '' : ($currentWorkspaceId ?? ''))) ?>"
    data-user-id="<?= e((string) ($renderAuthScreen ? '' : ($currentUser['id'] ?? ''))) ?>"
>
    <div class="bg-layer bg-layer-one" aria-hidden="true"></div>
    <div class="bg-layer bg-layer-two" aria-hidden="true"></div>
    <div class="grain" aria-hidden="true"></div>

    <div class="app-shell">
        <?php if ($flashes): ?>
            <div class="flash-stack" aria-live="polite">
                <?php foreach ($flashes as $flash): ?>
                    <div class="flash flash-<?= e((string) ($flash['type'] ?? 'info')) ?>" data-flash>
                        <span><?= e((string) ($flash['message'] ?? '')) ?></span>
                        <button type="button" class="flash-close" data-flash-close aria-label="Fechar">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($renderAuthScreen): ?>
            <?php include __DIR__ . '/partials/auth.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/partials/dashboard.php'; ?>
        <?php endif; ?>
    </div>
</body>
</html>
