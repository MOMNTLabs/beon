<?php
declare(strict_types=1);

function normalizeUserDisplayName(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mb_strlen($value) > 120) {
        $value = mb_substr($value, 0, 120);
    }

    return uppercaseFirstCharacter($value);
}

function createUser(PDO $pdo, string $name, string $email, string $passwordHash, string $createdAt): int
{
    $columnsSql = 'name, email, password_hash, created_at';
    $valuesSql = ':n, :e, :p, :c';
    $params = [
        ':n' => $name,
        ':e' => $email,
        ':p' => $passwordHash,
        ':c' => $createdAt,
    ];

    if (tableHasColumn($pdo, 'users', 'uuid')) {
        $columnsSql .= ', uuid';
        $valuesSql .= ', :u';
        $params[':u'] = generateUuidV4();
    }

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO users (' . $columnsSql . ')
             VALUES (' . $valuesSql . ')
             RETURNING id'
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (' . $columnsSql . ')
         VALUES (' . $valuesSql . ')'
    );
    $stmt->execute($params);

    return (int) $pdo->lastInsertId();
}

function userByEmail(PDO $pdo, string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }

    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function userByGoogleId(PDO $pdo, string $googleId): ?array
{
    $googleId = trim($googleId);
    if ($googleId === '') {
        return null;
    }

    ensureGoogleAuthSchema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = :google_id LIMIT 1');
    $stmt->execute([':google_id' => $googleId]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function linkGoogleAccountForUser(PDO $pdo, int $userId, string $googleId): void
{
    $googleId = trim($googleId);
    if ($userId <= 0 || $googleId === '') {
        throw new RuntimeException('Conta Google invalida.');
    }

    ensureGoogleAuthSchema($pdo);

    try {
        $stmt = $pdo->prepare(
            'UPDATE users
             SET google_id = :google_id
             WHERE id = :id
               AND (google_id IS NULL OR google_id = \'\' OR google_id = :google_id)'
        );
        $stmt->execute([
            ':google_id' => $googleId,
            ':id' => $userId,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('Esta conta Google ja esta vinculada a outro usuario.');
    }

    if ($stmt->rowCount() > 0) {
        return;
    }

    $check = $pdo->prepare('SELECT google_id FROM users WHERE id = :id LIMIT 1');
    $check->execute([':id' => $userId]);
    $currentGoogleId = trim((string) $check->fetchColumn());
    if ($currentGoogleId !== '' && $currentGoogleId !== $googleId) {
        throw new RuntimeException('Este usuario ja esta vinculado a outra conta Google.');
    }
}

function createGoogleUser(PDO $pdo, string $name, string $email, string $googleId, string $createdAt): int
{
    $normalizedName = normalizeUserDisplayName($name);
    if ($normalizedName === '') {
        $normalizedName = strtolower(trim($email));
    }

    $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $userId = createUser($pdo, $normalizedName, strtolower(trim($email)), $passwordHash, $createdAt);
    linkGoogleAccountForUser($pdo, $userId, $googleId);

    return $userId;
}
