<?php
$workspaceTaskStatusConfig = is_array($workspaceTaskStatusConfig ?? null)
    ? $workspaceTaskStatusConfig
    : taskStatusConfig((int) ($currentWorkspaceId ?? 0), $currentWorkspace ?? null);
$workspaceTaskStatuses = is_array($workspaceTaskStatusConfig['list'] ?? null)
    ? $workspaceTaskStatusConfig['list']
    : [];
$workspaceReviewStatusKey = $workspaceTaskStatusConfig['review_status_key'] ?? null;
$canManageTaskStatuses = !empty($canManageWorkspace);
$workspaceStatusesFormId = 'workspace-task-statuses-form-' . (int) ($currentWorkspaceId ?? 0);
$newWorkspaceStatusColor = taskStatusDefaultColorForKind('in_progress');
?>
<section class="workspace-settings-card workspace-statuses-card<?= $canManageTaskStatuses ? '' : ' is-readonly' ?>">
    <div class="workspace-statuses-card-head">
        <div>
            <h3>Status</h3>
        </div>
    </div>

    <form method="post" class="workspace-settings-form workspace-statuses-form" id="<?= e($workspaceStatusesFormId) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="workspace_update_task_statuses">
        <input
            type="hidden"
            name="task_review_status_key"
            value="<?= e((string) ($workspaceReviewStatusKey ?? '')) ?>"
            data-workspace-status-review-value
        >

        <div class="workspace-statuses-list">
            <?php foreach ($workspaceTaskStatuses as $statusDefinition): ?>
                <?php
                $statusKey = (string) ($statusDefinition['key'] ?? '');
                $statusLabel = (string) ($statusDefinition['label'] ?? '');
                $statusKind = (string) ($statusDefinition['kind'] ?? 'in_progress');
                $statusColor = normalizeTaskStatusColor(
                    (string) ($statusDefinition['color'] ?? ''),
                    $statusKind
                );
                $statusCssVars = (string) ($statusDefinition['css_vars'] ?? taskStatusCssVars($statusColor));
                $statusLocked = !empty($statusDefinition['is_locked']);
                $canMarkAsReview = !$statusLocked;
                ?>
                <div
                    class="workspace-status-row status-<?= e($statusKind) ?>"
                    data-workspace-status-row
                    data-status-color="<?= e($statusColor) ?>"
                    style="<?= e($statusCssVars) ?>"
                >
                    <input type="hidden" name="status_keys[]" value="<?= e($statusKey) ?>">
                    <input type="hidden" name="status_colors[]" value="<?= e($statusColor) ?>" data-workspace-status-color-hidden>
                    <span class="workspace-status-tone workspace-status-tone-<?= e($statusKind) ?>" aria-hidden="true"></span>
                    <input
                        type="text"
                        name="status_labels[]"
                        value="<?= e($statusLabel) ?>"
                        maxlength="40"
                        aria-label="Nome do status"
                        <?= $canManageTaskStatuses ? '' : 'readonly' ?>
                    >
                    <label class="workspace-status-color-control" title="Cor do status">
                        <input
                            type="color"
                            value="<?= e($statusColor) ?>"
                            aria-label="Cor do status"
                            data-workspace-status-color-input
                            <?= $canManageTaskStatuses ? '' : 'disabled' ?>
                        >
                    </label>
                    <button
                        type="button"
                        class="workspace-status-review-toggle<?= $canMarkAsReview ? '' : ' is-disabled' ?><?= $workspaceReviewStatusKey === $statusKey ? ' is-active' : '' ?>"
                        title="Definir como status de revisão"
                        data-workspace-status-review-toggle
                        data-status-key="<?= e($statusKey) ?>"
                        aria-pressed="<?= $workspaceReviewStatusKey === $statusKey ? 'true' : 'false' ?>"
                        <?= $canManageTaskStatuses && $canMarkAsReview ? '' : 'disabled' ?>
                    >
                        <span class="workspace-status-review-icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" focusable="false">
                                <path d="M3.6 10c1.7-2.6 3.9-3.9 6.4-3.9 2.5 0 4.7 1.3 6.4 3.9-1.7 2.6-3.9 3.9-6.4 3.9-2.5 0-4.7-1.3-6.4-3.9Z"></path>
                                <circle cx="10" cy="10" r="2.1"></circle>
                            </svg>
                        </span>
                        <span class="sr-only">Definir como status de revisão</span>
                    </button>
                    <?php if ($canManageTaskStatuses && !$statusLocked): ?>
                        <button
                            type="submit"
                            class="workspace-status-row-action"
                            name="remove_status_key"
                            value="<?= e($statusKey) ?>"
                            title="Remover status"
                        >
                            <span aria-hidden="true">&times;</span>
                            <span class="sr-only">Remover status</span>
                        </button>
                    <?php else: ?>
                        <span class="workspace-status-row-action workspace-status-row-action-placeholder" aria-hidden="true"></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($canManageTaskStatuses): ?>
            <div class="workspace-statuses-footer">
                <input
                    type="text"
                    name="new_status_label"
                    maxlength="40"
                    placeholder="Novo status"
                    aria-label="Novo status"
                >
                <label class="workspace-status-color-control workspace-status-color-control-new" title="Cor do novo status">
                    <input
                        type="color"
                        name="new_status_color"
                        value="<?= e($newWorkspaceStatusColor) ?>"
                        aria-label="Cor do novo status"
                    >
                </label>
                <button type="submit" class="btn btn-mini btn-ghost workspace-status-add-button" title="Adicionar status">
                    <span aria-hidden="true">+</span>
                    <span class="sr-only">Adicionar status</span>
                </button>
                <button type="submit" class="btn btn-mini">Salvar status</button>
            </div>
        <?php endif; ?>
    </form>
</section>
