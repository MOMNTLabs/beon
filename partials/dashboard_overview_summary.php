<?php
$overviewExecutiveTone = (string) ($globalDashboardOverview['executive_status_tone'] ?? 'stable');
$overviewWorkspaceCount = (int) ($globalDashboardOverview['workspace_count'] ?? 0);
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
$overviewNote = 'Os principais pontos de atencao vao aparecer aqui assim que houver movimento.';
$overviewListTitle = 'Principais pontos';
if ($overviewUrgentTasksTodayTotal > 0) {
    $overviewHeadline = $overviewUrgentTasksTodayTotal === 1
        ? 'Existe 1 urgencia para olhar agora'
        : 'Existem ' . $overviewUrgentTasksTodayTotal . ' urgencias para olhar agora';
    $overviewNote = 'Comece pelo que precisa de decisao imediata e avance depois para o restante do dia.';
    $overviewListTitle = 'Prioridade agora';
} elseif ($overviewDueTodayTotal > 0) {
    $overviewHeadline = $overviewDueTodayTotal === 1
        ? 'Ha 1 vencimento para hoje'
        : 'Ha ' . $overviewDueTodayTotal . ' vencimentos para hoje';
    $overviewNote = 'Vale abrir os vencimentos primeiro para nao deixar nada passar.';
    $overviewListTitle = 'Atencao imediata';
} elseif ($overviewLowStockTotal > 0) {
    $overviewHeadline = $overviewLowStockTotal === 1
        ? 'Existe 1 item pedindo reposicao'
        : 'Existem ' . $overviewLowStockTotal . ' itens pedindo reposicao';
    $overviewNote = 'O dashboard fica enxuto e traz so o que ja esta merecendo acompanhamento.';
    $overviewListTitle = 'Itens em foco';
} elseif ($overviewTasksTodayTotal > 0) {
    $overviewHeadline = $overviewTasksTodayTotal === 1
        ? 'Voce tem 1 tarefa no radar de hoje'
        : 'Voce tem ' . $overviewTasksTodayTotal . ' tarefas no radar de hoje';
    $overviewNote = 'Nada urgente por enquanto, mas estes sao os pontos mais relevantes para abrir primeiro.';
    $overviewListTitle = 'Mais relevantes';
} elseif ($overviewTasksTomorrowTotal > 0) {
    $overviewHeadline = $overviewTasksTomorrowTotal === 1
        ? 'Voce tem 1 tarefa programada para amanha'
        : 'Voce tem ' . $overviewTasksTomorrowTotal . ' tarefas programadas para amanha';
    $overviewNote = 'Sem urgencias agora, entao o dashboard ja antecipa o que entra no seu radar no proximo dia.';
    $overviewListTitle = 'Proximos passos';
} elseif ($overviewDueSoonTotal > 0) {
    $overviewHeadline = $overviewDueSoonTotal === 1
        ? 'Ha 1 vencimento proximo no radar'
        : 'Ha ' . $overviewDueSoonTotal . ' vencimentos proximos no radar';
    $overviewNote = 'Mesmo sem urgencia agora, vale acompanhar o que esta mais perto.';
    $overviewListTitle = 'Mais relevantes';
} elseif (!empty($overviewWorkspaceSummaries)) {
    $overviewHeadline = 'Resumo rapido dos workspaces';
    $overviewNote = 'Quando surgirem tarefas, vencimentos ou alertas, eles aparecem aqui em destaque.';
    $overviewListTitle = 'Mais relevantes';
}

$overviewQuickStats = [];
if ($overviewUrgentTasksTodayTotal > 0) {
    $overviewQuickStats[] = $overviewUrgentTasksTodayTotal . ' urgente(s)';
}
if ($overviewDueTodayTotal > 0) {
    $overviewQuickStats[] = $overviewDueTodayTotal . ' vence(m) hoje';
}
if ($overviewTasksTodayTotal > 0 && $overviewUrgentTasksTodayTotal === 0) {
    $overviewQuickStats[] = $overviewTasksTodayTotal . ' tarefa(s) hoje';
}
if ($overviewTasksTomorrowTotal > 0 && count($overviewQuickStats) < 3) {
    $overviewQuickStats[] = $overviewTasksTomorrowTotal . ' tarefa(s) amanha';
}
if ($overviewLowStockTotal > 0) {
    $overviewQuickStats[] = $overviewLowStockTotal . ' item(ns) em baixa';
}
if ($overviewAttentionWorkspaceTotal > 0 && count($overviewQuickStats) < 3) {
    $overviewQuickStats[] = $overviewAttentionWorkspaceTotal . ' workspace(s) em monitoramento';
}
$overviewQuickStats = array_slice($overviewQuickStats, 0, 3);

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

