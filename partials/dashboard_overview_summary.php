<?php
$overviewDueSoonTotal = (int) ($globalDashboardOverview['due_soon_total'] ?? 0);
$overviewTasksTodayTotal = (int) ($globalDashboardOverview['tasks_today_total'] ?? 0);
$overviewTasksTomorrowTotal = (int) ($globalDashboardOverview['tasks_tomorrow_total'] ?? 0);
$overviewUrgentTasksTodayTotal = (int) ($globalDashboardOverview['urgent_tasks_today_total'] ?? 0);
$overviewLowStockTotal = (int) ($globalDashboardOverview['low_stock_total'] ?? 0);
$overviewPriorityTasksTodayTotal = (int) ($globalDashboardOverview['priority_tasks_today_total'] ?? 0);
$overviewDueTodayTotal = (int) ($globalDashboardOverview['due_today_total'] ?? 0);
$overviewAttentionWorkspaceTotal = (int) ($globalDashboardOverview['attention_workspace_total'] ?? 0);
$overviewCriticalWorkspaceTotal = (int) ($globalDashboardOverview['critical_workspace_total'] ?? 0);
$overviewOpenTaskTotal = (int) ($globalDashboardOverview['user_open_task_total'] ?? 0);
$overviewTasksToday = array_values((array) ($globalDashboardOverview['tasks_today'] ?? []));
$overviewTasksTomorrow = array_values((array) ($globalDashboardOverview['tasks_tomorrow'] ?? []));
$overviewOpenTasks = array_values((array) ($globalDashboardOverview['open_tasks'] ?? []));
$overviewDueSoon = array_values((array) ($globalDashboardOverview['due_soon'] ?? []));
$overviewLowStock = array_values((array) ($globalDashboardOverview['low_stock'] ?? []));
$overviewWorkspaceSummaries = array_values((array) ($globalDashboardOverview['workspace_summaries'] ?? []));

$overviewWorkspacesById = [];
foreach ((array) ($userWorkspaces ?? []) as $overviewWorkspaceOption) {
    $overviewWorkspaceOptionId = (int) ($overviewWorkspaceOption['id'] ?? 0);
    if ($overviewWorkspaceOptionId > 0) {
        $overviewWorkspacesById[$overviewWorkspaceOptionId] = $overviewWorkspaceOption;
    }
}

$overviewWorkspaceForItem = static function (array $item) use ($overviewWorkspacesById): array {
    $workspaceId = (int) ($item['workspace_id'] ?? 0);
    if ($workspaceId > 0 && isset($overviewWorkspacesById[$workspaceId])) {
        return $overviewWorkspacesById[$workspaceId];
    }

    return [
        'id' => $workspaceId,
        'name' => trim((string) ($item['workspace_name'] ?? 'Workspace')),
    ];
};

$overviewPrimaryTasksWorkspaceId = (int) (
    $overviewTasksToday[0]['workspace_id']
    ?? $overviewTasksTomorrow[0]['workspace_id']
    ?? $overviewOpenTasks[0]['workspace_id']
    ?? 0
);
$overviewPrimaryDueWorkspaceId = (int) ($overviewDueSoon[0]['workspace_id'] ?? 0);
$overviewPrimaryInventoryWorkspaceId = (int) ($overviewLowStock[0]['workspace_id'] ?? 0);

$overviewListTitle = 'Minha prioridade agora';
if ($overviewUrgentTasksTodayTotal <= 0 && $overviewDueTodayTotal > 0) {
    $overviewListTitle = 'Atenção imediata';
} elseif ($overviewUrgentTasksTodayTotal <= 0 && $overviewLowStockTotal > 0) {
    $overviewListTitle = 'Itens em foco';
} elseif ($overviewUrgentTasksTodayTotal <= 0 && $overviewTasksTodayTotal <= 0 && $overviewTasksTomorrowTotal > 0) {
    $overviewListTitle = 'Próximos passos';
}

