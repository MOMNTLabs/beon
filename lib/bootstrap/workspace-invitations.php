<?php
declare(strict_types=1);

function workspaceInvitePath(string $selector, string $token, bool $absolute = false): string
{
    $query = http_build_query([
        'action' => 'workspace_invite',
        'selector' => $selector,
        'token' => $token,
    ]);

    $path = appPath('?' . $query);
    if (!$absolute) {
        return $path;
    }

    return appEntryUrl() . '/?' . $query;
}

function workspaceInviteParamsFromPath(string $path): ?array
{
    $path = trim($path);
    if ($path === '') {
        return null;
    }

    $parsed = parse_url($path);
    if ($parsed === false) {
        return null;
    }

    $queryParams = [];
    parse_str((string) ($parsed['query'] ?? ''), $queryParams);
    if (trim((string) ($queryParams['action'] ?? '')) !== 'workspace_invite') {
        return null;
    }

    $selector = trim((string) ($queryParams['selector'] ?? ''));
    $token = trim((string) ($queryParams['token'] ?? ''));
    if ($selector === '' || $token === '') {
        return null;
    }

    return [
        'selector' => $selector,
        'token' => $token,
        'path' => workspaceInvitePath($selector, $token, false),
    ];
}

function pruneExpiredWorkspaceEmailInvitations(?PDO $pdo = null): void
{
    $pdo ??= db();
    ensureWorkspaceEmailInvitationSchema($pdo);

    $now = nowIso();
    $stmt = $pdo->prepare(
        'UPDATE workspace_email_invitations
         SET status = :expired_status,
             updated_at = :updated_at,
             responded_at = COALESCE(responded_at, :responded_at)
         WHERE status = :pending_status
           AND expires_at <= :expires_at'
    );
    $stmt->execute([
        ':expired_status' => 'expired',
        ':updated_at' => $now,
        ':responded_at' => $now,
        ':pending_status' => 'pending',
        ':expires_at' => $now,
    ]);
}

function workspaceEmailInvitationById(PDO $pdo, int $invitationId): ?array
{
    if ($invitationId <= 0) {
        return null;
    }

    ensureWorkspaceEmailInvitationSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT *
         FROM workspace_email_invitations
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $invitationId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function validWorkspaceEmailInvitationRequest(string $selector, string $plainToken): ?array
{
    $selector = trim($selector);
    $plainToken = trim($plainToken);
    if (
        $selector === ''
        || $plainToken === ''
        || !preg_match('/^[a-f0-9]+$/i', $selector)
        || !preg_match('/^[a-f0-9]+$/i', $plainToken)
    ) {
        return null;
    }

    $pdo = db();
    ensureWorkspaceEmailInvitationSchema($pdo);
    ensureWorkspaceProfileSchema($pdo);
    ensureUserProfileSchema($pdo);
    pruneExpiredWorkspaceEmailInvitations($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             wei.*,
             w.name AS workspace_name,
             w.slug AS workspace_slug,
             w.is_personal AS workspace_is_personal,
             inviter.name AS invited_by_name,
             inviter.email AS invited_by_email,
             invited_user.id AS existing_user_id
         FROM workspace_email_invitations wei
         INNER JOIN workspaces w ON w.id = wei.workspace_id
         LEFT JOIN users inviter ON inviter.id = wei.invited_by
         LEFT JOIN users invited_user ON LOWER(invited_user.email) = wei.invited_email
         WHERE wei.selector = :selector
         LIMIT 1'
    );
    $stmt->execute([':selector' => $selector]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $expectedHash = (string) ($row['token_hash'] ?? '');
    $actualHash = hash('sha256', $plainToken);
    if (!hash_equals($expectedHash, $actualHash)) {
        return null;
    }

    if ((string) ($row['status'] ?? '') !== 'pending') {
        return null;
    }

    $row['workspace_is_personal'] = ((int) ($row['workspace_is_personal'] ?? 0)) === 1;
    $row['existing_user_id'] = (int) ($row['existing_user_id'] ?? 0);

    return $row;
}

function validWorkspaceEmailInvitationRequestFromPath(string $path): ?array
{
    $params = workspaceInviteParamsFromPath($path);
    if (!$params) {
        return null;
    }

    $request = validWorkspaceEmailInvitationRequest(
        (string) ($params['selector'] ?? ''),
        (string) ($params['token'] ?? '')
    );
    if (!$request) {
        return null;
    }

    $request['selector'] = (string) ($params['selector'] ?? '');
    $request['token'] = (string) ($params['token'] ?? '');
    $request['path'] = (string) ($params['path'] ?? '');

    return $request;
}

function workspaceInvitationById(PDO $pdo, int $invitationId): ?array
{
    if ($invitationId <= 0) {
        return null;
    }

    ensureWorkspaceInvitationSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT *
         FROM workspace_invitations
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $invitationId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function workspacePendingInvitationsForUser(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $pdo = db();
    ensureWorkspaceInvitationSchema($pdo);
    ensureWorkspaceProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             wi.id,
             wi.workspace_id,
             wi.invited_user_id,
             wi.invited_by,
             wi.status,
             wi.created_at,
             wi.updated_at,
             wi.responded_at,
             w.name AS workspace_name,
             w.slug AS workspace_slug,
             w.is_personal AS workspace_is_personal,
             w.avatar_data_url AS workspace_avatar_data_url,
             inviter.name AS invited_by_name,
             inviter.email AS invited_by_email
         FROM workspace_invitations wi
         INNER JOIN workspaces w ON w.id = wi.workspace_id
         LEFT JOIN users inviter ON inviter.id = wi.invited_by
         WHERE wi.invited_user_id = :user_id
           AND wi.status = :status
         ORDER BY wi.created_at DESC, wi.id DESC'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':status' => 'pending',
    ]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['workspace_is_personal'] = ((int) ($row['workspace_is_personal'] ?? 0)) === 1;
    }
    unset($row);

    return $rows;
}

function workspacePendingInvitationsForWorkspace(int $workspaceId): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    $pdo = db();
    ensureWorkspaceInvitationSchema($pdo);
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             wi.id,
             wi.workspace_id,
             wi.invited_user_id,
             wi.invited_by,
             wi.status,
             wi.created_at,
             wi.updated_at,
             wi.responded_at,
             u.name,
             u.email,
             u.avatar_data_url,
             inviter.name AS invited_by_name,
             inviter.email AS invited_by_email
         FROM workspace_invitations wi
         INNER JOIN users u ON u.id = wi.invited_user_id
         LEFT JOIN users inviter ON inviter.id = wi.invited_by
         WHERE wi.workspace_id = :workspace_id
           AND wi.status = :status
         ORDER BY wi.created_at DESC, wi.id DESC'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':status' => 'pending',
    ]);

    return $stmt->fetchAll();
}

