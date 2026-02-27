<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        verifyCsrf();

        switch ($action) {
            case 'register':
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $password = (string) ($_POST['password'] ?? '');
                $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

                if ($name === '' || $email === '' || $password === '') {
                    throw new RuntimeException('Preencha nome, e-mail e senha.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Informe um e-mail válido.');
                }
                if (mb_strlen($password) < 6) {
                    throw new RuntimeException('A senha deve ter pelo menos 6 caracteres.');
                }
                if ($password !== $passwordConfirm) {
                    throw new RuntimeException('A confirmação de senha não confere.');
                }

                $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $check->execute([':email' => $email]);
                if ($check->fetch()) {
                    throw new RuntimeException('Este e-mail já está cadastrado.');
                }

                $newUserId = createUser(
                    $pdo,
                    $name,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    nowIso()
                );
                loginUser($newUserId, true);
                flash('success', 'Conta criada com sucesso.');
                redirectTo('index.php');

            case 'login':
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $password = (string) ($_POST['password'] ?? '');
                if ($email === '' || $password === '') {
                    throw new RuntimeException('Informe e-mail e senha.');
                }

                $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $userRow = $stmt->fetch();
                if (!$userRow || !password_verify($password, (string) $userRow['password_hash'])) {
                    throw new RuntimeException('Credenciais inválidas.');
                }

                loginUser((int) $userRow['id'], true);
                flash('success', 'Login realizado.');
                redirectTo('index.php');

            case 'logout':
                logoutUser();
                flash('success', 'Sessão encerrada.');
                redirectTo('index.php');

            case 'switch_workspace':
                $authUser = requireAuth();
                $workspaceId = (int) ($_POST['workspace_id'] ?? 0);
                if ($workspaceId <= 0 || !userHasWorkspaceAccess((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Workspace invalido.');
                }

                setActiveWorkspaceId($workspaceId);
                flash('success', 'Workspace atualizado.');
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

            case 'add_workspace_member':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
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
                flash('success', 'Usuario adicionado ao workspace.');
                redirectTo('index.php#tasks');

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

                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
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
                if (isProtectedVaultGroupName($existingGroupName, $workspaceId)) {
                    throw new RuntimeException('Este grupo do cofre nao pode ser removido.');
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
                        ? sprintf('Grupo do cofre removido. %d item(ns) excluido(s).', $deletedEntriesCount)
                        : 'Grupo do cofre removido.'
                );
                redirectTo('index.php#vault');

            case 'create_vault_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $groupNameInput = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findVaultGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                createWorkspaceVaultEntry(
                    $pdo,
                    $workspaceId,
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['login_value'] ?? ''),
                    (string) ($_POST['password_value'] ?? ''),
                    $groupName,
                    (int) $authUser['id']
                );

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
                updateWorkspaceVaultEntry(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['login_value'] ?? ''),
                    (string) ($_POST['password_value'] ?? ''),
                    $groupName
                );

                flash('success', 'Item do cofre atualizado.');
                redirectTo('index.php#vault');

            case 'delete_vault_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                deleteWorkspaceVaultEntry($pdo, $workspaceId, $entryId);

                flash('success', 'Item removido do cofre.');
                redirectTo('index.php#vault');

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

                upsertTaskGroup($pdo, $groupName, (int) $authUser['id'], $workspaceId);
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

                if (isProtectedTaskGroupName($existingGroupName, $workspaceId)) {
                    throw new RuntimeException('Este grupo nao pode ser removido.');
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

            case 'create_task':
            case 'update_task':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                $isAutosave = $action === 'update_task' && (string) ($_POST['autosave'] ?? '') === '1';
                $usersById = usersMapById($workspaceId);
                $taskId = (int) ($_POST['task_id'] ?? 0);
                $title = normalizeTaskTitle((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $referenceLinksPosted = array_key_exists('reference_links_json', $_POST);
                $referenceImagesPosted = array_key_exists('reference_images_json', $_POST);
                $referenceLinks = $referenceLinksPosted
                    ? decodeReferenceUrlList((string) ($_POST['reference_links_json'] ?? '[]'))
                    : null;
                $referenceImages = $referenceImagesPosted
                    ? decodeReferenceImageList((string) ($_POST['reference_images_json'] ?? '[]'))
                    : null;
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
                upsertTaskGroup($pdo, $groupName, (int) $authUser['id'], $workspaceId);

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
                    $stmt = $pdo->prepare(
                        'INSERT INTO tasks (workspace_id, title, description, status, priority, due_date, overdue_flag, overdue_since_date, created_by, assigned_to, assignee_ids_json, reference_links_json, reference_images_json, group_name, created_at, updated_at)
                         VALUES (:workspace_id, :t, :d, :s, :p, :dd, :of, :osd, :cb, :at, :aj, :rl, :ri, :g, :c, :u)'
                    );
                    $now = nowIso();
                    $stmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':t' => $title,
                        ':d' => $description,
                        ':s' => $status,
                        ':p' => $priority,
                        ':dd' => $dueDate,
                        ':of' => $overdueFlag,
                        ':osd' => $overdueSinceDate,
                        ':cb' => (int) $authUser['id'],
                        ':at' => $assignedTo,
                        ':aj' => $assigneeIdsJson,
                        ':rl' => encodeReferenceUrlList($referenceLinks),
                        ':ri' => encodeReferenceImageList($referenceImages),
                        ':g' => $groupName,
                        ':c' => $now,
                        ':u' => $now,
                    ]);
                    $createdTaskId = (int) $pdo->lastInsertId();
                    if ($createdTaskId > 0) {
                        logTaskHistory(
                            $pdo,
                            $createdTaskId,
                            'created',
                            [
                                'title' => $title,
                                'status' => $status,
                                'priority' => $priority,
                                'due_date' => $dueDate,
                            ],
                            (int) $authUser['id'],
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
                                (int) $authUser['id'],
                                $now
                            );
                        }
                    }
                    flash('success', 'Tarefa criada.');
                    redirectTo('index.php#tasks');
                }

                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa invalida.');
                }
                $existingTaskStmt = $pdo->prepare(
                    'SELECT title, status, priority, due_date, overdue_flag, overdue_since_date, assignee_ids_json, group_name, reference_links_json, reference_images_json
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
                if ($referenceLinks === null) {
                    $referenceLinks = decodeReferenceUrlList($existingTaskRow['reference_links_json'] ?? null);
                }
                if ($referenceImages === null) {
                    $referenceImages = decodeReferenceImageList($existingTaskRow['reference_images_json'] ?? null);
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

                $stmt = $pdo->prepare(
                    'UPDATE tasks
                     SET title = :t,
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
                         group_name = :g,
                         updated_at = :u
                     WHERE id = :id
                       AND workspace_id = :workspace_id'
                );
                $updatedAt = nowIso();
                $stmt->execute([
                    ':t' => $title,
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
                    ':g' => $groupName,
                    ':u' => $updatedAt,
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);

                $existingStatus = normalizeTaskStatus((string) ($existingTaskRow['status'] ?? 'todo'));
                $existingPriority = normalizeTaskPriority((string) ($existingTaskRow['priority'] ?? 'medium'));
                $existingTitle = normalizeTaskTitle((string) ($existingTaskRow['title'] ?? ''));
                $existingDueDate = dueDateForStorage((string) ($existingTaskRow['due_date'] ?? ''));
                $existingGroup = normalizeTaskGroupName((string) ($existingTaskRow['group_name'] ?? 'Geral'));
                $existingOverdueFlag = ((int) ($existingTaskRow['overdue_flag'] ?? 0)) === 1 ? 1 : 0;
                $existingOverdueSinceDate = dueDateForStorage((string) ($existingTaskRow['overdue_since_date'] ?? ''));
                $existingAssigneeIds = decodeAssigneeIds($existingTaskRow['assignee_ids_json'] ?? null);
                $actorUserId = (int) $authUser['id'];
                $statusOptions = taskStatuses();
                $priorityOptions = taskPriorities();

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

                $taskHistory = taskHistoryList($taskId);
                if ($isAutosave && requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'task' => [
                            'id' => $taskId,
                            'group_name' => $groupName,
                            'due_date' => $dueDate,
                            'status' => $status,
                            'priority' => $priority,
                            'overdue_flag' => $overdueFlag,
                            'overdue_since_date' => $overdueSinceDate,
                            'overdue_days' => $overdueDays,
                            'reference_links_json' => encodeReferenceUrlList($referenceLinks ?? []),
                            'reference_images_json' => encodeReferenceImageList($referenceImages ?? []),
                            'history' => $taskHistory,
                            'updated_at' => $updatedAt,
                            'updated_at_label' => (new DateTimeImmutable($updatedAt))->format('d/m H:i'),
                        ],
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                    ]);
                }
                if (!$isAutosave) {
                    flash('success', 'Tarefa atualizada.');
                }
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
                    'SELECT status, overdue_flag, overdue_since_date
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

            case 'delete_task':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
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
        redirectTo('index.php');
    }
}

