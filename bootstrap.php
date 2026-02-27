<?php
declare(strict_types=1);

session_start();

date_default_timezone_set('America/Sao_Paulo');

const APP_NAME = 'WorkForm';
const DB_PATH = __DIR__ . '/storage/app.sqlite';
const REMEMBER_COOKIE_NAME = 'wf_remember';
const REMEMBER_TOKEN_DAYS = 30;

function ensureStorage(): void
{
    $storageDir = __DIR__ . '/storage';
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
    if (dbDriverName($pdo) === 'sqlite') {
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }
    migrate($pdo);

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

    if ($databaseUrl && preg_match('/^postgres(?:ql)?:\/\//i', $databaseUrl)) {
        return postgresConfigFromUrl($databaseUrl);
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

    return [
        'driver' => 'sqlite',
        'dsn' => 'sqlite:' . DB_PATH,
        'username' => null,
        'password' => null,
    ];
}

function envValue(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function postgresConfigFromUrl(string $databaseUrl): array
{
    $parts = parse_url($databaseUrl);
    if ($parts === false) {
        throw new RuntimeException('DATABASE_URL invalida.');
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

function migrate(PDO $pdo): void
{
    if (dbDriverName($pdo) === 'pgsql') {
        migratePostgres($pdo);
    } else {
        migrateSqlite($pdo);
    }

    ensureTaskExtendedSchema($pdo);
    ensureTaskGroupsSchema($pdo);
    ensureTaskHistorySchema($pdo);
}

function migrateSqlite(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT \'\',
            status TEXT NOT NULL,
            priority TEXT NOT NULL,
            due_date TEXT DEFAULT NULL,
            overdue_flag INTEGER NOT NULL DEFAULT 0,
            overdue_since_date TEXT DEFAULT NULL,
            created_by INTEGER NOT NULL,
            assigned_to INTEGER DEFAULT NULL,
            group_name TEXT NOT NULL DEFAULT \'Geral\',
            assignee_ids_json TEXT NOT NULL DEFAULT \'[]\',
            reference_links_json TEXT NOT NULL DEFAULT \'[]\',
            reference_images_json TEXT NOT NULL DEFAULT \'[]\',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS task_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            created_by INTEGER DEFAULT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS task_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id INTEGER NOT NULL,
            actor_user_id INTEGER DEFAULT NULL,
            event_type TEXT NOT NULL,
            payload_json TEXT NOT NULL DEFAULT \'{}\',
            created_at TEXT NOT NULL,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS remember_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            selector TEXT NOT NULL UNIQUE,
            token_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );
}

function migratePostgres(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id BIGSERIAL PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS tasks (
            id BIGSERIAL PRIMARY KEY,
            title TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT \'\',
            status VARCHAR(32) NOT NULL,
            priority VARCHAR(32) NOT NULL,
            due_date DATE DEFAULT NULL,
            overdue_flag SMALLINT NOT NULL DEFAULT 0,
            overdue_since_date DATE DEFAULT NULL,
            created_by BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            assigned_to BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
            group_name TEXT NOT NULL DEFAULT \'Geral\',
            assignee_ids_json TEXT NOT NULL DEFAULT \'[]\',
            reference_links_json TEXT NOT NULL DEFAULT \'[]\',
            reference_images_json TEXT NOT NULL DEFAULT \'[]\',
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS task_groups (
            id BIGSERIAL PRIMARY KEY,
            name TEXT NOT NULL UNIQUE,
            created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS task_history (
            id BIGSERIAL PRIMARY KEY,
            task_id BIGINT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
            actor_user_id BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
            event_type TEXT NOT NULL,
            payload_json TEXT NOT NULL DEFAULT \'{}\',
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS remember_tokens (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            selector TEXT NOT NULL UNIQUE,
            token_hash TEXT NOT NULL,
            expires_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
        )'
    );
}

function ensureTaskExtendedSchema(PDO $pdo): void
{
    if (!tableHasColumn($pdo, 'tasks', 'group_name')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN group_name TEXT NOT NULL DEFAULT 'Geral'");
    }

    if (!tableHasColumn($pdo, 'tasks', 'assignee_ids_json')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN assignee_ids_json TEXT NOT NULL DEFAULT '[]'");
    }
    if (!tableHasColumn($pdo, 'tasks', 'reference_links_json')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN reference_links_json TEXT NOT NULL DEFAULT '[]'");
    }
    if (!tableHasColumn($pdo, 'tasks', 'reference_images_json')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN reference_images_json TEXT NOT NULL DEFAULT '[]'");
    }
    if (!tableHasColumn($pdo, 'tasks', 'overdue_flag')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN overdue_flag INTEGER NOT NULL DEFAULT 0");
    }
    if (!tableHasColumn($pdo, 'tasks', 'overdue_since_date')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN overdue_since_date DATE DEFAULT NULL");
        } else {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN overdue_since_date TEXT DEFAULT NULL");
        }
    }

    $stmt = $pdo->query('SELECT id, assigned_to, group_name, assignee_ids_json, reference_links_json, reference_images_json FROM tasks');
    $rows = $stmt ? $stmt->fetchAll() : [];

    $update = $pdo->prepare(
        'UPDATE tasks
         SET group_name = :group_name,
             assignee_ids_json = :assignee_ids_json,
             reference_links_json = :reference_links_json,
             reference_images_json = :reference_images_json
         WHERE id = :id'
    );

    foreach ($rows as $row) {
        $groupName = normalizeTaskGroupName((string) ($row['group_name'] ?? ''));
        $assigneeIds = decodeAssigneeIds(
            $row['assignee_ids_json'] ?? null,
            isset($row['assigned_to']) ? (int) $row['assigned_to'] : null
        );
        $referenceLinks = decodeReferenceUrlList($row['reference_links_json'] ?? null);
        $referenceImages = decodeReferenceUrlList($row['reference_images_json'] ?? null);

        $update->execute([
            ':group_name' => $groupName,
            ':assignee_ids_json' => encodeAssigneeIds($assigneeIds),
            ':reference_links_json' => encodeReferenceUrlList($referenceLinks),
            ':reference_images_json' => encodeReferenceUrlList($referenceImages),
            ':id' => (int) $row['id'],
        ]);
    }
}

function ensureTaskHistorySchema(PDO $pdo): void
{
    if (dbDriverName($pdo) === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS task_history (
                id BIGSERIAL PRIMARY KEY,
                task_id BIGINT NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
                actor_user_id BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                event_type TEXT NOT NULL,
                payload_json TEXT NOT NULL DEFAULT \'{}\',
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_task_history_task_created
             ON task_history(task_id, created_at)'
        );
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS task_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            task_id INTEGER NOT NULL,
            actor_user_id INTEGER DEFAULT NULL,
            event_type TEXT NOT NULL,
            payload_json TEXT NOT NULL DEFAULT \'{}\',
            created_at TEXT NOT NULL,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
        )'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_task_history_task_created
         ON task_history(task_id, created_at)'
    );
}

function ensureTaskGroupsSchema(PDO $pdo): void
{
    if (dbDriverName($pdo) === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS task_groups (
                id BIGSERIAL PRIMARY KEY,
                name TEXT NOT NULL UNIQUE,
                created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS task_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
    }

    // Keep explicit groups in sync with task rows created before this table existed.
    $rows = $pdo->query(
        'SELECT DISTINCT group_name, MIN(created_by) AS created_by
         FROM tasks
         WHERE group_name IS NOT NULL AND group_name <> \'\'
         GROUP BY group_name'
    )->fetchAll();

    foreach ($rows as $row) {
        upsertTaskGroup(
            $pdo,
            (string) ($row['group_name'] ?? 'Geral'),
            isset($row['created_by']) ? (int) $row['created_by'] : null
        );
    }

    $groupCountStmt = $pdo->query('SELECT COUNT(*) FROM task_groups');
    $groupCount = $groupCountStmt ? (int) $groupCountStmt->fetchColumn() : 0;
    if ($groupCount <= 0) {
        upsertTaskGroup($pdo, 'Geral', null);
    }
}

function tableHasColumn(PDO $pdo, string $table, string $column): bool
{
    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = current_schema()
               AND table_name = :table
               AND column_name = :column
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    $columns = $stmt ? $stmt->fetchAll() : [];

    foreach ($columns as $info) {
        if ((string) ($info['name'] ?? '') === $column) {
            return true;
        }
    }

    return false;
}

function createUser(PDO $pdo, string $name, string $email, string $passwordHash, string $createdAt): int
{
    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, created_at)
             VALUES (:n, :e, :p, :c)
             RETURNING id'
        );
        $stmt->execute([
            ':n' => $name,
            ':e' => $email,
            ':p' => $passwordHash,
            ':c' => $createdAt,
        ]);

        return (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, created_at)
         VALUES (:n, :e, :p, :c)'
    );
    $stmt->execute([
        ':n' => $name,
        ':e' => $email,
        ':p' => $passwordHash,
        ':c' => $createdAt,
    ]);

    return (int) $pdo->lastInsertId();
}

function loginUser(int $userId, bool $remember = true): void
{
    $_SESSION['user_id'] = $userId;
    session_regenerate_id(true);

    if ($remember) {
        issueRememberToken($userId);
    }
}

function logoutUser(): void
{
    revokeRememberTokenByCookie();
    clearRememberCookie();
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
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

function setRememberCookie(string $selector, string $token, int $expiresTs): void
{
    $cookieValue = $selector . ':' . $token;

    setcookie(REMEMBER_COOKIE_NAME, $cookieValue, [
        'expires' => $expiresTs,
        'path' => '/',
        'domain' => '',
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
        'domain' => '',
        'secure' => requestIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

function requestIsHttps(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https === 'on' || $https === '1') {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto === 'https') {
        return true;
    }

    return ((int) ($_SERVER['SERVER_PORT'] ?? 0)) === 443;
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

    // Rotate token on successful remember-login.
    $delete = db()->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
    $delete->execute([':selector' => $selector]);
    loginUser((int) $row['user_id'], true);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function nowIso(): string
{
    return (new DateTimeImmutable())->format('Y-m-d H:i:s');
}

function redirectTo(string $path = 'index.php'): void
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
        http_response_code(419);
        exit('Token CSRF inválido.');
    }
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

    $stmt = db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        logoutUser();
        return null;
    }

    return $user;
}

function requireAuth(): array
{
    $user = currentUser();
    if (!$user) {
        flash('error', 'Faça login para continuar.');
        redirectTo('index.php');
    }

    return $user;
}

function usersList(): array
{
    return db()->query('SELECT id, name, email FROM users ORDER BY name ASC')->fetchAll();
}

function usersMapById(): array
{
    $map = [];
    foreach (usersList() as $user) {
        $map[(int) $user['id']] = $user;
    }

    return $map;
}

function taskStatuses(): array
{
    return [
        'todo' => 'Backlog',
        'in_progress' => 'Em andamento',
        'review' => 'Revisão',
        'done' => 'Concluído',
    ];
}

function taskPriorities(): array
{
    return [
        'low' => 'Baixa',
        'medium' => 'Média',
        'high' => 'Alta',
        'urgent' => 'Urgente',
    ];
}

function normalizeTaskStatus(string $value): string
{
    return array_key_exists($value, taskStatuses()) ? $value : 'todo';
}

function normalizeTaskPriority(string $value): string
{
    return array_key_exists($value, taskPriorities()) ? $value : 'medium';
}

function dueDateForStorage(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $date ? $date->format('Y-m-d') : null;
}

function taskOverdueDays(?string $overdueSinceDate): int
{
    $overdueSince = dueDateForStorage($overdueSinceDate);
    if ($overdueSince === null) {
        return 0;
    }

    $today = new DateTimeImmutable('today');
    $since = DateTimeImmutable::createFromFormat('Y-m-d', $overdueSince);
    if (!$since) {
        return 0;
    }

    $days = (int) $since->diff($today)->format('%r%a');
    return max(0, $days);
}

function normalizeTaskOverdueState(
    string $status,
    string $priority,
    ?string $dueDate,
    int $overdueFlag = 0,
    ?string $overdueSinceDate = null
): array {
    $status = normalizeTaskStatus($status);
    $priority = normalizeTaskPriority($priority);
    $overdueFlag = $overdueFlag === 1 ? 1 : 0;
    $overdueSinceDate = dueDateForStorage($overdueSinceDate);

    if ($status === 'done' || $dueDate === null) {
        return [
            'status' => $status,
            'priority' => $priority,
            'due_date' => $dueDate,
            'overdue_flag' => 0,
            'overdue_since_date' => null,
            'overdue_days' => 0,
        ];
    }

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    if ($dueDate < $today) {
        $overdueSince = $overdueSinceDate ?? $dueDate;
        return [
            'status' => $status,
            'priority' => 'urgent',
            'due_date' => $today,
            'overdue_flag' => 1,
            'overdue_since_date' => $overdueSince,
            'overdue_days' => taskOverdueDays($overdueSince),
        ];
    }

    if ($dueDate > $today) {
        return [
            'status' => $status,
            'priority' => $priority,
            'due_date' => $dueDate,
            'overdue_flag' => 0,
            'overdue_since_date' => null,
            'overdue_days' => 0,
        ];
    }

    return [
        'status' => $status,
        'priority' => $priority,
        'due_date' => $dueDate,
        'overdue_flag' => $overdueFlag,
        'overdue_since_date' => $overdueFlag === 1 ? ($overdueSinceDate ?? $dueDate) : null,
        'overdue_days' => $overdueFlag === 1 ? taskOverdueDays($overdueSinceDate ?? $dueDate) : 0,
    ];
}

function encodeTaskHistoryPayload(array $payload): string
{
    return json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
}

function decodeTaskHistoryPayload($value): array
{
    $raw = is_string($value) ? trim($value) : '';
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function logTaskHistory(
    PDO $pdo,
    int $taskId,
    string $eventType,
    array $payload = [],
    ?int $actorUserId = null,
    ?string $createdAt = null
): void {
    if ($taskId <= 0 || trim($eventType) === '') {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO task_history (task_id, actor_user_id, event_type, payload_json, created_at)
         VALUES (:task_id, :actor_user_id, :event_type, :payload_json, :created_at)'
    );

    $stmt->execute([
        ':task_id' => $taskId,
        ':actor_user_id' => $actorUserId,
        ':event_type' => trim($eventType),
        ':payload_json' => encodeTaskHistoryPayload($payload),
        ':created_at' => $createdAt ?: nowIso(),
    ]);
}

function taskHistoryList(int $taskId, int $limit = 80): array
{
    if ($taskId <= 0) {
        return [];
    }

    $limit = max(1, min($limit, 300));
    $sql = dbDriverName(db()) === 'pgsql'
        ? 'SELECT
               h.id,
               h.task_id,
               h.event_type,
               h.payload_json,
               h.created_at,
               u.name AS actor_name
           FROM task_history h
           LEFT JOIN users u ON u.id = h.actor_user_id
           WHERE h.task_id = :task_id
           ORDER BY h.created_at DESC, h.id DESC
           LIMIT ' . $limit
        : 'SELECT
               h.id,
               h.task_id,
               h.event_type,
               h.payload_json,
               h.created_at,
               u.name AS actor_name
           FROM task_history h
           LEFT JOIN users u ON u.id = h.actor_user_id
           WHERE h.task_id = :task_id
           ORDER BY h.created_at DESC, h.id DESC
           LIMIT ' . $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute([':task_id' => $taskId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['payload'] = decodeTaskHistoryPayload($row['payload_json'] ?? null);
        unset($row['payload_json']);
    }
    unset($row);

    return $rows;
}

function taskHistoryByTaskIds(array $taskIds, int $limitPerTask = 80): array
{
    $ids = array_values(array_unique(array_map('intval', $taskIds)));
    $ids = array_values(array_filter($ids, static fn (int $id) => $id > 0));
    if (!$ids) {
        return [];
    }

    $limitPerTask = max(1, min($limitPerTask, 300));
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $sql = dbDriverName(db()) === 'pgsql'
        ? 'SELECT
               h.id,
               h.task_id,
               h.event_type,
               h.payload_json,
               h.created_at,
               u.name AS actor_name
           FROM task_history h
           LEFT JOIN users u ON u.id = h.actor_user_id
           WHERE h.task_id IN (' . $placeholders . ')
           ORDER BY h.task_id ASC, h.created_at DESC, h.id DESC'
        : 'SELECT
               h.id,
               h.task_id,
               h.event_type,
               h.payload_json,
               h.created_at,
               u.name AS actor_name
           FROM task_history h
           LEFT JOIN users u ON u.id = h.actor_user_id
           WHERE h.task_id IN (' . $placeholders . ')
           ORDER BY h.task_id ASC, h.created_at DESC, h.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $taskId = (int) ($row['task_id'] ?? 0);
        if ($taskId <= 0) {
            continue;
        }
        if (!isset($grouped[$taskId])) {
            $grouped[$taskId] = [];
        }
        if (count($grouped[$taskId]) >= $limitPerTask) {
            continue;
        }
        $row['payload'] = decodeTaskHistoryPayload($row['payload_json'] ?? null);
        unset($row['payload_json']);
        $grouped[$taskId][] = $row;
    }

    return $grouped;
}

function applyOverdueTaskPolicy(): int
{
    $pdo = db();
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $updatedAt = nowIso();

    $select = $pdo->prepare(
        'SELECT id, due_date, overdue_flag, overdue_since_date
         FROM tasks
         WHERE status <> :done
           AND COALESCE(NULLIF(CAST(due_date AS TEXT), \'\'), \'\') <> \'\'
           AND CAST(due_date AS TEXT) < :today'
    );
    $select->execute([
        ':done' => 'done',
        ':today' => $today,
    ]);

    $rows = $select->fetchAll();
    if (!$rows) {
        return 0;
    }

    $update = $pdo->prepare(
        'UPDATE tasks
         SET due_date = :today,
             priority = :urgent,
             overdue_flag = 1,
             overdue_since_date = :overdue_since_date,
             updated_at = :updated_at
         WHERE id = :id'
    );

    $changed = 0;
    foreach ($rows as $row) {
        $taskId = (int) ($row['id'] ?? 0);
        if ($taskId <= 0) {
            continue;
        }
        $originalDueDate = dueDateForStorage((string) ($row['due_date'] ?? ''));
        if ($originalDueDate === null) {
            continue;
        }

        $previousOverdueFlag = ((int) ($row['overdue_flag'] ?? 0)) === 1 ? 1 : 0;
        $overdueSinceDate = dueDateForStorage((string) ($row['overdue_since_date'] ?? '')) ?? $originalDueDate;

        $update->execute([
            ':today' => $today,
            ':urgent' => 'urgent',
            ':overdue_since_date' => $overdueSinceDate,
            ':updated_at' => $updatedAt,
            ':id' => $taskId,
        ]);

        $changed += $update->rowCount();

        if ($previousOverdueFlag !== 1) {
            logTaskHistory(
                $pdo,
                $taskId,
                'overdue_started',
                [
                    'previous_due_date' => $originalDueDate,
                    'new_due_date' => $today,
                    'overdue_since_date' => $overdueSinceDate,
                    'overdue_days' => taskOverdueDays($overdueSinceDate),
                ],
                null,
                $updatedAt
            );
        }
    }

    return $changed;
}

function normalizeTaskGroupName(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'Geral';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mb_strlen($value) > 60) {
        $value = mb_substr($value, 0, 60);
    }

    return $value;
}

function normalizeAssigneeIds(array $values, ?array $usersById = null): array
{
    $result = [];

    foreach ($values as $value) {
        $id = (int) $value;
        if ($id <= 0) {
            continue;
        }
        if ($usersById !== null && !isset($usersById[$id])) {
            continue;
        }
        $result[$id] = $id;
    }

    return array_values($result);
}

function encodeAssigneeIds(array $ids): string
{
    $normalized = normalizeAssigneeIds($ids);
    return json_encode($normalized, JSON_UNESCAPED_UNICODE) ?: '[]';
}

function decodeAssigneeIds($jsonValue, ?int $fallbackAssignedTo = null): array
{
    $raw = is_string($jsonValue) ? trim($jsonValue) : '';
    $decoded = [];

    if ($raw !== '') {
        $value = json_decode($raw, true);
        if (is_array($value)) {
            $decoded = $value;
        }
    }

    if (!$decoded && $fallbackAssignedTo !== null && $fallbackAssignedTo > 0) {
        $decoded = [$fallbackAssignedTo];
    }

    return normalizeAssigneeIds($decoded);
}

function normalizeReferenceUrlList($value, int $maxItems = 20): array
{
    if (is_string($value)) {
        $raw = trim($value);
        if ($raw === '') {
            $value = [];
        } else {
            $decoded = json_decode($raw, true);
            $value = is_array($decoded) ? $decoded : preg_split('/\R+/u', $raw);
        }
    }

    if (!is_array($value)) {
        $value = [$value];
    }

    $result = [];

    foreach ($value as $item) {
        $url = trim((string) $item);
        if ($url === '') {
            continue;
        }
        if (mb_strlen($url) > 1000) {
            $url = mb_substr($url, 0, 1000);
        }

        $validated = filter_var($url, FILTER_VALIDATE_URL);
        if ($validated === false) {
            continue;
        }

        $scheme = strtolower((string) parse_url($validated, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            continue;
        }

        $result[$validated] = $validated;
        if (count($result) >= $maxItems) {
            break;
        }
    }

    return array_values($result);
}

function encodeReferenceUrlList(array $urls): string
{
    return json_encode(
        normalizeReferenceUrlList($urls),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: '[]';
}

function decodeReferenceUrlList($value): array
{
    return normalizeReferenceUrlList($value);
}

function findTaskGroupByName(string $groupName): ?string
{
    $needle = mb_strtolower(normalizeTaskGroupName($groupName));

    foreach (taskGroupsList() as $existingName) {
        if (mb_strtolower($existingName) === $needle) {
            return $existingName;
        }
    }

    return null;
}

function defaultTaskGroupName(): string
{
    $pdo = db();

    $row = $pdo->query('SELECT name FROM task_groups ORDER BY id ASC LIMIT 1')->fetch();
    $groupName = trim((string) ($row['name'] ?? ''));
    if ($groupName !== '') {
        return normalizeTaskGroupName($groupName);
    }

    $taskRow = $pdo->query(
        "SELECT group_name
         FROM tasks
         WHERE group_name IS NOT NULL AND group_name <> ''
         ORDER BY id ASC
         LIMIT 1"
    )->fetch();
    $taskGroupName = trim((string) ($taskRow['group_name'] ?? ''));
    if ($taskGroupName !== '') {
        $normalized = normalizeTaskGroupName($taskGroupName);
        upsertTaskGroup($pdo, $normalized, null);
        return $normalized;
    }

    upsertTaskGroup($pdo, 'Geral', null);
    return 'Geral';
}

function isProtectedTaskGroupName(string $groupName): bool
{
    return mb_strtolower(normalizeTaskGroupName($groupName)) === mb_strtolower(defaultTaskGroupName());
}

function upsertTaskGroup(PDO $pdo, string $groupName, ?int $createdBy = null): string
{
    $normalizedName = normalizeTaskGroupName($groupName);
    $now = nowIso();
    $driver = dbDriverName($pdo);

    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO task_groups (name, created_by, created_at)
             VALUES (:name, :created_by, :created_at)
             ON CONFLICT (name) DO NOTHING'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO task_groups (name, created_by, created_at)
             VALUES (:name, :created_by, :created_at)'
        );
    }

    $stmt->bindValue(':name', $normalizedName, PDO::PARAM_STR);
    if ($createdBy !== null && $createdBy > 0) {
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':created_at', $now, PDO::PARAM_STR);
    $stmt->execute();

    return $normalizedName;
}

function taskGroupsList(): array
{
    $pdo = db();
    $groups = [];

    $storedRows = $pdo->query('SELECT name FROM task_groups ORDER BY name ASC')->fetchAll();
    foreach ($storedRows as $row) {
        $groupName = normalizeTaskGroupName((string) ($row['name'] ?? 'Geral'));
        $groups[$groupName] = $groupName;
    }

    $rows = $pdo->query('SELECT DISTINCT group_name FROM tasks WHERE group_name IS NOT NULL AND group_name <> \'\' ORDER BY group_name ASC')->fetchAll();

    foreach ($rows as $row) {
        $groupName = normalizeTaskGroupName((string) ($row['group_name'] ?? 'Geral'));
        $groups[$groupName] = $groupName;
    }

    if (!$groups) {
        return ['Geral'];
    }

    $values = array_values($groups);
    natcasesort($values);
    return array_values($values);
}

function allTasks(): array
{
    $sql = 'SELECT
                t.*,
                creator.name AS creator_name,
                creator.email AS creator_email
            FROM tasks t
            INNER JOIN users creator ON creator.id = t.created_by
            ORDER BY
                t.group_name ASC,
                CASE t.status
                    WHEN \'review\' THEN 1
                    WHEN \'in_progress\' THEN 2
                    WHEN \'todo\' THEN 3
                    WHEN \'done\' THEN 4
                    ELSE 5
                END,
                CASE t.priority
                    WHEN \'urgent\' THEN 1
                    WHEN \'high\' THEN 2
                    WHEN \'medium\' THEN 3
                    WHEN \'low\' THEN 4
                    ELSE 5
                END,
                CASE WHEN COALESCE(NULLIF(CAST(t.due_date AS TEXT), \'\'), \'\') = \'\' THEN 1 ELSE 0 END,
                t.due_date ASC,
                t.updated_at DESC';

    $pdo = db();
    $tasks = $pdo->query($sql)->fetchAll();
    $usersById = usersMapById();
    $historyByTaskId = taskHistoryByTaskIds(array_map(static fn ($task) => (int) ($task['id'] ?? 0), $tasks));

    foreach ($tasks as &$task) {
        $task['status'] = normalizeTaskStatus((string) ($task['status'] ?? 'todo'));
        $task['priority'] = normalizeTaskPriority((string) ($task['priority'] ?? 'medium'));
        $task['due_date'] = dueDateForStorage((string) ($task['due_date'] ?? ''));
        $task['group_name'] = normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral'));
        $task['overdue_flag'] = ((int) ($task['overdue_flag'] ?? 0)) === 1 ? 1 : 0;
        $task['overdue_since_date'] = dueDateForStorage((string) ($task['overdue_since_date'] ?? ''));
        $task['overdue_days'] = $task['overdue_flag'] === 1
            ? taskOverdueDays($task['overdue_since_date'])
            : 0;
        $assigneeIds = decodeAssigneeIds(
            $task['assignee_ids_json'] ?? null,
            isset($task['assigned_to']) ? (int) $task['assigned_to'] : null
        );
        $assigneeIds = normalizeAssigneeIds($assigneeIds, $usersById);

        $task['assignee_ids'] = $assigneeIds;
        $task['reference_links'] = decodeReferenceUrlList($task['reference_links_json'] ?? null);
        $task['reference_images'] = decodeReferenceUrlList($task['reference_images_json'] ?? null);
        $task['assignees'] = [];

        foreach ($assigneeIds as $id) {
            if (isset($usersById[$id])) {
                $task['assignees'][] = $usersById[$id];
            }
        }

        $taskId = (int) ($task['id'] ?? 0);
        $task['history'] = $taskId > 0 ? ($historyByTaskId[$taskId] ?? []) : [];
        if ($taskId > 0) {
            $hasCreatedEvent = false;
            foreach ($task['history'] as $event) {
                if ((string) ($event['event_type'] ?? '') === 'created') {
                    $hasCreatedEvent = true;
                    break;
                }
            }

            if (!$hasCreatedEvent) {
                $task['history'][] = [
                    'id' => 0,
                    'task_id' => $taskId,
                    'event_type' => 'created',
                    'payload' => [
                        'title' => (string) ($task['title'] ?? ''),
                        'status' => normalizeTaskStatus((string) ($task['status'] ?? 'todo')),
                        'priority' => normalizeTaskPriority((string) ($task['priority'] ?? 'medium')),
                        'due_date' => dueDateForStorage((string) ($task['due_date'] ?? '')),
                    ],
                    'created_at' => (string) ($task['created_at'] ?? ''),
                    'actor_name' => (string) ($task['creator_name'] ?? ''),
                ];
            }
        }
    }
    unset($task);

    return $tasks;
}

function tasksByStatus(array $tasks): array
{
    $grouped = [];
    foreach (array_keys(taskStatuses()) as $status) {
        $grouped[$status] = [];
    }

    foreach ($tasks as $task) {
        $status = normalizeTaskStatus((string) $task['status']);
        $grouped[$status][] = $task;
    }

    return $grouped;
}

function filterTasks(array $tasks, ?string $statusFilter, ?int $assigneeFilterId): array
{
    $statusFilter = $statusFilter ? normalizeTaskStatus($statusFilter) : null;
    $assigneeFilterId = $assigneeFilterId && $assigneeFilterId > 0 ? $assigneeFilterId : null;

    if ($statusFilter === null && $assigneeFilterId === null) {
        return $tasks;
    }

    $filtered = [];

    foreach ($tasks as $task) {
        if ($statusFilter !== null && (string) $task['status'] !== $statusFilter) {
            continue;
        }

        if ($assigneeFilterId !== null) {
            $taskAssigneeIds = $task['assignee_ids'] ?? [];
            if (!in_array($assigneeFilterId, $taskAssigneeIds, true)) {
                continue;
            }
        }

        $filtered[] = $task;
    }

    return $filtered;
}

function tasksByGroup(array $tasks, ?array $groupNames = null): array
{
    $grouped = [];
    $preserveOrder = $groupNames !== null;

    if ($groupNames !== null) {
        foreach ($groupNames as $groupName) {
            $group = normalizeTaskGroupName((string) $groupName);
            $grouped[$group] = [];
        }
    }

    foreach ($tasks as $task) {
        $group = normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral'));
        if (!isset($grouped[$group])) {
            $grouped[$group] = [];
        }
        $grouped[$group][] = $task;
    }

    if (!$preserveOrder) {
        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
    }

    return $grouped;
}

function assigneeNamesSummary(array $task): string
{
    $names = [];
    foreach (($task['assignees'] ?? []) as $assignee) {
        $names[] = (string) ($assignee['name'] ?? '');
    }

    $names = array_values(array_filter($names, static fn ($name) => $name !== ''));

    if (!$names) {
        return 'Sem responsável';
    }

    return implode(', ', $names);
}

function taskDueDatePresentation(?string $dueDateValue): array
{
    $dueDateValue = trim((string) $dueDateValue);

    if ($dueDateValue === '') {
        return [
            'display' => 'Sem prazo',
            'title' => 'Sem prazo',
            'is_relative' => false,
        ];
    }

    try {
        $date = new DateTimeImmutable($dueDateValue);
    } catch (Throwable $e) {
        return [
            'display' => $dueDateValue,
            'title' => $dueDateValue,
            'is_relative' => false,
        ];
    }

    $iso = $date->format('Y-m-d');
    $fullLabel = $date->format('d/m/Y');
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');

    if ($iso === $today) {
        return [
            'display' => 'Hoje',
            'title' => 'Hoje (' . $fullLabel . ')',
            'is_relative' => true,
        ];
    }

    if ($iso === $tomorrow) {
        return [
            'display' => 'Amanha',
            'title' => 'Amanha (' . $fullLabel . ')',
            'is_relative' => true,
        ];
    }

    return [
        'display' => $fullLabel,
        'title' => $fullLabel,
        'is_relative' => false,
    ];
}

function dashboardStats(array $tasks): array
{
    $stats = [
        'total' => count($tasks),
        'done' => 0,
        'due_today' => 0,
        'urgent' => 0,
    ];

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    foreach ($tasks as $task) {
        if ($task['status'] === 'done') {
            $stats['done']++;
        }
        if (($task['due_date'] ?? null) === $today) {
            $stats['due_today']++;
        }
        if ($task['priority'] === 'urgent') {
            $stats['urgent']++;
        }
    }

    return $stats;
}

function countMyAssignedTasks(array $tasks, int $userId): int
{
    $count = 0;
    foreach ($tasks as $task) {
        $taskAssigneeIds = $task['assignee_ids'] ?? [];
        if (in_array($userId, $taskAssigneeIds, true) && $task['status'] !== 'done') {
            $count++;
        }
    }
    return $count;
}
