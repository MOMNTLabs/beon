<?php
declare(strict_types=1);

function workspaceRoles(): array
{
    return [
        'admin' => 'Administrador',
        'member' => 'Usuário',
    ];
}

function normalizeWorkspaceRole(string $value): string
{
    $value = trim(mb_strtolower($value));
    return array_key_exists($value, workspaceRoles()) ? $value : 'member';
}

function normalizePermissionFlag($value): int
{
    return ((int) $value) === 1 ? 1 : 0;
}

function normalizeWorkspaceName(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'Formula Online';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mb_strlen($value) > 80) {
        $value = mb_substr($value, 0, 80);
    }

    return uppercaseFirstCharacter($value);
}

function workspaceSlugify(string $value): string
{
    $raw = trim($value);
    if ($raw === '') {
        return 'workspace';
    }

    if (function_exists('iconv')) {
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
        if (is_string($translit) && $translit !== '') {
            $raw = $translit;
        }
    }

    $raw = mb_strtolower($raw);
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $raw) ?? '';
    $slug = trim($slug, '-');

    if ($slug === '') {
        return 'workspace';
    }

    return mb_substr($slug, 0, 96);
}

function workspaceSlugExists(PDO $pdo, string $slug): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM workspaces WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    return (bool) $stmt->fetchColumn();
}

