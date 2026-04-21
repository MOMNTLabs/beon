<?php
if (($currentWorkspaceId ?? null) !== null) {
    $workspaceIdForTaskTags = (int) $currentWorkspaceId;
    $taskTitleTagOptions = taskTitleTagOptionsByWorkspace($workspaceIdForTaskTags);
    $taskTitleTagColors = taskTitleTagColorsByWorkspace($workspaceIdForTaskTags);
    $hasPersistedTaskTitleTagOptions = hasTaskTitleTagOptionsByWorkspace($workspaceIdForTaskTags);
} else {
    $taskTitleTagOptions = normalizeTaskTitleTagOptionsList(taskTitleTagPresets());
    $taskTitleTagColors = [];
    $hasPersistedTaskTitleTagOptions = false;
}
if (!$hasPersistedTaskTitleTagOptions) {
    foreach ($tasks as $taskWithTagOption) {
        $taskTagOption = normalizeTaskTitleTag((string) ($taskWithTagOption['title_tag'] ?? ''));
        if ($taskTagOption !== '') {
            $taskTitleTagOptions[] = $taskTagOption;
        }
    }
}
$taskTitleTagOptions = normalizeTaskTitleTagOptionsList($taskTitleTagOptions);
$taskTitleTagOptionsPayload = [];
$taskTitleTagColorsPayload = [];
foreach ($taskTitleTagOptions as $taskTitleTagOptionValue) {
    $taskTitleTagColorValue = taskTitleTagColorForTag((string) $taskTitleTagOptionValue, $taskTitleTagColors);
    $taskTitleTagOptionsPayload[] = [
        'value' => (string) $taskTitleTagOptionValue,
        'color' => $taskTitleTagColorValue,
    ];
    $taskTitleTagColorsPayload[(string) $taskTitleTagOptionValue] = $taskTitleTagColorValue;
}
$statusMetaByKey = is_array($statusConfig['meta_by_key'] ?? null) ? $statusConfig['meta_by_key'] : [];
?>
<script
    type="application/json"
    id="task-title-tag-options-data"
    data-workspace-id="<?= e((string) ($currentWorkspaceId ?? 0)) ?>"