function workspacePendingEmailInvitationsForWorkspace(int $workspaceId): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    $pdo = db();
    ensureWorkspaceEmailInvitationSchema($pdo);
    pruneExpiredWorkspaceEmailInvitations($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             wei.id,
             wei.workspace_id,
             wei.invited_email,
             wei.invited_by,
             wei.status,
             wei.expires_at,
             wei.accepted_user_id,
             wei.created_at,
             wei.updated_at,
             wei.responded_at,
             inviter.name AS invited_by_name,
             inviter.email AS invited_by_email
         FROM workspace_email_invitations wei
         LEFT JOIN users inviter ON inviter.id = wei.invited_by
         WHERE wei.workspace_id = :workspace_id
           AND wei.status = :status
         ORDER BY wei.created_at DESC, wei.id DESC'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':status' => 'pending',
    ]);

    return $stmt->fetchAll();
}

function createWorkspaceInvitation(PDO $pdo, int $workspaceId, int $invitedUserId, int $invitedBy): int
{
    if ($workspaceId <= 0 || $invitedUserId <= 0 || $invitedBy <= 0) {
        throw new RuntimeException('Convite inválido.');
    }

    $workspace = workspaceById($workspaceId);
    if (!$workspace) {
        throw new RuntimeException('Workspace não encontrado.');
    }
    if (!empty($workspace['is_personal'])) {
        throw new RuntimeException('Workspace pessoal não permite convidar usuários.');
    }
    if (userHasWorkspaceAccess($invitedUserId, $workspaceId)) {
        throw new RuntimeException('Usuário já pertence a este workspace.');
    }

    ensureWorkspaceCanInviteMembers($workspaceId);
    ensureWorkspaceInvitationSchema($pdo);

    $existingStmt = $pdo->prepare(
        'SELECT id, status
         FROM workspace_invitations
         WHERE workspace_id = :workspace_id
           AND invited_user_id = :invited_user_id
         LIMIT 1'
    );
    $existingStmt->execute([
        ':workspace_id' => $workspaceId,
        ':invited_user_id' => $invitedUserId,
    ]);
    $existing = $existingStmt->fetch();

    if ($existing && (string) ($existing['status'] ?? '') === 'pending') {
        throw new RuntimeException('Convite já enviado para este usuário.');
    }

    $now = nowIso();
    if ($existing) {
        $invitationId = (int) ($existing['id'] ?? 0);
        $updateStmt = $pdo->prepare(
            'UPDATE workspace_invitations
             SET invited_by = :invited_by,
                 status = :status,
                 created_at = :created_at,
                 updated_at = :updated_at,
                 responded_at = NULL
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':invited_by' => $invitedBy,
            ':status' => 'pending',
            ':created_at' => $now,
            ':updated_at' => $now,
            ':id' => $invitationId,
        ]);

        return $invitationId;
    }

    $insertSql = 'INSERT INTO workspace_invitations (
            workspace_id,
            invited_user_id,
            invited_by,
            status,
            created_at,
            updated_at,
            responded_at
         ) VALUES (
            :workspace_id,
            :invited_user_id,
            :invited_by,
            :status,
            :created_at,
            :updated_at,
            NULL
         )';
    if (dbDriverName($pdo) === 'pgsql') {
        $insertSql .= ' RETURNING id';
    }

    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        ':workspace_id' => $workspaceId,
        ':invited_user_id' => $invitedUserId,
        ':invited_by' => $invitedBy,
        ':status' => 'pending',
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    if (dbDriverName($pdo) === 'pgsql') {
        return (int) $insertStmt->fetchColumn();
    }

    return (int) $pdo->lastInsertId();
}

