<?php
declare(strict_types=1);

function handleTaskGroupPostAction(PDO $pdo, string $action): bool
{
    switch ($action) {
            case 'create_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                $groupName = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));

                if (findTaskGroupByName($groupName, $workspaceId) !== null) {
                    throw new RuntimeException('Este grupo já existe.');
                }

                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem criar grupos.');
                }

                $rolesByUserId = workspaceRolesByUserId(workspaceMembersList($workspaceId));
                $permissionsByUserId = submittedGroupPermissionsByUserId($rolesByUserId, [
                    'include_admins' => true,
                    'force_enabled_user_ids' => [(int) $authUser['id']],
                ]);

                $pdo->beginTransaction();
                try {
                    upsertTaskGroup($pdo, $groupName, (int) $authUser['id'], $workspaceId);
                    saveTaskGroupPermissions(
                        $pdo,
                        $workspaceId,
                        $groupName,
                        $permissionsByUserId,
                        $rolesByUserId
                    );
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }
                flash('success', 'Grupo criado.');
                redirectTo('index.php#tasks');

            case 'rename_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                $oldGroupInput = normalizeTaskGroupName((string) ($_POST['old_group_name'] ?? ''));
                $newGroupName = normalizeTaskGroupName((string) ($_POST['new_group_name'] ?? ''));
                $existingOldGroupName = findTaskGroupByName($oldGroupInput, $workspaceId);

                if ($existingOldGroupName === null) {
                    throw new RuntimeException('Grupo não encontrado.');
                }
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $existingOldGroupName)) {
                    throw new RuntimeException('Você não possui acesso para gerenciar este grupo.');
                }

                $existingTargetGroupName = findTaskGroupByName($newGroupName, $workspaceId);
                if (
                    $existingTargetGroupName !== null &&
                    mb_strtolower($existingTargetGroupName) !== mb_strtolower($existingOldGroupName)
                ) {
                    throw new RuntimeException('Já existe um grupo com este nome.');
                }

                $taskCountStmt = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM tasks
                     WHERE workspace_id = :workspace_id
                       AND group_name = :group_name'
                );
                $taskCountStmt->execute([
                    ':workspace_id' => $workspaceId,
                    ':group_name' => $existingOldGroupName,
                ]);
                $affectedTaskCount = (int) $taskCountStmt->fetchColumn();
                $affectedTaskIds = [];
                $renameUpdatedAt = nowIso();

                if ($affectedTaskCount > 0 && $existingOldGroupName !== $newGroupName) {
                    $taskIdsStmt = $pdo->prepare(
                        'SELECT id
                         FROM tasks
                         WHERE workspace_id = :workspace_id
                           AND group_name = :group_name'
                    );
                    $taskIdsStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingOldGroupName,
                    ]);
                    $affectedTaskIds = array_map(
                        'intval',
                        array_column($taskIdsStmt->fetchAll(), 'id')
                    );
                }

                $pdo->beginTransaction();
                try {
                    if ($existingOldGroupName !== $newGroupName) {
                        $renameGroupStmt = $pdo->prepare(
                            'UPDATE task_groups
                             SET name = :new_group_name
                             WHERE workspace_id = :workspace_id
                               AND name = :old_group_name'
                        );
                        $renameGroupStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);
                        renameTaskGroupPermissions($pdo, $workspaceId, $existingOldGroupName, $newGroupName);
                    }

                    if ($affectedTaskCount > 0 && $existingOldGroupName !== $newGroupName) {
                        $renameTasksStmt = $pdo->prepare(
                            'UPDATE tasks
                             SET group_name = :new_group_name, updated_at = :updated_at
                             WHERE workspace_id = :workspace_id
                               AND group_name = :old_group_name'
                        );
                        $renameTasksStmt->execute([
                            ':new_group_name' => $newGroupName,
                            ':updated_at' => $renameUpdatedAt,
                            ':workspace_id' => $workspaceId,
                            ':old_group_name' => $existingOldGroupName,
                        ]);

                        foreach ($affectedTaskIds as $affectedTaskId) {
                            if ($affectedTaskId <= 0) {
                                continue;
                            }

                            logTaskHistory(
                                $pdo,
                                $affectedTaskId,
                                'group_changed',
                                ['old' => $existingOldGroupName, 'new' => $newGroupName],
                                (int) $authUser['id'],
                                $renameUpdatedAt
                            );
                        }
                    }

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
                        'old_group_name' => $existingOldGroupName,
                        'group_name' => $newGroupName,
                        'affected_task_count' => $affectedTaskCount,
                    ]);
                }

                flash('success', 'Grupo renomeado.');
                redirectTo('index.php#tasks');

            case 'delete_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                $groupName = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findTaskGroupByName($groupName, $workspaceId);

                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo não encontrado.');
                }
                if (!userCanAccessTaskGroup((int) $authUser['id'], $workspaceId, $existingGroupName)) {
                    throw new RuntimeException('Você não possui acesso para remover este grupo.');
                }

                $taskIdsStmt = $pdo->prepare(
                    'SELECT id
                     FROM tasks
                     WHERE workspace_id = :workspace_id
                       AND LOWER(TRIM(COALESCE(group_name, \'\'))) = LOWER(TRIM(:group_name))'
                );
                $taskIdsStmt->execute([
                    ':workspace_id' => $workspaceId,
                    ':group_name' => $existingGroupName,
                ]);
                $taskIds = array_map('intval', array_column($taskIdsStmt->fetchAll(), 'id'));
                $taskCount = count($taskIds);
                $undoBeforeGroup = taskUndoGroupSnapshot($pdo, $workspaceId, $existingGroupName, $taskIds);

                $pdo->beginTransaction();
                try {
                    if ($taskCount > 0) {
                        $taskPlaceholders = [];
                        $taskParams = [];
                        foreach ($taskIds as $index => $taskIdValue) {
                            $paramName = ':task_id_' . $index;
                            $taskPlaceholders[] = $paramName;
                            $taskParams[$paramName] = $taskIdValue;
                        }

                        $deleteHistorySql = 'DELETE FROM task_history WHERE task_id IN (' . implode(', ', $taskPlaceholders) . ')';
                        $deleteHistoryStmt = $pdo->prepare($deleteHistorySql);
                        foreach ($taskParams as $paramName => $paramValue) {
                            $deleteHistoryStmt->bindValue($paramName, $paramValue, PDO::PARAM_INT);
                        }
                        $deleteHistoryStmt->execute();
                    }

                    $deleteTasksStmt = $pdo->prepare(
                        'DELETE FROM tasks
                         WHERE workspace_id = :workspace_id
                           AND LOWER(TRIM(COALESCE(group_name, \'\'))) = LOWER(TRIM(:group_name))'
                    );
                    $deleteTasksStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':group_name' => $existingGroupName,
                    ]);

                    $deleteGroupStmt = $pdo->prepare(
                        'DELETE FROM task_groups
                         WHERE workspace_id = :workspace_id
                           AND LOWER(TRIM(COALESCE(name, \'\'))) = LOWER(TRIM(:name))'
                    );
                    $deleteGroupStmt->execute([
                        ':workspace_id' => $workspaceId,
                        ':name' => $existingGroupName,
                    ]);

                    if ($deleteGroupStmt->rowCount() <= 0) {
                        throw new RuntimeException('Não foi possível remover o grupo.');
                    }

                    deleteTaskGroupPermissions($pdo, $workspaceId, $existingGroupName);

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                $restoreToken = '';
                if (is_array($undoBeforeGroup) && !empty($undoBeforeGroup['group'])) {
                    if (!isset($_SESSION['task_group_restore']) || !is_array($_SESSION['task_group_restore'])) {
                        $_SESSION['task_group_restore'] = [];
                    }
                    if (!isset($_SESSION['task_group_restore'][$workspaceId]) || !is_array($_SESSION['task_group_restore'][$workspaceId])) {
                        $_SESSION['task_group_restore'][$workspaceId] = [];
                    }
                    $restoreToken = 'group_restore_' . taskUndoOperationId();
                    $_SESSION['task_group_restore'][$workspaceId][$restoreToken] = $undoBeforeGroup;
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $existingGroupName,
                        'deleted_task_count' => $taskCount,
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                        'restore_token' => $restoreToken,
                    ]);
                }

                flash(
                    'success',
                    $taskCount > 0
                        ? sprintf('Grupo removido. %d tarefa(s) excluida(s).', $taskCount)
                        : 'Grupo removido.'
                );
                redirectTo('index.php#tasks');

            case 'restore_deleted_group':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }

                $restoreToken = trim((string) ($_POST['restore_token'] ?? ''));
                if ($restoreToken === '') {
                    throw new RuntimeException('Restauração inválida.');
                }

                $restoreSnapshot =
                    $_SESSION['task_group_restore'][$workspaceId][$restoreToken] ?? null;
                if (!is_array($restoreSnapshot) || empty($restoreSnapshot['group'])) {
                    throw new RuntimeException('Este grupo não pode mais ser restaurado.');
                }

                $pdo->beginTransaction();
                try {
                    taskUndoEnsureGroupSnapshotAccess((int) $authUser['id'], $workspaceId, $restoreSnapshot);
                    taskUndoRestoreGroupSnapshot($pdo, $workspaceId, $restoreSnapshot);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                unset($_SESSION['task_group_restore'][$workspaceId][$restoreToken]);
                if (empty($_SESSION['task_group_restore'][$workspaceId])) {
                    unset($_SESSION['task_group_restore'][$workspaceId]);
                }
                if (empty($_SESSION['task_group_restore'])) {
                    unset($_SESSION['task_group_restore']);
                }

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'message' => 'Grupo restaurado.',
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id'], $workspaceId),
                    ]);
                }

                flash('success', 'Grupo restaurado.');
                redirectTo('index.php#tasks');

            case 'update_task_group_permissions':
                $authUser = requireAuth();
                $workspaceId = activeWorkspaceId($authUser);
                if ($workspaceId === null) {
                    throw new RuntimeException('Workspace ativo não encontrado.');
                }
                if (!userCanManageWorkspace((int) $authUser['id'], $workspaceId)) {
                    throw new RuntimeException('Somente administradores podem configurar acessos por grupo.');
                }

                $groupInput = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findTaskGroupByName($groupInput, $workspaceId);
                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo não encontrado.');
                }

                $rolesByUserId = workspaceRolesByUserId(workspaceMembersList($workspaceId));
                $permissionsByUserId = submittedGroupPermissionsByUserId($rolesByUserId, [
                    'include_admins' => true,
                    'force_enabled_user_ids' => [(int) $authUser['id']],
                ]);
                $pdo->beginTransaction();
                try {
                    saveTaskGroupPermissions($pdo, $workspaceId, $existingGroupName, $permissionsByUserId, $rolesByUserId);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $e;
                }

                flash('success', 'Permissoes do grupo atualizadas.');
                redirectTo('index.php#tasks');
    }

    return in_array($action, [
        'create_group',
        'rename_group',
        'delete_group',
        'restore_deleted_group',
        'update_task_group_permissions',
    ], true);
}