$overviewOpenActions = [];
if ($overviewTasksTodayTotal > 0) {
    $overviewOpenActions[] = [
        'view' => 'tasks',
        'label' => 'Ver tarefas',
        'workspace_id' => $overviewPrimaryTasksWorkspaceId,
    ];
}
if ($overviewTasksTomorrowTotal > 0 && $overviewTasksTodayTotal === 0) {
    $overviewOpenActions[] = [
        'view' => 'tasks',
        'label' => 'Ver amanhã',
        'workspace_id' => $overviewPrimaryTasksWorkspaceId,
    ];
}
if ($overviewDueSoonTotal > 0) {
    $overviewOpenActions[] = [
        'view' => 'dues',
        'label' => 'Ver vencimentos',
        'workspace_id' => $overviewPrimaryDueWorkspaceId,
    ];
}
if ($overviewLowStockTotal > 0) {
    $overviewOpenActions[] = [
        'view' => 'inventory',
        'label' => 'Ver estoque',
        'workspace_id' => $overviewPrimaryInventoryWorkspaceId,
    ];
}
if ($overviewOpenTaskTotal > 0 && $overviewTasksTodayTotal === 0 && $overviewTasksTomorrowTotal === 0) {
    $overviewOpenActions[] = [
        'view' => 'tasks',
        'label' => 'Ver tarefas',
        'workspace_id' => $overviewPrimaryTasksWorkspaceId,
    ];
}
$overviewPrimaryAction = $overviewOpenActions[0] ?? null;

$overviewAttentionLimit = 4;
$overviewAttentionItems = [];
$overviewSeenAttentionKeys = [];
$appendOverviewAttention = static function (array $item) use (&$overviewAttentionItems, &$overviewSeenAttentionKeys): void {
    $itemKey = trim((string) ($item['key'] ?? ''));
    if ($itemKey !== '' && isset($overviewSeenAttentionKeys[$itemKey])) {
        return;
    }

    if ($itemKey !== '') {
        $overviewSeenAttentionKeys[$itemKey] = true;
    }

    $overviewAttentionItems[] = $item;
};
$appendOverviewTask = static function (array $taskItem, string $kicker, string $tone) use ($appendOverviewAttention, $overviewWorkspaceForItem): void {
    $workspace = $overviewWorkspaceForItem($taskItem);
    $workspaceName = trim((string) ($workspace['name'] ?? $taskItem['workspace_name'] ?? 'Workspace'));
    $groupName = trim((string) ($taskItem['group_name'] ?? ''));
    $priorityKey = normalizeTaskPriority((string) ($taskItem['priority'] ?? 'medium'));
    $appendOverviewAttention([
        'key' => 'task:' . (string) ($taskItem['task_id'] ?? 0),
        'tone' => $tone,
        'kicker' => $kicker,
        'title' => trim((string) ($taskItem['title'] ?? 'Tarefa')),
        'meta' => $groupName,
        'detail' => trim((string) ($taskItem['priority_label'] ?? 'Média')),
        'priority' => $priorityKey,
        'workspace' => $workspace,
        'workspace_name' => $workspaceName,
    ]);
};
$appendOverviewDue = static function (array $dueItem, string $kicker, string $tone) use ($appendOverviewAttention, $overviewWorkspaceForItem): void {
    $workspace = $overviewWorkspaceForItem($dueItem);
    $workspaceName = trim((string) ($workspace['name'] ?? $dueItem['workspace_name'] ?? 'Workspace'));
    $groupName = trim((string) ($dueItem['group_name'] ?? ''));
    $daysLabel = trim((string) ($dueItem['days_until_label'] ?? ''));
    $amountLabel = trim((string) ($dueItem['amount_display'] ?? ''));
    $detailParts = array_filter([$daysLabel, $amountLabel]);
    $appendOverviewAttention([
        'key' => 'due:' . $workspaceName . ':' . trim((string) ($dueItem['label'] ?? '')) . ':' . trim((string) ($dueItem['next_due_date'] ?? '')),
        'tone' => $tone,
        'kicker' => $kicker,
        'title' => trim((string) ($dueItem['label'] ?? 'Vencimento')),
        'meta' => $groupName,
        'detail' => implode(' - ', $detailParts),
        'workspace' => $workspace,
        'workspace_name' => $workspaceName,
    ]);
};
$appendOverviewStock = static function (array $stockItem) use ($appendOverviewAttention, $overviewWorkspaceForItem): void {
    $workspace = $overviewWorkspaceForItem($stockItem);
    $workspaceName = trim((string) ($workspace['name'] ?? $stockItem['workspace_name'] ?? 'Workspace'));
    $groupName = trim((string) ($stockItem['group_name'] ?? ''));
    $stockDetail = trim((string) ($stockItem['quantity_display'] ?? '0'))
        . '/'
        . trim((string) ($stockItem['min_quantity_display'] ?? '0'))
        . ' '
        . trim((string) ($stockItem['unit_label'] ?? 'un'));
    $appendOverviewAttention([
        'key' => 'stock:' . $workspaceName . ':' . trim((string) ($stockItem['label'] ?? '')),
        'tone' => 'attention',
        'kicker' => 'Baixo estoque',
        'title' => trim((string) ($stockItem['label'] ?? 'Item')),
        'meta' => $groupName,
        'detail' => $stockDetail,
        'workspace' => $workspace,
        'workspace_name' => $workspaceName,
    ]);
};

