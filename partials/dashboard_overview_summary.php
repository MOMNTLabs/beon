<?php
$overviewExecutiveTone = (string) ($globalDashboardOverview['executive_status_tone'] ?? 'stable');
$overviewDueSoonTotal = (int) ($globalDashboardOverview['due_soon_total'] ?? 0);
$overviewTasksTodayTotal = (int) ($globalDashboardOverview['tasks_today_total'] ?? 0);
$overviewTasksTomorrowTotal = (int) ($globalDashboardOverview['tasks_tomorrow_total'] ?? 0);
$overviewUrgentTasksTodayTotal = (int) ($globalDashboardOverview['urgent_tasks_today_total'] ?? 0);
$overviewLowStockTotal = (int) ($globalDashboardOverview['low_stock_total'] ?? 0);
$overviewPriorityTasksTodayTotal = (int) ($globalDashboardOverview['priority_tasks_today_total'] ?? 0);
$overviewDueTodayTotal = (int) ($globalDashboardOverview['due_today_total'] ?? 0);
$overviewDueTomorrowTotal = (int) ($globalDashboardOverview['due_tomorrow_total'] ?? 0);
$overviewAttentionWorkspaceTotal = (int) ($globalDashboardOverview['attention_workspace_total'] ?? 0);
$overviewCriticalWorkspaceTotal = (int) ($globalDashboardOverview['critical_workspace_total'] ?? 0);
$overviewTasksToday = array_values((array) ($globalDashboardOverview['tasks_today'] ?? []));
$overviewTasksTomorrow = array_values((array) ($globalDashboardOverview['tasks_tomorrow'] ?? []));
$overviewDueSoon = array_values((array) ($globalDashboardOverview['due_soon'] ?? []));
$overviewLowStock = array_values((array) ($globalDashboardOverview['low_stock'] ?? []));
$overviewWorkspaceSummaries = array_values((array) ($globalDashboardOverview['workspace_summaries'] ?? []));
$overviewUserTaskTotal = (int) ($globalDashboardOverview['user_task_total'] ?? 0);
$overviewUserTaskDoneTotal = (int) ($globalDashboardOverview['user_task_done_total'] ?? 0);
$overviewUserOpenTaskTotal = (int) ($globalDashboardOverview['user_open_task_total'] ?? 0);
$overviewCompletionPercent = $overviewUserTaskTotal > 0
    ? max(0, min(100, (int) round(($overviewUserTaskDoneTotal / $overviewUserTaskTotal) * 100)))
    : 0;
$overviewCompletionSummary = $overviewUserTaskTotal > 0
    ? $overviewUserTaskDoneTotal . '/' . $overviewUserTaskTotal . ' · ' . $overviewCompletionPercent . '%'
    : 'Sem tarefas';
$overviewExecutiveLabel = trim((string) ($globalDashboardOverview['executive_status_label'] ?? 'Visão geral'));

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
    ?? 0
);
$overviewPrimaryDueWorkspaceId = (int) ($overviewDueSoon[0]['workspace_id'] ?? 0);
$overviewPrimaryInventoryWorkspaceId = (int) ($overviewLowStock[0]['workspace_id'] ?? 0);
$overviewHasImmediateAttention = $overviewUrgentTasksTodayTotal > 0
    || $overviewDueTodayTotal > 0
    || $overviewLowStockTotal > 0
    || $overviewCriticalWorkspaceTotal > 0;

