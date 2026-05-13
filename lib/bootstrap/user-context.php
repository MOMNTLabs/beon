<?php
declare(strict_types=1);

function setActiveWorkspaceId(?int $workspaceId): void
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($workspaceId !== null && $workspaceId > 0) {
        $_SESSION['workspace_id'] = $workspaceId;
        if ($userId > 0) {
            setLastWorkspaceCookie($userId, $workspaceId);
        }
        return;
    }

    unset($_SESSION['workspace_id']);
    if ($userId > 0) {
        setLastWorkspaceCookie($userId, null);
    }
}

function personalWorkspaceDefaultName(int $userId): string
{
    if ($userId <= 0) {
        return 'UsuÃ¡rio Workspace';
    }

    $stmt = db()->prepare(
        'SELECT name
         FROM users
         WHERE id = :user_id
         LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    $userName = normalizeUserDisplayName((string) $stmt->fetchColumn());
    if ($userName === '') {
        $userName = 'UsuÃ¡rio';
    }

    return $userName . ' Workspace';
}

function ensurePersonalWorkspaceForUser(int $userId): ?int
{
    if ($userId <= 0) {
        return null;
    }

    $pdo = db();
    $personalName = personalWorkspaceDefaultName($userId);
    $existingPersonal = personalWorkspaceForUserId($userId);
    if ($existingPersonal) {
        $workspaceId = (int) ($existingPersonal['id'] ?? 0);
        $existingName = trim((string) ($existingPersonal['name'] ?? ''));
        if ($workspaceId > 0 && $existingName !== $personalName) {
            $renameStmt = $pdo->prepare(
                'UPDATE workspaces
                 SET name = :name,
                     updated_at = :updated_at
                 WHERE id = :workspace_id'
            );
            $renameStmt->execute([
                ':name' => $personalName,
                ':updated_at' => nowIso(),
                ':workspace_id' => $workspaceId,
            ]);
        }

        return $workspaceId > 0 ? $workspaceId : null;
    }

    $workspaceId = createWorkspace($pdo, $personalName, $userId, true);
    return $workspaceId > 0 ? $workspaceId : null;
}

function ensureUserWorkspaceAccess(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    ensurePersonalWorkspaceForUser($userId);

    $workspaceCountStmt = db()->prepare(
        'SELECT COUNT(*)
         FROM workspace_members
         WHERE user_id = :user_id'
    );
    $workspaceCountStmt->execute([':user_id' => $userId]);
    $workspaceCount = (int) $workspaceCountStmt->fetchColumn();
    if ($workspaceCount > 0) {
        return;
    }

    $workspaceId = createWorkspace(db(), 'Formula Online', $userId);
    if ($workspaceId > 0) {
        setActiveWorkspaceId($workspaceId);
    }
}

function ensureActiveWorkspaceSessionForUser(int $userId): void
{
    if ($userId <= 0) {
        setActiveWorkspaceId(null);
        return;
    }

    $sessionWorkspaceId = (int) ($_SESSION['workspace_id'] ?? 0);
    if ($sessionWorkspaceId > 0 && userHasWorkspaceAccess($userId, $sessionWorkspaceId)) {
        return;
    }

    $cookieWorkspaceId = lastWorkspaceIdFromCookieForUser($userId);
    if ($cookieWorkspaceId !== null && userHasWorkspaceAccess($userId, $cookieWorkspaceId)) {
        setActiveWorkspaceId($cookieWorkspaceId);
        return;
    }

    $personalWorkspace = personalWorkspaceForUserId($userId);
    if ($personalWorkspace) {
        setActiveWorkspaceId((int) ($personalWorkspace['id'] ?? 0));
        return;
    }

    $workspaces = workspacesForUser($userId);
    if (!$workspaces) {
        setActiveWorkspaceId(null);
        return;
    }

    setActiveWorkspaceId((int) ($workspaces[0]['id'] ?? 0));
}

function activeWorkspaceId(?array $user = null): ?int
{
    $user ??= currentUser();
    if (!$user) {
        return null;
    }

    $userId = (int) ($user['id'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    ensureUserWorkspaceAccess($userId);
    ensureActiveWorkspaceSessionForUser($userId);

    $workspaceId = (int) ($_SESSION['workspace_id'] ?? 0);
    return $workspaceId > 0 ? $workspaceId : null;
}

function activeWorkspace(?array $user = null): ?array
{
    $workspaceId = activeWorkspaceId($user);
    if ($workspaceId === null) {
        return null;
    }

    $workspace = workspaceById($workspaceId);
    if (!$workspace) {
        return null;
    }

    $user ??= currentUser();
    if ($user) {
        $workspace['member_role'] = workspaceRoleForUser((int) ($user['id'] ?? 0), $workspaceId) ?? 'member';
    } else {
        $workspace['member_role'] = 'member';
    }

    return $workspace;
}

function userById(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $pdo = db();
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare('SELECT id, name, email, avatar_data_url, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        restoreRememberedSession();
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }

    $user = userById((int) $userId);

    if (!$user) {
        logoutUser();
        return null;
    }

    ensureUserWorkspaceAccess((int) $user['id']);
    ensureActiveWorkspaceSessionForUser((int) $user['id']);

    return $user;
}

function avatarLabelInitials(string $label, string $fallback = '?'): string
{
    $label = trim($label);
    if ($label === '') {
        return $fallback;
    }

    $normalized = preg_replace('/[^\p{L}\p{N}@._-]+/u', ' ', $label) ?? $label;
    $normalized = trim($normalized);
    if ($normalized === '') {
        return $fallback;
    }

    $words = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($words) >= 2) {
        $initials = mb_substr((string) $words[0], 0, 1) . mb_substr((string) $words[1], 0, 1);
        return mb_strtoupper($initials);
    }

    $single = (string) ($words[0] ?? $normalized);
    if (str_contains($single, '@')) {
        $single = (string) strtok($single, '@');
    }
    $single = preg_replace('/[^\p{L}\p{N}]+/u', '', $single) ?? $single;
    if ($single === '') {
        return $fallback;
    }

    return mb_strtoupper(mb_substr($single, 0, 2));
}

function avatarImageSource(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^data:image\/[a-z0-9.+-]+;base64,[a-z0-9+\/=\s]+$/i', $value) === 1) {
        return (string) preg_replace('/\s+/u', '', $value);
    }

    if (preg_match('~^https?://~i', $value) === 1) {
        return $value;
    }

    return '';
}

function avatarTagName(string $tag): string
{
    $tag = strtolower(trim($tag));
    return in_array($tag, ['div', 'span'], true) ? $tag : 'div';
}

function avatarCacheKey(string $value): string
{
    return substr(sha1($value), 0, 12);
}

function avatarImageResponseUrl(string $action, int $subjectId, string $source): string
{
    $normalized = avatarImageSource($source);
    if ($normalized === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $normalized) === 1) {
        return $normalized;
    }

    if ($subjectId <= 0) {
        return strlen($normalized) <= 32768 ? $normalized : '';
    }

    return appPath(
        '?action=' . rawurlencode($action)
        . '&id=' . $subjectId
        . '&v=' . avatarCacheKey($normalized)
    );
}