foreach ($overviewTasksToday as $overviewTaskToday) {
    if (normalizeTaskPriority((string) ($overviewTaskToday['priority'] ?? 'medium')) !== 'urgent') {
        continue;
    }

    $appendOverviewTask($overviewTaskToday, 'Hoje', 'critical');
}

foreach ($overviewTasksToday as $overviewTaskToday) {
    $taskPriority = normalizeTaskPriority((string) ($overviewTaskToday['priority'] ?? 'medium'));
    if ($taskPriority === 'urgent') {
        continue;
    }

    $appendOverviewTask(
        $overviewTaskToday,
        'Hoje',
        $taskPriority === 'high' ? 'attention' : 'stable'
    );
}

foreach ($overviewDueSoon as $overviewDueSoonItem) {
    if ((int) ($overviewDueSoonItem['days_until'] ?? -1) !== 0) {
        continue;
    }

    $appendOverviewDue($overviewDueSoonItem, 'Hoje', 'critical');
}

foreach ($overviewLowStock as $overviewLowStockItem) {
    $appendOverviewStock($overviewLowStockItem);
}

if (empty($overviewAttentionItems)) {
    foreach (array_slice($overviewTasksToday, 0, min(2, max(1, $overviewPriorityTasksTodayTotal))) as $overviewTaskToday) {
        $taskPriority = normalizeTaskPriority((string) ($overviewTaskToday['priority'] ?? 'medium'));
        $appendOverviewTask(
            $overviewTaskToday,
            'Hoje',
            ($taskPriority === 'high' || $taskPriority === 'urgent') ? 'attention' : 'stable'
        );
    }

    foreach (array_slice($overviewTasksTomorrow, 0, 2) as $overviewTaskTomorrow) {
        $taskPriority = normalizeTaskPriority((string) ($overviewTaskTomorrow['priority'] ?? 'medium'));
        $appendOverviewTask(
            $overviewTaskTomorrow,
            'Amanhã',
            ($taskPriority === 'high' || $taskPriority === 'urgent') ? 'attention' : 'stable'
        );
    }

    foreach (array_slice($overviewDueSoon, 0, 2) as $overviewDueSoonItem) {
        $daysUntil = (int) ($overviewDueSoonItem['days_until'] ?? -1);
        $appendOverviewDue(
            $overviewDueSoonItem,
            $daysUntil === 1 ? 'Amanhã' : 'Próximo',
            $daysUntil === 1 ? 'attention' : 'stable'
        );
    }
} else {
    foreach ($overviewTasksToday as $overviewTaskToday) {
        $taskPriority = normalizeTaskPriority((string) ($overviewTaskToday['priority'] ?? 'medium'));
        if ($taskPriority !== 'high') {
            continue;
        }

        $appendOverviewTask($overviewTaskToday, 'Hoje', 'attention');
    }

    foreach ($overviewDueSoon as $overviewDueSoonItem) {
        if ((int) ($overviewDueSoonItem['days_until'] ?? -1) !== 1) {
            continue;
        }

        $appendOverviewDue($overviewDueSoonItem, 'Amanhã', 'attention');
    }

    foreach ($overviewTasksTomorrow as $overviewTaskTomorrow) {
        $taskPriority = normalizeTaskPriority((string) ($overviewTaskTomorrow['priority'] ?? 'medium'));
        $appendOverviewTask(
            $overviewTaskTomorrow,
            'Amanhã',
            ($taskPriority === 'high' || $taskPriority === 'urgent') ? 'attention' : 'stable'
        );
    }
}

