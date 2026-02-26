<header class="top-nav dashboard-nav">
    <div class="brand">Workplace<span>Formula</span></div>
    <nav class="nav-links" aria-label="Navegação do dashboard">
        <a href="#tasks">Tarefas</a>
        <a href="#new-task">Nova tarefa</a>
        <a href="#team">Time</a>
    </nav>
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
            <strong><?= e((string) $stats['total']) ?></strong>
        </div>
        <div class="stat-cell">
            <span>Concluidas</span>
            <strong><?= e((string) $stats['done']) ?> (<?= e((string) $completionRate) ?>%)</strong>
        </div>
        <div class="stat-cell">
            <span>Para hoje</span>
            <strong><?= e((string) $stats['due_today']) ?></strong>
        </div>
        <div class="stat-cell">
            <span>Urgentes</span>
            <strong><?= e((string) $stats['urgent']) ?></strong>
        </div>
        <div class="stat-cell">
            <span>Minhas abertas</span>
            <strong><?= e((string) $myOpenTasks) ?></strong>
        </div>
    </section>

    <section class="workspace-layout tasklist-layout">
        <aside class="sidebar-stack">
            <section class="panel" id="new-task">
                <div class="panel-header">
                    <span class="pill-label">Criar tarefa</span>
                    <h2>Nova tarefa</h2>
                </div>

                <form method="post" class="form-stack">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="action" value="create_task">

                    <label>
                        <span>Titulo</span>
                        <input type="text" name="title" maxlength="140" placeholder="Ex.: Revisar landing da campanha" required>
                    </label>

                    <label>
                        <span>Descricao</span>
                        <textarea name="description" rows="4" placeholder="Detalhes, contexto, links ou checklist rapido..."></textarea>
                    </label>

                    <label>
                        <span>Grupo</span>
                        <input type="text" name="group_name" list="task-group-options" placeholder="Ex.: Administracao" value="Geral">
                    </label>
                    <datalist id="task-group-options">
                        <?php foreach ($taskGroups as $groupNameOption): ?>
                            <option value="<?= e((string) $groupNameOption) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

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
                            <input type="date" name="due_date">
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

                    <button type="submit" class="btn btn-pill">Adicionar tarefa</button>
                </form>
            </section>

            <section class="panel" id="team">
                <div class="panel-header">
                    <span class="pill-label">Time</span>
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
            </section>
        </aside>

        <section class="tasklist-wrap panel" id="tasks">
            <div class="panel-header board-header">
                <div>
                    <span class="pill-label">Lista de tarefas</span>
                    <h2>Tarefas agrupadas</h2>
                </div>
                <div class="board-summary">
                    <span><?= e((string) count($tasks)) ?> visiveis</span>
                    <span><?= e((string) $stats['total']) ?> total</span>
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
            </form>

            <div class="task-groups-list">
                <?php if (empty($tasks)): ?>
                    <div class="empty-card task-list-empty">
                        <p>Nenhuma tarefa encontrada com os filtros atuais.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasksGroupedByGroup as $groupName => $groupTasks): ?>
                        <section class="task-group" aria-labelledby="group-<?= e(md5((string) $groupName)) ?>">
                            <header class="task-group-head">
                                <h3 id="group-<?= e(md5((string) $groupName)) ?>"><?= e((string) $groupName) ?></h3>
                                <span><?= e((string) count($groupTasks)) ?></span>
                            </header>

                            <div class="task-list-rows">
                                <?php foreach ($groupTasks as $task): ?>
                                    <?php
                                    $taskId = (int) $task['id'];
                                    $priorityKey = normalizeTaskPriority((string) $task['priority']);
                                    $statusKey = normalizeTaskStatus((string) $task['status']);
                                    $assigneeSummary = assigneeNamesSummary($task);
                                    $dueDateValue = (string) ($task['due_date'] ?? '');
                                    $dueDateLabel = $dueDateValue !== '' ? (new DateTimeImmutable($dueDateValue))->format('d/m/Y') : 'Sem prazo';
                                    ?>
                                    <article class="task-list-item" id="task-<?= e((string) $taskId) ?>" data-task-item>
                                        <form method="post" class="task-list-form" id="update-task-<?= e((string) $taskId) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_task">
                                            <input type="hidden" name="task_id" value="<?= e((string) $taskId) ?>">

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
                                                        <summary title="<?= e($assigneeSummary) ?>"><?= e($assigneeSummary) ?></summary>
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

                                                <label class="tag-field due-tag-field" title="<?= e($dueDateLabel) ?>">
                                                    <span class="sr-only">Prazo</span>
                                                    <input type="date" name="due_date" value="<?= e($dueDateValue) ?>" class="due-date-input">
                                                </label>

                                                <button type="submit" class="btn btn-mini">Salvar</button>
                                                <button
                                                    type="button"
                                                    class="btn btn-mini btn-ghost task-expand-btn"
                                                    data-task-expand
                                                    aria-expanded="false"
                                                    aria-controls="task-details-<?= e((string) $taskId) ?>"
                                                >
                                                    Detalhes
                                                </button>
                                            </div>

                                            <div class="task-line-details" id="task-details-<?= e((string) $taskId) ?>" hidden>
                                                <div class="task-line-details-grid">
                                                    <label>
                                                        <span>Grupo</span>
                                                        <input
                                                            type="text"
                                                            name="group_name"
                                                            value="<?= e((string) ($task['group_name'] ?? 'Geral')) ?>"
                                                            list="task-group-options"
                                                            placeholder="Grupo"
                                                        >
                                                    </label>

                                                    <label>
                                                        <span>Descricao</span>
                                                        <textarea name="description" rows="3" placeholder="Descricao da tarefa..."><?= e((string) $task['description']) ?></textarea>
                                                    </label>
                                                </div>

                                                <div class="task-line-footer">
                                                    <div class="task-line-meta">
                                                        <span>Criado por <?= e((string) $task['creator_name']) ?></span>
                                                        <?php if (!empty($task['updated_at'])): ?>
                                                            <span>Atualizado em <?= e((new DateTimeImmutable((string) $task['updated_at']))->format('d/m H:i')) ?></span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="task-line-actions">
                                                        <button type="submit" class="btn btn-mini">Salvar alteracoes</button>
                                                        <button type="submit" form="delete-task-<?= e((string) $taskId) ?>" class="btn btn-mini btn-danger">Excluir</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>

                                        <form method="post" id="delete-task-<?= e((string) $taskId) ?>" class="task-delete-form" onsubmit="return confirm('Remover esta tarefa?');">
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