><?= json_encode(
    [
        'options' => $taskTitleTagOptionsPayload,
        'tag_colors' => $taskTitleTagColorsPayload,
        'palette' => taskTitleTagPalette(),
        'default_color' => taskTitleTagDefaultColor(),
    ],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>

<main class="dashboard dashboard-compact">
    <section class="workspace-layout tasklist-layout">
        <aside class="panel users-sidebar" id="team">
            <a href="<?= e(appPath()) ?>" class="sidebar-brand" aria-label="<?= e(APP_NAME) ?>">
                <img
                    src="assets/Bexon - Logo Horizontal Negativa.png?v=1"
                    alt="<?= e(APP_NAME) ?>"
                    class="sidebar-brand-lockup brand-lockup"
                    width="116"
                    height="29"
                >
            </a>
            <div class="users-sidebar-body">
                <div class="panel-header workspace-sidebar-header">
                    <button
                        type="button"
                        class="sidebar-view-toggle workspace-sidebar-overview-toggle is-active"
                        data-dashboard-view-toggle
                        data-view="overview"
                        aria-pressed="true"
                        aria-label="Dashboard geral"
                        title="Dashboard geral"
                    >
                        <span class="sidebar-view-toggle-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M4 11.5 12 5l8 6.5"></path>
                                <path d="M6.5 10v9h11v-9"></path>
                                <path d="M10 19v-5h4v5"></path>
                            </svg>
                        </span>
                    </button>
                    <div class="workspace-sidebar-heading-row">
                        <button
                            type="button"
                            class="sidebar-view-toggle workspace-mobile-menu-button"
                            data-mobile-sidebar-toggle
                            aria-expanded="false"
                            aria-controls="workspace-sidebar-menu"
                            aria-label="Abrir menu do workspace"
                            title="Menu"
                        >
                            <span class="workspace-mobile-menu-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M4 7h16"></path>
                                    <path d="M4 12h16"></path>
                                    <path d="M4 17h16"></path>
                                </svg>
                            </span>
                        </button>
                        <details class="workspace-sidebar-picker">
                            <summary aria-label="Trocar workspace">
                                <?php include __DIR__ . '/workspace_sidebar_picker_summary.php'; ?>
                                <span class="workspace-sidebar-picker-caret" aria-hidden="true">&#9662;</span>
                            </summary>
                            <div class="workspace-sidebar-picker-menu">
                                <div class="workspace-sidebar-picker-list">
                                    <?php foreach ($userWorkspaces as $workspaceOption): ?>
                                        <?php
                                        $workspaceOptionId = (int) ($workspaceOption['id'] ?? 0);
                                        $workspaceOptionName = (string) ($workspaceOption['name'] ?? 'Workspace');
                                        $isCurrentWorkspace = $currentWorkspaceId === $workspaceOptionId;
                                        ?>
                                        <?php if ($isCurrentWorkspace): ?>
                                            <span class="workspace-sidebar-picker-current">
                                                <?= renderWorkspaceAvatar($workspaceOption, 'avatar small workspace-sidebar-picker-avatar', true, 'span') ?>
                                                <span class="workspace-sidebar-picker-item-text" title="<?= e($workspaceOptionName) ?>"><?= e($workspaceOptionName) ?></span>
                                            </span>
                                        <?php else: ?>
                                            <form method="post" class="workspace-sidebar-picker-form">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="switch_workspace">
                                                <input type="hidden" name="workspace_id" value="<?= e((string) $workspaceOptionId) ?>">
                                                <button type="submit" class="workspace-sidebar-picker-option" title="<?= e($workspaceOptionName) ?>">
                                                    <?= renderWorkspaceAvatar($workspaceOption, 'avatar small workspace-sidebar-picker-avatar', true, 'span') ?>
                                                    <span class="workspace-sidebar-picker-item-text" title="<?= e($workspaceOptionName) ?>"><?= e($workspaceOptionName) ?></span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <button
                                    type="button"
                                    class="workspace-sidebar-create-trigger"
                                    data-open-workspace-create-modal
                                >
                                    <span aria-hidden="true">+</span>
                                    <span>Criar workspace</span>
                                </button>
                            </div>
                        </details>
                        <div class="workspace-sidebar-heading-actions">
                            <?php if (!empty($showUsersDashboardTab)): ?>
                                <button
                                    type="button"
                                    class="sidebar-view-toggle workspace-users-settings-button"
                                    data-dashboard-view-toggle
                                    data-view="users"
                                    aria-pressed="false"
                                    aria-label="Configuracoes do workspace"
                                    title="Configuracoes do workspace"
                                >
                                    <span class="workspace-users-settings-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" focusable="false">
                                            <path d="M4 6h16"></path>
                                            <path d="M4 12h16"></path>
                                            <path d="M4 18h16"></path>
                                            <circle cx="9" cy="6" r="2"></circle>
                                            <circle cx="15" cy="12" r="2"></circle>
                                            <circle cx="11" cy="18" r="2"></circle>
                                        </svg>
                                    </span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <nav class="sidebar-view-menu" id="workspace-sidebar-menu" aria-label="Menu do workspace">
                    <button
                        type="button"
                        class="sidebar-view-toggle"
                        data-dashboard-view-toggle
                        data-view="tasks"
                        aria-pressed="false"
                    >
                        <span class="sidebar-view-toggle-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M8 7h11"></path>
                                <path d="M8 12h11"></path>
                                <path d="M8 17h11"></path>
                                <path d="M4.5 7h.01"></path>
                                <path d="M4.5 12h.01"></path>
                                <path d="M4.5 17h.01"></path>
                            </svg>
                        </span>
                        <span class="sidebar-view-toggle-label">Lista de tarefas</span>
                    </button>
                    <button
                        type="button"
                        class="sidebar-view-toggle"
                        data-dashboard-view-toggle
                        data-view="vault"
                        aria-pressed="false"
                    >
                        <span class="sidebar-view-toggle-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <rect x="5" y="10" width="14" height="10" rx="2"></rect>
                                <path d="M8 10V7a4 4 0 1 1 8 0v3"></path>
                            </svg>
                        </span>
                        <span class="sidebar-view-toggle-label">Gerenciador de acessos</span>
                    </button>
                    <button
                        type="button"
                        class="sidebar-view-toggle"
                        data-dashboard-view-toggle
                        data-view="dues"
                        aria-pressed="false"
                    >
                        <span class="sidebar-view-toggle-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <rect x="4" y="5" width="16" height="15" rx="2"></rect>
                                <path d="M8 3v4"></path>
                                <path d="M16 3v4"></path>
                                <path d="M4 9h16"></path>
                                <path d="M8 13h3"></path>
                            </svg>
                        </span>
                        <span class="sidebar-view-toggle-label">Vencimentos</span>
                    </button>
                    <button
                        type="button"
                        class="sidebar-view-toggle"
                        data-dashboard-view-toggle
                        data-view="inventory"
                        aria-pressed="false"
                    >
                        <span class="sidebar-view-toggle-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M4 7.5 12 4l8 3.5-8 3.5-8-3.5Z"></path>
                                <path d="M4 12.5 12 16l8-3.5"></path>
                                <path d="M4 17.5 12 21l8-3.5"></path>
                                <path d="M12 11v10"></path>
                            </svg>
                        </span>
                        <span class="sidebar-view-toggle-label">Estoque</span>
                    </button>
                    <button
                        type="button"
                        class="sidebar-view-toggle"
                        data-dashboard-view-toggle
                        data-view="accounting"
                        aria-pressed="false"
                    >
                        <span class="sidebar-view-toggle-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <circle cx="12" cy="12" r="8"></circle>
                                <path d="M12 8v8"></path>
                                <path d="M9.5 9.5h4"></path>
                                <path d="M9.5 14.5h4"></path>
                            </svg>
                        </span>
                        <span class="sidebar-view-toggle-label">Contabilidade</span>
                    </button>
                </nav>
            </div>
        </aside>

        <header class="top-nav dashboard-nav dashboard-content-nav">
            <section class="stats-strip dashboard-stats dashboard-nav-stats" aria-label="Indicadores do workspace">
                <div class="stat-cell">
                    <span>Tarefas</span>
                    <strong data-dashboard-stat-total><?= e((string) $stats['total']) ?></strong>
                </div>
                <div class="stat-cell">
                    <span>Concluidas</span>
                    <strong data-dashboard-stat-done><?= e((string) $stats['done']) ?> (<?= e((string) $completionRate) ?>%)</strong>
                </div>
                <div class="stat-cell">
                    <span>Para hoje</span>
                    <strong data-dashboard-stat-due-today><?= e((string) $stats['due_today']) ?></strong>
                </div>
                <div class="stat-cell">
                    <span>Urgentes</span>
                    <strong data-dashboard-stat-urgent><?= e((string) $stats['urgent']) ?></strong>
                </div>
                <div class="stat-cell">
                    <span>Minhas abertas</span>
                    <strong data-dashboard-stat-my-open><?= e((string) $myOpenTasks) ?></strong>
                </div>
            </section>

            <div class="dashboard-nav-main">
                <div class="user-chip">
                    <?= renderUserAvatar($currentUser) ?>
                    <div>
                        <strong><?= e((string) $currentUser['name']) ?></strong>
                        <span><?= e((string) $currentUser['email']) ?></span>
                    </div>
                </div>

                <div class="top-nav-actions">
                    <div class="header-notification-menu" data-header-notifications>
                        <button
                            type="button"
                            class="header-notification-button"
                            data-header-notifications-toggle
                            aria-label="Notificacoes"
                            aria-haspopup="true"
                            aria-expanded="false"
                        >
                            <span class="header-notification-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M7.5 9.5a4.5 4.5 0 1 1 9 0v2.2c0 1.2.4 2.4 1.1 3.4l.9 1.3H5.5l.9-1.3c.7-1 1.1-2.2 1.1-3.4V9.5Z"></path>
                                    <path d="M10 19a2 2 0 0 0 4 0"></path>
                                </svg>
                            </span>
                            <span class="header-notification-count" data-header-notifications-count hidden>0</span>
                        </button>
                        <div
                            class="header-notification-dropdown"
                            data-header-notifications-dropdown
                            role="menu"
                            aria-label="Lista de notificacoes"
                            hidden
                        >
                            <div class="header-notification-dropdown-head">
                                <strong>Notificacoes</strong>
                            </div>
                            <div class="header-notification-list" data-header-notifications-list>
                                <p class="header-notification-empty">Sem notificacoes.</p>
                            </div>
                        </div>
                    </div>
                    <a
                        href="account-settings.php"
                        class="icon-gear-button top-account-settings-button"
                        aria-label="Configuracoes da conta"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M10.3 2.6h3.4l.5 2a7.8 7.8 0 0 1 1.9.8l1.8-1 2.4 2.4-1 1.8c.3.6.6 1.2.8 1.9l2 .5v3.4l-2 .5a7.8 7.8 0 0 1-.8 1.9l1 1.8-2.4 2.4-1.8-1a7.8 7.8 0 0 1-1.9.8l-.5 2h-3.4l-.5-2a7.8 7.8 0 0 1-1.9-.8l-1.8 1-2.4-2.4 1-1.8a7.8 7.8 0 0 1-.8-1.9l-2-.5v-3.4l2-.5c.2-.7.5-1.3.8-1.9l-1-1.8 2.4-2.4 1.8 1c.6-.3 1.2-.6 1.9-.8l.5-2Z"></path>
                            <circle cx="12" cy="12" r="3.2"></circle>
                        </svg>
                    </a>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn btn-pill btn-logout"><span>Sair</span></button>
                    </form>
                </div>
            </div>
        </header>

        <section class="overview-wrap panel" id="overview" data-dashboard-view-panel="overview">
            <?php if (false): ?>
            <?php
            $overviewExecutiveTone = (string) ($globalDashboardOverview['executive_status_tone'] ?? 'stable');
            $overviewExecutiveFocusTotal = (int) ($globalDashboardOverview['executive_focus_total'] ?? 0);
            $overviewExecutiveHeadline = $overviewExecutiveFocusTotal > 0
                ? $overviewExecutiveFocusTotal . ' ponto(s) pedem atencao hoje'
                : 'Operacao sob controle hoje';
            $overviewWorkspaceSummaries = array_values((array) ($globalDashboardOverview['workspace_summaries'] ?? []));
            $overviewTopWorkspaceSummaries = array_slice($overviewWorkspaceSummaries, 0, 4);
            $overviewMaxAttentionScore = 0;
            $overviewNegativeWorkspaceTotal = 0;
            foreach ($overviewWorkspaceSummaries as $overviewWorkspaceSummaryItem) {
                $overviewMaxAttentionScore = max(
                    $overviewMaxAttentionScore,
                    (int) ($overviewWorkspaceSummaryItem['attention_score'] ?? 0)
                );
                if ((int) ($overviewWorkspaceSummaryItem['balance_total_cents'] ?? 0) < 0) {
                    $overviewNegativeWorkspaceTotal++;
                }
            }
            $overviewImportantHighlights = [];
            $overviewDueSoonTotal = (int) ($globalDashboardOverview['due_soon_total'] ?? 0);
            $overviewTasksTodayTotal = (int) ($globalDashboardOverview['tasks_today_total'] ?? 0);
            $overviewUrgentTasksTodayTotal = (int) ($globalDashboardOverview['urgent_tasks_today_total'] ?? 0);
            $overviewLowStockTotal = (int) ($globalDashboardOverview['low_stock_total'] ?? 0);

            if ($overviewDueSoonTotal > 0) {
                $overviewImportantHighlights[] = [
                    'label' => 'Contas a vencer',
                    'value' => $overviewDueSoonTotal,
                ];
            }

            if ($overviewTasksTodayTotal > 0) {
                $overviewImportantHighlights[] = [
                    'label' => 'Tarefas do dia',
                    'value' => $overviewTasksTodayTotal,
                ];
            }

            if ($overviewUrgentTasksTodayTotal > 0) {
                $overviewImportantHighlights[] = [
                    'label' => 'Tarefas urgentes',
                    'value' => $overviewUrgentTasksTodayTotal,
                ];
            }

            if ($overviewLowStockTotal > 0) {
                $overviewImportantHighlights[] = [
                    'label' => 'Baixo estoque',
                    'value' => $overviewLowStockTotal,
                ];
            }
            $overviewPriorityTasksTodayTotal = (int) ($globalDashboardOverview['priority_tasks_today_total'] ?? 0);
            $overviewDueTodayTotal = (int) ($globalDashboardOverview['due_today_total'] ?? 0);
            $overviewDueTomorrowTotal = (int) ($globalDashboardOverview['due_tomorrow_total'] ?? 0);
            $overviewAttentionWorkspaceTotal = (int) ($globalDashboardOverview['attention_workspace_total'] ?? 0);
            $overviewBalanceTotalCents = (int) ($globalDashboardOverview['balance_total_cents'] ?? 0);
            $showOverviewTasksStatCard = $overviewTasksTodayTotal > 0;
            $showOverviewDueStatCard = $overviewDueSoonTotal > 0 || $overviewDueTodayTotal > 0 || $overviewDueTomorrowTotal > 0;
            $showOverviewBalanceStatCard = $overviewBalanceTotalCents !== 0;
            $showOverviewLowStockStatCard = $overviewLowStockTotal > 0;
            ?>
            <div class="panel-header board-header overview-board-header">
                <div>
                    <h2>Dashboard geral</h2>
                    <p>Resumo pessoal de todos os workspaces.</p>
                </div>
                <?php if (!empty($overviewImportantHighlights)): ?>
                    <div class="board-summary overview-board-summary" aria-label="Indicadores importantes">
                        <?php foreach ($overviewImportantHighlights as $overviewImportantHighlight): ?>
                            <span class="overview-summary-pill">
                                <?= e((string) ($overviewImportantHighlight['label'] ?? '')) ?>: <?= e((string) ($overviewImportantHighlight['value'] ?? 0)) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="overview-executive-grid" style="--overview-order: 0;">
                <section class="overview-executive-strip is-<?= e($overviewExecutiveTone) ?>" aria-label="Prioridades do dia">
                    <div class="overview-executive-main">
                        <span class="overview-executive-kicker">Prioridade do dia</span>
                        <div class="overview-executive-heading">
                            <h3><?= e($overviewExecutiveHeadline) ?></h3>
                            <span class="overview-executive-badge is-<?= e($overviewExecutiveTone) ?>">
                                <?= e((string) ($globalDashboardOverview['executive_status_label'] ?? 'Operacao estavel')) ?>
                            </span>
                        </div>
                        <p><?= e((string) ($globalDashboardOverview['executive_status_note'] ?? '')) ?></p>
                        <?php if (!empty($overviewImportantHighlights)): ?>
                            <div class="overview-executive-chips" aria-label="Indicadores rapidos">
                                <?php foreach ($overviewImportantHighlights as $overviewImportantHighlight): ?>
                                    <span class="overview-executive-chip">
                                        <?= e((string) ($overviewImportantHighlight['label'] ?? '')) ?>: <?= e((string) ($overviewImportantHighlight['value'] ?? 0)) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <dl class="overview-executive-focus">
                        <div class="overview-focus-card is-critical">
                            <dt>Urgentes hoje</dt>
                            <dd><?= e((string) ($globalDashboardOverview['urgent_tasks_today_total'] ?? 0)) ?></dd>
                            <small>Tarefas com prioridade maxima</small>
                        </div>
                        <div class="overview-focus-card is-due">
                            <dt>Vence hoje</dt>
                            <dd><?= e((string) ($globalDashboardOverview['due_today_total'] ?? 0)) ?></dd>
                            <small>Contas com decisao imediata</small>
                        </div>
                        <div class="overview-focus-card is-balance">
                            <dt>Fluxo do mes</dt>
                            <dd><?= e((string) ($globalDashboardOverview['balance_month_movement_display'] ?? 'R$ 0,00')) ?></dd>
                            <small>Movimento consolidado atual</small>
                        </div>
                    </dl>
                </section>

                <aside class="overview-radar-card" aria-label="Radar de workspaces">
                    <div class="overview-radar-top">
                        <div>
                            <span class="overview-radar-kicker">Radar</span>
                            <h3>Workspaces sob maior pressao</h3>
                            <p>Comparativo rapido para decidir onde entrar primeiro.</p>
                        </div>
                        <div class="overview-radar-summary">
                            <span class="overview-summary-pill">Criticos <?= e((string) ($globalDashboardOverview['critical_workspace_total'] ?? 0)) ?></span>
                            <span class="overview-summary-pill">Saldo negativo <?= e((string) $overviewNegativeWorkspaceTotal) ?></span>
                        </div>
                    </div>
                    <?php if (empty($overviewTopWorkspaceSummaries)): ?>
                        <div class="overview-empty-state">
                            <span class="overview-empty-icon" aria-hidden="true"></span>
                            <div class="overview-empty-copy">
                                <strong>Nenhum comparativo disponivel</strong>
                                <span>Assim que houver workspaces visiveis, o radar aparece aqui.</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <ol class="overview-radar-list">
                            <?php foreach ($overviewTopWorkspaceSummaries as $radarIndex => $workspaceSummary): ?>
                                <?php
                                $workspaceSummaryId = (int) ($workspaceSummary['workspace_id'] ?? 0);
                                $workspaceAttentionTone = (string) ($workspaceSummary['attention_tone'] ?? 'stable');
                                $workspaceAttentionScore = (int) ($workspaceSummary['attention_score'] ?? 0);
                                $workspaceAttentionShare = $overviewMaxAttentionScore > 0
                                    ? (int) max(
                                        $workspaceAttentionScore > 0 ? 14 : 0,
                                        round(($workspaceAttentionScore / $overviewMaxAttentionScore) * 100)
                                    )
                                    : 0;
                                ?>
                                <li class="overview-radar-item is-<?= e($workspaceAttentionTone) ?>" style="--overview-item-order: <?= e((string) ($radarIndex + 1)) ?>;">
                                    <div class="overview-radar-item-top">
                                        <span class="overview-radar-rank"><?= e(str_pad((string) ($radarIndex + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                                        <div class="overview-radar-item-main">
                                            <div class="overview-radar-item-heading">
                                                <strong><?= e((string) ($workspaceSummary['workspace_name'] ?? 'Workspace')) ?></strong>
                                                <?php if ($workspaceSummaryId === (int) ($currentWorkspaceId ?? 0)): ?>
                                                    <span class="overview-workspace-current">Ativo</span>
                                                <?php endif; ?>
                                            </div>
                                            <p><?= e((string) ($workspaceSummary['attention_note'] ?? 'Sem pendencias imediatas.')) ?></p>
                                        </div>
                                        <span class="overview-workspace-health is-<?= e($workspaceAttentionTone) ?>">
                                            <?= e((string) ($workspaceSummary['attention_label'] ?? 'Estavel')) ?>
                                        </span>
                                    </div>
                                    <div class="overview-radar-meter" style="--attention-share: <?= e((string) $workspaceAttentionShare) ?>%;">
                                        <span class="overview-radar-meter-fill is-<?= e($workspaceAttentionTone) ?>"></span>
                                    </div>
                                    <dl class="overview-radar-kpis">
                                        <div>
                                            <dt>Score</dt>
                                            <dd><?= e((string) $workspaceAttentionScore) ?></dd>
                                        </div>
                                        <div>
                                            <dt>Hoje</dt>
                                            <dd><?= e((string) ($workspaceSummary['tasks_today_count'] ?? 0)) ?></dd>
                                        </div>
                                        <div>
                                            <dt>Saldo</dt>
                                            <dd><?= e((string) ($workspaceSummary['balance_total_display'] ?? 'R$ 0,00')) ?></dd>
                                        </div>
                                    </dl>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </aside>
            </div>

            <?php if ($showOverviewTasksStatCard || $showOverviewDueStatCard || $showOverviewBalanceStatCard || $showOverviewLowStockStatCard): ?>
                <section class="stats-strip overview-stats" style="--overview-order: 1;" aria-label="Resumo geral do usuario">
                    <?php if ($showOverviewTasksStatCard): ?>
                        <article class="stat-cell overview-stat-card is-tasks">
                            <span>Tarefas de hoje</span>
                            <strong><?= e((string) $overviewTasksTodayTotal) ?></strong>
                            <small class="overview-stat-note"><?= e((string) $overviewPriorityTasksTodayTotal) ?> com alta prioridade</small>
                        </article>
                    <?php endif; ?>
                    <?php if ($showOverviewDueStatCard): ?>
                        <article class="stat-cell overview-stat-card is-due">
                            <span>Vencimentos proximos</span>
                            <strong><?= e((string) $overviewDueSoonTotal) ?></strong>
                            <small class="overview-stat-note"><?= e((string) $overviewDueTodayTotal) ?> hoje, <?= e((string) $overviewDueTomorrowTotal) ?> amanha</small>
                        </article>
                    <?php endif; ?>
                    <?php if ($showOverviewBalanceStatCard): ?>
                        <article class="stat-cell overview-stat-card is-balance">
                            <span>Saldo atual</span>
                            <strong><?= e((string) ($globalDashboardOverview['balance_total_display'] ?? 'R$ 0,00')) ?></strong>
                            <small class="overview-stat-note">Mov. mes <?= e((string) ($globalDashboardOverview['balance_month_movement_display'] ?? 'R$ 0,00')) ?></small>
                        </article>
                    <?php endif; ?>
                    <?php if ($showOverviewLowStockStatCard): ?>
                        <article class="stat-cell overview-stat-card is-stock">
                            <span>Baixo estoque</span>
                            <strong><?= e((string) $overviewLowStockTotal) ?></strong>
                            <small class="overview-stat-note"><?= e((string) $overviewAttentionWorkspaceTotal) ?> workspace(s) em monitoramento</small>
                        </article>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <div class="overview-panels-grid" style="--overview-order: 2;">
                <section class="overview-card overview-card-tasks">
                    <div class="overview-card-top">
                        <header class="overview-card-head">
                            <h3>Tarefas do dia</h3>
                            <span class="overview-card-count"><?= e((string) ($globalDashboardOverview['tasks_today_total'] ?? 0)) ?></span>
                        </header>
                        <button type="button" class="overview-card-action" data-dashboard-view-toggle data-view="tasks">
                            Abrir tarefas
                        </button>
                    </div>
                    <div class="overview-card-inline-metrics" aria-label="Resumo rapido de tarefas">
                        <span class="overview-card-inline-chip is-critical">Urgentes <?= e((string) ($globalDashboardOverview['urgent_tasks_today_total'] ?? 0)) ?></span>
                        <span class="overview-card-inline-chip">Alta prioridade <?= e((string) ($globalDashboardOverview['priority_tasks_today_total'] ?? 0)) ?></span>
                    </div>
                    <?php if (empty($globalDashboardOverview['tasks_today'])): ?>
                        <div class="overview-empty-state">
                            <span class="overview-empty-icon" aria-hidden="true"></span>
                            <div class="overview-empty-copy">
                                <strong>Nenhuma tarefa sua para hoje</strong>
                                <span>Seu foco esta livre neste momento.</span>
                            </div>
                            <button type="button" class="overview-empty-action" data-dashboard-view-toggle data-view="tasks">
                                Abrir tarefas
                            </button>
                        </div>
                    <?php else: ?>
                        <ul class="overview-list">
                            <?php foreach ((array) $globalDashboardOverview['tasks_today'] as $taskToday): ?>
                                <?php $taskPriorityClass = normalizeTaskPriority((string) ($taskToday['priority'] ?? 'medium')); ?>
                                <li class="overview-list-item overview-list-item-task priority-<?= e($taskPriorityClass) ?>">
                                    <div class="overview-list-main">
                                        <strong><?= e((string) ($taskToday['title'] ?? 'Tarefa')) ?></strong>
                                        <div class="overview-list-tags">
                                            <span class="overview-list-tag"><?= e((string) ($taskToday['workspace_name'] ?? 'Workspace')) ?></span>
                                            <span class="overview-list-tag is-muted"><?= e((string) ($taskToday['group_name'] ?? 'Geral')) ?></span>
                                        </div>
                                    </div>
                                    <span class="overview-priority priority-<?= e($taskPriorityClass) ?>">
                                        <?= e((string) ($taskToday['priority_label'] ?? 'Media')) ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="overview-card overview-card-due">
                    <div class="overview-card-top">
                        <header class="overview-card-head">
                            <h3>Contas para vencer</h3>
                            <span class="overview-card-count"><?= e((string) ($globalDashboardOverview['due_soon_total'] ?? 0)) ?></span>
                        </header>
                        <button type="button" class="overview-card-action" data-dashboard-view-toggle data-view="dues">
                            Abrir vencimentos
                        </button>
                    </div>
                    <div class="overview-card-inline-metrics" aria-label="Resumo rapido de vencimentos">
                        <span class="overview-card-inline-chip is-critical">Hoje <?= e((string) ($globalDashboardOverview['due_today_total'] ?? 0)) ?></span>
                        <span class="overview-card-inline-chip is-attention">Amanha <?= e((string) ($globalDashboardOverview['due_tomorrow_total'] ?? 0)) ?></span>
                    </div>
                    <?php if (empty($globalDashboardOverview['due_soon'])): ?>
                        <div class="overview-empty-state">
                            <span class="overview-empty-icon" aria-hidden="true"></span>
                            <div class="overview-empty-copy">
                                <strong>Sem vencimentos no curto prazo</strong>
                                <span>Nenhuma conta vence na janela configurada.</span>
                            </div>
                            <button type="button" class="overview-empty-action" data-dashboard-view-toggle data-view="dues">
                                Abrir vencimentos
                            </button>
                        </div>
                    <?php else: ?>
                        <ul class="overview-list">
                            <?php foreach ((array) $globalDashboardOverview['due_soon'] as $dueSoonItem): ?>
                                <?php
                                $dueDaysLabel = trim((string) ($dueSoonItem['days_until_label'] ?? ''));
                                $dueDateLabel = trim((string) ($dueSoonItem['next_due_display'] ?? ''));
                                $dueWhenLabel = $dueDaysLabel !== '' && $dueDateLabel !== ''
                                    ? $dueDaysLabel . ' (' . $dueDateLabel . ')'
                                    : ($dueDaysLabel !== '' ? $dueDaysLabel : $dueDateLabel);
                                $dueDaysUntil = $dueSoonItem['days_until'] ?? null;
                                $dueToneClass = 'calm';
                                if ($dueDaysUntil === 0) {
                                    $dueToneClass = 'critical';
                                } elseif ($dueDaysUntil === 1) {
                                    $dueToneClass = 'attention';
                                }
                                ?>
                                <li class="overview-list-item overview-list-item-due tone-<?= e($dueToneClass) ?>">
                                    <div class="overview-list-main">
                                        <strong><?= e((string) ($dueSoonItem['label'] ?? 'Vencimento')) ?></strong>
                                        <div class="overview-list-tags">
                                            <span class="overview-list-tag"><?= e((string) ($dueSoonItem['workspace_name'] ?? 'Workspace')) ?></span>
                                            <span class="overview-list-tag is-muted"><?= e((string) ($dueSoonItem['group_name'] ?? 'Geral')) ?></span>
                                        </div>
                                    </div>
                                    <div class="overview-list-aside">
                                        <span class="overview-when is-<?= e($dueToneClass) ?>"><?= e($dueWhenLabel) ?></span>
                                        <span class="overview-amount"><?= e((string) ($dueSoonItem['amount_display'] ?? 'R$ 0,00')) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="overview-card overview-card-stock">
                    <div class="overview-card-top">
                        <header class="overview-card-head">
                            <h3>Itens com baixo estoque</h3>
                            <span class="overview-card-count"><?= e((string) ($globalDashboardOverview['low_stock_total'] ?? 0)) ?></span>
                        </header>
                        <button type="button" class="overview-card-action" data-dashboard-view-toggle data-view="inventory">
                            Abrir estoque
                        </button>
                    </div>
                    <div class="overview-card-inline-metrics" aria-label="Resumo rapido de estoque">
                        <span class="overview-card-inline-chip is-attention">Itens abaixo do minimo <?= e((string) ($globalDashboardOverview['low_stock_total'] ?? 0)) ?></span>
                        <span class="overview-card-inline-chip">Workspaces em foco <?= e((string) (((int) ($globalDashboardOverview['critical_workspace_total'] ?? 0)) + ((int) ($globalDashboardOverview['attention_workspace_total'] ?? 0)))) ?></span>
                    </div>
                    <?php if (empty($globalDashboardOverview['low_stock'])): ?>
                        <div class="overview-empty-state">
                            <span class="overview-empty-icon" aria-hidden="true"></span>
                            <div class="overview-empty-copy">
                                <strong>Nenhum item abaixo do minimo</strong>
                                <span>Seu estoque visivel esta equilibrado agora.</span>
                            </div>
                            <button type="button" class="overview-empty-action" data-dashboard-view-toggle data-view="inventory">
                                Abrir estoque
                            </button>
                        </div>
                    <?php else: ?>
                        <ul class="overview-list">
                            <?php foreach ((array) $globalDashboardOverview['low_stock'] as $lowStockItem): ?>
                                <li class="overview-list-item overview-list-item-stock">
                                    <div class="overview-list-main">
                                        <strong><?= e((string) ($lowStockItem['label'] ?? 'Item')) ?></strong>
                                        <div class="overview-list-tags">
                                            <span class="overview-list-tag"><?= e((string) ($lowStockItem['workspace_name'] ?? 'Workspace')) ?></span>
                                            <span class="overview-list-tag is-muted"><?= e((string) ($lowStockItem['group_name'] ?? 'Geral')) ?></span>
                                        </div>
                                    </div>
                                    <div class="overview-list-aside">
                                        <span class="overview-stock-meta"><?= e((string) ($lowStockItem['quantity_display'] ?? '0')) ?>/<?= e((string) ($lowStockItem['min_quantity_display'] ?? '0')) ?> <?= e((string) ($lowStockItem['unit_label'] ?? 'un')) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

            <section class="overview-card overview-workspaces-card" style="--overview-order: 3;">
                <div class="overview-card-top">
                    <header class="overview-card-head">
                        <h3>Resumo por workspace</h3>
                        <span class="overview-card-count"><?= e((string) count($overviewWorkspaceSummaries)) ?></span>
                    </header>
                    <p class="overview-card-subtitle">Saldo referente a <?= e((string) ($globalDashboardOverview['accounting_period_label'] ?? '')) ?></p>
                </div>
                <?php if (empty($overviewWorkspaceSummaries)): ?>
                    <div class="overview-empty-state">
                        <span class="overview-empty-icon" aria-hidden="true"></span>
                        <div class="overview-empty-copy">
                            <strong>Nenhum workspace encontrado</strong>
                            <span>Sua conta ainda nao possui workspaces visiveis para resumir.</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="overview-workspaces-list">
                        <?php foreach ($overviewWorkspaceSummaries as $workspaceIndex => $workspaceSummary): ?>
                            <?php
                            $workspaceSummaryId = (int) ($workspaceSummary['workspace_id'] ?? 0);
                            $isActiveWorkspaceSummary = $workspaceSummaryId === (int) ($currentWorkspaceId ?? 0);
                            $workspaceAttentionTone = (string) ($workspaceSummary['attention_tone'] ?? 'stable');
                            $workspaceAttentionScore = (int) ($workspaceSummary['attention_score'] ?? 0);
                            $workspaceAttentionShare = $overviewMaxAttentionScore > 0
                                ? (int) max(
                                    $workspaceAttentionScore > 0 ? 14 : 0,
                                    round(($workspaceAttentionScore / $overviewMaxAttentionScore) * 100)
                                )
                                : 0;
                            ?>
                            <article class="overview-workspace-item is-<?= e($workspaceAttentionTone) ?><?= $isActiveWorkspaceSummary ? ' is-active-workspace' : '' ?>" style="--overview-item-order: <?= e((string) ($workspaceIndex + 1)) ?>;">
                                <div class="overview-workspace-meta">
                                    <div class="overview-workspace-title">
                                        <div class="overview-workspace-title-row">
                                            <strong><?= e((string) ($workspaceSummary['workspace_name'] ?? 'Workspace')) ?></strong>
                                            <span class="overview-workspace-health is-<?= e($workspaceAttentionTone) ?>">
                                                <?= e((string) ($workspaceSummary['attention_label'] ?? 'Estavel')) ?>
                                            </span>
                                        </div>
                                        <div class="overview-workspace-badges">
                                            <span class="overview-workspace-role"><?= e((string) ($workspaceSummary['workspace_role_label'] ?? 'Usuario')) ?></span>
                                            <?php if ((int) ($workspaceSummary['urgent_tasks_today_count'] ?? 0) > 0): ?>
                                                <span class="overview-workspace-chip is-critical"><?= e((string) ($workspaceSummary['urgent_tasks_today_count'] ?? 0)) ?> urgente(s)</span>
                                            <?php elseif ((int) ($workspaceSummary['due_today_count'] ?? 0) > 0): ?>
                                                <span class="overview-workspace-chip is-attention"><?= e((string) ($workspaceSummary['due_today_count'] ?? 0)) ?> vence hoje</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="overview-workspace-actions">
                                        <?php if ($isActiveWorkspaceSummary): ?>
                                            <span class="overview-workspace-current">Ativo</span>
                                        <?php elseif ($workspaceSummaryId > 0): ?>
                                            <form method="post" class="overview-workspace-open-form">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="switch_workspace">
                                                <input type="hidden" name="workspace_id" value="<?= e((string) $workspaceSummaryId) ?>">
                                                <button type="submit" class="overview-workspace-open">Abrir</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="overview-workspace-note"><?= e((string) ($workspaceSummary['attention_note'] ?? 'Sem pendencias imediatas.')) ?></p>
                                <div class="overview-workspace-meter">
                                    <div class="overview-workspace-meter-meta">
                                        <span>Pressao operacional</span>
                                        <strong>Score <?= e((string) $workspaceAttentionScore) ?></strong>
                                    </div>
                                    <div class="overview-radar-meter" style="--attention-share: <?= e((string) $workspaceAttentionShare) ?>%;">
                                        <span class="overview-radar-meter-fill is-<?= e($workspaceAttentionTone) ?>"></span>
                                    </div>
                                </div>
                                <dl class="overview-workspace-kpis">
                                    <div class="overview-workspace-kpi is-today">
                                        <dt>Hoje</dt>
                                        <dd><?= e((string) ($workspaceSummary['tasks_today_count'] ?? 0)) ?></dd>
                                    </div>
                                    <div class="overview-workspace-kpi is-critical">
                                        <dt>Urgente</dt>
                                        <dd><?= e((string) ($workspaceSummary['urgent_tasks_today_count'] ?? 0)) ?></dd>
                                    </div>
                                    <div class="overview-workspace-kpi is-due">
                                        <dt>Vence hoje</dt>
                                        <dd><?= e((string) ($workspaceSummary['due_today_count'] ?? 0)) ?></dd>
                                    </div>
                                    <div class="overview-workspace-kpi is-tomorrow">
                                        <dt>Amanha</dt>
                                        <dd><?= e((string) ($workspaceSummary['due_tomorrow_count'] ?? 0)) ?></dd>
                                    </div>
                                    <div class="overview-workspace-kpi is-stock">
                                        <dt>Baixo</dt>
                                        <dd><?= e((string) ($workspaceSummary['low_stock_count'] ?? 0)) ?></dd>
                                    </div>
                                    <div class="overview-workspace-kpi is-balance">
                                        <dt>Saldo</dt>
                                        <dd><?= e((string) ($workspaceSummary['balance_total_display'] ?? 'R$ 0,00')) ?></dd>
                                    </div>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <?php else: ?>
                <?php require __DIR__ . '/dashboard_overview_summary.php'; ?>
            <?php endif; ?>
        </section>

        <section class="tasklist-wrap panel" id="tasks" data-dashboard-view-panel="tasks" hidden>
            <div class="panel-header board-header">
                <div>
                    <h2>Lista de tarefas</h2>
                </div>
                <div class="board-summary">
                    <span data-board-visible-count><?= e((string) count($tasks)) ?> visiveis</span>
                    <span data-board-total-count><?= e((string) $stats['total']) ?> total</span>
                </div>
            </div>

            <form method="get" class="task-filters" id="task-filters" data-task-filter-form>
                <label>
                    <?php $groupFilterValue = (string) ($groupFilter ?? ''); ?>
                    <div class="tag-field row-inline-picker-wrap" data-inline-select-wrap>
                        <details class="row-inline-picker filter-inline-picker" data-inline-select-picker>
                            <summary aria-label="Filtrar por grupo">
                                <span class="row-inline-picker-summary-text" data-inline-select-text>
                                    <?php if ($groupFilterValue === ''): ?>
                                        Todos Grupos
                                    <?php else: ?>
                                        <?= e($groupFilterValue) ?>
                                    <?php endif; ?>
                                </span>
                            </summary>
                            <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Filtro de grupo">
                                <button
                                    type="button"
                                    class="row-inline-picker-option<?= $groupFilterValue === '' ? ' is-active' : '' ?>"
                                    data-inline-select-option
                                    data-value=""
                                    data-label="Todos Grupos"
                                    role="option"
                                    aria-selected="<?= $groupFilterValue === '' ? 'true' : 'false' ?>"
                                >Todos Grupos</button>
                                <?php foreach ($taskGroups as $groupOption): ?>
                                    <button
                                        type="button"
                                        class="row-inline-picker-option<?= $groupFilterValue === (string) $groupOption ? ' is-active' : '' ?>"
                                        data-inline-select-option
                                        data-value="<?= e((string) $groupOption) ?>"
                                        data-label="<?= e((string) $groupOption) ?>"
                                        role="option"
                                        aria-selected="<?= $groupFilterValue === (string) $groupOption ? 'true' : 'false' ?>"
                                    ><?= e((string) $groupOption) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <select
                            name="group"
                            class="tag-select row-inline-picker-native"
                            data-inline-select-source
                            hidden
                        >
                            <option value="">Todos Grupos</option>
                            <?php foreach ($taskGroups as $groupOption): ?>
                                <option value="<?= e((string) $groupOption) ?>"<?= $groupFilterValue === (string) $groupOption ? ' selected' : '' ?>>
                                    <?= e((string) $groupOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </label>

                <label>
                    <?php $creatorFilterValue = $creatorFilterId !== null ? (string) $creatorFilterId : ''; ?>
                    <div class="tag-field row-inline-picker-wrap" data-inline-select-wrap>
                        <details class="row-inline-picker filter-inline-picker" data-inline-select-picker>
                            <summary aria-label="Filtrar por criador">
                                <span class="row-inline-picker-summary-text" data-inline-select-text>
                                    <?php if ($creatorFilterValue === ''): ?>
                                        Criado por
                                    <?php else: ?>
                                        <?php
                                        $creatorLabel = 'Criado por';
                                        foreach ($users as $user) {
                                            if ((string) ((int) $user['id']) === $creatorFilterValue) {
                                                $creatorLabel = (string) $user['name'];
                                                break;
                                            }
                                        }
                                        ?>
                                        <?= e($creatorLabel) ?>
                                    <?php endif; ?>
                                </span>
                            </summary>
                            <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Filtro de criador">
                                <button
                                    type="button"
                                    class="row-inline-picker-option<?= $creatorFilterValue === '' ? ' is-active' : '' ?>"
                                    data-inline-select-option
                                    data-value=""
                                    data-label="Criado por"
                                    role="option"
                                    aria-selected="<?= $creatorFilterValue === '' ? 'true' : 'false' ?>"
                                >Criado por</button>
                                <?php foreach ($users as $user): ?>
                                    <?php $optionValue = (string) ((int) $user['id']); ?>
                                    <button
                                        type="button"
                                        class="row-inline-picker-option<?= $creatorFilterValue === $optionValue ? ' is-active' : '' ?>"
                                        data-inline-select-option
                                        data-value="<?= e($optionValue) ?>"
                                        data-label="<?= e((string) $user['name']) ?>"
                                        role="option"
                                        aria-selected="<?= $creatorFilterValue === $optionValue ? 'true' : 'false' ?>"
                                    ><?= e((string) $user['name']) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <select name="created_by" class="tag-select row-inline-picker-native" data-inline-select-source hidden>
                            <option value="">Criado por</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= e((string) $user['id']) ?>"<?= $creatorFilterId === (int) $user['id'] ? ' selected' : '' ?>>
                                    <?= e((string) $user['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </label>

                <label>
                    <?php $assigneeFilterValue = $assigneeFilterId !== null ? (string) $assigneeFilterId : ''; ?>
                    <div class="tag-field row-inline-picker-wrap" data-inline-select-wrap>
                        <details class="row-inline-picker filter-inline-picker" data-inline-select-picker>
                            <summary aria-label="Filtrar por responsavel">
                                <span class="row-inline-picker-summary-text" data-inline-select-text>
                                    <?php if ($assigneeFilterValue === ''): ?>
                                        Responsavel
                                    <?php else: ?>
                                        <?php
                                        $assigneeLabel = 'Responsavel';
                                        foreach ($users as $user) {
                                            if ((string) ((int) $user['id']) === $assigneeFilterValue) {
                                                $assigneeLabel = (string) $user['name'];
                                                break;
                                            }
                                        }
                                        ?>
                                        <?= e($assigneeLabel) ?>
                                    <?php endif; ?>
                                </span>
                            </summary>
                            <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Filtro de responsavel">
                                <button
                                    type="button"
                                    class="row-inline-picker-option<?= $assigneeFilterValue === '' ? ' is-active' : '' ?>"
                                    data-inline-select-option
                                    data-value=""
                                    data-label="Responsavel"
                                    role="option"
                                    aria-selected="<?= $assigneeFilterValue === '' ? 'true' : 'false' ?>"
                                >Responsavel</button>
                                <?php foreach ($users as $user): ?>
                                    <?php $optionValue = (string) ((int) $user['id']); ?>
                                    <button
                                        type="button"
                                        class="row-inline-picker-option<?= $assigneeFilterValue === $optionValue ? ' is-active' : '' ?>"
                                        data-inline-select-option
                                        data-value="<?= e($optionValue) ?>"
                                        data-label="<?= e((string) $user['name']) ?>"
                                        role="option"
                                        aria-selected="<?= $assigneeFilterValue === $optionValue ? 'true' : 'false' ?>"
                                    ><?= e((string) $user['name']) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <select name="assignee" class="tag-select row-inline-picker-native" data-inline-select-source hidden>
                            <option value="">Responsavel</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= e((string) $user['id']) ?>"<?= $assigneeFilterId === (int) $user['id'] ? ' selected' : '' ?>>
                                    <?= e((string) $user['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </label>

                <div class="task-filters-create">
                    <button
                        type="button"
                        class="icon-gear-button task-filters-reorder-groups"
                        data-toggle-task-group-reorder
                        aria-label="Ativar organizacao de grupos"
                        aria-pressed="false"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M8 7h10"></path>
                            <path d="M8 12h10"></path>
                            <path d="M8 17h10"></path>
                            <path d="M5 7h.01"></path>
                            <path d="M5 12h.01"></path>
                            <path d="M5 17h.01"></path>
                        </svg>
                    </button>
                    <?php if (!empty($canManageWorkspace)): ?>
                        <button
                            type="button"
                            class="icon-gear-button task-filters-create-group"
                            data-open-create-group-modal
                            aria-label="Criar grupo"
                        >
                            <span class="task-filters-create-group-plus" aria-hidden="true">+</span>
                            <span>Grupo</span>
                        </button>
                    <?php endif; ?>
                </div>
            </form>

            <datalist id="task-group-options">
                <?php foreach ($taskGroups as $groupNameOption): ?>
                    <option value="<?= e((string) $groupNameOption) ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <div class="task-groups-list" data-task-groups-list>
                <?php if (empty($tasksGroupedByGroup)): ?>
                    <div class="empty-card task-list-empty">
                        <p>Nenhuma tarefa encontrada com os filtros atuais.</p>
                        <button
                            type="button"
                            class="btn btn-mini"
                            data-open-create-task-modal
                            <?= empty($taskGroupsWithAccess) ? 'disabled' : '' ?>
                        >Nova tarefa</button>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasksGroupedByGroup as $groupName => $groupTasks): ?>
                        <?php
                        $taskGroupPermission = $taskGroupPermissions[$groupName] ?? ['can_view' => true, 'can_access' => true];
                        $taskGroupCanAccess = !empty($taskGroupPermission['can_access']);
                        $taskGroupPermissionsModalKey = 'task-group-perm-' . md5((string) $groupName);
                        ?>
                        <section
                            class="task-group<?= $taskGroupCanAccess ? '' : ' task-group-readonly' ?>"
                            aria-labelledby="group-<?= e(md5((string) $groupName)) ?>"
                            data-task-group
                            data-group-name="<?= e((string) $groupName) ?>"
                            data-group-can-access="<?= $taskGroupCanAccess ? '1' : '0' ?>"
                        >
                            <header class="task-group-head" data-task-group-head-toggle>
                                <div class="task-group-head-main">
                                    <form method="post" class="task-group-rename-form" data-group-rename-form>
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="rename_group">
                                        <input type="hidden" name="old_group_name" value="<?= e((string) $groupName) ?>">
                                        <h3 id="group-<?= e(md5((string) $groupName)) ?>">
                                            <input
                                                type="text"
                                                name="new_group_name"
                                                value="<?= e((string) $groupName) ?>"
                                                maxlength="60"
                                                class="task-group-name-input"
                                                data-group-name-input
                                                aria-label="Nome do grupo"
                                                spellcheck="false"
                                                <?= $taskGroupCanAccess ? '' : 'readonly' ?>
                                            >
                                        </h3>
                                    </form>
                                </div>
                                <div class="task-group-head-actions">
                                    <span class="task-group-collapse" data-group-toggle-indicator aria-hidden="true"><span>&#9662;</span></span>
                                    <button
                                        type="button"
                                        class="group-done-toggle-button"
                                        data-toggle-group-done
                                        data-label-hide="Ocultar concluidas"
                                        data-label-show="Exibir concluidas"
                                        aria-pressed="false"
                                        aria-label="Ocultar concluidas do grupo <?= e((string) $groupName) ?>"
                                    >Ocultar concluidas</button>
                                    <?php if (!empty($canManageWorkspace) && empty($isPersonalWorkspace)): ?>
                                        <button
                                            type="button"
                                            class="group-permissions-button"
                                            data-open-group-permissions-modal="<?= e($taskGroupPermissionsModalKey) ?>"
                                            aria-label="Gerenciar acesso do grupo <?= e((string) $groupName) ?>"
                                        >
                                            Acesso
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($taskGroupCanAccess): ?>
                                        <button
                                            type="button"
                                            class="group-add-button"
                                            data-open-create-task-modal
                                            data-create-group="<?= e((string) $groupName) ?>"
                                            aria-label="Criar tarefa no grupo <?= e((string) $groupName) ?>"
                                        >+</button>
                                        <form method="post" class="task-group-delete-form" data-group-delete-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_group">
                                            <input type="hidden" name="group_name" value="<?= e((string) $groupName) ?>">
                                            <button
                                                type="button"
                                                class="task-group-delete"
                                                data-group-delete
                                                aria-label="Excluir grupo <?= e((string) $groupName) ?>"
                                            ><span aria-hidden="true">&#10005;</span></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$taskGroupCanAccess): ?>
                                        <span class="task-group-readonly-tag">Somente leitura</span>
                                    <?php endif; ?>
                                    <span class="task-group-count"><?= e((string) count($groupTasks)) ?></span>
                                </div>
                            </header>

                            <div class="task-list-rows" data-task-dropzone data-group-name="<?= e((string) $groupName) ?>">
                                <?php if (!$groupTasks): ?>
                                    <div class="task-group-empty-row">
                                        <?php if ($taskGroupCanAccess): ?>
                                            <button
                                                type="button"
                                                class="task-group-empty-add"
                                                data-open-create-task-modal
                                                data-create-group="<?= e((string) $groupName) ?>"
                                                aria-label="Criar tarefa no grupo <?= e((string) $groupName) ?>"
                                            >+</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php foreach ($groupTasks as $task): ?>
                                    <?php
                                    $taskId = (int) $task['id'];
                                    $priorityKey = normalizeTaskPriority((string) $task['priority']);
                                    $statusKey = normalizeTaskStatus((string) $task['status']);
                                    $statusMeta = $statusMetaByKey[$statusKey] ?? taskStatusMeta($statusKey);
                                    $statusKind = (string) ($task['status_kind'] ?? $statusMeta['kind'] ?? 'todo');
                                    $statusLabel = (string) ($task['status_label'] ?? $statusMeta['label'] ?? ($statusOptions[$statusKey] ?? 'A fazer'));
                                    $statusOrder = (int) ($task['status_order'] ?? $statusMeta['order'] ?? 1);
                                    $statusColor = normalizeTaskStatusColor(
                                        (string) ($task['status_color'] ?? $statusMeta['color'] ?? ''),
                                        $statusKind
                                    );
                                    $statusCssVars = (string) ($statusMeta['css_vars'] ?? taskStatusCssVars($statusColor));
                                    $assigneeSummary = assigneeNamesSummary($task);
                                    $dueDateValue = (string) ($task['due_date'] ?? '');
                                    $dueDateUi = taskDueDatePresentation($dueDateValue);
                                    $isOverdueMarked = ((int) ($task['overdue_flag'] ?? 0)) === 1;
                                    $taskSubtasksDependencyEnabled = normalizePermissionFlag($task['subtasks_dependency_enabled'] ?? 0);
                                    $taskSubtasks = is_array($task['subtasks'] ?? null)
                                        ? $task['subtasks']
                                        : decodeTaskSubtasks($task['subtasks_json'] ?? null, $taskSubtasksDependencyEnabled === 1);
                                    $taskSubtasksProgress = taskSubtasksProgress($taskSubtasks, $taskSubtasksDependencyEnabled === 1);
                                    $taskSubtasksTotal = (int) ($taskSubtasksProgress['total'] ?? 0);
                                    $taskSubtasksCompleted = (int) ($taskSubtasksProgress['completed'] ?? 0);
                                    $taskTitleTag = normalizeTaskTitleTag((string) ($task['title_tag'] ?? ''));
                                    $taskTitleTagColor = taskTitleTagColorForTag($taskTitleTag, $taskTitleTagColors);
                                    $hasActiveRevisionRequest = taskHasActiveRevisionRequest(
                                        (string) ($task['description'] ?? ''),
                                        is_array($task['history'] ?? null) ? $task['history'] : []
                                    );
                                    ?>
                                    <article
                                        class="task-list-item task-status-<?= e($statusKind) ?><?= $isOverdueMarked ? ' has-overdue-flag' : '' ?>"
                                        id="task-<?= e((string) $taskId) ?>"
                                        data-task-item
                                        data-task-readonly="<?= $taskGroupCanAccess ? '0' : '1' ?>"
                                        data-group-name="<?= e((string) ($task['group_name'] ?? 'Geral')) ?>"
                                        data-status-value="<?= e($statusKey) ?>"
                                        data-status-kind="<?= e($statusKind) ?>"
                                        data-status-color="<?= e($statusColor) ?>"
                                        data-status-order="<?= e((string) $statusOrder) ?>"
                                        style="<?= e($statusCssVars) ?>"
                                        draggable="<?= $taskGroupCanAccess ? 'true' : 'false' ?>"
                                    >
                                        <form method="post" class="task-list-form" id="update-task-<?= e((string) $taskId) ?>" data-task-autosave-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_task">
                                            <input type="hidden" name="task_id" value="<?= e((string) $taskId) ?>">
                                            <input type="hidden" name="autosave" value="1">
                                            <input type="hidden" name="reference_links_json" value="<?= e(encodeReferenceUrlList($task['reference_links'] ?? [])) ?>" data-task-reference-links-json>
                                            <input type="hidden" name="subtasks_json" value="<?= e(encodeTaskSubtasks($taskSubtasks, $taskSubtasksDependencyEnabled === 1)) ?>" data-task-subtasks-json>
                                            <input type="hidden" name="subtasks_dependency_enabled" value="<?= $taskSubtasksDependencyEnabled === 1 ? '1' : '0' ?>" data-task-subtasks-dependency>
                                            <input type="hidden" name="title_tag" value="<?= e($taskTitleTag) ?>" data-task-title-tag>
                                            <input type="hidden" name="title_tag_color" value="<?= e($taskTitleTagColor) ?>" data-task-title-tag-color>
                                            <input type="hidden" name="overdue_flag" value="<?= $isOverdueMarked ? '1' : '0' ?>" data-task-overdue-flag>
                                            <input type="hidden" name="overdue_since_date" value="<?= e((string) ($task['overdue_since_date'] ?? '')) ?>" data-task-overdue-since-date>
                                            <input type="hidden" value="<?= e((string) (($task['overdue_days'] ?? 0))) ?>" data-task-overdue-days>
                                            <input type="hidden" name="has_active_revision" value="<?= $hasActiveRevisionRequest ? '1' : '0' ?>" data-task-has-active-revision>
                                            <input type="hidden" name="expected_updated_at" value="<?= e((string) ($task['updated_at'] ?? '')) ?>" data-task-expected-updated-at>

                                            <fieldset class="task-row-fieldset" <?= $taskGroupCanAccess ? '' : 'disabled' ?>>
                                            <div class="task-line-row">
                                                <div class="task-line-title">
                                                    <span
                                                        class="task-title-tag-badge"
                                                        data-task-title-tag-badge
                                                        data-tag-color="<?= e($taskTitleTagColor) ?>"
                                                        style="--wf-tag-color: <?= e($taskTitleTagColor) ?>;"
                                                        <?= $taskTitleTag === '' ? ' hidden' : '' ?>
                                                    ><?= e($taskTitleTag) ?></span>
                                                    <input
                                                        type="text"
                                                        name="title"
                                                        value="<?= e((string) $task['title']) ?>"
                                                        maxlength="140"
                                                        class="task-title-input"
                                                        aria-label="Titulo da tarefa"
                                                        required
                                                    >
                                                    <div
                                                        class="task-subtasks-progress<?= $taskSubtasksTotal > 0 ? '' : ' is-hidden' ?>"
                                                        data-task-subtasks-progress
                                                        aria-label="Progresso das subtarefas"
                                                    >
                                                        <div class="task-subtasks-progress-steps" data-task-subtasks-progress-steps>
                                                            <?php for ($index = 0; $index < $taskSubtasksTotal; $index++): ?>
                                                                <?php $isDoneStep = $index < $taskSubtasksCompleted; ?>
                                                                <span
                                                                    class="task-subtasks-progress-step<?= $isDoneStep ? ' is-done' : '' ?>"
                                                                    aria-hidden="true"
                                                                ></span>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <span class="task-subtasks-progress-text" data-task-subtasks-progress-text>
                                                            <?= e((string) $taskSubtasksCompleted) ?>/<?= e((string) $taskSubtasksTotal) ?> etapas
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="status-stepper" data-status-stepper>
                                                    <?php if ($hasActiveRevisionRequest): ?>
                                                        <button
                                                            type="button"
                                                            class="task-revision-badge"
                                                            data-task-revision-badge
                                                            title="Solicitacao de revisao ativa. Clique para remover."
                                                            aria-label="Remover solicitacao de revisao"
                                                        >Revisao</button>
                                                    <?php endif; ?>
                                                    <button
                                                        type="button"
                                                        class="status-stepper-btn"
                                                        data-status-step="-1"
                                                        aria-label="Status anterior"
                                                    >
                                                        <span aria-hidden="true">&#8249;</span>
                                                    </button>

                                                    <div class="tag-field tag-field-status row-inline-picker-wrap" data-inline-select-wrap>
                                                        <details
                                                            class="row-inline-picker status-inline-picker status-<?= e($statusKind) ?>"
                                                            data-inline-select-picker
                                                            data-status-color="<?= e($statusColor) ?>"
                                                            style="<?= e($statusCssVars) ?>"
                                                        >
                                                            <summary aria-label="Status da tarefa">
                                                                <span class="row-inline-picker-summary-text" data-inline-select-text><?= e($statusLabel) ?></span>
                                                            </summary>
                                                            <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Selecionar status">
                                                                <?php foreach ($statusOptions as $optionKey => $optionLabel): ?>
                                                                    <?php
                                                                    $optionMeta = $statusMetaByKey[$optionKey] ?? taskStatusMeta($optionKey);
                                                                    $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                                                    $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                                                    $optionColor = normalizeTaskStatusColor(
                                                                        (string) ($optionMeta['color'] ?? ''),
                                                                        $optionKind
                                                                    );
                                                                    $optionCssVars = (string) ($optionMeta['css_vars'] ?? taskStatusCssVars($optionColor));
                                                                    ?>
                                                                    <button
                                                                        type="button"
                                                                        class="row-inline-picker-option status-<?= e($optionKind) ?><?= $optionKey === $statusKey ? ' is-active' : '' ?>"
                                                                        data-inline-select-option
                                                                        data-value="<?= e($optionKey) ?>"
                                                                        data-label="<?= e($optionLabel) ?>"
                                                                        data-status-kind="<?= e($optionKind) ?>"
                                                                        data-status-color="<?= e($optionColor) ?>"
                                                                        data-status-order="<?= e((string) $optionOrder) ?>"
                                                                        style="<?= e($optionCssVars) ?>"
                                                                        role="option"
                                                                        aria-selected="<?= $optionKey === $statusKey ? 'true' : 'false' ?>"
                                                                    ><?= e($optionLabel) ?></button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </details>
                                                        <select
                                                            name="status"
                                                            class="tag-select status-select status-<?= e($statusKind) ?> row-inline-picker-native"
                                                            data-inline-select-source
                                                            data-status-color="<?= e($statusColor) ?>"
                                                            style="<?= e($statusCssVars) ?>"
                                                            hidden
                                                        >
                                                            <?php foreach ($statusOptions as $optionKey => $optionLabel): ?>
                                                                <?php
                                                                $optionMeta = $statusMetaByKey[$optionKey] ?? taskStatusMeta($optionKey);
                                                                $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                                                $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                                                $optionColor = normalizeTaskStatusColor(
                                                                    (string) ($optionMeta['color'] ?? ''),
                                                                    $optionKind
                                                                );
                                                                ?>
                                                                <option
                                                                    value="<?= e($optionKey) ?>"
                                                                    data-status-kind="<?= e($optionKind) ?>"
                                                                    data-status-color="<?= e($optionColor) ?>"
                                                                    data-status-order="<?= e((string) $optionOrder) ?>"
                                                                    <?= $optionKey === $statusKey ? ' selected' : '' ?>
                                                                >
                                                                    <?= e($optionLabel) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <button
                                                        type="button"
                                                        class="status-stepper-btn"
                                                        data-status-step="1"
                                                        aria-label="Proximo status"
                                                    >
                                                        <span aria-hidden="true">&#8250;</span>
                                                    </button>
                                                </div>

                                                <div class="tag-field tag-field-priority row-inline-picker-wrap" data-inline-select-wrap data-inline-picker-kind="priority">
                                                    <details class="row-inline-picker priority-inline-picker priority-<?= e($priorityKey) ?>" data-inline-select-picker>
                                                        <summary aria-label="Prioridade da tarefa">
                                                            <span class="row-inline-picker-summary-icon" aria-hidden="true">&#9873;</span>
                                                            <span class="row-inline-picker-summary-text sr-only" data-inline-select-text><?= e((string) ($priorityOptions[$priorityKey] ?? 'Media')) ?></span>
                                                        </summary>
                                                        <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Selecionar prioridade">
                                                            <?php foreach ($priorityOptions as $optionKey => $optionLabel): ?>
                                                                <button
                                                                    type="button"
                                                                    class="row-inline-picker-option priority-<?= e($optionKey) ?><?= $optionKey === $priorityKey ? ' is-active' : '' ?>"
                                                                    data-inline-select-option
                                                                    data-value="<?= e($optionKey) ?>"
                                                                    data-label="<?= e($optionLabel) ?>"
                                                                    role="option"
                                                                    aria-selected="<?= $optionKey === $priorityKey ? 'true' : 'false' ?>"
                                                                >
                                                                    <span class="row-inline-picker-option-flag" aria-hidden="true">&#9873;</span>
                                                                    <span><?= e($optionLabel) ?></span>
                                                                </button>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </details>
                                                    <select name="priority" class="tag-select priority-select priority-<?= e($priorityKey) ?> row-inline-picker-native" data-inline-select-source hidden>
                                                        <?php foreach ($priorityOptions as $optionKey => $optionLabel): ?>
                                                            <option value="<?= e($optionKey) ?>"<?= $optionKey === $priorityKey ? ' selected' : '' ?>>
                                                                &#9873;
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="tag-field assignee-tag-field">
                                                    <details class="assignee-picker row-assignee-picker">
                                                        <summary><?= e($assigneeSummary) ?></summary>
                                                        <div class="assignee-picker-menu">
                                                            <?php foreach ($users as $user): ?>
                                                                <label class="assignee-option">
                                                                    <input
                                                                        type="checkbox"
                                                                        name="assigned_to[]"
                                                                        value="<?= e((string) $user['id']) ?>"
                                                                        data-assignee-name="<?= e((string) $user['name']) ?>"
                                                                        data-assignee-avatar="<?= e(userAvatarDataUrl($user)) ?>"
                                                                        data-assignee-initial="<?= e(userDisplayInitial((string) $user['name'])) ?>"
                                                                        <?= in_array((int) $user['id'], $task['assignee_ids'] ?? [], true) ? 'checked' : '' ?>
                                                                    >
                                                                    <?= renderUserAvatar($user, 'avatar small assignee-option-avatar', true, 'span') ?>
                                                                    <span class="assignee-option-text"><?= e((string) $user['name']) ?></span>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </details>
                                                </div>

                                                <div class="tag-field due-tag-field">
                                                    <span class="sr-only">Prazo</span>
                                                    <?php if ($isOverdueMarked): ?>
                                                        <button
                                                            type="button"
                                                            class="task-overdue-badge"
                                                            data-task-overdue-badge
                                                            title="Tarefa em atraso. Clique para remover o aviso."
                                                            aria-label="Remover aviso de atraso"
                                                        >Atraso</button>
                                                    <?php endif; ?>
                                                    <button
                                                        type="button"
                                                        class="due-date-display<?= !empty($dueDateUi['is_relative']) ? ' is-relative' : '' ?>"
                                                        data-due-date-display
                                                        aria-label="Prazo: <?= e((string) $dueDateUi['title']) ?>"
                                                    ><?= e((string) $dueDateUi['display']) ?></button>
                                                    <input
                                                        type="date"
                                                        name="due_date"
                                                        value="<?= e($dueDateValue) ?>"
                                                        class="due-date-input due-date-input-overlay"
                                                        data-due-date-input
                                                    >
                                                </div>

                                                <button
                                                    type="button"
                                                    form="delete-task-<?= e((string) $taskId) ?>"
                                                    class="task-row-delete"
                                                    aria-label="Excluir tarefa"
                                                >
                                                    <span aria-hidden="true">&#10005;</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="task-expand-toggle"
                                                    data-task-expand
                                                    aria-label="Abrir tarefa"
                                                >
                                                    <span class="sr-only">Abrir tarefa</span>
                                                </button>
                                            </div>

                                            <div class="task-line-details" id="task-details-<?= e((string) $taskId) ?>" hidden>
                                                <div class="task-line-details-grid">
                                                    <label class="task-group-select-wrap">
                                                        <select
                                                            name="group_name"
                                                            class="tag-select group-tag-select"
                                                            data-task-group-select
                                                            aria-label="Grupo"
                                                        >
                                                            <?php
                                                            $currentTaskGroup = normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral'));
                                                            $groupRendered = false;
                                                            foreach ($taskGroupsWithAccess as $groupNameOption):
                                                                $optionValue = normalizeTaskGroupName((string) $groupNameOption);
                                                                $selected = $optionValue === $currentTaskGroup;
                                                                if ($selected) {
                                                                    $groupRendered = true;
                                                                }
                                                            ?>
                                                                <option value="<?= e($optionValue) ?>"<?= $selected ? ' selected' : '' ?>><?= e($optionValue) ?></option>
                                                            <?php endforeach; ?>
                                                            <?php if (!$groupRendered): ?>
                                                                <option value="<?= e($currentTaskGroup) ?>" selected><?= e($currentTaskGroup) ?></option>
                                                            <?php endif; ?>
                                                        </select>
                                                    </label>

                                                    <label>
                                                        <span>Descricao</span>
                                                        <textarea name="description" rows="3"><?= e((string) $task['description']) ?></textarea>
                                                    </label>
                                                </div>

                                                <div class="task-line-footer">
                                                    <div class="task-line-meta">
                                                        <span>Criado por <?= e((string) $task['creator_name']) ?></span>
                                                        <?php if (!empty($task['updated_at'])): ?>
                                                            <span data-task-updated-at>Atualizado em <?= e((new DateTimeImmutable((string) $task['updated_at']))->format('d/m H:i')) ?></span>
                                                        <?php endif; ?>
                                                    </div>

                                                </div>
                                            </div>
                                            </fieldset>
                                        </form>

                                        <form method="post" id="delete-task-<?= e((string) $taskId) ?>" class="task-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_task">
                                            <input type="hidden" name="task_id" value="<?= e((string) $taskId) ?>">
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="vault-wrap panel" id="vault" data-dashboard-view-panel="vault" hidden>
            <div class="panel-header board-header vault-header">
                <div>
                    <h2>Gerenciador de acessos</h2>
                </div>
                <div class="board-summary vault-summary">
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-vault-group-modal
                        aria-label="Criar grupo no cofre"
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M3 8a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"></path>
                                <path d="M12 11v5"></path>
                                <path d="M9.5 13.5h5"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo grupo</span>
                    </button>
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-vault-entry-modal
                        aria-label="Adicionar dado de acesso"
                        <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <circle cx="8" cy="12" r="2.4"></circle>
                                <path d="M10.4 12h9.6"></path>
                                <path d="M16 12v2.5"></path>
                                <path d="M19 12v2"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo acesso</span>
                    </button>
                    <span data-vault-total-count><?= e((string) count($vaultEntries)) ?> item(ns)</span>
                </div>
            </div>

            <div class="vault-groups-list">
                <?php if (empty($vaultEntriesByGroup)): ?>
                    <div class="empty-card">
                        <p>Nenhum item no cofre ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($vaultEntriesByGroup as $vaultGroupName => $groupVaultEntries): ?>
                        <?php
                        $vaultGroupPermission = $vaultGroupPermissions[$vaultGroupName] ?? ['can_view' => true, 'can_access' => true];
                        $vaultGroupCanAccess = !empty($vaultGroupPermission['can_access']);
                        $vaultGroupPermissionsModalKey = 'vault-group-perm-' . md5((string) $vaultGroupName);
                        ?>
                        <section class="task-group vault-group<?= $vaultGroupCanAccess ? '' : ' task-group-readonly' ?>" data-vault-group data-group-name="<?= e((string) $vaultGroupName) ?>">
                            <header class="task-group-head" data-vault-group-head-toggle>
                                <div class="task-group-head-main">
                                    <form method="post" class="task-group-rename-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="rename_vault_group">
                                        <input type="hidden" name="old_group_name" value="<?= e((string) $vaultGroupName) ?>">
                                        <h3>
                                            <input
                                                type="text"
                                                name="new_group_name"
                                                value="<?= e((string) $vaultGroupName) ?>"
                                                maxlength="60"
                                                class="task-group-name-input"
                                                aria-label="Nome do grupo do cofre"
                                                spellcheck="false"
                                                <?= $vaultGroupCanAccess ? '' : 'readonly' ?>
                                            >
                                        </h3>
                                        <button type="submit" class="sr-only">Salvar grupo</button>
                                    </form>
                                </div>
                                <div class="task-group-head-actions">
                                    <span class="task-group-collapse" data-group-toggle-indicator aria-hidden="true"><span>&#9662;</span></span>
                                    <?php if (!empty($canManageWorkspace) && empty($isPersonalWorkspace)): ?>
                                        <button
                                            type="button"
                                            class="group-permissions-button"
                                            data-open-group-permissions-modal="<?= e($vaultGroupPermissionsModalKey) ?>"
                                            aria-label="Gerenciar acesso do grupo do cofre <?= e((string) $vaultGroupName) ?>"
                                        >
                                            Acesso
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($vaultGroupCanAccess): ?>
                                        <button
                                            type="button"
                                            class="group-add-button"
                                            data-open-vault-entry-modal
                                            data-create-group="<?= e((string) $vaultGroupName) ?>"
                                            aria-label="Adicionar item no grupo <?= e((string) $vaultGroupName) ?>"
                                        >+</button>
                                        <form method="post" class="task-group-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_vault_group">
                                            <input type="hidden" name="group_name" value="<?= e((string) $vaultGroupName) ?>">
                                            <button
                                                type="submit"
                                                class="task-group-delete"
                                                aria-label="Excluir grupo do cofre <?= e((string) $vaultGroupName) ?>"
                                            ><span aria-hidden="true">&#10005;</span></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$vaultGroupCanAccess): ?>
                                        <span class="task-group-readonly-tag">Somente leitura</span>
                                    <?php endif; ?>
                                    <span class="task-group-count"><?= e((string) count($groupVaultEntries)) ?></span>
                                </div>
                            </header>

                            <div class="vault-group-rows" data-vault-group-rows>
                                <?php if (!$groupVaultEntries): ?>
                                    <div class="task-group-empty-row">
                                        <?php if ($vaultGroupCanAccess): ?>
                                            <button
                                                type="button"
                                                class="task-group-empty-add"
                                                data-open-vault-entry-modal
                                                data-create-group="<?= e((string) $vaultGroupName) ?>"
                                                aria-label="Adicionar item no grupo <?= e((string) $vaultGroupName) ?>"
                                            >+</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($groupVaultEntries as $vaultEntry): ?>
                                    <?php
                                    $vaultEntryId = (int) ($vaultEntry['id'] ?? 0);
                                    $vaultLabel = (string) ($vaultEntry['label'] ?? '');
                                    $vaultLogin = (string) ($vaultEntry['login_value'] ?? '');
                                    $vaultPassword = (string) ($vaultEntry['password_value'] ?? '');
                                    $vaultGroupValue = (string) ($vaultEntry['group_name'] ?? $vaultGroupName);
                                    ?>
                                    <article
                                        class="vault-entry-row"
                                        data-vault-entry
                                        data-entry-id="<?= e((string) $vaultEntryId) ?>"
                                        data-entry-label="<?= e($vaultLabel) ?>"
                                        data-entry-login="<?= e($vaultLogin) ?>"
                                        data-entry-password="<?= e($vaultPassword) ?>"
                                        data-entry-group="<?= e($vaultGroupValue) ?>"
                                    >
                                        <form method="post" class="vault-entry-name-form" data-vault-entry-name-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="rename_vault_entry_label">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $vaultEntryId) ?>">

                                            <div class="vault-entry-line">
                                                <div class="vault-entry-name">
                                                    <input
                                                        type="text"
                                                        name="label"
                                                        maxlength="120"
                                                        value="<?= e($vaultLabel) ?>"
                                                        class="task-title-input vault-entry-title-input"
                                                        aria-label="Nome do dado de acesso"
                                                        data-vault-entry-label-input
                                                        <?= $vaultGroupCanAccess ? '' : 'readonly' ?>
                                                        required
                                                    >
                                                </div>

                                                <div class="vault-entry-value">
                                                    <span class="vault-entry-value-text"><?= $vaultLogin !== '' ? e($vaultLogin) : '-' ?></span>
                                                    <button
                                                        type="button"
                                                        class="vault-icon-button"
                                                        data-vault-copy="<?= e($vaultLogin) ?>"
                                                        aria-label="Copiar login"
                                                        <?= $vaultLogin === '' ? 'disabled' : '' ?>
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <rect x="9" y="9" width="10" height="10" rx="2"></rect>
                                                            <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div
                                                    class="vault-entry-value vault-entry-password"
                                                    data-vault-password-cell
                                                    data-password-value="<?= e($vaultPassword) ?>"
                                                    data-visible="false"
                                                >
                                                    <span class="vault-entry-value-text" data-vault-password-text><?= $vaultPassword !== '' ? '••••••••' : '-' ?></span>
                                                    <button
                                                        type="button"
                                                        class="vault-icon-button"
                                                        data-vault-toggle-password
                                                        aria-label="Mostrar senha"
                                                        <?= $vaultPassword === '' ? 'disabled' : '' ?>
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                                                            <circle cx="12" cy="12" r="3"></circle>
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="vault-icon-button"
                                                        data-vault-copy="<?= e($vaultPassword) ?>"
                                                        aria-label="Copiar senha"
                                                        <?= $vaultPassword === '' ? 'disabled' : '' ?>
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <rect x="9" y="9" width="10" height="10" rx="2"></rect>
                                                            <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div class="vault-entry-tools">
                                                    <?php if ($vaultGroupCanAccess): ?>
                                                        <button
                                                            type="button"
                                                            class="vault-icon-button"
                                                            data-open-vault-edit-modal
                                                            aria-label="Editar dado de acesso"
                                                        >
                                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                                <path d="M4 20h4l10-10-4-4L4 16v4Z"></path>
                                                                <path d="m12 6 4 4"></path>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="vault-entry-delete-button"
                                                            data-vault-delete-entry
                                                            data-delete-form-id="delete-vault-entry-<?= e((string) $vaultEntryId) ?>"
                                                            aria-label="Excluir dado de acesso"
                                                        >
                                                            <span aria-hidden="true">&#10005;</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </form>

                                        <form method="post" id="delete-vault-entry-<?= e((string) $vaultEntryId) ?>" class="vault-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_vault_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $vaultEntryId) ?>">
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="due-wrap panel" id="dues" data-dashboard-view-panel="dues" hidden>
            <div class="panel-header board-header due-header">
                <div>
                    <h2>Vencimentos</h2>
                </div>
                <div class="board-summary due-summary">
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-due-group-modal
                        aria-label="Criar grupo de vencimentos"
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M3 8a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"></path>
                                <path d="M12 11v5"></path>
                                <path d="M9.5 13.5h5"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo grupo</span>
                    </button>
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-due-entry-modal
                        aria-label="Adicionar vencimento"
                        <?= empty($dueGroupsWithAccess) ? 'disabled' : '' ?>
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M8 6v4"></path>
                                <path d="M16 6v4"></path>
                                <rect x="4" y="8" width="16" height="12" rx="2"></rect>
                                <path d="M4 12h16"></path>
                                <path d="M12 14v4"></path>
                                <path d="M10 16h4"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo vencimento</span>
                    </button>
                    <span data-due-total-count><?= e((string) count($dueEntries)) ?> item(ns)</span>
                </div>
            </div>

            <div class="due-groups-list">
                <?php if (empty($dueEntriesByGroup)): ?>
                    <div class="empty-card">
                        <p>Nenhum vencimento cadastrado ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($dueEntriesByGroup as $dueGroupName => $groupDueEntries): ?>
                        <?php
                        $dueGroupPermission = $dueGroupPermissions[$dueGroupName] ?? ['can_view' => true, 'can_access' => true];
                        $dueGroupCanAccess = !empty($dueGroupPermission['can_access']);
                        $dueGroupPermissionsModalKey = 'due-group-perm-' . md5((string) $dueGroupName);
                        ?>
                        <section class="task-group due-group<?= $dueGroupCanAccess ? '' : ' task-group-readonly' ?>" data-due-group data-group-name="<?= e((string) $dueGroupName) ?>">
                            <header class="task-group-head" data-due-group-head-toggle>
                                <div class="task-group-head-main">
                                    <form method="post" class="task-group-rename-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="rename_due_group">
                                        <input type="hidden" name="old_group_name" value="<?= e((string) $dueGroupName) ?>">
                                        <h3>
                                            <input
                                                type="text"
                                                name="new_group_name"
                                                value="<?= e((string) $dueGroupName) ?>"
                                                maxlength="60"
                                                class="task-group-name-input"
                                                aria-label="Nome do grupo de vencimentos"
                                                spellcheck="false"
                                                <?= $dueGroupCanAccess ? '' : 'readonly' ?>
                                            >
                                        </h3>
                                        <button type="submit" class="sr-only">Salvar grupo</button>
                                    </form>
                                </div>
                                <div class="task-group-head-actions">
                                    <span class="task-group-collapse" data-group-toggle-indicator aria-hidden="true"><span>&#9662;</span></span>
                                    <?php if (!empty($canManageWorkspace) && empty($isPersonalWorkspace)): ?>
                                        <button
                                            type="button"
                                            class="group-permissions-button"
                                            data-open-group-permissions-modal="<?= e($dueGroupPermissionsModalKey) ?>"
                                            aria-label="Gerenciar acesso do grupo de vencimentos <?= e((string) $dueGroupName) ?>"
                                        >
                                            Acesso
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($dueGroupCanAccess): ?>
                                        <button
                                            type="button"
                                            class="group-add-button"
                                            data-open-due-entry-modal
                                            data-create-group="<?= e((string) $dueGroupName) ?>"
                                            aria-label="Adicionar vencimento no grupo <?= e((string) $dueGroupName) ?>"
                                        >+</button>
                                        <form method="post" class="task-group-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_due_group">
                                            <input type="hidden" name="group_name" value="<?= e((string) $dueGroupName) ?>">
                                            <button
                                                type="submit"
                                                class="task-group-delete"
                                                aria-label="Excluir grupo de vencimentos <?= e((string) $dueGroupName) ?>"
                                            ><span aria-hidden="true">&#10005;</span></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$dueGroupCanAccess): ?>
                                        <span class="task-group-readonly-tag">Somente leitura</span>
                                    <?php endif; ?>
                                    <span class="task-group-count"><?= e((string) count($groupDueEntries)) ?></span>
                                </div>
                            </header>

                            <div class="due-group-rows" data-due-group-rows>
                                <?php if (!$groupDueEntries): ?>
                                    <div class="task-group-empty-row">
                                        <?php if ($dueGroupCanAccess): ?>
                                            <button
                                                type="button"
                                                class="task-group-empty-add"
                                                data-open-due-entry-modal
                                                data-create-group="<?= e((string) $dueGroupName) ?>"
                                                aria-label="Adicionar vencimento no grupo <?= e((string) $dueGroupName) ?>"
                                            >+</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($groupDueEntries as $dueEntry): ?>
                                    <?php
                                    $dueEntryId = (int) ($dueEntry['id'] ?? 0);
                                    $dueLabel = (string) ($dueEntry['label'] ?? '');
                                    $dueDateValue = dueDateForStorage((string) ($dueEntry['due_date'] ?? ''));
                                    $dueRecurrenceType = normalizeDueRecurrenceType((string) ($dueEntry['recurrence_type'] ?? 'monthly'));
                                    $dueMonthlyDay = normalizeDueMonthlyDay($dueEntry['monthly_day'] ?? null);
                                    if ($dueRecurrenceType === 'monthly' && $dueMonthlyDay === null) {
                                        $dueMonthlyDay = dueMonthlyDayFromDate($dueDateValue);
                                    }
                                    $dueNextDateValue = dueDateForStorage((string) ($dueEntry['next_due_date'] ?? ''));
                                    if ($dueNextDateValue === null) {
                                        $dueNextDateValue = dueNextDueDate($dueRecurrenceType, $dueMonthlyDay, $dueDateValue);
                                    }
                                    $dueNextPresentation = taskDueDatePresentation($dueNextDateValue);
                                    $dueNextDisplay = (string) ($dueNextPresentation['display'] ?? '-');
                                    $dueNextTitle = (string) ($dueNextPresentation['title'] ?? $dueNextDisplay);
                                    $dueNextDateLabel = $dueNextDisplay;
                                    if ($dueNextDateValue !== null) {
                                        $dueNextDateObject = DateTimeImmutable::createFromFormat('Y-m-d', $dueNextDateValue);
                                        if ($dueNextDateObject) {
                                            $dueNextDateLabel = $dueNextDateObject->format('d/m/Y');
                                        }
                                    }
                                    if ($dueRecurrenceType === 'monthly') {
                                        $dueMonthlyDayLabel = $dueMonthlyDay !== null
                                            ? str_pad((string) $dueMonthlyDay, 2, '0', STR_PAD_LEFT)
                                            : '--';
                                        $dueScheduleLabel = 'Mensal - dia ' . $dueMonthlyDayLabel;
                                        $dueScheduleTitle = 'Vencimento mensal no dia ' . $dueMonthlyDayLabel . '.';
                                    } elseif ($dueRecurrenceType === 'annual') {
                                        $dueAnnualReferenceDate = $dueDateValue ?? $dueNextDateValue;
                                        $dueAnnualMonthDayLabel = '--/--';
                                        if ($dueAnnualReferenceDate !== null) {
                                            $dueAnnualDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $dueAnnualReferenceDate);
                                            if ($dueAnnualDateObj) {
                                                $dueAnnualMonthDayLabel = $dueAnnualDateObj->format('d/m');
                                            }
                                        }
                                        $dueScheduleLabel = 'Anual - ' . $dueAnnualMonthDayLabel;
                                        $dueScheduleTitle = 'Vencimento anual em ' . $dueAnnualMonthDayLabel . '.';
                                    } else {
                                        $dueScheduleLabel = 'Sem recorrencia';
                                        $dueScheduleTitle = 'Vencimento em ' . $dueNextTitle;
                                    }
                                    $dueGroupValue = (string) ($dueEntry['group_name'] ?? $dueGroupName);
                                    $dueAmountCents = normalizeDueAmountCents($dueEntry['amount_cents'] ?? null) ?? 0;
                                    $dueAmountDisplay = dueAmountLabelFromCents($dueAmountCents);
                                    ?>
                                    <article
                                        class="due-entry-row"
                                        data-due-entry
                                        data-entry-id="<?= e((string) $dueEntryId) ?>"
                                        data-entry-label="<?= e($dueLabel) ?>"
                                        data-entry-date="<?= e((string) ($dueDateValue ?? '')) ?>"
                                        data-entry-next-date="<?= e((string) ($dueNextDateValue ?? '')) ?>"
                                        data-entry-recurrence-type="<?= e($dueRecurrenceType) ?>"
                                        data-entry-monthly-day="<?= e((string) ($dueMonthlyDay ?? '')) ?>"
                                        data-entry-amount-cents="<?= e((string) $dueAmountCents) ?>"
                                        data-entry-group="<?= e($dueGroupValue) ?>"
                                    >
                                        <div class="due-entry-main">
                                            <div class="due-entry-line">
                                                <span class="due-entry-title"><?= e($dueLabel) ?></span>
                                                <span
                                                    class="due-entry-schedule<?= $dueRecurrenceType === 'monthly' ? ' is-monthly' : ($dueRecurrenceType === 'annual' ? ' is-annual' : ' is-fixed') ?>"
                                                    title="<?= e($dueScheduleTitle) ?>"
                                                ><?= e($dueScheduleLabel) ?></span>
                                                <span class="due-entry-next" title="Proximo vencimento: <?= e($dueNextTitle) ?>">
                                                    <span class="due-entry-inline-label">Prox.:</span>
                                                    <strong class="due-entry-next-date"><?= e($dueNextDateLabel) ?></strong>
                                                </span>
                                                <span class="due-entry-amount" title="Valor a pagar">
                                                    <span class="due-entry-inline-label">Valor:</span>
                                                    <strong class="due-entry-amount-number"><?= e($dueAmountDisplay) ?></strong>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="vault-entry-tools">
                                            <?php if ($dueGroupCanAccess): ?>
                                                <button
                                                    type="button"
                                                    class="vault-icon-button"
                                                    data-open-due-edit-modal
                                                    aria-label="Editar vencimento"
                                                >
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M4 20h4l10-10-4-4L4 16v4Z"></path>
                                                        <path d="m12 6 4 4"></path>
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="vault-entry-delete-button"
                                                    data-due-delete-entry
                                                    data-delete-form-id="delete-due-entry-<?= e((string) $dueEntryId) ?>"
                                                    aria-label="Excluir vencimento"
                                                >
                                                    <span aria-hidden="true">&#10005;</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <form method="post" id="delete-due-entry-<?= e((string) $dueEntryId) ?>" class="vault-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_due_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $dueEntryId) ?>">
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="inventory-wrap panel" id="inventory" data-dashboard-view-panel="inventory" hidden>
            <div class="panel-header board-header due-header">
                <div>
                    <h2>Estoque</h2>
                </div>
                <div class="board-summary inventory-summary">
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-inventory-group-modal
                        aria-label="Criar grupo de estoque"
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M3 8a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"></path>
                                <path d="M12 11v5"></path>
                                <path d="M9.5 13.5h5"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo grupo</span>
                    </button>
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-inventory-entry-modal
                        aria-label="Adicionar item ao estoque"
                        <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M12 5v14"></path>
                                <path d="M5 12h14"></path>
                                <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo item</span>
                    </button>
                    <span data-inventory-total-count><?= e((string) count($inventoryEntries)) ?> item(ns)</span>
                </div>
            </div>

            <div class="inventory-groups-list">
                <?php if (empty($inventoryEntriesByGroup)): ?>
                    <div class="empty-card">
                        <p>Nenhum item de estoque cadastrado ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($inventoryEntriesByGroup as $inventoryGroupName => $groupInventoryEntries): ?>
                        <section
                            class="task-group inventory-group"
                            data-inventory-group
                            data-group-name="<?= e((string) $inventoryGroupName) ?>"
                        >
                            <header class="task-group-head" data-inventory-group-head-toggle>
                                <div class="task-group-head-main">
                                    <form method="post" class="task-group-rename-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="rename_inventory_group">
                                        <input type="hidden" name="old_group_name" value="<?= e((string) $inventoryGroupName) ?>">
                                        <h3>
                                            <input
                                                type="text"
                                                name="new_group_name"
                                                value="<?= e((string) $inventoryGroupName) ?>"
                                                maxlength="60"
                                                class="task-group-name-input"
                                                aria-label="Nome do grupo de estoque"
                                                spellcheck="false"
                                            >
                                        </h3>
                                        <button type="submit" class="sr-only">Salvar grupo</button>
                                    </form>
                                </div>
                                <div class="task-group-head-actions">
                                    <span class="task-group-collapse" data-group-toggle-indicator aria-hidden="true"><span>&#9662;</span></span>
                                    <button
                                        type="button"
                                        class="group-add-button"
                                        data-open-inventory-entry-modal
                                        data-create-group="<?= e((string) $inventoryGroupName) ?>"
                                        aria-label="Adicionar item no grupo <?= e((string) $inventoryGroupName) ?>"
                                    >+</button>
                                    <form method="post" class="task-group-delete-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete_inventory_group">
                                        <input type="hidden" name="group_name" value="<?= e((string) $inventoryGroupName) ?>">
                                        <button
                                            type="submit"
                                            class="task-group-delete"
                                            aria-label="Excluir grupo de estoque <?= e((string) $inventoryGroupName) ?>"
                                        ><span aria-hidden="true">&#10005;</span></button>
                                    </form>
                                    <span class="task-group-count"><?= e((string) count($groupInventoryEntries)) ?></span>
                                </div>
                            </header>

                            <div class="inventory-group-rows" data-inventory-group-rows>
                                <?php if (!$groupInventoryEntries): ?>
                                    <div class="task-group-empty-row">
                                        <button
                                            type="button"
                                            class="task-group-empty-add"
                                            data-open-inventory-entry-modal
                                            data-create-group="<?= e((string) $inventoryGroupName) ?>"
                                            aria-label="Adicionar item no grupo <?= e((string) $inventoryGroupName) ?>"
                                        >+</button>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($groupInventoryEntries as $inventoryEntry): ?>
                                    <?php
                                    $inventoryEntryId = (int) ($inventoryEntry['id'] ?? 0);
                                    $inventoryLabel = (string) ($inventoryEntry['label'] ?? '');
                                    $inventoryQuantityValue = normalizeInventoryQuantityValue($inventoryEntry['quantity_value'] ?? null) ?? 0;
                                    $inventoryQuantityDisplay = (string) ($inventoryEntry['quantity_display'] ?? inventoryQuantityLabel($inventoryQuantityValue));
                                    $inventoryQuantityInput = (string) ($inventoryEntry['quantity_value_input'] ?? inventoryQuantityInputValue($inventoryQuantityValue));
                                    $inventoryMinQuantityValue = normalizeInventoryQuantityValue($inventoryEntry['min_quantity_value'] ?? null);
                                    $inventoryMinQuantityInput = $inventoryMinQuantityValue !== null
                                        ? (string) ($inventoryEntry['min_quantity_value_input'] ?? inventoryQuantityInputValue($inventoryMinQuantityValue))
                                        : '';
                                    $inventoryUnitLabel = normalizeInventoryUnitLabel((string) ($inventoryEntry['unit_label'] ?? 'un'));
                                    $inventoryGroupValue = (string) ($inventoryEntry['group_name'] ?? $inventoryGroupName);
                                    $inventoryNotes = (string) ($inventoryEntry['notes'] ?? '');
                                    $inventoryLowStock = ((int) ($inventoryEntry['is_low_stock'] ?? 0)) === 1;
                                    ?>
                                    <article
                                        class="inventory-entry-row"
                                        data-inventory-entry
                                        data-entry-id="<?= e((string) $inventoryEntryId) ?>"
                                        data-entry-label="<?= e($inventoryLabel) ?>"
                                        data-entry-quantity-value="<?= e($inventoryQuantityInput) ?>"
                                        data-entry-min-quantity-value="<?= e($inventoryMinQuantityInput) ?>"
                                        data-entry-unit-label="<?= e($inventoryUnitLabel) ?>"
                                        data-entry-group="<?= e($inventoryGroupValue) ?>"
                                        data-entry-notes="<?= e($inventoryNotes) ?>"
                                    >
                                        <div class="inventory-entry-main">
                                            <div class="inventory-entry-line">
                                                <span class="inventory-entry-title"><?= e($inventoryLabel) ?></span>
                                                <form
                                                    method="post"
                                                    class="inventory-entry-qty inventory-entry-qty-form"
                                                    data-inventory-inline-quantity-form
                                                    title="Quantidade disponivel"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="update_inventory_entry_quantity">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $inventoryEntryId) ?>">
                                                    <span class="inventory-entry-inline-label">Qtd.</span>
                                                    <label class="inventory-entry-qty-editor">
                                                        <span class="inventory-entry-qty-control">
                                                            <input
                                                                type="number"
                                                                name="quantity_value"
                                                                min="0"
                                                                step="1"
                                                                value="<?= e($inventoryQuantityInput) ?>"
                                                                class="inventory-entry-qty-input"
                                                                data-inventory-inline-quantity-input
                                                                aria-label="Quantidade de <?= e($inventoryLabel) ?>"
                                                            >
                                                            <button
                                                                type="button"
                                                                class="inventory-entry-qty-step inventory-entry-qty-step-right"
                                                                data-inventory-inline-quantity-step
                                                                data-step="1"
                                                                aria-label="Aumentar quantidade"
                                                            >&#9654;</button>
                                                            <button
                                                                type="button"
                                                                class="inventory-entry-qty-step inventory-entry-qty-step-left"
                                                                data-inventory-inline-quantity-step
                                                                data-step="-1"
                                                                aria-label="Diminuir quantidade"
                                                            >&#9664;</button>
                                                        </span>
                                                        <span class="inventory-entry-qty-unit"><?= e($inventoryUnitLabel) ?></span>
                                                    </label>
                                                    <button type="submit" class="sr-only">Salvar quantidade</button>
                                                </form>
                                                <?php if ($inventoryLowStock): ?>
                                                    <span class="inventory-entry-alert" title="Quantidade atual abaixo do estoque minimo">Baixo estoque</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="vault-entry-tools">
                                            <button
                                                type="button"
                                                class="vault-icon-button"
                                                data-open-inventory-edit-modal
                                                aria-label="Editar item de estoque"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M4 20h4l10-10-4-4L4 16v4Z"></path>
                                                    <path d="m12 6 4 4"></path>
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="vault-entry-delete-button"
                                                data-inventory-delete-entry
                                                data-delete-form-id="delete-inventory-entry-<?= e((string) $inventoryEntryId) ?>"
                                                aria-label="Excluir item de estoque"
                                            >
                                                <span aria-hidden="true">&#10005;</span>
                                            </button>
                                        </div>

                                        <form method="post" id="delete-inventory-entry-<?= e((string) $inventoryEntryId) ?>" class="vault-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_inventory_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $inventoryEntryId) ?>">
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="accounting-wrap panel" id="accounting" data-dashboard-view-panel="accounting" hidden>
            <div class="panel-header board-header accounting-header">
                <div>
                    <h2>Contabilidade</h2>
                    <p class="accounting-period-label"><?= e($accountingPeriodLabel) ?></p>
                </div>
                <div class="board-summary accounting-board-summary">
                    <form method="get" action="<?= e(appPath('#accounting')) ?>" class="accounting-period-form">
                        <a
                            href="<?= e($accountingPreviousPeriodPath) ?>"
                            class="accounting-period-nav"
                            aria-label="Ir para o mes anterior"
                        >
                            <svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
                                <path d="M9.5 3.5 5 8l4.5 4.5"></path>
                            </svg>
                        </a>
                        <label for="accounting-period-input" class="sr-only">Periodo de referencia</label>
                        <input
                            type="month"
                            id="accounting-period-input"
                            name="accounting_period"
                            value="<?= e($accountingPeriod) ?>"
                            class="accounting-period-input"
                        >
                        <a
                            href="<?= e($accountingNextPeriodPath) ?>"
                            class="accounting-period-nav"
                            aria-label="Ir para o proximo mes"
                        >
                            <svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
                                <path d="M6.5 3.5 11 8l-4.5 4.5"></path>
                            </svg>
                        </a>
                    </form>
                </div>
            </div>

            <div class="accounting-sheet">
                <div class="accounting-columns">
                    <section class="accounting-card">
                        <header class="accounting-card-head">
                            <div class="accounting-card-head-text">
                                <h3>Contas</h3>
                                <p>Despesas do mes</p>
                            </div>
                            <span><?= e((string) count($accountingExpenseEntries)) ?> item(ns)</span>
                        </header>

                        <div class="accounting-entries">
                            <?php if (empty($accountingExpenseEntries)): ?>
                                <div class="accounting-empty">Nenhuma conta cadastrada neste mes.</div>
                            <?php else: ?>
                                <?php foreach ($accountingExpenseEntries as $accountingEntry): ?>
                                    <?php
                                    $accountingEntryId = (int) ($accountingEntry['id'] ?? 0);
                                    $accountingEntryLabel = (string) ($accountingEntry['label'] ?? '');
                                    $accountingEntryAmountInput = (string) ($accountingEntry['amount_input'] ?? '0,00');
                                    $accountingEntryTotalAmountInput = (string) ($accountingEntry['total_amount_input'] ?? $accountingEntryAmountInput);
                                    $accountingEntryIsSettled = ((int) ($accountingEntry['is_settled'] ?? 0)) === 1;
                                    $accountingEntryIsInstallment = ((int) ($accountingEntry['is_installment'] ?? 0)) === 1;
                                    $accountingEntryInstallmentProgress = (string) ($accountingEntry['installment_progress'] ?? '');
                                    $accountingEntryInstallmentBadge = $accountingEntryInstallmentProgress !== ''
                                        ? ('Parcela ' . $accountingEntryInstallmentProgress)
                                        : 'Parcela';
                                    $accountingEntryIsCarried = ((int) ($accountingEntry['is_carried'] ?? 0)) === 1;
                                    ?>
                                    <div class="accounting-entry-row">
                                        <form method="post" class="accounting-entry-form" data-accounting-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <input
                                                type="text"
                                                name="label"
                                                value="<?= e($accountingEntryLabel) ?>"
                                                maxlength="120"
                                                class="accounting-input accounting-input-label"
                                                placeholder="Nome da conta"
                                                required
                                            >
                                            <?php if ($accountingEntryIsInstallment): ?>
                                                <button
                                                    type="button"
                                                    class="accounting-entry-badge is-installment accounting-entry-badge-inline"
                                                    data-accounting-entry-edit
                                                    aria-label="Editar conta parcelada"
                                                >
                                                    <?= e($accountingEntryInstallmentBadge) ?>
                                                </button>
                                            <?php endif; ?>
                                            <input
                                                type="text"
                                                name="amount_value"
                                                value="<?= e($accountingEntryAmountInput) ?>"
                                                class="accounting-input accounting-input-amount"
                                                placeholder="0,00"
                                                required
                                                data-accounting-primary-amount
                                                <?= $accountingEntryIsInstallment ? 'readonly' : '' ?>
                                            >
                                            <div class="accounting-entry-status">
                                                <label class="accounting-check">
                                                    <input type="checkbox" name="is_settled" value="1" <?= $accountingEntryIsSettled ? 'checked' : '' ?>>
                                                    <span>Pago</span>
                                                </label>
                                                <?php if ($accountingEntryIsCarried && !$accountingEntryIsSettled): ?>
                                                    <span class="accounting-entry-badge is-pending">Pendente</span>
                                                <?php endif; ?>
                                            </div>
                                            <input
                                                type="hidden"
                                                name="is_installment"
                                                value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="installment_progress"
                                                value="<?= e($accountingEntryInstallmentProgress) ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="total_amount_value"
                                                value="<?= e($accountingEntryTotalAmountInput) ?>"
                                            >
                                        </form>
                                        <form method="post" class="accounting-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <button type="submit" class="vault-entry-delete-button" aria-label="Excluir conta">
                                                <span aria-hidden="true">&#10005;</span>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="accounting-card-footer">
                            <details class="accounting-create-toggle">
                                <summary class="accounting-create-trigger">+ Adicionar</summary>
                                <form method="post" class="accounting-create-form" data-accounting-form>
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="create_accounting_entry">
                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                    <input type="hidden" name="entry_type" value="expense">
                                    <input
                                        type="text"
                                        name="label"
                                        maxlength="120"
                                        class="accounting-input accounting-input-label"
                                        placeholder="Nova conta"
                                        required
                                    >
                                    <input
                                        type="text"
                                        name="amount_value"
                                        class="accounting-input accounting-input-amount"
                                        placeholder="0,00"
                                        required
                                        data-accounting-primary-amount
                                    >
                                    <label class="accounting-check">
                                        <input type="checkbox" name="is_settled" value="1">
                                        <span>Pago</span>
                                    </label>
                                    <div class="accounting-entry-options">
                                        <label class="accounting-check accounting-check-installment">
                                            <input
                                                type="checkbox"
                                                name="is_installment"
                                                value="1"
                                                data-accounting-installment-toggle
                                            >
                                            <span>Parcelado</span>
                                        </label>
                                        <div class="accounting-installment-fields" data-accounting-installment-fields hidden>
                                            <div class="accounting-installment-progress-picker">
                                                <select
                                                    name="installment_number"
                                                    class="accounting-installment-select"
                                                    aria-label="Parcela atual"
                                                    data-accounting-installment-number
                                                    disabled
                                                >
                                                    <?php for ($installmentNumberOption = 1; $installmentNumberOption <= 60; $installmentNumberOption++): ?>
                                                        <option value="<?= e((string) $installmentNumberOption) ?>"><?= e((string) $installmentNumberOption) ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <span class="accounting-installment-separator">/</span>
                                                <select
                                                    name="installment_total"
                                                    class="accounting-installment-select"
                                                    aria-label="Total de parcelas"
                                                    data-accounting-installment-total-count
                                                    disabled
                                                >
                                                    <?php for ($installmentTotalOption = 2; $installmentTotalOption <= 60; $installmentTotalOption++): ?>
                                                        <option value="<?= e((string) $installmentTotalOption) ?>" <?= $installmentTotalOption === 2 ? 'selected' : '' ?>>
                                                            <?= e((string) $installmentTotalOption) ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <input type="hidden" name="installment_progress" value="" data-accounting-installment-progress>
                                            <input
                                                type="text"
                                                name="total_amount_value"
                                                class="accounting-input accounting-input-amount accounting-input-installment-total"
                                                placeholder="Valor total"
                                                aria-label="Valor total"
                                                data-accounting-installment-total-amount
                                                disabled
                                            >
                                        </div>
                                    </div>
                                    <div class="accounting-create-actions">
                                        <button type="submit" class="btn btn-mini">Adicionar</button>
                                        <button type="button" class="btn btn-mini btn-ghost" data-accounting-create-cancel>Cancelar</button>
                                    </div>
                                </form>
                            </details>

                            <dl class="accounting-totals">
                                <div>
                                    <dt>Total</dt>
                                    <dd><?= e((string) ($accountingSummary['expense_total_display'] ?? 'R$ 0,00')) ?></dd>
                                </div>
                                <div class="is-strong">
                                    <dt>Falta pagar</dt>
                                    <dd><?= e((string) ($accountingSummary['expense_remaining_display'] ?? 'R$ 0,00')) ?></dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    <section class="accounting-card">
                        <header class="accounting-card-head">
                            <div class="accounting-card-head-text">
                                <h3>Entradas</h3>
                                <p>Receitas do mes</p>
                            </div>
                            <span><?= e((string) count($accountingIncomeEntries)) ?> item(ns)</span>
                        </header>

                        <div class="accounting-entries">
                            <?php if (empty($accountingIncomeEntries)): ?>
                                <div class="accounting-empty">Nenhuma entrada cadastrada neste mes.</div>
                            <?php else: ?>
                                <?php foreach ($accountingIncomeEntries as $accountingEntry): ?>
                                    <?php
                                    $accountingEntryId = (int) ($accountingEntry['id'] ?? 0);
                                    $accountingEntryLabel = (string) ($accountingEntry['label'] ?? '');
                                    $accountingEntryAmountInput = (string) ($accountingEntry['amount_input'] ?? '0,00');
                                    $accountingEntryTotalAmountInput = (string) ($accountingEntry['total_amount_input'] ?? $accountingEntryAmountInput);
                                    $accountingEntryIsSettled = ((int) ($accountingEntry['is_settled'] ?? 0)) === 1;
                                    $accountingEntryIsInstallment = ((int) ($accountingEntry['is_installment'] ?? 0)) === 1;
                                    $accountingEntryInstallmentProgress = (string) ($accountingEntry['installment_progress'] ?? '');
                                    $accountingEntryInstallmentBadge = $accountingEntryInstallmentProgress !== ''
                                        ? ('Parcela ' . $accountingEntryInstallmentProgress)
                                        : 'Parcela';
                                    ?>
                                    <div class="accounting-entry-row">
                                        <form method="post" class="accounting-entry-form" data-accounting-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <input
                                                type="text"
                                                name="label"
                                                value="<?= e($accountingEntryLabel) ?>"
                                                maxlength="120"
                                                class="accounting-input accounting-input-label"
                                                placeholder="Nome da entrada"
                                                required
                                            >
                                            <?php if ($accountingEntryIsInstallment): ?>
                                                <button
                                                    type="button"
                                                    class="accounting-entry-badge is-installment accounting-entry-badge-inline"
                                                    data-accounting-entry-edit
                                                    aria-label="Editar entrada parcelada"
                                                >
                                                    <?= e($accountingEntryInstallmentBadge) ?>
                                                </button>
                                            <?php endif; ?>
                                            <input
                                                type="text"
                                                name="amount_value"
                                                value="<?= e($accountingEntryAmountInput) ?>"
                                                class="accounting-input accounting-input-amount"
                                                placeholder="0,00"
                                                required
                                                data-accounting-primary-amount
                                                <?= $accountingEntryIsInstallment ? 'readonly' : '' ?>
                                            >
                                            <div class="accounting-entry-status">
                                                <label class="accounting-check">
                                                    <input type="checkbox" name="is_settled" value="1" <?= $accountingEntryIsSettled ? 'checked' : '' ?>>
                                                    <span>Recebido</span>
                                                </label>
                                            </div>
                                            <input
                                                type="hidden"
                                                name="is_installment"
                                                value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="installment_progress"
                                                value="<?= e($accountingEntryInstallmentProgress) ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="total_amount_value"
                                                value="<?= e($accountingEntryTotalAmountInput) ?>"
                                            >
                                        </form>
                                        <form method="post" class="accounting-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <button type="submit" class="vault-entry-delete-button" aria-label="Excluir entrada">
                                                <span aria-hidden="true">&#10005;</span>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="accounting-card-footer">
                            <details class="accounting-create-toggle">
                                <summary class="accounting-create-trigger">+ Adicionar</summary>
                                <form method="post" class="accounting-create-form" data-accounting-form>
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="create_accounting_entry">
                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                    <input type="hidden" name="entry_type" value="income">
                                    <input
                                        type="text"
                                        name="label"
                                        maxlength="120"
                                        class="accounting-input accounting-input-label"
                                        placeholder="Nova entrada"
                                        required
                                    >
                                    <input
                                        type="text"
                                        name="amount_value"
                                        class="accounting-input accounting-input-amount"
                                        placeholder="0,00"
                                        required
                                        data-accounting-primary-amount
                                    >
                                    <label class="accounting-check">
                                        <input type="checkbox" name="is_settled" value="1">
                                        <span>Recebido</span>
                                    </label>
                                    <input type="hidden" name="is_installment" value="0">
                                    <input type="hidden" name="installment_progress" value="">
                                    <input type="hidden" name="total_amount_value" value="">
                                    <div class="accounting-create-actions">
                                        <button type="submit" class="btn btn-mini">Adicionar</button>
                                        <button type="button" class="btn btn-mini btn-ghost" data-accounting-create-cancel>Cancelar</button>
                                    </div>
                                </form>
                            </details>

                            <dl class="accounting-totals">
                                <div class="is-faturamento">
                                    <dt>Faturamento</dt>
                                    <dd>
                                        <span class="accounting-faturamento-recebido">
                                            <?= e((string) ($accountingSummary['income_received_display'] ?? 'R$ 0,00')) ?>
                                        </span>
                                        <span class="accounting-faturamento-separator">/</span>
                                        <strong class="accounting-faturamento-total">
                                            <?= e((string) ($accountingSummary['income_total_display'] ?? 'R$ 0,00')) ?>
                                        </strong>
                                    </dd>
                                </div>
                                <div class="is-strong is-positive">
                                    <dt>A receber</dt>
                                    <dd><?= e((string) ($accountingSummary['income_remaining_display'] ?? 'R$ 0,00')) ?></dd>
                                </div>
                            </dl>
                        </div>
                    </section>
                </div>

                <section class="accounting-balance-card">
                    <dl class="accounting-balance-values">
                        <div>
                            <dt>Saldo atual</dt>
                            <dd><?= e((string) ($accountingSummary['current_balance_display'] ?? 'R$ 0,00')) ?></dd>
                        </div>
                        <div class="is-final">
                            <dt>Saldo final</dt>
                            <dd><?= e((string) ($accountingSummary['final_balance_display'] ?? 'R$ 0,00')) ?></dd>
                        </div>
                    </dl>
                </section>
            </div>
        </section>

        <?php if (!empty($showUsersDashboardTab)): ?>
            <?php $workspaceTaskStatusConfig = $statusConfig; ?>
            <?php include __DIR__ . '/users_panel.php'; ?>
        <?php endif; ?>
    </section>
</main>

<div class="modal-backdrop" data-workspace-create-modal hidden>
    <div class="modal-scrim" data-close-workspace-create-modal></div>
    <section class="modal-card create-group-modal" role="dialog" aria-modal="true" aria-labelledby="workspace-create-title">
        <header class="modal-head">
            <h2 id="workspace-create-title">Novo workspace</h2>
            <button type="button" class="modal-close-button" data-close-workspace-create-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-workspace-create-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_workspace">

            <label>
                <span>Nome do workspace</span>
                <input type="text" name="workspace_name" maxlength="80" required data-workspace-create-name-input>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-workspace-create-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill">Criar workspace</button>
            </div>
        </form>
    </section>
</div>
<div class="modal-backdrop" data-create-modal hidden>
    <div class="modal-scrim" data-close-create-modal></div>
    <section class="modal-card create-task-modal task-create-modal-large" role="dialog" aria-modal="true" aria-labelledby="create-task-title">
        <header class="modal-head">
            <h2 id="create-task-title">Nova tarefa</h2>
            <button type="button" class="modal-close-button" data-close-create-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-create-task-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_task">
            <input type="hidden" name="redirect_group" value="<?= e((string) ($groupFilter ?? '')) ?>">
            <input type="hidden" name="redirect_created_by" value="<?= e((string) ($creatorFilterId ?? '')) ?>">
            <input type="hidden" name="redirect_assignee" value="<?= e((string) ($assigneeFilterId ?? '')) ?>">

            <label>
                <span>Titulo</span>
                <div class="create-task-title-composer" data-create-task-title-composer>
                    <div class="create-task-title-tag-picker" data-create-task-title-tag-picker>
                        <button
                            type="button"
                            class="create-task-title-tag-trigger is-empty"
                            data-create-task-title-tag-trigger
                            aria-haspopup="listbox"
                            aria-expanded="false"
                        >tag</button>
                        <input
                            type="text"
                            maxlength="40"
                            placeholder="Criar tag"
                            autocomplete="off"
                            data-create-task-title-tag-custom
                            hidden
                        >
                        <div class="create-task-title-tag-menu" data-create-task-title-tag-menu hidden></div>
                    </div>
                    <input type="text" name="title" maxlength="140" required data-create-task-title-input>
                </div>
                <input type="hidden" name="title_tag" value="" data-create-task-title-tag-input>
                <input type="hidden" name="title_tag_color" value="<?= e(taskTitleTagDefaultColor()) ?>" data-create-task-title-tag-color-input>
            </label>

            <div class="task-detail-inline-controls">
                <div class="assignee-picker-wrap task-detail-inline-field task-detail-inline-assignees">
                    <span class="assignee-picker-label">Responsaveis</span>
                    <details class="assignee-picker task-detail-inline-assignee-picker">
                        <summary>Selecionar</summary>
                        <div class="assignee-picker-menu">
                            <?php if (!$users): ?>
                                <p class="assignee-picker-empty">Nenhum usuario cadastrado.</p>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <label class="assignee-option">
                                        <input
                                            type="checkbox"
                                            name="assigned_to[]"
                                            value="<?= e((string) $user['id']) ?>"
                                            data-assignee-name="<?= e((string) $user['name']) ?>"
                                            data-assignee-avatar="<?= e(userAvatarDataUrl($user)) ?>"
                                            data-assignee-initial="<?= e(userDisplayInitial((string) $user['name'])) ?>"
                                            <?= (int) $user['id'] === (int) $currentUser['id'] ? 'checked' : '' ?>
                                        >
                                        <?= renderUserAvatar($user, 'avatar small assignee-option-avatar', true, 'span') ?>
                                        <span class="assignee-option-text"><?= e((string) $user['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>

                <div class="task-detail-inline-field task-detail-inline-status">
                    <span>Status</span>
                    <div class="status-stepper task-detail-status-stepper" data-status-stepper>
                        <button
                            type="button"
                            class="status-stepper-btn"
                            data-status-step="-1"
                            aria-label="Status anterior"
                        >
                            <span aria-hidden="true">&#8249;</span>
                        </button>

                        <div class="tag-field tag-field-status row-inline-picker-wrap" data-inline-select-wrap>
                            <details
                                class="row-inline-picker status-inline-picker status-<?= e($defaultTaskStatusKind) ?>"
                                data-inline-select-picker
                                data-status-color="<?= e($defaultTaskStatusColor) ?>"
                                style="<?= e($defaultTaskStatusCssVars) ?>"
                            >
                                <summary aria-label="Status da tarefa">
                                    <span class="row-inline-picker-summary-text" data-inline-select-text><?= e($defaultTaskStatusLabel) ?></span>
                                </summary>
                                <div
                                    class="assignee-picker-menu row-inline-picker-menu"
                                    role="listbox"
                                    aria-label="Selecionar status"
                                >
                                    <?php foreach ($statusOptions as $key => $label): ?>
                                        <?php
                                        $optionMeta = $statusMetaByKey[$key] ?? taskStatusMeta($key);
                                        $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                        $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                        $optionColor = normalizeTaskStatusColor(
                                            (string) ($optionMeta['color'] ?? ''),
                                            $optionKind
                                        );
                                        $optionCssVars = (string) ($optionMeta['css_vars'] ?? taskStatusCssVars($optionColor));
                                        ?>
                                        <button
                                            type="button"
                                            class="row-inline-picker-option status-<?= e($optionKind) ?><?= $key === $defaultTaskStatusKey ? ' is-active' : '' ?>"
                                            data-inline-select-option
                                            data-value="<?= e($key) ?>"
                                            data-label="<?= e($label) ?>"
                                            data-status-kind="<?= e($optionKind) ?>"
                                            data-status-color="<?= e($optionColor) ?>"
                                            data-status-order="<?= e((string) $optionOrder) ?>"
                                            style="<?= e($optionCssVars) ?>"
                                            role="option"
                                            aria-selected="<?= $key === $defaultTaskStatusKey ? 'true' : 'false' ?>"
                                        ><?= e($label) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                            <select
                                name="status"
                                class="tag-select status-select status-<?= e($defaultTaskStatusKind) ?> row-inline-picker-native"
                                data-inline-select-source
                                data-status-color="<?= e($defaultTaskStatusColor) ?>"
                                style="<?= e($defaultTaskStatusCssVars) ?>"
                                aria-label="Status"
                                hidden
                            >
                                <?php foreach ($statusOptions as $key => $label): ?>
                                    <?php
                                    $optionMeta = $statusMetaByKey[$key] ?? taskStatusMeta($key);
                                    $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                    $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                    $optionColor = normalizeTaskStatusColor(
                                        (string) ($optionMeta['color'] ?? ''),
                                        $optionKind
                                    );
                                    ?>
                                    <option
                                        value="<?= e($key) ?>"
                                        data-status-kind="<?= e($optionKind) ?>"
                                        data-status-color="<?= e($optionColor) ?>"
                                        data-status-order="<?= e((string) $optionOrder) ?>"
                                        <?= $key === $defaultTaskStatusKey ? ' selected' : '' ?>
                                    ><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button
                            type="button"
                            class="status-stepper-btn"
                            data-status-step="1"
                            aria-label="Proximo status"
                        >
                            <span aria-hidden="true">&#8250;</span>
                        </button>
                    </div>
                </div>

                <div class="task-detail-inline-field task-detail-inline-priority">
                    <span>Prioridade</span>
                    <div
                        class="tag-field tag-field-priority row-inline-picker-wrap"
                        data-inline-select-wrap
                        data-inline-picker-kind="priority"
                    >
                        <details
                            class="row-inline-picker priority-inline-picker priority-medium"
                            data-inline-select-picker
                        >
                            <summary aria-label="Prioridade da tarefa">
                                <span class="row-inline-picker-summary-icon" aria-hidden="true">&#9873;</span>
                                <span class="row-inline-picker-summary-text sr-only" data-inline-select-text>Media</span>
                            </summary>
                            <div
                                class="assignee-picker-menu row-inline-picker-menu"
                                role="listbox"
                                aria-label="Selecionar prioridade"
                            >
                                <?php foreach ($priorityOptions as $key => $label): ?>
                                    <button
                                        type="button"
                                        class="row-inline-picker-option priority-<?= e($key) ?><?= $key === 'medium' ? ' is-active' : '' ?>"
                                        data-inline-select-option
                                        data-value="<?= e($key) ?>"
                                        data-label="<?= e($label) ?>"
                                        role="option"
                                        aria-selected="<?= $key === 'medium' ? 'true' : 'false' ?>"
                                    >
                                        <span class="row-inline-picker-option-flag" aria-hidden="true">&#9873;</span>
                                        <span><?= e($label) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <select
                            name="priority"
                            class="tag-select priority-select priority-medium row-inline-picker-native"
                            data-inline-select-source
                            aria-label="Prioridade"
                            hidden
                        >
                            <?php foreach ($priorityOptions as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $key === 'medium' ? ' selected' : '' ?>>&#9873;</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <label>
                    <span>Grupo</span>
                    <div
                        class="task-detail-group-inline-wrap row-inline-picker-wrap"
                        data-inline-select-wrap
                        data-inline-picker-kind="group"
                    >
                        <details class="row-inline-picker group-inline-picker" data-inline-select-picker>
                            <summary aria-label="Grupo da tarefa">
                                <span class="row-inline-picker-summary-text" data-inline-select-text>
                                    <?= $taskGroupsWithAccess ? e((string) $taskGroupsWithAccess[0]) : 'Sem grupo com acesso' ?>
                                </span>
                            </summary>
                            <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Selecionar grupo">
                                <?php if (!$taskGroupsWithAccess): ?>
                                    <button
                                        type="button"
                                        class="row-inline-picker-option"
                                        data-inline-select-option
                                        data-value=""
                                        data-label="Sem grupo com acesso"
                                        role="option"
                                        aria-selected="true"
                                        disabled
                                    >Sem grupo com acesso</button>
                                <?php else: ?>
                                    <?php foreach ($taskGroupsWithAccess as $groupIndex => $groupNameOption): ?>
                                        <button
                                            type="button"
                                            class="row-inline-picker-option<?= $groupIndex === 0 ? ' is-active' : '' ?>"
                                            data-inline-select-option
                                            data-value="<?= e((string) $groupNameOption) ?>"
                                            data-label="<?= e((string) $groupNameOption) ?>"
                                            role="option"
                                            aria-selected="<?= $groupIndex === 0 ? 'true' : 'false' ?>"
                                        ><?= e((string) $groupNameOption) ?></button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </details>
                        <select
                            name="group_name"
                            class="group-tag-select row-inline-picker-native task-group-inline-select"
                            data-inline-select-source
                            data-inline-select-sync-options="group"
                            data-create-task-group-input
                            <?= empty($taskGroupsWithAccess) ? 'disabled' : '' ?>
                            hidden
                        >
                            <?php if (!$taskGroupsWithAccess): ?>
                                <option value="">Sem grupo com acesso</option>
                            <?php else: ?>
                                <?php foreach ($taskGroupsWithAccess as $groupNameOption): ?>
                                    <option value="<?= e((string) $groupNameOption) ?>">
                                        <?= e((string) $groupNameOption) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </label>

                <label>
                    <span>Prazo</span>
                    <input type="date" name="due_date" value="<?= e((new DateTimeImmutable('today'))->format('Y-m-d')) ?>">
                </label>
            </div>

            <div class="task-detail-edit-main-row">
                <div class="task-detail-edit-description-field">
                    <span>Descricao</span>
                    <div class="task-detail-edit-description-wrap task-create-description-wrap" data-create-task-description-wrap>
                        <div class="task-detail-edit-description-toolbar task-create-description-toolbar" data-create-task-description-toolbar>
                            <button
                                type="button"
                                data-create-task-description-format="bold"
                                title="Negrito"
                                aria-label="Negrito"
                            ><strong aria-hidden="true">B</strong></button>
                            <button
                                type="button"
                                data-create-task-description-format="italic"
                                title="Italico"
                                aria-label="Italico"
                            ><em aria-hidden="true">I</em></button>
                            <button
                                type="button"
                                data-create-task-description-action="divider"
                                title="Inserir separador"
                                aria-label="Inserir separador"
                            >
                                <span class="task-description-toolbar-divider-icon" aria-hidden="true"></span>
                            </button>
                        </div>
                        <div
                            class="task-detail-edit-description-editor task-create-description-editor"
                            data-create-task-description-editor
                            contenteditable="true"
                            role="textbox"
                            aria-multiline="true"
                            aria-label="Descricao da tarefa"
                        ></div>
                    </div>
                    <textarea name="description" rows="5" data-create-task-description hidden></textarea>
                </div>

                <div class="task-detail-edit-images-field">
                    <span>Imagens de referencia</span>
                    <div class="task-detail-edit-image-picker-actions">
                        <button type="button" class="btn btn-mini btn-ghost task-image-add-button" data-create-task-image-add>
                            <span class="task-image-add-button-icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" focusable="false">
                                    <rect x="2.5" y="4" width="15" height="12" rx="2.2"></rect>
                                    <circle cx="7.2" cy="8.1" r="1.4"></circle>
                                    <path d="M3.8 14.8 8.3 10.2l3 2.7 2.1-1.8 2.8 3.7"></path>
                                </svg>
                            </span>
                            <span class="task-image-add-button-label">Adicionar imagem</span>
                        </button>
                    </div>
                    <div class="task-detail-edit-image-picker" data-create-task-image-picker tabindex="0" aria-label="Adicionar imagens de referencia">
                        <input type="file" accept="image/*" multiple data-create-task-image-input hidden>
                        <div class="task-detail-edit-image-list" data-create-task-image-list></div>
                    </div>
                    <textarea name="reference_images_json" rows="1" data-create-task-images hidden></textarea>
                </div>
            </div>

            <label class="task-detail-edit-links-field">
                <span>Links de referencia</span>
                <textarea
                    name="reference_links_json"
                    rows="1"
                    class="task-detail-reference-input"
                    data-create-task-links
                ></textarea>
            </label>

            <div class="task-subtasks-editor">
                <div class="task-subtasks-editor-head">
                    <div class="task-subtasks-editor-title">
                        <span>Etapas</span>
                        <label class="task-subtasks-dependency-toggle" title="Ativar sequência entre etapas">
                            <input
                                type="checkbox"
                                aria-label="Ativar sequência entre etapas"
                                data-create-task-subtasks-dependency-toggle
                            >
                            <span class="task-subtasks-dependency-icon" aria-hidden="true">
                                <svg viewBox="0 0 20 20" focusable="false">
                                    <rect x="5.2" y="2.2" width="9.6" height="3.1" rx="1.1"></rect>
                                    <rect x="5.2" y="8.5" width="9.6" height="3.1" rx="1.1"></rect>
                                    <rect x="5.2" y="14.7" width="9.6" height="3.1" rx="1.1"></rect>
                                    <path d="M10 5.8v1.7"></path>
                                    <path d="M10 12.1v1.7"></path>
                                    <path d="M8.9 6.5 10 7.7l1.1-1.2"></path>
                                    <path d="M8.9 12.8 10 14l1.1-1.2"></path>
                                </svg>
                            </span>
                            <span class="sr-only">Ativar sequência entre etapas</span>
                        </label>
                    </div>
                </div>
                <div class="task-subtasks-edit-add">
                    <input
                        type="text"
                        maxlength="120"
                        placeholder="Nova etapa"
                        data-create-task-subtask-input
                    >
                    <button type="button" class="btn btn-mini btn-ghost" data-create-task-subtask-add>Adicionar etapa</button>
                </div>
                <div class="task-subtasks-edit-list" data-create-task-subtasks-list></div>
                <textarea name="subtasks_json" rows="1" data-create-task-subtasks hidden></textarea>
                <input type="hidden" name="subtasks_dependency_enabled" value="0" data-create-task-subtasks-dependency>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-create-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($taskGroupsWithAccess) ? 'disabled' : '' ?>>Adicionar tarefa</button>
            </div>
        </form>
    </section>
</div>

<?php if (!empty($canManageWorkspace)): ?>
    <?php
    $createGroupPermissionRows = [];
    $createGroupEnabledCount = 0;
    $createGroupCurrentUserId = (int) ($currentUser['id'] ?? 0);
    foreach ($workspaceMembers as $workspaceMember) {
        $memberId = (int) ($workspaceMember['id'] ?? 0);
        if ($memberId <= 0) {
            continue;
        }
        $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
        $memberEnabled = true;
        if ($memberEnabled) {
            $createGroupEnabledCount++;
        }
        $createGroupPermissionRows[] = [
            'id' => $memberId,
            'name' => (string) ($workspaceMember['name'] ?? 'Usuario'),
            'email' => (string) ($workspaceMember['email'] ?? ''),
            'enabled' => $memberEnabled,
            'required' => $memberId === $createGroupCurrentUserId,
            'role_label' => $memberRole === 'admin' ? 'Administrador' : 'Membro',
        ];
    }
    $createGroupTotalCount = count($createGroupPermissionRows);
    $createGroupAllEnabled = $createGroupTotalCount > 0 && $createGroupEnabledCount === $createGroupTotalCount;
    $createGroupCounterLabel = $createGroupEnabledCount . '/' . $createGroupTotalCount;
    ?>
    <div class="modal-backdrop" data-create-group-modal data-group-permissions-modal="task-group-create" hidden>
        <div class="modal-scrim" data-close-create-group-modal></div>
        <section class="modal-card create-group-modal" role="dialog" aria-modal="true" aria-labelledby="create-group-title">
            <header class="modal-head">
                <h2 id="create-group-title">Novo grupo</h2>
                <button type="button" class="modal-close-button" data-close-create-group-modal aria-label="Fechar modal">
                    <span aria-hidden="true">&#10005;</span>
                </button>
            </header>

            <form method="post" class="form-stack modal-form" data-create-group-form>
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="action" value="create_group">

                <label>
                    <span>Nome do grupo</span>
                    <input type="text" name="group_name" maxlength="60" required data-create-group-name-input>
                </label>

                <div class="group-permissions-scope" data-group-permissions-scope>
                    <label class="group-permissions-toggle group-permissions-toggle-master">
                        <input
                            type="checkbox"
                            data-permission-all-checkbox
                            <?= $createGroupAllEnabled ? 'checked' : '' ?>
                            <?= $createGroupTotalCount === 0 ? 'disabled' : '' ?>
                        >
                        <span>Aplicar a todos</span>
                    </label>
                    <span class="group-permissions-counter" data-permission-counter><?= e($createGroupCounterLabel) ?> permitidos</span>
                </div>

                <details class="group-permissions-members" open>
                    <summary>
                        <span>Acesso inicial do grupo</span>
                        <span class="group-permissions-summary-count" data-permission-summary-count><?= e($createGroupCounterLabel) ?></span>
                    </summary>
                    <div class="group-permissions-list">
                        <?php if (!$createGroupPermissionRows): ?>
                            <p class="group-permissions-empty">Nenhum usuario disponivel para configurar.</p>
                        <?php else: ?>
                            <?php foreach ($createGroupPermissionRows as $createGroupPermissionRow): ?>
                                <div class="group-permissions-row">
                                    <input type="hidden" name="member_ids[]" value="<?= e((string) $createGroupPermissionRow['id']) ?>">
                                    <div class="group-permissions-user">
                                        <strong><?= e((string) $createGroupPermissionRow['name']) ?></strong>
                                        <span><?= e((string) $createGroupPermissionRow['email']) ?></span>
                                        <span><?= e((string) $createGroupPermissionRow['role_label']) ?></span>
                                    </div>
                                    <label class="group-permissions-toggle">
                                        <input
                                            type="checkbox"
                                            name="permissions[<?= e((string) $createGroupPermissionRow['id']) ?>][enabled]"
                                            value="1"
                                            <?= !empty($createGroupPermissionRow['enabled']) ? 'checked' : '' ?>
                                            <?= !empty($createGroupPermissionRow['required']) ? 'disabled' : '' ?>
                                            data-permission-enabled-checkbox
                                        >
                                        <span><?= !empty($createGroupPermissionRow['required']) ? 'Obrigatorio' : 'Permitido' ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </details>

                <div class="modal-actions">
                    <button type="button" class="btn btn-mini btn-ghost" data-close-create-group-modal>Cancelar</button>
                    <button type="submit" class="btn btn-pill">Criar grupo</button>
                </div>
            </form>
        </section>
    </div>
<?php endif; ?>

<div class="modal-backdrop" data-vault-group-modal hidden>
    <div class="modal-scrim" data-close-vault-group-modal></div>
    <section class="modal-card create-group-modal" role="dialog" aria-modal="true" aria-labelledby="vault-group-modal-title">
        <header class="modal-head">
            <h2 id="vault-group-modal-title">Novo grupo do cofre</h2>
            <button type="button" class="modal-close-button" data-close-vault-group-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-vault-group-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_vault_group">

            <label>
                <span>Nome do grupo</span>
                <input type="text" name="group_name" maxlength="60" required data-vault-group-name-input>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-vault-group-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill">Criar grupo</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-vault-entry-modal hidden>
    <div class="modal-scrim" data-close-vault-entry-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="vault-entry-modal-title">
        <header class="modal-head">
            <h2 id="vault-entry-modal-title">Novo dado de acesso</h2>
            <button type="button" class="modal-close-button" data-close-vault-entry-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-vault-entry-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_vault_entry">

            <label>
                <span>Grupo</span>
                <select name="group_name" data-vault-entry-group <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>>
                    <?php if (!$vaultGroupsWithAccess): ?>
                        <option value="">Sem grupo com acesso</option>
                    <?php else: ?>
                        <?php foreach ($vaultGroupsWithAccess as $vaultGroupOption): ?>
                            <option value="<?= e((string) $vaultGroupOption) ?>"><?= e((string) $vaultGroupOption) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Nome</span>
                <input type="text" name="label" maxlength="120" required data-vault-entry-label>
            </label>

            <label>
                <span>Login</span>
                <input type="text" name="login_value" maxlength="220" data-vault-entry-login>
            </label>

            <label>
                <span>Senha</span>
                <input type="text" name="password_value" maxlength="220" data-vault-entry-password>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-vault-entry-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>>Adicionar</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-vault-entry-edit-modal hidden>
    <div class="modal-scrim" data-close-vault-entry-edit-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="vault-entry-edit-modal-title">
        <header class="modal-head">
            <h2 id="vault-entry-edit-modal-title">Editar dado de acesso</h2>
            <button type="button" class="modal-close-button" data-close-vault-entry-edit-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-vault-entry-edit-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="update_vault_entry">
            <input type="hidden" name="entry_id" value="" data-vault-entry-edit-id>

            <label>
                <span>Grupo</span>
                <select name="group_name" data-vault-entry-edit-group <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>>
                    <?php if (!$vaultGroupsWithAccess): ?>
                        <option value="">Sem grupo com acesso</option>
                    <?php else: ?>
                        <?php foreach ($vaultGroupsWithAccess as $vaultGroupOption): ?>
                            <option value="<?= e((string) $vaultGroupOption) ?>"><?= e((string) $vaultGroupOption) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Nome</span>
                <input type="text" name="label" maxlength="120" required data-vault-entry-edit-label>
            </label>

            <label>
                <span>Login</span>
                <input type="text" name="login_value" maxlength="220" data-vault-entry-edit-login>
            </label>

            <label>
                <span>Senha</span>
                <input type="text" name="password_value" maxlength="220" data-vault-entry-edit-password>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-vault-entry-edit-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>>Salvar</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-due-group-modal hidden>
    <div class="modal-scrim" data-close-due-group-modal></div>
    <section class="modal-card create-group-modal" role="dialog" aria-modal="true" aria-labelledby="due-group-modal-title">
        <header class="modal-head">
            <h2 id="due-group-modal-title">Novo grupo de vencimentos</h2>
            <button type="button" class="modal-close-button" data-close-due-group-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-due-group-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_due_group">

            <label>
                <span>Nome do grupo</span>
                <input type="text" name="group_name" maxlength="60" required data-due-group-name-input>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-due-group-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill">Criar grupo</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-due-entry-modal hidden>
    <div class="modal-scrim" data-close-due-entry-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="due-entry-modal-title">
        <header class="modal-head">
            <h2 id="due-entry-modal-title">Novo vencimento</h2>
            <button type="button" class="modal-close-button" data-close-due-entry-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-due-entry-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_due_entry">

            <label>
                <span>Grupo</span>
                <select name="group_name" data-due-entry-group <?= empty($dueGroupsWithAccess) ? 'disabled' : '' ?>>
                    <?php if (!$dueGroupsWithAccess): ?>
                        <option value="">Sem grupo com acesso</option>
                    <?php else: ?>
                        <?php foreach ($dueGroupsWithAccess as $dueGroupOption): ?>
                            <option value="<?= e((string) $dueGroupOption) ?>"><?= e((string) $dueGroupOption) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Nome</span>
                <input type="text" name="label" maxlength="120" required data-due-entry-label>
            </label>

            <label>
                <span>Valor (R$)</span>
                <input type="number" name="amount_value" min="0" step="0.01" required data-due-entry-amount>
            </label>

            <label>
                <span>Recorrencia</span>
                <select name="recurrence_type" data-due-entry-recurrence>
                    <option value="monthly" selected>Mensal</option>
                    <option value="annual">Anual</option>
                    <option value="fixed">Sem recorrencia</option>
                </select>
            </label>

            <div class="due-entry-schedule-grid">
                <label data-due-entry-monthly-wrap>
                    <span>Dia do mes</span>
                    <input type="number" name="monthly_day" min="1" max="31" step="1" data-due-entry-monthly-day>
                </label>

                <label data-due-entry-fixed-wrap hidden>
                    <span>Data de vencimento</span>
                    <input type="date" data-due-entry-fixed-date>
                </label>

                <div class="due-entry-annual-grid" data-due-entry-annual-wrap hidden>
                    <label>
                        <span>Mes</span>
                        <select data-due-entry-annual-month>
                            <?php for ($dueMonthIndex = 1; $dueMonthIndex <= 12; $dueMonthIndex++): ?>
                                <option value="<?= e(str_pad((string) $dueMonthIndex, 2, '0', STR_PAD_LEFT)) ?>">
                                    <?= e(str_pad((string) $dueMonthIndex, 2, '0', STR_PAD_LEFT)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>
                        <span>Dia</span>
                        <input type="number" min="1" max="31" step="1" data-due-entry-annual-day>
                    </label>
                </div>
            </div>
            <input type="hidden" name="due_date" data-due-entry-date>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-due-entry-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($dueGroupsWithAccess) ? 'disabled' : '' ?>>Adicionar</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-due-entry-edit-modal hidden>
    <div class="modal-scrim" data-close-due-entry-edit-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="due-entry-edit-modal-title">
        <header class="modal-head">
            <h2 id="due-entry-edit-modal-title">Editar vencimento</h2>
            <button type="button" class="modal-close-button" data-close-due-entry-edit-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-due-entry-edit-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="update_due_entry">
            <input type="hidden" name="entry_id" value="" data-due-entry-edit-id>

            <label>
                <span>Grupo</span>
                <select name="group_name" data-due-entry-edit-group <?= empty($dueGroupsWithAccess) ? 'disabled' : '' ?>>
                    <?php if (!$dueGroupsWithAccess): ?>
                        <option value="">Sem grupo com acesso</option>
                    <?php else: ?>
                        <?php foreach ($dueGroupsWithAccess as $dueGroupOption): ?>
                            <option value="<?= e((string) $dueGroupOption) ?>"><?= e((string) $dueGroupOption) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Nome</span>
                <input type="text" name="label" maxlength="120" required data-due-entry-edit-label>
            </label>

            <label>
                <span>Valor (R$)</span>
                <input type="number" name="amount_value" min="0" step="0.01" required data-due-entry-edit-amount>
            </label>

            <label>
                <span>Recorrencia</span>
                <select name="recurrence_type" data-due-entry-edit-recurrence>
                    <option value="monthly">Mensal</option>
                    <option value="annual">Anual</option>
                    <option value="fixed">Sem recorrencia</option>
                </select>
            </label>

            <div class="due-entry-schedule-grid">
                <label data-due-entry-edit-monthly-wrap>
                    <span>Dia do mes</span>
                    <input type="number" name="monthly_day" min="1" max="31" step="1" data-due-entry-edit-monthly-day>
                </label>

                <label data-due-entry-edit-fixed-wrap hidden>
                    <span>Data de vencimento</span>
                    <input type="date" data-due-entry-edit-fixed-date>
                </label>

                <div class="due-entry-annual-grid" data-due-entry-edit-annual-wrap hidden>
                    <label>
                        <span>Mes</span>
                        <select data-due-entry-edit-annual-month>
                            <?php for ($dueEditMonthIndex = 1; $dueEditMonthIndex <= 12; $dueEditMonthIndex++): ?>
                                <option value="<?= e(str_pad((string) $dueEditMonthIndex, 2, '0', STR_PAD_LEFT)) ?>">
                                    <?= e(str_pad((string) $dueEditMonthIndex, 2, '0', STR_PAD_LEFT)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label>
                        <span>Dia</span>
                        <input type="number" min="1" max="31" step="1" data-due-entry-edit-annual-day>
                    </label>
                </div>
            </div>
            <input type="hidden" name="due_date" data-due-entry-edit-date>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-due-entry-edit-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($dueGroupsWithAccess) ? 'disabled' : '' ?>>Salvar</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-inventory-group-modal hidden>
    <div class="modal-scrim" data-close-inventory-group-modal></div>
    <section class="modal-card create-group-modal" role="dialog" aria-modal="true" aria-labelledby="inventory-group-modal-title">
        <header class="modal-head">
            <h2 id="inventory-group-modal-title">Novo grupo de estoque</h2>
            <button type="button" class="modal-close-button" data-close-inventory-group-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-inventory-group-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_inventory_group">

            <label>
                <span>Nome do grupo</span>
                <input type="text" name="group_name" maxlength="60" required data-inventory-group-name-input>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-inventory-group-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill">Criar grupo</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-inventory-entry-modal hidden>
    <div class="modal-scrim" data-close-inventory-entry-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="inventory-entry-modal-title">
        <header class="modal-head">
            <h2 id="inventory-entry-modal-title">Novo item de estoque</h2>
            <button type="button" class="modal-close-button" data-close-inventory-entry-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-inventory-entry-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_inventory_entry">

            <label>
                <span>Grupo</span>
                <select name="group_name" data-inventory-entry-group <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>>
                    <?php if (!$inventoryGroupsWithAccess): ?>
                        <option value="">Sem grupo com acesso</option>
                    <?php else: ?>
                        <?php foreach ($inventoryGroupsWithAccess as $inventoryGroupOption): ?>
                            <option value="<?= e((string) $inventoryGroupOption) ?>"><?= e((string) $inventoryGroupOption) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Item</span>
                <input type="text" name="label" maxlength="120" required data-inventory-entry-label>
            </label>

            <div class="form-row">
                <label>
                    <span>Quantidade</span>
                    <input type="number" name="quantity_value" min="0" step="1" required data-inventory-entry-quantity>
                </label>
                <label>
                    <span>Unidade</span>
                    <input type="text" name="unit_label" maxlength="30" value="un" required data-inventory-entry-unit>
                </label>
            </div>

            <label>
                <span>Estoque minimo</span>
                <input type="number" name="min_quantity_value" min="0" step="1" data-inventory-entry-min-quantity>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-inventory-entry-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>>Adicionar</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-inventory-entry-edit-modal hidden>
    <div class="modal-scrim" data-close-inventory-entry-edit-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="inventory-entry-edit-modal-title">
        <header class="modal-head">
            <h2 id="inventory-entry-edit-modal-title">Editar item de estoque</h2>
            <button type="button" class="modal-close-button" data-close-inventory-entry-edit-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-inventory-entry-edit-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="update_inventory_entry">
            <input type="hidden" name="entry_id" value="" data-inventory-entry-edit-id>

            <label>
                <span>Grupo</span>
                <select name="group_name" data-inventory-entry-edit-group <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>>
                    <?php if (!$inventoryGroupsWithAccess): ?>
                        <option value="">Sem grupo com acesso</option>
                    <?php else: ?>
                        <?php foreach ($inventoryGroupsWithAccess as $inventoryGroupOption): ?>
                            <option value="<?= e((string) $inventoryGroupOption) ?>"><?= e((string) $inventoryGroupOption) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </label>

            <label>
                <span>Item</span>
                <input type="text" name="label" maxlength="120" required data-inventory-entry-edit-label>
            </label>

            <div class="form-row">
                <label>
                    <span>Quantidade</span>
                    <input type="number" name="quantity_value" min="0" step="1" required data-inventory-entry-edit-quantity>
                </label>
                <label>
                    <span>Unidade</span>
                    <input type="text" name="unit_label" maxlength="30" required data-inventory-entry-edit-unit>
                </label>
            </div>

            <label>
                <span>Estoque minimo</span>
                <input type="number" name="min_quantity_value" min="0" step="1" data-inventory-entry-edit-min-quantity>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-inventory-entry-edit-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill" <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>>Salvar</button>
            </div>
        </form>
    </section>
</div>

<?php if (!empty($canManageWorkspace)): ?>
    <?php foreach ($taskGroups as $taskGroupPermissionsName): ?>
        <?php
        $taskGroupPermissionsModalKey = 'task-group-perm-' . md5((string) $taskGroupPermissionsName);
        $taskPermissionsByUser = $taskGroupPermissionsByUserMap[$taskGroupPermissionsName] ?? [];
        ?>
        <div class="modal-backdrop" data-group-permissions-modal="<?= e($taskGroupPermissionsModalKey) ?>" hidden>
            <div class="modal-scrim" data-close-group-permissions-modal></div>
            <section class="modal-card group-permissions-modal-card" role="dialog" aria-modal="true" aria-labelledby="task-group-perm-title-<?= e(md5((string) $taskGroupPermissionsName)) ?>">
                <header class="modal-head">
                    <h2 id="task-group-perm-title-<?= e(md5((string) $taskGroupPermissionsName)) ?>">Acesso do grupo: <?= e((string) $taskGroupPermissionsName) ?></h2>
                    <button type="button" class="modal-close-button" data-close-group-permissions-modal aria-label="Fechar modal">
                        <span aria-hidden="true">&#10005;</span>
                    </button>
                </header>

                <form method="post" class="form-stack modal-form group-permissions-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_task_group_permissions">
                    <input type="hidden" name="group_name" value="<?= e((string) $taskGroupPermissionsName) ?>">

                    <?php
                    $taskPermissionRows = [];
                    $taskEnabledCount = 0;
                    foreach ($workspaceMembers as $workspaceMember) {
                        $memberId = (int) ($workspaceMember['id'] ?? 0);
                        if ($memberId <= 0) {
                            continue;
                        }
                        $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
                        $memberPermission = $taskPermissionsByUser[$memberId] ?? [];
                        $memberEnabled = (bool) ($memberPermission['can_view'] ?? true)
                            && (bool) ($memberPermission['can_access'] ?? true);
                        $isRequiredMember = $memberId === (int) ($currentUser['id'] ?? 0);
                        if ($isRequiredMember) {
                            $memberEnabled = true;
                        }
                        if ($memberEnabled) {
                            $taskEnabledCount++;
                        }
                        $taskPermissionRows[] = [
                            'id' => $memberId,
                            'name' => (string) ($workspaceMember['name'] ?? 'Usuario'),
                            'email' => (string) ($workspaceMember['email'] ?? ''),
                            'enabled' => $memberEnabled,
                            'required' => $isRequiredMember,
                            'role_label' => $memberRole === 'admin' ? 'Administrador' : 'Membro',
                        ];
                    }
                    $taskTotalCount = count($taskPermissionRows);
                    $taskAllEnabled = $taskTotalCount > 0 && $taskEnabledCount === $taskTotalCount;
                    $taskCounterLabel = $taskEnabledCount . '/' . $taskTotalCount;
                    ?>

                    <div class="group-permissions-scope" data-group-permissions-scope>
                        <label class="group-permissions-toggle group-permissions-toggle-master">
                            <input
                                type="checkbox"
                                data-permission-all-checkbox
                                <?= $taskAllEnabled ? 'checked' : '' ?>
                                <?= $taskTotalCount === 0 ? 'disabled' : '' ?>
                            >
                            <span>Aplicar a todos</span>
                        </label>
                        <span class="group-permissions-counter" data-permission-counter><?= e($taskCounterLabel) ?> permitidos</span>
                    </div>

                    <details class="group-permissions-members" open>
                        <summary>
                            <span>Usuarios do workspace</span>
                            <span class="group-permissions-summary-count" data-permission-summary-count><?= e($taskCounterLabel) ?></span>
                        </summary>
                        <div class="group-permissions-list">
                            <?php if (!$taskPermissionRows): ?>
                                <p class="group-permissions-empty">Nenhum usuario disponivel para configurar.</p>
                            <?php else: ?>
                                <?php foreach ($taskPermissionRows as $taskPermissionRow): ?>
                                    <div class="group-permissions-row">
                                        <input type="hidden" name="member_ids[]" value="<?= e((string) $taskPermissionRow['id']) ?>">
                                        <div class="group-permissions-user">
                                            <strong><?= e((string) $taskPermissionRow['name']) ?></strong>
                                            <span><?= e((string) $taskPermissionRow['email']) ?></span>
                                            <span><?= e((string) $taskPermissionRow['role_label']) ?></span>
                                        </div>
                                        <label class="group-permissions-toggle">
                                            <input
                                                type="checkbox"
                                                name="permissions[<?= e((string) $taskPermissionRow['id']) ?>][enabled]"
                                                value="1"
                                                <?= !empty($taskPermissionRow['enabled']) ? 'checked' : '' ?>
                                                <?= !empty($taskPermissionRow['required']) ? 'disabled' : '' ?>
                                                data-permission-enabled-checkbox
                                            >
                                            <span><?= !empty($taskPermissionRow['required']) ? 'Obrigatorio' : 'Permitido' ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-mini btn-ghost" data-close-group-permissions-modal>Cancelar</button>
                        <button type="submit" class="btn btn-pill">Salvar permissoes</button>
                    </div>
                </form>
            </section>
        </div>
    <?php endforeach; ?>

    <?php foreach ($vaultGroups as $vaultGroupPermissionsName): ?>
        <?php
        $vaultGroupPermissionsModalKey = 'vault-group-perm-' . md5((string) $vaultGroupPermissionsName);
        $vaultPermissionsByUser = $vaultGroupPermissionsByUserMap[$vaultGroupPermissionsName] ?? [];
        ?>
        <div class="modal-backdrop" data-group-permissions-modal="<?= e($vaultGroupPermissionsModalKey) ?>" hidden>
            <div class="modal-scrim" data-close-group-permissions-modal></div>
            <section class="modal-card group-permissions-modal-card" role="dialog" aria-modal="true" aria-labelledby="vault-group-perm-title-<?= e(md5((string) $vaultGroupPermissionsName)) ?>">
                <header class="modal-head">
                    <h2 id="vault-group-perm-title-<?= e(md5((string) $vaultGroupPermissionsName)) ?>">Acesso do grupo do cofre: <?= e((string) $vaultGroupPermissionsName) ?></h2>
                    <button type="button" class="modal-close-button" data-close-group-permissions-modal aria-label="Fechar modal">
                        <span aria-hidden="true">&#10005;</span>
                    </button>
                </header>

                <form method="post" class="form-stack modal-form group-permissions-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_vault_group_permissions">
                    <input type="hidden" name="group_name" value="<?= e((string) $vaultGroupPermissionsName) ?>">

                    <?php
                    $vaultPermissionRows = [];
                    $vaultEnabledCount = 0;
                    foreach ($workspaceMembers as $workspaceMember) {
                        $memberId = (int) ($workspaceMember['id'] ?? 0);
                        if ($memberId <= 0) {
                            continue;
                        }
                        $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
                        if ($memberRole === 'admin') {
                            continue;
                        }
                        $memberPermission = $vaultPermissionsByUser[$memberId] ?? [];
                        $memberEnabled = (bool) ($memberPermission['can_view'] ?? true)
                            && (bool) ($memberPermission['can_access'] ?? true);
                        if ($memberEnabled) {
                            $vaultEnabledCount++;
                        }
                        $vaultPermissionRows[] = [
                            'id' => $memberId,
                            'name' => (string) ($workspaceMember['name'] ?? 'Usuario'),
                            'email' => (string) ($workspaceMember['email'] ?? ''),
                            'enabled' => $memberEnabled,
                        ];
                    }
                    $vaultTotalCount = count($vaultPermissionRows);
                    $vaultAllEnabled = $vaultTotalCount > 0 && $vaultEnabledCount === $vaultTotalCount;
                    $vaultCounterLabel = $vaultEnabledCount . '/' . $vaultTotalCount;
                    ?>

                    <div class="group-permissions-scope" data-group-permissions-scope>
                        <label class="group-permissions-toggle group-permissions-toggle-master">
                            <input
                                type="checkbox"
                                data-permission-all-checkbox
                                <?= $vaultAllEnabled ? 'checked' : '' ?>
                                <?= $vaultTotalCount === 0 ? 'disabled' : '' ?>
                            >
                            <span>Aplicar a todos</span>
                        </label>
                        <span class="group-permissions-counter" data-permission-counter><?= e($vaultCounterLabel) ?> permitidos</span>
                    </div>

                    <details class="group-permissions-members" open>
                        <summary>
                            <span>Usuarios do workspace</span>
                            <span class="group-permissions-summary-count" data-permission-summary-count><?= e($vaultCounterLabel) ?></span>
                        </summary>
                        <div class="group-permissions-list">
                            <?php if (!$vaultPermissionRows): ?>
                                <p class="group-permissions-empty">Nenhum usuario disponivel para configurar.</p>
                            <?php else: ?>
                                <?php foreach ($vaultPermissionRows as $vaultPermissionRow): ?>
                                    <div class="group-permissions-row">
                                        <input type="hidden" name="member_ids[]" value="<?= e((string) $vaultPermissionRow['id']) ?>">
                                        <div class="group-permissions-user">
                                            <strong><?= e((string) $vaultPermissionRow['name']) ?></strong>
                                            <span><?= e((string) $vaultPermissionRow['email']) ?></span>
                                        </div>
                                        <label class="group-permissions-toggle">
                                            <input
                                                type="checkbox"
                                                name="permissions[<?= e((string) $vaultPermissionRow['id']) ?>][enabled]"
                                                value="1"
                                                <?= !empty($vaultPermissionRow['enabled']) ? 'checked' : '' ?>
                                                data-permission-enabled-checkbox
                                            >
                                            <span>Permitido</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-mini btn-ghost" data-close-group-permissions-modal>Cancelar</button>
                        <button type="submit" class="btn btn-pill">Salvar permissoes</button>
                    </div>
                </form>
            </section>
        </div>
    <?php endforeach; ?>

    <?php foreach ($dueGroups as $dueGroupPermissionsName): ?>
        <?php
        $dueGroupPermissionsModalKey = 'due-group-perm-' . md5((string) $dueGroupPermissionsName);
        $duePermissionsByUser = $dueGroupPermissionsByUserMap[$dueGroupPermissionsName] ?? [];
        ?>
        <div class="modal-backdrop" data-group-permissions-modal="<?= e($dueGroupPermissionsModalKey) ?>" hidden>
            <div class="modal-scrim" data-close-group-permissions-modal></div>
            <section class="modal-card group-permissions-modal-card" role="dialog" aria-modal="true" aria-labelledby="due-group-perm-title-<?= e(md5((string) $dueGroupPermissionsName)) ?>">
                <header class="modal-head">
                    <h2 id="due-group-perm-title-<?= e(md5((string) $dueGroupPermissionsName)) ?>">Acesso do grupo de vencimentos: <?= e((string) $dueGroupPermissionsName) ?></h2>
                    <button type="button" class="modal-close-button" data-close-group-permissions-modal aria-label="Fechar modal">
                        <span aria-hidden="true">&#10005;</span>
                    </button>
                </header>

                <form method="post" class="form-stack modal-form group-permissions-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update_due_group_permissions">
                    <input type="hidden" name="group_name" value="<?= e((string) $dueGroupPermissionsName) ?>">

                    <?php
                    $duePermissionRows = [];
                    $dueEnabledCount = 0;
                    foreach ($workspaceMembers as $workspaceMember) {
                        $memberId = (int) ($workspaceMember['id'] ?? 0);
                        if ($memberId <= 0) {
                            continue;
                        }
                        $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
                        if ($memberRole === 'admin') {
                            continue;
                        }
                        $memberPermission = $duePermissionsByUser[$memberId] ?? [];
                        $memberEnabled = (bool) ($memberPermission['can_view'] ?? true)
                            && (bool) ($memberPermission['can_access'] ?? true);
                        if ($memberEnabled) {
                            $dueEnabledCount++;
                        }
                        $duePermissionRows[] = [
                            'id' => $memberId,
                            'name' => (string) ($workspaceMember['name'] ?? 'Usuario'),
                            'email' => (string) ($workspaceMember['email'] ?? ''),
                            'enabled' => $memberEnabled,
                        ];
                    }
                    $dueTotalCount = count($duePermissionRows);
                    $dueAllEnabled = $dueTotalCount > 0 && $dueEnabledCount === $dueTotalCount;
                    $dueCounterLabel = $dueEnabledCount . '/' . $dueTotalCount;
                    ?>

                    <div class="group-permissions-scope" data-group-permissions-scope>
                        <label class="group-permissions-toggle group-permissions-toggle-master">
                            <input
                                type="checkbox"
                                data-permission-all-checkbox
                                <?= $dueAllEnabled ? 'checked' : '' ?>
                                <?= $dueTotalCount === 0 ? 'disabled' : '' ?>
                            >
                            <span>Aplicar a todos</span>
                        </label>
                        <span class="group-permissions-counter" data-permission-counter><?= e($dueCounterLabel) ?> permitidos</span>
                    </div>

                    <details class="group-permissions-members" open>
                        <summary>
                            <span>Usuarios do workspace</span>
                            <span class="group-permissions-summary-count" data-permission-summary-count><?= e($dueCounterLabel) ?></span>
                        </summary>
                        <div class="group-permissions-list">
                            <?php if (!$duePermissionRows): ?>
                                <p class="group-permissions-empty">Nenhum usuario disponivel para configurar.</p>
                            <?php else: ?>
                                <?php foreach ($duePermissionRows as $duePermissionRow): ?>
                                    <div class="group-permissions-row">
                                        <input type="hidden" name="member_ids[]" value="<?= e((string) $duePermissionRow['id']) ?>">
                                        <div class="group-permissions-user">
                                            <strong><?= e((string) $duePermissionRow['name']) ?></strong>
                                            <span><?= e((string) $duePermissionRow['email']) ?></span>
                                        </div>
                                        <label class="group-permissions-toggle">
                                            <input
                                                type="checkbox"
                                                name="permissions[<?= e((string) $duePermissionRow['id']) ?>][enabled]"
                                                value="1"
                                                <?= !empty($duePermissionRow['enabled']) ? 'checked' : '' ?>
                                                data-permission-enabled-checkbox
                                            >
                                            <span>Permitido</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-mini btn-ghost" data-close-group-permissions-modal>Cancelar</button>
                        <button type="submit" class="btn btn-pill">Salvar permissoes</button>
                    </div>
                </form>
            </section>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-backdrop" data-task-detail-modal hidden>
    <div class="modal-scrim" data-close-task-detail-modal></div>
    <section class="modal-card task-detail-modal" role="dialog" aria-modal="true" aria-labelledby="task-detail-modal-title">
        <header class="modal-head task-detail-modal-head">
            <div class="task-detail-modal-head-copy">
                <h2 id="task-detail-modal-title" data-task-detail-title>Tarefa</h2>
            </div>
            <div class="task-detail-modal-head-actions">
                <button type="button" class="btn btn-mini btn-danger" data-task-detail-delete>Remover</button>
                <button type="button" class="btn btn-mini btn-ghost" data-task-detail-edit>Editar</button>
                <button type="button" class="btn btn-mini btn-ghost" data-task-detail-request-revision hidden>Solicitar ajuste</button>
                <button type="button" class="btn btn-mini" data-task-detail-save hidden>Salvar</button>
                <button type="button" class="btn btn-mini btn-ghost" data-task-detail-cancel-edit hidden>Cancelar</button>
                <button type="button" class="modal-close-button" data-close-task-detail-modal aria-label="Fechar modal">
                    <span aria-hidden="true">&#10005;</span>
                </button>
            </div>
        </header>

        <div class="task-detail-modal-body">
            <section class="task-detail-view" data-task-detail-view>
                <div class="task-detail-view-layout">
                    <div class="task-detail-view-main">
                        <div class="task-detail-view-block">
                            <div class="task-detail-view-tags">
                                <span class="task-detail-view-tag task-title-tag-badge task-detail-title-tag" data-task-detail-view-title-tag hidden></span>
                                <span class="task-detail-view-tag" data-task-detail-view-status></span>
                                <span class="task-detail-view-tag" data-task-detail-view-priority></span>
                                <span class="task-detail-view-tag" data-task-detail-view-group></span>
                                <span class="task-detail-view-tag" data-task-detail-view-due></span>
                            </div>
                            <div class="task-detail-view-assignees" data-task-detail-view-assignees></div>
                        </div>

                        <div class="task-detail-view-block">
                            <div class="task-detail-view-label-row">
                                <div class="task-detail-view-label">Descricao</div>
                                <button
                                    type="button"
                                    class="task-detail-description-remove"
                                    data-task-detail-remove-revision
                                    hidden
                                    aria-label="Remover ultima solicitacao de ajuste"
                                    title="Remover ultima solicitacao de ajuste"
                                >
                                    <span aria-hidden="true">&#10005;</span>
                                </button>
                            </div>
                            <div class="task-detail-view-description" data-task-detail-view-description></div>
                            <div class="task-detail-description-versions" data-task-detail-view-description-versions hidden></div>
                        </div>

                        <div class="task-detail-view-block" data-task-detail-view-subtasks-wrap hidden>
                            <div class="task-detail-view-label">Etapas</div>
                            <div class="task-detail-subtasks-list" data-task-detail-view-subtasks></div>
                        </div>

                        <div class="task-detail-view-block" data-task-detail-view-references hidden>
                            <div class="task-detail-view-label">Links</div>

                            <div class="task-detail-ref-section" data-task-detail-view-images-wrap hidden>
                                <div class="task-detail-ref-title">Imagens</div>
                                <div class="task-detail-ref-images" data-task-detail-view-images></div>
                            </div>

                            <div class="task-detail-ref-section" data-task-detail-view-links-wrap hidden>
                                <div class="task-detail-ref-links" data-task-detail-view-links></div>
                            </div>
                        </div>

                        <div class="task-detail-view-meta">
                            <span data-task-detail-view-created-by></span>
                            <span data-task-detail-view-updated-at></span>
                        </div>
                    </div>

                    <aside class="task-detail-history-column">
                        <div class="task-detail-view-label">Historico</div>
                        <div class="task-detail-history-list" data-task-detail-view-history></div>
                    </aside>
                </div>
            </section>

            <section class="task-detail-edit" data-task-detail-edit-panel hidden>
                <div class="form-stack modal-form">
                    <label>
                        <span>Titulo</span>
                        <div class="create-task-title-composer" data-task-detail-edit-title-composer>
                            <div class="create-task-title-tag-picker" data-task-detail-edit-title-tag-picker>
                                <button
                                    type="button"
                                    class="create-task-title-tag-trigger is-empty"
                                    data-task-detail-edit-title-tag-trigger
                                    aria-haspopup="listbox"
                                    aria-expanded="false"
                                >tag</button>
                                <input
                                    type="text"
                                    maxlength="40"
                                    placeholder="Criar tag"
                                    autocomplete="off"
                                    data-task-detail-edit-title-tag-custom
                                    hidden
                                >
                                <div class="create-task-title-tag-menu" data-task-detail-edit-title-tag-menu hidden></div>
                            </div>
                            <input type="text" maxlength="140" required data-task-detail-edit-title>
                        </div>
                        <input type="hidden" name="title_tag" value="" data-task-detail-edit-title-tag-input>
                        <input type="hidden" name="title_tag_color" value="<?= e(taskTitleTagDefaultColor()) ?>" data-task-detail-edit-title-tag-color-input>
                    </label>

                    <div class="task-detail-inline-controls">
                        <div class="assignee-picker-wrap task-detail-inline-field task-detail-inline-assignees">
                            <span class="assignee-picker-label">Responsaveis</span>
                            <details class="assignee-picker task-detail-inline-assignee-picker" data-task-detail-edit-assignees>
                                <summary>Selecionar</summary>
                                <div class="assignee-picker-menu" data-task-detail-edit-assignees-menu></div>
                            </details>
                        </div>

                        <div class="task-detail-inline-field task-detail-inline-status">
                            <span>Status</span>
                            <div class="status-stepper task-detail-status-stepper" data-status-stepper>
                                <button
                                    type="button"
                                    class="status-stepper-btn"
                                    data-status-step="-1"
                                    aria-label="Status anterior"
                                >
                                    <span aria-hidden="true">&#8249;</span>
                                </button>

                                <div class="tag-field tag-field-status row-inline-picker-wrap" data-inline-select-wrap>
                                    <details
                                        class="row-inline-picker status-inline-picker status-<?= e($defaultTaskStatusKind) ?>"
                                        data-inline-select-picker
                                        data-status-color="<?= e($defaultTaskStatusColor) ?>"
                                        style="<?= e($defaultTaskStatusCssVars) ?>"
                                    >
                                        <summary aria-label="Status da tarefa">
                                            <span class="row-inline-picker-summary-text" data-inline-select-text><?= e($defaultTaskStatusLabel) ?></span>
                                        </summary>
                                        <div
                                            class="assignee-picker-menu row-inline-picker-menu"
                                            role="listbox"
                                            aria-label="Selecionar status"
                                        >
                                            <?php foreach ($statusOptions as $key => $label): ?>
                                                <?php
                                                $optionMeta = $statusMetaByKey[$key] ?? taskStatusMeta($key);
                                                $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                                $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                                $optionColor = normalizeTaskStatusColor(
                                                    (string) ($optionMeta['color'] ?? ''),
                                                    $optionKind
                                                );
                                                $optionCssVars = (string) ($optionMeta['css_vars'] ?? taskStatusCssVars($optionColor));
                                                ?>
                                                <button
                                                    type="button"
                                                    class="row-inline-picker-option status-<?= e($optionKind) ?><?= $key === $defaultTaskStatusKey ? ' is-active' : '' ?>"
                                                    data-inline-select-option
                                                    data-value="<?= e($key) ?>"
                                                    data-label="<?= e($label) ?>"
                                                    data-status-kind="<?= e($optionKind) ?>"
                                                    data-status-color="<?= e($optionColor) ?>"
                                                    data-status-order="<?= e((string) $optionOrder) ?>"
                                                    style="<?= e($optionCssVars) ?>"
                                                    role="option"
                                                    aria-selected="<?= $key === $defaultTaskStatusKey ? 'true' : 'false' ?>"
                                                ><?= e($label) ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                    <select
                                        class="tag-select status-select status-<?= e($defaultTaskStatusKind) ?> row-inline-picker-native"
                                        data-inline-select-source
                                        data-task-detail-edit-status
                                        data-status-color="<?= e($defaultTaskStatusColor) ?>"
                                        style="<?= e($defaultTaskStatusCssVars) ?>"
                                        aria-label="Status"
                                        hidden
                                    >
                                        <?php foreach ($statusOptions as $key => $label): ?>
                                            <?php
                                            $optionMeta = $statusMetaByKey[$key] ?? taskStatusMeta($key);
                                            $optionKind = (string) ($optionMeta['kind'] ?? 'in_progress');
                                            $optionOrder = (int) ($optionMeta['order'] ?? 1);
                                            $optionColor = normalizeTaskStatusColor(
                                                (string) ($optionMeta['color'] ?? ''),
                                                $optionKind
                                            );
                                            ?>
                                            <option
                                                value="<?= e($key) ?>"
                                                data-status-kind="<?= e($optionKind) ?>"
                                                data-status-color="<?= e($optionColor) ?>"
                                                data-status-order="<?= e((string) $optionOrder) ?>"
                                                <?= $key === $defaultTaskStatusKey ? ' selected' : '' ?>
                                            ><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button
                                    type="button"
                                    class="status-stepper-btn"
                                    data-status-step="1"
                                    aria-label="Proximo status"
                                >
                                    <span aria-hidden="true">&#8250;</span>
                                </button>
                            </div>
                        </div>

                        <div class="task-detail-inline-field task-detail-inline-priority">
                            <span>Prioridade</span>
                            <div
                                class="tag-field tag-field-priority row-inline-picker-wrap"
                                data-inline-select-wrap
                                data-inline-picker-kind="priority"
                            >
                                <details
                                    class="row-inline-picker priority-inline-picker priority-medium"
                                    data-inline-select-picker
                                >
                                    <summary aria-label="Prioridade da tarefa">
                                        <span class="row-inline-picker-summary-icon" aria-hidden="true">&#9873;</span>
                                        <span class="row-inline-picker-summary-text sr-only" data-inline-select-text>Media</span>
                                    </summary>
                                    <div
                                        class="assignee-picker-menu row-inline-picker-menu"
                                        role="listbox"
                                        aria-label="Selecionar prioridade"
                                    >
                                        <?php foreach ($priorityOptions as $key => $label): ?>
                                            <button
                                                type="button"
                                                class="row-inline-picker-option priority-<?= e($key) ?><?= $key === 'medium' ? ' is-active' : '' ?>"
                                                data-inline-select-option
                                                data-value="<?= e($key) ?>"
                                                data-label="<?= e($label) ?>"
                                                role="option"
                                                aria-selected="<?= $key === 'medium' ? 'true' : 'false' ?>"
                                            >
                                                <span class="row-inline-picker-option-flag" aria-hidden="true">&#9873;</span>
                                                <span><?= e($label) ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                                <select
                                    class="tag-select priority-select priority-medium row-inline-picker-native"
                                    data-inline-select-source
                                    data-task-detail-edit-priority
                                    aria-label="Prioridade"
                                    hidden
                                >
                                    <?php foreach ($priorityOptions as $key => $label): ?>
                                        <option value="<?= e($key) ?>"<?= $key === 'medium' ? ' selected' : '' ?>>&#9873;</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <label>
                            <span>Grupo</span>
                            <div
                                class="task-detail-group-inline-wrap row-inline-picker-wrap"
                                data-inline-select-wrap
                                data-inline-picker-kind="group"
                            >
                                <details class="row-inline-picker group-inline-picker" data-inline-select-picker>
                                    <summary aria-label="Grupo da tarefa">
                                        <span class="row-inline-picker-summary-text" data-inline-select-text>
                                            <?= $taskGroupsWithAccess ? e((string) $taskGroupsWithAccess[0]) : 'Sem grupo com acesso' ?>
                                        </span>
                                    </summary>
                                    <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Selecionar grupo">
                                        <?php if (!$taskGroupsWithAccess): ?>
                                            <button
                                                type="button"
                                                class="row-inline-picker-option"
                                                data-inline-select-option
                                                data-value=""
                                                data-label="Sem grupo com acesso"
                                                role="option"
                                                aria-selected="true"
                                                disabled
                                            >Sem grupo com acesso</button>
                                        <?php else: ?>
                                            <?php foreach ($taskGroupsWithAccess as $groupIndex => $groupNameOption): ?>
                                                <button
                                                    type="button"
                                                    class="row-inline-picker-option<?= $groupIndex === 0 ? ' is-active' : '' ?>"
                                                    data-inline-select-option
                                                    data-value="<?= e((string) $groupNameOption) ?>"
                                                    data-label="<?= e((string) $groupNameOption) ?>"
                                                    role="option"
                                                    aria-selected="<?= $groupIndex === 0 ? 'true' : 'false' ?>"
                                                ><?= e((string) $groupNameOption) ?></button>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </details>
                                <select
                                    class="group-tag-select row-inline-picker-native task-group-inline-select"
                                    data-inline-select-source
                                    data-inline-select-sync-options="group"
                                    data-task-detail-edit-group
                                    hidden
                                >
                                    <?php if (!$taskGroupsWithAccess): ?>
                                        <option value="">Sem grupo com acesso</option>
                                    <?php else: ?>
                                        <?php foreach ($taskGroupsWithAccess as $groupNameOption): ?>
                                            <option value="<?= e((string) $groupNameOption) ?>">
                                                <?= e((string) $groupNameOption) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </label>

                        <label>
                            <span>Prazo</span>
                            <input type="date" data-task-detail-edit-due-date>
                        </label>
                    </div>

                    <div class="task-detail-edit-main-row">
                        <div class="task-detail-edit-description-field">
                            <span>Descricao</span>
                            <div class="task-detail-edit-description-wrap" data-task-detail-edit-description-wrap>
                                <div class="task-detail-edit-description-toolbar" data-task-detail-edit-description-toolbar>
                                    <button
                                        type="button"
                                        data-task-detail-description-format="bold"
                                        title="Negrito"
                                        aria-label="Negrito"
                                    ><strong aria-hidden="true">B</strong></button>
                                    <button
                                        type="button"
                                        data-task-detail-description-format="italic"
                                        title="Italico"
                                        aria-label="Italico"
                                    ><em aria-hidden="true">I</em></button>
                                    <button
                                        type="button"
                                        data-task-detail-description-action="divider"
                                        title="Inserir separador"
                                        aria-label="Inserir separador"
                                    >
                                        <span class="task-description-toolbar-divider-icon" aria-hidden="true"></span>
                                    </button>
                                </div>
                                <div
                                    class="task-detail-edit-description-editor"
                                    data-task-detail-edit-description-editor
                                    contenteditable="true"
                                    role="textbox"
                                    aria-multiline="true"
                                    aria-label="Descricao da tarefa"
                                ></div>
                            </div>
                            <textarea rows="5" data-task-detail-edit-description hidden></textarea>
                        </div>

                        <div class="task-detail-edit-images-field">
                            <span>Imagens de referencia</span>
                            <div class="task-detail-edit-image-picker-actions">
                                <button type="button" class="btn btn-mini btn-ghost task-image-add-button" data-task-detail-image-add>
                                    <span class="task-image-add-button-icon" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" focusable="false">
                                            <rect x="2.5" y="4" width="15" height="12" rx="2.2"></rect>
                                            <circle cx="7.2" cy="8.1" r="1.4"></circle>
                                            <path d="M3.8 14.8 8.3 10.2l3 2.7 2.1-1.8 2.8 3.7"></path>
                                        </svg>
                                    </span>
                                    <span class="task-image-add-button-label">Adicionar imagem</span>
                                </button>
                            </div>
                            <div class="task-detail-edit-image-picker" data-task-detail-image-picker tabindex="0" aria-label="Adicionar imagens de referencia">
                                <input type="file" accept="image/*" multiple data-task-detail-image-input hidden>
                                <div class="task-detail-edit-image-list" data-task-detail-image-list></div>
                            </div>
                            <textarea rows="1" data-task-detail-edit-images hidden></textarea>
                        </div>
                    </div>

                    <div class="task-subtasks-editor">
                        <div class="task-subtasks-editor-head">
                            <div class="task-subtasks-editor-title">
                                <span>Etapas</span>
                                <label class="task-subtasks-dependency-toggle" title="Ativar sequência entre etapas">
                                    <input
                                        type="checkbox"
                                        aria-label="Ativar sequência entre etapas"
                                        data-task-detail-edit-subtasks-dependency-toggle
                                    >
                                    <span class="task-subtasks-dependency-icon" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" focusable="false">
                                            <rect x="5.2" y="2.2" width="9.6" height="3.1" rx="1.1"></rect>
                                            <rect x="5.2" y="8.5" width="9.6" height="3.1" rx="1.1"></rect>
                                            <rect x="5.2" y="14.7" width="9.6" height="3.1" rx="1.1"></rect>
                                            <path d="M10 5.8v1.7"></path>
                                            <path d="M10 12.1v1.7"></path>
                                            <path d="M8.9 6.5 10 7.7l1.1-1.2"></path>
                                            <path d="M8.9 12.8 10 14l1.1-1.2"></path>
                                        </svg>
                                    </span>
                                    <span class="sr-only">Ativar sequência entre etapas</span>
                                </label>
                            </div>
                        </div>
                        <div class="task-subtasks-edit-add">
                            <input
                                type="text"
                                maxlength="120"
                                placeholder="Nova etapa"
                                data-task-detail-edit-subtask-input
                            >
                            <button type="button" class="btn btn-mini btn-ghost" data-task-detail-edit-subtask-add>Adicionar etapa</button>
                        </div>
                        <div class="task-subtasks-edit-list" data-task-detail-edit-subtasks-list></div>
                        <textarea rows="1" data-task-detail-edit-subtasks hidden></textarea>
                        <input type="hidden" value="0" data-task-detail-edit-subtasks-dependency>
                    </div>

                    <label class="task-detail-edit-links-field">
                        <span>Links de referencia</span>
                        <textarea
                            rows="1"
                            class="task-detail-reference-input"
                            data-task-detail-edit-links
                        ></textarea>
                    </label>
                </div>
            </section>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-task-review-modal hidden>
    <div class="modal-scrim" data-close-task-review-modal></div>
    <section class="modal-card review-task-modal" role="dialog" aria-modal="true" aria-labelledby="task-review-modal-title">
        <header class="modal-head">
            <h2 id="task-review-modal-title">Solicitar ajuste</h2>
            <button type="button" class="modal-close-button" data-close-task-review-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-task-review-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="request_task_revision">
            <input type="hidden" name="task_id" value="" data-task-review-task-id>

            <label>
                <span>Nova descricao para ajustes</span>
                <textarea name="revision_description" rows="6" maxlength="8000" required data-task-review-description></textarea>
            </label>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-task-review-modal>Cancelar</button>
                <button type="submit" class="btn btn-mini" data-task-review-submit>Salvar ajuste</button>
            </div>
        </form>
    </section>
</div>

<form method="post" data-task-remove-revision-form hidden>
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="action" value="remove_task_revision">
    <input type="hidden" name="task_id" value="" data-task-remove-revision-task-id>
</form>

<div class="modal-backdrop task-image-preview-modal" data-task-image-preview-modal hidden>
    <div class="modal-scrim" data-close-task-image-preview></div>
    <section class="modal-card task-image-preview-card" role="dialog" aria-modal="true" aria-label="Imagem de referencia">
        <header class="modal-head task-image-preview-head">
            <button type="button" class="modal-close-button" data-close-task-image-preview aria-label="Fechar visualizacao da imagem">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>
        <div class="task-image-preview-body">
            <div class="task-image-preview-nav-slot">
                <button
                    type="button"
                    class="task-image-preview-nav task-image-preview-nav-prev"
                    data-task-image-preview-prev
                    aria-label="Imagem anterior"
                    hidden
                >
                    <span aria-hidden="true">&#8249;</span>
                </button>
            </div>
            <div class="task-image-preview-viewport">
                <img src="" alt="Imagem de referencia ampliada" data-task-image-preview-img>
            </div>
            <div class="task-image-preview-nav-slot">
                <button
                    type="button"
                    class="task-image-preview-nav task-image-preview-nav-next"
                    data-task-image-preview-next
                    aria-label="Proxima imagem"
                    hidden
                >
                    <span aria-hidden="true">&#8250;</span>
                </button>
            </div>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-confirm-modal hidden>
    <div class="modal-scrim" data-close-confirm-modal></div>
    <section class="modal-card confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
        <header class="modal-head">
            <h2 id="confirm-modal-title">Confirmar</h2>
            <button type="button" class="modal-close-button" data-close-confirm-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <div class="confirm-modal-body">
            <p data-confirm-modal-message>Tem certeza?</p>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn btn-mini btn-ghost" data-close-confirm-modal>Cancelar</button>
            <button type="button" class="btn btn-mini btn-danger" data-confirm-modal-submit>Confirmar</button>
        </div>
    </section>
</div>