$currentUser = currentUser();
$currentWorkspaceId = $currentUser ? activeWorkspaceId($currentUser) : null;
$currentWorkspace = ($currentUser && $currentWorkspaceId !== null) ? activeWorkspace($currentUser) : null;
$userWorkspaces = $currentUser ? workspacesForUser((int) $currentUser['id']) : [];
$flashes = getFlashes();
$statusOptions = taskStatuses();
$priorityOptions = taskPriorities();
$users = ($currentUser && $currentWorkspaceId !== null) ? usersList($currentWorkspaceId) : [];
$workspaceMembers = ($currentUser && $currentWorkspaceId !== null) ? workspaceMembersList($currentWorkspaceId) : [];
$vaultEntries = ($currentUser && $currentWorkspaceId !== null) ? workspaceVaultEntriesList($currentWorkspaceId) : [];
$vaultGroups = ($currentUser && $currentWorkspaceId !== null) ? vaultGroupsList($currentWorkspaceId) : ['Geral'];
$protectedVaultGroupName = ($currentUser && $currentWorkspaceId !== null) ? defaultVaultGroupName($currentWorkspaceId) : 'Geral';
if ($currentUser && $vaultGroups) {
    $protectedVaultIndex = null;
    foreach ($vaultGroups as $index => $groupName) {
        if (mb_strtolower((string) $groupName) === mb_strtolower($protectedVaultGroupName)) {
            $protectedVaultIndex = $index;
            break;
        }
    }
    if ($protectedVaultIndex !== null && $protectedVaultIndex !== 0) {
        $protectedVault = $vaultGroups[$protectedVaultIndex];
        unset($vaultGroups[$protectedVaultIndex]);
        array_unshift($vaultGroups, $protectedVault);
        $vaultGroups = array_values($vaultGroups);
    }
}
$vaultEntriesByGroup = $currentUser ? vaultEntriesByGroup($vaultEntries, $vaultGroups) : [];
$statusFilter = isset($_GET['status']) && trim((string) $_GET['status']) !== ''
    ? normalizeTaskStatus((string) $_GET['status'])
    : null;