$overviewAttentionItems = [];
$overviewSeenAttentionKeys = [];
$appendOverviewAttention = static function (array $item) use (&$overviewAttentionItems, &$overviewSeenAttentionKeys): void {
    if (count($overviewAttentionItems) >= 4) {
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
$appendOverviewTask = static function (array $taskItem, string $kicker, string $tone) use ($appendOverviewAttention): void {
    $workspaceName = trim((string) ($taskItem['workspace_name'] ?? 'Workspace'));
    $groupName = trim((string) ($taskItem['group_name'] ?? ''));
    $metaParts = array_filter([$workspaceName, $groupName !== '' ? $groupName : null]);
    $appendOverviewAttention([
        'key' => 'task:' . (string) ($taskItem['task_id'] ?? 0),
        'tone' => $tone,
        'kicker' => $kicker,
        'title' => trim((string) ($taskItem['title'] ?? 'Tarefa')),
        'meta' => implode(' - ', $metaParts),
        'detail' => trim((string) ($taskItem['priority_label'] ?? 'Media')),
    ]);
};
$appendOverviewDue = static function (array $dueItem, string $kicker, string $tone) use ($appendOverviewAttention): void {
    $workspaceName = trim((string) ($dueItem['workspace_name'] ?? 'Workspace'));
    $groupName = trim((string) ($dueItem['group_name'] ?? ''));
    $metaParts = array_filter([$workspaceName, $groupName !== '' ? $groupName : null]);
    $daysLabel = trim((string) ($dueItem['days_until_label'] ?? ''));
    $amountLabel = trim((string) ($dueItem['amount_display'] ?? ''));
    $detailParts = array_filter([$daysLabel, $amountLabel]);
    $appendOverviewAttention([
        'key' => 'due:' . $workspaceName . ':' . trim((string) ($dueItem['label'] ?? '')) . ':' . trim((string) ($dueItem['next_due_date'] ?? '')),
        'tone' => $tone,
        'kicker' => $kicker,
        'title' => trim((string) ($dueItem['label'] ?? 'Vencimento')),
        'meta' => implode(' - ', $metaParts),
        'detail' => implode(' - ', $detailParts),
    ]);
};
$appendOverviewStock = static function (array $stockItem) use ($appendOverviewAttention): void {
    $workspaceName = trim((string) ($stockItem['workspace_name'] ?? 'Workspace'));
    $groupName = trim((string) ($stockItem['group_name'] ?? ''));
    $metaParts = array_filter([$workspaceName, $groupName !== '' ? $groupName : null]);
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
        'meta' => implode(' - ', $metaParts),
        'detail' => $stockDetail,
    ]);
};
$appendOverviewWorkspace = static function (array $workspaceSummary) use ($appendOverviewAttention): void {
    $appendOverviewAttention([
        'key' => 'workspace:' . (string) ($workspaceSummary['workspace_id'] ?? 0),
        'tone' => (string) ($workspaceSummary['attention_tone'] ?? 'stable'),
        'kicker' => 'Workspace em foco',
        'title' => trim((string) ($workspaceSummary['workspace_name'] ?? 'Workspace')),
        'meta' => trim((string) ($workspaceSummary['workspace_role_label'] ?? 'Usuario')),
        'detail' => trim((string) ($workspaceSummary['attention_note'] ?? 'Sem pendencias imediatas.')),
    ]);
};

