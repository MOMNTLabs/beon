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
