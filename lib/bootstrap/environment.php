<?php
declare(strict_types=1);

function envValue(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function envFlag(string $key, bool $default = false): bool
{
    $value = envValue($key);
    if ($value === null) {
        return $default;
    }

    $normalized = strtolower(trim($value));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function appIsRailway(): bool
{
    foreach ([
        'RAILWAY_PROJECT_ID',
        'RAILWAY_SERVICE_ID',
        'RAILWAY_ENVIRONMENT_ID',
        'RAILWAY_PUBLIC_DOMAIN',
    ] as $key) {
        if (envValue($key) !== null) {
            return true;
        }
    }

    return false;
}

function appEnvironment(): string
{
    $configured = strtolower(trim((string) (
        envValue('APP_ENV')
        ?? envValue('APPLICATION_ENV')
        ?? envValue('ENVIRONMENT')
        ?? ''
    )));
    if ($configured !== '') {
        return $configured;
    }

    if (appIsRailway()) {
        return 'production';
    }

    $host = bootstrapRequestHostName();
    if (
        in_array($host, ['localhost', '127.0.0.1'], true)
        || str_ends_with($host, '.local')
        || str_ends_with($host, '.test')
    ) {
        return 'local';
    }

    return PHP_SAPI === 'cli' ? 'local' : 'production';
}

function appUsesProductionGuards(): bool
{
    if (envFlag('APP_PRODUCTION_GUARDS', false)) {
        return true;
    }

    return in_array(appEnvironment(), ['production', 'prod', 'staging', 'stage'], true)
        || appIsRailway();
}

function appAllowsSqliteFallback(): bool
{
    if (envFlag('APP_ALLOW_SQLITE_FALLBACK', false)) {
        return true;
    }

    return !appUsesProductionGuards();
}

function appAllowsFileBackedVaultKey(): bool
{
    if (envFlag('APP_ALLOW_FILE_VAULT_KEY', false)) {
        return true;
    }

    return !appUsesProductionGuards();
}

function appCanWriteDiagnosticFiles(): bool
{
    if (envFlag('APP_DIAGNOSTIC_FILES', false)) {
        return true;
    }

    return !appUsesProductionGuards();
}

function configuredVaultEncryptionKeyValue(): string
{
    return trim((string) (envValue('APP_VAULT_ENCRYPTION_KEY') ?? envValue('VAULT_ENCRYPTION_KEY') ?? ''));
}

function productionConfigDiagnostics(): array
{
    $errors = [];
    $warnings = [];

    if (configuredAppUrl() === '') {
        $errors[] = 'APP_URL ausente ou invalida para este ambiente.';
    }

    if (configuredSiteUrl() === '') {
        $warnings[] = 'SITE_URL nao definida; links de volta para o site podem apontar para o host atual.';
    }

    if (trim((string) envValue('COOKIE_DOMAIN', '')) === '') {
        $warnings[] = 'COOKIE_DOMAIN nao definido; revise cookies compartilhados entre app e site.';
    }

    if (envFlag('APP_AUTO_MIGRATE', false)) {
        $errors[] = 'APP_AUTO_MIGRATE deve permanecer false em producao.';
    }

    if (configuredVaultEncryptionKeyValue() === '') {
        $errors[] = 'APP_VAULT_ENCRYPTION_KEY nao definida.';
    }

    if (envFlag('APP_ALLOW_SQLITE_FALLBACK', false)) {
        $warnings[] = 'APP_ALLOW_SQLITE_FALLBACK=true esta ativo; isso nao e recomendado em producao.';
    }

    if (envFlag('APP_ALLOW_FILE_VAULT_KEY', false)) {
        $warnings[] = 'APP_ALLOW_FILE_VAULT_KEY=true esta ativo; isso nao e recomendado em producao.';
    }

    if (envFlag('APP_DIAGNOSTIC_FILES', false)) {
        $warnings[] = 'APP_DIAGNOSTIC_FILES=true esta ativo; fallbacks podem voltar a gravar arquivos locais.';
    }

    try {
        $config = dbConfig();
        $driver = (string) ($config['driver'] ?? '');
        if ($driver !== 'pgsql') {
            $errors[] = 'A configuracao de banco em producao deve resolver para PostgreSQL.';
        }

        if ($driver === 'pgsql' && !extension_loaded('pdo_pgsql')) {
            $errors[] = 'A extensao pdo_pgsql nao esta carregada neste runtime.';
        }

        $dsn = strtolower((string) ($config['dsn'] ?? ''));
        if ($driver === 'pgsql' && !str_contains($dsn, 'sslmode=require')) {
            $warnings[] = 'A conexao PostgreSQL nao declara sslmode=require.';
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }

    if (trim((string) envValue('MAIL_FROM_ADDRESS', '')) === '') {
        $warnings[] = 'MAIL_FROM_ADDRESS nao definido; envio de e-mail pode cair em fallback.';
    }

    if (trim((string) envValue('RESEND_API_KEY', '')) === '') {
        $warnings[] = 'RESEND_API_KEY nao definido; envio de e-mail dependera do mail() do ambiente.';
    }

    return [
        'environment' => appEnvironment(),
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

function assetVersion(string $relativePath, string $fallback = '1'): string
{
    $path = __DIR__ . '/../..' . '/' . ltrim($relativePath, '/\\');
    return is_file($path) ? (string) filemtime($path) : $fallback;
}
