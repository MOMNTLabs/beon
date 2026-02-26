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
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="btn btn-pill btn-light">Sair</button>
    </form>
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
                <div class="panel-header">
                    <h2>Usuarios</h2>
                </div>
                <ul class="team-list">
                    <?php if (!$users): ?>
                        <li>Nenhum usuario cadastrado.</li>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <li>
                                <div class="avatar small" aria-hidden="true"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></div>
                                <div>
                                    <strong><?= e((string) $user['name']) ?></strong>
                                    <span><?= e((string) $user['email']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <footer class="sidebar-footer">
                <button type="button" class="icon-gear-button sidebar-settings-button" aria-label="Configuracoes">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M10.3 2.6h3.4l.5 2a7.8 7.8 0 0 1 1.9.8l1.8-1 2.4 2.4-1 1.8c.3.6.6 1.2.8 1.9l2 .5v3.4l-2 .5a7.8 7.8 0 0 1-.8 1.9l1 1.8-2.4 2.4-1.8-1a7.8 7.8 0 0 1-1.9.8l-.5 2h-3.4l-.5-2a7.8 7.8 0 0 1-1.9-.8l-1.8 1-2.4-2.4 1-1.8a7.8 7.8 0 0 1-.8-1.9l-2-.5v-3.4l2-.5c.2-.7.5-1.3.8-1.9l-1-1.8 2.4-2.4 1.8 1c.6-.3 1.2-.6 1.9-.8l.5-2Z"></path>
                        <circle cx="12" cy="12" r="3.2"></circle>
                    </svg>
                </button>
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

            <form method="get" class="task-filters" id="task-filters">
                <label>
                    <span>Status</span>
                    <select name="status">
                        <option value="">Todos</option>
                        <?php foreach ($statusOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $statusFilter === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Responsavel</span>
                    <select name="assignee">
                        <option value="">Todos</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= e((string) $user['id']) ?>"<?= $assigneeFilterId === (int) $user['id'] ? ' selected' : '' ?>>
                                <?= e((string) $user['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="task-filters-actions">
                    <button type="submit" class="btn btn-mini">Filtrar</button>
                    <a href="index.php#tasks" class="btn btn-mini btn-ghost">Limpar</a>
                </div>

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
                                            ><span aria-hidden="true">x</span></button>
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
                                    ?>
                                    <article
                                        class="task-list-item"
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

                                                <label class="tag-field tag-field-status">
                                                    <span class="sr-only">Status</span>
                                                    <select name="status" class="tag-select status-select status-<?= e($statusKey) ?>">
                                                        <?php foreach ($statusOptions as $optionKey => $optionLabel): ?>
                                                            <option value="<?= e($optionKey) ?>"<?= $optionKey === $statusKey ? ' selected' : '' ?>>
                                                                <?= e($optionLabel) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>

                                                <label class="tag-field tag-field-priority">
                                                    <span class="sr-only">Prioridade</span>
                                                    <select name="priority" class="tag-select priority-select priority-<?= e($priorityKey) ?>">
                                                        <?php foreach ($priorityOptions as $optionKey => $optionLabel): ?>
                                                            <option value="<?= e($optionKey) ?>"<?= $optionKey === $priorityKey ? ' selected' : '' ?>>
                                                                <?= e($optionLabel) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>

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
                                                    <span aria-hidden="true">×</span>
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
                <span aria-hidden="true">×</span>
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
                    <select name="priority">
                        <?php foreach ($priorityOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>"<?= $key === 'medium' ? ' selected' : '' ?>><?= e($label) ?></option>
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
                <span aria-hidden="true">×</span>
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
                    <span aria-hidden="true">×</span>
                </button>
            </div>
        </header>

        <div class="task-detail-modal-body">
            <section class="task-detail-view" data-task-detail-view>
                <div class="task-detail-view-block">
                    <div class="task-detail-view-title" data-task-detail-view-title></div>
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

                <div class="task-detail-view-meta">
                    <span data-task-detail-view-created-by></span>
                    <span data-task-detail-view-updated-at></span>
                </div>
            </section>

            <section class="task-detail-edit" data-task-detail-edit-panel hidden>
                <div class="form-stack modal-form">
                    <label>
                        <span>Titulo</span>
                        <input type="text" maxlength="140" required data-task-detail-edit-title>
                    </label>

                    <div class="form-row">
                        <label>
                            <span>Status</span>
                            <select class="tag-select status-select" data-task-detail-edit-status>
                                <?php foreach ($statusOptions as $key => $label): ?>
                                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span>Prioridade</span>
                            <select class="tag-select priority-select" data-task-detail-edit-priority>
                                <?php foreach ($priorityOptions as $key => $label): ?>
                                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
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

                    <div class="assignee-picker-wrap">
                        <span class="assignee-picker-label">Responsaveis</span>
                        <details class="assignee-picker" data-task-detail-edit-assignees>
                            <summary>Selecionar</summary>
                            <div class="assignee-picker-menu" data-task-detail-edit-assignees-menu></div>
                        </details>
                    </div>

                    <label>
                        <span>Descricao</span>
                        <textarea rows="5" data-task-detail-edit-description></textarea>
                    </label>
                </div>
            </section>
        </div>
    </section>
</div>

<div class="modal-backdrop" data-confirm-modal hidden>
    <div class="modal-scrim" data-close-confirm-modal></div>
    <section class="modal-card confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
        <header class="modal-head">
            <h2 id="confirm-modal-title">Confirmar</h2>
            <button type="button" class="modal-close-button" data-close-confirm-modal aria-label="Fechar modal">
                <span aria-hidden="true">×</span>
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
