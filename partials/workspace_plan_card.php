<?php
$workspacePlanCardDefinitions = billingPlanDefinitions();
$workspacePlanCardWorkspaceId = (int) ($currentWorkspaceId ?? 0);
$workspacePlanCardBillingLimit = $workspacePlanCardWorkspaceId > 0
    ? workspaceBillingLimit($workspacePlanCardWorkspaceId)
    : [];
$workspacePlanCardWorkspacePlanKey = normalizeBillingPlanKey((string) ($workspacePlanCardBillingLimit['plan_key'] ?? ''), null);
$workspacePlanCardUserId = (int) ($currentUser['id'] ?? 0);
$workspacePlanCardUserSubscription = $workspacePlanCardUserId > 0 ? userSubscriptionByUserId($workspacePlanCardUserId) : null;
$workspacePlanCardUserPlanKey = is_array($workspacePlanCardUserSubscription) ? billingSubscriptionPlanKey($workspacePlanCardUserSubscription) : '';
$workspacePlanCardCurrentPlanKey = $workspacePlanCardWorkspacePlanKey !== ''
    ? $workspacePlanCardWorkspacePlanKey
    : ($workspacePlanCardUserPlanKey !== '' ? $workspacePlanCardUserPlanKey : 'free');
$workspacePlanCardCurrentPlan = is_array($workspacePlanCardDefinitions[$workspacePlanCardCurrentPlanKey] ?? null)
    ? $workspacePlanCardDefinitions[$workspacePlanCardCurrentPlanKey]
    : (is_array($workspacePlanCardDefinitions['free'] ?? null) ? $workspacePlanCardDefinitions['free'] : ['key' => 'free', 'name' => 'Free']);
$workspacePlanCardName = trim((string) ($workspacePlanCardCurrentPlan['name'] ?? 'Plano'));
$workspacePlanCardBadge = trim((string) ($workspacePlanCardCurrentPlan['badge'] ?? ''));
$workspacePlanCardSummary = trim((string) ($workspacePlanCardCurrentPlan['summary'] ?? ''));
$workspacePlanCardMemberLimit = max(0, (int) ($workspacePlanCardBillingLimit['max_users'] ?? ($workspacePlanCardCurrentPlan['max_users'] ?? 0)));
$workspacePlanCardMemberCount = max(0, (int) ($workspacePlanCardBillingLimit['member_count'] ?? 0));
$workspacePlanCardIsPersonalWorkspace = (bool) ($workspacePlanCardIsPersonalWorkspace ?? !empty($isPersonalWorkspace));
$workspacePlanCardCapacityLabel = '';
if ($workspacePlanCardMemberLimit > 0) {
    $workspacePlanCardCapacityLabel = $workspacePlanCardMemberLimit === 1
        ? '1 usuário'
        : sprintf('Até %d usuários', $workspacePlanCardMemberLimit);
} else {
    $workspacePlanCardCapacityLabel = trim((string) ($workspacePlanCardCurrentPlan['users_label'] ?? ''));
}
$workspacePlanCardUsageLabel = '';
if ($workspacePlanCardMemberLimit > 0 && $workspacePlanCardWorkspacePlanKey !== '' && !$workspacePlanCardIsPersonalWorkspace) {
    $workspacePlanCardUsageLabel = sprintf(
        '%d/%d em uso',
        min($workspacePlanCardMemberCount, $workspacePlanCardMemberLimit),
        $workspacePlanCardMemberLimit
    );
}
$workspacePlanCardCompactMetaLabel = '';
if ($workspacePlanCardMemberLimit > 0 && $workspacePlanCardWorkspacePlanKey !== '' && !$workspacePlanCardIsPersonalWorkspace) {
    $workspacePlanCardCompactMetaLabel = sprintf(
        '%d/%d usuários',
        min($workspacePlanCardMemberCount, $workspacePlanCardMemberLimit),
        $workspacePlanCardMemberLimit
    );
}
if ($workspacePlanCardCompactMetaLabel === '') {
    $workspacePlanCardCompactMetaLabel = $workspacePlanCardCapacityLabel;
}
$workspacePlanCardUpgradeMap = [
    'free' => 'solo',
    'solo' => 'team',
    'team' => 'business',
];
$workspacePlanCardUpgradePlanKey = $workspacePlanCardUpgradeMap[$workspacePlanCardCurrentPlanKey] ?? '';
$workspacePlanCardUpgradePlan = $workspacePlanCardUpgradePlanKey !== '' && is_array($workspacePlanCardDefinitions[$workspacePlanCardUpgradePlanKey] ?? null)
    ? $workspacePlanCardDefinitions[$workspacePlanCardUpgradePlanKey]
    : null;
