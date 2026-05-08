<?php
declare(strict_types=1);

function handleDuePostAction(PDO $pdo, string $action): bool
{
    switch ($action) {
            case 'create_due_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $groupName = normalizeDueGroupName((string) ($_POST['group_name'] ?? ''));
                if (findDueGroupByName($groupName, $workspaceId) !== null) {
                    throw new RuntimeException('Este grupo de vencimentos já existe.');
                }

                upsertDueGroup($pdo, $groupName, (int) $authUser['id'], $workspaceId);
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $groupName,
                        'message' => 'Grupo de vencimentos criado.',
                    ]);
                }
                flash('success', 'Grupo de vencimentos criado.');
                redirectTo('index.php#accounting');

            case 'rename_due_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $oldGroupInput = normalizeDueGroupName((string) ($_POST['old_group_name'] ?? ''));
                $newGroupName = normalizeDueGroupName((string) ($_POST['new_group_name'] ?? ''));
                $existingOldGroupName = findDueGroupByName($oldGroupInput, $workspaceId);
                if ($existingOldGroupName === null) {
                    throw new RuntimeException('Grupo de vencimentos não encontrado.');
                }
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, $existingOldGroupName)) {
                    throw new RuntimeException('Você não possui acesso para gerenciar este grupo de vencimentos.');
                }

                $existingTargetGroupName = findDueGroupByName($newGroupName, $workspaceId);
                if (
                    $existingTargetGroupName !== null &&
                    mb_strtolower($existingTargetGroupName) !== mb_strtolower($existingOldGroupName)
                ) {
                    throw new RuntimeException('Já existe um grupo de vencimentos com este nome.');
                }

                if (mb_strtolower($existingOldGroupName) !== mb_strtolower($newGroupName)) {
                    $pdo->beginTransaction();
                    try {
                        $renameGroupStmt = $pdo->prepare(
                            'UPDATE workspace_due_groups
                             SET name = :new_group_name
                             WHERE workspace_id = :workspace_id
                               AND name = :old_group_name'
                        );
                        $renameGroupStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);

                        $renameEntriesStmt = $pdo->prepare(
                            'UPDATE workspace_due_entries
                             SET group_name = :new_group_name,
                                 updated_at = :updated_at
                             WHERE workspace_id = :workspace_id
                               AND group_name = :old_group_name'
                        );
                        $renameEntriesStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':updated_at' => nowIso(),
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);

                        renameDueGroupPermissions(
                            $pdo,
                            $workspaceId,
                            $existingOldGroupName,
                            $newGroupName
                        );

                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'old_group_name' => $existingOldGroupName,
                        'group_name' => $newGroupName,
                        'message' => 'Grupo de vencimentos renomeado.',
                    ]);
                }

                flash('success', 'Grupo de vencimentos renomeado.');
                redirectTo('index.php#accounting');

            case 'delete_due_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $groupName = normalizeDueGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findDueGroupByName($groupName, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo de vencimentos não encontrado.');
                }
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, $existingGroupName)) {
                    throw new RuntimeException('Você não possui acesso para remover este grupo de vencimentos.');
                }

                $pdo->beginTransaction();
                try {
                    $deleteEntriesStmt = $pdo->prepare(
                        'DELETE FROM workspace_due_entries
                         WHERE workspace_id = :workspace_id
                           AND group_name = :group_name'
                    );
                    $deleteEntriesStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    $deletedEntriesCount = (int) $deleteEntriesStmt->rowCount();

                    $deleteGroupStmt = $pdo->prepare(
                        'DELETE FROM workspace_due_groups
                         WHERE workspace_id = :workspace_id
                           AND name = :group_name'
                    );
                    $deleteGroupStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    if ($deleteGroupStmt->rowCount() <= 0) {
                        throw new RuntimeException('Não foi possível remover o grupo de vencimentos.');
                    }

                    deleteDueGroupPermissions($pdo, $workspaceId, $existingGroupName);

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $existingGroupName,
                        'deleted_entries_count' => $deletedEntriesCount,
                        'message' => $deletedEntriesCount > 0
                            ? 'Grupo de vencimentos removido. ' . appItemCountLabel($deletedEntriesCount) . ($deletedEntriesCount === 1 ? ' excluido.' : ' excluidos.')
                            : 'Grupo de vencimentos removido.',
                    ]);
                }
                flash(
                    'success',
                    $deletedEntriesCount > 0
                        ? 'Grupo de vencimentos removido. ' . appItemCountLabel($deletedEntriesCount) . ($deletedEntriesCount === 1 ? ' excluido.' : ' excluidos.')
                        : 'Grupo de vencimentos removido.'
                );
                redirectTo('index.php#accounting');

            case 'update_due_group_permissions':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem configurar acessos por grupo.');
                }

                $groupInput = normalizeDueGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findDueGroupByName($groupInput, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo de vencimentos não encontrado.');
                }

                $rolesByUserId = workspaceRolesByUserId(workspaceMembersList($workspaceId));
                $permissionsByUserId = submittedGroupPermissionsByUserId($rolesByUserId);
                $pdo->beginTransaction();
                try {
                    saveDueGroupPermissions($pdo, $workspaceId, $existingGroupName, $permissionsByUserId, $rolesByUserId);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                flash('success', 'Permissoes do grupo de vencimentos atualizadas.');
                redirectTo('index.php#accounting');

            case 'create_due_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $groupNameInput = normalizeDueGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findDueGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, $groupName)) {
                    throw new RuntimeException('Você não possui acesso para adicionar itens neste grupo de vencimentos.');
                }

                $createdEntryId = createWorkspaceDueEntry(
                    $pdo,
                    $workspaceId,
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['due_date'] ?? ''),
                    $groupName,
                    '',
                    (string) ($_POST['amount_value'] ?? ''),
                    (int) $authUser['id'],
                    (string) ($_POST['recurrence_type'] ?? 'monthly'),
                    $_POST['monthly_day'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $createdEntryId,
                        'message' => 'Vencimento criado.',
                    ]);
                }

                flash('success', 'Vencimento criado.');
                redirectTo('index.php#accounting');

            case 'update_due_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $groupNameInput = normalizeDueGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findDueGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_due_entries
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $entryGroupStmt->execute([
                    ':id' => $entryId,
                    ':workspace_id' => $workspaceId,
                ]);
                $entryGroupName = $entryGroupStmt->fetchColumn();
                if (!is_string($entryGroupName) || trim($entryGroupName) === '') {
                    throw new RuntimeException('Registro não encontrado.');
                }
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, (string) $entryGroupName)) {
                    throw new RuntimeException('Você não possui acesso para editar este vencimento.');
                }
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, $groupName)) {
                    throw new RuntimeException('Você não possui acesso ao grupo de destino.');
                }

                updateWorkspaceDueEntry(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['due_date'] ?? ''),
                    $groupName,
                    '',
                    (string) ($_POST['amount_value'] ?? ''),
                    (string) ($_POST['recurrence_type'] ?? 'monthly'),
                    $_POST['monthly_day'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Vencimento atualizado.',
                    ]);
                }

                flash('success', 'Vencimento atualizado.');
                redirectTo('index.php#accounting');

            case 'delete_due_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_due_entries
                     WHERE id = :id
                       AND workspace_id = :workspace_id
                     LIMIT 1'
                );
                $entryGroupStmt->execute([
                    ':id' => $entryId,
                    ':workspace_id' => $workspaceId,
                ]);
                $entryGroupName = $entryGroupStmt->fetchColumn();
                if (!is_string($entryGroupName) || trim($entryGroupName) === '') {
                    throw new RuntimeException('Registro não encontrado.');
                }
                if (!userCanAccessDueGroup((int) $authUser['id'], $workspaceId, (string) $entryGroupName)) {
                    throw new RuntimeException('Você não possui acesso para excluir este vencimento.');
                }

                deleteWorkspaceDueEntry($pdo, $workspaceId, $entryId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Vencimento removido.',
                    ]);
                }

                flash('success', 'Vencimento removido.');
                redirectTo('index.php#accounting');
    }

    return in_array($action, [
        'create_due_group',
        'rename_due_group',
        'delete_due_group',
        'update_due_group_permissions',
        'create_due_entry',
        'update_due_entry',
        'delete_due_entry',
    ], true);
}
