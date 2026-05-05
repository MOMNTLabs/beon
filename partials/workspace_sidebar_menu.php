<?php
$workspaceSidebarConfig = is_array($workspaceSidebarConfig ?? null)
    ? $workspaceSidebarConfig
    : workspaceSidebarToolsConfig($currentWorkspaceId ?? null, $currentWorkspace ?? null);
$enabledSidebarTools = is_array($workspaceSidebarConfig['enabled'] ?? null)
    ? $workspaceSidebarConfig['enabled']
    : ['tasks'];
$availableSidebarTools = is_array($workspaceSidebarConfig['available_to_add'] ?? null)
    ? $workspaceSidebarConfig['available_to_add']
    : [];
$sidebarOptionalToolLabels = is_array($workspaceSidebarConfig['optional_labels'] ?? null)
    ? $workspaceSidebarConfig['optional_labels']
    : workspaceSidebarOptionalToolLabels();
$currentSidebarView = normalizeDashboardViewKey((string) ($_GET['view'] ?? ''));
$sidebarToolAddRedirectPath = dashboardPath($currentSidebarView !== '' ? $currentSidebarView : 'overview');
$sidebarPlanDefinitions = billingPlanDefinitions();
$sidebarWorkspaceBillingLimit = !empty($currentWorkspaceId)
    ? workspaceBillingLimit((int) $currentWorkspaceId)
    : [];
$sidebarWorkspacePlanKey = normalizeBillingPlanKey((string) ($sidebarWorkspaceBillingLimit['plan_key'] ?? ''), null);
$currentSidebarUserId = (int) ($currentUser['id'] ?? 0);
$sidebarUserSubscription = $currentSidebarUserId > 0 ? userSubscriptionByUserId($currentSidebarUserId) : null;
$sidebarUserPlanKey = is_array($sidebarUserSubscription) ? billingSubscriptionPlanKey($sidebarUserSubscription) : '';
$sidebarCurrentPlanKey = $sidebarWorkspacePlanKey !== ''
    ? $sidebarWorkspacePlanKey
    : ($sidebarUserPlanKey !== '' ? $sidebarUserPlanKey : 'free');
$sidebarCurrentPlan = is_array($sidebarPlanDefinitions[$sidebarCurrentPlanKey] ?? null)
    ? $sidebarPlanDefinitions[$sidebarCurrentPlanKey]
    : (is_array($sidebarPlanDefinitions['free'] ?? null) ? $sidebarPlanDefinitions['free'] : ['key' => 'free', 'name' => 'Free']);
$sidebarPlanName = trim((string) ($sidebarCurrentPlan['name'] ?? 'Plano'));
$sidebarPlanBadge = trim((string) ($sidebarCurrentPlan['badge'] ?? ''));
$sidebarPlanSummary = trim((string) ($sidebarCurrentPlan['summary'] ?? ''));
$sidebarPlanMemberLimit = max(0, (int) ($sidebarWorkspaceBillingLimit['max_users'] ?? ($sidebarCurrentPlan['max_users'] ?? 0)));
$sidebarPlanMemberCount = max(0, (int) ($sidebarWorkspaceBillingLimit['member_count'] ?? 0));
$sidebarPlanCapacityLabel = '';
if ($sidebarPlanMemberLimit > 0) {
    $sidebarPlanCapacityLabel = $sidebarPlanMemberLimit === 1
        ? '1 usuario'
        : sprintf('Ate %d usuarios', $sidebarPlanMemberLimit);
} else {
    $sidebarPlanCapacityLabel = trim((string) ($sidebarCurrentPlan['users_label'] ?? ''));
}
$sidebarPlanUsageLabel = '';
if ($sidebarPlanMemberLimit > 0 && $sidebarWorkspacePlanKey !== '' && empty($isPersonalWorkspace)) {
    $sidebarPlanUsageLabel = sprintf(
        '%d/%d em uso',
        min($sidebarPlanMemberCount, $sidebarPlanMemberLimit),
        $sidebarPlanMemberLimit
    );
}
$sidebarUpgradeMap = [
    'free' => 'solo',
    'solo' => 'team',
    'team' => 'business',
];
$sidebarUpgradePlanKey = $sidebarUpgradeMap[$sidebarCurrentPlanKey] ?? '';
$sidebarUpgradePlan = $sidebarUpgradePlanKey !== '' && is_array($sidebarPlanDefinitions[$sidebarUpgradePlanKey] ?? null)
    ? $sidebarPlanDefinitions[$sidebarUpgradePlanKey]
    : null;
