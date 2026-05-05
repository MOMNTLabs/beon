<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (requestShouldRedirectToConfiguredAppHost()) {
    header('Location: ' . appUrl(currentRequestQuerySuffix()));
    exit;
}

if (requestShouldServePublicHomeFromIndex()) {
    require __DIR__ . '/home.php';
    return;
}

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
require_once __DIR__ . '/handlers/task_snapshot.php';
require_once __DIR__ . '/handlers/section_snapshot.php';
require_once __DIR__ . '/handlers/post_auth.php';
require_once __DIR__ . '/handlers/post_workspace.php';
require_once __DIR__ . '/handlers/post_tasks.php';
require_once __DIR__ . '/handlers/post_vault.php';
require_once __DIR__ . '/handlers/post_dues.php';
require_once __DIR__ . '/handlers/post_inventory.php';
require_once __DIR__ . '/handlers/post_accounting.php';
require_once __DIR__ . '/handlers/post_task_groups.php';
require_once __DIR__ . '/handlers/dashboard_overview.php';

$forceAuthScreen = false;
$authInitialPanel = 'login';
$passwordResetRequest = null;
$authRedirectPath = safeRedirectPath((string) (($_GET['next'] ?? $_POST['next'] ?? '')), appDefaultAfterLoginPath());
$workspaceInviteRequest = validWorkspaceEmailInvitationRequestFromPath($authRedirectPath);
$authAllowsDirectRegister = str_starts_with($authRedirectPath, 'home?action=checkout')
    || (!empty($workspaceInviteRequest) && (int) ($workspaceInviteRequest['existing_user_id'] ?? 0) <= 0);
$requestedAuthPanel = trim((string) ($_GET['auth'] ?? ''));
if (in_array($requestedAuthPanel, ['login', 'register', 'forgot-password', 'reset-password'], true)) {
    $authInitialPanel = $requestedAuthPanel === 'register' && !$authAllowsDirectRegister
        ? 'login'
        : $requestedAuthPanel;
    $forceAuthScreen = true;
}

$entryAction = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
$billingGateBypassActions = [
    'login',
    'register',
    'logout',
    'request_password_reset',
    'perform_password_reset',
    'reset_password',
    'workspace_invite',
];
$shouldBypassBillingGate = $forceAuthScreen || in_array($entryAction, $billingGateBypassActions, true);

