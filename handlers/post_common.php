<?php
declare(strict_types=1);

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
    if (
        $workspaceId !== null &&
        $workspaceId > 0 &&
        shouldApplyOverduePolicyDuringRequests()
    ) {
        applyOverdueTaskPolicyIfNeeded($workspaceId);
    }

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

    $creatorRaw = $get['created_by'] ?? null;
    if (($creatorRaw === null || trim((string) $creatorRaw) === '') && isset($post['redirect_created_by'])) {
        $creatorRaw = $post['redirect_created_by'];
    }
    $assigneeRaw = $get['assignee'] ?? null;
    if (($assigneeRaw === null || trim((string) $assigneeRaw) === '') && isset($post['redirect_assignee'])) {
        $assigneeRaw = $post['redirect_assignee'];
    }

    $params = [];
    if ($groupRaw !== null) {
        $params['group'] = normalizeTaskGroupName($groupRaw);
    }

    $creatorId = isset($creatorRaw) ? (int) $creatorRaw : 0;
    if ($creatorId > 0) {
        $params['created_by'] = (string) $creatorId;
    }
    $assigneeId = isset($assigneeRaw) ? (int) $assigneeRaw : 0;
    if ($assigneeId > 0) {
        $params['assignee'] = (string) $assigneeId;
    }

    $query = http_build_query($params);
    return $query !== '' ? appPath('?' . $query . '#tasks') : appPath('#tasks');
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

    return appPath('?' . $query . '#accounting');
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