function generateWorkspaceSlug(PDO $pdo, string $workspaceName): string
{
    $base = workspaceSlugify($workspaceName);
    $slug = $base;
    $suffix = 2;

    while (workspaceSlugExists($pdo, $slug)) {
        $slug = mb_substr($base, 0, 90) . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

function guessPrimaryAdminUserId(PDO $pdo): ?int
{
    $rows = $pdo->query('SELECT id, name, email FROM users ORDER BY id ASC')->fetchAll();
    if (!$rows) {
        return null;
    }

    foreach ($rows as $row) {
        $userId = (int) ($row['id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $name = mb_strtolower(trim((string) ($row['name'] ?? '')));
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
        if (str_contains($name, 'bruno') || str_contains($email, 'bruno')) {
            continue;
        }

        return $userId;
    }

    return (int) ($rows[0]['id'] ?? 0) ?: null;
}

function ensureWorkspaceTaskStatusSchema(PDO $pdo): void
{
    static $checkedConnections = [];

    $connectionId = spl_object_id($pdo);
    if (!empty($checkedConnections[$connectionId])) {
        return;
    }

    ensureWorkspaceProfileSchema($pdo);

    $columns = [
        'task_statuses_json' => "ALTER TABLE workspaces ADD COLUMN task_statuses_json TEXT NOT NULL DEFAULT '[]'",
        'task_review_status_key' => 'ALTER TABLE workspaces ADD COLUMN task_review_status_key TEXT DEFAULT NULL',
        'sidebar_tools_json' => "ALTER TABLE workspaces ADD COLUMN sidebar_tools_json TEXT NOT NULL DEFAULT '[]'",
    ];

    foreach ($columns as $column => $statement) {
        if (tableHasColumn($pdo, 'workspaces', $column)) {
            continue;
        }

        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            if (!tableHasColumn($pdo, 'workspaces', $column)) {
                throw $e;
            }
        }
    }

    $checkedConnections[$connectionId] = true;
}

function workspaceById(int $workspaceId): ?array
{
    if ($workspaceId <= 0) {
        return null;
    }

    $pdo = db();
    ensureWorkspaceTaskStatusSchema($pdo);
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             w.id,
             w.name,
             w.slug,
             w.is_personal,
             w.avatar_data_url,
             w.created_by,
             creator.name AS owner_name,
             creator.avatar_data_url AS owner_avatar_data_url,
             w.task_statuses_json,
             w.task_review_status_key,
             w.sidebar_tools_json,
             w.created_at,
             w.updated_at
         FROM workspaces w
         LEFT JOIN users creator ON creator.id = w.created_by
         WHERE w.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $workspaceId]);
    $workspace = $stmt->fetch();

    if (!$workspace) {
        return null;
    }

    $workspace['is_personal'] = ((int) ($workspace['is_personal'] ?? 0)) === 1;
    return $workspace;
}

function workspaceIsPersonal(int $workspaceId): bool
{
    if ($workspaceId <= 0) {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT is_personal
         FROM workspaces
         WHERE id = :workspace_id
         LIMIT 1'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    return ((int) $stmt->fetchColumn()) === 1;
}

function workspaceCanManageMembers(int $workspaceId): bool
{
    return !workspaceIsPersonal($workspaceId);
}

function personalWorkspaceForUserId(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $pdo = db();
    ensureWorkspaceProfileSchema($pdo);
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             w.id,
             w.name,
             w.slug,
             w.is_personal,
             w.avatar_data_url,
             w.created_by,
             creator.name AS owner_name,
             creator.avatar_data_url AS owner_avatar_data_url,
             w.created_at,
             w.updated_at
         FROM workspaces w
         INNER JOIN workspace_members wm ON wm.workspace_id = w.id
         LEFT JOIN users creator ON creator.id = w.created_by
         WHERE wm.user_id = :member_user_id
           AND w.is_personal = 1
           AND w.created_by = :owner_user_id
         ORDER BY w.created_at ASC, w.id ASC
         LIMIT 1'
    );
    $stmt->execute([
        ':member_user_id' => $userId,
        ':owner_user_id' => $userId,
    ]);
    $workspace = $stmt->fetch();
    if (!$workspace) {
        return null;
    }

    $workspace['is_personal'] = true;
    return $workspace;
}

function workspaceRoleCacheKey(int $workspaceId, int $userId): string
{
    return $workspaceId . ':' . $userId;
}

function invalidateWorkspaceRoleCache(?int $workspaceId = null, ?int $userId = null): void
{
    if (!isset($GLOBALS['workspace_role_cache']) || !is_array($GLOBALS['workspace_role_cache'])) {
        return;
    }

    if ($workspaceId === null && $userId === null) {
        $GLOBALS['workspace_role_cache'] = [];
        return;
    }

    $cache = &$GLOBALS['workspace_role_cache'];
    if ($workspaceId !== null && $workspaceId > 0 && $userId !== null && $userId > 0) {
        unset($cache[workspaceRoleCacheKey($workspaceId, $userId)]);
        return;
    }

    if ($workspaceId !== null && $workspaceId > 0) {
        $prefix = $workspaceId . ':';
        foreach (array_keys($cache) as $cacheKey) {
            if (str_starts_with((string) $cacheKey, $prefix)) {
                unset($cache[$cacheKey]);
            }
        }
        return;
    }

    if ($userId !== null && $userId > 0) {
        $suffix = ':' . $userId;
        foreach (array_keys($cache) as $cacheKey) {
            if (str_ends_with((string) $cacheKey, $suffix)) {
                unset($cache[$cacheKey]);
            }
        }
    }
}

function workspaceRoleForUser(int $userId, int $workspaceId): ?string
{
    if ($userId <= 0 || $workspaceId <= 0) {
        return null;
    }

    if (!isset($GLOBALS['workspace_role_cache']) || !is_array($GLOBALS['workspace_role_cache'])) {
        $GLOBALS['workspace_role_cache'] = [];
    }
    $cache = &$GLOBALS['workspace_role_cache'];
    $cacheKey = workspaceRoleCacheKey($workspaceId, $userId);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = db()->prepare(
        'SELECT role
         FROM workspace_members
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    $role = $stmt->fetchColumn();
    if (!is_string($role) || trim($role) === '') {
        $cache[$cacheKey] = null;
        return null;
    }

    $normalizedRole = normalizeWorkspaceRole($role);
    $cache[$cacheKey] = $normalizedRole;
    return $normalizedRole;
}

function userHasWorkspaceAccess(int $userId, int $workspaceId): bool
{
    return workspaceRoleForUser($userId, $workspaceId) !== null;
}

function workspaceMembershipCount(int $workspaceId): int
{
    if ($workspaceId <= 0) {
        return 0;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM workspace_members
         WHERE workspace_id = :workspace_id'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);

    return max(0, (int) $stmt->fetchColumn());
}

function userCanManageWorkspace(int $userId, int $workspaceId): bool
{
    return workspaceRoleForUser($userId, $workspaceId) === 'admin';
}

function upsertWorkspaceMember(PDO $pdo, int $workspaceId, int $userId, string $role = 'member'): void
{
    if ($workspaceId <= 0 || $userId <= 0) {
        return;
    }

    $normalizedRole = normalizeWorkspaceRole($role);

    $existingStmt = $pdo->prepare(
        'SELECT role
         FROM workspace_members
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id
         LIMIT 1'
    );
    $existingStmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    $existingRole = $existingStmt->fetchColumn();
    if (is_string($existingRole) && trim($existingRole) !== '') {
        $existingRole = normalizeWorkspaceRole($existingRole);

        if ($existingRole === 'admin' && $normalizedRole !== 'admin') {
            return;
        }

        if ($existingRole !== $normalizedRole) {
            $updateStmt = $pdo->prepare(
                'UPDATE workspace_members
                 SET role = :role
                 WHERE workspace_id = :workspace_id
                   AND user_id = :user_id'
            );
            $updateStmt->execute([
                ':role' => $normalizedRole,
                ':workspace_id' => $workspaceId,
                ':user_id' => $userId,
            ]);
            invalidateWorkspaceRoleCache($workspaceId, $userId);
        }

        return;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO workspace_members (workspace_id, user_id, role, created_at)
         VALUES (:workspace_id, :user_id, :role, :created_at)'
    );
    $insertStmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
        ':role' => $normalizedRole,
        ':created_at' => nowIso(),
    ]);
    invalidateWorkspaceRoleCache($workspaceId, $userId);
}

function createWorkspace(PDO $pdo, string $workspaceName, int $createdBy, bool $isPersonal = false): int
{
    $createdBy = (int) $createdBy;
    if ($createdBy <= 0) {
        throw new RuntimeException('Criador do workspace inválido.');
    }

    ensureWorkspaceTaskStatusSchema($pdo);

    $name = normalizeWorkspaceName($workspaceName);
    $slug = generateWorkspaceSlug($pdo, $name);
    $now = nowIso();
    $personalFlag = $isPersonal ? 1 : 0;

    $taskStatusesJson = encodeWorkspaceTaskStatusDefinitions(defaultTaskStatusDefinitions());
    $taskReviewStatusKey = defaultTaskReviewStatusKey();
    $sidebarToolsJson = encodeWorkspaceSidebarTools([]);

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO workspaces (name, slug, is_personal, created_by, task_statuses_json, task_review_status_key, sidebar_tools_json, created_at, updated_at)
             VALUES (:name, :slug, :is_personal, :created_by, :task_statuses_json, :task_review_status_key, :sidebar_tools_json, :created_at, :updated_at)
             RETURNING id'
        );
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':is_personal' => $personalFlag,
            ':created_by' => $createdBy,
            ':task_statuses_json' => $taskStatusesJson,
            ':task_review_status_key' => $taskReviewStatusKey,
            ':sidebar_tools_json' => $sidebarToolsJson,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $workspaceId = (int) $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO workspaces (name, slug, is_personal, created_by, task_statuses_json, task_review_status_key, sidebar_tools_json, created_at, updated_at)
             VALUES (:name, :slug, :is_personal, :created_by, :task_statuses_json, :task_review_status_key, :sidebar_tools_json, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':is_personal' => $personalFlag,
            ':created_by' => $createdBy,
            ':task_statuses_json' => $taskStatusesJson,
            ':task_review_status_key' => $taskReviewStatusKey,
            ':sidebar_tools_json' => $sidebarToolsJson,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $workspaceId = (int) $pdo->lastInsertId();
    }

    upsertWorkspaceMember($pdo, $workspaceId, $createdBy, 'admin');
    upsertTaskGroup($pdo, 'Geral', $createdBy, $workspaceId);

    return $workspaceId;
}

