<?php
declare(strict_types=1);

function passwordResetPath(string $selector, string $token, bool $absolute = false): string
{
    $query = http_build_query([
        'action' => 'reset_password',
        'selector' => $selector,
        'token' => $token,
    ]);

    $path = appPath('?' . $query . '#reset-password');
    if (!$absolute) {
        return $path;
    }

    return appEntryUrl() . '/?' . $query . '#reset-password';
}

function pruneExpiredPasswordResetTokens(): void
{
    $stmt = db()->prepare(
        'DELETE FROM password_reset_tokens
         WHERE expires_at <= :expires_at'
    );
    $stmt->execute([':expires_at' => nowIso()]);
}

function deletePasswordResetTokensForUser(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $stmt = db()->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
}

function issuePasswordResetToken(int $userId): array
{
    if ($userId <= 0) {
        throw new RuntimeException('UsuÃ¡rio invÃ¡lido para redefiniÃ§Ã£o de senha.');
    }

    $pdo = db();
    pruneExpiredPasswordResetTokens();
    deletePasswordResetTokensForUser($userId);

    $selector = bin2hex(random_bytes(9));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+' . PASSWORD_RESET_TOKEN_HOURS . ' hour'))->format('Y-m-d H:i:s');
    $createdAt = nowIso();

    $stmt = $pdo->prepare(
        'INSERT INTO password_reset_tokens (user_id, selector, token_hash, expires_at, created_at)
         VALUES (:user_id, :selector, :token_hash, :expires_at, :created_at)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':selector' => $selector,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiresAt,
        ':created_at' => $createdAt,
    ]);

    return [
        'selector' => $selector,
        'token' => $token,
        'expires_at' => $expiresAt,
        'path' => passwordResetPath($selector, $token, false),
        'url' => passwordResetPath($selector, $token, true),
    ];
}

function validPasswordResetRequest(string $selector, string $plainToken): ?array
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

    pruneExpiredPasswordResetTokens();

    $stmt = db()->prepare(
        'SELECT prt.user_id, prt.token_hash, prt.expires_at, u.email, u.name
         FROM password_reset_tokens prt
         INNER JOIN users u ON u.id = prt.user_id
         WHERE prt.selector = :selector
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
        $delete = db()->prepare('DELETE FROM password_reset_tokens WHERE selector = :selector');
        $delete->execute([':selector' => $selector]);
        return null;
    }

    return $row;
}
