<?php
declare(strict_types=1);

function renderCreateTaskGroupOptionsHtml(array $taskGroupsWithAccess): string
{
    ob_start();
    if (!$taskGroupsWithAccess) {
        echo '<option value="">Sem grupo com acesso</option>';
    } else {
        foreach ($taskGroupsWithAccess as $groupNameOption) {
            $groupNameOption = normalizeTaskGroupName((string) $groupNameOption);
            echo '<option value="' . e((string) $groupNameOption) . '">'
                . e((string) $groupNameOption)
                . '</option>';
        }
    }

    return (string) ob_get_clean();
}

function respondTaskPanelSnapshot(): void
{
    $currentUser = currentUser();
    if (!$currentUser) {
        respondJson([
            'ok' => false,
            'error' => 'Sessão expirada. Faça login novamente.',
        ], 401);
    }

    $currentWorkspaceId = activeWorkspaceId($currentUser);
    if ($currentWorkspaceId === null) {
        throw new RuntimeException('Workspace ativo não encontrado.');
    }

    if (shouldApplyOverduePolicyDuringRequests()) {
        applyOverdueTaskPolicyIfNeeded($currentWorkspaceId);
    }

    $currentWorkspace = workspaceById($currentWorkspaceId);
    $isPersonalWorkspace = !empty($currentWorkspace['is_personal']);
    $statusConfig = taskStatusConfig($currentWorkspaceId, $currentWorkspace);
    $statusOptions = $statusConfig['options'];
    $defaultTaskStatusKey = (string) ($statusConfig['todo_status_key'] ?? 'todo');
    $defaultTaskStatusMeta = $statusConfig['meta_by_key'][$defaultTaskStatusKey] ?? taskStatusMeta($defaultTaskStatusKey);
    $defaultTaskStatusLabel = (string) ($defaultTaskStatusMeta['label'] ?? 'A fazer');
    $defaultTaskStatusKind = (string) ($defaultTaskStatusMeta['kind'] ?? 'todo');
    $defaultTaskStatusColor = (string) ($defaultTaskStatusMeta['color'] ?? taskStatusDefaultColorForKind($defaultTaskStatusKind));
    $defaultTaskStatusCssVars = (string) ($defaultTaskStatusMeta['css_vars'] ?? taskStatusCssVars($defaultTaskStatusColor));
    $reviewTaskStatusKey = $statusConfig['review_status_key'] ?? null;
    $priorityOptions = taskPriorities();
    $users = usersList($currentWorkspaceId);
    $canManageWorkspace = userCanManageWorkspace((int) $currentUser['id'], $currentWorkspaceId);
    $taskGroupsAll = taskGroupsList($currentWorkspaceId);

    $taskGroupPermissions = [];
    $taskGroups = [];
    $taskGroupsWithAccess = [];
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
    }

    $taskTitleTagColors = taskTitleTagColorsByWorkspace($currentWorkspaceId);
    $groupFilter = isset($_GET['group']) && trim((string) $_GET['group']) !== ''
        ? normalizeTaskGroupName((string) $_GET['group'])
        : null;
    if ($groupFilter !== null && !in_array($groupFilter, $taskGroups, true)) {
        $groupFilter = null;
    }

    $creatorFilterRaw = $_GET['created_by'] ?? null;
    $creatorFilterId = isset($creatorFilterRaw) ? (int) $creatorFilterRaw : null;
    $creatorFilterId = $creatorFilterId && $creatorFilterId > 0 ? $creatorFilterId : null;
    $assigneeFilterRaw = $_GET['assignee'] ?? null;
    $assigneeFilterId = isset($assigneeFilterRaw) ? (int) $assigneeFilterRaw : null;
    $assigneeFilterId = $assigneeFilterId && $assigneeFilterId > 0 ? $assigneeFilterId : null;
    $workspaceUserIds = array_map(
        static fn (array $user): int => (int) ($user['id'] ?? 0),
        is_array($users) ? $users : []
    );
    if ($creatorFilterId !== null && !in_array($creatorFilterId, $workspaceUserIds, true)) {
        $creatorFilterId = null;
    }
    if ($assigneeFilterId !== null && !in_array($assigneeFilterId, $workspaceUserIds, true)) {
        $assigneeFilterId = null;
    }

    $taskVisibleKeys = [];
    foreach ($taskGroups as $taskGroupName) {
        $taskVisibleKeys[mb_strtolower(normalizeTaskGroupName($taskGroupName))] = true;
    }

    $allTasks = allTasks($currentWorkspaceId);
    $allTasks = array_values(array_filter(
        $allTasks,
        static function (array $task) use ($taskVisibleKeys): bool {
            $groupKey = mb_strtolower(normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral')));
            return isset($taskVisibleKeys[$groupKey]);
        }
    ));
    $tasks = filterTasks($allTasks, $groupFilter, $creatorFilterId, $assigneeFilterId);
    $showEmptyGroups = $groupFilter === null && $creatorFilterId === null && $assigneeFilterId === null;
    $groupingSource = null;
    if ($showEmptyGroups) {
        $groupingSource = $taskGroups;
    } elseif ($groupFilter !== null) {
        $groupingSource = [$groupFilter];
    }

    $tasksGroupedByGroup = tasksByGroup($tasks, $groupingSource);
    $stats = dashboardStats($allTasks);
    $myOpenTasks = countMyAssignedTasks($allTasks, (int) $currentUser['id']);
    $completionRate = $stats['total'] > 0 ? (int) round(($stats['done'] / $stats['total']) * 100) : 0;

    ob_start();
    include __DIR__ . '/../partials/tasks_panel.php';
    $tasksPanelHtml = (string) ob_get_clean();

    respondJson([
        'ok' => true,
        'tasks_panel_html' => $tasksPanelHtml,
        'create_task_group_options_html' => renderCreateTaskGroupOptionsHtml($taskGroupsWithAccess),
        'has_task_group_access' => !empty($taskGroupsWithAccess),
        'undo_state' => taskUndoState($currentWorkspaceId),
        'summary' => [
            'total' => (int) $stats['total'],
            'done' => (int) $stats['done'],
            'completion_rate' => $completionRate,
            'due_today' => (int) $stats['due_today'],
            'urgent' => (int) $stats['urgent'],
            'my_open' => (int) $myOpenTasks,
        ],
    ]);
}
