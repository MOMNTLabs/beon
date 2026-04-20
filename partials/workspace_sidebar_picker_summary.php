<?php
$workspaceSummary = is_array($currentWorkspace ?? null) ? $currentWorkspace : [];
$workspaceSummaryName = (string) ($workspaceSummary['name'] ?? 'Workspace');
?>
<span class="workspace-sidebar-picker-summary-main">
    <?= renderWorkspaceAvatar($workspaceSummary, 'avatar small workspace-sidebar-picker-avatar', true, 'span') ?>
    <span class="workspace-sidebar-picker-title" title="<?= e($workspaceSummaryName) ?>"><?= e($workspaceSummaryName) ?></span>
</span>
