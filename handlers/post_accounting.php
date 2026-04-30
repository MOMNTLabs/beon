<?php
declare(strict_types=1);

function handleAccountingPostAction(PDO $pdo, string $action): bool
{
    switch ($action) {
            case 'set_accounting_opening_balance':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo nÃ£o encontrado.');
                }

                $periodKey = normalizeAccountingPeriodKey((string) ($_POST['period_key'] ?? ''));
                setWorkspaceAccountingOpeningBalance(
                    $pdo,
                    $workspaceId,
                    $periodKey,
                    $_POST['opening_balance_value'] ?? null,
                    (int) ($authUser['id'] ?? 0)
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Saldo inicial atualizado.',
                    ]);
                }

                flash('success', 'Saldo inicial atualizado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'create_accounting_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $periodKey = normalizeAccountingPeriodKey((string) ($_POST['period_key'] ?? ''));
                $entryType = normalizeAccountingEntryType((string) ($_POST['entry_type'] ?? 'expense'));
                $isSettled = array_key_exists('is_settled', $_POST) ? 1 : 0;
                $isInstallment = $entryType === 'expense' && ((string) ($_POST['is_installment'] ?? '0')) === '1' ? 1 : 0;
                $isMonthlyDue = $entryType === 'expense' && ((string) ($_POST['is_monthly_due'] ?? '0')) === '1' ? 1 : 0;

                if ($isMonthlyDue === 1) {
                    createWorkspaceAccountingMonthlyDue(
                        $pdo,
                        $workspaceId,
                        $periodKey,
                        (string) ($_POST['label'] ?? ''),
                        $_POST['amount_value'] ?? null,
                        $isSettled,
                        (int) ($authUser['id'] ?? 0),
                        $_POST['monthly_day'] ?? null
                    );
                } else {
                    createWorkspaceAccountingEntry(
                        $pdo,
                        $workspaceId,
                        $periodKey,
                        $entryType,
                        (string) ($_POST['label'] ?? ''),
                        $_POST['amount_value'] ?? null,
                        $isSettled,
                        (int) ($authUser['id'] ?? 0),
                        $isInstallment,
                        accountingInstallmentProgressFromRequest($_POST),
                        $_POST['total_amount_value'] ?? null,
                        $_POST['installment_number'] ?? null,
                        $_POST['installment_total'] ?? null
                    );
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => $entryType === 'income' ? 'Entrada adicionada.' : 'Conta adicionada.',
                    ]);
                }

                flash('success', $entryType === 'income' ? 'Entrada adicionada.' : 'Conta adicionada.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'update_accounting_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro inválido.');
                }

                $entryWorkspaceStmt = $pdo->prepare(
                    'SELECT workspace_id, entry_type
                     FROM workspace_accounting_entries
                     WHERE id = :id
                     LIMIT 1'
                );
                $entryWorkspaceStmt->execute([':id' => $entryId]);
                $entryRow = $entryWorkspaceStmt->fetch(PDO::FETCH_ASSOC);
                $entryWorkspaceId = (int) ($entryRow['workspace_id'] ?? 0);
                if ($entryWorkspaceId <= 0 || $entryWorkspaceId !== $workspaceId) {
                    throw new RuntimeException('Registro não encontrado.');
                }

                $isSettled = array_key_exists('is_settled', $_POST) ? 1 : 0;
                $entryType = normalizeAccountingEntryType((string) ($entryRow['entry_type'] ?? 'expense'));
                $isInstallment = $entryType === 'expense' && ((string) ($_POST['is_installment'] ?? '0')) === '1' ? 1 : 0;
                updateWorkspaceAccountingEntryWithCarrySync(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['label'] ?? ''),
                    $_POST['amount_value'] ?? null,
                    $isSettled,
                    $isInstallment,
                    accountingInstallmentProgressFromRequest($_POST),
                    $_POST['total_amount_value'] ?? null,
                    $_POST['installment_number'] ?? null,
                    $_POST['installment_total'] ?? null,
                    $_POST['monthly_day'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Registro atualizado.',
                    ]);
                }

                flash('success', 'Registro atualizado.');
                redirectTo(accountingRedirectPathFromRequest());

            case 'delete_accounting_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                if ($entryId <= 0) {
                    throw new RuntimeException('Registro inválido.');
                }

                $entryWorkspaceStmt = $pdo->prepare(
                    'SELECT workspace_id
                     FROM workspace_accounting_entries
                     WHERE id = :id
                     LIMIT 1'
                );
                $entryWorkspaceStmt->execute([':id' => $entryId]);
                $entryWorkspaceId = (int) $entryWorkspaceStmt->fetchColumn();
                if ($entryWorkspaceId <= 0 || $entryWorkspaceId !== $workspaceId) {
                    throw new RuntimeException('Registro não encontrado.');
                }

                deleteWorkspaceAccountingEntryWithCarrySync($pdo, $workspaceId, $entryId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Registro removido.',
                    ]);
                }

                flash('success', 'Registro removido.');
                redirectTo(accountingRedirectPathFromRequest());
    }

    return in_array($action, [
        'set_accounting_opening_balance',
        'create_accounting_entry',
        'update_accounting_entry',
        'delete_accounting_entry',
    ], true);
}