function updateWorkspaceName(PDO $pdo, int $workspaceId, string $workspaceName): void
{
    updateWorkspaceProfile($pdo, $workspaceId, $workspaceName, [], true);
}

function uploadedWorkspaceAvatarDataUrl(array $file): string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha ao enviar a foto do workspace.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Arquivo de foto do workspace inválido.');
    }

    if ($size > 2 * 1024 * 1024) {
        throw new RuntimeException('A foto do workspace deve ter no maximo 2 MB.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException('Arquivo de foto do workspace inválido.');
    }

    $contents = file_get_contents($tmpName);
    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('Não foi possível ler a foto do workspace.');
    }

    $imageInfo = @getimagesizefromstring($contents);
    $mime = strtolower((string) ($imageInfo['mime'] ?? ''));
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!$imageInfo || !in_array($mime, $allowedMimeTypes, true)) {
        throw new RuntimeException('Use uma imagem PNG, JPG, WEBP ou GIF.');
    }

    return 'data:' . $mime . ';base64,' . base64_encode($contents);
}

function updateWorkspaceProfile(
    PDO $pdo,
    int $workspaceId,
    string $workspaceName,
    array $avatarFile = [],
    bool $allowRename = true
): void {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace inválido.');
    }

    ensureWorkspaceProfileSchema($pdo);
    ensureUserProfileSchema($pdo);
    $workspace = workspaceById($workspaceId);
    if (!$workspace) {
        throw new RuntimeException('Workspace não encontrado.');
    }

    $isPersonalWorkspace = !empty($workspace['is_personal']);
    $workspaceOwnerId = (int) ($workspace['created_by'] ?? 0);

    $params = [
        ':updated_at' => nowIso(),
        ':workspace_id' => $workspaceId,
    ];
    $setClauses = [
        'updated_at = :updated_at',
    ];

    if ($allowRename) {
        $trimmed = trim($workspaceName);
        if ($trimmed === '') {
            throw new RuntimeException('Informe um nome para o workspace.');
        }

        $params[':name'] = normalizeWorkspaceName($trimmed);
        $setClauses[] = 'name = :name';
    }

    $avatarDataUrl = uploadedWorkspaceAvatarDataUrl($avatarFile);
    if ($avatarDataUrl !== '') {
        if ($isPersonalWorkspace && $workspaceOwnerId > 0) {
            $userAvatarStmt = $pdo->prepare(
                'UPDATE users
                 SET avatar_data_url = :avatar_data_url
                 WHERE id = :user_id'
            );
            $userAvatarStmt->execute([
                ':avatar_data_url' => $avatarDataUrl,
                ':user_id' => $workspaceOwnerId,
            ]);
        } else {
            $params[':avatar_data_url'] = $avatarDataUrl;
            $setClauses[] = 'avatar_data_url = :avatar_data_url';
        }
    }

    if (count($setClauses) === 1 && $avatarDataUrl === '') {
        throw new RuntimeException('Nenhuma alteração enviada para o workspace.');
    }

    $stmt = $pdo->prepare(
        'UPDATE workspaces
         SET ' . implode(', ', $setClauses) . '
         WHERE id = :workspace_id'
    );
    $stmt->execute($params);
}

