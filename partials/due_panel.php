        <section class="due-wrap panel" id="dues" data-dashboard-view-panel="dues" hidden>
            <div class="panel-header board-header due-header">
                <div>
                    <h2>Vencimentos</h2>
                </div>
                <div class="board-summary due-summary">
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-due-group-modal
                        aria-label="Criar grupo de vencimentos"
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M3 8a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"></path>
                                <path d="M12 11v5"></path>
                                <path d="M9.5 13.5h5"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo grupo</span>
                    </button>
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-due-entry-modal
                        aria-label="Adicionar vencimento"
                        <?= empty($dueGroupsWithAccess) ? 'disabled' : '' ?>
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M8 6v4"></path>
                                <path d="M16 6v4"></path>
                                <rect x="4" y="8" width="16" height="12" rx="2"></rect>
                                <path d="M4 12h16"></path>
                                <path d="M12 14v4"></path>
                                <path d="M10 16h4"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo vencimento</span>
                    </button>
                    <span data-due-total-count><?= e(appItemCountLabel(count($dueEntries))) ?></span>
                </div>
            </div>

            <div class="due-groups-list">
                <?php if (empty($dueEntriesByGroup)): ?>
                    <div class="empty-card">
                        <p>Nenhum vencimento cadastrado ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($dueEntriesByGroup as $dueGroupName => $groupDueEntries): ?>
                        <?php
                        $dueGroupPermission = $dueGroupPermissions[$dueGroupName] ?? ['can_view' => true, 'can_access' => true];
                        $dueGroupCanAccess = !empty($dueGroupPermission['can_access']);
                        $dueGroupPermissionsModalKey = 'due-group-perm-' . md5((string) $dueGroupName);
                        ?>
                        <section class="task-group due-group<?= $dueGroupCanAccess ? '' : ' task-group-readonly' ?>" data-due-group data-group-name="<?= e((string) $dueGroupName) ?>">
                            <header class="task-group-head" data-due-group-head-toggle>
                                <div class="task-group-head-main">
                                    <form method="post" class="task-group-rename-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="rename_due_group">
                                        <input type="hidden" name="old_group_name" value="<?= e((string) $dueGroupName) ?>">
                                        <h3>
                                            <input
                                                type="text"
                                                name="new_group_name"
                                                value="<?= e((string) $dueGroupName) ?>"
                                                maxlength="60"
                                                class="task-group-name-input"
                                                aria-label="Nome do grupo de vencimentos"
                                                spellcheck="false"
                                                <?= $dueGroupCanAccess ? '' : 'readonly' ?>
                                            >
                                        </h3>
                                        <button type="submit" class="sr-only">Salvar grupo</button>
                                    </form>
                                </div>
                                <div class="task-group-head-actions">
                                    <span class="task-group-collapse" data-group-toggle-indicator aria-hidden="true"><span>&#9662;</span></span>
                                    <?php if (!empty($canManageWorkspace) && empty($isPersonalWorkspace)): ?>
                                        <button
                                            type="button"
                                            class="group-permissions-button"
                                            data-open-group-permissions-modal="<?= e($dueGroupPermissionsModalKey) ?>"
                                            aria-label="Gerenciar acesso do grupo de vencimentos <?= e((string) $dueGroupName) ?>"
                                        >
                                            Acesso
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($dueGroupCanAccess): ?>
                                        <button
                                            type="button"
                                            class="group-add-button"
                                            data-open-due-entry-modal
                                            data-create-group="<?= e((string) $dueGroupName) ?>"
                                            aria-label="Adicionar vencimento no grupo <?= e((string) $dueGroupName) ?>"
                                        >+</button>
                                        <form method="post" class="task-group-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_due_group">
                                            <input type="hidden" name="group_name" value="<?= e((string) $dueGroupName) ?>">
                                            <button
                                                type="submit"
                                                class="task-group-delete"
                                                aria-label="Excluir grupo de vencimentos <?= e((string) $dueGroupName) ?>"
                                            ><span aria-hidden="true">&#10005;</span></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$dueGroupCanAccess): ?>
                                        <span class="task-group-readonly-tag">Somente leitura</span>
                                    <?php endif; ?>
                                    <span class="task-group-count"><?= e((string) count($groupDueEntries)) ?></span>
                                </div>
                            </header>

                            <div class="due-group-rows" data-due-group-rows>
                                <?php if (!$groupDueEntries): ?>
                                    <div class="task-group-empty-row">
                                        <?php if ($dueGroupCanAccess): ?>
                                            <button
                                                type="button"
                                                class="task-group-empty-add"
                                                data-open-due-entry-modal
                                                data-create-group="<?= e((string) $dueGroupName) ?>"
                                                aria-label="Adicionar vencimento no grupo <?= e((string) $dueGroupName) ?>"
                                            >+</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($groupDueEntries as $dueEntry): ?>
                                    <?php
                                    $dueEntryId = (int) ($dueEntry['id'] ?? 0);
                                    $dueLabel = (string) ($dueEntry['label'] ?? '');
                                    $dueDateValue = dueDateForStorage((string) ($dueEntry['due_date'] ?? ''));
                                    $dueRecurrenceType = normalizeDueRecurrenceType((string) ($dueEntry['recurrence_type'] ?? 'monthly'));
                                    $dueMonthlyDay = normalizeDueMonthlyDay($dueEntry['monthly_day'] ?? null);
                                    if ($dueRecurrenceType === 'monthly' && $dueMonthlyDay === null) {
                                        $dueMonthlyDay = dueMonthlyDayFromDate($dueDateValue);
                                    }
                                    $dueNextDateValue = dueDateForStorage((string) ($dueEntry['next_due_date'] ?? ''));
                                    if ($dueNextDateValue === null) {
                                        $dueNextDateValue = dueNextDueDate($dueRecurrenceType, $dueMonthlyDay, $dueDateValue);
                                    }
                                    $dueNextPresentation = taskDueDatePresentation($dueNextDateValue);
                                    $dueNextDisplay = (string) ($dueNextPresentation['display'] ?? '-');
                                    $dueNextTitle = (string) ($dueNextPresentation['title'] ?? $dueNextDisplay);
                                    $dueNextDateLabel = $dueNextDisplay;
                                    if ($dueNextDateValue !== null) {
                                        $dueNextDateObject = DateTimeImmutable::createFromFormat('Y-m-d', $dueNextDateValue);
                                        if ($dueNextDateObject) {
                                            $dueNextDateLabel = $dueNextDateObject->format('d/m/Y');
                                        }
                                    }
                                    if ($dueRecurrenceType === 'monthly') {
                                        $dueMonthlyDayLabel = $dueMonthlyDay !== null
                                            ? str_pad((string) $dueMonthlyDay, 2, '0', STR_PAD_LEFT)
                                            : '--';
                                        $dueScheduleLabel = 'Mensal - dia ' . $dueMonthlyDayLabel;
                                        $dueScheduleTitle = 'Vencimento mensal no dia ' . $dueMonthlyDayLabel . '.';
                                    } elseif ($dueRecurrenceType === 'annual') {
                                        $dueAnnualReferenceDate = $dueDateValue ?? $dueNextDateValue;
                                        $dueAnnualMonthDayLabel = '--/--';
                                        if ($dueAnnualReferenceDate !== null) {
                                            $dueAnnualDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $dueAnnualReferenceDate);
                                            if ($dueAnnualDateObj) {
                                                $dueAnnualMonthDayLabel = $dueAnnualDateObj->format('d/m');
                                            }
                                        }
                                        $dueScheduleLabel = 'Anual - ' . $dueAnnualMonthDayLabel;
                                        $dueScheduleTitle = 'Vencimento anual em ' . $dueAnnualMonthDayLabel . '.';
                                    } else {
                                        $dueScheduleLabel = 'Sem recorrencia';
                                        $dueScheduleTitle = 'Vencimento em ' . $dueNextTitle;
                                    }
                                    $dueGroupValue = (string) ($dueEntry['group_name'] ?? $dueGroupName);
                                    $dueAmountCents = normalizeDueAmountCents($dueEntry['amount_cents'] ?? null) ?? 0;
                                    $dueAmountDisplay = dueAmountLabelFromCents($dueAmountCents);
                                    ?>
                                    <article
                                        class="due-entry-row"
                                        data-due-entry
                                        data-entry-id="<?= e((string) $dueEntryId) ?>"
                                        data-entry-label="<?= e($dueLabel) ?>"
                                        data-entry-date="<?= e((string) ($dueDateValue ?? '')) ?>"
                                        data-entry-next-date="<?= e((string) ($dueNextDateValue ?? '')) ?>"
                                        data-entry-recurrence-type="<?= e($dueRecurrenceType) ?>"
                                        data-entry-monthly-day="<?= e((string) ($dueMonthlyDay ?? '')) ?>"
                                        data-entry-amount-cents="<?= e((string) $dueAmountCents) ?>"
                                        data-entry-group="<?= e($dueGroupValue) ?>"
                                    >
                                        <div class="due-entry-main">
                                            <div class="due-entry-line">
                                                <span class="due-entry-title"><?= e($dueLabel) ?></span>
                                                <span
                                                    class="due-entry-schedule<?= $dueRecurrenceType === 'monthly' ? ' is-monthly' : ($dueRecurrenceType === 'annual' ? ' is-annual' : ' is-fixed') ?>"
                                                    title="<?= e($dueScheduleTitle) ?>"
                                                ><?= e($dueScheduleLabel) ?></span>
                                                <span class="due-entry-next" title="Próximo vencimento: <?= e($dueNextTitle) ?>">
                                                    <span class="due-entry-inline-label">Prox.:</span>
                                                    <strong class="due-entry-next-date"><?= e($dueNextDateLabel) ?></strong>
                                                </span>
                                                <span class="due-entry-amount" title="Valor a pagar">
                                                    <span class="due-entry-inline-label">Valor:</span>
                                                    <strong class="due-entry-amount-number"><?= e($dueAmountDisplay) ?></strong>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="vault-entry-tools">
                                            <?php if ($dueGroupCanAccess): ?>
                                                <button
                                                    type="button"
                                                    class="vault-icon-button"
                                                    data-open-due-edit-modal
                                                    aria-label="Editar vencimento"
                                                >
                                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M4 20h4l10-10-4-4L4 16v4Z"></path>
                                                        <path d="m12 6 4 4"></path>
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="vault-entry-delete-button"
                                                    data-due-delete-entry
                                                    data-delete-form-id="delete-due-entry-<?= e((string) $dueEntryId) ?>"
                                                    aria-label="Excluir vencimento"
                                                >
                                                    <span aria-hidden="true">&#10005;</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>

                                        <form method="post" id="delete-due-entry-<?= e((string) $dueEntryId) ?>" class="vault-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_due_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $dueEntryId) ?>">
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