if (count($overviewAttentionItems) < $overviewAttentionLimit) {
    foreach ($overviewOpenTasks as $overviewOpenTask) {
        if (count($overviewAttentionItems) >= $overviewAttentionLimit) {
            break;
        }

        $openTaskPriority = normalizeTaskPriority((string) ($overviewOpenTask['priority'] ?? 'medium'));
        $openTaskDueLabel = trim((string) ($overviewOpenTask['due_date_display'] ?? ''));
        $openTaskKicker = $openTaskDueLabel !== '' && mb_strtolower($openTaskDueLabel) !== 'sem prazo'
            ? $openTaskDueLabel
            : 'Em aberto';
        $appendOverviewTask(
            $overviewOpenTask,
            $openTaskKicker,
            ($openTaskPriority === 'high' || $openTaskPriority === 'urgent') ? 'attention' : 'stable'
        );
    }
}

$overviewAttentionItems = array_slice($overviewAttentionItems, 0, $overviewAttentionLimit);

$overviewWorkspaceTitle = ($overviewCriticalWorkspaceTotal + $overviewAttentionWorkspaceTotal) > 0
    ? 'Workspaces com atenção'
    : 'Por workspace';
?>
<div class="panel-header board-header overview-board-header dashboard-brief-head">
    <div>
        <h2>Dashboard</h2>
    </div>
</div>

