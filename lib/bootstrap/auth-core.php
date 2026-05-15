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
        throw new RuntimeException('Conta Google inválida.');
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
        throw new RuntimeException('Esta conta Google já está vinculada a outro usuário.');
    }

    if ($stmt->rowCount() > 0) {
        return;
    }

    $check = $pdo->prepare('SELECT google_id FROM users WHERE id = :id LIMIT 1');
    $check->execute([':id' => $userId]);
    $currentGoogleId = trim((string) $check->fetchColumn());
    if ($currentGoogleId !== '' && $currentGoogleId !== $googleId) {
        throw new RuntimeException('Este usuário já está vinculado a outra conta Google.');
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

function stripeTimestampToIso($value): ?string
{
    if (!is_numeric($value)) {
        return null;
    }

    $timestamp = (int) $value;
    if ($timestamp <= 0) {
        return null;
    }

    return (new DateTimeImmutable('@' . $timestamp))
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->format('Y-m-d H:i:s');
}

function uploadedUserAvatarDataUrl(array $file): string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha ao enviar a foto de perfil.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Arquivo de foto inválido.');
    }

    if ($size > 2 * 1024 * 1024) {
        throw new RuntimeException('A foto de perfil deve ter no maximo 2 MB.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException('Arquivo de foto inválido.');
    }

    $contents = file_get_contents($tmpName);
    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('Não foi possível ler a foto de perfil.');
    }

    $imageInfo = @getimagesizefromstring($contents);
    $mime = strtolower((string) ($imageInfo['mime'] ?? ''));
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!$imageInfo || !in_array($mime, $allowedMimeTypes, true)) {
        throw new RuntimeException('Use uma imagem PNG, JPG, WEBP ou GIF.');
    }

    return 'data:' . $mime . ';base64,' . base64_encode($contents);
}

function updateUserProfile(PDO $pdo, int $userId, string $name, array $avatarFile = []): void
{
    if ($userId <= 0) {
        throw new RuntimeException('Usuário inválido.');
    }

    ensureUserProfileSchema($pdo);

    $normalizedName = normalizeUserDisplayName($name);
    if ($normalizedName === '') {
        throw new RuntimeException('Informe um nome válido.');
    }

    $params = [
        ':name' => $normalizedName,
        ':id' => $userId,
    ];
    $sql = 'UPDATE users
            SET name = :name';

    $avatarDataUrl = uploadedUserAvatarDataUrl($avatarFile);
    if ($avatarDataUrl !== '') {
        $sql .= ',
                avatar_data_url = :avatar_data_url';
        $params[':avatar_data_url'] = $avatarDataUrl;
    }

    $sql .= '
            WHERE id = :id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function updateUserPassword(PDO $pdo, int $userId, string $currentPassword, string $newPassword, string $confirmPassword): void
{
    if ($userId <= 0) {
        throw new RuntimeException('Usuário inválido.');
    }

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        throw new RuntimeException('Preencha os campos de senha.');
    }

    if ($newPassword !== $confirmPassword) {
        throw new RuntimeException('A confirmação da nova senha não confere.');
    }

    if (mb_strlen($newPassword) < 6) {
        throw new RuntimeException('A nova senha deve ter pelo menos 6 caracteres.');
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $hash = (string) $stmt->fetchColumn();
    if ($hash === '') {
        throw new RuntimeException('Usuário não encontrado.');
    }

    if (!password_verify($currentPassword, $hash)) {
        throw new RuntimeException('Senha atual inválida.');
    }

    if (password_verify($newPassword, $hash)) {
        throw new RuntimeException('A nova senha deve ser diferente da senha atual.');
    }

    $updateStmt = $pdo->prepare(
        'UPDATE users
         SET password_hash = :password_hash
         WHERE id = :id'
    );
    $updateStmt->execute([
        ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':id' => $userId,
    ]);
}
