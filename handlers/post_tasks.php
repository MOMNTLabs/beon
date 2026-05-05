<?php
declare(strict_types=1);

function handleTaskPostAction(PDO $pdo, string $action): bool
{
    switch ($action) {
            case 'task_undo':
            case 'task_redo':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nao encontrado.');
                }

                $result = taskUndoApply(
                    $pdo,
                    $workspaceId,
                    (int) $authUser['id'],
                    $action === 'task_redo'
                );
                $message = (string) ($result['message'] ?? 'Acao aplicada.');

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $message,
                        'undo_state' => taskUndoState($workspaceId),
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                    ]);
                }

                flash('success', $message);
                redirectTo(tasksRedirectPathFromRequest());

            case 'create_task':
            case 'update_task':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
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
                $expectedUpdatedAt = trim((string) ($_POST['expected_updated_at'] ?? ''));
                $enforceTaskRevisionCheck = $action === 'update_task' && $expectedUpdatedAt !== '';
                $overdueFlagPosted = array_key_exists('overdue_flag', $_POST);
                $overdueFlag = $overdueFlagPosted
                    ? (((int) ($_POST['overdue_flag'] ?? 0)) === 1 ? 1 : 0)
                    : null;
                $overdueSinceDate = dueDateForStorage((string) ($_POST['overdue_since_date'] ?? ''));
                $status = normalizeTaskStatus((string) ($_POST['status'] ?? 'todo'), $workspaceId);
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
                    throw new RuntimeException('O título da tarefa é obrigatório.');
                }
                if (mb_strlen($title) > 140) {
                    throw new RuntimeException('O título deve ter no máximo 140 caracteres.');
                }
                if (count($submittedAssigneeIds) !== count($assigneeIds)) {
                    throw new RuntimeException('Um ou mais responsáveis selecionados são inválidos.');
                }

                if ($action === 'create_task') {
                    if (!userCanAccessTaskGroup($actorUserId, $workspaceId, $groupName)) {
                        throw new RuntimeException('Você não possui acesso para criar tarefas neste grupo.');
                    }
                    upsertTaskGroup($pdo, $groupName, $actorUserId, $workspaceId);

                    $normalized = normalizeTaskOverdueState(
                        $status,
                        $priority,
                        $dueDate,
                        $overdueFlag ?? 0,
                        $overdueSinceDate,
                        $workspaceId
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
                    $status = applyTaskSubtasksCompletionStatus($status, $subtasks, $workspaceId);
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

                        taskUndoPushOperation(
                            $workspaceId,
                            taskUndoBuildOperation(
                                'create',
                                $createdTaskId,
                                null,
                                taskUndoTaskSnapshot($pdo, $workspaceId, $createdTaskId)
                            )
                        );
                    }
                    flash('success', 'Tarefa criada.');
                    redirectTo(tasksRedirectPathFromRequest());
                }

                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
                }
                $existingTaskStmt = $pdo->prepare(
                    'SELECT title, title_tag, description, status, priority, due_date, overdue_flag, overdue_since_date, assignee_ids_json, group_name, reference_links_json, reference_images_json, subtasks_json, subtasks_dependency_enabled, updated_at
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
                    throw new RuntimeException('Tarefa inválida.');
                }
                $taskTitleTagColors = taskTitleTagColorsByWorkspace($workspaceId, $pdo);
                $existingTaskGroupName = normalizeTaskGroupName((string) ($existingTaskRow['group_name'] ?? 'Geral'));
                if (!userCanAccessTaskGroup($actorUserId, $workspaceId, $existingTaskGroupName)) {
                    throw new RuntimeException('Você não possui acesso para editar tarefas deste grupo.');
                }
                if (!userCanAccessTaskGroup($actorUserId, $workspaceId, $groupName)) {
                    throw new RuntimeException('Você não possui acesso ao grupo de destino.');
                }
                upsertTaskGroup($pdo, $groupName, $actorUserId, $workspaceId);
                $undoBeforeTask = taskUndoTaskSnapshot($pdo, $workspaceId, $taskId);

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
                    $overdueSinceDate,
                    $workspaceId
                );
                $status = $normalized['status'];
                $priority = $normalized['priority'];
                $dueDate = $normalized['due_date'];
                $overdueFlag = $normalized['overdue_flag'];
                $overdueSinceDate = $normalized['overdue_since_date'];
                $overdueDays = (int) ($normalized['overdue_days'] ?? 0);
                $status = applyTaskSubtasksCompletionStatus($status, $subtasks, $workspaceId);

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
                       AND workspace_id = :workspace_id' . ($enforceTaskRevisionCheck ? '
                       AND updated_at = :expected_updated_at' : '')
                );
                $updatedAt = nowIso();
                $updateParams = [
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
                ];
                if ($enforceTaskRevisionCheck) {
                    $updateParams[':expected_updated_at'] = $expectedUpdatedAt;
                }
                $stmt->execute($updateParams);

                if ($enforceTaskRevisionCheck && $stmt->rowCount() === 0) {
                    $currentVersionStmt = $pdo->prepare(
                        'SELECT updated_at
                         FROM tasks
                         WHERE id = :id
                           AND workspace_id = :workspace_id
                         LIMIT 1'
                    );
                    $currentVersionStmt->execute([
                        ':id' => $taskId,
                        ':workspace_id' => $workspaceId,
                    ]);
                    $latestUpdatedAt = trim((string) ($currentVersionStmt->fetchColumn() ?: ''));
                    if ($latestUpdatedAt === '') {
                        throw new RuntimeException('Tarefa inválida.');
                    }

                    if ($latestUpdatedAt !== $expectedUpdatedAt) {
                        $conflictMessage = 'Esta tarefa foi atualizada em outra sessão. Atualize os dados antes de salvar novamente.';
                        if (requestExpectsJson()) {
                            $latestUpdatedAtLabel = (new DateTimeImmutable($latestUpdatedAt))->format('d/m H:i');
                            respondJson([
                                'ok' => false,
                                'error' => $conflictMessage,
                                'code' => 'task_conflict',
                                'task' => [
                                    'id' => $taskId,
                                    'updated_at' => $latestUpdatedAt,
                                    'updated_at_label' => $latestUpdatedAtLabel,
                                ],
                            ], 409);
                        }

                        throw new RuntimeException($conflictMessage);
                    }

                    $updatedAt = $latestUpdatedAt;
                }

                $existingStatus = normalizeTaskStatus((string) ($existingTaskRow['status'] ?? 'todo'), $workspaceId);
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
                $statusOptions = taskStatuses($workspaceId);
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

                taskUndoPushOperation(
                    $workspaceId,
                    taskUndoBuildOperation(
                        'update',
                        $taskId,
                        $undoBeforeTask,
                        taskUndoTaskSnapshot($pdo, $workspaceId, $taskId)
                    )
                );

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
                        'undo_state' => taskUndoState($workspaceId),
                    ]);
                }
                if (!$isAutosave) {
                    flash('success', 'Tarefa atualizada.');
                }
                redirectTo('index.php#task-' . $taskId);

            case 'add_task_title_tag_option':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $titleTag = normalizeTaskTitleTag((string) ($_POST['title_tag'] ?? ''));
                if ($titleTag === '') {
                    throw new RuntimeException('Tag inválida.');
                }

                $taskTitleTagOptions = addTaskTitleTagOptionForWorkspace(
                    $pdo,
                    $workspaceId,
                    $titleTag
                );
                $taskTitleTagColors = taskTitleTagColorsByWorkspace($workspaceId, $pdo);
                $resolvedColor = taskTitleTagColorForTag($titleTag, $taskTitleTagColors);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'title_tag' => $titleTag,
                        'title_tag_color' => $resolvedColor,
                        'options' => $taskTitleTagOptions,
                    ]);
                }

                flash('success', 'Tag adicionada.');
                redirectTo('index.php#tasks');

            case 'remove_task_title_tag_option':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $titleTag = normalizeTaskTitleTag((string) ($_POST['title_tag'] ?? ''));
                if ($titleTag === '') {
                    throw new RuntimeException('Tag inválida.');
                }

                $taskTitleTagOptions = removeTaskTitleTagOptionForWorkspace(
                    $pdo,
                    $workspaceId,
                    $titleTag
                );
                $taskTitleTagColors = taskTitleTagColorsByWorkspace($workspaceId, $pdo);
                $removedTagKey = mb_strtolower($titleTag, 'UTF-8');
                if (isset($taskTitleTagColors[$removedTagKey])) {
                    unset($taskTitleTagColors[$removedTagKey]);
                    saveTaskTitleTagColorsByWorkspace($pdo, $workspaceId, $taskTitleTagColors);
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'title_tag' => $titleTag,
                        'options' => $taskTitleTagOptions,
                    ]);
                }

                flash('success', 'Tag removida.');
                redirectTo('index.php#tasks');
            case 'set_task_title_tag_color':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $titleTag = normalizeTaskTitleTag((string) ($_POST['title_tag'] ?? ''));
                if ($titleTag === '') {
                    throw new RuntimeException('Tag inválida.');
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
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
                }

                $newDescription = trim((string) ($_POST['revision_description'] ?? ''));
                if ($newDescription === '') {
                    throw new RuntimeException('A nova descrição é obrigatória.');
                }
                if (mb_strlen($newDescription) > 8000) {
                    throw new RuntimeException('A nova descrição deve ter no máximo 8000 caracteres.');
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
                    throw new RuntimeException('Tarefa inválida.');
                }

                $taskGroupName = normalizeTaskGroupName((string) ($taskRow['group_name'] ?? 'Geral'));
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $taskGroupName)) {
                    throw new RuntimeException('Você não possui acesso para atualizar esta tarefa.');
                }

                $taskStatus = normalizeTaskStatus((string) ($taskRow['status'] ?? 'todo'), $workspaceId);
                if (taskStatusKind($taskStatus, $workspaceId) !== 'review') {
                    throw new RuntimeException('A solicitação de ajuste só pode ser feita em tarefas em revisão.');
                }

                $previousDescription = trim((string) ($taskRow['description'] ?? ''));
                if ($previousDescription === $newDescription) {
                    throw new RuntimeException('A nova descrição precisa ser diferente da descrição atual.');
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
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
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
                    throw new RuntimeException('Tarefa inválida.');
                }

                $taskGroupName = normalizeTaskGroupName((string) ($taskRow['group_name'] ?? 'Geral'));
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $taskGroupName)) {
                    throw new RuntimeException('Você não possui acesso para atualizar esta tarefa.');
                }

                $taskStatus = normalizeTaskStatus((string) ($taskRow['status'] ?? 'todo'), $workspaceId);

                $currentDescription = trim((string) ($taskRow['description'] ?? ''));
                if ($currentDescription === '') {
                    throw new RuntimeException('A tarefa não possui uma solicitação ativa para remover.');
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
                    throw new RuntimeException('Não há solicitação de ajuste ativa para remover.');
                }

                $restoredDescription = trim((string) ($activeRevision['previous_description'] ?? ''));
                if ($restoredDescription === '') {
                    throw new RuntimeException('Não foi possível restaurar a descrição anterior.');
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

                flash('success', 'Solicitação de ajuste removida.');
                redirectTo('index.php#task-' . $taskId);

            case 'move_task':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
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
                    throw new RuntimeException('Tarefa inválida.');
                }
                $existingTaskGroupName = normalizeTaskGroupName((string) ($existingTaskRow['group_name'] ?? 'Geral'));
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $existingTaskGroupName)) {
                    throw new RuntimeException('Você não possui acesso para atualizar esta tarefa.');
                }

                $undoBeforeTask = taskUndoTaskSnapshot($pdo, $workspaceId, $taskId);
                $existingStatus = normalizeTaskStatus((string) ($existingTaskRow['status'] ?? 'todo'), $workspaceId);
                $existingOverdueFlag = ((int) ($existingTaskRow['overdue_flag'] ?? 0)) === 1 ? 1 : 0;
                $existingOverdueSinceDate = dueDateForStorage((string) ($existingTaskRow['overdue_since_date'] ?? ''));
                $status = normalizeTaskStatus((string) ($_POST['status'] ?? 'todo'), $workspaceId);
                $doneStatusKey = taskDoneStatusKey($workspaceId);
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
                    ':done' => $doneStatusKey,
                    ':u' => $updatedAt,
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);

                $statusOptions = taskStatuses($workspaceId);
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

                if (taskStatusKind($status, $workspaceId) === 'done' && $existingOverdueFlag === 1) {
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

                taskUndoPushOperation(
                    $workspaceId,
                    taskUndoBuildOperation(
                        'update',
                        $taskId,
                        $undoBeforeTask,
                        taskUndoTaskSnapshot($pdo, $workspaceId, $taskId)
                    )
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'task_id' => $taskId,
                        'status' => $status,
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                        'undo_state' => taskUndoState($workspaceId),
                    ]);
                }
                flash('success', 'Status atualizado.');
                redirectTo('index.php#task-' . $taskId);

            case 'load_task_detail':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
                }

                $taskStmt = $pdo->prepare(
                    'SELECT id, group_name, description, reference_links_json, reference_images_json, subtasks_json, subtasks_dependency_enabled, updated_at
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
                    throw new RuntimeException('Tarefa inválida.');
                }

                $taskGroupName = normalizeTaskGroupName((string) ($taskRow['group_name'] ?? 'Geral'));
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $taskGroupName)) {
                    throw new RuntimeException('Você não possui acesso a esta tarefa.');
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
                            'updated_at' => (string) ($taskRow['updated_at'] ?? ''),
                            'updated_at_label' => !empty($taskRow['updated_at'])
                                ? (new DateTimeImmutable((string) $taskRow['updated_at']))->format('d/m H:i')
                                : '',
                        ],
                    ]);
                }
                redirectTo('index.php#task-' . $taskId);

            case 'delete_task':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
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
                    throw new RuntimeException('Tarefa inválida.');
                }
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $taskGroupName)) {
                    throw new RuntimeException('Você não possui acesso para remover esta tarefa.');
                }
                $undoBeforeTask = taskUndoTaskSnapshot($pdo, $workspaceId, $taskId);
                $stmt = $pdo->prepare(
                    'DELETE FROM tasks
                     WHERE id = :id
                       AND workspace_id = :workspace_id'
                );
                $stmt->execute([
                    ':id' => $taskId,
                    ':workspace_id' => $workspaceId,
                ]);
                taskUndoPushOperation(
                    $workspaceId,
                    taskUndoBuildOperation('delete', $taskId, $undoBeforeTask, null)
                );
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'task_id' => $taskId,
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                        'undo_state' => taskUndoState($workspaceId),
                    ]);
                }
                flash('success', 'Tarefa removida.');
                redirectTo('index.php#tasks');

    }

    return in_array($action, [
        'task_undo',
        'task_redo',
        'create_task',
        'update_task',
        'add_task_title_tag_option',
        'remove_task_title_tag_option',
        'set_task_title_tag_color',
        'request_task_revision',
        'remove_task_revision',
        'move_task',
        'load_task_detail',
        'delete_task',
    ], true);
}
