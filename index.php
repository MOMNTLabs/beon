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

set_exception_handler(static function (Throwable $e): void {
    error_log(sprintf(
        'Unhandled app error [host=%s uri=%s]: %s',
        (string) ($_SERVER['HTTP_HOST'] ?? 'unknown'),
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        $e->getMessage()
    ));

    if (function_exists('requestExpectsJson') && requestExpectsJson()) {
        respondJson([
            'ok' => false,
            'error' => 'Nao foi possivel carregar o app agora.',
        ], 500);
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $homeUrl = e(siteUrl('home'));
    $loginUrl = e(appUrl('?auth=login#login'));
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . e(APP_NAME) . '</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f7f8fb;color:#263241;font-family:Arial,sans-serif}.error-card{width:min(520px,calc(100% - 32px));padding:28px;border:1px solid #d9e1ec;border-radius:8px;background:#fff;box-shadow:0 18px 42px rgba(39,54,78,.12)}h1{margin:0 0 10px;font-size:22px}p{margin:0 0 18px;line-height:1.5}.actions{display:flex;gap:10px;flex-wrap:wrap}a{display:inline-flex;align-items:center;min-height:40px;padding:0 14px;border-radius:6px;background:#263241;color:#fff;text-decoration:none}a.secondary{background:#eef2f7;color:#263241}</style></head><body><main class="error-card"><h1>Nao foi possivel carregar o app agora.</h1><p>O erro foi registrado no servidor. Tente atualizar a pagina; se continuar, entre novamente.</p><div class="actions"><a href="' . $loginUrl . '">Entrar novamente</a><a class="secondary" href="' . $homeUrl . '">Voltar para o site</a></div></main></body></html>';
    exit;
});

require_once __DIR__ . '/handlers/task_snapshot.php';
require_once __DIR__ . '/handlers/section_snapshot.php';
require_once __DIR__ . '/handlers/post_auth.php';
require_once __DIR__ . '/handlers/post_workspace.php';
require_once __DIR__ . '/handlers/post_google_drive.php';
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
$authAllowsDirectRegister = authAllowsDirectRegisterForRedirectPath($authRedirectPath);
$authRegisterRedirectPath = !empty($workspaceInviteRequest) || str_starts_with($authRedirectPath, 'home?action=checkout')
    ? $authRedirectPath
    : appPlansPath();
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
    'google_auth',
    'google_callback',
    'google_drive_auth',
    'google_drive_callback',
    'google_drive_download',
    'plans',
];
$shouldBypassBillingGate = $forceAuthScreen || in_array($entryAction, $billingGateBypassActions, true);