$overviewHeadline = 'Dashboard limpo por enquanto';
$overviewListTitle = 'Principais pontos';
if ($overviewUrgentTasksTodayTotal > 0) {
    if ($overviewTasksTodayTotal > $overviewUrgentTasksTodayTotal) {
        $overviewHeadline = $overviewUrgentTasksTodayTotal === 1
            ? 'Existe 1 urgência entre ' . $overviewTasksTodayTotal . ' tarefas de hoje'
            : 'Existem ' . $overviewUrgentTasksTodayTotal . ' urgências entre ' . $overviewTasksTodayTotal . ' tarefas de hoje';
    } else {
        $overviewHeadline = $overviewUrgentTasksTodayTotal === 1
            ? 'Existe 1 urgência para olhar agora'
            : 'Existem ' . $overviewUrgentTasksTodayTotal . ' urgências para olhar agora';
    }
    $overviewListTitle = 'Minha prioridade agora';
} elseif ($overviewDueTodayTotal > 0) {
    $overviewHeadline = $overviewDueTodayTotal === 1
        ? 'Há 1 vencimento para hoje'
        : 'Há ' . $overviewDueTodayTotal . ' vencimentos para hoje';
    $overviewListTitle = 'Atenção imediata';
} elseif ($overviewLowStockTotal > 0) {
    $overviewHeadline = $overviewLowStockTotal === 1
        ? 'Existe 1 item pedindo reposição'
        : 'Existem ' . $overviewLowStockTotal . ' itens pedindo reposição';
    $overviewListTitle = 'Itens em foco';
} elseif ($overviewTasksTodayTotal > 0) {
    $overviewHeadline = $overviewTasksTodayTotal === 1
        ? 'Você tem 1 tarefa no radar de hoje'
        : 'Você tem ' . $overviewTasksTodayTotal . ' tarefas no radar de hoje';
    $overviewListTitle = 'Mais relevantes';
} elseif ($overviewTasksTomorrowTotal > 0) {
    $overviewHeadline = $overviewTasksTomorrowTotal === 1
        ? 'Você tem 1 tarefa programada para amanhã'
        : 'Você tem ' . $overviewTasksTomorrowTotal . ' tarefas programadas para amanhã';
    $overviewListTitle = 'Próximos passos';
} elseif ($overviewDueSoonTotal > 0) {
    $overviewHeadline = $overviewDueSoonTotal === 1
        ? 'Há 1 vencimento próximo no radar'
        : 'Há ' . $overviewDueSoonTotal . ' vencimentos próximos no radar';
    $overviewListTitle = 'Mais relevantes';
} elseif (!empty($overviewWorkspaceSummaries)) {
    $overviewHeadline = 'Resumo rápido dos workspaces';
}

$overviewOpenActions = [];
if ($overviewTasksTodayTotal > 0) {
    $overviewOpenActions[] = [
        'view' => 'tasks',
        'label' => 'Abrir tarefas',
        'workspace_id' => $overviewPrimaryTasksWorkspaceId,
    ];
}
if ($overviewTasksTomorrowTotal > 0 && $overviewTasksTodayTotal === 0) {
    $overviewOpenActions[] = [
        'view' => 'tasks',
        'label' => 'Ver tarefas de amanhã',
        'workspace_id' => $overviewPrimaryTasksWorkspaceId,
    ];
}
if ($overviewDueSoonTotal > 0) {
    $overviewOpenActions[] = [
        'view' => 'dues',
        'label' => 'Abrir vencimentos',
        'workspace_id' => $overviewPrimaryDueWorkspaceId,
    ];
}
if ($overviewLowStockTotal > 0) {
    $overviewOpenActions[] = [
        'view' => 'inventory',
        'label' => 'Abrir estoque',
        'workspace_id' => $overviewPrimaryInventoryWorkspaceId,
    ];
}
$overviewOpenActions = array_slice($overviewOpenActions, 0, 3);

$overviewDayMetrics = [
    [
        'label' => 'Urgentes hoje',
        'value' => $overviewUrgentTasksTodayTotal,
        'tone' => $overviewUrgentTasksTodayTotal > 0 ? 'critical' : 'stable',
        'hint' => $overviewUrgentTasksTodayTotal > 0 ? 'fazer primeiro' : 'sem urgência',
    ],
    [
        'label' => 'Hoje',
        'value' => $overviewTasksTodayTotal,
        'tone' => $overviewTasksTodayTotal > 0 ? 'today' : 'stable',
        'hint' => 'tarefas no radar',
    ],
    [
        'label' => 'Amanhã',
        'value' => $overviewTasksTomorrowTotal,
        'tone' => $overviewTasksTomorrowTotal > 0 ? 'tomorrow' : 'stable',
        'hint' => 'próximas tarefas',
    ],
    [
        'label' => 'Abertas',
        'value' => $overviewUserOpenTaskTotal,
        'tone' => $overviewUserOpenTaskTotal > 0 ? 'open' : 'stable',
        'hint' => 'todos workspaces',
    ],
];

$overviewAttentionItems = [];
$overviewSeenAttentionKeys = [];
$appendOverviewAttention = static function (array $item) use (&$overviewAttentionItems, &$overviewSeenAttentionKeys): void {
    if (count($overviewAttentionItems) >= 8) {
        return;
    }

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

    $appendOverviewTask($overviewTaskToday, 'Urgente hoje', 'critical');
}

foreach ($overviewTasksToday as $overviewTaskToday) {
    $taskPriority = normalizeTaskPriority((string) ($overviewTaskToday['priority'] ?? 'medium'));
    if ($taskPriority === 'urgent') {
        continue;
    }

    $appendOverviewTask(
        $overviewTaskToday,
        'Para hoje',
        $taskPriority === 'high' ? 'attention' : 'stable'
    );
}

foreach ($overviewDueSoon as $overviewDueSoonItem) {
    if ((int) ($overviewDueSoonItem['days_until'] ?? -1) !== 0) {
        continue;
    }

    $appendOverviewDue($overviewDueSoonItem, 'Vence hoje', 'critical');
}