<div class="dashboard-brief-grid dashboard-brief-grid-compact">
    <section class="dashboard-brief-list-card dashboard-priority-card" aria-label="Minha prioridade agora">
        <div class="dashboard-brief-list-head">
            <div>
                <h3><?= e($overviewListTitle) ?></h3>
            </div>
            <div class="dashboard-brief-head-actions">
                <?php if (!empty($overviewAttentionItems)): ?>
                    <span><?= e((string) count($overviewAttentionItems)) ?> item(ns)</span>
                <?php endif; ?>
                <?php if ($overviewPrimaryAction): ?>
                    <?php
                    $overviewActionView = trim((string) ($overviewPrimaryAction['view'] ?? 'overview'));
                    $overviewActionWorkspaceId = (int) ($overviewPrimaryAction['workspace_id'] ?? 0);
                    $overviewActionRedirect = dashboardPath($overviewActionView);
                    $overviewNeedsWorkspaceSwitch = $overviewActionWorkspaceId > 0
                        && $overviewActionWorkspaceId !== (int) ($currentWorkspaceId ?? 0);
                    ?>
                    <?php if ($overviewNeedsWorkspaceSwitch): ?>
                        <form method="post" class="dashboard-brief-action-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="switch_workspace">
                            <input type="hidden" name="workspace_id" value="<?= e((string) $overviewActionWorkspaceId) ?>">
                            <input type="hidden" name="redirect_to" value="<?= e($overviewActionRedirect) ?>">
                            <button type="submit" class="dashboard-brief-action">Ver tudo</button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="dashboard-brief-action" data-dashboard-view-toggle data-view="<?= e($overviewActionView) ?>">
                            Ver tudo
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($overviewAttentionItems)): ?>
            <div class="dashboard-brief-empty">
                <strong>Nada para acompanhar agora</strong>
                <p>Quando surgir algo importante, aparece aqui.</p>
            </div>
        <?php else: ?>
            <ul class="dashboard-brief-list">
                <?php foreach ($overviewAttentionItems as $overviewAttentionItem): ?>
                    <?php
                    $overviewAttentionToneClass = trim((string) ($overviewAttentionItem['tone'] ?? 'stable'));
                    $overviewAttentionMeta = trim((string) ($overviewAttentionItem['meta'] ?? ''));
                    $overviewAttentionDetail = trim((string) ($overviewAttentionItem['detail'] ?? ''));
                    $overviewAttentionPriorityRaw = trim((string) ($overviewAttentionItem['priority'] ?? ''));
                    $overviewAttentionPriority = $overviewAttentionPriorityRaw !== ''
                        ? normalizeTaskPriority($overviewAttentionPriorityRaw)
                        : '';
                    $overviewAttentionWorkspace = is_array($overviewAttentionItem['workspace'] ?? null)
                        ? $overviewAttentionItem['workspace']
                        : [];
                    $overviewAttentionWorkspaceName = trim((string) (
                        $overviewAttentionWorkspace['name']
                        ?? $overviewAttentionItem['workspace_name']
                        ?? ''
                    ));
                    $overviewAttentionHasWorkspace = $overviewAttentionWorkspaceName !== '';
                    if ($overviewAttentionHasWorkspace) {
                        $overviewAttentionWorkspace['name'] = $overviewAttentionWorkspaceName;
                    }
                    ?>
                    <li class="dashboard-brief-item is-<?= e($overviewAttentionToneClass) ?><?= $overviewAttentionPriority !== '' ? ' priority-' . e($overviewAttentionPriority) : '' ?><?= $overviewAttentionHasWorkspace ? ' has-workspace-avatar' : '' ?>">
                        <div class="dashboard-brief-item-top">
                            <span class="dashboard-brief-item-kicker"><?= e((string) ($overviewAttentionItem['kicker'] ?? 'Ponto importante')) ?></span>
                            <?php if ($overviewAttentionDetail !== ''): ?>
                                <span class="dashboard-brief-item-detail<?= $overviewAttentionPriority !== '' ? ' priority-' . e($overviewAttentionPriority) : '' ?>"><?= e($overviewAttentionDetail) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="dashboard-brief-item-main">
                            <?php if ($overviewAttentionHasWorkspace): ?>
                                <?= renderWorkspaceAvatar($overviewAttentionWorkspace, 'avatar small dashboard-brief-workspace-avatar', false, 'span') ?>
                            <?php endif; ?>
                            <div class="dashboard-brief-item-copy">
                                <strong><?= e((string) ($overviewAttentionItem['title'] ?? 'Item')) ?></strong>
                                <?php if ($overviewAttentionMeta !== ''): ?>
                                    <span class="dashboard-brief-item-meta"><?= e($overviewAttentionMeta) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="dashboard-workspace-summary-card is-compact" aria-label="Resumo por workspace">
        <div class="dashboard-brief-list-head dashboard-workspace-summary-head">
            <div>
                <h3><?= e($overviewWorkspaceTitle) ?></h3>
                <small>Resumo rápido de todos os workspaces.</small>
            </div>
        </div>

        <?php if (empty($overviewWorkspaceSummaries)): ?>
            <div class="dashboard-brief-empty">
                <strong>Nenhum workspace para resumir</strong>
                <p>Quando você participar de workspaces, eles aparecem aqui.</p>
            </div>
        <?php else: ?>
            <ul class="dashboard-workspace-summary-list is-compact">
                <?php foreach ($overviewWorkspaceSummaries as $overviewWorkspaceSummary): ?>
                    <?php
                    $overviewWorkspaceSummaryId = (int) ($overviewWorkspaceSummary['workspace_id'] ?? 0);
                    $overviewWorkspaceSummaryName = trim((string) ($overviewWorkspaceSummary['workspace_name'] ?? 'Workspace'));
                    $overviewWorkspaceSummaryTone = trim((string) ($overviewWorkspaceSummary['attention_tone'] ?? 'stable'));
                    if (!in_array($overviewWorkspaceSummaryTone, ['critical', 'attention', 'stable'], true)) {
                        $overviewWorkspaceSummaryTone = 'stable';
                    }
                    $overviewWorkspaceCard = $overviewWorkspacesById[$overviewWorkspaceSummaryId] ?? [
                        'id' => $overviewWorkspaceSummaryId,
                        'name' => $overviewWorkspaceSummaryName,
                    ];
                    $overviewWorkspaceCard['name'] = $overviewWorkspaceSummaryName;
                    $overviewWorkspaceOpenTotal = (int) ($overviewWorkspaceSummary['user_open_task_count'] ?? 0);
                    $overviewWorkspaceTodayTotal = (int) ($overviewWorkspaceSummary['tasks_today_count'] ?? 0);
                    $overviewWorkspaceUrgentTotal = (int) ($overviewWorkspaceSummary['urgent_tasks_today_count'] ?? 0);
                    $overviewWorkspaceTomorrowTotal = (int) ($overviewWorkspaceSummary['tasks_tomorrow_count'] ?? 0);
                    $overviewWorkspaceDueToday = (int) ($overviewWorkspaceSummary['due_today_count'] ?? 0);
                    $overviewWorkspaceLowStock = (int) ($overviewWorkspaceSummary['low_stock_count'] ?? 0);
                    $overviewWorkspacePills = [];
                    if ($overviewWorkspaceOpenTotal > 0) {
                        $overviewWorkspacePills[] = $overviewWorkspaceOpenTotal . ' aberta(s)';
                    }
                    if ($overviewWorkspaceTodayTotal > 0) {
                        $overviewWorkspacePills[] = $overviewWorkspaceTodayTotal . ' hoje';
                    }
                    if ($overviewWorkspaceUrgentTotal > 0) {
                        $overviewWorkspacePills[] = $overviewWorkspaceUrgentTotal . ' urgente(s)';
                    }
                    if ($overviewWorkspaceTomorrowTotal > 0) {
                        $overviewWorkspacePills[] = $overviewWorkspaceTomorrowTotal . ' amanhã';
                    }
                    if ($overviewWorkspaceDueToday > 0) {
                        $overviewWorkspacePills[] = $overviewWorkspaceDueToday . ' vence(m) hoje';
                    }
                    if ($overviewWorkspaceLowStock > 0) {
                        $overviewWorkspacePills[] = $overviewWorkspaceLowStock . ' baixo estoque';
                    }
                    if (empty($overviewWorkspacePills)) {
                        $overviewWorkspacePills[] = 'sem pendências';
                    }
                    $overviewWorkspaceActionRedirect = dashboardPath('tasks');
                    $overviewWorkspaceNeedsSwitch = $overviewWorkspaceSummaryId > 0
                        && $overviewWorkspaceSummaryId !== (int) ($currentWorkspaceId ?? 0);
                    ?>
                    <li class="dashboard-workspace-summary-item is-<?= e($overviewWorkspaceSummaryTone) ?>">
                        <div class="dashboard-workspace-summary-main">
                            <?= renderWorkspaceAvatar($overviewWorkspaceCard, 'avatar small dashboard-workspace-summary-avatar', false, 'span') ?>
                            <div class="dashboard-workspace-summary-copy">
                                <div class="dashboard-workspace-summary-title">
                                    <strong><?= e($overviewWorkspaceSummaryName) ?></strong>
                                    <span class="dashboard-brief-status is-<?= e($overviewWorkspaceSummaryTone) ?>"><?= e((string) ($overviewWorkspaceSummary['attention_label'] ?? 'Estável')) ?></span>
                                </div>
                                <span><?= e((string) ($overviewWorkspaceSummary['workspace_role_label'] ?? 'Usuário')) ?></span>
                            </div>
                        </div>

                        <div class="dashboard-workspace-summary-pills">
                            <?php foreach (array_slice($overviewWorkspacePills, 0, 4) as $overviewWorkspacePill): ?>
                                <span><?= e($overviewWorkspacePill) ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div class="dashboard-workspace-summary-actions">
                            <?php if ($overviewWorkspaceNeedsSwitch): ?>
                                <form method="post" class="dashboard-brief-action-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="switch_workspace">
                                    <input type="hidden" name="workspace_id" value="<?= e((string) $overviewWorkspaceSummaryId) ?>">
                                    <input type="hidden" name="redirect_to" value="<?= e($overviewWorkspaceActionRedirect) ?>">
                                    <button type="submit" class="dashboard-brief-action">Abrir</button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="dashboard-brief-action" data-dashboard-view-toggle data-view="tasks">Abrir</button>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
