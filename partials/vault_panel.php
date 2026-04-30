        <section class="vault-wrap panel" id="vault" data-dashboard-view-panel="vault" hidden>
            <div class="panel-header board-header vault-header">
                <div>
                    <h2>Gerenciador de acessos</h2>
                </div>
                <div class="board-summary vault-summary">
                    <button
                        type="button"
                        class="icon-gear-button vault-summary-button"
                        data-open-vault-group-modal
                        aria-label="Criar grupo no cofre"
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
                        data-open-vault-entry-modal
                        aria-label="Adicionar dado de acesso"
                        <?= empty($vaultGroupsWithAccess) ? 'disabled' : '' ?>
                    >
                        <span class="vault-summary-button-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false">
                                <circle cx="8" cy="12" r="2.4"></circle>
                                <path d="M10.4 12h9.6"></path>
                                <path d="M16 12v2.5"></path>
                                <path d="M19 12v2"></path>
                            </svg>
                        </span>
                        <span class="vault-summary-button-label">Novo acesso</span>
                    </button>
                    <span data-vault-total-count><?= e((string) count($vaultEntries)) ?> item(ns)</span>
                </div>
            </div>

            <div class="vault-groups-list">
                <?php if (empty($vaultEntriesByGroup)): ?>
                    <div class="empty-card">
                        <p>Nenhum item no cofre ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($vaultEntriesByGroup as $vaultGroupName => $groupVaultEntries): ?>
                        <?php
                        $vaultGroupPermission = $vaultGroupPermissions[$vaultGroupName] ?? ['can_view' => true, 'can_access' => true];
                        $vaultGroupCanAccess = !empty($vaultGroupPermission['can_access']);
                        $vaultGroupPermissionsModalKey = 'vault-group-perm-' . md5((string) $vaultGroupName);
                        ?>
                        <section class="task-group vault-group<?= $vaultGroupCanAccess ? '' : ' task-group-readonly' ?>" data-vault-group data-group-name="<?= e((string) $vaultGroupName) ?>">
                            <header class="task-group-head" data-vault-group-head-toggle>
                                <div class="task-group-head-main">
                                    <form method="post" class="task-group-rename-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                        <input type="hidden" name="action" value="rename_vault_group">
                                        <input type="hidden" name="old_group_name" value="<?= e((string) $vaultGroupName) ?>">
                                        <h3>
                                            <input
                                                type="text"
                                                name="new_group_name"
                                                value="<?= e((string) $vaultGroupName) ?>"
                                                maxlength="60"
                                                class="task-group-name-input"
                                                aria-label="Nome do grupo do cofre"
                                                spellcheck="false"
                                                <?= $vaultGroupCanAccess ? '' : 'readonly' ?>
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
                                            data-open-group-permissions-modal="<?= e($vaultGroupPermissionsModalKey) ?>"
                                            aria-label="Gerenciar acesso do grupo do cofre <?= e((string) $vaultGroupName) ?>"
                                        >
                                            Acesso
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($vaultGroupCanAccess): ?>
                                        <button
                                            type="button"
                                            class="group-add-button"
                                            data-open-vault-entry-modal
                                            data-create-group="<?= e((string) $vaultGroupName) ?>"
                                            aria-label="Adicionar item no grupo <?= e((string) $vaultGroupName) ?>"
                                        >+</button>
                                        <form method="post" class="task-group-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_vault_group">
                                            <input type="hidden" name="group_name" value="<?= e((string) $vaultGroupName) ?>">
                                            <button
                                                type="submit"
                                                class="task-group-delete"
                                                aria-label="Excluir grupo do cofre <?= e((string) $vaultGroupName) ?>"
                                            ><span aria-hidden="true">&#10005;</span></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$vaultGroupCanAccess): ?>
                                        <span class="task-group-readonly-tag">Somente leitura</span>
                                    <?php endif; ?>
                                    <span class="task-group-count"><?= e((string) count($groupVaultEntries)) ?></span>
                                </div>
                            </header>

                            <div class="vault-group-rows" data-vault-group-rows>
                                <?php if (!$groupVaultEntries): ?>
                                    <div class="task-group-empty-row">
                                        <?php if ($vaultGroupCanAccess): ?>
                                            <button
                                                type="button"
                                                class="task-group-empty-add"
                                                data-open-vault-entry-modal
                                                data-create-group="<?= e((string) $vaultGroupName) ?>"
                                                aria-label="Adicionar item no grupo <?= e((string) $vaultGroupName) ?>"
                                            >+</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($groupVaultEntries as $vaultEntry): ?>
                                    <?php
                                    $vaultEntryId = (int) ($vaultEntry['id'] ?? 0);
                                    $vaultLabel = (string) ($vaultEntry['label'] ?? '');
                                    $vaultLogin = (string) ($vaultEntry['login_value'] ?? '');
                                    $vaultPassword = (string) ($vaultEntry['password_value'] ?? '');
                                    $vaultPasswordUnavailable = !empty($vaultEntry['password_unavailable']);
                                    $vaultCanUsePassword = !$vaultPasswordUnavailable && $vaultPassword !== '';
                                    $vaultGroupValue = (string) ($vaultEntry['group_name'] ?? $vaultGroupName);
                                    ?>
                                    <article
                                        class="vault-entry-row"
                                        data-vault-entry
                                        data-entry-id="<?= e((string) $vaultEntryId) ?>"
                                        data-entry-label="<?= e($vaultLabel) ?>"
                                        data-entry-login="<?= e($vaultLogin) ?>"
                                        data-entry-password="<?= e($vaultPassword) ?>"
                                        data-entry-password-unavailable="<?= $vaultPasswordUnavailable ? '1' : '0' ?>"
                                        data-entry-group="<?= e($vaultGroupValue) ?>"
                                    >
                                        <form method="post" class="vault-entry-name-form" data-vault-entry-name-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="rename_vault_entry_label">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $vaultEntryId) ?>">

                                            <div class="vault-entry-line">
                                                <div class="vault-entry-name">
                                                    <input
                                                        type="text"
                                                        name="label"
                                                        maxlength="120"
                                                        value="<?= e($vaultLabel) ?>"
                                                        class="task-title-input vault-entry-title-input"
                                                        aria-label="Nome do dado de acesso"
                                                        data-vault-entry-label-input
                                                        <?= $vaultGroupCanAccess ? '' : 'readonly' ?>
                                                        required
                                                    >
                                                </div>

                                                <div class="vault-entry-value">
                                                    <span class="vault-entry-value-text"><?= $vaultLogin !== '' ? e($vaultLogin) : '-' ?></span>
                                                    <button
                                                        type="button"
                                                        class="vault-icon-button"
                                                        data-vault-copy="<?= e($vaultLogin) ?>"
                                                        aria-label="Copiar login"
                                                        <?= $vaultLogin === '' ? 'disabled' : '' ?>
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <rect x="9" y="9" width="10" height="10" rx="2"></rect>
                                                            <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div
                                                    class="vault-entry-value vault-entry-password"
                                                    data-vault-password-cell
                                                    data-password-value="<?= e($vaultPassword) ?>"
                                                    data-visible="false"
                                                >
                                                    <span class="vault-entry-value-text" data-vault-password-text><?= $vaultPasswordUnavailable ? 'Indisponivel' : ($vaultCanUsePassword ? '********' : '-') ?></span>
                                                    <button
                                                        type="button"
                                                        class="vault-icon-button"
                                                        data-vault-toggle-password
                                                        aria-label="Mostrar senha"
                                                        <?= !$vaultCanUsePassword ? 'disabled' : '' ?>
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"></path>
                                                            <circle cx="12" cy="12" r="3"></circle>
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="vault-icon-button"
                                                        data-vault-copy="<?= e($vaultPassword) ?>"
                                                        aria-label="Copiar senha"
                                                        <?= !$vaultCanUsePassword ? 'disabled' : '' ?>
                                                    >
                                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                                            <rect x="9" y="9" width="10" height="10" rx="2"></rect>
                                                            <path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"></path>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div class="vault-entry-tools">
                                                    <?php if ($vaultGroupCanAccess): ?>
                                                        <button
                                                            type="button"
                                                            class="vault-icon-button"
                                                            data-open-vault-edit-modal
                                                            aria-label="Editar dado de acesso"
                                                        >
                                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                                <path d="M4 20h4l10-10-4-4L4 16v4Z"></path>
                                                                <path d="m12 6 4 4"></path>
                                                            </svg>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="vault-entry-delete-button"
                                                            data-vault-delete-entry
                                                            data-delete-form-id="delete-vault-entry-<?= e((string) $vaultEntryId) ?>"
                                                            aria-label="Excluir dado de acesso"
                                                        >
                                                            <span aria-hidden="true">&#10005;</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </form>

                                        <form method="post" id="delete-vault-entry-<?= e((string) $vaultEntryId) ?>" class="vault-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_vault_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $vaultEntryId) ?>">
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