$assigneeFilterId = isset($_GET['assignee']) ? (int) $_GET['assignee'] : null;
$assigneeFilterId = $assigneeFilterId && $assigneeFilterId > 0 ? $assigneeFilterId : null;

if ($currentUser && $currentWorkspaceId !== null) {
    applyOverdueTaskPolicy($currentWorkspaceId);
}

$allTasks = ($currentUser && $currentWorkspaceId !== null) ? allTasks($currentWorkspaceId) : [];
$tasks = $currentUser ? filterTasks($allTasks, $statusFilter, $assigneeFilterId) : [];
$taskGroups = ($currentUser && $currentWorkspaceId !== null) ? taskGroupsList($currentWorkspaceId) : ['Geral'];
$protectedGroupName = ($currentUser && $currentWorkspaceId !== null) ? defaultTaskGroupName($currentWorkspaceId) : 'Geral';
if ($currentUser && $taskGroups) {
    $protectedIndex = null;
    foreach ($taskGroups as $index => $groupName) {
        if (mb_strtolower((string) $groupName) === mb_strtolower($protectedGroupName)) {
            $protectedIndex = $index;
            break;
        }
    }
    if ($protectedIndex !== null && $protectedIndex !== 0) {
        $protected = $taskGroups[$protectedIndex];
        unset($taskGroups[$protectedIndex]);
        array_unshift($taskGroups, $protected);
        $taskGroups = array_values($taskGroups);
    }
}
$showEmptyGroups = $currentUser && $statusFilter === null && $assigneeFilterId === null;
$tasksGroupedByGroup = $currentUser ? tasksByGroup($tasks, $showEmptyGroups ? $taskGroups : null) : [];
$stats = $currentUser ? dashboardStats($allTasks) : ['total' => 0, 'done' => 0, 'due_today' => 0, 'urgent' => 0];
$myOpenTasks = $currentUser ? countMyAssignedTasks($allTasks, (int) $currentUser['id']) : 0;
$completionRate = $stats['total'] > 0 ? (int) round(($stats['done'] / $stats['total']) * 100) : 0;
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
    <link rel="stylesheet" href="assets/styles.css?v=40">
    <script src="assets/app.js?v=16" defer></script>
</head>
<body
    class="<?= $currentUser ? 'is-dashboard' : 'is-auth' ?>"
    data-default-group-name="<?= e((string) $protectedGroupName) ?>"
    data-workspace-id="<?= e((string) ($currentWorkspaceId ?? '')) ?>"
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

        <?php if (!$currentUser): ?>
            <?php include __DIR__ . '/partials/auth.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/partials/dashboard.php'; ?>
        <?php endif; ?>
    </div>
</body>
</html>
