<?php
declare(strict_types=1);

function vaultSecretIsEncrypted(string $value): bool
{
    return str_starts_with($value, VAULT_SECRET_PREFIX);
}

function vaultEncryptionKey(): string
{
    static $key = null;
    if (is_string($key) && strlen($key) === 32) {
        return $key;
    }

    $configured = configuredVaultEncryptionKeyValue();
    if ($configured !== '') {
        $decoded = null;
        if (preg_match('/^[a-f0-9]{64}$/i', $configured)) {
            $decoded = hex2bin($configured);
        } else {
            $base64Candidate = preg_replace('/^base64:/i', '', $configured);
            $decodedCandidate = base64_decode((string) $base64Candidate, true);
            if (is_string($decodedCandidate) && strlen($decodedCandidate) >= 32) {
                $decoded = substr($decodedCandidate, 0, 32);
            }
        }

        $key = is_string($decoded) && strlen($decoded) === 32
            ? $decoded
            : hash('sha256', $configured, true);
        return $key;
    }

    if (!appAllowsFileBackedVaultKey()) {
        throw new RuntimeException('Configure APP_VAULT_ENCRYPTION_KEY neste ambiente para proteger o cofre.');
    }

    ensureStorage();
    if (is_file(VAULT_ENCRYPTION_KEY_PATH)) {
        $stored = trim((string) file_get_contents(VAULT_ENCRYPTION_KEY_PATH));
        $decoded = base64_decode($stored, true);
        if (is_string($decoded) && strlen($decoded) === 32) {
            $key = $decoded;
            return $key;
        }
    }

    $key = random_bytes(32);
    file_put_contents(VAULT_ENCRYPTION_KEY_PATH, base64_encode($key), LOCK_EX);
    @chmod(VAULT_ENCRYPTION_KEY_PATH, 0600);
    return $key;
}

function vaultEncryptSecret(string $plainValue): string
{
    $plainValue = (string) $plainValue;
    if ($plainValue === '' || vaultSecretIsEncrypted($plainValue)) {
        return $plainValue;
    }

    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL e obrigatorio para proteger senhas do cofre.');
    }

    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        $plainValue,
        'aes-256-gcm',
        vaultEncryptionKey(),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag,
        '',
        16
    );

    if (!is_string($ciphertext) || $ciphertext === '' || strlen($tag) !== 16) {
        throw new RuntimeException('Não foi possível proteger a senha do cofre.');
    }

    return VAULT_SECRET_PREFIX . base64_encode($nonce . $tag . $ciphertext);
}

function vaultDecryptSecret(string $storedValue): string
{
    $storedValue = (string) $storedValue;
    if ($storedValue === '' || !vaultSecretIsEncrypted($storedValue)) {
        return $storedValue;
    }

    if (!function_exists('openssl_decrypt')) {
        throw new RuntimeException('OpenSSL e obrigatorio para ler senhas do cofre.');
    }

    $payload = base64_decode(substr($storedValue, strlen(VAULT_SECRET_PREFIX)), true);
    if (!is_string($payload) || strlen($payload) <= 28) {
        throw new RuntimeException('Senha do cofre está em formato inválido.');
    }

    $nonce = substr($payload, 0, 12);
    $tag = substr($payload, 12, 16);
    $ciphertext = substr($payload, 28);

    $plainValue = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        vaultEncryptionKey(),
        OPENSSL_RAW_DATA,
        $nonce,
        $tag
    );

    if (!is_string($plainValue)) {
        throw new RuntimeException('Não foi possível descriptografar uma senha do cofre.');
    }

    return $plainValue;
}

function migratePlainVaultSecretsToEncrypted(PDO $pdo): void
{
    $rows = $pdo->query(
        'SELECT id, password_value
         FROM workspace_vault_entries
         WHERE password_value IS NOT NULL
           AND password_value <> \'\''
    )->fetchAll();

    if (!$rows) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE workspace_vault_entries
         SET password_value = :password_value
         WHERE id = :id'
    );

    foreach ($rows as $row) {
        $storedValue = (string) ($row['password_value'] ?? '');
        if ($storedValue === '' || vaultSecretIsEncrypted($storedValue)) {
            continue;
        }

        $stmt->execute([
            ':password_value' => vaultEncryptSecret($storedValue),
            ':id' => (int) ($row['id'] ?? 0),
        ]);
    }
}