function userAvatarSubjectId(array $user): int
{
    foreach (['avatar_user_id', 'user_id', 'invited_user_id', 'id'] as $key) {
        $value = (int) ($user[$key] ?? 0);
        if ($value > 0) {
            return $value;
        }
    }

    return 0;
}

function workspaceAvatarSubjectId(array $workspace): int
{
    foreach (['workspace_id', 'id'] as $key) {
        $value = (int) ($workspace[$key] ?? 0);
        if ($value > 0) {
            return $value;
        }
    }

    return 0;
}

function userAvatarImageSrc(array $user): string
{
    $source = (string) ($user['avatar_data_url'] ?? '');
    return avatarImageResponseUrl('user_avatar', userAvatarSubjectId($user), $source);
}

function userDisplayInitial(string $label, string $fallback = 'U'): string
{
    return mb_substr(avatarLabelInitials($label, $fallback), 0, 1);
}

function workspaceAvatarImageSrc(array $workspace): string
{
    $isPersonal = !empty($workspace['workspace_is_personal']) || !empty($workspace['is_personal']);
    if ($isPersonal) {
        $ownerImage = (string) ($workspace['owner_avatar_data_url'] ?? '');
        if ($ownerImage !== '') {
            return avatarImageResponseUrl('workspace_avatar', workspaceAvatarSubjectId($workspace), $ownerImage);
        }
    }

    $source = (string) ($workspace['workspace_avatar_data_url'] ?? $workspace['avatar_data_url'] ?? '');
    return avatarImageResponseUrl('workspace_avatar', workspaceAvatarSubjectId($workspace), $source);
}

function renderUserAvatar(array $user, string $class = 'avatar', bool $unused = false, string $tag = 'div'): string
{
    $tag = avatarTagName($tag);
    $label = trim((string) ($user['name'] ?? $user['email'] ?? $user['invited_email'] ?? 'Usuário'));
    $imageSrc = userAvatarImageSrc($user);
    $classes = trim($class . ($imageSrc !== '' ? ' has-image' : ''));

    if ($imageSrc !== '') {
        return '<' . $tag . ' class="' . e($classes) . '"><img src="' . e($imageSrc) . '" alt=""></' . $tag . '>';
    }

    return '<' . $tag . ' class="' . e($classes) . '">' . e(avatarLabelInitials($label, 'U')) . '</' . $tag . '>';
}

