<?php
declare(strict_types=1);

function ensureStorage(): void
{
    $storageDir = __DIR__ . '/../..' . '/storage';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0777, true);
    }
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = dbConfig();

    if (($config['driver'] ?? 'sqlite') === 'sqlite') {
        ensureStorage();
    }

    $pdo = new PDO(
        (string) $config['dsn'],
        $config['username'] ?? null,
        $config['password'] ?? null
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $driver = dbDriverName($pdo);
    if ($driver === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }
    if (shouldAutoRunMigrations($driver)) {
        migrate($pdo);
    }

    return $pdo;
}

function dbDriverName(PDO $pdo): string
{
    return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function dbConfig(): array
{
    $databaseUrl = envValue('DATABASE_URL')
        ?? envValue('DATABASE_PRIVATE_URL')
        ?? envValue('POSTGRES_URL');

    if ($databaseUrl !== null && $databaseUrl !== '') {
        if (preg_match('/^postgres(?:ql)?:\/\//i', $databaseUrl)) {
            return postgresConfigFromUrl($databaseUrl);
        }

        if (!appAllowsSqliteFallback()) {
            throw new RuntimeException('DATABASE_URL deve apontar para PostgreSQL neste ambiente.');
        }
    }

    $pgHost = envValue('PGHOST') ?? envValue('POSTGRES_HOST');
    $pgPort = envValue('PGPORT') ?? envValue('POSTGRES_PORT') ?? '5432';
    $pgDb = envValue('PGDATABASE') ?? envValue('POSTGRES_DB');
    $pgUser = envValue('PGUSER') ?? envValue('POSTGRES_USER');
    $pgPass = envValue('PGPASSWORD') ?? envValue('POSTGRES_PASSWORD');
    $pgSslMode = envValue('PGSSLMODE');

    if ($pgHost && $pgDb && $pgUser !== null) {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $pgHost,
            $pgPort,
            $pgDb
        );
        if ($pgSslMode) {
            $dsn .= ';sslmode=' . $pgSslMode;
        }

        return [
            'driver' => 'pgsql',
            'dsn' => $dsn,
            'username' => $pgUser,
            'password' => $pgPass,
        ];
    }

    if (!appAllowsSqliteFallback()) {
        throw new RuntimeException('Configure DATABASE_URL ou as credenciais PG* para executar o Bexon neste ambiente.');
    }

    return [
        'driver' => 'sqlite',
        'dsn' => 'sqlite:' . DB_PATH,
        'username' => null,
        'password' => null,
    ];
}

function shouldAutoRunMigrations(string $driver = ''): bool
{
    if ($driver === 'sqlite') {
        return PHP_SAPI !== 'cli';
    }

    if (PHP_SAPI === 'cli') {
        return false;
    }

    if (appUsesProductionGuards()) {
        return false;
    }

    return envFlag('APP_AUTO_MIGRATE', false);
}

function shouldApplyOverduePolicyDuringRequests(): bool
{
    return envFlag('APP_AUTO_OVERDUE_POLICY', false);
}

function postgresConfigFromUrl(string $databaseUrl): array
{
    $parts = parse_url($databaseUrl);
    if ($parts === false) {
        throw new RuntimeException('DATABASE_URL inválida.');
    }

    $host = $parts['host'] ?? null;
    $port = isset($parts['port']) ? (string) $parts['port'] : '5432';
    $dbName = isset($parts['path']) ? ltrim((string) $parts['path'], '/') : '';
    $dbName = rawurldecode($dbName);
    $user = isset($parts['user']) ? rawurldecode((string) $parts['user']) : null;
    $pass = isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : null;

    if (!$host || $dbName === '' || $user === null) {
        throw new RuntimeException('DATABASE_URL incompleta para PostgreSQL.');
    }

    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbName);

    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $query);
        if (!empty($query['sslmode'])) {
            $dsn .= ';sslmode=' . $query['sslmode'];
        }
    }

    return [
        'driver' => 'pgsql',
        'dsn' => $dsn,
        'username' => $user,
        'password' => $pass,
    ];
}
