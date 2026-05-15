            <?php
            $renderAccountingMoney = static function (string $amountLabel, string $extraClass = ''): string {
                $normalized = trim($amountLabel);
                if ($normalized === '') {
                    $normalized = 'R$ 0,00';
                }

                $className = trim('accounting-money ' . $extraClass);
                if (preg_match('/^(-?\s*R\$)?\s*([\d\.]+)(,\d{2})$/u', $normalized, $matches)) {
                    $prefix = trim((string) ($matches[1] ?? ''));
                    $major = (string) ($matches[2] ?? '0');
                    $minor = (string) ($matches[3] ?? ',00');

                    return sprintf(
                        '<span class="%s">%s<span class="accounting-money-major">%s</span><span class="accounting-money-minor">%s</span></span>',
                        e($className),
                        $prefix !== ''
                            ? '<span class="accounting-money-prefix">' . e($prefix) . '</span>'
                            : '',
                        e($major),
                        e($minor)
                    );
                }

                return '<span class="' . e($className) . '">' . e($normalized) . '</span>';
            };
            ?>
            <div class="accounting-sheet">
                <div class="accounting-columns">
                    <section class="accounting-card is-expense-card<?= empty($accountingExpenseEntries) ? ' is-empty' : '' ?>">
                        <header class="accounting-card-head">
                            <div class="accounting-card-head-text">
                                <h3>Contas</h3>
                                <p>Despesas do mês</p>
                            </div>
                            <span><?= e(appItemCountLabel(count($accountingExpenseEntries))) ?></span>
                        </header>

                        <div class="accounting-entries">
                            <?php if (empty($accountingExpenseEntries)): ?>
                                <div class="accounting-empty">Nenhuma conta cadastrada neste mês.</div>
                            <?php else: ?>
                                <?php foreach ($accountingExpenseEntries as $accountingEntry): ?>
                                    <?php
                                    $accountingEntryId = (int) ($accountingEntry['id'] ?? 0);
                                    $accountingEntryLabel = (string) ($accountingEntry['label'] ?? '');
                                    $accountingEntryAmountInput = (string) ($accountingEntry['amount_input'] ?? '0,00');
                                    $accountingEntryTotalAmountInput = (string) ($accountingEntry['total_amount_input'] ?? $accountingEntryAmountInput);
                                    $accountingEntryIsSettled = ((int) ($accountingEntry['is_settled'] ?? 0)) === 1;
                                    $accountingEntryIsInstallment = ((int) ($accountingEntry['is_installment'] ?? 0)) === 1;
                                    $accountingEntryInstallmentProgress = (string) ($accountingEntry['installment_progress'] ?? '');
                                    $accountingEntryInstallmentBadge = $accountingEntryInstallmentProgress !== ''
                                        ? ('Parcela ' . $accountingEntryInstallmentProgress)
                                        : 'Parcela';
                                    $accountingEntryIsCarried = ((int) ($accountingEntry['is_carried'] ?? 0)) === 1;
                                    $accountingEntrySourceDueId = (int) ($accountingEntry['source_due_entry_id'] ?? 0);
                                    $accountingEntryIsMonthlyDue = $accountingEntrySourceDueId > 0;
                                    $accountingEntryIsMonthlyGoal = ((int) ($accountingEntry['is_monthly_goal'] ?? 0)) === 1;
                                    $accountingEntryMonthlyDay = normalizeDueMonthlyDay($accountingEntry['source_due_monthly_day'] ?? null);
                                    $accountingEntryDueDateDisplay = (string) ($accountingEntry['due_date_display'] ?? '');
                                    $accountingEntryGoalPaymentInput = (string) ($accountingEntry['goal_payment_input'] ?? '0,00');
                                    $accountingEntryGoalPaymentDisplay = (string) ($accountingEntry['goal_payment_display'] ?? 'R$ 0,00');
                                    $accountingEntryGoalTotalDisplay = (string) ($accountingEntry['total_amount_display'] ?? $accountingEntryAmountInput);
                                    $accountingEntryGoalPaymentHistory = is_array($accountingEntry['goal_payment_history'] ?? null)
                                        ? $accountingEntry['goal_payment_history']
                                        : [];
                                    $accountingEntryGoalPaidCents = max(0, (int) ($accountingEntry['paid_amount_cents'] ?? 0));
                                    $accountingEntryGoalTotalCents = max(0, (int) ($accountingEntry['total_amount_cents'] ?? 0));
                                    $accountingEntryGoalRemainingCents = max(0, $accountingEntryGoalTotalCents - $accountingEntryGoalPaidCents);
                                    $accountingEntryGoalPaymentCompactDisplay = dueAmountCompactLabelFromCents($accountingEntryGoalPaidCents, true);
                                    $accountingEntryGoalTotalCompactDisplay = dueAmountCompactLabelFromCents($accountingEntryGoalTotalCents, true);
                                    $accountingEntryGoalRemainingDisplay = dueAmountLabelFromCents($accountingEntryGoalRemainingCents);
                                    $accountingEntryGoalIsComplete = $accountingEntryGoalTotalCents > 0
                                        && $accountingEntryGoalPaidCents >= $accountingEntryGoalTotalCents;
                                    $accountingEntryGoalProgressPercent = $accountingEntryGoalTotalCents > 0
                                        ? min(100, max(0, ($accountingEntryGoalPaidCents / $accountingEntryGoalTotalCents) * 100))
                                        : 0;
                                    $accountingEntryGoalProgressWidth = number_format($accountingEntryGoalProgressPercent, 2, '.', '');
                                    $accountingEntryMonthlyBadge = $accountingEntryIsMonthlyGoal
                                        ? 'Saldo a quitar'
                                        : ($accountingEntryIsMonthlyDue && $accountingEntryMonthlyDay !== null
                                            ? ('Mensal - ' . str_pad((string) $accountingEntryMonthlyDay, 2, '0', STR_PAD_LEFT))
                                            : '');
                                    $accountingEntryIsOverdue = ((int) ($accountingEntry['is_overdue'] ?? 0)) === 1;
                                    $accountingEntryOverdueDays = max(0, (int) ($accountingEntry['overdue_days'] ?? 0));
                                    $accountingEntryShowPendingBadge = $accountingEntryIsCarried
                                        && !$accountingEntryIsSettled
                                        && !$accountingEntryIsInstallment
                                        && !$accountingEntryIsMonthlyGoal;
                                    ?>
                                    <div class="accounting-entry-row<?= $accountingEntryIsMonthlyGoal ? ' is-goal-entry' : '' ?>">
                                        <button
                                            type="button"
                                            class="accounting-entry-summary"
                                            data-accounting-entry-toggle
                                            aria-expanded="false"
                                        >
                                            <span class="accounting-entry-summary-main">
                                                <span class="accounting-entry-summary-head">
                                                    <span class="accounting-entry-summary-title" title="<?= e($accountingEntryLabel) ?>"><?= e($accountingEntryLabel) ?></span>
                                                    <?php if ($accountingEntryIsMonthlyGoal): ?>
                                                        <span
                                                            class="accounting-entry-goal-progress<?= $accountingEntryGoalIsComplete ? ' is-complete' : '' ?>"
                                                            aria-label="Pago <?= e($accountingEntryGoalPaymentDisplay) ?> de <?= e($accountingEntryGoalTotalDisplay) ?>"
                                                        >
                                                            <?php if ($accountingEntryGoalIsComplete): ?>
                                                                <span class="accounting-entry-goal-progress-status" aria-hidden="true">
                                                                    <svg viewBox="0 0 16 16" focusable="false">
                                                                        <path d="M3.5 8.4 6.5 11.2 12.5 4.8"></path>
                                                                    </svg>
                                                                </span>
                                                            <?php endif; ?>
                                                            <span class="accounting-entry-goal-progress-bar">
                                                                <span class="accounting-entry-goal-progress-fill" style="width: <?= e($accountingEntryGoalProgressWidth) ?>%"></span>
                                                                <span class="accounting-entry-goal-progress-values">
                                                                    <span class="accounting-entry-goal-progress-paid"><?= e($accountingEntryGoalPaymentCompactDisplay) ?></span>
                                                                    <span class="accounting-entry-goal-progress-separator">/</span>
                                                                    <strong class="accounting-entry-goal-progress-total"><?= e($accountingEntryGoalTotalCompactDisplay) ?></strong>
                                                                </span>
                                                            </span>
                                                        </span>
                                                    <?php elseif ($accountingEntryMonthlyBadge !== '' || $accountingEntryIsInstallment || $accountingEntryShowPendingBadge || $accountingEntryIsOverdue): ?>
                                                        <span class="accounting-entry-summary-meta">
                                                            <?php if ($accountingEntryMonthlyBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-monthly"><?= e($accountingEntryMonthlyBadge) ?></span>
                                                            <?php elseif ($accountingEntryIsInstallment): ?>
                                                                <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($accountingEntryShowPendingBadge): ?>
                                                                <span class="accounting-entry-badge is-pending">Pendente</span>
                                                            <?php endif; ?>
                                                            <?php if ($accountingEntryIsOverdue): ?>
                                                                <span
                                                                    class="accounting-entry-badge is-overdue"
                                                                    title="Conta em atraso h&aacute; <?= e((string) $accountingEntryOverdueDays) ?> dia(s)."
                                                                >Atrasado</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </span>
                                            <?php if ($accountingEntryIsMonthlyGoal): ?>
                                                <span
                                                    class="accounting-entry-summary-amount accounting-entry-summary-amount-goal"
                                                    aria-label="Pago at&eacute; agora <?= e($accountingEntryGoalPaymentDisplay) ?>"
                                                >
                                                    <?= $renderAccountingMoney($accountingEntryGoalPaymentDisplay) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="accounting-entry-summary-amount"><?= $renderAccountingMoney($accountingEntryAmountInput) ?></span>
                                            <?php endif; ?>
                                        </button>
                                        <?php if ($accountingEntryIsMonthlyGoal): ?>
                                            <div class="accounting-entry-goal-payment-panel">
                                                <button
                                                    type="button"
                                                    class="accounting-entry-goal-payment-trigger"
                                                    data-accounting-goal-payment-toggle
                                                    aria-label="Abrir lançamentos"
                                                    title="Abrir lançamentos"
                                                >
                                                    <span class="accounting-entry-goal-payment-trigger-icon" aria-hidden="true">+</span>
                                                </button>
                                            </div>
                                            <div class="accounting-entry-goal-payment-drawer" data-accounting-goal-payment-drawer hidden>
                                                <div class="accounting-entry-goal-payment-drawer-head">
                                                    <div class="accounting-entry-goal-payment-drawer-copy">
                                                        <strong>Lançamentos</strong>
                                                        <span>Os lançamentos abaixo compõem o valor já pago.</span>
                                                    </div>
                                                    <div class="accounting-entry-goal-payment-drawer-tools">
                                                        <div
                                                            class="accounting-entry-goal-payment-remaining<?= $accountingEntryGoalIsComplete ? ' is-complete' : '' ?>"
                                                            aria-label="Falta <?= e($accountingEntryGoalRemainingDisplay) ?> para quitar o saldo"
                                                        >
                                                            <span>Falta</span>
                                                            <strong><?= $renderAccountingMoney($accountingEntryGoalRemainingDisplay) ?></strong>
                                                        </div>
                                                        <button
                                                            type="button"
                                                            class="accounting-entry-goal-payment-close"
                                                            data-accounting-goal-payment-close
                                                            aria-label="Fechar lançamentos"
                                                        >
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                </div>
                                                <form method="post" class="accounting-entry-goal-payment-add-form" data-accounting-goal-payment-add-form autocomplete="off">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                    <input type="hidden" name="action" value="add_accounting_goal_payment">
                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                    <input
                                                        type="text"
                                                        name="payment_amount_value"
                                                        value=""
                                                        class="accounting-input accounting-input-amount"
                                                        inputmode="numeric"
                                                        placeholder="0,00"
                                                        autocomplete="off"
                                                        required
                                                    >
                                                    <div class="accounting-entry-goal-payment-actions">
                                                        <button type="submit" class="btn btn-mini">Adicionar valor</button>
                                                    </div>
                                                </form>
                                                <div class="accounting-entry-goal-payment-history">
                                                    <?php if ($accountingEntryGoalPaymentHistory): ?>
                                                        <?php foreach ($accountingEntryGoalPaymentHistory as $goalPaymentHistoryItem): ?>
                                                            <?php
                                                            $goalPaymentHistoryId = (int) ($goalPaymentHistoryItem['id'] ?? 0);
                                                            $goalPaymentHistoryAmountDisplay = (string) ($goalPaymentHistoryItem['amount_display'] ?? 'R$ 0,00');
                                                            $goalPaymentHistoryCreatedAt = (string) ($goalPaymentHistoryItem['created_at'] ?? '');
                                                            $goalPaymentHistoryCreatedAtDisplay = (string) ($goalPaymentHistoryItem['created_at_display'] ?? '');
                                                            ?>
                                                            <div class="accounting-entry-goal-payment-item">
                                                                <div class="accounting-entry-goal-payment-item-main">
                                                                    <strong><?= $renderAccountingMoney($goalPaymentHistoryAmountDisplay) ?></strong>
                                                                    <time datetime="<?= e($goalPaymentHistoryCreatedAt) ?>"><?= e($goalPaymentHistoryCreatedAtDisplay) ?></time>
                                                                </div>
                                                                <form method="post" class="accounting-entry-goal-payment-delete-form" data-accounting-goal-payment-delete-form>
                                                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                                    <input type="hidden" name="action" value="delete_accounting_goal_payment">
                                                                    <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                                    <input type="hidden" name="payment_id" value="<?= e((string) $goalPaymentHistoryId) ?>">
                                                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                                    <button
                                                                        type="submit"
                                                                        class="accounting-entry-goal-payment-delete"
                                                                        aria-label="Remover lançamento de <?= e($goalPaymentHistoryAmountDisplay) ?>"
                                                                    >
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <p class="accounting-entry-goal-payment-empty">Nenhum lançamento registrado.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <form method="post" class="accounting-entry-quick-status-form" data-accounting-form>
                                                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                                <input type="hidden" name="action" value="update_accounting_entry">
                                                <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                                <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                                <input type="hidden" name="label" value="<?= e($accountingEntryLabel) ?>">
                                                <input type="hidden" name="amount_value" value="<?= e($accountingEntryAmountInput) ?>">
                                                <input type="hidden" name="is_installment" value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>">
                                                <input type="hidden" name="installment_progress" value="<?= e($accountingEntryInstallmentProgress) ?>">
                                                <input type="hidden" name="total_amount_value" value="<?= e($accountingEntryTotalAmountInput) ?>">
                                                <input type="hidden" name="monthly_mode" value="uniform">
                                                <input type="hidden" name="monthly_day" value="<?= $accountingEntryMonthlyDay !== null ? e((string) $accountingEntryMonthlyDay) : '' ?>">
                                                <label class="accounting-check">
                                                    <input type="checkbox" name="is_settled" value="1" <?= $accountingEntryIsSettled ? 'checked' : '' ?>>
                                                    <span>Pago</span>
                                                </label>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" class="accounting-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <button type="submit" class="vault-entry-delete-button" aria-label="Excluir conta">
                                                <span aria-hidden="true">&#10005;</span>
                                            </button>
                                        </form>
                                        <form method="post" class="accounting-entry-form accounting-entry-editor-form" data-accounting-form hidden autocomplete="off">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <input
                                                type="text"
                                                name="label"
                                                value="<?= e($accountingEntryLabel) ?>"
                                                maxlength="120"
                                                class="accounting-input accounting-input-label"
                                                placeholder="Nome da conta"
                                                autocomplete="off"
                                                required
                                            >
                                            <input
                                                type="text"
                                                name="amount_value"
                                                value="<?= e($accountingEntryIsMonthlyGoal ? $accountingEntryTotalAmountInput : $accountingEntryAmountInput) ?>"
                                                class="accounting-input accounting-input-amount"
                                                inputmode="numeric"
                                                placeholder="0,00"
                                                autocomplete="off"
                                                required
                                                data-accounting-primary-amount
                                                <?= $accountingEntryIsInstallment ? 'readonly' : '' ?>
                                            >
                                            <?php if ($accountingEntryIsMonthlyGoal || $accountingEntryMonthlyBadge !== '' || $accountingEntryIsInstallment || $accountingEntryShowPendingBadge): ?>
                                                <div class="accounting-entry-meta">
                                                    <?php if ($accountingEntryIsMonthlyGoal): ?>
                                                        <span class="accounting-entry-goal-status">Os pagamentos parciais são lançados no botão +.</span>
                                                    <?php elseif ($accountingEntryMonthlyBadge !== ''): ?>
                                                        <label class="accounting-entry-edit-control is-monthly">
                                                            <span>Mensal -</span>
                                                            <select name="monthly_day" class="accounting-installment-select" aria-label="Dia do vencimento mensal">
                                                                <?php for ($monthlyDayOption = 1; $monthlyDayOption <= 31; $monthlyDayOption++): ?>
                                                                    <option value="<?= e((string) $monthlyDayOption) ?>" <?= $monthlyDayOption === $accountingEntryMonthlyDay ? 'selected' : '' ?>>
                                                                        <?= e(str_pad((string) $monthlyDayOption, 2, '0', STR_PAD_LEFT)) ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </label>
                                                    <?php elseif ($accountingEntryIsInstallment): ?>
                                                        <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($accountingEntryShowPendingBadge): ?>
                                                        <span class="accounting-entry-badge is-pending">Pendente</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="accounting-entry-status">
                                                <?php if (!$accountingEntryIsMonthlyGoal): ?>
                                                    <label class="accounting-check">
                                                        <input type="checkbox" name="is_settled" value="1" <?= $accountingEntryIsSettled ? 'checked' : '' ?>>
                                                        <span>Pago</span>
                                                    </label>
                                                <?php endif; ?>
                                            </div>
                                            <div class="accounting-entry-editor-actions">
                                                <button type="submit" class="btn btn-mini">Salvar</button>
                                                <button type="button" class="btn btn-mini btn-ghost" data-accounting-entry-cancel>Cancelar</button>
                                            </div>
                                            <input
                                                type="hidden"
                                                name="is_installment"
                                                value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="installment_progress"
                                                value="<?= e($accountingEntryInstallmentProgress) ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="total_amount_value"
                                                value="<?= e($accountingEntryTotalAmountInput) ?>"
                                            >
                                            <input type="hidden" name="is_monthly_due" value="<?= ($accountingEntryIsMonthlyDue || $accountingEntryIsMonthlyGoal) ? '1' : '0' ?>">
                                            <input type="hidden" name="monthly_mode" value="<?= $accountingEntryIsMonthlyGoal ? 'goal' : 'uniform' ?>">
                                            <?php if (!$accountingEntryIsMonthlyDue): ?>
                                                <input type="hidden" name="monthly_day" value="">
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="accounting-card-footer">
                            <details class="accounting-create-toggle">
                                <summary class="accounting-create-trigger">+ Adicionar</summary>
                                <form method="post" class="accounting-create-form" data-accounting-form autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="create_accounting_entry">
                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                    <input type="hidden" name="entry_type" value="expense">
                                    <input
                                        type="text"
                                        name="label"
                                        maxlength="120"
                                        class="accounting-input accounting-input-label"
                                        placeholder="Nova conta"
                                        autocomplete="off"
                                        required
                                    >
                                    <input
                                        type="text"
                                        name="amount_value"
                                        class="accounting-input accounting-input-amount"
                                        inputmode="numeric"
                                        placeholder="0,00"
                                        autocomplete="off"
                                        required
                                        data-accounting-primary-amount
                                    >
                                    <div class="accounting-create-footer">
                                        <div class="accounting-create-meta">
                                            <div class="accounting-entry-options">
                                                <select
                                                    name="accounting_type_choice"
                                                    class="accounting-installment-select accounting-entry-type-select"
                                                    aria-label="Tipo de conta"
                                                    data-accounting-type-select
                                                >
                                                    <option value="single">&Uacute;nica</option>
                                                    <option value="installment">Parcelada</option>
                                                    <option value="monthly">Mensal</option>
                                                    <option value="goal">Saldo a quitar</option>
                                                </select>
                                                <input
                                                    type="checkbox"
                                                    name="is_installment"
                                                    value="1"
                                                    class="accounting-hidden-toggle"
                                                    data-accounting-installment-toggle
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                >
                                                <input
                                                    type="checkbox"
                                                    name="is_monthly_due"
                                                    value="1"
                                                    class="accounting-hidden-toggle"
                                                    data-accounting-monthly-toggle
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                >
                                                <div class="accounting-installment-fields" data-accounting-installment-fields hidden>
                                                    <div class="accounting-installment-progress-picker">
                                                        <select
                                                            name="installment_number"
                                                            class="accounting-installment-select"
                                                            aria-label="Parcela atual"
                                                            data-accounting-installment-number
                                                            disabled
                                                        >
                                                            <?php for ($installmentNumberOption = 1; $installmentNumberOption <= 60; $installmentNumberOption++): ?>
                                                                <option value="<?= e((string) $installmentNumberOption) ?>"><?= e((string) $installmentNumberOption) ?></option>
                                                            <?php endfor; ?>
                                                        </select>
                                                        <span class="accounting-installment-separator">/</span>
                                                        <select
                                                            name="installment_total"
                                                            class="accounting-installment-select"
                                                            aria-label="Total de parcelas"
                                                            data-accounting-installment-total-count
                                                            disabled
                                                        >
                                                            <?php for ($installmentTotalOption = 2; $installmentTotalOption <= 60; $installmentTotalOption++): ?>
                                                                <option value="<?= e((string) $installmentTotalOption) ?>" <?= $installmentTotalOption === 2 ? 'selected' : '' ?>>
                                                                    <?= e((string) $installmentTotalOption) ?>
                                                                </option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                    <input type="hidden" name="installment_progress" value="" data-accounting-installment-progress>
                                                    <input
                                                        type="text"
                                                        name="total_amount_value"
                                                        class="accounting-input accounting-input-amount accounting-input-installment-total"
                                                        inputmode="numeric"
                                                        placeholder="Valor total"
                                                        aria-label="Valor total"
                                                        data-accounting-installment-total-amount
                                                        disabled
                                                    >
                                                </div>
                                                <input
                                                    type="hidden"
                                                    name="monthly_mode"
                                                    value="uniform"
                                                    data-accounting-monthly-mode
                                                >
                                                <div class="accounting-monthly-fields" data-accounting-monthly-fields hidden>
                                                    <div class="accounting-monthly-day-field" data-accounting-monthly-day-field>
                                                        <span class="accounting-entry-inline-label">Vencimento</span>
                                                        <select
                                                            name="monthly_day"
                                                            class="accounting-installment-select accounting-monthly-day-select"
                                                            aria-label="Dia do vencimento mensal"
                                                            data-accounting-monthly-day
                                                            disabled
                                                        >
                                                            <?php for ($monthlyDayOption = 1; $monthlyDayOption <= 31; $monthlyDayOption++): ?>
                                                                <option value="<?= e((string) $monthlyDayOption) ?>" <?= $monthlyDayOption === 1 ? 'selected' : '' ?>>
                                                                    <?= e(str_pad((string) $monthlyDayOption, 2, '0', STR_PAD_LEFT)) ?>
                                                                </option>
                                                            <?php endfor; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accounting-create-actions">
                                            <button type="submit" class="btn btn-mini">Adicionar</button>
                                            <button type="button" class="btn btn-mini btn-ghost" data-accounting-create-cancel>Cancelar</button>
                                        </div>
                                    </div>
                                </form>
                            </details>

                            <?php
                            $accountingExpenseTotalCents = max(0, (int) ($accountingSummary['expense_total_cents'] ?? 0));
                            $accountingExpensePaidCents = max(0, (int) ($accountingSummary['expense_paid_cents'] ?? 0));
                            $accountingHideExpensePaidTotal = $accountingExpenseTotalCents > 0
                                && $accountingExpensePaidCents >= $accountingExpenseTotalCents;
                            ?>
                            <dl class="accounting-totals is-single">
                                <div class="is-expense-total">
                                    <dt>Total</dt>
                                    <dd
                                        class="accounting-total-pair"
                                        aria-label="<?= $accountingHideExpensePaidTotal
                                            ? ('Total pago ' . e((string) ($accountingSummary['expense_total_display'] ?? 'R$ 0,00')))
                                            : ('Pago ' . e((string) ($accountingSummary['expense_paid_display'] ?? 'R$ 0,00')) . ' de ' . e((string) ($accountingSummary['expense_total_display'] ?? 'R$ 0,00'))) ?>"
                                    >
                                        <?php if (!$accountingHideExpensePaidTotal): ?>
                                            <span class="accounting-total-secondary"><?= $renderAccountingMoney((string) ($accountingSummary['expense_paid_display'] ?? 'R$ 0,00')) ?></span>
                                            <span class="accounting-total-separator">/</span>
                                        <?php endif; ?>
                                        <strong class="accounting-total-main"><?= $renderAccountingMoney((string) ($accountingSummary['expense_total_display'] ?? 'R$ 0,00')) ?></strong>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    <section class="accounting-card is-income-card<?= empty($accountingIncomeEntries) ? ' is-empty' : '' ?>">
                        <header class="accounting-card-head">
                            <div class="accounting-card-head-text">
                                <h3>Entradas</h3>
                                <p>Receitas do mês</p>
                            </div>
                            <span><?= e(appItemCountLabel(count($accountingIncomeEntries))) ?></span>
                        </header>

                        <div class="accounting-entries">
                            <?php if (empty($accountingIncomeEntries)): ?>
                                <div class="accounting-empty">Nenhuma entrada cadastrada neste mês.</div>
                            <?php else: ?>
                                <?php foreach ($accountingIncomeEntries as $accountingEntry): ?>
                                    <?php
                                    $accountingEntryId = (int) ($accountingEntry['id'] ?? 0);
                                    $accountingEntryLabel = (string) ($accountingEntry['label'] ?? '');
                                    $accountingEntryAmountInput = (string) ($accountingEntry['amount_input'] ?? '0,00');
                                    $accountingEntryTotalAmountInput = (string) ($accountingEntry['total_amount_input'] ?? $accountingEntryAmountInput);
                                    $accountingEntryIsSettled = ((int) ($accountingEntry['is_settled'] ?? 0)) === 1;
                                    $accountingEntryIsInstallment = ((int) ($accountingEntry['is_installment'] ?? 0)) === 1;
                                    $accountingEntryInstallmentProgress = (string) ($accountingEntry['installment_progress'] ?? '');
                                    $accountingEntryInstallmentBadge = $accountingEntryInstallmentProgress !== ''
                                        ? ('Parcela ' . $accountingEntryInstallmentProgress)
                                        : 'Parcela';
                                    $accountingEntryIsMonthly = ((int) ($accountingEntry['is_monthly'] ?? 0)) === 1;
                                    $accountingEntryMonthlyDay = normalizeDueMonthlyDay($accountingEntry['monthly_day'] ?? null);
                                    $accountingEntryMonthlyBadge = $accountingEntryIsMonthly && $accountingEntryMonthlyDay !== null
                                        ? ('Mensal - ' . str_pad((string) $accountingEntryMonthlyDay, 2, '0', STR_PAD_LEFT))
                                        : '';
                                    ?>
                                    <div class="accounting-entry-row">
                                        <button
                                            type="button"
                                            class="accounting-entry-summary"
                                            data-accounting-entry-toggle
                                            aria-expanded="false"
                                        >
                                            <span class="accounting-entry-summary-main">
                                                <span class="accounting-entry-summary-head">
                                                    <?php if ($accountingEntryMonthlyBadge !== '' || $accountingEntryIsInstallment): ?>
                                                        <span class="accounting-entry-summary-meta">
                                                            <?php if ($accountingEntryMonthlyBadge !== ''): ?>
                                                                <span class="accounting-entry-badge is-monthly"><?= e($accountingEntryMonthlyBadge) ?></span>
                                                            <?php elseif ($accountingEntryIsInstallment): ?>
                                                                <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="accounting-entry-summary-title" title="<?= e($accountingEntryLabel) ?>"><?= e($accountingEntryLabel) ?></span>
                                                </span>
                                            </span>
                                            <span class="accounting-entry-summary-amount"><?= $renderAccountingMoney($accountingEntryAmountInput) ?></span>
                                        </button>
                                        <form method="post" class="accounting-entry-quick-status-form" data-accounting-form>
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <input type="hidden" name="label" value="<?= e($accountingEntryLabel) ?>">
                                            <input type="hidden" name="amount_value" value="<?= e($accountingEntryAmountInput) ?>">
                                            <input type="hidden" name="is_installment" value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>">
                                            <input type="hidden" name="installment_progress" value="<?= e($accountingEntryInstallmentProgress) ?>">
                                            <input type="hidden" name="total_amount_value" value="<?= e($accountingEntryTotalAmountInput) ?>">
                                            <input type="hidden" name="is_monthly_due" value="<?= $accountingEntryIsMonthly ? '1' : '0' ?>">
                                            <input type="hidden" name="monthly_day" value="<?= $accountingEntryMonthlyDay !== null ? e((string) $accountingEntryMonthlyDay) : '' ?>">
                                            <label class="accounting-check">
                                                <input type="checkbox" name="is_settled" value="1" <?= $accountingEntryIsSettled ? 'checked' : '' ?>>
                                                <span>Recebido</span>
                                            </label>
                                        </form>
                                        <form method="post" class="accounting-entry-delete-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <button type="submit" class="vault-entry-delete-button" aria-label="Excluir entrada">
                                                <span aria-hidden="true">&#10005;</span>
                                            </button>
                                        </form>
                                        <form method="post" class="accounting-entry-form accounting-entry-editor-form" data-accounting-form hidden autocomplete="off">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="update_accounting_entry">
                                            <input type="hidden" name="entry_id" value="<?= e((string) $accountingEntryId) ?>">
                                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                            <input
                                                type="text"
                                                name="label"
                                                value="<?= e($accountingEntryLabel) ?>"
                                                maxlength="120"
                                                class="accounting-input accounting-input-label"
                                                placeholder="Nome da entrada"
                                                autocomplete="off"
                                                required
                                            >
                                            <input
                                                type="text"
                                                name="amount_value"
                                                value="<?= e($accountingEntryAmountInput) ?>"
                                                class="accounting-input accounting-input-amount"
                                                inputmode="numeric"
                                                placeholder="0,00"
                                                autocomplete="off"
                                                required
                                                data-accounting-primary-amount
                                                <?= $accountingEntryIsInstallment ? 'readonly' : '' ?>
                                            >
                                            <?php if ($accountingEntryMonthlyBadge !== '' || $accountingEntryIsInstallment): ?>
                                                <div class="accounting-entry-meta">
                                                    <?php if ($accountingEntryMonthlyBadge !== ''): ?>
                                                        <label class="accounting-entry-edit-control is-monthly">
                                                            <span>Mensal -</span>
                                                            <select name="monthly_day" class="accounting-installment-select" aria-label="Dia do recebimento mensal">
                                                                <?php for ($monthlyDayOption = 1; $monthlyDayOption <= 31; $monthlyDayOption++): ?>
                                                                    <option value="<?= e((string) $monthlyDayOption) ?>" <?= $monthlyDayOption === $accountingEntryMonthlyDay ? 'selected' : '' ?>>
                                                                        <?= e(str_pad((string) $monthlyDayOption, 2, '0', STR_PAD_LEFT)) ?>
                                                                    </option>
                                                                <?php endfor; ?>
                                                            </select>
                                                        </label>
                                                    <?php elseif ($accountingEntryIsInstallment): ?>
                                                        <span class="accounting-entry-badge is-installment"><?= e($accountingEntryInstallmentBadge) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="accounting-entry-status">
                                                <label class="accounting-check">
                                                    <input type="checkbox" name="is_settled" value="1" <?= $accountingEntryIsSettled ? 'checked' : '' ?>>
                                                    <span>Recebido</span>
                                                </label>
                                            </div>
                                            <div class="accounting-entry-editor-actions">
                                                <button type="submit" class="btn btn-mini">Salvar</button>
                                                <button type="button" class="btn btn-mini btn-ghost" data-accounting-entry-cancel>Cancelar</button>
                                            </div>
                                            <input
                                                type="hidden"
                                                name="is_installment"
                                                value="<?= $accountingEntryIsInstallment ? '1' : '0' ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="installment_progress"
                                                value="<?= e($accountingEntryInstallmentProgress) ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="total_amount_value"
                                                value="<?= e($accountingEntryTotalAmountInput) ?>"
                                            >
                                            <input type="hidden" name="is_monthly_due" value="<?= $accountingEntryIsMonthly ? '1' : '0' ?>">
                                            <?php if (!$accountingEntryIsMonthly): ?>
                                                <input type="hidden" name="monthly_day" value="">
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="accounting-card-footer">
                            <details class="accounting-create-toggle">
                                <summary class="accounting-create-trigger">+ Adicionar</summary>
                                <form method="post" class="accounting-create-form" data-accounting-form autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                                    <input type="hidden" name="action" value="create_accounting_entry">
                                    <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                                    <input type="hidden" name="entry_type" value="income">
                                    <input
                                        type="text"
                                        name="label"
                                        maxlength="120"
                                        class="accounting-input accounting-input-label"
                                        placeholder="Nova entrada"
                                        autocomplete="off"
                                        required
                                    >
                                    <input
                                        type="text"
                                        name="amount_value"
                                        class="accounting-input accounting-input-amount"
                                        inputmode="numeric"
                                        placeholder="0,00"
                                        autocomplete="off"
                                        required
                                        data-accounting-primary-amount
                                    >
                                    <div class="accounting-create-footer">
                                        <div class="accounting-create-meta">
                                            <div class="accounting-entry-options">
                                                <select
                                                    name="accounting_type_choice"
                                                    class="accounting-installment-select accounting-entry-type-select"
                                                    aria-label="Tipo de entrada"
                                                    data-accounting-type-select
                                                >
                                                    <option value="single">&Uacute;nica</option>
                                                    <option value="monthly">Mensal</option>
                                                </select>
                                                <input
                                                    type="checkbox"
                                                    name="is_installment"
                                                    value="1"
                                                    class="accounting-hidden-toggle"
                                                    data-accounting-installment-toggle
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                >
                                                <input
                                                    type="checkbox"
                                                    name="is_monthly_due"
                                                    value="1"
                                                    class="accounting-hidden-toggle"
                                                    data-accounting-monthly-toggle
                                                    tabindex="-1"
                                                    aria-hidden="true"
                                                >
                                                <div class="accounting-installment-fields" data-accounting-installment-fields hidden>
                                                    <select
                                                        name="installment_number"
                                                        class="accounting-installment-select"
                                                        aria-label="Parcela atual"
                                                        data-accounting-installment-number
                                                        disabled
                                                    >
                                                        <option value="1">1</option>
                                                    </select>
                                                    <select
                                                        name="installment_total"
                                                        class="accounting-installment-select"
                                                        aria-label="Total de parcelas"
                                                        data-accounting-installment-total-count
                                                        disabled
                                                    >
                                                        <option value="2">2</option>
                                                    </select>
                                                    <input type="hidden" name="installment_progress" value="" data-accounting-installment-progress disabled>
                                                    <input type="hidden" name="total_amount_value" value="" data-accounting-installment-total-amount disabled>
                                                </div>
                                                <div class="accounting-monthly-fields" data-accounting-monthly-fields hidden>
                                                    <span class="accounting-entry-inline-label">Vencimento</span>
                                                    <select
                                                        name="monthly_day"
                                                        class="accounting-installment-select accounting-monthly-day-select"
                                                        aria-label="Dia do recebimento mensal"
                                                        data-accounting-monthly-day
                                                        disabled
                                                    >
                                                        <?php for ($monthlyDayOption = 1; $monthlyDayOption <= 31; $monthlyDayOption++): ?>
                                                            <option value="<?= e((string) $monthlyDayOption) ?>" <?= $monthlyDayOption === 1 ? 'selected' : '' ?>>
                                                                <?= e(str_pad((string) $monthlyDayOption, 2, '0', STR_PAD_LEFT)) ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="accounting-create-actions">
                                            <button type="submit" class="btn btn-mini">Adicionar</button>
                                            <button type="button" class="btn btn-mini btn-ghost" data-accounting-create-cancel>Cancelar</button>
                                        </div>
                                    </div>
                                </form>
                            </details>

                            <?php
                            $accountingIncomeTotalCents = max(0, (int) ($accountingSummary['income_total_cents'] ?? 0));
                            $accountingIncomeReceivedCents = max(0, (int) ($accountingSummary['income_received_cents'] ?? 0));
                            $accountingHideIncomeReceivedTotal = $accountingIncomeTotalCents > 0
                                && $accountingIncomeReceivedCents >= $accountingIncomeTotalCents;
                            ?>
                            <dl class="accounting-totals is-single">
                                <div class="is-income-total">
                                    <dt>Total</dt>
                                    <dd
                                        class="accounting-total-pair"
                                        aria-label="<?= $accountingHideIncomeReceivedTotal
                                            ? ('Total recebido ' . e((string) ($accountingSummary['income_total_display'] ?? 'R$ 0,00')))
                                            : ('Recebido ' . e((string) ($accountingSummary['income_received_display'] ?? 'R$ 0,00')) . ' de ' . e((string) ($accountingSummary['income_total_display'] ?? 'R$ 0,00'))) ?>"
                                    >
                                        <?php if (!$accountingHideIncomeReceivedTotal): ?>
                                            <span class="accounting-total-secondary"><?= $renderAccountingMoney((string) ($accountingSummary['income_received_display'] ?? 'R$ 0,00')) ?></span>
                                            <span class="accounting-total-separator">/</span>
                                        <?php endif; ?>
                                        <strong class="accounting-total-main"><?= $renderAccountingMoney((string) ($accountingSummary['income_total_display'] ?? 'R$ 0,00')) ?></strong>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </section>
                </div>

                <section class="accounting-balance-card">
                    <?php
                    $accountingOpeningBalanceCents = (int) ($accountingSummary['opening_balance_cents'] ?? 0);
                    $accountingOpeningBalanceActionLabel = $accountingOpeningBalanceCents !== 0
                        ? 'Atualizar saldo atual'
                        : 'Informar saldo atual';
                    $accountingCurrentBalanceCents = (int) ($accountingSummary['current_balance_cents'] ?? 0);
                    $accountingCurrentBalanceClass = $accountingCurrentBalanceCents < 0
                        ? ' is-negative'
                        : ($accountingCurrentBalanceCents > 0 ? ' is-positive' : '');
                    $accountingFinalBalanceCents = (int) ($accountingSummary['final_balance_cents'] ?? 0);
                    $accountingFinalBalanceClass = $accountingFinalBalanceCents < 0
                        ? ' is-negative'
                        : ($accountingFinalBalanceCents > 0 ? ' is-positive' : '');
                    ?>
                    <div class="accounting-opening-balance-editor">
                        <button
                            type="button"
                            class="accounting-create-trigger accounting-opening-balance-trigger"
                            data-accounting-opening-balance-toggle
                            aria-expanded="false"
                        >
                            <?= e($accountingOpeningBalanceActionLabel) ?>
                        </button>
                        <form method="post" class="accounting-opening-balance-form" data-accounting-form hidden>
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="action" value="set_accounting_opening_balance">
                            <input type="hidden" name="period_key" value="<?= e($accountingPeriod) ?>">
                            <label class="accounting-opening-balance-field">
                                <span>Saldo atual da conta</span>
                                <input
                                    type="text"
                                    name="opening_balance_value"
                                    value="<?= e((string) ($accountingSummary['opening_balance_display'] ?? 'R$ 0,00')) ?>"
                                    class="accounting-input accounting-input-amount"
                                    inputmode="numeric"
                                    placeholder="0,00"
                                    data-accounting-allow-negative="1"
                                >
                            </label>
                            <div class="accounting-opening-balance-actions">
                                <button type="submit" class="btn btn-mini">Confirmar</button>
                                <button type="button" class="btn btn-mini btn-ghost" data-accounting-opening-balance-cancel>Cancelar</button>
                            </div>
                        </form>
                    </div>
                    <dl class="accounting-balance-values">
                        <div class="is-current<?= e($accountingCurrentBalanceClass) ?>">
                            <dt>Saldo atual</dt>
                            <dd><?= $renderAccountingMoney((string) ($accountingSummary['current_balance_display'] ?? 'R$ 0,00')) ?></dd>
                        </div>
                        <div class="is-final is-projected<?= e($accountingFinalBalanceClass) ?>">
                            <dt>Saldo projetado</dt>
                            <dd><?= $renderAccountingMoney((string) ($accountingSummary['final_balance_display'] ?? 'R$ 0,00')) ?></dd>
                        </div>
                    </dl>
                </section>
            </div>