function renderWorkspaceAvatar(array $workspace, string $class = 'avatar', bool $unused = false, string $tag = 'div'): string
{
    $tag = avatarTagName($tag);
    $isPersonal = !empty($workspace['workspace_is_personal']) || !empty($workspace['is_personal']);
    $workspaceLabel = trim((string) ($workspace['workspace_name'] ?? $workspace['name'] ?? 'Workspace'));
    $ownerLabel = trim((string) ($workspace['owner_name'] ?? ''));
    if ($ownerLabel === '' && $isPersonal) {
        $ownerLabel = trim((string) preg_replace('/\s+workspace$/iu', '', $workspaceLabel));
    }
    $label = $isPersonal && $ownerLabel !== '' ? $ownerLabel : $workspaceLabel;
    $imageSrc = workspaceAvatarImageSrc($workspace);
    $classes = trim(
        $class
        . ' workspace-avatar'
        . ($isPersonal ? ' workspace-avatar-personal' : '')
        . ($imageSrc !== '' ? ' has-image' : '')
    );

    if ($imageSrc !== '') {
        return '<' . $tag . ' class="' . e($classes) . '"><img src="' . e($imageSrc) . '" alt=""></' . $tag . '>';
    }

    return '<' . $tag . ' class="' . e($classes) . '">' . e(avatarLabelInitials($label, $isPersonal ? 'U' : 'W')) . '</' . $tag . '>';
}

function avatarBinaryParts(string $value): ?array
{
    $normalized = avatarImageSource($value);
    if (!str_starts_with($normalized, 'data:image/')) {
        return null;
    }

    if (!preg_match('#^data:(image/(?:png|jpe?g|webp|gif));base64,([a-z0-9+/=]+)$#i', $normalized, $matches)) {
        return null;
    }

    $bytes = base64_decode((string) $matches[2], true);
    if (!is_string($bytes) || $bytes === '') {
        return null;
    }

    return [
        'mime' => strtolower((string) $matches[1]),
        'bytes' => $bytes,
        'etag' => '"' . sha1($bytes) . '"',
    ];
}

function outputAvatarImageSource(string $value): void
{
    $normalized = avatarImageSource($value);
    if ($normalized === '') {
        http_response_code(404);
        exit;
    }

    if (preg_match('~^https?://~i', $normalized) === 1) {
        header('Location: ' . $normalized);
        exit;
    }

    $parts = avatarBinaryParts($normalized);
    if ($parts === null) {
        http_response_code(404);
        exit;
    }

    $etag = (string) $parts['etag'];
    $ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($ifNoneMatch === $etag) {
        http_response_code(304);
        header('ETag: ' . $etag);
        header('Cache-Control: private, max-age=604800');
        exit;
    }

    $bytes = (string) $parts['bytes'];
    header('Content-Type: ' . (string) $parts['mime']);
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, max-age=604800');
    header('ETag: ' . $etag);
    echo $bytes;
    exit;
}

function respondUserAvatarImage(): void
{
    $currentUser = currentUser();
    $userId = (int) ($_GET['id'] ?? 0);
    if (!$currentUser || $userId <= 0) {
        http_response_code(404);
        exit;
    }

    ensureUserProfileSchema(db());
    $stmt = db()->prepare('SELECT avatar_data_url FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    outputAvatarImageSource((string) $stmt->fetchColumn());
}

function respondWorkspaceAvatarImage(): void
{
    $currentUser = currentUser();
    $workspaceId = (int) ($_GET['id'] ?? 0);
    if (!$currentUser || $workspaceId <= 0) {
        http_response_code(404);
        exit;
    }

    if (workspaceRoleForUser((int) ($currentUser['id'] ?? 0), $workspaceId) === null) {
        http_response_code(404);
        exit;
    }

    $workspace = workspaceById($workspaceId);
    if (!$workspace) {
        http_response_code(404);
        exit;
    }

    $isPersonal = !empty($workspace['is_personal']);
    if ($isPersonal) {
        $ownerImage = avatarImageSource((string) ($workspace['owner_avatar_data_url'] ?? ''));
        if ($ownerImage !== '') {
            outputAvatarImageSource($ownerImage);
        }
    }

    outputAvatarImageSource((string) ($workspace['avatar_data_url'] ?? ''));
}
