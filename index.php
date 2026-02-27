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

function dashboardSummaryPayloadForUser(int $userId): array
{
    $allTasks = allTasks();
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

            case 'create_group':
                $authUser = requireAuth();
                $groupName = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));

                if (findTaskGroupByName($groupName) !== null) {
                    throw new RuntimeException('Este grupo jÃ¡ existe.');
                }

                upsertTaskGroup($pdo, $groupName, (int) $authUser['id']);
                flash('success', 'Grupo criado.');
                redirectTo('index.php#tasks');

            case 'rename_group':
                $authUser = requireAuth();
                $oldGroupInput = normalizeTaskGroupName((string) ($_POST['old_group_name'] ?? ''));
                $newGroupName = normalizeTaskGroupName((string) ($_POST['new_group_name'] ?? ''));
                $existingOldGroupName = findTaskGroupByName($oldGroupInput);

                if ($existingOldGroupName === null) {
                    throw new RuntimeException('Grupo nao encontrado.');
                }

                $existingTargetGroupName = findTaskGroupByName($newGroupName);
                if (
                    $existingTargetGroupName !== null &&
                    mb_strtolower($existingTargetGroupName) !== mb_strtolower($existingOldGroupName)
                ) {
                    throw new RuntimeException('Ja existe um grupo com este nome.');
                }

                $taskCountStmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE group_name = :group_name');
                $taskCountStmt->execute([':group_name' => $existingOldGroupName]);
                $affectedTaskCount = (int) $taskCountStmt->fetchColumn();
                $affectedTaskIds = [];
                $renameUpdatedAt = nowIso();

                if ($affectedTaskCount > 0 && $existingOldGroupName !== $newGroupName) {
                    $taskIdsStmt = $pdo->prepare('SELECT id FROM tasks WHERE group_name = :group_name');
                    $taskIdsStmt->execute([':group_name' => $existingOldGroupName]);
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
                             WHERE name = :old_group_name'
                        );
                        $renameGroupStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':old_group_name' => $existingOldGroupName,
                        ]);
                    }

                    if ($affectedTaskCount > 0 && $existingOldGroupName !== $newGroupName) {
                        $renameTasksStmt = $pdo->prepare(
                            'UPDATE tasks
                             SET group_name = :new_group_name, updated_at = :updated_at
                             WHERE group_name = :old_group_name'
                        );
                        $renameTasksStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':updated_at' => $renameUpdatedAt,
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
                $groupName = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findTaskGroupByName($groupName);
                $fallbackGroupName = defaultTaskGroupName();

                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo nao encontrado.');
                }

                if (isProtectedTaskGroupName($existingGroupName)) {
                    throw new RuntimeException('Este grupo nao pode ser removido.');
                }

                $countStmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE group_name = :group_name');
                $countStmt->execute([':group_name' => $existingGroupName]);
                $taskCount = (int) $countStmt->fetchColumn();
                $movedTaskIds = [];
                $movedUpdatedAt = nowIso();
                if ($taskCount > 0) {
                    $taskIdsStmt = $pdo->prepare('SELECT id FROM tasks WHERE group_name = :group_name');
                    $taskIdsStmt->execute([':group_name' => $existingGroupName]);
                    $movedTaskIds = array_map(
                        'intval',
                        array_column($taskIdsStmt->fetchAll(), 'id')
                    );
                }
                upsertTaskGroup($pdo, $fallbackGroupName, null);

                if ($taskCount > 0) {
                    $moveStmt = $pdo->prepare(
                        'UPDATE tasks
                         SET group_name = :target_group, updated_at = :updated_at
                         WHERE group_name = :source_group'
                    );
                    $moveStmt->execute([
                        ':target_group' => $fallbackGroupName,
                        ':updated_at' => $movedUpdatedAt,
                        ':source_group' => $existingGroupName,
                    ]);

                    foreach ($movedTaskIds as $movedTaskId) {
                        if ($movedTaskId <= 0) {
                            continue;
                        }

                        logTaskHistory(
                            $pdo,
                            $movedTaskId,
                            'group_changed',
                            ['old' => $existingGroupName, 'new' => $fallbackGroupName],
                            (int) $authUser['id'],
                            $movedUpdatedAt
                        );
                    }
                }

                $deleteStmt = $pdo->prepare('DELETE FROM task_groups WHERE name = :name');
                $deleteStmt->execute([':name' => $existingGroupName]);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $existingGroupName,
                        'moved_task_count' => $taskCount,
                        'moved_to_group' => $fallbackGroupName,
                    ]);
                }

                flash(
                    'success',
                    $taskCount > 0
                        ? sprintf('Grupo removido. Tarefas movidas para %s.', $fallbackGroupName)
                        : 'Grupo removido.'
                );
                redirectTo('index.php#tasks');

            case 'create_task':
            case 'update_task':
                $authUser = requireAuth();
                $isAutosave = $action === 'update_task' && (string) ($_POST['autosave'] ?? '') === '1';
                $usersById = usersMapById();
                $taskId = (int) ($_POST['task_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
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
                    ? defaultTaskGroupName()
                    : normalizeTaskGroupName($groupInputRaw);
                $existingGroupName = findTaskGroupByName($groupName);
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
                upsertTaskGroup($pdo, $groupName, (int) $authUser['id']);

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
                        'INSERT INTO tasks (title, description, status, priority, due_date, overdue_flag, overdue_since_date, created_by, assigned_to, assignee_ids_json, reference_links_json, reference_images_json, group_name, created_at, updated_at)
                         VALUES (:t, :d, :s, :p, :dd, :of, :osd, :cb, :at, :aj, :rl, :ri, :g, :c, :u)'
                    );
                    $now = nowIso();
                    $stmt->execute([
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
                     LIMIT 1'
                );
                $existingTaskStmt->execute([':id' => $taskId]);
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
                     WHERE id = :id'
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
                ]);

                $existingStatus = normalizeTaskStatus((string) ($existingTaskRow['status'] ?? 'todo'));
                $existingPriority = normalizeTaskPriority((string) ($existingTaskRow['priority'] ?? 'medium'));
                $existingTitle = trim((string) ($existingTaskRow['title'] ?? ''));
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
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id']),
                    ]);
                }
                if (!$isAutosave) {
                    flash('success', 'Tarefa atualizada.');
                }
                redirectTo('index.php#task-' . $taskId);

            case 'move_task':
                $authUser = requireAuth();
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa invalida.');
                }

                $existingTaskStmt = $pdo->prepare(
                    'SELECT status, overdue_flag, overdue_since_date
                     FROM tasks
                     WHERE id = :id
                     LIMIT 1'
                );
                $existingTaskStmt->execute([':id' => $taskId]);
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
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':s' => $status,
                    ':done' => 'done',
                    ':u' => $updatedAt,
                    ':id' => $taskId,
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
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id']),
                    ]);
                }
                flash('success', 'Status atualizado.');
                redirectTo('index.php#task-' . $taskId);

            case 'delete_task':
                $authUser = requireAuth();
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
                }
                $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id');
                $stmt->execute([':id' => $taskId]);
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'task_id' => $taskId,
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id']),
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
$flashes = getFlashes();
$statusOptions = taskStatuses();
$priorityOptions = taskPriorities();
$users = usersList();
$statusFilter = isset($_GET['status']) && trim((string) $_GET['status']) !== ''
    ? normalizeTaskStatus((string) $_GET['status'])
    : null;
$assigneeFilterId = isset($_GET['assignee']) ? (int) $_GET['assignee'] : null;
$assigneeFilterId = $assigneeFilterId && $assigneeFilterId > 0 ? $assigneeFilterId : null;

if ($currentUser) {
    applyOverdueTaskPolicy();
}

$allTasks = $currentUser ? allTasks() : [];
$tasks = $currentUser ? filterTasks($allTasks, $statusFilter, $assigneeFilterId) : [];
$taskGroups = $currentUser ? taskGroupsList() : ['Geral'];
$protectedGroupName = $currentUser ? defaultTaskGroupName() : 'Geral';
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
    <link rel="stylesheet" href="assets/styles.css?v=29">
    <script src="assets/app.js?v=13" defer></script>
</head>
<body class="<?= $currentUser ? 'is-dashboard' : 'is-auth' ?>" data-default-group-name="<?= e((string) $protectedGroupName) ?>">
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
