<?php
declare(strict_types=1);

function handleInventoryPostAction(PDO $pdo, string $action): bool
{
    switch ($action) {
            case 'create_inventory_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $groupName = normalizeInventoryGroupName((string) ($_POST['group_name'] ?? ''));
                if (findInventoryGroupByName($groupName, $workspaceId) !== null) {
                    throw new RuntimeException('Este grupo de estoque já existe.');
                }

                upsertInventoryGroup($pdo, $groupName, (int) $authUser['id'], $workspaceId);
                flash('success', 'Grupo de estoque criado.');
                redirectTo('index.php#inventory');

            case 'rename_inventory_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $oldGroupInput = normalizeInventoryGroupName((string) ($_POST['old_group_name'] ?? ''));
                $newGroupName = normalizeInventoryGroupName((string) ($_POST['new_group_name'] ?? ''));
                $existingOldGroupName = findInventoryGroupByName($oldGroupInput, $workspaceId);
                if ($existingOldGroupName === null) {
                    throw new RuntimeException('Grupo de estoque não encontrado.');
                }

                $existingTargetGroupName = findInventoryGroupByName($newGroupName, $workspaceId);
                if (
                    $existingTargetGroupName !== null &&
                    mb_strtolower($existingTargetGroupName) !== mb_strtolower($existingOldGroupName)
                ) {
                    throw new RuntimeException('Já existe um grupo de estoque com este nome.');
                }

                if (mb_strtolower($existingOldGroupName) !== mb_strtolower($newGroupName)) {
                    $pdo->beginTransaction();
                    try {
                        $renameGroupStmt = $pdo->prepare(
                            'UPDATE workspace_inventory_groups
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
                            'UPDATE workspace_inventory_entries
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

                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                }

                flash('success', 'Grupo de estoque renomeado.');
                redirectTo('index.php#inventory');

            case 'delete_inventory_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $groupName = normalizeInventoryGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findInventoryGroupByName($groupName, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo de estoque não encontrado.');
                }

                $pdo->beginTransaction();
                try {
                    $deleteEntriesStmt = $pdo->prepare(
                        'DELETE FROM workspace_inventory_entries
                         WHERE workspace_id = :workspace_id
                           AND group_name = :group_name'
                    );
                    $deleteEntriesStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    $deletedEntriesCount = (int) $deleteEntriesStmt->rowCount();

                    $deleteGroupStmt = $pdo->prepare(
                        'DELETE FROM workspace_inventory_groups
                         WHERE workspace_id = :workspace_id
                           AND name = :group_name'
                    );
                    $deleteGroupStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    if ($deleteGroupStmt->rowCount() <= 0) {
                        throw new RuntimeException('Não foi possível remover o grupo de estoque.');
                    }

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                flash(
                    'success',
                    $deletedEntriesCount > 0
                        ? sprintf('Grupo de estoque removido. %d item(ns) excluido(s).', $deletedEntriesCount)
                        : 'Grupo de estoque removido.'
                );
                redirectTo('index.php#inventory');

            case 'create_inventory_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $groupNameInput = normalizeInventoryGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findInventoryGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;

                $createdEntryId = createWorkspaceInventoryEntry(
                    $pdo,
                    $workspaceId,
                    (string) ($_POST['label'] ?? ''),
                    $_POST['quantity_value'] ?? null,
                    (string) ($_POST['unit_label'] ?? 'un'),
                    $groupName,
                    $_POST['min_quantity_value'] ?? null,
                    (string) ($_POST['notes'] ?? ''),
                    (int) $authUser['id']
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $createdEntryId,
                        'message' => 'Item de estoque criado.',
                    ]);
                }

                flash('success', 'Item de estoque criado.');
                redirectTo('index.php#inventory');

            case 'update_inventory_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $groupNameInput = normalizeInventoryGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findInventoryGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_inventory_entries
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

                updateWorkspaceInventoryEntry(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['label'] ?? ''),
                    $_POST['quantity_value'] ?? null,
                    (string) ($_POST['unit_label'] ?? 'un'),
                    $groupName,
                    $_POST['min_quantity_value'] ?? null,
                    (string) ($_POST['notes'] ?? '')
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Item de estoque atualizado.',
                    ]);
                }

                flash('success', 'Item de estoque atualizado.');
                redirectTo('index.php#inventory');

            case 'update_inventory_entry_quantity':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                updateWorkspaceInventoryEntryQuantity(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    $_POST['quantity_value'] ?? null
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Quantidade atualizada.',
                    ]);
                }

                flash('success', 'Quantidade atualizada.');
                redirectTo('index.php#inventory');

            case 'delete_inventory_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_inventory_entries
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

                deleteWorkspaceInventoryEntry($pdo, $workspaceId, $entryId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Item de estoque removido.',
                    ]);
                }

                flash('success', 'Item de estoque removido.');
                redirectTo('index.php#inventory');
    }

    return in_array($action, [
        'create_inventory_group',
        'rename_inventory_group',
        'delete_inventory_group',
        'create_inventory_entry',
        'update_inventory_entry',
        'update_inventory_entry_quantity',
        'delete_inventory_entry',
    ], true);
}
