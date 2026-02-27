<header class="top-nav dashboard-nav">
    <a href="index.php" class="brand" aria-label="WorkForm">
        <img src="assets/WorkForm - Logo (Negativa).svg?v=1" alt="WorkForm" class="brand-lockup" width="116" height="29">
    </a>

    <div class="user-chip">
        <div class="avatar" aria-hidden="true"><?= e(strtoupper(substr((string) $currentUser['name'], 0, 1))) ?></div>
        <div>
            <strong><?= e((string) $currentUser['name']) ?></strong>
            <span><?= e((string) $currentUser['email']) ?></span>
        </div>
    </div>
    <div class="top-nav-actions">
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
</header>

<main class="dashboard dashboard-compact">
    <section class="stats-strip dashboard-stats" aria-label="Indicadores do workspace">
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

    <section class="workspace-layout tasklist-layout">
        <aside class="panel users-sidebar" id="team">
            <div class="users-sidebar-body">
                <div class="panel-header workspace-sidebar-header">
                    <details class="workspace-sidebar-picker">
                        <summary aria-label="Trocar workspace">
                            <span class="workspace-sidebar-picker-title"><?= e((string) ($currentWorkspace['name'] ?? 'Workspace')) ?></span>
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
                                        <span class="workspace-sidebar-picker-current"><?= e($workspaceOptionName) ?></span>
                                    <?php else: ?>
                                        <form method="post" class="workspace-sidebar-picker-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="switch_workspace">
                                            <input type="hidden" name="workspace_id" value="<?= e((string) $workspaceOptionId) ?>">
                                            <button type="submit" class="workspace-sidebar-picker-option"><?= e($workspaceOptionName) ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                            <form method="post" class="workspace-sidebar-create-form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                <input type="hidden" name="action" value="create_workspace">
                                <label>
                                    <span class="sr-only">Novo workspace</span>
                                    <input type="text" name="workspace_name" maxlength="80" placeholder="Novo workspace" required>
                                </label>
                                <button type="submit" class="btn btn-mini btn-ghost">Criar</button>
                            </form>
                        </div>
                    </details>
                    <p>Equipe do workspace</p>
                </div>
                <ul class="team-list">
                    <?php if (!$workspaceMembers): ?>
                        <li>Nenhum usuario cadastrado.</li>
                    <?php else: ?>
                        <?php foreach ($workspaceMembers as $workspaceMember): ?>
                            <?php
                            $memberRole = normalizeWorkspaceRole((string) ($workspaceMember['workspace_role'] ?? 'member'));
                            $memberRoleLabel = workspaceRoles()[$memberRole] ?? 'Usuario';
                            ?>
                            <li>
                                <div class="avatar small" aria-hidden="true"><?= e(strtoupper(substr((string) $workspaceMember['name'], 0, 1))) ?></div>
                                <div class="team-user-meta">
                                    <strong><?= e((string) $workspaceMember['name']) ?></strong>
                                    <span class="workspace-member-role workspace-role-<?= e((string) $memberRole) ?>"><?= e((string) $memberRoleLabel) ?></span>
                                    <span><?= e((string) $workspaceMember['email']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <footer class="sidebar-footer">
                <a
                    href="workspace-settings.php"
                    class="icon-gear-button sidebar-settings-button"
                    aria-label="Configuracoes do workspace"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10.3 2.6h3.4l.5 2a7.8 7.8 0 0 1 1.9.8l1.8-1 2.4 2.4-1 1.8c.3.6.6 1.2.8 1.9l2 .5v3.4l-2 .5a7.8 7.8 0 0 1-.8 1.9l1 1.8-2.4 2.4-1.8-1a7.8 7.8 0 0 1-1.9.8l-.5 2h-3.4l-.5-2a7.8 7.8 0 0 1-1.9-.8l-1.8 1-2.4-2.4 1-1.8a7.8 7.8 0 0 1-.8-1.9l-2-.5v-3.4l2-.5c.2-.7.5-1.3.8-1.9l-1-1.8 2.4-2.4 1.8 1c.6-.3 1.2-.6 1.9-.8l.5-2Z"></path>
                        <circle cx="12" cy="12" r="3.2"></circle>
                    </svg>
                </a>
            </footer>
        </aside>

        <section class="tasklist-wrap panel" id="tasks">
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
                    <span>Status</span>
                    <?php $statusFilterValue = (string) ($statusFilter ?? ''); ?>
                    <div class="tag-field row-inline-picker-wrap" data-inline-select-wrap>
                        <details
                            class="row-inline-picker status-inline-picker<?= $statusFilterValue !== '' ? ' status-' . e($statusFilterValue) : '' ?>"
                            data-inline-select-picker
                        >
                            <summary aria-label="Filtrar por status">
                                <span class="row-inline-picker-summary-text" data-inline-select-text>
                                    <?php if ($statusFilterValue === ''): ?>
                                        Todos
                                    <?php else: ?>
                                        <?= e((string) ($statusOptions[$statusFilterValue] ?? 'Todos')) ?>
                                    <?php endif; ?>
                                </span>
                            </summary>
                            <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Filtro de status">
                                <button
                                    type="button"
                                    class="row-inline-picker-option<?= $statusFilterValue === '' ? ' is-active' : '' ?>"
                                    data-inline-select-option
                                    data-value=""
                                    data-label="Todos"
                                    role="option"
                                    aria-selected="<?= $statusFilterValue === '' ? 'true' : 'false' ?>"
                                >Todos</button>
                                <?php foreach ($statusOptions as $key => $label): ?>
                                    <button
                                        type="button"
                                        class="row-inline-picker-option status-<?= e($key) ?><?= $statusFilterValue === $key ? ' is-active' : '' ?>"
                                        data-inline-select-option
                                        data-value="<?= e($key) ?>"
                                        data-label="<?= e($label) ?>"
                                        role="option"
                                        aria-selected="<?= $statusFilterValue === $key ? 'true' : 'false' ?>"
                                    ><?= e($label) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </details>
                        <select
                            name="status"
                            class="tag-select status-select<?= $statusFilterValue !== '' ? ' status-' . e($statusFilterValue) : '' ?> row-inline-picker-native"
                            data-inline-select-source
                            hidden
                        >
                            <option value="">Todos</option>
                            <?php foreach ($statusOptions as $key => $label): ?>
                                <option value="<?= e($key) ?>"<?= $statusFilter === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </label>

                <label>
                    <span>Responsavel</span>
                    <?php $assigneeFilterValue = $assigneeFilterId !== null ? (string) $assigneeFilterId : ''; ?>
                    <div class="tag-field row-inline-picker-wrap" data-inline-select-wrap>
                        <details class="row-inline-picker filter-inline-picker" data-inline-select-picker>
                            <summary aria-label="Filtrar por responsavel">
                                <span class="row-inline-picker-summary-text" data-inline-select-text>
                                    <?php if ($assigneeFilterValue === ''): ?>
                                        Todos
                                    <?php else: ?>
                                        <?php
                                        $assigneeLabel = 'Todos';
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
                                    data-label="Todos"
                                    role="option"
                                    aria-selected="<?= $assigneeFilterValue === '' ? 'true' : 'false' ?>"
                                >Todos</button>
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
                            <option value="">Todos</option>
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
                        class="icon-gear-button task-filters-create-group"
                        data-open-create-group-modal
                        aria-label="Criar grupo"
                    >
                        <span aria-hidden="true">+</span>
                    </button>
                </div>
            </form>

            <datalist id="task-group-options">
                <?php foreach ($taskGroups as $groupNameOption): ?>
                    <option value="<?= e((string) $groupNameOption) ?>"></option>
                <?php endforeach; ?>
            </datalist>

            <div class="task-groups-list">
                <?php if (empty($tasksGroupedByGroup)): ?>
                    <div class="empty-card task-list-empty">
                        <p>Nenhuma tarefa encontrada com os filtros atuais.</p>
                        <button type="button" class="btn btn-mini" data-open-create-task-modal>Nova tarefa</button>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasksGroupedByGroup as $groupName => $groupTasks): ?>
                        <?php $isProtectedGroup = isset($protectedGroupName) && mb_strtolower((string) $groupName) === mb_strtolower((string) $protectedGroupName); ?>
                        <section class="task-group" aria-labelledby="group-<?= e(md5((string) $groupName)) ?>" data-task-group data-group-name="<?= e((string) $groupName) ?>">
                            <header class="task-group-head">
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
                                            >
                                        </h3>
                                    </form>
                                </div>
                                <div class="task-group-head-actions">
                                    <button
                                        type="button"
                                        class="task-group-collapse"
                                        data-group-toggle
                                        aria-expanded="true"
                                        aria-label="Retrair grupo"
                                    ><span aria-hidden="true">&#9662;</span></button>
                                    <button
                                        type="button"
                                        class="group-add-button"
                                        data-open-create-task-modal
                                        data-create-group="<?= e((string) $groupName) ?>"
                                        aria-label="Criar tarefa no grupo <?= e((string) $groupName) ?>"
                                    >+</button>
                                    <?php if (!$isProtectedGroup): ?>
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
                                    <span class="task-group-count"><?= e((string) count($groupTasks)) ?></span>
                                </div>
                            </header>

                            <div class="task-list-rows" data-task-dropzone data-group-name="<?= e((string) $groupName) ?>">
                                <?php if (!$groupTasks): ?>
                                    <div class="task-group-empty-row">
                                        <button
                                            type="button"
                                            class="task-group-empty-add"
                                            data-open-create-task-modal
                                            data-create-group="<?= e((string) $groupName) ?>"
                                            aria-label="Criar tarefa no grupo <?= e((string) $groupName) ?>"
                                        >+</button>
                                    </div>
                                <?php endif; ?>
                                <?php foreach ($groupTasks as $task): ?>
                                    <?php
                                    $taskId = (int) $task['id'];
                                    $priorityKey = normalizeTaskPriority((string) $task['priority']);
                                    $statusKey = normalizeTaskStatus((string) $task['status']);
                                    $assigneeSummary = assigneeNamesSummary($task);
                                    $dueDateValue = (string) ($task['due_date'] ?? '');
                                    $dueDateUi = taskDueDatePresentation($dueDateValue);
                                    $isOverdueMarked = ((int) ($task['overdue_flag'] ?? 0)) === 1;
                                    ?>
                                    <article
                                        class="task-list-item task-status-<?= e($statusKey) ?><?= $isOverdueMarked ? ' has-overdue-flag' : '' ?>"
                                        id="task-<?= e((string) $taskId) ?>"
                                        data-task-item
                                        data-group-name="<?= e((string) ($task['group_name'] ?? 'Geral')) ?>"
                                        draggable="true"
                                    >
                                        <form method="post" class="task-list-form" id="update-task-<?= e((string) $taskId) ?>" data-task-autosave-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_task">
                                            <input type="hidden" name="task_id" value="<?= e((string) $taskId) ?>">
                                            <input type="hidden" name="autosave" value="1">
                                            <input type="hidden" name="reference_links_json" value="<?= e(encodeReferenceUrlList($task['reference_links'] ?? [])) ?>" data-task-reference-links-json>
                                            <input type="hidden" name="reference_images_json" value="<?= e(encodeReferenceImageList($task['reference_images'] ?? [])) ?>" data-task-reference-images-json>
                                            <input type="hidden" name="overdue_flag" value="<?= $isOverdueMarked ? '1' : '0' ?>" data-task-overdue-flag>
                                            <input type="hidden" name="overdue_since_date" value="<?= e((string) ($task['overdue_since_date'] ?? '')) ?>" data-task-overdue-since-date>
                                            <input type="hidden" value="<?= e((string) (($task['overdue_days'] ?? 0))) ?>" data-task-overdue-days>
                                            <input type="hidden" value="<?= e((string) json_encode($task['history'] ?? [], JSON_UNESCAPED_UNICODE)) ?>" data-task-history-json>

                                            <div class="task-line-row">
                                                <div class="task-line-title">
                                                    <input
                                                        type="text"
                                                        name="title"
                                                        value="<?= e((string) $task['title']) ?>"
                                                        maxlength="140"
                                                        class="task-title-input"
                                                        aria-label="Titulo da tarefa"
                                                        required
                                                    >
                                                </div>

                                                <div class="status-stepper" data-status-stepper>
                                                    <button
                                                        type="button"
                                                        class="status-stepper-btn"
                                                        data-status-step="-1"
                                                        aria-label="Status anterior"
                                                    >
                                                        <span aria-hidden="true">&#8249;</span>
                                                    </button>

                                                    <div class="tag-field tag-field-status row-inline-picker-wrap" data-inline-select-wrap>
                                                        <details class="row-inline-picker status-inline-picker status-<?= e($statusKey) ?>" data-inline-select-picker>
                                                            <summary aria-label="Status da tarefa">
                                                                <span class="row-inline-picker-summary-text" data-inline-select-text><?= e((string) ($statusOptions[$statusKey] ?? 'Backlog')) ?></span>
                                                            </summary>
                                                            <div class="assignee-picker-menu row-inline-picker-menu" role="listbox" aria-label="Selecionar status">
                                                                <?php foreach ($statusOptions as $optionKey => $optionLabel): ?>
                                                                    <button
                                                                        type="button"
                                                                        class="row-inline-picker-option status-<?= e($optionKey) ?><?= $optionKey === $statusKey ? ' is-active' : '' ?>"
                                                                        data-inline-select-option
                                                                        data-value="<?= e($optionKey) ?>"
                                                                        data-label="<?= e($optionLabel) ?>"
                                                                        role="option"
                                                                        aria-selected="<?= $optionKey === $statusKey ? 'true' : 'false' ?>"
                                                                    ><?= e($optionLabel) ?></button>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </details>
                                                        <select name="status" class="tag-select status-select status-<?= e($statusKey) ?> row-inline-picker-native" data-inline-select-source hidden>
                                                            <?php foreach ($statusOptions as $optionKey => $optionLabel): ?>
                                                                <option value="<?= e($optionKey) ?>"<?= $optionKey === $statusKey ? ' selected' : '' ?>>
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
                                                                        <?= in_array((int) $user['id'], $task['assignee_ids'] ?? [], true) ? 'checked' : '' ?>
                                                                    >
                                                                    <span><?= e((string) $user['name']) ?></span>
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
                                                            foreach ($taskGroups as $groupNameOption):
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
    </section>