foreach ($overviewLowStock as $overviewLowStockItem) {
    $appendOverviewStock($overviewLowStockItem);
}

if (empty($overviewAttentionItems)) {
    foreach (array_slice($overviewTasksToday, 0, min(2, max(1, $overviewPriorityTasksTodayTotal))) as $overviewTaskToday) {
        $taskPriority = normalizeTaskPriority((string) ($overviewTaskToday['priority'] ?? 'medium'));
        $appendOverviewTask(
            $overviewTaskToday,
            'Para hoje',
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
            $daysUntil === 1 ? 'Vence amanhã' : 'Próximo vencimento',
            $daysUntil === 1 ? 'attention' : 'stable'
        );
    }
} else {
    foreach ($overviewTasksToday as $overviewTaskToday) {
        $taskPriority = normalizeTaskPriority((string) ($overviewTaskToday['priority'] ?? 'medium'));
        if ($taskPriority !== 'high') {
            continue;
        }

        $appendOverviewTask($overviewTaskToday, 'Alta prioridade', 'attention');
    }

    foreach ($overviewDueSoon as $overviewDueSoonItem) {
        if ((int) ($overviewDueSoonItem['days_until'] ?? -1) !== 1) {
            continue;
        }

        $appendOverviewDue($overviewDueSoonItem, 'Vence amanhã', 'attention');
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
?>
<div class="panel-header board-header overview-board-header dashboard-brief-head">
    <div>
        <h2>Dashboard</h2>
    </div>
</div>

<div class="dashboard-brief-grid">
    <section class="dashboard-brief-hero is-<?= e($overviewExecutiveTone) ?>" aria-label="Resumo do dia">
        <div class="dashboard-brief-hero-head">
            <span class="dashboard-brief-kicker">Resumo do dia</span>
            <span class="dashboard-brief-status is-<?= e($overviewExecutiveTone) ?>"><?= e($overviewExecutiveLabel) ?></span>
        </div>
        <h3><?= e($overviewHeadline) ?></h3>

        <dl class="dashboard-day-metrics" aria-label="Indicadores do dia">
            <?php foreach ($overviewDayMetrics as $overviewDayMetric): ?>
                <div class="dashboard-day-metric is-<?= e((string) ($overviewDayMetric['tone'] ?? 'stable')) ?>">
                    <dt><?= e((string) ($overviewDayMetric['label'] ?? 'Indicador')) ?></dt>
                    <dd><?= e((string) ($overviewDayMetric['value'] ?? 0)) ?></dd>
                    <small><?= e((string) ($overviewDayMetric['hint'] ?? '')) ?></small>
                </div>
            <?php endforeach; ?>
        </dl>

        <div class="dashboard-progress-card" style="--dashboard-progress: <?= e((string) $overviewCompletionPercent) ?>%;">
            <div class="dashboard-progress-card-head">
                <span>Concluídas</span>
                <strong><?= e($overviewCompletionSummary) ?></strong>
            </div>
            <div class="dashboard-progress-track" aria-hidden="true">
                <span></span>
            </div>
        </div>

        <?php if (!empty($overviewOpenActions)): ?>
            <div class="dashboard-brief-actions">
                <?php foreach ($overviewOpenActions as $overviewOpenAction): ?>
                    <?php
                    $overviewActionView = trim((string) ($overviewOpenAction['view'] ?? 'overview'));
                    $overviewActionWorkspaceId = (int) ($overviewOpenAction['workspace_id'] ?? 0);
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
                            <button type="submit" class="dashboard-brief-action">
                                <?= e((string) ($overviewOpenAction['label'] ?? 'Abrir')) ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <button
                            type="button"
                            class="dashboard-brief-action"
                            data-dashboard-view-toggle
                            data-view="<?= e($overviewActionView) ?>"
                        >
                            <?= e((string) ($overviewOpenAction['label'] ?? 'Abrir')) ?>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-brief-list-card" aria-label="Minha prioridade agora">
        <div class="dashboard-brief-list-head">
            <div>
                <h3><?= e($overviewListTitle) ?></h3>
                <small>Regra: urgência + prazo</small>
            </div>
            <?php if (!empty($overviewAttentionItems)): ?>
                <span><?= e((string) count($overviewAttentionItems)) ?> item(ns)</span>
            <?php endif; ?>
        </div>

        <?php if (empty($overviewAttentionItems)): ?>
            <div class="dashboard-brief-empty">
                <strong>Nada para acompanhar ainda</strong>
                <p>Esta área vai mostrar os principais pontos de atenção assim que eles existirem.</p>
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

    <section class="dashboard-workspace-summary-card" aria-label="Resumo por workspace">
        <div class="dashboard-brief-list-head dashboard-workspace-summary-head">
            <div>
                <h3>Por workspace</h3>
                <small><?= e((string) count($overviewWorkspaceSummaries)) ?> workspace(s) no painel</small>
            </div>
        </div>

        <?php if (empty($overviewWorkspaceSummaries)): ?>
            <div class="dashboard-brief-empty">
                <strong>Nenhum workspace para resumir</strong>
                <p>Quando você participar de workspaces, eles aparecem aqui.</p>
            </div>
        <?php else: ?>
            <ul class="dashboard-workspace-summary-list">
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
                    $overviewWorkspaceTaskTotal = (int) ($overviewWorkspaceSummary['user_task_count'] ?? 0);
                    $overviewWorkspaceDoneTotal = (int) ($overviewWorkspaceSummary['user_task_done_count'] ?? 0);
                    $overviewWorkspaceOpenTotal = (int) ($overviewWorkspaceSummary['user_open_task_count'] ?? 0);
                    $overviewWorkspaceCompletion = $overviewWorkspaceTaskTotal > 0
                        ? max(0, min(100, (int) round(($overviewWorkspaceDoneTotal / $overviewWorkspaceTaskTotal) * 100)))
                        : 0;
                    $overviewWorkspaceProgressLabel = $overviewWorkspaceTaskTotal > 0
                        ? $overviewWorkspaceDoneTotal . '/' . $overviewWorkspaceTaskTotal . ' concluídas'
                        : 'Sem tarefas';
                    $overviewWorkspaceKpis = [
                        ['label' => 'Abertas', 'value' => $overviewWorkspaceOpenTotal, 'tone' => $overviewWorkspaceOpenTotal > 0 ? 'open' : 'stable'],
                        ['label' => 'Hoje', 'value' => (int) ($overviewWorkspaceSummary['tasks_today_count'] ?? 0), 'tone' => ((int) ($overviewWorkspaceSummary['tasks_today_count'] ?? 0)) > 0 ? 'today' : 'stable'],
                        ['label' => 'Urgentes', 'value' => (int) ($overviewWorkspaceSummary['urgent_tasks_today_count'] ?? 0), 'tone' => ((int) ($overviewWorkspaceSummary['urgent_tasks_today_count'] ?? 0)) > 0 ? 'critical' : 'stable'],
                        ['label' => 'Amanhã', 'value' => (int) ($overviewWorkspaceSummary['tasks_tomorrow_count'] ?? 0), 'tone' => ((int) ($overviewWorkspaceSummary['tasks_tomorrow_count'] ?? 0)) > 0 ? 'tomorrow' : 'stable'],
                    ];
                    $overviewWorkspaceSignals = [];
                    $overviewWorkspaceDueToday = (int) ($overviewWorkspaceSummary['due_today_count'] ?? 0);
                    $overviewWorkspaceLowStock = (int) ($overviewWorkspaceSummary['low_stock_count'] ?? 0);
                    if ($overviewWorkspaceDueToday > 0) {
                        $overviewWorkspaceSignals[] = $overviewWorkspaceDueToday . ' vence(m) hoje';
                    }
                    if ($overviewWorkspaceLowStock > 0) {
                        $overviewWorkspaceSignals[] = $overviewWorkspaceLowStock . ' baixo estoque';
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
                                <span><?= e((string) ($overviewWorkspaceSummary['workspace_role_label'] ?? 'Usuário')) ?> · <?= e((string) ($overviewWorkspaceSummary['attention_note'] ?? 'Sem pendências imediatas.')) ?></span>
                            </div>
                        </div>

                        <dl class="dashboard-workspace-summary-kpis">
                            <?php foreach ($overviewWorkspaceKpis as $overviewWorkspaceKpi): ?>
                                <div class="is-<?= e((string) ($overviewWorkspaceKpi['tone'] ?? 'stable')) ?>">
                                    <dt><?= e((string) ($overviewWorkspaceKpi['label'] ?? 'Item')) ?></dt>
                                    <dd><?= e((string) ($overviewWorkspaceKpi['value'] ?? 0)) ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>

                        <div class="dashboard-workspace-summary-footer">
                            <div class="dashboard-workspace-progress" style="--dashboard-progress: <?= e((string) $overviewWorkspaceCompletion) ?>%;">
                                <span><?= e($overviewWorkspaceProgressLabel) ?></span>
                                <div class="dashboard-progress-track" aria-hidden="true">
                                    <span></span>
                                </div>
                            </div>
                            <?php if (!empty($overviewWorkspaceSignals)): ?>
                                <div class="dashboard-workspace-signals">
                                    <?php foreach ($overviewWorkspaceSignals as $overviewWorkspaceSignal): ?>
                                        <span><?= e($overviewWorkspaceSignal) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