$entryUser = currentUser();
if (
    $entryUser
    && !$shouldBypassBillingGate
    && envFlag('APP_ENFORCE_BILLING', false)
    && !userHasAppAccess((int) ($entryUser['id'] ?? 0))
) {
    flash('success', 'Escolha um plano para liberar o acesso ao app.');
    redirectTo(appPlansPath());
}
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && !$entryUser
    && !$forceAuthScreen
    && ($entryAction === '' || $entryAction === 'plans')
) {
    if ($entryAction === 'plans') {
        redirectTo(appUrl('?auth=login&next=' . urlencode(appPlansPath()) . '#login'));
    }

    redirectTo(siteUrl('home'));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $getAction = trim((string) ($_GET['action'] ?? ''));

    if ($getAction === 'user_avatar') {
        respondUserAvatarImage();
    }

    if ($getAction === 'workspace_avatar') {
        respondWorkspaceAvatarImage();
    }

    if ($getAction === 'google_auth') {
        handleGoogleOAuthStart($pdo);
    }

    if ($getAction === 'google_callback') {
        handleGoogleOAuthCallback($pdo);
    }

    if ($getAction === 'google_drive_auth') {
        handleGoogleDriveOAuthStart($pdo);
    }

    if ($getAction === 'google_drive_callback') {
        handleGoogleDriveOAuthCallback($pdo);
    }

    if ($getAction === 'google_drive_download') {
        handleGoogleDriveDownload($pdo);
    }

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
                if (handleGoogleDrivePostAction($pdo, $action)) {
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
$renderPlansScreen = $currentUser && !$forceAuthScreen && $entryAction === 'plans';
$renderAuthScreen = (!$currentUser || $forceAuthScreen) && !$renderPlansScreen;
$currentWorkspaceId = $currentUser ? activeWorkspaceId($currentUser) : null;
$currentWorkspace = ($currentUser && $currentWorkspaceId !== null) ? activeWorkspace($currentUser) : null;
if ($currentUser && $currentWorkspaceId !== null && shouldApplyOverduePolicyDuringRequests()) {
    applyOverdueTaskPolicyIfNeeded($currentWorkspaceId);
}
$userWorkspaces = $currentUser ? workspacesForUser((int) $currentUser['id']) : [];
$flashes = getFlashes();
$pendingGoogleRegistration = pendingGoogleRegistration();
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

$dashboardLoadErrorMessages = [];
$appendDashboardLoadError = static function (string $message, Throwable $e) use (
    &$flashes,
    &$dashboardLoadErrorMessages,
    $currentUser,
    $currentWorkspaceId
): void {
    $normalizedMessage = trim($message);
    if ($normalizedMessage !== '' && !in_array($normalizedMessage, $dashboardLoadErrorMessages, true)) {
        $dashboardLoadErrorMessages[] = $normalizedMessage;
        $flashes[] = [
            'type' => 'error',
            'message' => $normalizedMessage,
        ];
    }

    error_log(sprintf(
        'Dashboard load error [user=%d workspace=%s host=%s uri=%s]: %s',
        (int) ($currentUser['id'] ?? 0),
        $currentWorkspaceId !== null ? (string) $currentWorkspaceId : 'none',
        (string) ($_SERVER['HTTP_HOST'] ?? 'unknown'),
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
        $e->getMessage()
    ));
};

$vaultVisibleKeys = [];
foreach ($vaultGroups as $vaultGroupName) {
    $vaultVisibleKeys[mb_strtolower(normalizeVaultGroupName($vaultGroupName))] = true;
}
$vaultEntries = [];
if ($currentUser && $currentWorkspaceId !== null) {
    try {
        $vaultEntries = workspaceVaultEntriesList($currentWorkspaceId);
        $vaultEntries = array_values(array_filter(
            $vaultEntries,
            static function (array $entry) use ($vaultVisibleKeys): bool {
                $groupKey = mb_strtolower(normalizeVaultGroupName((string) ($entry['group_name'] ?? 'Geral')));
                return isset($vaultVisibleKeys[$groupKey]);
            }
        ));
    } catch (Throwable $e) {
        $appendDashboardLoadError('Nao foi possivel carregar o cofre deste workspace.', $e);
    }
}
$vaultEntriesByGroup = $currentUser ? vaultEntriesByGroup($vaultEntries, $vaultGroups) : [];

$dueVisibleKeys = [];
foreach ($dueGroups as $dueGroupName) {
    $dueVisibleKeys[mb_strtolower(normalizeDueGroupName($dueGroupName))] = true;
}
$dueEntries = [];
if ($currentUser && $currentWorkspaceId !== null) {
    try {
        $dueEntries = workspaceDueEntriesList($currentWorkspaceId);
        $dueEntries = array_values(array_filter(
            $dueEntries,
            static function (array $entry) use ($dueVisibleKeys): bool {
                $groupKey = mb_strtolower(normalizeDueGroupName((string) ($entry['group_name'] ?? 'Geral')));
                return isset($dueVisibleKeys[$groupKey]);
            }
        ));
    } catch (Throwable $e) {
        $appendDashboardLoadError('Nao foi possivel carregar os vencimentos deste workspace.', $e);
    }
}
$dueEntriesByGroup = $currentUser ? dueEntriesByGroup($dueEntries, $dueGroups) : [];
$inventoryEntries = [];
if ($currentUser && $currentWorkspaceId !== null) {
    try {
        $inventoryEntries = workspaceInventoryEntriesList($currentWorkspaceId);
    } catch (Throwable $e) {
        $appendDashboardLoadError('Nao foi possivel carregar o estoque deste workspace.', $e);
    }
}
$inventoryEntriesByGroup = $currentUser ? inventoryEntriesByGroup($inventoryEntries, $inventoryGroups) : [];
$accountingPeriod = normalizeAccountingPeriodKey((string) ($_GET['accounting_period'] ?? ''));
$accountingPeriodLabel = accountingMonthLabel($accountingPeriod);
$accountingPeriodDate = DateTimeImmutable::createFromFormat('!Y-m', $accountingPeriod) ?: new DateTimeImmutable('first day of this month');
$accountingPreviousPeriod = $accountingPeriodDate->modify('-1 month')->format('Y-m');
$accountingNextPeriod = $accountingPeriodDate->modify('+1 month')->format('Y-m');
$accountingPreviousPeriodPath = accountingRedirectPathFromRequest(['accounting_period' => $accountingPreviousPeriod], []);
$accountingNextPeriodPath = accountingRedirectPathFromRequest(['accounting_period' => $accountingNextPeriod], []);
$accountingEntries = [];
if ($currentUser && $currentWorkspaceId !== null) {
    try {
        $accountingEntries = workspaceAccountingEntriesList($currentWorkspaceId, $accountingPeriod);
    } catch (Throwable $e) {
        $appendDashboardLoadError('Nao foi possivel carregar a contabilidade deste workspace.', $e);
    }
}
$accountingEntriesByType = workspaceAccountingEntriesByType($accountingEntries);
$accountingExpenseEntries = $accountingEntriesByType['expense'] ?? [];
$accountingIncomeEntries = $accountingEntriesByType['income'] ?? [];
$accountingOpeningBalanceCents = 0;
if ($currentUser && $currentWorkspaceId !== null) {
    try {
        $accountingOpeningBalanceCents = workspaceAccountingOpeningBalanceCents($currentWorkspaceId, $accountingPeriod);
    } catch (Throwable $e) {
        $appendDashboardLoadError('Nao foi possivel carregar o saldo inicial da contabilidade.', $e);
    }
}
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
$allTasks = [];
$tasks = [];
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
$stats = ['total' => 0, 'done' => 0, 'due_today' => 0, 'urgent' => 0];
$myOpenTasks = 0;
$completionRate = 0;
if ($currentUser && $currentWorkspaceId !== null) {
    try {
        $allTasks = allTasks($currentWorkspaceId);
        $allTasks = array_values(array_filter(
            $allTasks,
            static function (array $task) use ($taskVisibleKeys): bool {
                $groupKey = mb_strtolower(normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral')));
                return isset($taskVisibleKeys[$groupKey]);
            }
        ));
        $tasks = filterTasks($allTasks, $groupFilter, $creatorFilterId, $assigneeFilterId);
        $tasksGroupedByGroup = tasksByGroup($tasks, $groupingSource);
        $stats = dashboardStats($allTasks);
        $myOpenTasks = countMyAssignedTasks($allTasks, (int) $currentUser['id']);
        $completionRate = $stats['total'] > 0 ? (int) round(($stats['done'] / $stats['total']) * 100) : 0;
    } catch (Throwable $e) {
        $appendDashboardLoadError('Nao foi possivel carregar as tarefas deste workspace.', $e);
    }
}

$globalDashboardOverview = buildGlobalDashboardOverview(null, []);
if ($currentUser) {
    try {
        $globalDashboardOverview = buildGlobalDashboardOverview($currentUser, $userWorkspaces);
    } catch (Throwable $e) {
        $appendDashboardLoadError('Nao foi possivel carregar o dashboard geral agora.', $e);
    }
}
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
    <script>
        (() => {
            const authHashes = new Set(["app", "login", "register", "forgot-password", "reset-password"]);
            const currentHash = String(window.location.hash || "").replace(/^#/, "");
            const params = new URLSearchParams(window.location.search);
            if (authHashes.has(currentHash) && !params.has("auth") && !params.has("action")) {
                window.history?.replaceState?.(null, "", window.location.pathname + window.location.search);
            }
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js" defer></script>
    <script src="assets/compliance.js?v=<?= e($complianceAssetVersion) ?>" defer></script>
    <script src="assets/loading.js?v=<?= e($loadingAssetVersion) ?>" defer></script>
    <script src="assets/app.js?v=<?= e($appAssetVersion) ?>" defer></script>
</head>
<body
    class="<?= $renderAuthScreen ? 'is-auth' : ($renderPlansScreen ? 'is-plans' : 'is-dashboard') ?>"
    data-default-group-name="<?= e((string) $defaultTaskGroupName) ?>"
    data-workspace-id="<?= e((string) (($renderAuthScreen || $renderPlansScreen) ? '' : ($currentWorkspaceId ?? ''))) ?>"
    data-user-id="<?= e((string) ($renderAuthScreen ? '' : ($currentUser['id'] ?? ''))) ?>"
    data-workspace-enabled-views="<?= e((string) (($renderAuthScreen || $renderPlansScreen) ? '' : implode(',', $workspaceEnabledViews))) ?>"
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
        <?php elseif ($renderPlansScreen): ?>
            <?php include __DIR__ . '/partials/plans.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/partials/dashboard.php'; ?>
        <?php endif; ?>
    </div>
</body>
</html>