</main>

<div class="modal-backdrop" data-create-modal hidden>
    <div class="modal-scrim" data-close-create-modal></div>
    <section class="modal-card create-task-modal" role="dialog" aria-modal="true" aria-labelledby="create-task-title">
        <header class="modal-head">
            <h2 id="create-task-title">Nova tarefa</h2>
            <button type="button" class="modal-close-button" data-close-create-modal aria-label="Fechar modal">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>

        <form method="post" class="form-stack modal-form" data-create-task-form>
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <input type="hidden" name="action" value="create_task">

            <label>
                <span>Titulo</span>
                <input type="text" name="title" maxlength="140" required data-create-task-title-input>
            </label>

            <label>
                <span>Descricao</span>
                <textarea name="description" rows="4"></textarea>
            </label>

            <label>
                <span>Grupo</span>
                <select name="group_name" data-create-task-group-input>
                    <?php foreach ($taskGroups as $groupNameOption): ?>
                        <option value="<?= e((string) $groupNameOption) ?>"<?= (isset($protectedGroupName) && mb_strtolower((string) $groupNameOption) === mb_strtolower((string) $protectedGroupName)) ? ' selected' : '' ?>>
                            <?= e((string) $groupNameOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="form-row">
                <label>
                    <span>Status</span>
                    <select name="status">
                        <?php foreach ($statusOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Prioridade</span>
                    <select name="priority" class="priority-select priority-medium" aria-label="Prioridade">
                        <?php foreach ($priorityOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $key === 'medium' ? ' selected' : '' ?>>&#9873;</option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="form-row">
                <label>
                    <span>Prazo</span>
                    <input type="date" name="due_date" value="<?= e((new DateTimeImmutable('today'))->format('Y-m-d')) ?>">
                </label>

                <div class="assignee-picker-wrap">
                    <span class="assignee-picker-label">Responsaveis</span>
                    <details class="assignee-picker">
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
                                            <?= (int) $user['id'] === (int) $currentUser['id'] ? 'checked' : '' ?>
                                        >
                                        <span><?= e((string) $user['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </details>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-create-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill">Adicionar tarefa</button>
            </div>
        </form>
    </section>
</div>

<div class="modal-backdrop" data-create-group-modal hidden>
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

            <div class="modal-actions">
                <button type="button" class="btn btn-mini btn-ghost" data-close-create-group-modal>Cancelar</button>
                <button type="submit" class="btn btn-pill">Criar grupo</button>
            </div>
        </form>
    </section>
</div>

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
                                <span class="task-detail-view-tag" data-task-detail-view-status></span>
                                <span class="task-detail-view-tag" data-task-detail-view-priority></span>
                                <span class="task-detail-view-tag" data-task-detail-view-group></span>
                                <span class="task-detail-view-tag" data-task-detail-view-due></span>
                            </div>
                            <div class="task-detail-view-assignees" data-task-detail-view-assignees></div>
                        </div>

                        <div class="task-detail-view-block">
                            <div class="task-detail-view-label">Descricao</div>
                            <div class="task-detail-view-description" data-task-detail-view-description></div>
                        </div>

                        <div class="task-detail-view-block" data-task-detail-view-references hidden>
                            <div class="task-detail-view-label">Referencias</div>

                            <div class="task-detail-ref-section" data-task-detail-view-images-wrap hidden>
                                <div class="task-detail-ref-title">Imagens</div>
                                <div class="task-detail-ref-images" data-task-detail-view-images></div>
                            </div>

                            <div class="task-detail-ref-section" data-task-detail-view-links-wrap hidden>
                                <div class="task-detail-ref-title">Links</div>
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
                        <input type="text" maxlength="140" required data-task-detail-edit-title>
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

                                <label class="tag-field tag-field-status">
                                    <span class="sr-only">Status</span>
                                    <select class="tag-select status-select" data-task-detail-edit-status>
                                        <?php foreach ($statusOptions as $key => $label): ?>
                                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>

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
                            <label class="tag-field">
                                <span class="sr-only">Prioridade</span>
                                <select class="tag-select priority-select" data-task-detail-edit-priority>
                                    <?php foreach ($priorityOptions as $key => $label): ?>
                                        <option value="<?= e($key) ?>">&#9873;</option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="form-row">
                        <label>
                            <span>Grupo</span>
                            <select data-task-detail-edit-group></select>
                        </label>

                        <label>
                            <span>Prazo</span>
                            <input type="date" data-task-detail-edit-due-date>
                        </label>
                    </div>

                    <div class="task-detail-edit-main-row">
                        <label class="task-detail-edit-description-field">
                            <span>Descricao</span>
                            <div class="task-detail-edit-description-wrap" data-task-detail-edit-description-wrap>
                                <div class="task-detail-edit-description-toolbar" data-task-detail-edit-description-toolbar hidden>
                                    <button type="button" data-task-detail-description-format="bold">Negrito</button>
                                    <button type="button" data-task-detail-description-format="italic">Italico</button>
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
                        </label>

                        <div class="task-detail-edit-images-field">
                            <span>Imagens de referencia</span>
                            <div class="task-detail-edit-image-picker" data-task-detail-image-picker tabindex="0" aria-label="Adicionar imagens de referencia">
                                <input type="file" accept="image/*" multiple data-task-detail-image-input hidden>
                                <div class="task-detail-edit-image-picker-actions">
                                    <button type="button" class="btn btn-mini btn-ghost" data-task-detail-image-add>Adicionar imagem</button>
                                </div>
                                <div class="task-detail-edit-image-list" data-task-detail-image-list></div>
                            </div>
                            <textarea rows="1" data-task-detail-edit-images hidden></textarea>
                        </div>
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

<div class="modal-backdrop task-image-preview-modal" data-task-image-preview-modal hidden>
    <div class="modal-scrim" data-close-task-image-preview></div>
    <section class="modal-card task-image-preview-card" role="dialog" aria-modal="true" aria-label="Imagem de referencia">
        <header class="modal-head task-image-preview-head">
            <button type="button" class="modal-close-button" data-close-task-image-preview aria-label="Fechar visualizacao da imagem">
                <span aria-hidden="true">&#10005;</span>
            </button>
        </header>
        <div class="task-image-preview-body">
            <img src="" alt="Imagem de referencia ampliada" data-task-image-preview-img>
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