function createWorkspaceEmailInvitation(PDO $pdo, int $workspaceId, string $invitedEmail, int $invitedBy): array
{
    $invitedEmail = strtolower(trim($invitedEmail));
    if ($workspaceId <= 0 || $invitedBy <= 0 || $invitedEmail === '') {
        throw new RuntimeException('Convite inválido.');
    }
    if (!filter_var($invitedEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Informe um e-mail válido.');
    }

    $workspace = workspaceById($workspaceId);
    if (!$workspace) {
        throw new RuntimeException('Workspace não encontrado.');
    }
    if (!empty($workspace['is_personal'])) {
        throw new RuntimeException('Workspace pessoal não permite convidar usuários.');
    }

    $existingUserStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = :email LIMIT 1');
    $existingUserStmt->execute([':email' => $invitedEmail]);
    $existingUserId = (int) $existingUserStmt->fetchColumn();
    if ($existingUserId > 0 && userHasWorkspaceAccess($existingUserId, $workspaceId)) {
        throw new RuntimeException('Usuário já pertence a este workspace.');
    }

    ensureWorkspaceCanInviteMembers($workspaceId);
    ensureWorkspaceEmailInvitationSchema($pdo);
    pruneExpiredWorkspaceEmailInvitations($pdo);

    $selector = bin2hex(random_bytes(9));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+' . WORKSPACE_INVITATION_TOKEN_HOURS . ' hour'))->format('Y-m-d H:i:s');
    $now = nowIso();

    $existingStmt = $pdo->prepare(
        'SELECT id
         FROM workspace_email_invitations
         WHERE workspace_id = :workspace_id
           AND invited_email = :invited_email
         LIMIT 1'
    );
    $existingStmt->execute([
        ':workspace_id' => $workspaceId,
        ':invited_email' => $invitedEmail,
    ]);
    $existingId = (int) $existingStmt->fetchColumn();

    if ($existingId > 0) {
        $updateStmt = $pdo->prepare(
            'UPDATE workspace_email_invitations
             SET invited_by = :invited_by,
                 selector = :selector,
                 token_hash = :token_hash,
                 status = :status,
                 expires_at = :expires_at,
                 accepted_user_id = NULL,
                 created_at = :created_at,
                 updated_at = :updated_at,
                 responded_at = NULL
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':invited_by' => $invitedBy,
            ':selector' => $selector,
            ':token_hash' => $tokenHash,
            ':status' => 'pending',
            ':expires_at' => $expiresAt,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':id' => $existingId,
        ]);

        return [
            'id' => $existingId,
            'selector' => $selector,
            'token' => $token,
            'expires_at' => $expiresAt,
            'path' => workspaceInvitePath($selector, $token, false),
            'url' => workspaceInvitePath($selector, $token, true),
            'invited_email' => $invitedEmail,
        ];
    }

    $insertSql = 'INSERT INTO workspace_email_invitations (
            workspace_id,
            invited_email,
            invited_by,
            selector,
            token_hash,
            status,
            expires_at,
            accepted_user_id,
            created_at,
            updated_at,
            responded_at
         ) VALUES (
            :workspace_id,
            :invited_email,
            :invited_by,
            :selector,
            :token_hash,
            :status,
            :expires_at,
            NULL,
            :created_at,
            :updated_at,
            NULL
         )';
    if (dbDriverName($pdo) === 'pgsql') {
        $insertSql .= ' RETURNING id';
    }

    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        ':workspace_id' => $workspaceId,
        ':invited_email' => $invitedEmail,
        ':invited_by' => $invitedBy,
        ':selector' => $selector,
        ':token_hash' => $tokenHash,
        ':status' => 'pending',
        ':expires_at' => $expiresAt,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $invitationId = dbDriverName($pdo) === 'pgsql'
        ? (int) $insertStmt->fetchColumn()
        : (int) $pdo->lastInsertId();

    return [
        'id' => $invitationId,
        'selector' => $selector,
        'token' => $token,
        'expires_at' => $expiresAt,
        'path' => workspaceInvitePath($selector, $token, false),
        'url' => workspaceInvitePath($selector, $token, true),
        'invited_email' => $invitedEmail,
    ];
}