$sidebarUpgradeName = trim((string) ($sidebarUpgradePlan['name'] ?? ''));
$sidebarPlanContextLabel = 'Plano atual';
$sidebarPlanToneClass = preg_replace('/[^a-z0-9_-]+/i', '', $sidebarCurrentPlanKey) ?: 'free';
?>

<nav class="sidebar-view-menu" id="workspace-sidebar-menu" aria-label="Menu do workspace">
    <?php foreach ($enabledSidebarTools as $sidebarToolView): ?>
        <?php if ($sidebarToolView === 'tasks'): ?>
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
        <?php elseif ($sidebarToolView === 'vault'): ?>
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
        <?php elseif ($sidebarToolView === 'inventory'): ?>
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
        <?php elseif ($sidebarToolView === 'accounting'): ?>
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
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<?php if (!empty($canManageWorkspace)): ?>
    <details class="workspace-sidebar-tool-adder">
        <summary
            class="workspace-sidebar-tool-adder-trigger"
            aria-label="Adicionar ferramenta ao sidebar"
            title="Adicionar ferramenta"
        >
            <span aria-hidden="true">+</span>
        </summary>
        <div class="workspace-sidebar-tool-adder-menu">
            <?php if ($availableSidebarTools === []): ?>
                <p class="workspace-sidebar-tool-adder-empty">Todas as ferramentas já foram adicionadas.</p>
            <?php else: ?>
                <?php foreach ($availableSidebarTools as $sidebarToolKey): ?>
                    <?php $toolLabel = (string) ($sidebarOptionalToolLabels[$sidebarToolKey] ?? $sidebarToolKey); ?>
                    <form method="post" class="workspace-sidebar-tool-adder-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="action" value="workspace_add_sidebar_tool">
                        <input type="hidden" name="sidebar_tool" value="<?= e((string) $sidebarToolKey) ?>">
                        <input type="hidden" name="redirect_to" value="<?= e($sidebarToolAddRedirectPath) ?>">
                        <button type="submit" class="workspace-sidebar-tool-adder-option"><?= e($toolLabel) ?></button>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </details>
<?php endif; ?>

<section class="workspace-sidebar-plan-card workspace-sidebar-plan-card--<?= e($sidebarPlanToneClass) ?>" aria-label="Plano ativo">
    <div class="workspace-sidebar-plan-top">
        <div class="workspace-sidebar-plan-copy">
            <span class="workspace-sidebar-plan-eyebrow"><?= e($sidebarPlanContextLabel) ?></span>
            <strong class="workspace-sidebar-plan-name"><?= e($sidebarPlanName) ?></strong>
        </div>
        <?php if ($sidebarPlanBadge !== ''): ?>
            <span class="workspace-sidebar-plan-badge"><?= e($sidebarPlanBadge) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($sidebarPlanSummary !== ''): ?>
        <p class="workspace-sidebar-plan-summary"><?= e($sidebarPlanSummary) ?></p>
    <?php endif; ?>

    <?php if ($sidebarPlanCapacityLabel !== '' || $sidebarPlanUsageLabel !== ''): ?>
        <div class="workspace-sidebar-plan-meta">
            <?php if ($sidebarPlanCapacityLabel !== ''): ?>
                <span class="workspace-sidebar-plan-chip"><?= e($sidebarPlanCapacityLabel) ?></span>
            <?php endif; ?>
            <?php if ($sidebarPlanUsageLabel !== ''): ?>
                <span class="workspace-sidebar-plan-chip is-usage"><?= e($sidebarPlanUsageLabel) ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($sidebarUpgradeName !== ''): ?>
        <div class="workspace-sidebar-plan-upgrade">
            <a href="<?= e(siteUrl('home#planos')) ?>" class="workspace-sidebar-plan-button" target="_blank" rel="noopener">
                Faça upgrade
            </a>
        </div>
    <?php endif; ?>
</section>
