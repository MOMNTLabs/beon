<?php
declare(strict_types=1);

session_start();

date_default_timezone_set('America/Sao_Paulo');

const APP_NAME = 'Workplace Formula';
const DB_PATH = __DIR__ . '/storage/app.sqlite';

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
            created_by INTEGER NOT NULL,
            assigned_to INTEGER DEFAULT NULL,
            group_name TEXT NOT NULL DEFAULT \'Geral\',
            assignee_ids_json TEXT NOT NULL DEFAULT \'[]\',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
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
            created_by BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            assigned_to BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
            group_name TEXT NOT NULL DEFAULT \'Geral\',
            assignee_ids_json TEXT NOT NULL DEFAULT \'[]\',
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
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

    $stmt = $pdo->query('SELECT id, assigned_to, group_name, assignee_ids_json FROM tasks');
    $rows = $stmt ? $stmt->fetchAll() : [];

    $update = $pdo->prepare(
        'UPDATE tasks
         SET group_name = :group_name,
             assignee_ids_json = :assignee_ids_json
         WHERE id = :id'
    );

    foreach ($rows as $row) {
        $groupName = normalizeTaskGroupName((string) ($row['group_name'] ?? ''));
        $assigneeIds = decodeAssigneeIds(
            $row['assignee_ids_json'] ?? null,
            isset($row['assigned_to']) ? (int) $row['assigned_to'] : null
        );

        $update->execute([
            ':group_name' => $groupName,
            ':assignee_ids_json' => encodeAssigneeIds($assigneeIds),
            ':id' => (int) $row['id'],
        ]);
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
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
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

function taskGroupsList(): array
{
    $rows = db()->query('SELECT DISTINCT group_name FROM tasks WHERE group_name IS NOT NULL AND group_name <> \'\' ORDER BY group_name ASC')->fetchAll();
    $groups = [];

    foreach ($rows as $row) {
        $groups[] = normalizeTaskGroupName((string) ($row['group_name'] ?? 'Geral'));
    }

    if (!$groups) {
        return ['Geral'];
    }

    return array_values(array_unique($groups));
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
                    WHEN \'in_progress\' THEN 1
                    WHEN \'review\' THEN 2
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
                CASE WHEN t.due_date IS NULL OR t.due_date = \'\' THEN 1 ELSE 0 END,
                t.due_date ASC,
                t.updated_at DESC';

    $tasks = db()->query($sql)->fetchAll();
    $usersById = usersMapById();

    foreach ($tasks as &$task) {
        $task['group_name'] = normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral'));
        $assigneeIds = decodeAssigneeIds(
            $task['assignee_ids_json'] ?? null,
            isset($task['assigned_to']) ? (int) $task['assigned_to'] : null
        );
        $assigneeIds = normalizeAssigneeIds($assigneeIds, $usersById);

        $task['assignee_ids'] = $assigneeIds;
        $task['assignees'] = [];

        foreach ($assigneeIds as $id) {
            if (isset($usersById[$id])) {
                $task['assignees'][] = $usersById[$id];
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

function tasksByGroup(array $tasks): array
{
    $grouped = [];

    foreach ($tasks as $task) {
        $group = normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral'));
        if (!isset($grouped[$group])) {
            $grouped[$group] = [];
        }
        $grouped[$group][] = $task;
    }

    ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

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