$entryUser = currentUser();
if (
    $entryUser
    && !$shouldBypassBillingGate
    && envFlag('APP_ENFORCE_BILLING', false)
    && !userHasAppAccess((int) ($entryUser['id'] ?? 0))
) {
    $pendingCheckoutUserId = (int) ($entryUser['id'] ?? 0);
    logoutUser();
    setPendingCheckoutUserId($pendingCheckoutUserId);
    getFlashes();
    redirectTo(siteUrl('home?checkout=required#planos'));
}
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && !$entryUser
    && !$forceAuthScreen
    && $entryAction === ''
) {
    redirectTo(siteUrl('home'));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $getAction = trim((string) ($_GET['action'] ?? ''));

    if ($getAction === 'reset_password') {
        $selector = trim((string) ($_GET['selector'] ?? ''));
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($selector === '' || $token === '') {
            flash('error', 'Link de redefinição inválido.');
            redirectTo(appUrl('?auth=forgot-password#forgot-password'));
        }

        $passwordResetRequest = validPasswordResetRequest($selector, $token);
        if (!$passwordResetRequest) {
            flash('error', 'Este link de redefinição e inválido ou expirou.');
            redirectTo(appUrl('?auth=forgot-password#forgot-password'));
        }

        $passwordResetRequest['selector'] = $selector;
        $passwordResetRequest['token'] = $token;
        $authInitialPanel = 'reset-password';
        $forceAuthScreen = true;
    }

    if ($getAction === 'workspace_invite') {
        $selector = trim((string) ($_GET['selector'] ?? ''));
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($selector === '' || $token === '') {
            flash('error', 'Link de convite invalido.');
            redirectTo(appUrl('?auth=login#login'));
        }

        $workspaceInviteRequest = validWorkspaceEmailInvitationRequest($selector, $token);
        if (!$workspaceInviteRequest) {
            flash('error', 'Este link de convite e invalido ou expirou.');
            redirectTo(appUrl('?auth=login#login'));
        }

        $workspaceInviteRequest['selector'] = $selector;
        $workspaceInviteRequest['token'] = $token;
        $workspaceInviteRequest['path'] = workspaceInvitePath($selector, $token, false);
        $authRedirectPath = (string) $workspaceInviteRequest['path'];
        $authAllowsDirectRegister = (int) ($workspaceInviteRequest['existing_user_id'] ?? 0) <= 0;

        $invitedEmail = strtolower(trim((string) ($workspaceInviteRequest['invited_email'] ?? '')));
        if ($entryUser) {
            $entryUserEmail = strtolower(trim((string) ($entryUser['email'] ?? '')));
            if ($invitedEmail === '' || $entryUserEmail !== $invitedEmail) {
                logoutUser();
                flash('error', 'Este convite foi enviado para ' . $invitedEmail . '. Entre com essa conta para continuar.');
                redirectTo(authErrorRedirectPath('login', $authRedirectPath));
            }

            try {
                $acceptedWorkspaceId = acceptWorkspaceEmailInvitation($pdo, $selector, $token, (int) ($entryUser['id'] ?? 0));
                setActiveWorkspaceId($acceptedWorkspaceId);
                flash('success', 'Convite aceito. Voce entrou no workspace.');
                redirectTo(dashboardPath('users'));
            } catch (Throwable $e) {
                if (!userHasAppAccess((int) ($entryUser['id'] ?? 0))) {
                    logoutUser();
                }
                flash('error', $e->getMessage());
                redirectTo(authErrorRedirectPath($authAllowsDirectRegister ? 'register' : 'login', $authRedirectPath));
            }
        }

        $authInitialPanel = $authAllowsDirectRegister ? 'register' : 'login';
        $forceAuthScreen = true;
    }

    if ($getAction === 'task_panel_snapshot') {
        try {
            respondTaskPanelSnapshot();
        } catch (Throwable $e) {
            respondJson([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    if ($getAction === 'vault_panel_snapshot') {
        try {
            respondVaultPanelSnapshot();
        } catch (Throwable $e) {
            respondJson([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    if ($getAction === 'due_panel_snapshot') {
        try {
            respondDuePanelSnapshot();
        } catch (Throwable $e) {
            respondJson([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    if ($getAction === 'inventory_panel_snapshot') {
        try {
            respondInventoryPanelSnapshot();
        } catch (Throwable $e) {
            respondJson([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    if ($getAction === 'accounting_panel_snapshot') {
        try {
            respondAccountingPanelSnapshot();
        } catch (Throwable $e) {
            respondJson([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    if ($getAction === 'users_panel_snapshot') {
        try {
            respondUsersPanelSnapshot();
        } catch (Throwable $e) {
            respondJson([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    if ($getAction === 'task_notifications_feed') {
        try {
            $authUser = currentUser();
            if (!$authUser) {
                respondJson([
                    'ok' => false,
                    'error' => 'Sessão expirada. Faça login novamente.',
                ], 401);
            }

            $workspaceId = activeWorkspaceId($authUser);
            if ($workspaceId === null) {
                throw new RuntimeException('Workspace ativo não encontrado.');
            }

            if (shouldApplyOverduePolicyDuringRequests()) {
                applyOverdueTaskPolicyIfNeeded($workspaceId);
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
    $redirectPathOnError = '';

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
if ($currentUser && $currentWorkspaceId !== null && shouldApplyOverduePolicyDuringRequests()) {
    applyOverdueTaskPolicyIfNeeded($currentWorkspaceId);
}
$userWorkspaces = $currentUser ? workspacesForUser((int) $currentUser['id']) : [];
$flashes = getFlashes();
$statusConfig = ($currentUser && $currentWorkspaceId !== null)
    ? taskStatusConfig($currentWorkspaceId, $currentWorkspace)
    : taskStatusConfig();
$statusOptions = $statusConfig['options'];
$defaultTaskStatusKey = (string) ($statusConfig['todo_status_key'] ?? 'todo');
$defaultTaskStatusMeta = $statusConfig['meta_by_key'][$defaultTaskStatusKey] ?? taskStatusMeta($defaultTaskStatusKey);
$defaultTaskStatusLabel = (string) ($defaultTaskStatusMeta['label'] ?? 'A fazer');
$defaultTaskStatusKind = (string) ($defaultTaskStatusMeta['kind'] ?? 'todo');
$defaultTaskStatusColor = (string) ($defaultTaskStatusMeta['color'] ?? taskStatusDefaultColorForKind($defaultTaskStatusKind));
$defaultTaskStatusCssVars = (string) ($defaultTaskStatusMeta['css_vars'] ?? taskStatusCssVars($defaultTaskStatusColor));
$reviewTaskStatusKey = $statusConfig['review_status_key'] ?? null;
$priorityOptions = taskPriorities();
$users = ($currentUser && $currentWorkspaceId !== null) ? usersList($currentWorkspaceId) : [];
$workspaceMembers = ($currentUser && $currentWorkspaceId !== null) ? workspaceMembersList($currentWorkspaceId) : [];
$workspacePendingInvitations = ($currentUser && $currentWorkspaceId !== null)
    ? workspacePendingInvitationsForWorkspace($currentWorkspaceId)
    : [];
$workspacePendingEmailInvitations = ($currentUser && $currentWorkspaceId !== null)
    ? workspacePendingEmailInvitationsForWorkspace($currentWorkspaceId)
    : [];
$currentUserWorkspaceInvitations = $currentUser
    ? workspacePendingInvitationsForUser((int) $currentUser['id'])
    : [];
$canManageWorkspace = ($currentUser && $currentWorkspaceId !== null)
    ? userCanManageWorkspace((int) $currentUser['id'], $currentWorkspaceId)
    : false;
$isPersonalWorkspace = !empty($currentWorkspace['is_personal']);
$showUsersDashboardTab = true;
$workspaceSidebarConfig = ($currentUser && $currentWorkspaceId !== null)
    ? workspaceSidebarToolsConfig($currentWorkspaceId, $currentWorkspace)
    : workspaceSidebarToolsConfig();
$workspaceEnabledViews = ($currentUser && $currentWorkspaceId !== null)
    ? workspaceEnabledDashboardViews($currentWorkspaceId, $currentWorkspace, !empty($showUsersDashboardTab))
    : [];
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
    $taskPermissionsByGroupMap = [];
    $vaultPermissionsByGroupMap = [];
    $duePermissionsByGroupMap = [];
    if ($canManageWorkspace) {
        $taskPermissionsByGroupMap = taskGroupPermissionsByUserMapByGroup($currentWorkspaceId);
        $vaultPermissionsByGroupMap = vaultGroupPermissionsByUserMapByGroup($currentWorkspaceId);
        $duePermissionsByGroupMap = dueGroupPermissionsByUserMapByGroup($currentWorkspaceId);
    }

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
            $taskGroupPermissionsByUserMap[$taskGroupName] = $taskPermissionsByGroupMap[$taskGroupName] ?? [];
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
            $vaultGroupPermissionsByUserMap[$vaultGroupName] = $vaultPermissionsByGroupMap[$vaultGroupName] ?? [];
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
            $dueGroupPermissionsByUserMap[$dueGroupName] = $duePermissionsByGroupMap[$dueGroupName] ?? [];
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
$themeBexonAssetVersion = is_file(__DIR__ . '/assets/theme-bexon.css')
    ? (string) filemtime(__DIR__ . '/assets/theme-bexon.css')
    : '1';
$appAssetVersion = is_file(__DIR__ . '/assets/app.js')
    ? (string) filemtime(__DIR__ . '/assets/app.js')
    : '1';
$loadingAssetVersion = is_file(__DIR__ . '/assets/loading.js')
    ? (string) filemtime(__DIR__ . '/assets/loading.js')
    : '1';
$complianceAssetVersion = assetVersion('assets/compliance.js');
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
$allTasks = ($currentUser && $currentWorkspaceId !== null) ? allTasks($currentWorkspaceId) : [];
$allTasks = array_values(array_filter(
    $allTasks,
    static function (array $task) use ($taskVisibleKeys): bool {
        $groupKey = mb_strtolower(normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral')));
        return isset($taskVisibleKeys[$groupKey]);
    }
));
$tasks = $currentUser ? filterTasks($allTasks, $groupFilter, $creatorFilterId, $assigneeFilterId) : [];
$showEmptyGroups = $currentUser
    && $groupFilter === null
    && $creatorFilterId === null
    && $assigneeFilterId === null;
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

$globalDashboardOverview = buildGlobalDashboardOverview($currentUser, $userWorkspaces);
$overviewStats = $currentUser ? [
    'total' => (int) ($globalDashboardOverview['user_task_total'] ?? 0),
    'done' => (int) ($globalDashboardOverview['user_task_done_total'] ?? 0),
    'open' => (int) ($globalDashboardOverview['user_open_task_total'] ?? 0),
    'due_today' => (int) ($globalDashboardOverview['tasks_today_total'] ?? 0),
    'urgent_today' => (int) ($globalDashboardOverview['urgent_tasks_today_total'] ?? 0),
] : ['total' => 0, 'done' => 0, 'open' => 0, 'due_today' => 0, 'urgent_today' => 0];
$overviewCompletionRate = $overviewStats['total'] > 0
    ? (int) round(($overviewStats['done'] / $overviewStats['total']) * 100)
    : 0;

$defaultTaskGroupName = $taskGroups[0] ?? 'Geral';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="assets/Bexon---Logo-Symbol.png?v=1">
    <link rel="shortcut icon" href="assets/Bexon---Logo-Symbol.png?v=1">
    <link rel="preçonnect" href="https://fonts.googleapis.com">
    <link rel="preçonnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="assets/styles.css?v=<?= e($stylesAssetVersion) ?>">
    <link rel="stylesheet" href="assets/theme-bexon.css?v=<?= e($themeBexonAssetVersion) ?>">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js" defer></script>
    <script src="assets/compliance.js?v=<?= e($complianceAssetVersion) ?>" defer></script>
    <script src="assets/loading.js?v=<?= e($loadingAssetVersion) ?>" defer></script>
    <script src="assets/app.js?v=<?= e($appAssetVersion) ?>" defer></script>
</head>
<body
    class="<?= $renderAuthScreen ? 'is-auth' : 'is-dashboard' ?>"
    data-default-group-name="<?= e((string) $defaultTaskGroupName) ?>"
    data-workspace-id="<?= e((string) ($renderAuthScreen ? '' : ($currentWorkspaceId ?? ''))) ?>"
    data-user-id="<?= e((string) ($renderAuthScreen ? '' : ($currentUser['id'] ?? ''))) ?>"
    data-workspace-enabled-views="<?= e((string) ($renderAuthScreen ? '' : implode(',', $workspaceEnabledViews))) ?>"
>
    <div class="bg-layer bg-layer-one" aria-hidden="true"></div>
    <div class="bg-layer bg-layer-two" aria-hidden="true"></div>
    <div class="grain" aria-hidden="true"></div>

    <div class="app-shell">
        <?php if ($flashes && !$renderAuthScreen): ?>
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

