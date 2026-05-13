<?php
declare(strict_types=1);

function loginUser(int $userId, bool $remember = true): void
{
    $_SESSION['user_id'] = $userId;
    clearPendingCheckoutUserId();
    session_regenerate_id(true);
    ensureUserWorkspaceAccess($userId);
    ensureActiveWorkspaceSessionForUser($userId);

    if ($remember) {
        issueRememberToken($userId);
    }
}

function logoutUser(): void
{
    revokeRememberTokenByCookie();
    clearRememberCookie();
    unset($_SESSION['user_id']);
    unset($_SESSION['workspace_id']);
    clearPendingCheckoutUserId();
    session_regenerate_id(true);
}

function setPendingCheckoutUserId(int $userId): void
{
    if ($userId <= 0) {
        clearPendingCheckoutUserId();
        return;
    }

    $_SESSION['pending_checkout_user_id'] = $userId;
    $_SESSION['pending_checkout_created_at'] = time();
}

function pendingCheckoutUserId(): ?int
{
    $userId = (int) ($_SESSION['pending_checkout_user_id'] ?? 0);
    $createdAt = (int) ($_SESSION['pending_checkout_created_at'] ?? 0);

    if ($userId <= 0 || $createdAt <= 0 || (time() - $createdAt) > PENDING_CHECKOUT_SESSION_TTL_SECONDS) {
        clearPendingCheckoutUserId();
        return null;
    }

    return $userId;
}

function clearPendingCheckoutUserId(): void
{
    unset($_SESSION['pending_checkout_user_id'], $_SESSION['pending_checkout_created_at']);
}

function issueRememberToken(int $userId): void
{
    $pdo = db();
    $selector = bin2hex(random_bytes(9));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+' . REMEMBER_TOKEN_DAYS . ' days'))->format('Y-m-d H:i:s');
    $createdAt = nowIso();

    $stmt = $pdo->prepare(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, created_at)
         VALUES (:user_id, :selector, :token_hash, :expires_at, :created_at)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':selector' => $selector,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiresAt,
        ':created_at' => $createdAt,
    ]);

    pruneRememberTokensForUser($userId, 8);
    setRememberCookie($selector, $token, (new DateTimeImmutable($expiresAt))->getTimestamp());
}

function pruneRememberTokensForUser(int $userId, int $keep = 8): void
{
    $pdo = db();
    $driver = dbDriverName($pdo);

    if ($driver === 'pgsql') {
        $sql = 'DELETE FROM remember_tokens
                WHERE id IN (
                    SELECT id FROM remember_tokens
                    WHERE user_id = :user_id
                    ORDER BY created_at DESC
                    OFFSET :offset
                )';
    } else {
        $sql = 'DELETE FROM remember_tokens
                WHERE id IN (
                    SELECT id FROM remember_tokens
                    WHERE user_id = :user_id
                    ORDER BY created_at DESC
                    LIMIT -1 OFFSET :offset
                )';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $keep), PDO::PARAM_INT);
    $stmt->execute();
}

function requestIsHttps(): bool
{
    return bootstrapRequestIsHttps();
}

function setRememberCookie(string $selector, string $token, int $expiresTs): void
{
    $cookieValue = $selector . ':' . $token;

    setcookie(REMEMBER_COOKIE_NAME, $cookieValue, [
        'expires' => $expiresTs,
        'path' => '/',
        'domain' => bootstrapConfiguredCookieDomain(),
        'secure' => requestIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[REMEMBER_COOKIE_NAME] = $cookieValue;
}

function clearRememberCookie(): void
{
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => bootstrapConfiguredCookieDomain(),
        'secure' => requestIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

function setLastWorkspaceCookie(int $userId, ?int $workspaceId): void
{
    if ($userId <= 0 || $workspaceId === null || $workspaceId <= 0) {
        clearLastWorkspaceCookie();
        return;
    }

    $cookieValue = $userId . ':' . $workspaceId;
    $expiresTs = (new DateTimeImmutable('+' . LAST_WORKSPACE_COOKIE_DAYS . ' days'))->getTimestamp();

    setcookie(LAST_WORKSPACE_COOKIE_NAME, $cookieValue, [
        'expires' => $expiresTs,
        'path' => '/',
        'domain' => bootstrapConfiguredCookieDomain(),
        'secure' => requestIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[LAST_WORKSPACE_COOKIE_NAME] = $cookieValue;
}

function clearLastWorkspaceCookie(): void
{
    setcookie(LAST_WORKSPACE_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'domain' => bootstrapConfiguredCookieDomain(),
        'secure' => requestIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[LAST_WORKSPACE_COOKIE_NAME]);
}

function lastWorkspaceIdFromCookieForUser(int $userId): ?int
{
    if ($userId <= 0) {
        return null;
    }

    $raw = trim((string) ($_COOKIE[LAST_WORKSPACE_COOKIE_NAME] ?? ''));
    if ($raw === '' || !str_contains($raw, ':')) {
        return null;
    }

    [$cookieUserId, $workspaceId] = explode(':', $raw, 2);
    if (!ctype_digit($cookieUserId) || !ctype_digit($workspaceId)) {
        return null;
    }

    if ((int) $cookieUserId !== $userId) {
        return null;
    }

    $parsedWorkspaceId = (int) $workspaceId;
    return $parsedWorkspaceId > 0 ? $parsedWorkspaceId : null;
}

function rememberCookieParts(): ?array
{
    $raw = (string) ($_COOKIE[REMEMBER_COOKIE_NAME] ?? '');
    if ($raw === '' || !str_contains($raw, ':')) {
        return null;
    }

    [$selector, $token] = explode(':', $raw, 2);
    if ($selector === '' || $token === '') {
        return null;
    }

    return [$selector, $token];
}

function revokeRememberTokenByCookie(): void
{
    $parts = rememberCookieParts();
    if (!$parts) {
        return;
    }

    [$selector] = $parts;
    $stmt = db()->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
    $stmt->execute([':selector' => $selector]);
}

function restoreRememberedSession(): void
{
    static $attempted = false;

    if ($attempted) {
        return;
    }
    $attempted = true;

    if (!empty($_SESSION['user_id'])) {
        return;
    }

    $parts = rememberCookieParts();
    if (!$parts) {
        return;
    }

    [$selector, $plainToken] = $parts;

    $stmt = db()->prepare(
        'SELECT user_id, token_hash, expires_at
         FROM remember_tokens
         WHERE selector = :selector
         LIMIT 1'
    );
    $stmt->execute([':selector' => $selector]);
    $row = $stmt->fetch();

    if (!$row) {
        clearRememberCookie();
        return;
    }

    $expiresAt = (string) ($row['expires_at'] ?? '');
    if ($expiresAt === '' || strtotime($expiresAt) === false || strtotime($expiresAt) < time()) {
        $delete = db()->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $delete->execute([':selector' => $selector]);
        clearRememberCookie();
        return;
    }

    $expectedHash = (string) ($row['token_hash'] ?? '');
    $actualHash = hash('sha256', $plainToken);

    if (!hash_equals($expectedHash, $actualHash)) {
        $delete = db()->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $delete->execute([':selector' => $selector]);
        clearRememberCookie();
        return;
    }

    $delete = db()->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
    $delete->execute([':selector' => $selector]);
    loginUser((int) $row['user_id'], true);
}

function deleteRememberTokensForUser(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $stmt = db()->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
}
