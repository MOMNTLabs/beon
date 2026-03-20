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

require_once __DIR__ . '/handlers/post_common.php';
require_once __DIR__ . '/handlers/post_auth.php';
require_once __DIR__ . '/handlers/post_workspace.php';
require_once __DIR__ . '/handlers/post_tasks.php';
require_once __DIR__ . '/handlers/post_vault.php';
require_once __DIR__ . '/handlers/post_dues.php';
require_once __DIR__ . '/handlers/post_inventory.php';
require_once __DIR__ . '/handlers/post_accounting.php';
require_once __DIR__ . '/handlers/post_task_groups.php';

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

            applyOverdueTaskPolicyIfNeeded($workspaceId);

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

            default:
                if (handleAuthPostAction($pdo, $action, $redirectPathOnError)) {
                    break;
                }
                if (handleWorkspacePostAction($pdo, $action)) {
                    break;
                }
                if (handleTaskPostAction($pdo, $action)) {
                    break;
                }
                if (handleVaultPostAction($pdo, $action)) {
                    break;
                }
                if (handleDuePostAction($pdo, $action)) {
                    break;
                }
                if (handleInventoryPostAction($pdo, $action)) {
                    break;
                }
                if (handleAccountingPostAction($pdo, $action)) {
                    break;
                }
                if (handleTaskGroupPostAction($pdo, $action)) {
                    break;
                }
                throw new RuntimeException('Acao invalida.');
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
if ($currentUser && $currentWorkspaceId !== null) {
    applyOverdueTaskPolicyIfNeeded($currentWorkspaceId);
}
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
