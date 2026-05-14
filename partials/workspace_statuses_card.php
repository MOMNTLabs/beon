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
$workspaceStatusColorPalette = taskStatusColorPalette();
$newWorkspaceStatusColor = normalizeTaskStatusPaletteColor(taskStatusDefaultColorForKind('in_progress'), 'in_progress');
$newWorkspaceStatusColorLabel = (string) ($workspaceStatusColorPalette[$newWorkspaceStatusColor] ?? $newWorkspaceStatusColor);
$workspaceStatusesBadgeLabel = count($workspaceTaskStatuses) === 1 ? 'etapa' : 'etapas';
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

        <div class="workspace-statuses-list" data-workspace-status-list>
            <?php foreach ($workspaceTaskStatuses as $statusDefinition): ?>
                <?php
                $statusKey = (string) ($statusDefinition['key'] ?? '');
                $statusLabel = (string) ($statusDefinition['label'] ?? '');
                $statusKind = (string) ($statusDefinition['kind'] ?? 'in_progress');
                $statusColor = normalizeTaskStatusPaletteColor(
                    (string) ($statusDefinition['color'] ?? ''),
                    $statusKind
                );
                $statusCssVars = (string) ($statusDefinition['css_vars'] ?? taskStatusCssVars($statusColor));
                $statusColorLabel = (string) ($workspaceStatusColorPalette[$statusColor] ?? $statusColor);
                $statusLocked = !empty($statusDefinition['is_locked']);
                $statusEdge = $statusLocked
                    ? ($statusKind === 'todo' ? 'start' : 'end')
                    : '';
                $canReorderStatus = $canManageTaskStatuses && !$statusLocked;
                $canMarkAsReview = !$statusLocked;
                ?>
                <div
                    class="workspace-status-row status-<?= e($statusKind) ?>"
                    data-workspace-status-row
                    data-workspace-status-sortable="<?= $canReorderStatus ? 'true' : 'false' ?>"
                    data-workspace-status-edge="<?= e($statusEdge) ?>"
                    data-status-color="<?= e($statusColor) ?>"
                    style="<?= e($statusCssVars) ?>"
                >
                    <input type="hidden" name="status_keys[]" value="<?= e($statusKey) ?>">
                    <input
                        type="hidden"
                        name="status_colors[]"
                        value="<?= e($statusColor) ?>"
                        data-workspace-status-color-hidden
                        data-workspace-status-color-input
                        data-workspace-status-color-kind="<?= e($statusKind) ?>"
                    >
                    <span
                        class="workspace-status-tone workspace-status-tone-<?= e($statusKind) ?>"
                        aria-hidden="true"
                        data-workspace-status-tone
                    ></span>
                    <input
                        type="text"
                        name="status_labels[]"
                        value="<?= e($statusLabel) ?>"
                        maxlength="40"
                        aria-label="Nome do status"
                        <?= $canManageTaskStatuses ? '' : 'readonly' ?>
                    >
                    <div
                        class="workspace-status-color-control<?= $canManageTaskStatuses ? '' : ' is-disabled' ?>"
                        title="Cor do status"
                        data-workspace-status-color-control
                        style="--workspace-status-selected-color: <?= e($statusColor) ?>;"
                    >
                        <button
                            type="button"
                            class="workspace-status-color-trigger"
                            data-workspace-status-color-trigger
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-label="Cor do status"
                            <?= $canManageTaskStatuses ? '' : 'disabled' ?>
                        >
                            <span
                                class="workspace-status-color-trigger-swatch"
                                aria-hidden="true"
                                data-workspace-status-color-current-swatch
                                style="--workspace-status-option-color: <?= e($statusColor) ?>;"
                            ></span>
                            <span class="workspace-status-color-trigger-label" data-workspace-status-color-current-label>
                                <?= e($statusColorLabel) ?>
                            </span>
                        </button>
                        <div class="workspace-status-color-menu" data-workspace-status-color-menu role="listbox" hidden>
                            <?php foreach ($workspaceStatusColorPalette as $paletteColor => $paletteLabel): ?>
                                <?php $isSelectedPaletteColor = $statusColor === $paletteColor; ?>
                                <button
                                    type="button"
                                    class="workspace-status-color-option<?= $isSelectedPaletteColor ? ' is-selected' : '' ?>"
                                    data-workspace-status-color-option
                                    data-value="<?= e($paletteColor) ?>"
                                    data-label="<?= e($paletteLabel) ?>"
                                    role="option"
                                    aria-selected="<?= $isSelectedPaletteColor ? 'true' : 'false' ?>"
                                >
                                    <span
                                        class="workspace-status-color-option-swatch"
                                        aria-hidden="true"
                                        style="--workspace-status-option-color: <?= e($paletteColor) ?>;"
                                    ></span>
                                    <span class="workspace-status-color-option-label"><?= e($paletteLabel) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
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
                    <?php if ($canReorderStatus): ?>
                        <button
                            type="button"
                            class="workspace-status-reorder-handle"
                            data-workspace-status-reorder-handle
                            draggable="true"
                            aria-label="Reorganizar status"
                            title="Arrastar para reorganizar"
                        >
                            <span class="workspace-status-reorder-dots" aria-hidden="true">
                                <span></span><span></span><span></span>
                                <span></span><span></span><span></span>
                            </span>
                        </button>
                    <?php else: ?>
                        <span class="workspace-status-reorder-handle workspace-status-reorder-handle-placeholder" aria-hidden="true"></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($canManageTaskStatuses): ?>
            <div class="workspace-statuses-footer">
                <div class="workspace-status-create-row" data-workspace-status-create-row>
                    <input
                        type="text"
                        name="new_status_label"
                        maxlength="40"
                        placeholder="Novo status"
                        aria-label="Novo status"
                    >
                    <div
                        class="workspace-status-color-control workspace-status-color-control-new"
                        title="Cor do novo status"
                        data-workspace-status-color-control
                        style="--workspace-status-selected-color: <?= e($newWorkspaceStatusColor) ?>;"
                    >
                        <input
                            type="hidden"
                            name="new_status_color"
                            data-workspace-status-color-input
                            data-workspace-status-color-kind="in_progress"
                            value="<?= e($newWorkspaceStatusColor) ?>"
                        >
                        <button
                            type="button"
                            class="workspace-status-color-trigger"
                            data-workspace-status-color-trigger
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            aria-label="Cor do novo status"
                        >
                            <span
                                class="workspace-status-color-trigger-swatch"
                                aria-hidden="true"
                                data-workspace-status-color-current-swatch
                                style="--workspace-status-option-color: <?= e($newWorkspaceStatusColor) ?>;"
                            ></span>
                            <span class="workspace-status-color-trigger-label" data-workspace-status-color-current-label>
                                <?= e($newWorkspaceStatusColorLabel) ?>
                            </span>
                        </button>
                        <div class="workspace-status-color-menu" data-workspace-status-color-menu role="listbox" hidden>
                            <?php foreach ($workspaceStatusColorPalette as $paletteColor => $paletteLabel): ?>
                                <?php $isSelectedPaletteColor = $newWorkspaceStatusColor === $paletteColor; ?>
                                <button
                                    type="button"
                                    class="workspace-status-color-option<?= $isSelectedPaletteColor ? ' is-selected' : '' ?>"
                                    data-workspace-status-color-option
                                    data-value="<?= e($paletteColor) ?>"
                                    data-label="<?= e($paletteLabel) ?>"
                                    role="option"
                                    aria-selected="<?= $isSelectedPaletteColor ? 'true' : 'false' ?>"
                                >
                                    <span
                                        class="workspace-status-color-option-swatch"
                                        aria-hidden="true"
                                        style="--workspace-status-option-color: <?= e($paletteColor) ?>;"
                                    ></span>
                                    <span class="workspace-status-color-option-label"><?= e($paletteLabel) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button
                        type="submit"
                        class="btn btn-mini btn-ghost workspace-status-add-button"
                        title="Adicionar status"
                        data-workspace-status-add-button
                    >
                        <span aria-hidden="true">+</span>
                        <span class="sr-only">Adicionar status</span>
                    </button>
                </div>
                <div class="workspace-statuses-actions">
                    <button
                        type="submit"
                        class="btn btn-mini workspace-status-save-button"
                        data-workspace-status-save-button
                        disabled
                    >
                        Salvar status
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </form>
</section>
