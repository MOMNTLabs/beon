<?php
$workspaceSidebarConfig = is_array($workspaceSidebarConfig ?? null)
    ? $workspaceSidebarConfig
    : workspaceSidebarToolsConfig($currentWorkspaceId ?? null, $currentWorkspace ?? null);
$sidebarOptionalLabels = is_array($workspaceSidebarConfig['optional_labels'] ?? null)
    ? $workspaceSidebarConfig['optional_labels']
    : workspaceSidebarOptionalToolLabels();
$enabledOptionalTools = is_array($workspaceSidebarConfig['enabled_optional'] ?? null)
    ? $workspaceSidebarConfig['enabled_optional']
    : [];
$availableToAddTools = is_array($workspaceSidebarConfig['available_to_add'] ?? null)
    ? $workspaceSidebarConfig['available_to_add']
    : array_keys($sidebarOptionalLabels);
?>

<section class="workspace-settings-card workspace-sidebar-tools-card<?= empty($canManageWorkspace) ? ' is-readonly' : '' ?>">
    <h3>Ferramentas</h3>

    <?php if (!empty($canManageWorkspace)): ?>
        <form method="post" class="workspace-settings-form workspace-sidebar-tools-form" data-sidebar-tools-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="workspace_update_sidebar_tools">

            <label>
                <span>Adicionar ferramenta</span>
                <div class="workspace-sidebar-tools-add-row">
                    <select data-sidebar-tools-add-select>
                        <option value="">Selecione...</option>
                        <?php foreach ($sidebarOptionalLabels as $toolKey => $toolLabel): ?>
                            <?php $toolAvailable = in_array($toolKey, $availableToAddTools, true); ?>
                            <option
                                value="<?= e((string) $toolKey) ?>"
                                <?= $toolAvailable ? '' : 'disabled hidden' ?>
                            >
                                <?= e((string) $toolLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-mini btn-ghost" data-sidebar-tools-add-button>Adicionar</button>
                </div>
            </label>

            <ul class="workspace-sidebar-tools-list" data-sidebar-tools-list>
                <?php foreach ($enabledOptionalTools as $toolKey): ?>
                    <?php $toolLabel = (string) ($sidebarOptionalLabels[$toolKey] ?? $toolKey); ?>
                    <li class="workspace-sidebar-tool-item" data-sidebar-tool-key="<?= e((string) $toolKey) ?>">
                        <input type="hidden" name="sidebar_tools[]" value="<?= e((string) $toolKey) ?>" data-sidebar-tool-input>
                        <span class="workspace-sidebar-tool-item-label"><?= e($toolLabel) ?></span>
                        <div class="workspace-sidebar-tool-item-actions">
                            <button type="button" class="workspace-sidebar-tool-action" data-sidebar-tools-move="up" aria-label="Mover para cima" title="Mover para cima">↑</button>
                            <button type="button" class="workspace-sidebar-tool-action" data-sidebar-tools-move="down" aria-label="Mover para baixo" title="Mover para baixo">↓</button>
                            <button type="button" class="workspace-sidebar-tool-action is-remove" data-sidebar-tools-remove aria-label="Remover ferramenta" title="Remover">Remover</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <p class="workspace-sidebar-tools-empty" data-sidebar-tools-empty <?= $enabledOptionalTools === [] ? '' : 'hidden' ?>>
                Nenhuma ferramenta adicional no sidebar.
            </p>

            <button type="submit" class="btn btn-mini">Salvar ferramentas</button>

            <template data-sidebar-tools-row-template>
                <li class="workspace-sidebar-tool-item" data-sidebar-tool-key="">
                    <input type="hidden" name="sidebar_tools[]" value="" data-sidebar-tool-input>
                    <span class="workspace-sidebar-tool-item-label"></span>
                    <div class="workspace-sidebar-tool-item-actions">
                        <button type="button" class="workspace-sidebar-tool-action" data-sidebar-tools-move="up" aria-label="Mover para cima" title="Mover para cima">↑</button>
                        <button type="button" class="workspace-sidebar-tool-action" data-sidebar-tools-move="down" aria-label="Mover para baixo" title="Mover para baixo">↓</button>
                        <button type="button" class="workspace-sidebar-tool-action is-remove" data-sidebar-tools-remove aria-label="Remover ferramenta" title="Remover">Remover</button>
                    </div>
                </li>
            </template>
        </form>
    <?php else: ?>
        <ul class="workspace-sidebar-tools-readonly-list">
            <li>Lista de tarefas</li>
            <?php foreach ($enabledOptionalTools as $toolKey): ?>
                <li><?= e((string) ($sidebarOptionalLabels[$toolKey] ?? $toolKey)) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
