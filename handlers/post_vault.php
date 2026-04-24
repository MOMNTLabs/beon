<?php
declare(strict_types=1);

function handleVaultPostAction(PDO $pdo, string $action): bool
{
    switch ($action) {
            case 'create_vault_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $groupName = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                if (findVaultGroupByName($groupName, $workspaceId) !== null) {
                    throw new RuntimeException('Este grupo de cofre já existe.');
                }

                upsertVaultGroup($pdo, $groupName, (int) $authUser['id'], $workspaceId);
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $groupName,
                        'message' => 'Grupo do cofre criado.',
                    ]);
                }
                flash('success', 'Grupo do cofre criado.');
                redirectTo('index.php#vault');

            case 'rename_vault_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $oldGroupInput = normalizeVaultGroupName((string) ($_POST['old_group_name'] ?? ''));
                $newGroupName = normalizeVaultGroupName((string) ($_POST['new_group_name'] ?? ''));
                $existingOldGroupName = findVaultGroupByName($oldGroupInput, $workspaceId);
                if ($existingOldGroupName === null) {
                    throw new RuntimeException('Grupo do cofre não encontrado.');
                }
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, $existingOldGroupName)) {
                    throw new RuntimeException('Você não possui acesso para gerenciar este grupo do cofre.');
                }

                $existingTargetGroupName = findVaultGroupByName($newGroupName, $workspaceId);
                if (
                    $existingTargetGroupName !== null &&
                    mb_strtolower($existingTargetGroupName) !== mb_strtolower($existingOldGroupName)
                ) {
                    throw new RuntimeException('Já existe um grupo do cofre com este nome.');
                }

                if (mb_strtolower($existingOldGroupName) !== mb_strtolower($newGroupName)) {
                    $pdo->beginTransaction();
                    try {
                        $renameGroupStmt = $pdo->prepare(
                            'UPDATE workspace_vault_groups
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
                            'UPDATE workspace_vault_entries
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

                        renameVaultGroupPermissions(
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
                        'message' => 'Grupo do cofre renomeado.',
                    ]);
                }

                flash('success', 'Grupo do cofre renomeado.');
                redirectTo('index.php#vault');

            case 'delete_vault_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $groupName = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findVaultGroupByName($groupName, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo do cofre não encontrado.');
                }
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, $existingGroupName)) {
                    throw new RuntimeException('Você não possui acesso para remover este grupo do cofre.');
                }

                $pdo->beginTransaction();
                try {
                    $deleteEntriesStmt = $pdo->prepare(
                        'DELETE FROM workspace_vault_entries
                         WHERE workspace_id = :workspace_id
                           AND group_name = :group_name'
                    );
                    $deleteEntriesStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    $deletedEntriesCount = (int) $deleteEntriesStmt->rowCount();

                    $deleteGroupStmt = $pdo->prepare(
                        'DELETE FROM workspace_vault_groups
                         WHERE workspace_id = :workspace_id
                           AND name = :group_name'
                    );
                    $deleteGroupStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);
                    if ($deleteGroupStmt->rowCount() <= 0) {
                        throw new RuntimeException('Não foi possível remover o grupo do cofre.');
                    }

                    deleteVaultGroupPermissions($pdo, $workspaceId, $existingGroupName);

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
                            ? sprintf('Grupo do cofre removido. %d item(ns) excluido(s).', $deletedEntriesCount)
                            : 'Grupo do cofre removido.',
                    ]);
                }
                flash(
                    'success',
                    $deletedEntriesCount > 0
                        ? sprintf('Grupo do cofre removido. %d item(ns) excluido(s).', $deletedEntriesCount)
                        : 'Grupo do cofre removido.'
                );
                redirectTo('index.php#vault');

            case 'update_vault_group_permissions':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem configurar acessos por grupo.');
                }

                $groupInput = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findVaultGroupByName($groupInput, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo do cofre não encontrado.');
                }

                $rolesByUserId = workspaceRolesByUserId(workspaceMembersList($workspaceId));
                $permissionsByUserId = submittedGroupPermissionsByUserId($rolesByUserId);
                $pdo->beginTransaction();
                try {
                    saveVaultGroupPermissions($pdo, $workspaceId, $existingGroupName, $permissionsByUserId, $rolesByUserId);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                flash('success', 'Permissoes do grupo do cofre atualizadas.');
                redirectTo('index.php#vault');

            case 'create_vault_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $groupNameInput = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findVaultGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, $groupName)) {
                    throw new RuntimeException('Você não possui acesso para adicionar itens neste grupo do cofre.');
                }
                $createdEntryId = createWorkspaceVaultEntry(
                    $pdo,
                    $workspaceId,
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['login_value'] ?? ''),
                    (string) ($_POST['password_value'] ?? ''),
                    $groupName,
                    (int) $authUser['id']
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $createdEntryId,
                        'message' => 'Item salvo no cofre.',
                    ]);
                }

                flash('success', 'Item salvo no cofre.');
                redirectTo('index.php#vault');

            case 'rename_vault_entry_label':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $label = (string) ($_POST['label'] ?? '');
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_vault_entries
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
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, (string) $entryGroupName)) {
                    throw new RuntimeException('Você não possui acesso para editar este item do cofre.');
                }
                updateWorkspaceVaultEntryLabel($pdo, $workspaceId, $entryId, $label);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'label' => normalizeVaultEntryLabel($label),
                    ]);
                }

                flash('success', 'Nome do acesso atualizado.');
                redirectTo('index.php#vault');

            case 'update_vault_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $groupNameInput = normalizeVaultGroupName((string) ($_POST['group_name'] ?? ''));
                $groupName = findVaultGroupByName($groupNameInput, $workspaceId) ?? $groupNameInput;
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_vault_entries
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
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, (string) $entryGroupName)) {
                    throw new RuntimeException('Você não possui acesso para editar este item do cofre.');
                }
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, $groupName)) {
                    throw new RuntimeException('Você não possui acesso ao grupo de destino.');
                }
                updateWorkspaceVaultEntry(
                    $pdo,
                    $workspaceId,
                    $entryId,
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['login_value'] ?? ''),
                    (string) ($_POST['password_value'] ?? ''),
                    $groupName
                );

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Item do cofre atualizado.',
                    ]);
                }

                flash('success', 'Item do cofre atualizado.');
                redirectTo('index.php#vault');

            case 'delete_vault_entry':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $entryId = (int) ($_POST['entry_id'] ?? 0);
                $entryGroupStmt = $pdo->prepare(
                    'SELECT group_name
                     FROM workspace_vault_entries
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
                if (!userCanAccessVaultGroup((int) $authUser['id'], $workspaceId, (string) $entryGroupName)) {
                    throw new RuntimeException('Você não possui acesso para excluir este item do cofre.');
                }
                deleteWorkspaceVaultEntry($pdo, $workspaceId, $entryId);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'entry_id' => $entryId,
                        'message' => 'Item removido do cofre.',
                    ]);
                }

                flash('success', 'Item removido do cofre.');
                redirectTo('index.php#vault');

    }

    return in_array($action, [
        'create_vault_group',
        'rename_vault_group',
        'delete_vault_group',
        'update_vault_group_permissions',
        'create_vault_entry',
        'rename_vault_entry_label',
        'update_vault_entry',
        'delete_vault_entry',
    ], true);
}

