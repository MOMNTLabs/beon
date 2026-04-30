        <section class="inventory-wrap panel" id="inventory" data-dashboard-view-panel="inventory" hidden>
            <div class="panel-header board-header due-header">
                <div>
                    <h2>Estoque</h2>
                </div>
                <div class="board-summary inventory-summary">
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-inventory-group-modal
                        aria-label="Criar grupo de estoque"
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
                        data-open-inventory-entry-modal
                        aria-label="Adicionar item ao estoque"
                        <?= empty($inventoryGroupsWithAccess) ? 'disabled' : '' ?>
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <path d="M12 5v14"></path>
                                <path d="M5 12h14"></path>
                                <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo item</span>
                    </button>
                    <span data-inventory-total-count><?= e((string) count($inventoryEntries)) ?> item(ns)</span>
                </div>
            </div>

            <div class="inventory-groups-list">
                <?php if (empty($inventoryEntriesByGroup)): ?>
                    <div class="empty-card">
                        <p>Nenhum item de estoque cadastrado ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($inventoryEntriesByGroup as $inventoryGroupName => $groupInventoryEntries): ?>
                        <section
                            class="task-group inventory-group"
                            data-inventory-group
                            data-group-name="<?= e((string) $inventoryGroupName) ?>"
                        >
                            <header class="task-group-head" data-inventory-group-head-toggle>
                                <div class="task-group-head-main">
                                    <form method="post" class="task-group-rename-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="rename_inventory_group">
                                        <input type="hidden" name="old_group_name" value="<?= e((string) $inventoryGroupName) ?>">
                                        <h3>
                                            <input
                                                type="text"
                                                name="new_group_name"
                                                value="<?= e((string) $inventoryGroupName) ?>"
                                                maxlength="60"
                                                class="task-group-name-input"
                                                aria-label="Nome do grupo de estoque"
                                                spellcheck="false"
                                            >
                                        </h3>
                                        <button type="submit" class="sr-only">Salvar grupo</button>
                                    </form>
                                </div>
                                <div class="task-group-head-actions">
                                    <span class="task-group-collapse" data-group-toggle-indicator aria-hidden="true"><span>&#9662;</span></span>
                                    <button
                                        type="button"
                                        class="group-add-button"
                                        data-open-inventory-entry-modal
                                        data-create-group="<?= e((string) $inventoryGroupName) ?>"
                                        aria-label="Adicionar item no grupo <?= e((string) $inventoryGroupName) ?>"
                                    >+</button>
                                    <form method="post" class="task-group-delete-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete_inventory_group">
                                        <input type="hidden" name="group_name" value="<?= e((string) $inventoryGroupName) ?>">
                                        <button
                                            type="submit"
                                            class="task-group-delete"
                                            aria-label="Excluir grupo de estoque <?= e((string) $inventoryGroupName) ?>"
                                        ><span aria-hidden="true">&#10005;</span></button>
                                    </form>
                                    <span class="task-group-count"><?= e((string) count($groupInventoryEntries)) ?></span>
                                </div>
                            </header>

                            <div class="inventory-group-rows" data-inventory-group-rows>
                                <?php if (!$groupInventoryEntries): ?>
                                    <div class="task-group-empty-row">
                                        <button
                                            type="button"
                                            class="task-group-empty-add"
                                            data-open-inventory-entry-modal
                                            data-create-group="<?= e((string) $inventoryGroupName) ?>"
                                            aria-label="Adicionar item no grupo <?= e((string) $inventoryGroupName) ?>"
                                        >+</button>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($groupInventoryEntries as $inventoryEntry): ?>
                                    <?php
                                    $inventoryEntryId = (int) ($inventoryEntry['id'] ?? 0);
                                    $inventoryLabel = (string) ($inventoryEntry['label'] ?? '');
                                    $inventoryQuantityValue = normalizeInventoryQuantityValue($inventoryEntry['quantity_value'] ?? null) ?? 0;
                                    $inventoryQuantityDisplay = (string) ($inventoryEntry['quantity_display'] ?? inventoryQuantityLabel($inventoryQuantityValue));
                                    $inventoryQuantityInput = (string) ($inventoryEntry['quantity_value_input'] ?? inventoryQuantityInputValue($inventoryQuantityValue));
                                    $inventoryMinQuantityValue = normalizeInventoryQuantityValue($inventoryEntry['min_quantity_value'] ?? null);
                                    $inventoryMinQuantityInput = $inventoryMinQuantityValue !== null
                                        ? (string) ($inventoryEntry['min_quantity_value_input'] ?? inventoryQuantityInputValue($inventoryMinQuantityValue))
                                        : '';
                                    $inventoryUnitLabel = normalizeInventoryUnitLabel((string) ($inventoryEntry['unit_label'] ?? 'un'));
                                    $inventoryGroupValue = (string) ($inventoryEntry['group_name'] ?? $inventoryGroupName);
                                    $inventoryNotes = (string) ($inventoryEntry['notes'] ?? '');
                                    $inventoryLowStock = ((int) ($inventoryEntry['is_low_stock'] ?? 0)) === 1;
                                    ?>
                                    <article
                                        class="inventory-entry-row"
                                        data-inventory-entry
                                        data-entry-id="<?= e((string) $inventoryEntryId) ?>"
                                        data-entry-label="<?= e($inventoryLabel) ?>"
                                        data-entry-quantity-value="<?= e($inventoryQuantityInput) ?>"
                                        data-entry-min-quantity-value="<?= e($inventoryMinQuantityInput) ?>"
                                        data-entry-unit-label="<?= e($inventoryUnitLabel) ?>"
                                        data-entry-group="<?= e($inventoryGroupValue) ?>"
                                        data-entry-notes="<?= e($inventoryNotes) ?>"
                                    >
                                        <div class="inventory-entry-main">
                                            <div class="inventory-entry-line">
                                                <span class="inventory-entry-title"><?= e($inventoryLabel) ?></span>
                                                <form
                                                    method="post"
                                                    class="inventory-entry-qty inventory-entry-qty-form"
                                                    data-inventory-inline-quantity-form
                                                    title="Quantidade disponível"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="update_inventory_entry_quantity">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $inventoryEntryId) ?>">
                                                    <span class="inventory-entry-inline-label">Qtd.</span>
                                                    <label class="inventory-entry-qty-editor">
                                                        <span class="inventory-entry-qty-control">
                                                            <input
                                                                type="number"
                                                                name="quantity_value"
                                                                min="0"
                                                                step="1"
                                                                value="<?= e($inventoryQuantityInput) ?>"
                                                                class="inventory-entry-qty-input"
                                                                data-inventory-inline-quantity-input
                                                                aria-label="Quantidade de <?= e($inventoryLabel) ?>"
                                                            >
                                                            <button
                                                                type="button"
                                                                class="inventory-entry-qty-step inventory-entry-qty-step-right"
                                                                data-inventory-inline-quantity-step
                                                                data-step="1"
                                                                aria-label="Aumentar quantidade"
                                                            >&#9654;</button>
                                                            <button
                                                                type="button"
                                                                class="inventory-entry-qty-step inventory-entry-qty-step-left"
                                                                data-inventory-inline-quantity-step
                                                                data-step="-1"
                                                                aria-label="Diminuir quantidade"
                                                            >&#9664;</button>
                                                        </span>
                                                        <span class="inventory-entry-qty-unit"><?= e($inventoryUnitLabel) ?></span>
                                                    </label>
                                                    <button type="submit" class="sr-only">Salvar quantidade</button>
                                                </form>
                                                <?php if ($inventoryLowStock): ?>
                                                    <span class="inventory-entry-alert" title="Quantidade atual abaixo do estoque mínimo">Baixo estoque</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="vault-entry-tools">
                                            <button
                                                type="button"
                                                class="vault-icon-button"
                                                data-open-inventory-edit-modal
                                                aria-label="Editar item de estoque"
                                            >
                                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M4 20h4l10-10-4-4L4 16v4Z"></path>
                                                    <path d="m12 6 4 4"></path>
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="vault-entry-delete-button"
                                                data-inventory-delete-entry
                                                data-delete-form-id="delete-inventory-entry-<?= e((string) $inventoryEntryId) ?>"
                                                aria-label="Excluir item de estoque"
                                            >
                                                <span aria-hidden="true">&#10005;</span>
                                            </button>
                                        </div>

                                        <form method="post" id="delete-inventory-entry-<?= e((string) $inventoryEntryId) ?>" class="vault-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_inventory_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $inventoryEntryId) ?>">
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="accounting-wrap panel" id="accounting" data-dashboard-view-panel="accounting" hidden>
            <div class="panel-header board-header accounting-header">
                <div>
                    <h2>Contabilidade</h2>
                    <p class="accounting-period-label"><?= e($accountingPeriodLabel) ?></p>
                </div>
                <div class="board-summary accounting-board-summary">
                    <form method="get" action="<?= e(appPath('#accounting')) ?>" class="accounting-period-form">
                        <a
                            href="<?= e($accountingPreviousPeriodPath) ?>"
                            class="accounting-period-nav"
                            aria-label="Ir para o mes anterior"
                        >
                            <svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
                                <path d="M9.5 3.5 5 8l4.5 4.5"></path>
                            </svg>
                        </a>
                        <label for="accounting-period-input" class="sr-only">Periodo de referencia</label>
                        <input
                            type="month"
                            id="accounting-period-input"
                            name="accounting_period"
                            value="<?= e($accountingPeriod) ?>"
                            class="accounting-period-input"
                        >
                        <a
                            href="<?= e($accountingNextPeriodPath) ?>"
                            class="accounting-period-nav"
                            aria-label="Ir para o próximo mês"
                        >
                            <svg viewBox="0 0 16 16" focusable="false" aria-hidden="true">
                                <path d="M6.5 3.5 11 8l-4.5 4.5"></path>
                            </svg>
                        </a>
                    </form>
                </div>
            </div>

            <?php include __DIR__ . '/accounting_sheet.php'; ?>
        </section>