foreach ($overviewTasksToday as $overviewTaskToday) {
    if (normalizeTaskPriority((string) ($overviewTaskToday['priority'] ?? 'medium')) !== 'urgent') {
        continue;
    }

    $appendOverviewTask($overviewTaskToday, 'Urgente hoje', 'critical');
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

foreach ($overviewWorkspaceSummaries as $overviewWorkspaceSummary) {
    if ((string) ($overviewWorkspaceSummary['attention_tone'] ?? 'stable') !== 'critical') {
        continue;
    }

    $appendOverviewWorkspace($overviewWorkspaceSummary);
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
            'Amanha',
            ($taskPriority === 'high' || $taskPriority === 'urgent') ? 'attention' : 'stable'
        );
    }

    foreach (array_slice($overviewDueSoon, 0, 2) as $overviewDueSoonItem) {
        $daysUntil = (int) ($overviewDueSoonItem['days_until'] ?? -1);
        $appendOverviewDue(
            $overviewDueSoonItem,
            $daysUntil === 1 ? 'Vence amanha' : 'Proximo vencimento',
            $daysUntil === 1 ? 'attention' : 'stable'
        );
    }

    foreach (array_slice($overviewWorkspaceSummaries, 0, 1) as $overviewWorkspaceSummary) {
        if ((int) ($overviewWorkspaceSummary['attention_score'] ?? 0) <= 0 && $overviewWorkspaceCount > 0) {
            continue;
        }

        $appendOverviewWorkspace($overviewWorkspaceSummary);
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

        $appendOverviewDue($overviewDueSoonItem, 'Vence amanha', 'attention');
    }

    foreach ($overviewTasksTomorrow as $overviewTaskTomorrow) {
        $taskPriority = normalizeTaskPriority((string) ($overviewTaskTomorrow['priority'] ?? 'medium'));
        if ($taskPriority !== 'high' && $taskPriority !== 'urgent') {
            continue;
        }

        $appendOverviewTask($overviewTaskTomorrow, 'Amanha', 'attention');
    }

    foreach ($overviewWorkspaceSummaries as $overviewWorkspaceSummary) {
        if ((string) ($overviewWorkspaceSummary['attention_tone'] ?? 'stable') !== 'attention') {
            continue;
        }

        $appendOverviewWorkspace($overviewWorkspaceSummary);
    }
}
?>
<div class="panel-header board-header overview-board-header dashboard-brief-head">
    <div>
        <h2>Dashboard</h2>
        <p>Um resumo curto do que merece sua atencao primeiro.</p>
    </div>
</div>

<div class="dashboard-brief-grid">
    <section class="dashboard-brief-hero is-<?= e($overviewExecutiveTone) ?>" aria-label="Resumo principal do dashboard">
        <span class="dashboard-brief-kicker"><?= $overviewHasImmediateAttention ? 'Agora' : 'Visao geral' ?></span>
        <h3><?= e($overviewHeadline) ?></h3>
        <p><?= e($overviewNote) ?></p>
        <?php if (!empty($overviewQuickStats)): ?>
            <div class="dashboard-brief-chips" aria-label="Indicadores principais">
                <?php foreach ($overviewQuickStats as $overviewQuickStat): ?>
                    <span class="dashboard-brief-chip"><?= e((string) $overviewQuickStat) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($overviewOpenActions)): ?>
            <div class="dashboard-brief-actions">
                <?php foreach ($overviewOpenActions as $overviewOpenAction): ?>
                    <?php
                    $overviewActionView = trim((string) ($overviewOpenAction['view'] ?? 'overview'));
                    $overviewActionWorkspaceId = (int) ($overviewOpenAction['workspace_id'] ?? 0);
                    $overviewActionRedirect = 'index.php#' . $overviewActionView;
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

    <section class="dashboard-brief-list-card" aria-label="Lista resumida do dashboard">
        <div class="dashboard-brief-list-head">
            <h3><?= e($overviewListTitle) ?></h3>
            <?php if (!empty($overviewAttentionItems)): ?>
                <span><?= e((string) count($overviewAttentionItems)) ?> item(ns)</span>
            <?php endif; ?>
        </div>

        <?php if (empty($overviewAttentionItems)): ?>
            <div class="dashboard-brief-empty">
                <strong>Nada para acompanhar ainda</strong>
                <p>Esta area vai mostrar os principais pontos de atencao assim que eles existirem.</p>
            </div>
        <?php else: ?>
            <ul class="dashboard-brief-list">
                <?php foreach ($overviewAttentionItems as $overviewAttentionItem): ?>
                    <?php
                    $overviewAttentionToneClass = trim((string) ($overviewAttentionItem['tone'] ?? 'stable'));
                    $overviewAttentionMeta = trim((string) ($overviewAttentionItem['meta'] ?? ''));
                    $overviewAttentionDetail = trim((string) ($overviewAttentionItem['detail'] ?? ''));
                    ?>
                    <li class="dashboard-brief-item is-<?= e($overviewAttentionToneClass) ?>">
                        <div class="dashboard-brief-item-top">
                            <span class="dashboard-brief-item-kicker"><?= e((string) ($overviewAttentionItem['kicker'] ?? 'Ponto importante')) ?></span>
                            <?php if ($overviewAttentionDetail !== ''): ?>
                                <span class="dashboard-brief-item-detail"><?= e($overviewAttentionDetail) ?></span>
                            <?php endif; ?>
                        </div>
                        <strong><?= e((string) ($overviewAttentionItem['title'] ?? 'Item')) ?></strong>
                        <?php if ($overviewAttentionMeta !== ''): ?>
                            <span class="dashboard-brief-item-meta"><?= e($overviewAttentionMeta) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
