<?php
$workspaceSummary = is_array($currentWorkspace ?? null) ? $currentWorkspace : [];
$workspaceSummaryName = (string) ($workspaceSummary['name'] ?? 'Workspace');
?>
<span class="workspace-sidebar-picker-summary-main">
    <?= renderWorkspaceAvatar($workspaceSummary, 'avatar small workspace-sidebar-picker-avatar', true, 'span') ?>
    <span class="workspace-sidebar-picker-title"><?= e($workspaceSummaryName) ?></span>
</span>