function workspaceAdminCount(int $workspaceId): int
{
    if ($workspaceId <= 0) {
        return 0;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM workspace_members
         WHERE workspace_id = :workspace_id
           AND role = :role'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':role' => 'admin',
    ]);

    return (int) $stmt->fetchColumn();
}

function updateWorkspaceMemberRole(PDO $pdo, int $workspaceId, int $userId, string $role): void
{
    if ($workspaceId <= 0 || $userId <= 0) {
        throw new RuntimeException('Usuário inválido.');
    }

    $nextRole = normalizeWorkspaceRole($role);
    $currentRole = workspaceRoleForUser($userId, $workspaceId);
    if ($currentRole === null) {
        throw new RuntimeException('Usuário não pertence a este workspace.');
    }
    if ($currentRole === $nextRole) {
        return;
    }

    if ($currentRole === 'admin' && $nextRole !== 'admin' && workspaceAdminCount($workspaceId) <= 1) {
        throw new RuntimeException('Mantenha pelo menos um administrador no workspace.');
    }

    $stmt = $pdo->prepare(
        'UPDATE workspace_members
         SET role = :role
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id'
    );
    $stmt->execute([
        ':role' => $nextRole,
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    invalidateWorkspaceRoleCache($workspaceId, $userId);
}

function removeWorkspaceMember(PDO $pdo, int $workspaceId, int $userId): void
{
    if ($workspaceId <= 0 || $userId <= 0) {
        throw new RuntimeException('Usuário inválido.');
    }

    $existingRole = workspaceRoleForUser($userId, $workspaceId);
    if ($existingRole === null) {
        throw new RuntimeException('Usuário não pertence a este workspace.');
    }

    if ($existingRole === 'admin' && workspaceAdminCount($workspaceId) <= 1) {
        throw new RuntimeException('Mantenha pelo menos um administrador no workspace.');
    }

    $stmt = $pdo->prepare(
        'DELETE FROM workspace_members
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    invalidateWorkspaceRoleCache($workspaceId, $userId);
}

function workspacesForUser(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $pdo = db();
    ensureWorkspaceProfileSchema($pdo);
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             w.id,
             w.name,
             w.slug,
             w.is_personal,
             w.avatar_data_url,
             w.created_by,
             creator.name AS owner_name,
             creator.avatar_data_url AS owner_avatar_data_url,
             w.created_at,
             w.updated_at,
             wm.role AS member_role
         FROM workspaces w
         INNER JOIN workspace_members wm ON wm.workspace_id = w.id
         LEFT JOIN users creator ON creator.id = w.created_by
         WHERE wm.user_id = :user_id
         ORDER BY
             CASE WHEN w.is_personal = 1 THEN 0 ELSE 1 END,
             w.created_at ASC,
             w.id ASC'
    );
    $stmt->execute([':user_id' => $userId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['is_personal'] = ((int) ($row['is_personal'] ?? 0)) === 1;
        $row['member_role'] = normalizeWorkspaceRole((string) ($row['member_role'] ?? 'member'));
    }
    unset($row);

    return $rows;
}

function workspaceMembersList(int $workspaceId): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    $pdo = db();
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             u.id,
             u.name,
             u.email,
             u.avatar_data_url,
             wm.role AS workspace_role,
             wm.created_at AS workspace_member_created_at
         FROM workspace_members wm
         INNER JOIN users u ON u.id = wm.user_id
         WHERE wm.workspace_id = :workspace_id
         ORDER BY
             CASE WHEN wm.role = \'admin\' THEN 0 ELSE 1 END,
             u.name ASC,
             u.id ASC'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['workspace_role'] = normalizeWorkspaceRole((string) ($row['workspace_role'] ?? 'member'));
    }
    unset($row);

    return $rows;
}

function deleteWorkspaceOwnedByUser(PDO $pdo, int $workspaceId, int $ownerUserId): void
{
    if ($workspaceId <= 0 || $ownerUserId <= 0) {
        throw new RuntimeException('Workspace inválido.');
    }

    $workspaceStmt = $pdo->prepare(
        'SELECT id, created_by
         FROM workspaces
         WHERE id = :workspace_id
         LIMIT 1'
    );
    $workspaceStmt->execute([':workspace_id' => $workspaceId]);
    $workspace = $workspaceStmt->fetch();
    if (!$workspace) {
        throw new RuntimeException('Workspace não encontrado.');
    }

    if ((int) ($workspace['created_by'] ?? 0) !== $ownerUserId) {
        throw new RuntimeException('Somente o criador pode remover este workspace.');
    }

    $pdo->beginTransaction();
    try {
        $deleteHistoryStmt = $pdo->prepare(
            'DELETE FROM task_history
             WHERE task_id IN (
                SELECT id
                FROM tasks
                WHERE workspace_id = :workspace_id
             )'
        );
        $deleteHistoryStmt->execute([':workspace_id' => $workspaceId]);

        $deleteTasksStmt = $pdo->prepare(
            'DELETE FROM tasks
             WHERE workspace_id = :workspace_id'
        );
        $deleteTasksStmt->execute([':workspace_id' => $workspaceId]);

        $deleteGroupsStmt = $pdo->prepare(
            'DELETE FROM task_groups
             WHERE workspace_id = :workspace_id'
        );
        $deleteGroupsStmt->execute([':workspace_id' => $workspaceId]);

        $deleteMembersStmt = $pdo->prepare(
            'DELETE FROM workspace_members
             WHERE workspace_id = :workspace_id'
        );
        $deleteMembersStmt->execute([':workspace_id' => $workspaceId]);
        invalidateWorkspaceRoleCache($workspaceId);

        $deleteWorkspaceStmt = $pdo->prepare(
            'DELETE FROM workspaces
             WHERE id = :workspace_id
               AND created_by = :owner_user_id'
        );
        $deleteWorkspaceStmt->execute([
            ':workspace_id' => $workspaceId,
            ':owner_user_id' => $ownerUserId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function leaveWorkspace(PDO $pdo, int $workspaceId, int $userId): void
{
    if ($workspaceId <= 0 || $userId <= 0) {
        throw new RuntimeException('Workspace inválido.');
    }

    $workspaceStmt = $pdo->prepare(
        'SELECT id, created_by
         FROM workspaces
         WHERE id = :workspace_id
         LIMIT 1'
    );
    $workspaceStmt->execute([':workspace_id' => $workspaceId]);
    $workspace = $workspaceStmt->fetch();
    if (!$workspace) {
        throw new RuntimeException('Workspace não encontrado.');
    }

    if ((int) ($workspace['created_by'] ?? 0) === $userId) {
        throw new RuntimeException('Você criou este workspace. Use a opção de remover workspace.');
    }

    $role = workspaceRoleForUser($userId, $workspaceId);
    if ($role === null) {
        throw new RuntimeException('Você não pertence a este workspace.');
    }

    $pdo->beginTransaction();
    try {
        if ($role === 'admin' && workspaceAdminCount($workspaceId) <= 1) {
            $promoteStmt = $pdo->prepare(
                'SELECT user_id
                 FROM workspace_members
                 WHERE workspace_id = :workspace_id
                   AND user_id <> :user_id
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $promoteStmt->execute([
                ':workspace_id' => $workspaceId,
                ':user_id' => $userId,
            ]);
            $nextAdminUserId = (int) $promoteStmt->fetchColumn();
            if ($nextAdminUserId <= 0) {
                throw new RuntimeException('Não foi possível sair deste workspace agora.');
            }

            $updateRoleStmt = $pdo->prepare(
                'UPDATE workspace_members
                 SET role = :role
                 WHERE workspace_id = :workspace_id
                   AND user_id = :user_id'
            );
            $updateRoleStmt->execute([
                ':role' => 'admin',
                ':workspace_id' => $workspaceId,
                ':user_id' => $nextAdminUserId,
            ]);
            invalidateWorkspaceRoleCache($workspaceId, $nextAdminUserId);
        }

        $removeStmt = $pdo->prepare(
            'DELETE FROM workspace_members
             WHERE workspace_id = :workspace_id
               AND user_id = :user_id'
        );
        $removeStmt->execute([
            ':workspace_id' => $workspaceId,
            ':user_id' => $userId,
        ]);
        invalidateWorkspaceRoleCache($workspaceId, $userId);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function workspaceMembershipsDetailedForUser(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $pdo = db();
    ensureWorkspaceProfileSchema($pdo);
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             w.id,
             w.name,
             w.slug,
             w.is_personal,
             w.avatar_data_url,
             w.created_by,
             creator.name AS owner_name,
             creator.avatar_data_url AS owner_avatar_data_url,
             w.created_at,
             w.updated_at,
             wm.role AS member_role,
             creator.name AS creator_name,
             creator.email AS creator_email,
             (
                SELECT COUNT(*)
                FROM workspace_members wm2
                WHERE wm2.workspace_id = w.id
             ) AS member_count
         FROM workspaces w
         INNER JOIN workspace_members wm ON wm.workspace_id = w.id
         LEFT JOIN users creator ON creator.id = w.created_by
         WHERE wm.user_id = :user_id
         ORDER BY
             CASE WHEN w.is_personal = 1 THEN 0 ELSE 1 END,
             w.created_at ASC,
             w.id ASC'
    );
    $stmt->execute([':user_id' => $userId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['created_by'] = (int) ($row['created_by'] ?? 0);
        $row['is_personal'] = ((int) ($row['is_personal'] ?? 0)) === 1;
        $row['member_count'] = (int) ($row['member_count'] ?? 0);
        $row['member_role'] = normalizeWorkspaceRole((string) ($row['member_role'] ?? 'member'));
        $row['is_owner'] = ((int) $row['created_by']) === $userId;
    }
    unset($row);

    return $rows;
}