function acceptWorkspaceInvitation(PDO $pdo, int $invitationId, int $userId): int
{
    if ($invitationId <= 0 || $userId <= 0) {
        throw new RuntimeException('Convite inválido.');
    }

    $invitation = workspaceInvitationById($pdo, $invitationId);
    if (!$invitation || (int) ($invitation['invited_user_id'] ?? 0) !== $userId) {
        throw new RuntimeException('Convite não encontrado.');
    }
    if ((string) ($invitation['status'] ?? '') !== 'pending') {
        throw new RuntimeException('Este convite não está mais pendente.');
    }

    $workspaceId = (int) ($invitation['workspace_id'] ?? 0);
    if ($workspaceId <= 0 || workspaceIsPersonal($workspaceId)) {
        throw new RuntimeException('Workspace inválido.');
    }

    ensureWorkspaceCanInviteMembers($workspaceId);
    enforceWorkspaceMemberLimit($workspaceId, $userId);

    $now = nowIso();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        upsertWorkspaceMember($pdo, $workspaceId, $userId, 'member');

        $stmt = $pdo->prepare(
            'UPDATE workspace_invitations
             SET status = :status,
                 updated_at = :updated_at,
                 responded_at = :responded_at
             WHERE id = :id
               AND invited_user_id = :invited_user_id
               AND status = :pending_status'
        );
        $stmt->execute([
            ':status' => 'accepted',
            ':updated_at' => $now,
            ':responded_at' => $now,
            ':id' => $invitationId,
            ':invited_user_id' => $userId,
            ':pending_status' => 'pending',
        ]);

        if ($stmt->rowCount() <= 0) {
            throw new RuntimeException('Este convite não está mais pendente.');
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $workspaceId;
}

function acceptWorkspaceEmailInvitation(PDO $pdo, string $selector, string $plainToken, int $userId): int
{
    if ($userId <= 0) {
        throw new RuntimeException('Convite inválido.');
    }

    $invitation = validWorkspaceEmailInvitationRequest($selector, $plainToken);
    if (!$invitation) {
        throw new RuntimeException('Este link de convite é inválido ou expirou.');
    }

    $user = userById($userId);
    $userEmail = strtolower(trim((string) ($user['email'] ?? '')));
    $invitedEmail = strtolower(trim((string) ($invitation['invited_email'] ?? '')));
    if ($userEmail === '' || $userEmail !== $invitedEmail) {
        throw new RuntimeException('Este convite foi enviado para outro e-mail.');
    }

    $workspaceId = (int) ($invitation['workspace_id'] ?? 0);
    if ($workspaceId <= 0 || !empty($invitation['workspace_is_personal'])) {
        throw new RuntimeException('Workspace inválido.');
    }

    $now = nowIso();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        if (!userHasWorkspaceAccess($userId, $workspaceId)) {
            ensureWorkspaceCanInviteMembers($workspaceId);
            enforceWorkspaceMemberLimit($workspaceId, $userId);
            upsertWorkspaceMember($pdo, $workspaceId, $userId, 'member');
        }

        $stmt = $pdo->prepare(
            'UPDATE workspace_email_invitations
             SET status = :status,
                 accepted_user_id = :accepted_user_id,
                 updated_at = :updated_at,
                 responded_at = :responded_at
             WHERE id = :id
               AND status = :pending_status'
        );
        $stmt->execute([
            ':status' => 'accepted',
            ':accepted_user_id' => $userId,
            ':updated_at' => $now,
            ':responded_at' => $now,
            ':id' => (int) ($invitation['id'] ?? 0),
            ':pending_status' => 'pending',
        ]);

        if ($stmt->rowCount() <= 0) {
            throw new RuntimeException('Este convite não está mais pendente.');
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return $workspaceId;
}

function declineWorkspaceInvitation(PDO $pdo, int $invitationId, int $userId): int
{
    if ($invitationId <= 0 || $userId <= 0) {
        throw new RuntimeException('Convite inválido.');
    }

    $invitation = workspaceInvitationById($pdo, $invitationId);
    if (!$invitation || (int) ($invitation['invited_user_id'] ?? 0) !== $userId) {
        throw new RuntimeException('Convite não encontrado.');
    }
    if ((string) ($invitation['status'] ?? '') !== 'pending') {
        throw new RuntimeException('Este convite não está mais pendente.');
    }

    $workspaceId = (int) ($invitation['workspace_id'] ?? 0);
    $now = nowIso();
    $stmt = $pdo->prepare(
        'UPDATE workspace_invitations
         SET status = :status,
             updated_at = :updated_at,
             responded_at = :responded_at
         WHERE id = :id
           AND invited_user_id = :invited_user_id
           AND status = :pending_status'
    );
    $stmt->execute([
        ':status' => 'declined',
        ':updated_at' => $now,
        ':responded_at' => $now,
        ':id' => $invitationId,
        ':invited_user_id' => $userId,
        ':pending_status' => 'pending',
    ]);

    if ($stmt->rowCount() <= 0) {
        throw new RuntimeException('Este convite não está mais pendente.');
    }

    return $workspaceId;
}

function cancelWorkspaceInvitation(PDO $pdo, int $invitationId, int $workspaceId): void
{
    if ($invitationId <= 0 || $workspaceId <= 0) {
        throw new RuntimeException('Convite inválido.');
    }

    $invitation = workspaceInvitationById($pdo, $invitationId);
    if (!$invitation || (int) ($invitation['workspace_id'] ?? 0) !== $workspaceId) {
        throw new RuntimeException('Convite não encontrado.');
    }
    if ((string) ($invitation['status'] ?? '') !== 'pending') {
        throw new RuntimeException('Este convite não está mais pendente.');
    }

    $stmt = $pdo->prepare(
        'UPDATE workspace_invitations
         SET status = :status,
             updated_at = :updated_at,
             responded_at = :responded_at
         WHERE id = :id
           AND workspace_id = :workspace_id
           AND status = :pending_status'
    );
    $now = nowIso();
    $stmt->execute([
        ':status' => 'cancelled',
        ':updated_at' => $now,
        ':responded_at' => $now,
        ':id' => $invitationId,
        ':workspace_id' => $workspaceId,
        ':pending_status' => 'pending',
    ]);

    if ($stmt->rowCount() <= 0) {
        throw new RuntimeException('Este convite não está mais pendente.');
    }
}

function cancelWorkspaceEmailInvitation(PDO $pdo, int $invitationId, int $workspaceId): void
{
    if ($invitationId <= 0 || $workspaceId <= 0) {
        throw new RuntimeException('Convite inválido.');
    }

    pruneExpiredWorkspaceEmailInvitations($pdo);
    $invitation = workspaceEmailInvitationById($pdo, $invitationId);
    if (!$invitation || (int) ($invitation['workspace_id'] ?? 0) !== $workspaceId) {
        throw new RuntimeException('Convite não encontrado.');
    }
    if ((string) ($invitation['status'] ?? '') !== 'pending') {
        throw new RuntimeException('Este convite não está mais pendente.');
    }

    $stmt = $pdo->prepare(
        'UPDATE workspace_email_invitations
         SET status = :status,
             updated_at = :updated_at,
             responded_at = :responded_at
         WHERE id = :id
           AND workspace_id = :workspace_id
           AND status = :pending_status'
    );
    $now = nowIso();
    $stmt->execute([
        ':status' => 'cancelled',
        ':updated_at' => $now,
        ':responded_at' => $now,
        ':id' => $invitationId,
        ':workspace_id' => $workspaceId,
        ':pending_status' => 'pending',
    ]);

    if ($stmt->rowCount() <= 0) {
        throw new RuntimeException('Este convite não está mais pendente.');
    }
}