$workspacePlanCardUpgradeName = trim((string) ($workspacePlanCardUpgradePlan['name'] ?? ''));
$workspacePlanCardContextLabel = trim((string) ($workspacePlanCardContextLabel ?? 'Plano atual'));
$workspacePlanCardToneClass = preg_replace('/[^a-z0-9_-]+/i', '', $workspacePlanCardCurrentPlanKey) ?: 'free';
$workspacePlanCardExtraClass = trim((string) ($workspacePlanCardExtraClass ?? ''));
$workspacePlanCardHeading = trim((string) ($workspacePlanCardHeading ?? ''));
$workspacePlanCardAriaLabel = trim((string) ($workspacePlanCardAriaLabel ?? 'Plano ativo'));
$workspacePlanCardClasses = trim(sprintf(
    'workspace-sidebar-plan-card workspace-sidebar-plan-card--%s %s',
    $workspacePlanCardToneClass,
    $workspacePlanCardExtraClass
));
?>

<section class="<?= e($workspacePlanCardClasses) ?>" aria-label="<?= e($workspacePlanCardAriaLabel) ?>">
    <?php if ($workspacePlanCardHeading !== ''): ?>
        <h3 class="workspace-sidebar-plan-heading"><?= e($workspacePlanCardHeading) ?></h3>
    <?php endif; ?>

    <div class="workspace-sidebar-plan-top">
        <div class="workspace-sidebar-plan-copy">
            <span class="workspace-sidebar-plan-eyebrow"><?= e($workspacePlanCardContextLabel) ?></span>
            <strong class="workspace-sidebar-plan-name"><?= e($workspacePlanCardName) ?></strong>
            <?php if ($workspacePlanCardCompactMetaLabel !== ''): ?>
                <span class="workspace-sidebar-plan-compact-meta"><?= e($workspacePlanCardCompactMetaLabel) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($workspacePlanCardBadge !== ''): ?>
            <span class="workspace-sidebar-plan-badge"><?= e($workspacePlanCardBadge) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($workspacePlanCardSummary !== ''): ?>
        <p class="workspace-sidebar-plan-summary"><?= e($workspacePlanCardSummary) ?></p>
    <?php endif; ?>

    <?php if ($workspacePlanCardCapacityLabel !== '' || $workspacePlanCardUsageLabel !== ''): ?>
        <div class="workspace-sidebar-plan-meta">
            <?php if ($workspacePlanCardCapacityLabel !== ''): ?>
                <span class="workspace-sidebar-plan-chip"><?= e($workspacePlanCardCapacityLabel) ?></span>
            <?php endif; ?>
            <?php if ($workspacePlanCardUsageLabel !== ''): ?>
                <span class="workspace-sidebar-plan-chip is-usage"><?= e($workspacePlanCardUsageLabel) ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($workspacePlanCardUpgradeName !== ''): ?>
        <div class="workspace-sidebar-plan-upgrade">
            <a href="<?= e(siteUrl('home#planos')) ?>" class="workspace-sidebar-plan-button" target="_blank" rel="noopener">
                Faça upgrade
            </a>
        </div>
    <?php endif; ?>
</section>
