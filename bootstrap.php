<?php
declare(strict_types=1);

session_start();

date_default_timezone_set('America/Sao_Paulo');

const APP_NAME = 'Bexon';
const DB_PATH = __DIR__ . '/storage/app.sqlite';
const REMEMBER_COOKIE_NAME = 'wf_remember';
const REMEMBER_TOKEN_DAYS = 30;
const PASSWORD_RESET_TOKEN_HOURS = 1;
const PASSWORD_RESET_LOG_PATH = __DIR__ . '/storage/password-reset-mails.log';
const LAST_WORKSPACE_COOKIE_NAME = 'wf_last_workspace';
const LAST_WORKSPACE_COOKIE_DAYS = 365;

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
    if (shouldAutoRunMigrations()) {
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

function shouldAutoRunMigrations(): bool
{
    if (PHP_SAPI === 'cli') {
        return true;
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

    ensureUserProfileSchema($pdo);
    ensureAppMetaSchema($pdo);
    ensureWorkspaceSchema($pdo);
    ensureWorkspaceVaultSchema($pdo);
    ensureWorkspaceDueSchema($pdo);
    ensureWorkspaceInventorySchema($pdo);
    ensureWorkspaceAccountingSchema($pdo);
    ensureTaskExtendedSchema($pdo);
    ensureTaskGroupsSchema($pdo);
    ensureTaskHistorySchema($pdo);
    ensureGroupPermissionSchema($pdo);
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
            title_tag TEXT NOT NULL DEFAULT \'\',
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
            subtasks_json TEXT NOT NULL DEFAULT \'[]\',
            subtasks_dependency_enabled INTEGER NOT NULL DEFAULT 0,
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

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_reset_tokens (
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
            title_tag TEXT NOT NULL DEFAULT \'\',
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
            subtasks_json TEXT NOT NULL DEFAULT \'[]\',
            subtasks_dependency_enabled SMALLINT NOT NULL DEFAULT 0,
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

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id BIGSERIAL PRIMARY KEY,
            user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            selector TEXT NOT NULL UNIQUE,
            token_hash TEXT NOT NULL,
            expires_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
        )'
    );
}

function ensureAppMetaSchema(PDO $pdo): void
{
    if (dbDriverName($pdo) === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_meta (
                meta_key TEXT PRIMARY KEY,
                meta_value TEXT NOT NULL DEFAULT \'\',
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS app_meta (
            meta_key TEXT PRIMARY KEY,
            meta_value TEXT NOT NULL DEFAULT \'\',
            updated_at TEXT NOT NULL
        )'
    );
}

function ensureUserProfileSchema(PDO $pdo): void
{
    if (tableHasColumn($pdo, 'users', 'avatar_data_url')) {
        return;
    }

    $pdo->exec("ALTER TABLE users ADD COLUMN avatar_data_url TEXT NOT NULL DEFAULT ''");
}

function ensureWorkspaceProfileSchema(PDO $pdo): void
{
    if (tableHasColumn($pdo, 'workspaces', 'avatar_data_url')) {
        return;
    }

    $pdo->exec("ALTER TABLE workspaces ADD COLUMN avatar_data_url TEXT NOT NULL DEFAULT ''");
}

function appMetaGet(PDO $pdo, string $metaKey): ?string
{
    $metaKey = trim($metaKey);
    if ($metaKey === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT meta_value
         FROM app_meta
         WHERE meta_key = :meta_key
         LIMIT 1'
    );
    $stmt->execute([':meta_key' => $metaKey]);
    $value = $stmt->fetchColumn();
    if (!is_string($value)) {
        return null;
    }

    return $value;
}

function appMetaSet(PDO $pdo, string $metaKey, string $metaValue): void
{
    $metaKey = trim($metaKey);
    if ($metaKey === '') {
        return;
    }

    $updatedAt = nowIso();
    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO app_meta (meta_key, meta_value, updated_at)
             VALUES (:meta_key, :meta_value, :updated_at)
             ON CONFLICT (meta_key)
             DO UPDATE SET
                meta_value = EXCLUDED.meta_value,
                updated_at = EXCLUDED.updated_at'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT OR REPLACE INTO app_meta (meta_key, meta_value, updated_at)
             VALUES (:meta_key, :meta_value, :updated_at)'
        );
    }

    $stmt->execute([
        ':meta_key' => $metaKey,
        ':meta_value' => $metaValue,
        ':updated_at' => $updatedAt,
    ]);
}

function ensureWorkspaceSchema(PDO $pdo): void
{
    $driver = dbDriverName($pdo);

    if ($driver === 'pgsql') {
        $pdo->exec(
             'CREATE TABLE IF NOT EXISTS workspaces (
                 id BIGSERIAL PRIMARY KEY,
                 name TEXT NOT NULL,
                 slug TEXT NOT NULL UNIQUE,
                 is_personal SMALLINT NOT NULL DEFAULT 0,
                 avatar_data_url TEXT NOT NULL DEFAULT \'\',
                 task_statuses_json TEXT NOT NULL DEFAULT \'[]\',
                 task_review_status_key TEXT DEFAULT NULL,
                 sidebar_tools_json TEXT NOT NULL DEFAULT \'[]\',
                 created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                 created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                 updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
             )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_members (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                role VARCHAR(32) NOT NULL DEFAULT \'member\',
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                UNIQUE(workspace_id, user_id)
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_workspace_members_user_workspace
             ON workspace_members(user_id, workspace_id)'
        );
    } else {
        $pdo->exec(
             'CREATE TABLE IF NOT EXISTS workspaces (
                 id INTEGER PRIMARY KEY AUTOINCREMENT,
                 name TEXT NOT NULL,
                 slug TEXT NOT NULL UNIQUE,
                 is_personal INTEGER NOT NULL DEFAULT 0,
                 avatar_data_url TEXT NOT NULL DEFAULT \'\',
                 task_statuses_json TEXT NOT NULL DEFAULT \'[]\',
                 task_review_status_key TEXT DEFAULT NULL,
                 sidebar_tools_json TEXT NOT NULL DEFAULT \'[]\',
                 created_by INTEGER DEFAULT NULL,
                 created_at TEXT NOT NULL,
                 updated_at TEXT NOT NULL,
                 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
             )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                role TEXT NOT NULL DEFAULT \'member\',
                created_at TEXT NOT NULL,
                UNIQUE(workspace_id, user_id),
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_workspace_members_user_workspace
             ON workspace_members(user_id, workspace_id)'
        );
    }

    if (!tableHasColumn($pdo, 'workspaces', 'is_personal')) {
        if ($driver === 'pgsql') {
            $pdo->exec('ALTER TABLE workspaces ADD COLUMN is_personal SMALLINT NOT NULL DEFAULT 0');
        } else {
            $pdo->exec('ALTER TABLE workspaces ADD COLUMN is_personal INTEGER NOT NULL DEFAULT 0');
        }
    }

    if (!tableHasColumn($pdo, 'workspaces', 'task_statuses_json')) {
        $pdo->exec("ALTER TABLE workspaces ADD COLUMN task_statuses_json TEXT NOT NULL DEFAULT '[]'");
    }

    if (!tableHasColumn($pdo, 'workspaces', 'task_review_status_key')) {
        $pdo->exec('ALTER TABLE workspaces ADD COLUMN task_review_status_key TEXT DEFAULT NULL');
    }
    if (!tableHasColumn($pdo, 'workspaces', 'sidebar_tools_json')) {
        $pdo->exec("ALTER TABLE workspaces ADD COLUMN sidebar_tools_json TEXT NOT NULL DEFAULT '[]'");
    }

    if (!tableHasColumn($pdo, 'tasks', 'workspace_id')) {
        if ($driver === 'pgsql') {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN workspace_id BIGINT DEFAULT NULL');
        } else {
            $pdo->exec('ALTER TABLE tasks ADD COLUMN workspace_id INTEGER DEFAULT NULL');
        }
    }

    if ($driver === 'pgsql' && !pgConstraintExists($pdo, 'tasks_workspace_id_fkey')) {
        $pdo->exec(
            'ALTER TABLE tasks
             ADD CONSTRAINT tasks_workspace_id_fkey
             FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE'
        );
    }

    if ($driver === 'sqlite' && !tableHasColumn($pdo, 'task_groups', 'workspace_id')) {
        $pdo->exec('ALTER TABLE task_groups RENAME TO task_groups_legacy');
        $pdo->exec(
            'CREATE TABLE task_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER DEFAULT NULL,
                name TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
        $pdo->exec(
            'INSERT INTO task_groups (id, workspace_id, name, created_by, created_at)
             SELECT id, NULL, name, created_by, created_at
             FROM task_groups_legacy'
        );
        $pdo->exec('DROP TABLE task_groups_legacy');
    } elseif ($driver === 'pgsql' && !tableHasColumn($pdo, 'task_groups', 'workspace_id')) {
        $pdo->exec('ALTER TABLE task_groups ADD COLUMN workspace_id BIGINT DEFAULT NULL');
    }

    if ($driver === 'pgsql') {
        $pdo->exec('ALTER TABLE task_groups DROP CONSTRAINT IF EXISTS task_groups_name_key');

        if (!pgConstraintExists($pdo, 'task_groups_workspace_id_fkey')) {
            $pdo->exec(
                'ALTER TABLE task_groups
                 ADD CONSTRAINT task_groups_workspace_id_fkey
                 FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE'
            );
        }
    }

    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_task_groups_workspace_name_unique
         ON task_groups(workspace_id, name)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_task_groups_workspace
         ON task_groups(workspace_id)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_tasks_workspace
         ON tasks(workspace_id)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_tasks_workspace_group_due_updated
         ON tasks(workspace_id, group_name, due_date, updated_at)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_tasks_workspace_due_status
         ON tasks(workspace_id, due_date, status)'
    );

    $users = $pdo->query('SELECT id, name, email FROM users ORDER BY id ASC')->fetchAll();
    if (!$users) {
        return;
    }

    $workspaceRow = $pdo->query(
        'SELECT id, name, created_by
         FROM workspaces
         ORDER BY id ASC
         LIMIT 1'
    )->fetch();

    $defaultWorkspaceId = (int) ($workspaceRow['id'] ?? 0);
    $createdDefaultWorkspace = false;
    $adminUserId = (int) ($workspaceRow['created_by'] ?? 0);
    if ($adminUserId <= 0) {
        $adminUserId = guessPrimaryAdminUserId($pdo) ?? (int) ($users[0]['id'] ?? 0);
    }

    if ($defaultWorkspaceId <= 0) {
        $defaultWorkspaceId = createWorkspace($pdo, 'Formula Online', $adminUserId);
        $createdDefaultWorkspace = $defaultWorkspaceId > 0;
    }

    if ($defaultWorkspaceId <= 0) {
        return;
    }

    $legacyTaskCountStmt = $pdo->query('SELECT COUNT(*) FROM tasks WHERE workspace_id IS NULL');
    $legacyTaskCount = $legacyTaskCountStmt ? (int) $legacyTaskCountStmt->fetchColumn() : 0;

    $legacyGroupCountStmt = $pdo->query('SELECT COUNT(*) FROM task_groups WHERE workspace_id IS NULL');
    $legacyGroupCount = $legacyGroupCountStmt ? (int) $legacyGroupCountStmt->fetchColumn() : 0;

    $updateTasksWorkspace = $pdo->prepare(
        'UPDATE tasks
         SET workspace_id = :workspace_id
         WHERE workspace_id IS NULL'
    );
    if ($legacyTaskCount > 0) {
        $updateTasksWorkspace->execute([':workspace_id' => $defaultWorkspaceId]);
    }

    $updateGroupsWorkspace = $pdo->prepare(
        'UPDATE task_groups
         SET workspace_id = :workspace_id
         WHERE workspace_id IS NULL'
    );
    if ($legacyGroupCount > 0) {
        $updateGroupsWorkspace->execute([':workspace_id' => $defaultWorkspaceId]);
    }

    // Legacy bootstrap: when creating the first workspace or migrating orphaned data,
    // keep existing users together in the migrated "Formula Online" space.
    if ($createdDefaultWorkspace || $legacyTaskCount > 0 || $legacyGroupCount > 0) {
        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $role = $userId === $adminUserId ? 'admin' : 'member';
            upsertWorkspaceMember($pdo, $defaultWorkspaceId, $userId, $role);
        }
    }

    $defaultStatusDefinitionsJson = encodeWorkspaceTaskStatusDefinitions(defaultTaskStatusDefinitions());
    $defaultReviewStatusKey = defaultTaskReviewStatusKey();
    $workspaceStatusStmt = $pdo->prepare(
        'UPDATE workspaces
         SET task_statuses_json = :task_statuses_json,
             task_review_status_key = :task_review_status_key
         WHERE id = :workspace_id'
    );
    $workspaceRows = $pdo->query(
        'SELECT id, task_statuses_json, task_review_status_key
         FROM workspaces'
    )->fetchAll();
    foreach ($workspaceRows as $workspaceRow) {
        $workspaceId = (int) ($workspaceRow['id'] ?? 0);
        if ($workspaceId <= 0) {
            continue;
        }

        $taskStatusesJson = trim((string) ($workspaceRow['task_statuses_json'] ?? ''));
        $taskReviewStatusKey = trim((string) ($workspaceRow['task_review_status_key'] ?? ''));
        if ($taskStatusesJson !== '' && $taskStatusesJson !== '[]') {
            continue;
        }

        $workspaceStatusStmt->execute([
            ':task_statuses_json' => $defaultStatusDefinitionsJson,
            ':task_review_status_key' => $taskReviewStatusKey !== ''
                ? $taskReviewStatusKey
                : $defaultReviewStatusKey,
            ':workspace_id' => $workspaceId,
        ]);
    }
}

function ensureTaskExtendedSchema(PDO $pdo): void
{
    $needsBackfill = false;
    $backfillMetaKey = 'task_extended_backfill_v4';

    if (!tableHasColumn($pdo, 'tasks', 'group_name')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN group_name TEXT NOT NULL DEFAULT 'Geral'");
        $needsBackfill = true;
    }

    if (!tableHasColumn($pdo, 'tasks', 'assignee_ids_json')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN assignee_ids_json TEXT NOT NULL DEFAULT '[]'");
        $needsBackfill = true;
    }
    if (!tableHasColumn($pdo, 'tasks', 'reference_links_json')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN reference_links_json TEXT NOT NULL DEFAULT '[]'");
        $needsBackfill = true;
    }
    if (!tableHasColumn($pdo, 'tasks', 'reference_images_json')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN reference_images_json TEXT NOT NULL DEFAULT '[]'");
        $needsBackfill = true;
    }
    if (!tableHasColumn($pdo, 'tasks', 'overdue_flag')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN overdue_flag INTEGER NOT NULL DEFAULT 0");
        $needsBackfill = true;
    }
    if (!tableHasColumn($pdo, 'tasks', 'overdue_since_date')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN overdue_since_date DATE DEFAULT NULL");
        } else {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN overdue_since_date TEXT DEFAULT NULL");
        }
        $needsBackfill = true;
    }
    if (!tableHasColumn($pdo, 'tasks', 'subtasks_json')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN subtasks_json TEXT NOT NULL DEFAULT '[]'");
        $needsBackfill = true;
    }
    if (!tableHasColumn($pdo, 'tasks', 'subtasks_dependency_enabled')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN subtasks_dependency_enabled SMALLINT NOT NULL DEFAULT 0");
        } else {
            $pdo->exec("ALTER TABLE tasks ADD COLUMN subtasks_dependency_enabled INTEGER NOT NULL DEFAULT 0");
        }
        $needsBackfill = true;
    }
    if (!tableHasColumn($pdo, 'tasks', 'title_tag')) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN title_tag TEXT NOT NULL DEFAULT ''");
        $needsBackfill = true;
    }

    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_tasks_workspace_assigned_to
         ON tasks(workspace_id, assigned_to)'
    );

    if (!$needsBackfill && appMetaGet($pdo, $backfillMetaKey) === '1') {
        return;
    }

    $stmt = $pdo->query('SELECT id, assigned_to, group_name, assignee_ids_json, reference_links_json, reference_images_json, subtasks_json, subtasks_dependency_enabled, title_tag FROM tasks');
    $rows = $stmt ? $stmt->fetchAll() : [];
    if (!$rows) {
        appMetaSet($pdo, $backfillMetaKey, '1');
        return;
    }

    $update = $pdo->prepare(
        'UPDATE tasks
         SET group_name = :group_name,
             assignee_ids_json = :assignee_ids_json,
             reference_links_json = :reference_links_json,
             reference_images_json = :reference_images_json,
             subtasks_json = :subtasks_json,
             subtasks_dependency_enabled = :subtasks_dependency_enabled,
             title_tag = :title_tag
         WHERE id = :id'
    );

    foreach ($rows as $row) {
        $groupName = normalizeTaskGroupName((string) ($row['group_name'] ?? ''));
        $assigneeIds = decodeAssigneeIds(
            $row['assignee_ids_json'] ?? null,
            isset($row['assigned_to']) ? (int) $row['assigned_to'] : null
        );
        $referenceLinks = decodeReferenceUrlList($row['reference_links_json'] ?? null);
        $referenceImages = decodeReferenceImageList($row['reference_images_json'] ?? null);
        $subtasksDependencyEnabled = normalizePermissionFlag($row['subtasks_dependency_enabled'] ?? 0);
        $subtasks = decodeTaskSubtasks($row['subtasks_json'] ?? null, $subtasksDependencyEnabled === 1);
        $titleTag = normalizeTaskTitleTag((string) ($row['title_tag'] ?? ''));

        $update->execute([
            ':group_name' => $groupName,
            ':assignee_ids_json' => encodeAssigneeIds($assigneeIds),
            ':reference_links_json' => encodeReferenceUrlList($referenceLinks),
            ':reference_images_json' => encodeReferenceImageList($referenceImages),
            ':subtasks_json' => encodeTaskSubtasks($subtasks, $subtasksDependencyEnabled === 1),
            ':subtasks_dependency_enabled' => $subtasksDependencyEnabled,
            ':title_tag' => $titleTag,
            ':id' => (int) $row['id'],
        ]);
    }

    appMetaSet($pdo, $backfillMetaKey, '1');
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
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_task_history_task_event_id
             ON task_history(task_id, event_type, id)'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_task_history_task_created_id
             ON task_history(task_id, created_at, id)'
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
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_task_history_task_event_id
         ON task_history(task_id, event_type, id)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_task_history_task_created_id
         ON task_history(task_id, created_at, id)'
    );
}

function ensureTaskGroupsSchema(PDO $pdo): void
{
    if (dbDriverName($pdo) === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS task_groups (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT DEFAULT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                name TEXT NOT NULL,
                created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS task_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER DEFAULT NULL,
                name TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
    }

    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_task_groups_workspace_name_unique
         ON task_groups(workspace_id, name)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_task_groups_workspace
         ON task_groups(workspace_id)'
    );

    // Keep explicit groups in sync with task rows created before this table existed.
    $rows = $pdo->query(
        'SELECT workspace_id, group_name, MIN(created_by) AS created_by
         FROM tasks
         WHERE workspace_id IS NOT NULL
           AND group_name IS NOT NULL
           AND group_name <> \'\'
         GROUP BY workspace_id, group_name'
    )->fetchAll();

    foreach ($rows as $row) {
        $workspaceId = (int) ($row['workspace_id'] ?? 0);
        if ($workspaceId <= 0) {
            continue;
        }

        upsertTaskGroup(
            $pdo,
            (string) ($row['group_name'] ?? 'Geral'),
            isset($row['created_by']) ? (int) $row['created_by'] : null,
            $workspaceId
        );
    }

    $workspaceRows = $pdo->query(
        'SELECT id, created_by
         FROM workspaces
         ORDER BY id ASC'
    )->fetchAll();

    foreach ($workspaceRows as $workspaceRow) {
        $workspaceId = (int) ($workspaceRow['id'] ?? 0);
        if ($workspaceId <= 0) {
            continue;
        }

        $groupCountStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM task_groups
             WHERE workspace_id = :workspace_id'
        );
        $groupCountStmt->execute([':workspace_id' => $workspaceId]);
        $groupCount = (int) $groupCountStmt->fetchColumn();
        if ($groupCount > 0) {
            continue;
        }

        upsertTaskGroup(
            $pdo,
            'Geral',
            isset($workspaceRow['created_by']) ? (int) $workspaceRow['created_by'] : null,
            $workspaceId
        );
    }
}

function ensureWorkspaceVaultSchema(PDO $pdo): void
{
    if (dbDriverName($pdo) === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_vault_entries (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                label TEXT NOT NULL,
                login_value TEXT NOT NULL DEFAULT \'\',
                password_value TEXT NOT NULL DEFAULT \'\',
                group_name TEXT NOT NULL DEFAULT \'Geral\',
                notes TEXT NOT NULL DEFAULT \'\',
                created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_vault_groups (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                name TEXT NOT NULL,
                created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_vault_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                label TEXT NOT NULL,
                login_value TEXT NOT NULL DEFAULT \'\',
                password_value TEXT NOT NULL DEFAULT \'\',
                group_name TEXT NOT NULL DEFAULT \'Geral\',
                notes TEXT NOT NULL DEFAULT \'\',
                created_by INTEGER DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_vault_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
    }

    if (!tableHasColumn($pdo, 'workspace_vault_entries', 'group_name')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_vault_entries ADD COLUMN group_name TEXT NOT NULL DEFAULT 'Geral'");
        } else {
            $pdo->exec("ALTER TABLE workspace_vault_entries ADD COLUMN group_name TEXT NOT NULL DEFAULT 'Geral'");
        }
    }

    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_vault_entries_workspace
         ON workspace_vault_entries(workspace_id)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_vault_entries_workspace_updated
         ON workspace_vault_entries(workspace_id, updated_at)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_vault_entries_workspace_group
         ON workspace_vault_entries(workspace_id, group_name)'
    );

    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_workspace_vault_groups_workspace_name_unique
         ON workspace_vault_groups(workspace_id, name)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_vault_groups_workspace
         ON workspace_vault_groups(workspace_id)'
    );

    $rows = $pdo->query(
        'SELECT id, group_name
         FROM workspace_vault_entries'
    )->fetchAll();
    if ($rows) {
        $normalizeStmt = $pdo->prepare(
            'UPDATE workspace_vault_entries
             SET group_name = :group_name
             WHERE id = :id'
        );
        foreach ($rows as $row) {
            $normalizeStmt->execute([
                ':group_name' => normalizeVaultGroupName((string) ($row['group_name'] ?? 'Geral')),
                ':id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    $entryGroups = $pdo->query(
        'SELECT workspace_id, group_name, MIN(created_by) AS created_by
         FROM workspace_vault_entries
         WHERE workspace_id IS NOT NULL
         GROUP BY workspace_id, group_name'
    )->fetchAll();
    foreach ($entryGroups as $entryGroupRow) {
        $workspaceId = (int) ($entryGroupRow['workspace_id'] ?? 0);
        if ($workspaceId <= 0) {
            continue;
        }

        upsertVaultGroup(
            $pdo,
            (string) ($entryGroupRow['group_name'] ?? 'Geral'),
            isset($entryGroupRow['created_by']) ? (int) $entryGroupRow['created_by'] : null,
            $workspaceId
        );
    }

    $workspaceRows = $pdo->query(
        'SELECT id, created_by
         FROM workspaces
         ORDER BY id ASC'
    )->fetchAll();
    foreach ($workspaceRows as $workspaceRow) {
        $workspaceId = (int) ($workspaceRow['id'] ?? 0);
        if ($workspaceId <= 0) {
            continue;
        }

        $groupCountStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM workspace_vault_groups
             WHERE workspace_id = :workspace_id'
        );
        $groupCountStmt->execute([':workspace_id' => $workspaceId]);
        $groupCount = (int) $groupCountStmt->fetchColumn();
        if ($groupCount > 0) {
            continue;
        }

        upsertVaultGroup(
            $pdo,
            'Geral',
            isset($workspaceRow['created_by']) ? (int) $workspaceRow['created_by'] : null,
            $workspaceId
        );
    }
}

function ensureWorkspaceDueSchema(PDO $pdo): void
{
    if (dbDriverName($pdo) === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_due_entries (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                label TEXT NOT NULL,
                recurrence_type VARCHAR(16) NOT NULL DEFAULT \'monthly\',
                monthly_day SMALLINT DEFAULT NULL,
                due_date DATE DEFAULT NULL,
                amount_cents BIGINT NOT NULL DEFAULT 0,
                group_name TEXT NOT NULL DEFAULT \'Geral\',
                notes TEXT NOT NULL DEFAULT \'\',
                created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_due_groups (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                name TEXT NOT NULL,
                created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_due_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                label TEXT NOT NULL,
                recurrence_type TEXT NOT NULL DEFAULT \'monthly\',
                monthly_day INTEGER DEFAULT NULL,
                due_date TEXT DEFAULT NULL,
                amount_cents INTEGER NOT NULL DEFAULT 0,
                group_name TEXT NOT NULL DEFAULT \'Geral\',
                notes TEXT NOT NULL DEFAULT \'\',
                created_by INTEGER DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_due_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
    }

    if (!tableHasColumn($pdo, 'workspace_due_entries', 'group_name')) {
        $pdo->exec("ALTER TABLE workspace_due_entries ADD COLUMN group_name TEXT NOT NULL DEFAULT 'Geral'");
    }
    if (!tableHasColumn($pdo, 'workspace_due_entries', 'due_date')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_due_entries ADD COLUMN due_date DATE DEFAULT NULL");
        } else {
            $pdo->exec("ALTER TABLE workspace_due_entries ADD COLUMN due_date TEXT DEFAULT NULL");
        }
    }
    if (!tableHasColumn($pdo, 'workspace_due_entries', 'recurrence_type')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_due_entries ADD COLUMN recurrence_type VARCHAR(16) NOT NULL DEFAULT 'monthly'");
        } else {
            $pdo->exec("ALTER TABLE workspace_due_entries ADD COLUMN recurrence_type TEXT NOT NULL DEFAULT 'monthly'");
        }
    }
    if (!tableHasColumn($pdo, 'workspace_due_entries', 'monthly_day')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_due_entries ADD COLUMN monthly_day SMALLINT DEFAULT NULL");
        } else {
            $pdo->exec("ALTER TABLE workspace_due_entries ADD COLUMN monthly_day INTEGER DEFAULT NULL");
        }
    }
    if (!tableHasColumn($pdo, 'workspace_due_entries', 'amount_cents')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_due_entries ADD COLUMN amount_cents BIGINT NOT NULL DEFAULT 0");
        } else {
            $pdo->exec("ALTER TABLE workspace_due_entries ADD COLUMN amount_cents INTEGER NOT NULL DEFAULT 0");
        }
    }
    if (!tableHasColumn($pdo, 'workspace_due_entries', 'notes')) {
        $pdo->exec("ALTER TABLE workspace_due_entries ADD COLUMN notes TEXT NOT NULL DEFAULT ''");
    }

    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_due_entries_workspace
         ON workspace_due_entries(workspace_id)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_due_entries_workspace_due
         ON workspace_due_entries(workspace_id, due_date)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_due_entries_workspace_monthly_day
         ON workspace_due_entries(workspace_id, monthly_day)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_due_entries_workspace_group
         ON workspace_due_entries(workspace_id, group_name)'
    );
    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_workspace_due_groups_workspace_name_unique
         ON workspace_due_groups(workspace_id, name)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_due_groups_workspace
         ON workspace_due_groups(workspace_id)'
    );

    $rows = $pdo->query(
        'SELECT id, label, recurrence_type, monthly_day, due_date, amount_cents, group_name
         FROM workspace_due_entries'
    )->fetchAll();
    if ($rows) {
        $normalizeStmt = $pdo->prepare(
            'UPDATE workspace_due_entries
             SET label = :label,
                 recurrence_type = :recurrence_type,
                 monthly_day = :monthly_day,
                 due_date = :due_date,
                 amount_cents = :amount_cents,
                 group_name = :group_name
             WHERE id = :id'
        );
        foreach ($rows as $row) {
            $dueDate = dueDateForStorage((string) ($row['due_date'] ?? ''));
            $recurrenceType = normalizeDueRecurrenceType((string) ($row['recurrence_type'] ?? 'monthly'));
            $monthlyDay = normalizeDueMonthlyDay($row['monthly_day'] ?? null);
            $amountCents = normalizeDueAmountCents($row['amount_cents'] ?? null) ?? 0;
            if ($monthlyDay === null && $dueDate !== null) {
                $monthlyDay = dueMonthlyDayFromDate($dueDate);
            }
            if ($recurrenceType === 'monthly') {
                if ($monthlyDay === null) {
                    $monthlyDay = (int) (new DateTimeImmutable('today'))->format('j');
                }
            } else {
                if ($dueDate === null) {
                    $dueDate = (new DateTimeImmutable('today'))->format('Y-m-d');
                }
                $monthlyDay = null;
            }
            $nextDueDate = dueNextDueDate($recurrenceType, $monthlyDay, $dueDate);
            if ($nextDueDate === null) {
                $nextDueDate = (new DateTimeImmutable('today'))->format('Y-m-d');
            }

            $normalizeStmt->execute([
                ':label' => normalizeDueEntryLabel((string) ($row['label'] ?? '')),
                ':recurrence_type' => $recurrenceType,
                ':monthly_day' => $monthlyDay,
                ':due_date' => $nextDueDate,
                ':amount_cents' => $amountCents,
                ':group_name' => normalizeDueGroupName((string) ($row['group_name'] ?? 'Geral')),
                ':id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    $entryGroups = $pdo->query(
        'SELECT workspace_id, group_name, MIN(created_by) AS created_by
         FROM workspace_due_entries
         WHERE workspace_id IS NOT NULL
         GROUP BY workspace_id, group_name'
    )->fetchAll();
    foreach ($entryGroups as $entryGroupRow) {
        $workspaceId = (int) ($entryGroupRow['workspace_id'] ?? 0);
        if ($workspaceId <= 0) {
            continue;
        }

        upsertDueGroup(
            $pdo,
            (string) ($entryGroupRow['group_name'] ?? 'Geral'),
            isset($entryGroupRow['created_by']) ? (int) $entryGroupRow['created_by'] : null,
            $workspaceId
        );
    }

    $workspaceRows = $pdo->query(
        'SELECT id, created_by
         FROM workspaces
         ORDER BY id ASC'
    )->fetchAll();
    foreach ($workspaceRows as $workspaceRow) {
        $workspaceId = (int) ($workspaceRow['id'] ?? 0);
        if ($workspaceId <= 0) {
            continue;
        }

        $groupCountStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM workspace_due_groups
             WHERE workspace_id = :workspace_id'
        );
        $groupCountStmt->execute([':workspace_id' => $workspaceId]);
        $groupCount = (int) $groupCountStmt->fetchColumn();
        if ($groupCount > 0) {
            continue;
        }

        upsertDueGroup(
            $pdo,
            'Geral',
            isset($workspaceRow['created_by']) ? (int) $workspaceRow['created_by'] : null,
            $workspaceId
        );
    }
}

function ensureWorkspaceInventorySchema(PDO $pdo): void
{
    if (dbDriverName($pdo) === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_inventory_entries (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                label TEXT NOT NULL,
                quantity_value NUMERIC(12,2) NOT NULL DEFAULT 0,
                min_quantity_value NUMERIC(12,2) DEFAULT NULL,
                unit_label VARCHAR(30) NOT NULL DEFAULT \'un\',
                group_name TEXT NOT NULL DEFAULT \'Geral\',
                notes TEXT NOT NULL DEFAULT \'\',
                created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_inventory_groups (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                name TEXT NOT NULL,
                created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_inventory_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                label TEXT NOT NULL,
                quantity_value REAL NOT NULL DEFAULT 0,
                min_quantity_value REAL DEFAULT NULL,
                unit_label TEXT NOT NULL DEFAULT \'un\',
                group_name TEXT NOT NULL DEFAULT \'Geral\',
                notes TEXT NOT NULL DEFAULT \'\',
                created_by INTEGER DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_inventory_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
    }

    if (!tableHasColumn($pdo, 'workspace_inventory_entries', 'group_name')) {
        $pdo->exec("ALTER TABLE workspace_inventory_entries ADD COLUMN group_name TEXT NOT NULL DEFAULT 'Geral'");
    }
    if (!tableHasColumn($pdo, 'workspace_inventory_entries', 'quantity_value')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_inventory_entries ADD COLUMN quantity_value NUMERIC(12,2) NOT NULL DEFAULT 0");
        } else {
            $pdo->exec("ALTER TABLE workspace_inventory_entries ADD COLUMN quantity_value REAL NOT NULL DEFAULT 0");
        }
    }
    if (!tableHasColumn($pdo, 'workspace_inventory_entries', 'min_quantity_value')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_inventory_entries ADD COLUMN min_quantity_value NUMERIC(12,2) DEFAULT NULL");
        } else {
            $pdo->exec("ALTER TABLE workspace_inventory_entries ADD COLUMN min_quantity_value REAL DEFAULT NULL");
        }
    }
    if (!tableHasColumn($pdo, 'workspace_inventory_entries', 'unit_label')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_inventory_entries ADD COLUMN unit_label VARCHAR(30) NOT NULL DEFAULT 'un'");
        } else {
            $pdo->exec("ALTER TABLE workspace_inventory_entries ADD COLUMN unit_label TEXT NOT NULL DEFAULT 'un'");
        }
    }
    if (!tableHasColumn($pdo, 'workspace_inventory_entries', 'notes')) {
        $pdo->exec("ALTER TABLE workspace_inventory_entries ADD COLUMN notes TEXT NOT NULL DEFAULT ''");
    }

    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_inventory_entries_workspace
         ON workspace_inventory_entries(workspace_id)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_inventory_entries_workspace_group
         ON workspace_inventory_entries(workspace_id, group_name)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_inventory_entries_workspace_label
         ON workspace_inventory_entries(workspace_id, label)'
    );
    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_workspace_inventory_groups_workspace_name_unique
         ON workspace_inventory_groups(workspace_id, name)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_inventory_groups_workspace
         ON workspace_inventory_groups(workspace_id)'
    );

    $rows = $pdo->query(
        'SELECT id, label, quantity_value, min_quantity_value, unit_label, group_name, notes
         FROM workspace_inventory_entries'
    )->fetchAll();
    if ($rows) {
        $normalizeStmt = $pdo->prepare(
            'UPDATE workspace_inventory_entries
             SET label = :label,
                 quantity_value = :quantity_value,
                 min_quantity_value = :min_quantity_value,
                 unit_label = :unit_label,
                 group_name = :group_name,
                 notes = :notes
             WHERE id = :id'
        );
        foreach ($rows as $row) {
            $quantityValue = normalizeInventoryQuantityValue($row['quantity_value'] ?? null) ?? 0;
            $minQuantityValue = normalizeInventoryQuantityValue($row['min_quantity_value'] ?? null);
            $normalizeStmt->execute([
                ':label' => normalizeInventoryEntryLabel((string) ($row['label'] ?? '')),
                ':quantity_value' => inventoryQuantityStorageValue($quantityValue),
                ':min_quantity_value' => $minQuantityValue !== null
                    ? inventoryQuantityStorageValue($minQuantityValue)
                    : null,
                ':unit_label' => normalizeInventoryUnitLabel((string) ($row['unit_label'] ?? 'un')),
                ':group_name' => normalizeInventoryGroupName((string) ($row['group_name'] ?? 'Geral')),
                ':notes' => normalizeInventoryEntryNotes((string) ($row['notes'] ?? '')),
                ':id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    $entryGroups = $pdo->query(
        'SELECT workspace_id, group_name, MIN(created_by) AS created_by
         FROM workspace_inventory_entries
         WHERE workspace_id IS NOT NULL
         GROUP BY workspace_id, group_name'
    )->fetchAll();
    foreach ($entryGroups as $entryGroupRow) {
        $workspaceId = (int) ($entryGroupRow['workspace_id'] ?? 0);
        if ($workspaceId <= 0) {
            continue;
        }

        upsertInventoryGroup(
            $pdo,
            (string) ($entryGroupRow['group_name'] ?? 'Geral'),
            isset($entryGroupRow['created_by']) ? (int) $entryGroupRow['created_by'] : null,
            $workspaceId
        );
    }

    $workspaceRows = $pdo->query(
        'SELECT id, created_by
         FROM workspaces
         ORDER BY id ASC'
    )->fetchAll();
    foreach ($workspaceRows as $workspaceRow) {
        $workspaceId = (int) ($workspaceRow['id'] ?? 0);
        if ($workspaceId <= 0) {
            continue;
        }

        $groupCountStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM workspace_inventory_groups
             WHERE workspace_id = :workspace_id'
        );
        $groupCountStmt->execute([':workspace_id' => $workspaceId]);
        $groupCount = (int) $groupCountStmt->fetchColumn();
        if ($groupCount > 0) {
            continue;
        }

        upsertInventoryGroup(
            $pdo,
            'Geral',
            isset($workspaceRow['created_by']) ? (int) $workspaceRow['created_by'] : null,
            $workspaceId
        );
    }
}

function ensureWorkspaceAccountingSchema(PDO $pdo): void
{
    if (dbDriverName($pdo) === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_accounting_entries (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                period_key VARCHAR(7) NOT NULL,
                entry_type VARCHAR(16) NOT NULL DEFAULT \'expense\',
                label TEXT NOT NULL,
                amount_cents BIGINT NOT NULL DEFAULT 0,
                total_amount_cents BIGINT NOT NULL DEFAULT 0,
                is_installment SMALLINT NOT NULL DEFAULT 0,
                installment_number INTEGER NOT NULL DEFAULT 0,
                installment_total INTEGER NOT NULL DEFAULT 0,
                is_settled SMALLINT NOT NULL DEFAULT 0,
                carry_source_entry_id BIGINT DEFAULT NULL REFERENCES workspace_accounting_entries(id) ON DELETE SET NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_accounting_periods (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                period_key VARCHAR(7) NOT NULL,
                opening_balance_cents BIGINT NOT NULL DEFAULT 0,
                updated_by BIGINT DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_accounting_entries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                period_key TEXT NOT NULL,
                entry_type TEXT NOT NULL DEFAULT \'expense\',
                label TEXT NOT NULL,
                amount_cents INTEGER NOT NULL DEFAULT 0,
                total_amount_cents INTEGER NOT NULL DEFAULT 0,
                is_installment INTEGER NOT NULL DEFAULT 0,
                installment_number INTEGER NOT NULL DEFAULT 0,
                installment_total INTEGER NOT NULL DEFAULT 0,
                is_settled INTEGER NOT NULL DEFAULT 0,
                carry_source_entry_id INTEGER DEFAULT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_by INTEGER DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (carry_source_entry_id) REFERENCES workspace_accounting_entries(id) ON DELETE SET NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_accounting_periods (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                period_key TEXT NOT NULL,
                opening_balance_cents INTEGER NOT NULL DEFAULT 0,
                updated_by INTEGER DEFAULT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            )'
        );
    }

    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'period_key')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN period_key TEXT NOT NULL DEFAULT '1970-01'");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'entry_type')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN entry_type TEXT NOT NULL DEFAULT 'expense'");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'label')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN label TEXT NOT NULL DEFAULT ''");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'amount_cents')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN amount_cents BIGINT NOT NULL DEFAULT 0");
        } else {
            $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN amount_cents INTEGER NOT NULL DEFAULT 0");
        }
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'total_amount_cents')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN total_amount_cents BIGINT NOT NULL DEFAULT 0");
        } else {
            $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN total_amount_cents INTEGER NOT NULL DEFAULT 0");
        }
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'is_installment')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN is_installment INTEGER NOT NULL DEFAULT 0");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'installment_number')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN installment_number INTEGER NOT NULL DEFAULT 0");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'installment_total')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN installment_total INTEGER NOT NULL DEFAULT 0");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'is_settled')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN is_settled INTEGER NOT NULL DEFAULT 0");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'carry_source_entry_id')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN carry_source_entry_id INTEGER DEFAULT NULL");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'sort_order')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN sort_order INTEGER NOT NULL DEFAULT 0");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'created_by')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN created_by INTEGER DEFAULT NULL");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'created_at')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN created_at TEXT NOT NULL DEFAULT ''");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_entries', 'updated_at')) {
        $pdo->exec("ALTER TABLE workspace_accounting_entries ADD COLUMN updated_at TEXT NOT NULL DEFAULT ''");
    }

    if (!tableHasColumn($pdo, 'workspace_accounting_periods', 'period_key')) {
        $pdo->exec("ALTER TABLE workspace_accounting_periods ADD COLUMN period_key TEXT NOT NULL DEFAULT '1970-01'");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_periods', 'opening_balance_cents')) {
        if (dbDriverName($pdo) === 'pgsql') {
            $pdo->exec("ALTER TABLE workspace_accounting_periods ADD COLUMN opening_balance_cents BIGINT NOT NULL DEFAULT 0");
        } else {
            $pdo->exec("ALTER TABLE workspace_accounting_periods ADD COLUMN opening_balance_cents INTEGER NOT NULL DEFAULT 0");
        }
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_periods', 'updated_by')) {
        $pdo->exec("ALTER TABLE workspace_accounting_periods ADD COLUMN updated_by INTEGER DEFAULT NULL");
    }
    if (!tableHasColumn($pdo, 'workspace_accounting_periods', 'updated_at')) {
        $pdo->exec("ALTER TABLE workspace_accounting_periods ADD COLUMN updated_at TEXT NOT NULL DEFAULT ''");
    }

    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_accounting_entries_workspace_period
         ON workspace_accounting_entries(workspace_id, period_key)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_accounting_entries_workspace_period_type
         ON workspace_accounting_entries(workspace_id, period_key, entry_type)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_workspace_accounting_entries_workspace_period_sort
         ON workspace_accounting_entries(workspace_id, period_key, sort_order, id)'
    );
    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_workspace_accounting_entries_workspace_period_carry_source
         ON workspace_accounting_entries(workspace_id, period_key, carry_source_entry_id)'
    );
    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_workspace_accounting_periods_workspace_period
         ON workspace_accounting_periods(workspace_id, period_key)'
    );

    $rows = $pdo->query(
        'SELECT id, period_key, entry_type, label, amount_cents, total_amount_cents, is_installment,
                installment_number, installment_total, is_settled, carry_source_entry_id, sort_order, created_at, updated_at
         FROM workspace_accounting_entries'
    )->fetchAll();
    if ($rows) {
        $normalizeStmt = $pdo->prepare(
            'UPDATE workspace_accounting_entries
             SET period_key = :period_key,
                 entry_type = :entry_type,
                 label = :label,
                 amount_cents = :amount_cents,
                 total_amount_cents = :total_amount_cents,
                 is_installment = :is_installment,
                 installment_number = :installment_number,
                 installment_total = :installment_total,
                 is_settled = :is_settled,
                 carry_source_entry_id = :carry_source_entry_id,
                 sort_order = :sort_order,
                 created_at = :created_at,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        foreach ($rows as $row) {
            $normalizedPeriod = normalizeAccountingPeriodKey((string) ($row['period_key'] ?? ''));
            $normalizedType = normalizeAccountingEntryType((string) ($row['entry_type'] ?? 'expense'));
            $normalizedLabel = normalizeAccountingEntryLabel((string) ($row['label'] ?? ''));
            $normalizedAmount = normalizeDueAmountCents($row['amount_cents'] ?? null) ?? 0;
            $normalizedTotalAmount = normalizeDueAmountCents($row['total_amount_cents'] ?? null);
            if ($normalizedTotalAmount === null || $normalizedTotalAmount <= 0) {
                $normalizedTotalAmount = $normalizedAmount;
            }
            $installmentMeta = normalizeAccountingInstallmentMeta(
                $row['is_installment'] ?? 0,
                $row['installment_number'] ?? 0,
                $row['installment_total'] ?? 0,
                $normalizedTotalAmount
            );
            if ($installmentMeta['is_installment'] === 1) {
                $normalizedAmount = accountingInstallmentAmountCents(
                    $normalizedTotalAmount,
                    $installmentMeta['installment_number'],
                    $installmentMeta['installment_total']
                );
            } else {
                $normalizedTotalAmount = $normalizedAmount;
            }
            $normalizedSettled = ((int) ($row['is_settled'] ?? 0)) === 1 ? 1 : 0;
            $carrySourceEntryId = isset($row['carry_source_entry_id']) ? (int) $row['carry_source_entry_id'] : 0;
            if ($carrySourceEntryId <= 0) {
                $carrySourceEntryId = null;
            }
            $normalizedSortOrder = max(0, (int) ($row['sort_order'] ?? 0));
            $createdAt = trim((string) ($row['created_at'] ?? ''));
            $updatedAt = trim((string) ($row['updated_at'] ?? ''));
            $normalizeStmt->execute([
                ':period_key' => $normalizedPeriod,
                ':entry_type' => $normalizedType,
                ':label' => $normalizedLabel,
                ':amount_cents' => $normalizedAmount,
                ':total_amount_cents' => $normalizedTotalAmount,
                ':is_installment' => $installmentMeta['is_installment'],
                ':installment_number' => $installmentMeta['installment_number'],
                ':installment_total' => $installmentMeta['installment_total'],
                ':is_settled' => $normalizedSettled,
                ':carry_source_entry_id' => $carrySourceEntryId,
                ':sort_order' => $normalizedSortOrder,
                ':created_at' => $createdAt !== '' ? $createdAt : nowIso(),
                ':updated_at' => $updatedAt !== '' ? $updatedAt : nowIso(),
                ':id' => (int) ($row['id'] ?? 0),
            ]);
        }
    }

    $periodRows = $pdo->query(
        'SELECT id, period_key, opening_balance_cents, updated_at
         FROM workspace_accounting_periods'
    )->fetchAll();
    if ($periodRows) {
        $periodNormalizeStmt = $pdo->prepare(
            'UPDATE workspace_accounting_periods
             SET period_key = :period_key,
                 opening_balance_cents = :opening_balance_cents,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        foreach ($periodRows as $periodRow) {
            $periodNormalizeStmt->execute([
                ':period_key' => normalizeAccountingPeriodKey((string) ($periodRow['period_key'] ?? '')),
                ':opening_balance_cents' => normalizeDueAmountCents($periodRow['opening_balance_cents'] ?? null) ?? 0,
                ':updated_at' => trim((string) ($periodRow['updated_at'] ?? '')) !== ''
                    ? trim((string) ($periodRow['updated_at'] ?? ''))
                    : nowIso(),
                ':id' => (int) ($periodRow['id'] ?? 0),
            ]);
        }
    }
}

function ensureGroupPermissionSchema(PDO $pdo): void
{
    $driver = dbDriverName($pdo);

    if ($driver === 'pgsql') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS task_group_permissions (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                group_name TEXT NOT NULL,
                user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                can_view SMALLINT NOT NULL DEFAULT 1,
                can_access SMALLINT NOT NULL DEFAULT 1,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_vault_group_permissions (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                group_name TEXT NOT NULL,
                user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                can_view SMALLINT NOT NULL DEFAULT 1,
                can_access SMALLINT NOT NULL DEFAULT 1,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_due_group_permissions (
                id BIGSERIAL PRIMARY KEY,
                workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
                group_name TEXT NOT NULL,
                user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                can_view SMALLINT NOT NULL DEFAULT 1,
                can_access SMALLINT NOT NULL DEFAULT 1,
                updated_at TIMESTAMP WITHOUT TIME ZONE NOT NULL
            )'
        );
    } else {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS task_group_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                group_name TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                can_view INTEGER NOT NULL DEFAULT 1,
                can_access INTEGER NOT NULL DEFAULT 1,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_vault_group_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                group_name TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                can_view INTEGER NOT NULL DEFAULT 1,
                can_access INTEGER NOT NULL DEFAULT 1,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS workspace_due_group_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workspace_id INTEGER NOT NULL,
                group_name TEXT NOT NULL,
                user_id INTEGER NOT NULL,
                can_view INTEGER NOT NULL DEFAULT 1,
                can_access INTEGER NOT NULL DEFAULT 1,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
    }

    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_task_group_permissions_workspace_group_user
         ON task_group_permissions(workspace_id, group_name, user_id)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_task_group_permissions_workspace_user
         ON task_group_permissions(workspace_id, user_id)'
    );
    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_vault_group_permissions_workspace_group_user
         ON workspace_vault_group_permissions(workspace_id, group_name, user_id)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_vault_group_permissions_workspace_user
         ON workspace_vault_group_permissions(workspace_id, user_id)'
    );
    $pdo->exec(
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_due_group_permissions_workspace_group_user
         ON workspace_due_group_permissions(workspace_id, group_name, user_id)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_due_group_permissions_workspace_user
         ON workspace_due_group_permissions(workspace_id, user_id)'
    );

    $normalizers = [
        'task_group_permissions' => 'normalizeTaskGroupName',
        'workspace_vault_group_permissions' => 'normalizeVaultGroupName',
        'workspace_due_group_permissions' => 'normalizeDueGroupName',
    ];

    foreach ($normalizers as $tableName => $normalizeFn) {
        $rows = $pdo->query(
            'SELECT id, group_name, can_view, can_access
             FROM ' . $tableName
        )->fetchAll();
        if (!$rows) {
            continue;
        }

        $updateStmt = $pdo->prepare(
            'UPDATE ' . $tableName . '
             SET group_name = :group_name,
                 can_view = :can_view,
                 can_access = :can_access,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $updatedAt = nowIso();

        foreach ($rows as $row) {
            $canView = ((int) ($row['can_view'] ?? 1)) === 1 ? 1 : 0;
            $canAccess = ((int) ($row['can_access'] ?? 1)) === 1 ? 1 : 0;
            if ($canView === 0) {
                $canAccess = 0;
            }

            $updateStmt->execute([
                ':group_name' => $normalizeFn((string) ($row['group_name'] ?? 'Geral')),
                ':can_view' => $canView,
                ':can_access' => $canAccess,
                ':updated_at' => $updatedAt,
                ':id' => (int) ($row['id'] ?? 0),
            ]);
        }
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

function pgConstraintExists(PDO $pdo, string $constraintName): bool
{
    if (dbDriverName($pdo) !== 'pgsql') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM pg_constraint
         WHERE conname = :name
         LIMIT 1'
    );
    $stmt->execute([':name' => $constraintName]);

    return (bool) $stmt->fetchColumn();
}

function generateUuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
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

function loginUser(int $userId, bool $remember = true): void
{
    $_SESSION['user_id'] = $userId;
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
        'domain' => '',
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
        'domain' => '',
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

function deleteRememberTokensForUser(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $stmt = db()->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);
}

function appBasePath(): string
{
    $configuredAppUrl = trim((string) envValue('APP_URL', ''));
    if ($configuredAppUrl !== '') {
        $configuredPath = (string) (parse_url($configuredAppUrl, PHP_URL_PATH) ?? '');
        $configuredPath = preg_replace('~/index\.php/?$~i', '', $configuredPath) ?? $configuredPath;
        $configuredPath = '/' . ltrim($configuredPath, '/');
        $configuredPath = rtrim($configuredPath, '/');
        return $configuredPath === '/' ? '' : $configuredPath;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $scriptDirectory = str_replace('\\', '/', dirname($scriptName));
    $scriptDirectory = rtrim($scriptDirectory, '/');
    if ($scriptDirectory === '' || $scriptDirectory === '.' || $scriptDirectory === '/') {
        return '';
    }

    return $scriptDirectory;
}

function normalizeDashboardViewKey(string $view): string
{
    $normalized = strtolower(trim($view));
    return in_array($normalized, ['overview', 'tasks', 'vault', 'dues', 'inventory', 'accounting', 'users'], true)
        ? $normalized
        : '';
}

function dashboardStateQueryParamsFromFragment(string $fragment): ?array
{
    $normalizedFragment = trim($fragment);
    if ($normalizedFragment === '') {
        return null;
    }

    if (preg_match('/^task-(\d+)$/', $normalizedFragment, $matches)) {
        $taskId = (int) ($matches[1] ?? 0);
        if ($taskId > 0) {
            return [
                'view' => 'tasks',
                'task' => (string) $taskId,
            ];
        }
    }

    $view = normalizeDashboardViewKey($normalizedFragment);
    if ($view === '') {
        return null;
    }

    return [
        'view' => $view === 'overview' ? null : $view,
        'task' => null,
    ];
}

function canonicalizeAppRelativePath(string $path): string
{
    $parsed = parse_url($path);
    if ($parsed === false) {
        return $path;
    }

    $pathPart = (string) ($parsed['path'] ?? '');
    $fragment = trim((string) ($parsed['fragment'] ?? ''));
    $queryParams = [];
    if (isset($parsed['query']) && trim((string) $parsed['query']) !== '') {
        parse_str((string) $parsed['query'], $queryParams);
    }

    $remainingFragment = '';
    $fragmentState = dashboardStateQueryParamsFromFragment($fragment);
    if ($fragmentState !== null) {
        foreach ($fragmentState as $paramKey => $paramValue) {
            if ($paramValue === null || trim((string) $paramValue) === '') {
                unset($queryParams[$paramKey]);
                continue;
            }

            $queryParams[$paramKey] = (string) $paramValue;
        }
    } elseif ($fragment !== '') {
        $remainingFragment = '#' . $fragment;
    }

    $normalizedView = normalizeDashboardViewKey((string) ($queryParams['view'] ?? ''));
    if ($normalizedView === '' || $normalizedView === 'overview') {
        unset($queryParams['view']);
    } else {
        $queryParams['view'] = $normalizedView;
    }

    $taskId = (int) ($queryParams['task'] ?? 0);
    if ($taskId > 0) {
        if (($queryParams['view'] ?? 'tasks') !== 'tasks') {
            unset($queryParams['task']);
        } else {
            $queryParams['view'] = 'tasks';
            $queryParams['task'] = (string) $taskId;
        }
    } else {
        unset($queryParams['task']);
    }

    $queryString = http_build_query($queryParams);
    return $pathPart
        . ($queryString !== '' ? '?' . $queryString : '')
        . $remainingFragment;
}

function dashboardPath(?string $view = null, array $params = []): string
{
    $normalizedView = normalizeDashboardViewKey((string) ($view ?? ''));
    if ($normalizedView === '' || $normalizedView === 'overview') {
        unset($params['view']);
    } else {
        $params['view'] = $normalizedView;
    }

    $taskId = (int) ($params['task'] ?? 0);
    if ($taskId > 0) {
        $params['view'] = 'tasks';
        $params['task'] = (string) $taskId;
    } else {
        unset($params['task']);
    }

    $queryString = http_build_query($params);
    return appPath($queryString !== '' ? '?' . $queryString : '');
}

function taskDetailPath(int $taskId, array $params = []): string
{
    if ($taskId > 0) {
        $params['task'] = (string) $taskId;
    } else {
        unset($params['task']);
    }

    return dashboardPath('tasks', $params);
}

function appPath(string $path = ''): string
{
    $trimmedPath = trim($path);
    $basePath = appBasePath();
    $baseRoot = $basePath !== '' ? $basePath . '/' : '/';

    if ($trimmedPath === '') {
        return $baseRoot;
    }

    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $trimmedPath) || str_starts_with($trimmedPath, '//')) {
        return $trimmedPath;
    }

    if (preg_match('~^/?index\.php(?=$|[?#])~i', $trimmedPath)) {
        $trimmedPath = preg_replace('~^/?index\.php~i', '', $trimmedPath) ?? $trimmedPath;
        if ($trimmedPath === '') {
            return $baseRoot;
        }
    }

    $trimmedPath = canonicalizeAppRelativePath($trimmedPath);
    if ($trimmedPath === '') {
        return $baseRoot;
    }

    if ($trimmedPath[0] === '?' || $trimmedPath[0] === '#') {
        return $baseRoot . ltrim($trimmedPath, '/');
    }

    if ($trimmedPath[0] === '/') {
        if (
            $basePath === ''
            || $trimmedPath === $basePath
            || str_starts_with($trimmedPath, $basePath . '/')
            || str_starts_with($trimmedPath, $basePath . '?')
            || str_starts_with($trimmedPath, $basePath . '#')
        ) {
            return $trimmedPath;
        }

        return ($basePath !== '' ? $basePath : '') . $trimmedPath;
    }

    return ($basePath !== '' ? $basePath . '/' : '/') . ltrim($trimmedPath, '/');
}

function appEntryUrl(): string
{
    $configuredAppUrl = trim((string) envValue('APP_URL', ''));
    if ($configuredAppUrl !== '') {
        return rtrim(preg_replace('~/index\.php/?$~i', '', $configuredAppUrl) ?? $configuredAppUrl, '/');
    }

    $scheme = requestIsHttps() ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $scheme . '://' . $host . appBasePath();
}

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
        throw new RuntimeException('Usuario invalido para redefinicao de senha.');
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

function httpPostJson(string $url, array $headers, array $payload, int $timeoutSeconds = 15): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) {
        throw new RuntimeException('Falha ao serializar payload JSON.');
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Falha ao inicializar cliente HTTP.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge(
                ['Content-Type: application/json', 'Content-Length: ' . strlen($body)],
                $headers
            ),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ]);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error !== '' ? $error : 'Falha desconhecida ao enviar requisicao HTTP.');
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [
            'status_code' => $statusCode,
            'body' => (string) $responseBody,
        ];
    }

    $headerLines = array_merge(
        ['Content-Type: application/json', 'Content-Length: ' . strlen($body)],
        $headers
    );
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headerLines),
            'content' => $body,
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $statusCode = 0;
    foreach ($responseHeaders as $headerLine) {
        if (preg_match('/\s(\d{3})\s/', (string) $headerLine, $matches)) {
            $statusCode = (int) ($matches[1] ?? 0);
            break;
        }
    }

    if ($responseBody === false && $statusCode === 0) {
        throw new RuntimeException('Falha ao enviar requisicao HTTP.');
    }

    return [
        'status_code' => $statusCode,
        'body' => (string) $responseBody,
    ];
}

function sendTextEmailViaResend(
    string $toEmail,
    string $subject,
    string $textBody,
    string $fromAddress,
    string $fromName
): array {
    $apiKey = trim((string) envValue('RESEND_API_KEY', ''));
    if ($apiKey === '' || $fromAddress === '') {
        return ['sent' => false, 'provider' => 'resend', 'configured' => false];
    }

    $replyTo = trim((string) envValue('MAIL_REPLY_TO', ''));
    $payload = [
        'from' => $fromName !== '' ? ($fromName . ' <' . $fromAddress . '>') : $fromAddress,
        'to' => [$toEmail],
        'subject' => $subject,
        'text' => $textBody,
    ];
    if ($replyTo !== '') {
        $payload['reply_to'] = $replyTo;
    }

    try {
        $response = httpPostJson(
            'https://api.resend.com/emails',
            ['Authorization: Bearer ' . $apiKey],
            $payload
        );
    } catch (Throwable $e) {
        return [
            'sent' => false,
            'provider' => 'resend',
            'configured' => true,
            'error' => $e->getMessage(),
        ];
    }

    $statusCode = (int) ($response['status_code'] ?? 0);
    if ($statusCode >= 200 && $statusCode < 300) {
        return ['sent' => true, 'logged_to_file' => false, 'provider' => 'resend'];
    }

    return [
        'sent' => false,
        'provider' => 'resend',
        'configured' => true,
        'status_code' => $statusCode,
        'response_body' => (string) ($response['body'] ?? ''),
    ];
}

function sendPasswordResetEmail(string $email, string $name, string $resetUrl, string $expiresAt): array
{
    $email = strtolower(trim($email));
    $name = trim($name);
    if ($email === '') {
        return ['sent' => false, 'logged_to_file' => false];
    }

    $subject = APP_NAME . ' | Redefinicao de senha';
    $body = implode("\n", [
        'Oi' . ($name !== '' ? ' ' . $name : '') . ',',
        '',
        'Recebemos um pedido para redefinir a senha da sua conta no ' . APP_NAME . '.',
        'Use o link abaixo para cadastrar uma nova senha:',
        $resetUrl,
        '',
        'Este link expira em ' . $expiresAt . '.',
        'Se voce nao fez esse pedido, pode ignorar esta mensagem.',
    ]);

    $configuredFromAddress = trim((string) envValue('MAIL_FROM_ADDRESS', ''));
    $fromAddress = $configuredFromAddress !== '' ? $configuredFromAddress : 'no-reply@bexon.local';
    $fromName = trim((string) envValue('MAIL_FROM_NAME', APP_NAME));
    $resendResult = sendTextEmailViaResend($email, $subject, $body, $configuredFromAddress, $fromName);
    if (!empty($resendResult['sent'])) {
        return $resendResult;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromAddress . '>',
    ];

    $sent = @mail($email, $subject, $body, implode("\r\n", $headers));
    if ($sent) {
        return ['sent' => true, 'logged_to_file' => false];
    }

    ensureStorage();
    $logEntry = implode("\n", [
        str_repeat('=', 72),
        'Timestamp: ' . nowIso(),
        'To: ' . $email,
        'Subject: ' . $subject,
        'Expires At: ' . $expiresAt,
        'Provider: ' . (string) ($resendResult['provider'] ?? 'mail'),
        'Provider Error: ' . (string) ($resendResult['error'] ?? ($resendResult['response_body'] ?? '')),
        '',
        $body,
        '',
    ]);
    file_put_contents(PASSWORD_RESET_LOG_PATH, $logEntry, FILE_APPEND | LOCK_EX);

    return [
        'sent' => false,
        'logged_to_file' => true,
        'log_path' => PASSWORD_RESET_LOG_PATH,
    ];
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
    header('Location: ' . appPath($path));
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
        throw new RuntimeException('Sessao expirada ou token CSRF invalido. Recarregue a pagina e tente novamente.');
    }
}

function workspaceRoles(): array
{
    return [
        'admin' => 'Administrador',
        'member' => 'Usuario',
    ];
}

function normalizeWorkspaceRole(string $value): string
{
    $value = trim(mb_strtolower($value));
    return array_key_exists($value, workspaceRoles()) ? $value : 'member';
}

function normalizePermissionFlag($value): int
{
    return ((int) $value) === 1 ? 1 : 0;
}

function normalizeWorkspaceName(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'Formula Online';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mb_strlen($value) > 80) {
        $value = mb_substr($value, 0, 80);
    }

    return uppercaseFirstCharacter($value);
}

function workspaceSlugify(string $value): string
{
    $raw = trim($value);
    if ($raw === '') {
        return 'workspace';
    }

    if (function_exists('iconv')) {
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $raw);
        if (is_string($translit) && $translit !== '') {
            $raw = $translit;
        }
    }

    $raw = mb_strtolower($raw);
    $slug = preg_replace('/[^a-z0-9]+/u', '-', $raw) ?? '';
    $slug = trim($slug, '-');

    if ($slug === '') {
        return 'workspace';
    }

    return mb_substr($slug, 0, 96);
}

function workspaceSlugExists(PDO $pdo, string $slug): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM workspaces WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    return (bool) $stmt->fetchColumn();
}

function generateWorkspaceSlug(PDO $pdo, string $workspaceName): string
{
    $base = workspaceSlugify($workspaceName);
    $slug = $base;
    $suffix = 2;

    while (workspaceSlugExists($pdo, $slug)) {
        $slug = mb_substr($base, 0, 90) . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

function guessPrimaryAdminUserId(PDO $pdo): ?int
{
    $rows = $pdo->query('SELECT id, name, email FROM users ORDER BY id ASC')->fetchAll();
    if (!$rows) {
        return null;
    }

    foreach ($rows as $row) {
        $userId = (int) ($row['id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $name = mb_strtolower(trim((string) ($row['name'] ?? '')));
        $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
        if (str_contains($name, 'bruno') || str_contains($email, 'bruno')) {
            continue;
        }

        return $userId;
    }

    return (int) ($rows[0]['id'] ?? 0) ?: null;
}

function ensureWorkspaceTaskStatusSchema(PDO $pdo): void
{
    static $checkedConnections = [];

    $connectionId = spl_object_id($pdo);
    if (!empty($checkedConnections[$connectionId])) {
        return;
    }

    ensureWorkspaceProfileSchema($pdo);

    $columns = [
        'task_statuses_json' => "ALTER TABLE workspaces ADD COLUMN task_statuses_json TEXT NOT NULL DEFAULT '[]'",
        'task_review_status_key' => 'ALTER TABLE workspaces ADD COLUMN task_review_status_key TEXT DEFAULT NULL',
        'sidebar_tools_json' => "ALTER TABLE workspaces ADD COLUMN sidebar_tools_json TEXT NOT NULL DEFAULT '[]'",
    ];

    foreach ($columns as $column => $statement) {
        if (tableHasColumn($pdo, 'workspaces', $column)) {
            continue;
        }

        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            if (!tableHasColumn($pdo, 'workspaces', $column)) {
                throw $e;
            }
        }
    }

    $checkedConnections[$connectionId] = true;
}

function workspaceById(int $workspaceId): ?array
{
    if ($workspaceId <= 0) {
        return null;
    }

    $pdo = db();
    ensureWorkspaceTaskStatusSchema($pdo);
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             w.id,
             w.name,
             w.slug,
             w.is_personal,
             w.avatar_data_url,
             w.created_by,
             creator.avatar_data_url AS owner_avatar_data_url,
             w.task_statuses_json,
             w.task_review_status_key,
             w.sidebar_tools_json,
             w.created_at,
             w.updated_at
         FROM workspaces w
         LEFT JOIN users creator ON creator.id = w.created_by
         WHERE w.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $workspaceId]);
    $workspace = $stmt->fetch();

    if (!$workspace) {
        return null;
    }

    $workspace['is_personal'] = ((int) ($workspace['is_personal'] ?? 0)) === 1;
    return $workspace;
}

function workspaceIsPersonal(int $workspaceId): bool
{
    if ($workspaceId <= 0) {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT is_personal
         FROM workspaces
         WHERE id = :workspace_id
         LIMIT 1'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    return ((int) $stmt->fetchColumn()) === 1;
}

function workspaceCanManageMembers(int $workspaceId): bool
{
    return !workspaceIsPersonal($workspaceId);
}

function personalWorkspaceForUserId(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $pdo = db();
    ensureWorkspaceProfileSchema($pdo);
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             w.id,
             w.name,
             w.slug,
             w.is_personal,
             w.avatar_data_url,
             w.created_by,
             creator.avatar_data_url AS owner_avatar_data_url,
             w.created_at,
             w.updated_at
         FROM workspaces w
         INNER JOIN workspace_members wm ON wm.workspace_id = w.id
         LEFT JOIN users creator ON creator.id = w.created_by
         WHERE wm.user_id = :member_user_id
           AND w.is_personal = 1
           AND w.created_by = :owner_user_id
         ORDER BY w.created_at ASC, w.id ASC
         LIMIT 1'
    );
    $stmt->execute([
        ':member_user_id' => $userId,
        ':owner_user_id' => $userId,
    ]);
    $workspace = $stmt->fetch();
    if (!$workspace) {
        return null;
    }

    $workspace['is_personal'] = true;
    return $workspace;
}

function workspaceRoleCacheKey(int $workspaceId, int $userId): string
{
    return $workspaceId . ':' . $userId;
}

function invalidateWorkspaceRoleCache(?int $workspaceId = null, ?int $userId = null): void
{
    if (!isset($GLOBALS['workspace_role_cache']) || !is_array($GLOBALS['workspace_role_cache'])) {
        return;
    }

    if ($workspaceId === null && $userId === null) {
        $GLOBALS['workspace_role_cache'] = [];
        return;
    }

    $cache = &$GLOBALS['workspace_role_cache'];
    if ($workspaceId !== null && $workspaceId > 0 && $userId !== null && $userId > 0) {
        unset($cache[workspaceRoleCacheKey($workspaceId, $userId)]);
        return;
    }

    if ($workspaceId !== null && $workspaceId > 0) {
        $prefix = $workspaceId . ':';
        foreach (array_keys($cache) as $cacheKey) {
            if (str_starts_with((string) $cacheKey, $prefix)) {
                unset($cache[$cacheKey]);
            }
        }
        return;
    }

    if ($userId !== null && $userId > 0) {
        $suffix = ':' . $userId;
        foreach (array_keys($cache) as $cacheKey) {
            if (str_ends_with((string) $cacheKey, $suffix)) {
                unset($cache[$cacheKey]);
            }
        }
    }
}

function workspaceRoleForUser(int $userId, int $workspaceId): ?string
{
    if ($userId <= 0 || $workspaceId <= 0) {
        return null;
    }

    if (!isset($GLOBALS['workspace_role_cache']) || !is_array($GLOBALS['workspace_role_cache'])) {
        $GLOBALS['workspace_role_cache'] = [];
    }
    $cache = &$GLOBALS['workspace_role_cache'];
    $cacheKey = workspaceRoleCacheKey($workspaceId, $userId);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = db()->prepare(
        'SELECT role
         FROM workspace_members
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    $role = $stmt->fetchColumn();
    if (!is_string($role) || trim($role) === '') {
        $cache[$cacheKey] = null;
        return null;
    }

    $normalizedRole = normalizeWorkspaceRole($role);
    $cache[$cacheKey] = $normalizedRole;
    return $normalizedRole;
}

function userHasWorkspaceAccess(int $userId, int $workspaceId): bool
{
    return workspaceRoleForUser($userId, $workspaceId) !== null;
}

function userCanManageWorkspace(int $userId, int $workspaceId): bool
{
    return workspaceRoleForUser($userId, $workspaceId) === 'admin';
}

function upsertWorkspaceMember(PDO $pdo, int $workspaceId, int $userId, string $role = 'member'): void
{
    if ($workspaceId <= 0 || $userId <= 0) {
        return;
    }

    $normalizedRole = normalizeWorkspaceRole($role);

    $existingStmt = $pdo->prepare(
        'SELECT role
         FROM workspace_members
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id
         LIMIT 1'
    );
    $existingStmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    $existingRole = $existingStmt->fetchColumn();
    if (is_string($existingRole) && trim($existingRole) !== '') {
        $existingRole = normalizeWorkspaceRole($existingRole);

        if ($existingRole === 'admin' && $normalizedRole !== 'admin') {
            return;
        }

        if ($existingRole !== $normalizedRole) {
            $updateStmt = $pdo->prepare(
                'UPDATE workspace_members
                 SET role = :role
                 WHERE workspace_id = :workspace_id
                   AND user_id = :user_id'
            );
            $updateStmt->execute([
                ':role' => $normalizedRole,
                ':workspace_id' => $workspaceId,
                ':user_id' => $userId,
            ]);
            invalidateWorkspaceRoleCache($workspaceId, $userId);
        }

        return;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO workspace_members (workspace_id, user_id, role, created_at)
         VALUES (:workspace_id, :user_id, :role, :created_at)'
    );
    $insertStmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
        ':role' => $normalizedRole,
        ':created_at' => nowIso(),
    ]);
    invalidateWorkspaceRoleCache($workspaceId, $userId);
}

function createWorkspace(PDO $pdo, string $workspaceName, int $createdBy, bool $isPersonal = false): int
{
    $createdBy = (int) $createdBy;
    if ($createdBy <= 0) {
        throw new RuntimeException('Criador do workspace invalido.');
    }

    ensureWorkspaceTaskStatusSchema($pdo);

    $name = normalizeWorkspaceName($workspaceName);
    $slug = generateWorkspaceSlug($pdo, $name);
    $now = nowIso();
    $personalFlag = $isPersonal ? 1 : 0;

    $taskStatusesJson = encodeWorkspaceTaskStatusDefinitions(defaultTaskStatusDefinitions());
    $taskReviewStatusKey = defaultTaskReviewStatusKey();
    $sidebarToolsJson = encodeWorkspaceSidebarTools([]);

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO workspaces (name, slug, is_personal, created_by, task_statuses_json, task_review_status_key, sidebar_tools_json, created_at, updated_at)
             VALUES (:name, :slug, :is_personal, :created_by, :task_statuses_json, :task_review_status_key, :sidebar_tools_json, :created_at, :updated_at)
             RETURNING id'
        );
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':is_personal' => $personalFlag,
            ':created_by' => $createdBy,
            ':task_statuses_json' => $taskStatusesJson,
            ':task_review_status_key' => $taskReviewStatusKey,
            ':sidebar_tools_json' => $sidebarToolsJson,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $workspaceId = (int) $stmt->fetchColumn();
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO workspaces (name, slug, is_personal, created_by, task_statuses_json, task_review_status_key, sidebar_tools_json, created_at, updated_at)
             VALUES (:name, :slug, :is_personal, :created_by, :task_statuses_json, :task_review_status_key, :sidebar_tools_json, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':is_personal' => $personalFlag,
            ':created_by' => $createdBy,
            ':task_statuses_json' => $taskStatusesJson,
            ':task_review_status_key' => $taskReviewStatusKey,
            ':sidebar_tools_json' => $sidebarToolsJson,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $workspaceId = (int) $pdo->lastInsertId();
    }

    upsertWorkspaceMember($pdo, $workspaceId, $createdBy, 'admin');
    upsertTaskGroup($pdo, 'Geral', $createdBy, $workspaceId);

    return $workspaceId;
}

function updateWorkspaceName(PDO $pdo, int $workspaceId, string $workspaceName): void
{
    updateWorkspaceProfile($pdo, $workspaceId, $workspaceName, [], true);
}

function updateWorkspaceProfile(
    PDO $pdo,
    int $workspaceId,
    string $workspaceName,
    array $avatarFile = [],
    bool $allowRename = true
): void {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    ensureWorkspaceProfileSchema($pdo);
    ensureUserProfileSchema($pdo);
    $workspace = workspaceById($workspaceId);
    if (!$workspace) {
        throw new RuntimeException('Workspace nao encontrado.');
    }
    $isPersonalWorkspace = !empty($workspace['is_personal']);
    $workspaceOwnerId = (int) ($workspace['created_by'] ?? 0);

    $params = [
        ':updated_at' => nowIso(),
        ':workspace_id' => $workspaceId,
    ];
    $setClauses = [
        'updated_at = :updated_at',
    ];

    if ($allowRename) {
        $trimmed = trim($workspaceName);
        if ($trimmed === '') {
            throw new RuntimeException('Informe um nome para o workspace.');
        }

        $params[':name'] = normalizeWorkspaceName($trimmed);
        $setClauses[] = 'name = :name';
    }

    $avatarDataUrl = uploadedWorkspaceAvatarDataUrl($avatarFile);
    if ($avatarDataUrl !== '') {
        if ($isPersonalWorkspace && $workspaceOwnerId > 0) {
            $userAvatarStmt = $pdo->prepare(
                'UPDATE users
                 SET avatar_data_url = :avatar_data_url
                 WHERE id = :user_id'
            );
            $userAvatarStmt->execute([
                ':avatar_data_url' => $avatarDataUrl,
                ':user_id' => $workspaceOwnerId,
            ]);
        } else {
            $params[':avatar_data_url'] = $avatarDataUrl;
            $setClauses[] = 'avatar_data_url = :avatar_data_url';
        }
    }

    if (count($setClauses) === 1 && $avatarDataUrl === '') {
        throw new RuntimeException('Nenhuma alteracao enviada para o workspace.');
    }

    $stmt = $pdo->prepare(
        'UPDATE workspaces
         SET ' . implode(', ', $setClauses) . '
         WHERE id = :workspace_id'
    );
    $stmt->execute($params);
}

function workspaceAdminCount(int $workspaceId): int
{
    if ($workspaceId <= 0) {
        return 0;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM workspace_members
         WHERE workspace_id = :workspace_id
           AND role = :role'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':role' => 'admin',
    ]);

    return (int) $stmt->fetchColumn();
}

function updateWorkspaceMemberRole(PDO $pdo, int $workspaceId, int $userId, string $role): void
{
    if ($workspaceId <= 0 || $userId <= 0) {
        throw new RuntimeException('Usuario invalido.');
    }

    $nextRole = normalizeWorkspaceRole($role);
    $currentRole = workspaceRoleForUser($userId, $workspaceId);
    if ($currentRole === null) {
        throw new RuntimeException('Usuario nao pertence a este workspace.');
    }
    if ($currentRole === $nextRole) {
        return;
    }

    if ($currentRole === 'admin' && $nextRole !== 'admin' && workspaceAdminCount($workspaceId) <= 1) {
        throw new RuntimeException('Mantenha pelo menos um administrador no workspace.');
    }

    $stmt = $pdo->prepare(
        'UPDATE workspace_members
         SET role = :role
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id'
    );
    $stmt->execute([
        ':role' => $nextRole,
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    invalidateWorkspaceRoleCache($workspaceId, $userId);
}

function removeWorkspaceMember(PDO $pdo, int $workspaceId, int $userId): void
{
    if ($workspaceId <= 0 || $userId <= 0) {
        throw new RuntimeException('Usuario invalido.');
    }

    $existingRole = workspaceRoleForUser($userId, $workspaceId);
    if ($existingRole === null) {
        throw new RuntimeException('Usuario nao pertence a este workspace.');
    }

    if ($existingRole === 'admin' && workspaceAdminCount($workspaceId) <= 1) {
        throw new RuntimeException('Mantenha pelo menos um administrador no workspace.');
    }

    $stmt = $pdo->prepare(
        'DELETE FROM workspace_members
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    invalidateWorkspaceRoleCache($workspaceId, $userId);
}

function normalizeUserDisplayName(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mb_strlen($value) > 80) {
        $value = mb_substr($value, 0, 80);
    }

    return uppercaseFirstCharacter($value);
}

function normalizeUserAvatarDataUrl(?string $value): string
{
    $normalized = preg_replace('/\s+/', '', trim((string) $value)) ?? '';
    if ($normalized === '') {
        return '';
    }

    if (!preg_match('#^data:image/(?:png|jpe?g|webp|gif);base64,[a-z0-9+/=]+$#i', $normalized)) {
        return '';
    }

    return $normalized;
}

function userAvatarDataUrl(array $user): string
{
    return normalizeUserAvatarDataUrl((string) ($user['avatar_data_url'] ?? ''));
}

function userDisplayInitial(?string $name): string
{
    $normalized = normalizeUserDisplayName((string) $name);
    if ($normalized === '') {
        return '?';
    }

    return mb_strtoupper(mb_substr($normalized, 0, 1));
}

function renderUserAvatar(array $user, string $class = 'avatar', bool $ariaHidden = true, string $tag = 'div'): string
{
    $tag = in_array($tag, ['div', 'span'], true) ? $tag : 'div';
    $class = trim($class);
    $avatarDataUrl = userAvatarDataUrl($user);
    $name = trim((string) ($user['name'] ?? 'Usuario'));
    $attributes = $class !== '' ? ' class="' . e($class . ($avatarDataUrl !== '' ? ' has-image' : '')) . '"' : '';
    $attributes .= $ariaHidden ? ' aria-hidden="true"' : ' aria-label="' . e($name !== '' ? $name : 'Usuario') . '"';

    if ($avatarDataUrl !== '') {
        return sprintf(
            '<%1$s%2$s><img src="%3$s" alt="" loading="lazy"></%1$s>',
            $tag,
            $attributes,
            e($avatarDataUrl)
        );
    }

    return sprintf(
        '<%1$s%2$s>%3$s</%1$s>',
        $tag,
        $attributes,
        e(userDisplayInitial($name))
    );
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
        throw new RuntimeException('Arquivo de foto invalido.');
    }

    if ($size > 2 * 1024 * 1024) {
        throw new RuntimeException('A foto de perfil deve ter no maximo 2 MB.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException('Arquivo de foto invalido.');
    }

    $contents = file_get_contents($tmpName);
    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('Nao foi possivel ler a foto de perfil.');
    }

    $imageInfo = @getimagesizefromstring($contents);
    $mime = strtolower((string) ($imageInfo['mime'] ?? ''));
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!$imageInfo || !in_array($mime, $allowedMimeTypes, true)) {
        throw new RuntimeException('Use uma imagem PNG, JPG, WEBP ou GIF.');
    }

    return 'data:' . $mime . ';base64,' . base64_encode($contents);
}

function normalizeWorkspaceAvatarDataUrl(?string $value): string
{
    return normalizeUserAvatarDataUrl($value);
}

function workspaceAvatarDataUrl(array $workspace): string
{
    if (!empty($workspace['is_personal'])) {
        $ownerAvatarDataUrl = normalizeUserAvatarDataUrl((string) ($workspace['owner_avatar_data_url'] ?? ''));
        if ($ownerAvatarDataUrl !== '') {
            return $ownerAvatarDataUrl;
        }
    }

    return normalizeWorkspaceAvatarDataUrl((string) ($workspace['avatar_data_url'] ?? ''));
}

function workspaceDisplayInitial(?string $name): string
{
    $normalized = normalizeWorkspaceName((string) $name);
    if ($normalized === '') {
        return '?';
    }

    return mb_strtoupper(mb_substr($normalized, 0, 1));
}

function renderWorkspaceAvatar(
    array $workspace,
    string $class = 'avatar',
    bool $ariaHidden = true,
    string $tag = 'div'
): string {
    $tag = in_array($tag, ['div', 'span'], true) ? $tag : 'div';
    $class = trim($class);
    $avatarDataUrl = workspaceAvatarDataUrl($workspace);
    $name = trim((string) ($workspace['name'] ?? 'Workspace'));
    $isPersonalWorkspace = !empty($workspace['is_personal']);
    $classNames = trim(
        'workspace-avatar '
        . ($isPersonalWorkspace ? ' workspace-avatar-personal' : '')
        . ' '
        . $class
        . ($avatarDataUrl !== '' ? ' has-image' : '')
    );
    $attributes = $classNames !== '' ? ' class="' . e($classNames) . '"' : '';
    $attributes .= $ariaHidden ? ' aria-hidden="true"' : ' aria-label="' . e($name !== '' ? $name : 'Workspace') . '"';

    if ($avatarDataUrl !== '') {
        return sprintf(
            '<%1$s%2$s><img src="%3$s" alt="" loading="lazy"></%1$s>',
            $tag,
            $attributes,
            e($avatarDataUrl)
        );
    }

    return sprintf(
        '<%1$s%2$s>%3$s</%1$s>',
        $tag,
        $attributes,
        e(workspaceDisplayInitial($name))
    );
}

function uploadedWorkspaceAvatarDataUrl(array $file): string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha ao enviar a foto do workspace.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Arquivo de foto do workspace invalido.');
    }

    if ($size > 2 * 1024 * 1024) {
        throw new RuntimeException('A foto do workspace deve ter no maximo 2 MB.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException('Arquivo de foto do workspace invalido.');
    }

    $contents = file_get_contents($tmpName);
    if (!is_string($contents) || $contents === '') {
        throw new RuntimeException('Nao foi possivel ler a foto do workspace.');
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
        throw new RuntimeException('Usuario invalido.');
    }

    ensureUserProfileSchema($pdo);

    $normalizedName = normalizeUserDisplayName($name);
    if ($normalizedName === '') {
        throw new RuntimeException('Informe um nome valido.');
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
        throw new RuntimeException('Usuario invalido.');
    }

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        throw new RuntimeException('Preencha os campos de senha.');
    }

    if ($newPassword !== $confirmPassword) {
        throw new RuntimeException('A confirmacao da nova senha nao confere.');
    }

    if (mb_strlen($newPassword) < 6) {
        throw new RuntimeException('A nova senha deve ter pelo menos 6 caracteres.');
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $hash = (string) $stmt->fetchColumn();
    if ($hash === '') {
        throw new RuntimeException('Usuario nao encontrado.');
    }

    if (!password_verify($currentPassword, $hash)) {
        throw new RuntimeException('Senha atual invalida.');
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

function workspaceMembershipCount(int $workspaceId): int
{
    if ($workspaceId <= 0) {
        return 0;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM workspace_members
         WHERE workspace_id = :workspace_id'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    return (int) $stmt->fetchColumn();
}

function deleteWorkspaceOwnedByUser(PDO $pdo, int $workspaceId, int $ownerUserId): void
{
    if ($workspaceId <= 0 || $ownerUserId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    $workspaceStmt = $pdo->prepare(
        'SELECT id, created_by
         FROM workspaces
         WHERE id = :workspace_id
         LIMIT 1'
    );
    $workspaceStmt->execute([':workspace_id' => $workspaceId]);
    $workspace = $workspaceStmt->fetch();
    if (!$workspace) {
        throw new RuntimeException('Workspace nao encontrado.');
    }

    if ((int) ($workspace['created_by'] ?? 0) !== $ownerUserId) {
        throw new RuntimeException('Somente o criador pode remover este workspace.');
    }

    $pdo->beginTransaction();
    try {
        $deleteHistoryStmt = $pdo->prepare(
            'DELETE FROM task_history
             WHERE task_id IN (
                SELECT id
                FROM tasks
                WHERE workspace_id = :workspace_id
             )'
        );
        $deleteHistoryStmt->execute([':workspace_id' => $workspaceId]);

        $deleteTasksStmt = $pdo->prepare(
            'DELETE FROM tasks
             WHERE workspace_id = :workspace_id'
        );
        $deleteTasksStmt->execute([':workspace_id' => $workspaceId]);

        $deleteGroupsStmt = $pdo->prepare(
            'DELETE FROM task_groups
             WHERE workspace_id = :workspace_id'
        );
        $deleteGroupsStmt->execute([':workspace_id' => $workspaceId]);

        $deleteMembersStmt = $pdo->prepare(
            'DELETE FROM workspace_members
             WHERE workspace_id = :workspace_id'
        );
        $deleteMembersStmt->execute([':workspace_id' => $workspaceId]);
        invalidateWorkspaceRoleCache($workspaceId);

        $deleteWorkspaceStmt = $pdo->prepare(
            'DELETE FROM workspaces
             WHERE id = :workspace_id
               AND created_by = :owner_user_id'
        );
        $deleteWorkspaceStmt->execute([
            ':workspace_id' => $workspaceId,
            ':owner_user_id' => $ownerUserId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function leaveWorkspace(PDO $pdo, int $workspaceId, int $userId): void
{
    if ($workspaceId <= 0 || $userId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    $workspaceStmt = $pdo->prepare(
        'SELECT id, created_by
         FROM workspaces
         WHERE id = :workspace_id
         LIMIT 1'
    );
    $workspaceStmt->execute([':workspace_id' => $workspaceId]);
    $workspace = $workspaceStmt->fetch();
    if (!$workspace) {
        throw new RuntimeException('Workspace nao encontrado.');
    }

    if ((int) ($workspace['created_by'] ?? 0) === $userId) {
        throw new RuntimeException('Voce criou este workspace. Use a opcao de remover workspace.');
    }

    $role = workspaceRoleForUser($userId, $workspaceId);
    if ($role === null) {
        throw new RuntimeException('Voce nao pertence a este workspace.');
    }

    $pdo->beginTransaction();
    try {
        if ($role === 'admin' && workspaceAdminCount($workspaceId) <= 1) {
            $promoteStmt = $pdo->prepare(
                'SELECT user_id
                 FROM workspace_members
                 WHERE workspace_id = :workspace_id
                   AND user_id <> :user_id
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $promoteStmt->execute([
                ':workspace_id' => $workspaceId,
                ':user_id' => $userId,
            ]);
            $nextAdminUserId = (int) $promoteStmt->fetchColumn();
            if ($nextAdminUserId <= 0) {
                throw new RuntimeException('Nao foi possivel sair deste workspace agora.');
            }

            $updateRoleStmt = $pdo->prepare(
                'UPDATE workspace_members
                 SET role = :role
                 WHERE workspace_id = :workspace_id
                   AND user_id = :user_id'
            );
            $updateRoleStmt->execute([
                ':role' => 'admin',
                ':workspace_id' => $workspaceId,
                ':user_id' => $nextAdminUserId,
            ]);
            invalidateWorkspaceRoleCache($workspaceId, $nextAdminUserId);
        }

        $removeStmt = $pdo->prepare(
            'DELETE FROM workspace_members
             WHERE workspace_id = :workspace_id
               AND user_id = :user_id'
        );
        $removeStmt->execute([
            ':workspace_id' => $workspaceId,
            ':user_id' => $userId,
        ]);
        invalidateWorkspaceRoleCache($workspaceId, $userId);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function workspaceMembershipsDetailedForUser(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $pdo = db();
    ensureWorkspaceProfileSchema($pdo);
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             w.id,
             w.name,
             w.slug,
             w.is_personal,
             w.avatar_data_url,
             w.created_by,
             creator.avatar_data_url AS owner_avatar_data_url,
             w.created_at,
             w.updated_at,
             wm.role AS member_role,
             creator.name AS creator_name,
             creator.email AS creator_email,
             (
                SELECT COUNT(*)
                FROM workspace_members wm2
                WHERE wm2.workspace_id = w.id
             ) AS member_count
         FROM workspaces w
         INNER JOIN workspace_members wm ON wm.workspace_id = w.id
         LEFT JOIN users creator ON creator.id = w.created_by
         WHERE wm.user_id = :user_id
         ORDER BY
             CASE WHEN w.is_personal = 1 THEN 0 ELSE 1 END,
             w.created_at ASC,
             w.id ASC'
    );
    $stmt->execute([':user_id' => $userId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['created_by'] = (int) ($row['created_by'] ?? 0);
        $row['is_personal'] = ((int) ($row['is_personal'] ?? 0)) === 1;
        $row['member_count'] = (int) ($row['member_count'] ?? 0);
        $row['member_role'] = normalizeWorkspaceRole((string) ($row['member_role'] ?? 'member'));
        $row['is_owner'] = ((int) $row['created_by']) === $userId;
    }
    unset($row);

    return $rows;
}

function workspacesForUser(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $pdo = db();
    ensureWorkspaceProfileSchema($pdo);
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             w.id,
             w.name,
             w.slug,
             w.is_personal,
             w.avatar_data_url,
             w.created_by,
             creator.avatar_data_url AS owner_avatar_data_url,
             w.created_at,
             w.updated_at,
             wm.role AS member_role
         FROM workspaces w
         INNER JOIN workspace_members wm ON wm.workspace_id = w.id
         LEFT JOIN users creator ON creator.id = w.created_by
         WHERE wm.user_id = :user_id
         ORDER BY
             CASE WHEN w.is_personal = 1 THEN 0 ELSE 1 END,
             w.created_at ASC,
             w.id ASC'
    );
    $stmt->execute([':user_id' => $userId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['is_personal'] = ((int) ($row['is_personal'] ?? 0)) === 1;
        $row['member_role'] = normalizeWorkspaceRole((string) ($row['member_role'] ?? 'member'));
    }
    unset($row);

    return $rows;
}

function workspaceMembersList(int $workspaceId): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    $pdo = db();
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare(
        'SELECT
             u.id,
             u.name,
             u.email,
             u.avatar_data_url,
             wm.role AS workspace_role
         FROM workspace_members wm
         INNER JOIN users u ON u.id = wm.user_id
         WHERE wm.workspace_id = :workspace_id
         ORDER BY
             CASE wm.role WHEN \'admin\' THEN 1 ELSE 2 END,
             u.name ASC'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    $members = $stmt->fetchAll();

    foreach ($members as &$member) {
        $member['workspace_role'] = normalizeWorkspaceRole((string) ($member['workspace_role'] ?? 'member'));
    }
    unset($member);

    return $members;
}

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
        return 'Usuario Workspace';
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
        $userName = 'Usuario';
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

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        restoreRememberedSession();
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }

    $pdo = db();
    ensureUserProfileSchema($pdo);

    $stmt = $pdo->prepare('SELECT id, name, email, avatar_data_url, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        logoutUser();
        return null;
    }

    ensureUserWorkspaceAccess((int) $user['id']);
    ensureActiveWorkspaceSessionForUser((int) $user['id']);

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

function usersList(?int $workspaceId = null): array
{
    $pdo = db();
    ensureUserProfileSchema($pdo);

    if ($workspaceId === null) {
        return $pdo->query('SELECT id, name, email, avatar_data_url FROM users ORDER BY name ASC')->fetchAll();
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.name, u.email, u.avatar_data_url
         FROM workspace_members wm
         INNER JOIN users u ON u.id = wm.user_id
         WHERE wm.workspace_id = :workspace_id
         ORDER BY u.name ASC'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    return $stmt->fetchAll();
}

function usersMapById(?int $workspaceId = null): array
{
    $map = [];
    foreach (usersList($workspaceId) as $user) {
        $map[(int) $user['id']] = $user;
    }

    return $map;
}

function workspaceVaultEntriesList(?int $workspaceId = null): array
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT ve.id,
                ve.workspace_id,
                ve.label,
                ve.login_value,
                ve.password_value,
                ve.group_name,
                ve.notes,
                ve.created_by,
                ve.created_at,
                ve.updated_at,
                u.name AS created_by_name
         FROM workspace_vault_entries ve
         LEFT JOIN users u ON u.id = ve.created_by
         WHERE ve.workspace_id = :workspace_id
         ORDER BY ve.group_name ASC, ve.updated_at DESC, ve.id DESC'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['workspace_id'] = (int) ($row['workspace_id'] ?? 0);
        $row['created_by'] = isset($row['created_by']) ? (int) $row['created_by'] : null;
        $row['label'] = normalizeVaultEntryLabel((string) ($row['label'] ?? ''));
        $row['login_value'] = normalizeVaultFieldValue((string) ($row['login_value'] ?? ''), 220);
        $row['password_value'] = normalizeVaultFieldValue((string) ($row['password_value'] ?? ''), 220);
        $row['group_name'] = normalizeVaultGroupName((string) ($row['group_name'] ?? 'Geral'));
        $row['notes'] = trim((string) ($row['notes'] ?? ''));
    }
    unset($row);

    return $rows;
}

function normalizeVaultGroupName(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'Geral';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mb_strlen($value) > 60) {
        $value = mb_substr($value, 0, 60);
    }

    return uppercaseFirstCharacter($value);
}

function findVaultGroupByName(string $groupName, ?int $workspaceId = null): ?string
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return null;
    }

    $needle = mb_strtolower(normalizeVaultGroupName($groupName));
    foreach (vaultGroupsList($workspaceId) as $existingName) {
        if (mb_strtolower($existingName) === $needle) {
            return $existingName;
        }
    }

    return null;
}

function defaultVaultGroupName(?int $workspaceId = null): string
{
    $pdo = db();
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return 'Geral';
    }

    $rowStmt = $pdo->prepare(
        'SELECT name
         FROM workspace_vault_groups
         WHERE workspace_id = :workspace_id
         ORDER BY id ASC
         LIMIT 1'
    );
    $rowStmt->execute([':workspace_id' => $workspaceId]);
    $row = $rowStmt->fetch();
    $groupName = trim((string) ($row['name'] ?? ''));
    if ($groupName !== '') {
        return normalizeVaultGroupName($groupName);
    }

    $entryStmt = $pdo->prepare(
        "SELECT group_name
         FROM workspace_vault_entries
         WHERE workspace_id = :workspace_id
           AND group_name IS NOT NULL
           AND group_name <> ''
         ORDER BY id ASC
         LIMIT 1"
    );
    $entryStmt->execute([':workspace_id' => $workspaceId]);
    $entryRow = $entryStmt->fetch();
    $entryGroupName = trim((string) ($entryRow['group_name'] ?? ''));
    if ($entryGroupName !== '') {
        $normalized = normalizeVaultGroupName($entryGroupName);
        upsertVaultGroup($pdo, $normalized, null, $workspaceId);
        return $normalized;
    }

    upsertVaultGroup($pdo, 'Geral', null, $workspaceId);
    return 'Geral';
}

function isProtectedVaultGroupName(string $groupName, ?int $workspaceId = null): bool
{
    return mb_strtolower(normalizeVaultGroupName($groupName)) === mb_strtolower(defaultVaultGroupName($workspaceId));
}

function upsertVaultGroup(PDO $pdo, string $groupName, ?int $createdBy = null, ?int $workspaceId = null): string
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        throw new RuntimeException('Workspace ativo nao encontrado.');
    }

    $normalized = normalizeVaultGroupName($groupName);
    $createdAt = nowIso();

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_vault_groups (workspace_id, name, created_by, created_at)
             VALUES (:workspace_id, :name, :created_by, :created_at)
             ON CONFLICT (workspace_id, name) DO NOTHING'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO workspace_vault_groups (workspace_id, name, created_by, created_at)
             VALUES (:workspace_id, :name, :created_by, :created_at)'
        );
    }

    $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
    $stmt->bindValue(':name', $normalized, PDO::PARAM_STR);
    if ($createdBy !== null && $createdBy > 0) {
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
    $stmt->execute();

    return $normalized;
}

function vaultGroupsList(?int $workspaceId = null): array
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return ['Geral'];
    }

    $groups = [];

    $storedSql = dbDriverName(db()) === 'pgsql'
        ? 'SELECT name
           FROM workspace_vault_groups
           WHERE workspace_id = :workspace_id
           ORDER BY LOWER(name) ASC'
        : 'SELECT name
           FROM workspace_vault_groups
           WHERE workspace_id = :workspace_id
           ORDER BY name COLLATE NOCASE ASC';

    $storedStmt = db()->prepare($storedSql);
    $storedStmt->execute([':workspace_id' => $workspaceId]);
    foreach ($storedStmt->fetchAll() as $row) {
        $groupName = normalizeVaultGroupName((string) ($row['name'] ?? ''));
        $groups[$groupName] = $groupName;
    }

    $entryStmt = db()->prepare(
        'SELECT DISTINCT group_name
         FROM workspace_vault_entries
         WHERE workspace_id = :workspace_id'
    );
    $entryStmt->execute([':workspace_id' => $workspaceId]);
    foreach ($entryStmt->fetchAll() as $row) {
        $groupName = normalizeVaultGroupName((string) ($row['group_name'] ?? ''));
        $groups[$groupName] = $groupName;
    }

    if (!$groups) {
        $default = defaultVaultGroupName($workspaceId);
        return [$default];
    }

    $values = array_values($groups);
    usort($values, static fn ($a, $b) => strcasecmp($a, $b));

    return $values;
}

function vaultEntriesByGroup(array $entries, ?array $groupNames = null): array
{
    $groups = [];
    if ($groupNames !== null) {
        foreach ($groupNames as $groupName) {
            $normalized = normalizeVaultGroupName((string) $groupName);
            $groups[$normalized] = [];
        }
    }

    foreach ($entries as $entry) {
        $groupName = normalizeVaultGroupName((string) ($entry['group_name'] ?? 'Geral'));
        if (!array_key_exists($groupName, $groups)) {
            $groups[$groupName] = [];
        }
        $groups[$groupName][] = $entry;
    }

    return $groups;
}

function createWorkspaceVaultEntry(
    PDO $pdo,
    int $workspaceId,
    string $label,
    string $loginValue,
    string $passwordValue,
    string $groupName = 'Geral',
    ?int $createdBy = null
): int {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    $label = normalizeVaultEntryLabel($label);
    if ($label === '') {
        throw new RuntimeException('Informe um nome para o acesso.');
    }

    $loginValue = normalizeVaultFieldValue($loginValue, 220);
    $passwordValue = normalizeVaultFieldValue($passwordValue, 220);
    $groupName = normalizeVaultGroupName($groupName);
    upsertVaultGroup($pdo, $groupName, $createdBy, $workspaceId);

    $createdAt = nowIso();
    $updatedAt = $createdAt;

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_vault_entries (
                workspace_id, label, login_value, password_value, group_name, notes, created_by, created_at, updated_at
            ) VALUES (
                :workspace_id, :label, :login_value, :password_value, :group_name, :notes, :created_by, :created_at, :updated_at
            )
            RETURNING id'
        );
        $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
        $stmt->bindValue(':label', $label, PDO::PARAM_STR);
        $stmt->bindValue(':login_value', $loginValue, PDO::PARAM_STR);
        $stmt->bindValue(':password_value', $passwordValue, PDO::PARAM_STR);
        $stmt->bindValue(':group_name', $groupName, PDO::PARAM_STR);
        $stmt->bindValue(':notes', '', PDO::PARAM_STR);
        if ($createdBy !== null && $createdBy > 0) {
            $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
        $stmt->bindValue(':updated_at', $updatedAt, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare(
        'INSERT INTO workspace_vault_entries (
            workspace_id, label, login_value, password_value, group_name, notes, created_by, created_at, updated_at
        ) VALUES (
            :workspace_id, :label, :login_value, :password_value, :group_name, :notes, :created_by, :created_at, :updated_at
        )'
    );
    $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
    $stmt->bindValue(':label', $label, PDO::PARAM_STR);
    $stmt->bindValue(':login_value', $loginValue, PDO::PARAM_STR);
    $stmt->bindValue(':password_value', $passwordValue, PDO::PARAM_STR);
    $stmt->bindValue(':group_name', $groupName, PDO::PARAM_STR);
    $stmt->bindValue(':notes', '', PDO::PARAM_STR);
    if ($createdBy !== null && $createdBy > 0) {
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
    $stmt->bindValue(':updated_at', $updatedAt, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $pdo->lastInsertId();
}

function updateWorkspaceVaultEntry(
    PDO $pdo,
    int $workspaceId,
    int $entryId,
    string $label,
    string $loginValue,
    string $passwordValue,
    string $groupName = 'Geral'
): void {
    if ($workspaceId <= 0 || $entryId <= 0) {
        throw new RuntimeException('Registro invalido.');
    }

    $label = normalizeVaultEntryLabel($label);
    if ($label === '') {
        throw new RuntimeException('Informe um nome para o acesso.');
    }

    $loginValue = normalizeVaultFieldValue($loginValue, 220);
    $passwordValue = normalizeVaultFieldValue($passwordValue, 220);
    $groupName = normalizeVaultGroupName($groupName);
    upsertVaultGroup($pdo, $groupName, null, $workspaceId);

    $stmt = $pdo->prepare(
        'UPDATE workspace_vault_entries
         SET label = :label,
             login_value = :login_value,
             password_value = :password_value,
             group_name = :group_name,
             notes = :notes,
             updated_at = :updated_at
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':label' => $label,
        ':login_value' => $loginValue,
        ':password_value' => $passwordValue,
        ':group_name' => $groupName,
        ':notes' => '',
        ':updated_at' => nowIso(),
        ':id' => $entryId,
        ':workspace_id' => $workspaceId,
    ]);

    if ($stmt->rowCount() <= 0) {
        $existsStmt = $pdo->prepare(
            'SELECT 1
             FROM workspace_vault_entries
             WHERE id = :id
               AND workspace_id = :workspace_id
             LIMIT 1'
        );
        $existsStmt->execute([
            ':id' => $entryId,
            ':workspace_id' => $workspaceId,
        ]);
        if (!$existsStmt->fetchColumn()) {
            throw new RuntimeException('Registro nao encontrado.');
        }
    }
}

function updateWorkspaceVaultEntryLabel(
    PDO $pdo,
    int $workspaceId,
    int $entryId,
    string $label
): void {
    if ($workspaceId <= 0 || $entryId <= 0) {
        throw new RuntimeException('Registro invalido.');
    }

    $label = normalizeVaultEntryLabel($label);
    if ($label === '') {
        throw new RuntimeException('Informe um nome para o acesso.');
    }

    $stmt = $pdo->prepare(
        'UPDATE workspace_vault_entries
         SET label = :label,
             updated_at = :updated_at
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':label' => $label,
        ':updated_at' => nowIso(),
        ':id' => $entryId,
        ':workspace_id' => $workspaceId,
    ]);

    if ($stmt->rowCount() <= 0) {
        $existsStmt = $pdo->prepare(
            'SELECT 1
             FROM workspace_vault_entries
             WHERE id = :id
               AND workspace_id = :workspace_id
             LIMIT 1'
        );
        $existsStmt->execute([
            ':id' => $entryId,
            ':workspace_id' => $workspaceId,
        ]);
        if (!$existsStmt->fetchColumn()) {
            throw new RuntimeException('Registro nao encontrado.');
        }
    }
}

function deleteWorkspaceVaultEntry(PDO $pdo, int $workspaceId, int $entryId): void
{
    if ($workspaceId <= 0 || $entryId <= 0) {
        throw new RuntimeException('Registro invalido.');
    }

    $stmt = $pdo->prepare(
        'DELETE FROM workspace_vault_entries
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':id' => $entryId,
        ':workspace_id' => $workspaceId,
    ]);

    if ($stmt->rowCount() <= 0) {
        throw new RuntimeException('Registro nao encontrado.');
    }
}

function workspaceDueEntriesList(?int $workspaceId = null): array
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT de.id,
                de.workspace_id,
                de.label,
                de.recurrence_type,
                de.monthly_day,
                de.due_date,
                de.amount_cents,
                de.group_name,
                de.notes,
                de.created_by,
                de.created_at,
                de.updated_at,
                u.name AS created_by_name
         FROM workspace_due_entries de
         LEFT JOIN users u ON u.id = de.created_by
         WHERE de.workspace_id = :workspace_id
         ORDER BY de.group_name ASC, de.updated_at DESC, de.id DESC'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['workspace_id'] = (int) ($row['workspace_id'] ?? 0);
        $row['created_by'] = isset($row['created_by']) ? (int) $row['created_by'] : null;
        $row['label'] = normalizeDueEntryLabel((string) ($row['label'] ?? ''));
        $row['due_date'] = dueDateForStorage((string) ($row['due_date'] ?? ''));
        $row['amount_cents'] = normalizeDueAmountCents($row['amount_cents'] ?? null) ?? 0;
        $row['amount_display'] = dueAmountLabelFromCents($row['amount_cents']);
        $row['group_name'] = normalizeDueGroupName((string) ($row['group_name'] ?? 'Geral'));
        $row['notes'] = normalizeDueEntryNotes((string) ($row['notes'] ?? ''));
        $row['recurrence_type'] = normalizeDueRecurrenceType((string) ($row['recurrence_type'] ?? 'monthly'));
        $row['monthly_day'] = normalizeDueMonthlyDay($row['monthly_day'] ?? null);
        if ($row['recurrence_type'] === 'monthly') {
            if ($row['monthly_day'] === null && $row['due_date'] !== null) {
                $row['monthly_day'] = dueMonthlyDayFromDate($row['due_date']);
            }
            if ($row['monthly_day'] === null) {
                $row['monthly_day'] = (int) (new DateTimeImmutable('today'))->format('j');
            }
        } else {
            $row['monthly_day'] = null;
        }
        $row['next_due_date'] = dueNextDueDate(
            (string) $row['recurrence_type'],
            $row['monthly_day'],
            $row['due_date']
        );
    }
    unset($row);

    usort(
        $rows,
        static function (array $a, array $b): int {
            $groupCompare = strcasecmp(
                (string) ($a['group_name'] ?? ''),
                (string) ($b['group_name'] ?? '')
            );
            if ($groupCompare !== 0) {
                return $groupCompare;
            }

            $nextDueA = dueDateForStorage((string) ($a['next_due_date'] ?? ''));
            $nextDueB = dueDateForStorage((string) ($b['next_due_date'] ?? ''));
            $dateA = $nextDueA ?? '9999-12-31';
            $dateB = $nextDueB ?? '9999-12-31';
            if ($dateA !== $dateB) {
                return strcmp($dateA, $dateB);
            }

            $updatedA = (string) ($a['updated_at'] ?? '');
            $updatedB = (string) ($b['updated_at'] ?? '');
            if ($updatedA !== $updatedB) {
                return strcmp($updatedB, $updatedA);
            }

            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        }
    );

    return $rows;
}

function normalizeDueGroupName(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'Geral';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mb_strlen($value) > 60) {
        $value = mb_substr($value, 0, 60);
    }

    return uppercaseFirstCharacter($value);
}

function normalizeDueEntryLabel(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (mb_strlen($value) > 120) {
        $value = mb_substr($value, 0, 120);
    }

    return uppercaseFirstCharacter($value);
}

function normalizeDueEntryNotes(string $value): string
{
    $value = trim($value);
    if (mb_strlen($value) > 1000) {
        $value = mb_substr($value, 0, 1000);
    }

    return $value;
}

function normalizeDueAmountCents($value): ?int
{
    if ($value === null) {
        return null;
    }

    if (is_int($value)) {
        return max(0, $value);
    }

    if (is_float($value)) {
        return max(0, (int) round($value * 100));
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $raw = str_replace(['R$', 'r$', ' '], '', $raw);
    if (strpos($raw, ',') !== false) {
        if (strpos($raw, '.') !== false) {
            $raw = str_replace('.', '', $raw);
        }
        $raw = str_replace(',', '.', $raw);
    }

    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $raw)) {
        return null;
    }

    $parts = explode('.', $raw, 2);
    $integerPart = preg_replace('/\D/', '', (string) ($parts[0] ?? '0'));
    $decimalPart = preg_replace('/\D/', '', (string) ($parts[1] ?? ''));
    $decimalPart = str_pad(substr($decimalPart, 0, 2), 2, '0');

    $amount = ((int) $integerPart * 100) + (int) $decimalPart;
    return max(0, $amount);
}

function dueAmountLabelFromCents($amountCents): string
{
    $normalized = normalizeDueAmountCents($amountCents) ?? 0;
    return 'R$ ' . number_format($normalized / 100, 2, ',', '.');
}

function dueAmountLabelFromSignedCents($amountCents): string
{
    $normalized = 0;
    if (is_int($amountCents)) {
        $normalized = $amountCents;
    } elseif (is_float($amountCents)) {
        $normalized = (int) round($amountCents);
    } elseif (is_string($amountCents) && is_numeric(trim($amountCents))) {
        $normalized = (int) round((float) trim($amountCents));
    } elseif (is_numeric($amountCents)) {
        $normalized = (int) round((float) $amountCents);
    }

    $isNegative = $normalized < 0;
    $absoluteValue = abs($normalized);
    return ($isNegative ? '-R$ ' : 'R$ ') . number_format($absoluteValue / 100, 2, ',', '.');
}

function normalizeDueRecurrenceType(string $value): string
{
    $normalized = mb_strtolower(trim($value));
    if ($normalized === 'fixed') {
        return 'fixed';
    }
    if ($normalized === 'annual') {
        return 'annual';
    }

    return 'monthly';
}

function normalizeDueMonthlyDay($value): ?int
{
    if ($value === null) {
        return null;
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $day = (int) $raw;
    if ($day < 1 || $day > 31) {
        return null;
    }

    return $day;
}

function dueMonthlyDayFromDate(?string $dueDate): ?int
{
    $normalizedDate = dueDateForStorage($dueDate);
    if ($normalizedDate === null) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $normalizedDate);
    if (!$date) {
        return null;
    }

    return (int) $date->format('j');
}

function dueNextMonthlyDate(?int $monthlyDay, ?DateTimeImmutable $fromDate = null): ?string
{
    $monthlyDay = normalizeDueMonthlyDay($monthlyDay);
    if ($monthlyDay === null) {
        return null;
    }

    $baseDate = $fromDate instanceof DateTimeImmutable ? $fromDate : new DateTimeImmutable('today');
    $year = (int) $baseDate->format('Y');
    $month = (int) $baseDate->format('n');
    $daysInMonth = (int) $baseDate->format('t');
    $targetDay = min($monthlyDay, $daysInMonth);
    $candidate = $baseDate->setDate($year, $month, $targetDay);

    if ($candidate->format('Y-m-d') < $baseDate->format('Y-m-d')) {
        $nextMonthBase = $baseDate->modify('first day of next month');
        $nextYear = (int) $nextMonthBase->format('Y');
        $nextMonth = (int) $nextMonthBase->format('n');
        $nextDaysInMonth = (int) $nextMonthBase->format('t');
        $nextTargetDay = min($monthlyDay, $nextDaysInMonth);
        $candidate = $nextMonthBase->setDate($nextYear, $nextMonth, $nextTargetDay);
    }

    return $candidate->format('Y-m-d');
}

function dueNextAnnualDate(?string $dueDate, ?DateTimeImmutable $fromDate = null): ?string
{
    $normalizedDate = dueDateForStorage($dueDate);
    if ($normalizedDate === null) {
        return null;
    }

    $referenceDate = DateTimeImmutable::createFromFormat('Y-m-d', $normalizedDate);
    if (!$referenceDate) {
        return null;
    }

    $baseDate = $fromDate instanceof DateTimeImmutable ? $fromDate : new DateTimeImmutable('today');
    $referenceMonth = (int) $referenceDate->format('n');
    $referenceDay = (int) $referenceDate->format('j');
    $baseYear = (int) $baseDate->format('Y');

    $currentYearAnchor = $baseDate->setDate($baseYear, $referenceMonth, 1);
    $currentYearTargetDay = min($referenceDay, (int) $currentYearAnchor->format('t'));
    $candidate = $currentYearAnchor->setDate($baseYear, $referenceMonth, $currentYearTargetDay);

    if ($candidate->format('Y-m-d') < $baseDate->format('Y-m-d')) {
        $nextYear = $baseYear + 1;
        $nextYearAnchor = $currentYearAnchor->setDate($nextYear, $referenceMonth, 1);
        $nextYearTargetDay = min($referenceDay, (int) $nextYearAnchor->format('t'));
        $candidate = $nextYearAnchor->setDate($nextYear, $referenceMonth, $nextYearTargetDay);
    }

    return $candidate->format('Y-m-d');
}

function dueNextDueDate(string $recurrenceType, ?int $monthlyDay, ?string $dueDate): ?string
{
    $recurrenceType = normalizeDueRecurrenceType($recurrenceType);
    $dueDate = dueDateForStorage($dueDate);
    $monthlyDay = normalizeDueMonthlyDay($monthlyDay);

    if ($recurrenceType === 'fixed') {
        return $dueDate;
    }
    if ($recurrenceType === 'annual') {
        return dueNextAnnualDate($dueDate);
    }

    if ($monthlyDay === null && $dueDate !== null) {
        $monthlyDay = dueMonthlyDayFromDate($dueDate);
    }

    return dueNextMonthlyDate($monthlyDay);
}

function findDueGroupByName(string $groupName, ?int $workspaceId = null): ?string
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return null;
    }

    $needle = mb_strtolower(normalizeDueGroupName($groupName));
    foreach (dueGroupsList($workspaceId) as $existingName) {
        if (mb_strtolower($existingName) === $needle) {
            return $existingName;
        }
    }

    return null;
}

function defaultDueGroupName(?int $workspaceId = null): string
{
    $pdo = db();
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return 'Geral';
    }

    $rowStmt = $pdo->prepare(
        'SELECT name
         FROM workspace_due_groups
         WHERE workspace_id = :workspace_id
         ORDER BY id ASC
         LIMIT 1'
    );
    $rowStmt->execute([':workspace_id' => $workspaceId]);
    $row = $rowStmt->fetch();
    $groupName = trim((string) ($row['name'] ?? ''));
    if ($groupName !== '') {
        return normalizeDueGroupName($groupName);
    }

    $entryStmt = $pdo->prepare(
        "SELECT group_name
         FROM workspace_due_entries
         WHERE workspace_id = :workspace_id
           AND group_name IS NOT NULL
           AND group_name <> ''
         ORDER BY id ASC
         LIMIT 1"
    );
    $entryStmt->execute([':workspace_id' => $workspaceId]);
    $entryRow = $entryStmt->fetch();
    $entryGroupName = trim((string) ($entryRow['group_name'] ?? ''));
    if ($entryGroupName !== '') {
        $normalized = normalizeDueGroupName($entryGroupName);
        upsertDueGroup($pdo, $normalized, null, $workspaceId);
        return $normalized;
    }

    upsertDueGroup($pdo, 'Geral', null, $workspaceId);
    return 'Geral';
}

function upsertDueGroup(PDO $pdo, string $groupName, ?int $createdBy = null, ?int $workspaceId = null): string
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        throw new RuntimeException('Workspace ativo nao encontrado.');
    }

    $normalized = normalizeDueGroupName($groupName);
    $createdAt = nowIso();

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_due_groups (workspace_id, name, created_by, created_at)
             VALUES (:workspace_id, :name, :created_by, :created_at)
             ON CONFLICT (workspace_id, name) DO NOTHING'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO workspace_due_groups (workspace_id, name, created_by, created_at)
             VALUES (:workspace_id, :name, :created_by, :created_at)'
        );
    }

    $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
    $stmt->bindValue(':name', $normalized, PDO::PARAM_STR);
    if ($createdBy !== null && $createdBy > 0) {
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
    $stmt->execute();

    return $normalized;
}

function dueGroupsList(?int $workspaceId = null): array
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return ['Geral'];
    }

    $groups = [];

    $storedSql = dbDriverName(db()) === 'pgsql'
        ? 'SELECT name
           FROM workspace_due_groups
           WHERE workspace_id = :workspace_id
           ORDER BY LOWER(name) ASC'
        : 'SELECT name
           FROM workspace_due_groups
           WHERE workspace_id = :workspace_id
           ORDER BY name COLLATE NOCASE ASC';

    $storedStmt = db()->prepare($storedSql);
    $storedStmt->execute([':workspace_id' => $workspaceId]);
    foreach ($storedStmt->fetchAll() as $row) {
        $groupName = normalizeDueGroupName((string) ($row['name'] ?? ''));
        $groups[$groupName] = $groupName;
    }

    $entryStmt = db()->prepare(
        'SELECT DISTINCT group_name
         FROM workspace_due_entries
         WHERE workspace_id = :workspace_id'
    );
    $entryStmt->execute([':workspace_id' => $workspaceId]);
    foreach ($entryStmt->fetchAll() as $row) {
        $groupName = normalizeDueGroupName((string) ($row['group_name'] ?? ''));
        $groups[$groupName] = $groupName;
    }

    if (!$groups) {
        $default = defaultDueGroupName($workspaceId);
        return [$default];
    }

    $values = array_values($groups);
    usort($values, static fn ($a, $b) => strcasecmp($a, $b));

    return $values;
}

function dueEntriesByGroup(array $entries, ?array $groupNames = null): array
{
    $groups = [];
    if ($groupNames !== null) {
        foreach ($groupNames as $groupName) {
            $normalized = normalizeDueGroupName((string) $groupName);
            $groups[$normalized] = [];
        }
    }

    foreach ($entries as $entry) {
        $groupName = normalizeDueGroupName((string) ($entry['group_name'] ?? 'Geral'));
        if (!array_key_exists($groupName, $groups)) {
            $groups[$groupName] = [];
        }
        $groups[$groupName][] = $entry;
    }

    return $groups;
}

function createWorkspaceDueEntry(
    PDO $pdo,
    int $workspaceId,
    string $label,
    ?string $dueDate,
    string $groupName = 'Geral',
    string $notes = '',
    $amountInput = null,
    ?int $createdBy = null,
    string $recurrenceType = 'monthly',
    $monthlyDay = null
): int {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    $label = normalizeDueEntryLabel($label);
    if ($label === '') {
        throw new RuntimeException('Informe um nome para o vencimento.');
    }

    $recurrenceType = normalizeDueRecurrenceType($recurrenceType);
    $dueDate = dueDateForStorage($dueDate);
    $monthlyDay = normalizeDueMonthlyDay($monthlyDay);

    if ($recurrenceType === 'monthly') {
        if ($monthlyDay === null && $dueDate !== null) {
            $monthlyDay = dueMonthlyDayFromDate($dueDate);
        }
        if ($monthlyDay === null) {
            throw new RuntimeException('Informe um dia valido para o vencimento mensal.');
        }
        $dueDate = dueNextMonthlyDate($monthlyDay);
    } elseif ($recurrenceType === 'annual') {
        if ($dueDate === null) {
            throw new RuntimeException('Informe uma data valida para o vencimento anual.');
        }
        $dueDate = dueNextAnnualDate($dueDate);
        if ($dueDate === null) {
            throw new RuntimeException('Informe uma data valida para o vencimento anual.');
        }
        $monthlyDay = null;
    } else {
        if ($dueDate === null) {
            throw new RuntimeException('Informe uma data de vencimento valida.');
        }
        $monthlyDay = null;
    }

    $groupName = normalizeDueGroupName($groupName);
    $notes = normalizeDueEntryNotes($notes);
    $amountCents = normalizeDueAmountCents($amountInput) ?? 0;
    upsertDueGroup($pdo, $groupName, $createdBy, $workspaceId);

    $createdAt = nowIso();
    $updatedAt = $createdAt;

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_due_entries (
                workspace_id, label, recurrence_type, monthly_day, due_date, amount_cents, group_name, notes, created_by, created_at, updated_at
            ) VALUES (
                :workspace_id, :label, :recurrence_type, :monthly_day, :due_date, :amount_cents, :group_name, :notes, :created_by, :created_at, :updated_at
            )
            RETURNING id'
        );
        $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
        $stmt->bindValue(':label', $label, PDO::PARAM_STR);
        $stmt->bindValue(':recurrence_type', $recurrenceType, PDO::PARAM_STR);
        if ($monthlyDay !== null) {
            $stmt->bindValue(':monthly_day', $monthlyDay, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':monthly_day', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':due_date', $dueDate, PDO::PARAM_STR);
        $stmt->bindValue(':amount_cents', $amountCents, PDO::PARAM_INT);
        $stmt->bindValue(':group_name', $groupName, PDO::PARAM_STR);
        $stmt->bindValue(':notes', $notes, PDO::PARAM_STR);
        if ($createdBy !== null && $createdBy > 0) {
            $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
        $stmt->bindValue(':updated_at', $updatedAt, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare(
        'INSERT INTO workspace_due_entries (
            workspace_id, label, recurrence_type, monthly_day, due_date, amount_cents, group_name, notes, created_by, created_at, updated_at
        ) VALUES (
            :workspace_id, :label, :recurrence_type, :monthly_day, :due_date, :amount_cents, :group_name, :notes, :created_by, :created_at, :updated_at
        )'
    );
    $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
    $stmt->bindValue(':label', $label, PDO::PARAM_STR);
    $stmt->bindValue(':recurrence_type', $recurrenceType, PDO::PARAM_STR);
    if ($monthlyDay !== null) {
        $stmt->bindValue(':monthly_day', $monthlyDay, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':monthly_day', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':due_date', $dueDate, PDO::PARAM_STR);
    $stmt->bindValue(':amount_cents', $amountCents, PDO::PARAM_INT);
    $stmt->bindValue(':group_name', $groupName, PDO::PARAM_STR);
    $stmt->bindValue(':notes', $notes, PDO::PARAM_STR);
    if ($createdBy !== null && $createdBy > 0) {
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
    $stmt->bindValue(':updated_at', $updatedAt, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $pdo->lastInsertId();
}

function updateWorkspaceDueEntry(
    PDO $pdo,
    int $workspaceId,
    int $entryId,
    string $label,
    ?string $dueDate,
    string $groupName = 'Geral',
    string $notes = '',
    $amountInput = null,
    string $recurrenceType = 'monthly',
    $monthlyDay = null
): void {
    if ($workspaceId <= 0 || $entryId <= 0) {
        throw new RuntimeException('Registro invalido.');
    }

    $label = normalizeDueEntryLabel($label);
    if ($label === '') {
        throw new RuntimeException('Informe um nome para o vencimento.');
    }

    $recurrenceType = normalizeDueRecurrenceType($recurrenceType);
    $dueDate = dueDateForStorage($dueDate);
    $monthlyDay = normalizeDueMonthlyDay($monthlyDay);

    if ($recurrenceType === 'monthly') {
        if ($monthlyDay === null && $dueDate !== null) {
            $monthlyDay = dueMonthlyDayFromDate($dueDate);
        }
        if ($monthlyDay === null) {
            throw new RuntimeException('Informe um dia valido para o vencimento mensal.');
        }
        $dueDate = dueNextMonthlyDate($monthlyDay);
    } elseif ($recurrenceType === 'annual') {
        if ($dueDate === null) {
            throw new RuntimeException('Informe uma data valida para o vencimento anual.');
        }
        $dueDate = dueNextAnnualDate($dueDate);
        if ($dueDate === null) {
            throw new RuntimeException('Informe uma data valida para o vencimento anual.');
        }
        $monthlyDay = null;
    } else {
        if ($dueDate === null) {
            throw new RuntimeException('Informe uma data de vencimento valida.');
        }
        $monthlyDay = null;
    }

    $groupName = normalizeDueGroupName($groupName);
    $notes = normalizeDueEntryNotes($notes);
    $amountCents = normalizeDueAmountCents($amountInput) ?? 0;
    upsertDueGroup($pdo, $groupName, null, $workspaceId);

    $stmt = $pdo->prepare(
        'UPDATE workspace_due_entries
         SET label = :label,
             recurrence_type = :recurrence_type,
             monthly_day = :monthly_day,
             due_date = :due_date,
             amount_cents = :amount_cents,
             group_name = :group_name,
             notes = :notes,
             updated_at = :updated_at
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':label' => $label,
        ':recurrence_type' => $recurrenceType,
        ':monthly_day' => $monthlyDay,
        ':due_date' => $dueDate,
        ':amount_cents' => $amountCents,
        ':group_name' => $groupName,
        ':notes' => $notes,
        ':updated_at' => nowIso(),
        ':id' => $entryId,
        ':workspace_id' => $workspaceId,
    ]);

    if ($stmt->rowCount() <= 0) {
        $existsStmt = $pdo->prepare(
            'SELECT 1
             FROM workspace_due_entries
             WHERE id = :id
               AND workspace_id = :workspace_id
             LIMIT 1'
        );
        $existsStmt->execute([
            ':id' => $entryId,
            ':workspace_id' => $workspaceId,
        ]);
        if (!$existsStmt->fetchColumn()) {
            throw new RuntimeException('Registro nao encontrado.');
        }
    }
}

function deleteWorkspaceDueEntry(PDO $pdo, int $workspaceId, int $entryId): void
{
    if ($workspaceId <= 0 || $entryId <= 0) {
        throw new RuntimeException('Registro invalido.');
    }

    $stmt = $pdo->prepare(
        'DELETE FROM workspace_due_entries
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':id' => $entryId,
        ':workspace_id' => $workspaceId,
    ]);

    if ($stmt->rowCount() <= 0) {
        throw new RuntimeException('Registro nao encontrado.');
    }
}

function normalizeInventoryGroupName(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'Geral';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mb_strlen($value) > 60) {
        $value = mb_substr($value, 0, 60);
    }

    return uppercaseFirstCharacter($value);
}

function normalizeInventoryEntryLabel(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (mb_strlen($value) > 120) {
        $value = mb_substr($value, 0, 120);
    }

    return uppercaseFirstCharacter($value);
}

function normalizeInventoryUnitLabel(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'un';
    }

    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    if (mb_strlen($value) > 30) {
        $value = mb_substr($value, 0, 30);
    }

    return mb_strtolower($value);
}

function normalizeInventoryEntryNotes(string $value): string
{
    $value = trim($value);
    if (mb_strlen($value) > 1000) {
        $value = mb_substr($value, 0, 1000);
    }

    return $value;
}

function normalizeInventoryQuantityValue($value): ?int
{
    if ($value === null) {
        return null;
    }

    if (is_int($value)) {
        return $value >= 0 ? $value : null;
    }

    if (is_float($value)) {
        $numeric = $value;
        if ($numeric < 0) {
            return null;
        }

        return (int) round($numeric);
    }

    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $raw = str_replace(['R$', 'r$', ' '], '', $raw);
    if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d+)?$/', $raw) === 1) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    } elseif (preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/', $raw) === 1) {
        $raw = str_replace(',', '', $raw);
    } elseif (strpos($raw, ',') !== false && strpos($raw, '.') === false) {
        $raw = str_replace(',', '.', $raw);
    }

    if (!is_numeric($raw)) {
        return null;
    }

    $numeric = (float) $raw;
    if ($numeric < 0) {
        return null;
    }

    return (int) round($numeric);
}

function inventoryQuantityStorageValue($value): string
{
    $normalized = normalizeInventoryQuantityValue($value) ?? 0;
    return (string) $normalized;
}

function inventoryQuantityInputValue($value): string
{
    $normalized = normalizeInventoryQuantityValue($value);
    if ($normalized === null) {
        return '';
    }

    return (string) $normalized;
}

function inventoryQuantityLabel($value): string
{
    $normalized = normalizeInventoryQuantityValue($value) ?? 0;
    return number_format((float) $normalized, 0, ',', '.');
}

function findInventoryGroupByName(string $groupName, ?int $workspaceId = null): ?string
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return null;
    }

    $needle = mb_strtolower(normalizeInventoryGroupName($groupName));
    foreach (inventoryGroupsList($workspaceId) as $existingName) {
        if (mb_strtolower($existingName) === $needle) {
            return $existingName;
        }
    }

    return null;
}

function defaultInventoryGroupName(?int $workspaceId = null): string
{
    $pdo = db();
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return 'Geral';
    }

    $rowStmt = $pdo->prepare(
        'SELECT name
         FROM workspace_inventory_groups
         WHERE workspace_id = :workspace_id
         ORDER BY id ASC
         LIMIT 1'
    );
    $rowStmt->execute([':workspace_id' => $workspaceId]);
    $row = $rowStmt->fetch();
    $groupName = trim((string) ($row['name'] ?? ''));
    if ($groupName !== '') {
        return normalizeInventoryGroupName($groupName);
    }

    $entryStmt = $pdo->prepare(
        "SELECT group_name
         FROM workspace_inventory_entries
         WHERE workspace_id = :workspace_id
           AND group_name IS NOT NULL
           AND group_name <> ''
         ORDER BY id ASC
         LIMIT 1"
    );
    $entryStmt->execute([':workspace_id' => $workspaceId]);
    $entryRow = $entryStmt->fetch();
    $entryGroupName = trim((string) ($entryRow['group_name'] ?? ''));
    if ($entryGroupName !== '') {
        $normalized = normalizeInventoryGroupName($entryGroupName);
        upsertInventoryGroup($pdo, $normalized, null, $workspaceId);
        return $normalized;
    }

    upsertInventoryGroup($pdo, 'Geral', null, $workspaceId);
    return 'Geral';
}

function upsertInventoryGroup(PDO $pdo, string $groupName, ?int $createdBy = null, ?int $workspaceId = null): string
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        throw new RuntimeException('Workspace ativo nao encontrado.');
    }

    $normalized = normalizeInventoryGroupName($groupName);
    $createdAt = nowIso();

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_inventory_groups (workspace_id, name, created_by, created_at)
             VALUES (:workspace_id, :name, :created_by, :created_at)
             ON CONFLICT (workspace_id, name) DO NOTHING'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO workspace_inventory_groups (workspace_id, name, created_by, created_at)
             VALUES (:workspace_id, :name, :created_by, :created_at)'
        );
    }

    $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
    $stmt->bindValue(':name', $normalized, PDO::PARAM_STR);
    if ($createdBy !== null && $createdBy > 0) {
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
    $stmt->execute();

    return $normalized;
}

function inventoryGroupsList(?int $workspaceId = null): array
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return ['Geral'];
    }

    $groups = [];

    $storedSql = dbDriverName(db()) === 'pgsql'
        ? 'SELECT name
           FROM workspace_inventory_groups
           WHERE workspace_id = :workspace_id
           ORDER BY LOWER(name) ASC'
        : 'SELECT name
           FROM workspace_inventory_groups
           WHERE workspace_id = :workspace_id
           ORDER BY name COLLATE NOCASE ASC';

    $storedStmt = db()->prepare($storedSql);
    $storedStmt->execute([':workspace_id' => $workspaceId]);
    foreach ($storedStmt->fetchAll() as $row) {
        $groupName = normalizeInventoryGroupName((string) ($row['name'] ?? ''));
        $groups[$groupName] = $groupName;
    }

    $entryStmt = db()->prepare(
        'SELECT DISTINCT group_name
         FROM workspace_inventory_entries
         WHERE workspace_id = :workspace_id'
    );
    $entryStmt->execute([':workspace_id' => $workspaceId]);
    foreach ($entryStmt->fetchAll() as $row) {
        $groupName = normalizeInventoryGroupName((string) ($row['group_name'] ?? ''));
        $groups[$groupName] = $groupName;
    }

    if (!$groups) {
        $default = defaultInventoryGroupName($workspaceId);
        return [$default];
    }

    $values = array_values($groups);
    usort($values, static fn ($a, $b) => strcasecmp($a, $b));

    return $values;
}

function workspaceInventoryEntriesList(?int $workspaceId = null): array
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT ie.id,
                ie.workspace_id,
                ie.label,
                ie.quantity_value,
                ie.min_quantity_value,
                ie.unit_label,
                ie.group_name,
                ie.notes,
                ie.created_by,
                ie.created_at,
                ie.updated_at,
                u.name AS created_by_name
         FROM workspace_inventory_entries ie
         LEFT JOIN users u ON u.id = ie.created_by
         WHERE ie.workspace_id = :workspace_id
         ORDER BY ie.group_name ASC, ie.updated_at DESC, ie.id DESC'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $quantityValue = normalizeInventoryQuantityValue($row['quantity_value'] ?? null) ?? 0;
        $minQuantityValue = normalizeInventoryQuantityValue($row['min_quantity_value'] ?? null);
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['workspace_id'] = (int) ($row['workspace_id'] ?? 0);
        $row['created_by'] = isset($row['created_by']) ? (int) $row['created_by'] : null;
        $row['label'] = normalizeInventoryEntryLabel((string) ($row['label'] ?? ''));
        $row['quantity_value'] = $quantityValue;
        $row['quantity_value_input'] = inventoryQuantityInputValue($quantityValue);
        $row['quantity_display'] = inventoryQuantityLabel($quantityValue);
        $row['min_quantity_value'] = $minQuantityValue;
        $row['min_quantity_value_input'] = inventoryQuantityInputValue($minQuantityValue);
        $row['min_quantity_display'] = $minQuantityValue !== null ? inventoryQuantityLabel($minQuantityValue) : '';
        $row['unit_label'] = normalizeInventoryUnitLabel((string) ($row['unit_label'] ?? 'un'));
        $row['group_name'] = normalizeInventoryGroupName((string) ($row['group_name'] ?? 'Geral'));
        $row['notes'] = normalizeInventoryEntryNotes((string) ($row['notes'] ?? ''));
        $row['is_low_stock'] = $minQuantityValue !== null && $quantityValue <= $minQuantityValue ? 1 : 0;
    }
    unset($row);

    usort(
        $rows,
        static function (array $a, array $b): int {
            $groupCompare = strcasecmp(
                (string) ($a['group_name'] ?? ''),
                (string) ($b['group_name'] ?? '')
            );
            if ($groupCompare !== 0) {
                return $groupCompare;
            }

            $lowA = ((int) ($a['is_low_stock'] ?? 0)) === 1 ? 0 : 1;
            $lowB = ((int) ($b['is_low_stock'] ?? 0)) === 1 ? 0 : 1;
            if ($lowA !== $lowB) {
                return $lowA <=> $lowB;
            }

            $labelCompare = strcasecmp(
                (string) ($a['label'] ?? ''),
                (string) ($b['label'] ?? '')
            );
            if ($labelCompare !== 0) {
                return $labelCompare;
            }

            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        }
    );

    return $rows;
}

function inventoryEntriesByGroup(array $entries, ?array $groupNames = null): array
{
    $groups = [];
    if ($groupNames !== null) {
        foreach ($groupNames as $groupName) {
            $normalized = normalizeInventoryGroupName((string) $groupName);
            $groups[$normalized] = [];
        }
    }

    foreach ($entries as $entry) {
        $groupName = normalizeInventoryGroupName((string) ($entry['group_name'] ?? 'Geral'));
        if (!array_key_exists($groupName, $groups)) {
            $groups[$groupName] = [];
        }
        $groups[$groupName][] = $entry;
    }

    return $groups;
}

function createWorkspaceInventoryEntry(
    PDO $pdo,
    int $workspaceId,
    string $label,
    $quantityValue,
    string $unitLabel,
    string $groupName = 'Geral',
    $minQuantityValue = null,
    string $notes = '',
    ?int $createdBy = null
): int {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    $label = normalizeInventoryEntryLabel($label);
    if ($label === '') {
        throw new RuntimeException('Informe um nome para o item.');
    }

    $quantity = normalizeInventoryQuantityValue($quantityValue);
    if ($quantity === null) {
        throw new RuntimeException('Informe uma quantidade valida.');
    }

    $minQuantity = normalizeInventoryQuantityValue($minQuantityValue);
    $unit = normalizeInventoryUnitLabel($unitLabel);
    $groupName = normalizeInventoryGroupName($groupName);
    $notes = normalizeInventoryEntryNotes($notes);
    upsertInventoryGroup($pdo, $groupName, $createdBy, $workspaceId);

    $createdAt = nowIso();
    $updatedAt = $createdAt;

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_inventory_entries (
                workspace_id, label, quantity_value, min_quantity_value, unit_label, group_name, notes, created_by, created_at, updated_at
            ) VALUES (
                :workspace_id, :label, :quantity_value, :min_quantity_value, :unit_label, :group_name, :notes, :created_by, :created_at, :updated_at
            )
            RETURNING id'
        );
        $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
        $stmt->bindValue(':label', $label, PDO::PARAM_STR);
        $stmt->bindValue(':quantity_value', inventoryQuantityStorageValue($quantity), PDO::PARAM_STR);
        if ($minQuantity !== null) {
            $stmt->bindValue(':min_quantity_value', inventoryQuantityStorageValue($minQuantity), PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':min_quantity_value', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':unit_label', $unit, PDO::PARAM_STR);
        $stmt->bindValue(':group_name', $groupName, PDO::PARAM_STR);
        $stmt->bindValue(':notes', $notes, PDO::PARAM_STR);
        if ($createdBy !== null && $createdBy > 0) {
            $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
        $stmt->bindValue(':updated_at', $updatedAt, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare(
        'INSERT INTO workspace_inventory_entries (
            workspace_id, label, quantity_value, min_quantity_value, unit_label, group_name, notes, created_by, created_at, updated_at
        ) VALUES (
            :workspace_id, :label, :quantity_value, :min_quantity_value, :unit_label, :group_name, :notes, :created_by, :created_at, :updated_at
        )'
    );
    $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
    $stmt->bindValue(':label', $label, PDO::PARAM_STR);
    $stmt->bindValue(':quantity_value', inventoryQuantityStorageValue($quantity), PDO::PARAM_STR);
    if ($minQuantity !== null) {
        $stmt->bindValue(':min_quantity_value', inventoryQuantityStorageValue($minQuantity), PDO::PARAM_STR);
    } else {
        $stmt->bindValue(':min_quantity_value', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':unit_label', $unit, PDO::PARAM_STR);
    $stmt->bindValue(':group_name', $groupName, PDO::PARAM_STR);
    $stmt->bindValue(':notes', $notes, PDO::PARAM_STR);
    if ($createdBy !== null && $createdBy > 0) {
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
    $stmt->bindValue(':updated_at', $updatedAt, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $pdo->lastInsertId();
}

function updateWorkspaceInventoryEntry(
    PDO $pdo,
    int $workspaceId,
    int $entryId,
    string $label,
    $quantityValue,
    string $unitLabel,
    string $groupName = 'Geral',
    $minQuantityValue = null,
    string $notes = ''
): void {
    if ($workspaceId <= 0 || $entryId <= 0) {
        throw new RuntimeException('Registro invalido.');
    }

    $label = normalizeInventoryEntryLabel($label);
    if ($label === '') {
        throw new RuntimeException('Informe um nome para o item.');
    }

    $quantity = normalizeInventoryQuantityValue($quantityValue);
    if ($quantity === null) {
        throw new RuntimeException('Informe uma quantidade valida.');
    }

    $minQuantity = normalizeInventoryQuantityValue($minQuantityValue);
    $unit = normalizeInventoryUnitLabel($unitLabel);
    $groupName = normalizeInventoryGroupName($groupName);
    $notes = normalizeInventoryEntryNotes($notes);
    upsertInventoryGroup($pdo, $groupName, null, $workspaceId);

    $stmt = $pdo->prepare(
        'UPDATE workspace_inventory_entries
         SET label = :label,
             quantity_value = :quantity_value,
             min_quantity_value = :min_quantity_value,
             unit_label = :unit_label,
             group_name = :group_name,
             notes = :notes,
             updated_at = :updated_at
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':label' => $label,
        ':quantity_value' => inventoryQuantityStorageValue($quantity),
        ':min_quantity_value' => $minQuantity !== null ? inventoryQuantityStorageValue($minQuantity) : null,
        ':unit_label' => $unit,
        ':group_name' => $groupName,
        ':notes' => $notes,
        ':updated_at' => nowIso(),
        ':id' => $entryId,
        ':workspace_id' => $workspaceId,
    ]);

    if ($stmt->rowCount() <= 0) {
        $existsStmt = $pdo->prepare(
            'SELECT 1
             FROM workspace_inventory_entries
             WHERE id = :id
               AND workspace_id = :workspace_id
             LIMIT 1'
        );
        $existsStmt->execute([
            ':id' => $entryId,
            ':workspace_id' => $workspaceId,
        ]);
        if (!$existsStmt->fetchColumn()) {
            throw new RuntimeException('Registro nao encontrado.');
        }
    }
}

function updateWorkspaceInventoryEntryQuantity(
    PDO $pdo,
    int $workspaceId,
    int $entryId,
    $quantityValue
): void {
    if ($workspaceId <= 0 || $entryId <= 0) {
        throw new RuntimeException('Registro invalido.');
    }

    $quantity = normalizeInventoryQuantityValue($quantityValue);
    if ($quantity === null) {
        throw new RuntimeException('Informe uma quantidade valida.');
    }

    $stmt = $pdo->prepare(
        'UPDATE workspace_inventory_entries
         SET quantity_value = :quantity_value,
             updated_at = :updated_at
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':quantity_value' => inventoryQuantityStorageValue($quantity),
        ':updated_at' => nowIso(),
        ':id' => $entryId,
        ':workspace_id' => $workspaceId,
    ]);

    if ($stmt->rowCount() <= 0) {
        $existsStmt = $pdo->prepare(
            'SELECT 1
             FROM workspace_inventory_entries
             WHERE id = :id
               AND workspace_id = :workspace_id
             LIMIT 1'
        );
        $existsStmt->execute([
            ':id' => $entryId,
            ':workspace_id' => $workspaceId,
        ]);
        if (!$existsStmt->fetchColumn()) {
            throw new RuntimeException('Registro nao encontrado.');
        }
    }
}

function deleteWorkspaceInventoryEntry(PDO $pdo, int $workspaceId, int $entryId): void
{
    if ($workspaceId <= 0 || $entryId <= 0) {
        throw new RuntimeException('Registro invalido.');
    }

    $stmt = $pdo->prepare(
        'DELETE FROM workspace_inventory_entries
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':id' => $entryId,
        ':workspace_id' => $workspaceId,
    ]);

    if ($stmt->rowCount() <= 0) {
        throw new RuntimeException('Registro nao encontrado.');
    }
}

function normalizeAccountingPeriodKey(?string $value): string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return (new DateTimeImmutable('today'))->format('Y-m');
    }

    if (preg_match('/^\d{4}-\d{2}$/', $raw) === 1) {
        $year = (int) substr($raw, 0, 4);
        $month = (int) substr($raw, 5, 2);
        if ($year >= 1970 && $year <= 9999 && $month >= 1 && $month <= 12) {
            return sprintf('%04d-%02d', $year, $month);
        }
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        return substr($raw, 0, 7);
    }

    return (new DateTimeImmutable('today'))->format('Y-m');
}

function accountingPreviousPeriodKey(?string $periodKey): string
{
    $normalized = normalizeAccountingPeriodKey($periodKey);
    $date = DateTimeImmutable::createFromFormat('!Y-m', $normalized) ?: new DateTimeImmutable('first day of this month');
    return $date->modify('-1 month')->format('Y-m');
}

function accountingNextPeriodKey(?string $periodKey): string
{
    $normalized = normalizeAccountingPeriodKey($periodKey);
    $date = DateTimeImmutable::createFromFormat('!Y-m', $normalized) ?: new DateTimeImmutable('first day of this month');
    return $date->modify('+1 month')->format('Y-m');
}

function accountingMonthLabel(string $periodKey): string
{
    $periodKey = normalizeAccountingPeriodKey($periodKey);
    $year = (int) substr($periodKey, 0, 4);
    $month = (int) substr($periodKey, 5, 2);
    $monthNames = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];

    $monthLabel = $monthNames[$month] ?? 'Mes';
    return $monthLabel . ' de ' . (string) $year;
}

function parseAccountingInstallmentProgress(?string $value): ?array
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/^(\d{1,3})\s*\/\s*(\d{1,3})$/', $raw, $matches) !== 1) {
        return null;
    }

    $installmentNumber = (int) ($matches[1] ?? 0);
    $installmentTotal = (int) ($matches[2] ?? 0);
    if ($installmentTotal < 2 || $installmentNumber < 1 || $installmentNumber > $installmentTotal) {
        return null;
    }

    return [
        'installment_number' => $installmentNumber,
        'installment_total' => $installmentTotal,
    ];
}

function accountingInstallmentProgressLabel(int $installmentNumber, int $installmentTotal): string
{
    if ($installmentTotal < 2 || $installmentNumber < 1 || $installmentNumber > $installmentTotal) {
        return '';
    }

    return $installmentNumber . '/' . $installmentTotal;
}

function accountingInstallmentAmountCents(
    int $totalAmountCents,
    int $installmentNumber,
    int $installmentTotal
): int {
    $totalAmountCents = max(0, $totalAmountCents);
    if ($totalAmountCents <= 0 || $installmentTotal <= 0 || $installmentNumber <= 0) {
        return 0;
    }

    $baseAmount = intdiv($totalAmountCents, $installmentTotal);
    $remainder = $totalAmountCents - ($baseAmount * $installmentTotal);

    return $baseAmount + ($installmentNumber <= $remainder ? 1 : 0);
}

function normalizeAccountingInstallmentMeta(
    $isInstallmentValue,
    $installmentNumberValue,
    $installmentTotalValue,
    int $totalAmountCents = 0
): array {
    $installmentNumber = max(0, (int) $installmentNumberValue);
    $installmentTotal = max(0, (int) $installmentTotalValue);
    $isInstallment = ((int) $isInstallmentValue) === 1 || $installmentTotal > 1;

    if (!$isInstallment || $totalAmountCents <= 0 || $installmentTotal < 2 || $installmentNumber < 1 || $installmentNumber > $installmentTotal) {
        return [
            'is_installment' => 0,
            'installment_number' => 0,
            'installment_total' => 0,
        ];
    }

    return [
        'is_installment' => 1,
        'installment_number' => $installmentNumber,
        'installment_total' => $installmentTotal,
    ];
}

function resolveAccountingEntryAmounts(
    $amountInput,
    $totalAmountInput,
    int $isInstallment = 0,
    ?string $installmentProgress = null,
    $installmentNumberInput = null,
    $installmentTotalInput = null
): array {
    if ($isInstallment === 1) {
        $parsedInstallment = parseAccountingInstallmentProgress($installmentProgress);
        if ($parsedInstallment === null) {
            $installmentNumber = (int) $installmentNumberInput;
            $installmentTotal = (int) $installmentTotalInput;
            if ($installmentTotal >= 2 && $installmentNumber >= 1 && $installmentNumber <= $installmentTotal) {
                $parsedInstallment = [
                    'installment_number' => $installmentNumber,
                    'installment_total' => $installmentTotal,
                ];
            }
        }
        if ($parsedInstallment === null) {
            throw new RuntimeException('Informe a parcela no formato 4/12.');
        }

        $totalAmountCents = normalizeDueAmountCents($totalAmountInput);
        if ($totalAmountCents === null) {
            $totalAmountCents = normalizeDueAmountCents($amountInput);
        }
        if ($totalAmountCents === null) {
            throw new RuntimeException('Informe o valor total do parcelamento.');
        }

        return [
            'amount_cents' => accountingInstallmentAmountCents(
                $totalAmountCents,
                (int) $parsedInstallment['installment_number'],
                (int) $parsedInstallment['installment_total']
            ),
            'total_amount_cents' => $totalAmountCents,
            'is_installment' => 1,
            'installment_number' => (int) $parsedInstallment['installment_number'],
            'installment_total' => (int) $parsedInstallment['installment_total'],
        ];
    }

    $amountCents = normalizeDueAmountCents($amountInput);
    if ($amountCents === null) {
        throw new RuntimeException('Informe um valor valido.');
    }

    return [
        'amount_cents' => $amountCents,
        'total_amount_cents' => $amountCents,
        'is_installment' => 0,
        'installment_number' => 0,
        'installment_total' => 0,
    ];
}

function normalizeAccountingEntryType(string $value): string
{
    $normalized = mb_strtolower(trim($value));
    return $normalized === 'income' ? 'income' : 'expense';
}

function normalizeAccountingEntryLabel(string $value): string
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

function accountingEntryTypeLabel(string $entryType): string
{
    return normalizeAccountingEntryType($entryType) === 'income'
        ? 'Entrada'
        : 'Conta';
}

function workspaceAccountingNormalizeEntryRow(array $row, string $defaultPeriodKey): array
{
    $row['id'] = (int) ($row['id'] ?? 0);
    $row['workspace_id'] = (int) ($row['workspace_id'] ?? 0);
    $row['period_key'] = normalizeAccountingPeriodKey((string) ($row['period_key'] ?? $defaultPeriodKey));
    $row['entry_type'] = normalizeAccountingEntryType((string) ($row['entry_type'] ?? 'expense'));
    $row['entry_type_label'] = accountingEntryTypeLabel((string) $row['entry_type']);
    $row['label'] = normalizeAccountingEntryLabel((string) ($row['label'] ?? ''));
    $row['amount_cents'] = normalizeDueAmountCents($row['amount_cents'] ?? null) ?? 0;
    $row['total_amount_cents'] = normalizeDueAmountCents($row['total_amount_cents'] ?? null);
    if ($row['total_amount_cents'] === null || $row['total_amount_cents'] <= 0) {
        $row['total_amount_cents'] = $row['amount_cents'];
    }

    $installmentMeta = normalizeAccountingInstallmentMeta(
        $row['is_installment'] ?? 0,
        $row['installment_number'] ?? 0,
        $row['installment_total'] ?? 0,
        (int) $row['total_amount_cents']
    );
    $row['is_installment'] = $installmentMeta['is_installment'];
    $row['installment_number'] = $installmentMeta['installment_number'];
    $row['installment_total'] = $installmentMeta['installment_total'];
    if ($row['is_installment'] === 1) {
        $row['amount_cents'] = accountingInstallmentAmountCents(
            (int) $row['total_amount_cents'],
            (int) $row['installment_number'],
            (int) $row['installment_total']
        );
    } else {
        $row['total_amount_cents'] = $row['amount_cents'];
    }

    $row['amount_display'] = dueAmountLabelFromCents($row['amount_cents']);
    $row['amount_input'] = dueAmountLabelFromCents($row['amount_cents']);
    $row['total_amount_display'] = dueAmountLabelFromCents($row['total_amount_cents']);
    $row['total_amount_input'] = dueAmountLabelFromCents($row['total_amount_cents']);
    $row['installment_progress'] = $row['is_installment'] === 1
        ? accountingInstallmentProgressLabel((int) $row['installment_number'], (int) $row['installment_total'])
        : '';

    $row['is_settled'] = ((int) ($row['is_settled'] ?? 0)) === 1 ? 1 : 0;
    $row['sort_order'] = max(0, (int) ($row['sort_order'] ?? 0));
    $row['created_by'] = isset($row['created_by']) ? (int) $row['created_by'] : null;
    $carrySourceEntryId = isset($row['carry_source_entry_id']) ? (int) $row['carry_source_entry_id'] : 0;
    $row['carry_source_entry_id'] = $carrySourceEntryId > 0 ? $carrySourceEntryId : null;
    $row['is_carried'] = $row['carry_source_entry_id'] !== null ? 1 : 0;

    return $row;
}

function workspaceAccountingEntriesListRaw(
    PDO $pdo,
    int $workspaceId,
    string $periodKey,
    ?string $entryType = null
): array {
    if ($workspaceId <= 0) {
        return [];
    }

    $periodKey = normalizeAccountingPeriodKey($periodKey);
    $entryType = $entryType !== null ? normalizeAccountingEntryType($entryType) : null;

    $sql =
        'SELECT ae.id,
                ae.workspace_id,
                ae.period_key,
                ae.entry_type,
                ae.label,
                ae.amount_cents,
                ae.total_amount_cents,
                ae.is_installment,
                ae.installment_number,
                ae.installment_total,
                ae.is_settled,
                ae.carry_source_entry_id,
                ae.sort_order,
                ae.created_by,
                ae.created_at,
                ae.updated_at,
                u.name AS created_by_name
         FROM workspace_accounting_entries ae
         LEFT JOIN users u ON u.id = ae.created_by
         WHERE ae.workspace_id = :workspace_id
           AND ae.period_key = :period_key';
    if ($entryType !== null) {
        $sql .= ' AND ae.entry_type = :entry_type';
    }
    $sql .= '
         ORDER BY ae.entry_type ASC, ae.sort_order ASC, ae.id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
    $stmt->bindValue(':period_key', $periodKey, PDO::PARAM_STR);
    if ($entryType !== null) {
        $stmt->bindValue(':entry_type', $entryType, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row = workspaceAccountingNormalizeEntryRow($row, $periodKey);
    }
    unset($row);

    return $rows;
}

function workspaceAccountingEnsurePeriodCarryover(
    PDO $pdo,
    int $workspaceId,
    string $periodKey
): void {
    if ($workspaceId <= 0) {
        return;
    }

    $periodKey = normalizeAccountingPeriodKey($periodKey);
    $previousPeriod = accountingPreviousPeriodKey($periodKey);
    $sourceEntries = workspaceAccountingEntriesListRaw($pdo, $workspaceId, $previousPeriod, 'expense');
    if (!$sourceEntries) {
        return;
    }

    $existingStmt = $pdo->prepare(
        'SELECT carry_source_entry_id
         FROM workspace_accounting_entries
         WHERE workspace_id = :workspace_id
           AND period_key = :period_key
           AND entry_type = :entry_type
           AND carry_source_entry_id IS NOT NULL'
    );
    $existingStmt->execute([
        ':workspace_id' => $workspaceId,
        ':period_key' => $periodKey,
        ':entry_type' => 'expense',
    ]);
    $existingRows = $existingStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $existingBySourceId = [];
    foreach ($existingRows as $existingSourceId) {
        $normalizedSourceId = (int) $existingSourceId;
        if ($normalizedSourceId > 0) {
            $existingBySourceId[$normalizedSourceId] = true;
        }
    }

    $sortOrderStmt = $pdo->prepare(
        'SELECT COALESCE(MAX(sort_order), 0)
         FROM workspace_accounting_entries
         WHERE workspace_id = :workspace_id
           AND period_key = :period_key
           AND entry_type = :entry_type'
    );
    $sortOrderStmt->execute([
        ':workspace_id' => $workspaceId,
        ':period_key' => $periodKey,
        ':entry_type' => 'expense',
    ]);
    $nextSortOrder = ((int) $sortOrderStmt->fetchColumn()) + 1;

    $insertStmt = $pdo->prepare(
        'INSERT INTO workspace_accounting_entries (
            workspace_id,
            period_key,
            entry_type,
            label,
            amount_cents,
            total_amount_cents,
            is_installment,
            installment_number,
            installment_total,
            is_settled,
            carry_source_entry_id,
            sort_order,
            created_by,
            created_at,
            updated_at
        ) VALUES (
            :workspace_id,
            :period_key,
            :entry_type,
            :label,
            :amount_cents,
            :total_amount_cents,
            :is_installment,
            :installment_number,
            :installment_total,
            :is_settled,
            :carry_source_entry_id,
            :sort_order,
            :created_by,
            :created_at,
            :updated_at
        )'
    );

    foreach ($sourceEntries as $sourceEntry) {
        $sourceEntryId = (int) ($sourceEntry['id'] ?? 0);
        if ($sourceEntryId <= 0) {
            continue;
        }
        if (isset($existingBySourceId[$sourceEntryId])) {
            continue;
        }

        $isSettled = ((int) ($sourceEntry['is_settled'] ?? 0)) === 1;
        $isInstallment = ((int) ($sourceEntry['is_installment'] ?? 0)) === 1;
        $installmentNumber = (int) ($sourceEntry['installment_number'] ?? 0);
        $installmentTotal = (int) ($sourceEntry['installment_total'] ?? 0);
        $totalAmountCents = normalizeDueAmountCents($sourceEntry['total_amount_cents'] ?? null) ?? 0;
        $amountCents = normalizeDueAmountCents($sourceEntry['amount_cents'] ?? null) ?? 0;

        if ($isInstallment && $installmentTotal >= 2) {
            if ($installmentNumber < 1) {
                $installmentNumber = 1;
            } elseif ($installmentNumber > $installmentTotal) {
                $installmentNumber = $installmentTotal;
            }

            if ($isSettled) {
                if ($installmentNumber >= $installmentTotal) {
                    continue;
                }
                $installmentNumber++;
            }

            $amountCents = accountingInstallmentAmountCents($totalAmountCents, $installmentNumber, $installmentTotal);
        } else {
            if ($isSettled) {
                continue;
            }
            $isInstallment = false;
            $installmentNumber = 0;
            $installmentTotal = 0;
            $totalAmountCents = $amountCents;
        }

        $timestamp = nowIso();
        $insertStmt->execute([
            ':workspace_id' => $workspaceId,
            ':period_key' => $periodKey,
            ':entry_type' => 'expense',
            ':label' => normalizeAccountingEntryLabel((string) ($sourceEntry['label'] ?? '')),
            ':amount_cents' => $amountCents,
            ':total_amount_cents' => $totalAmountCents,
            ':is_installment' => $isInstallment ? 1 : 0,
            ':installment_number' => $installmentNumber,
            ':installment_total' => $installmentTotal,
            ':is_settled' => 0,
            ':carry_source_entry_id' => $sourceEntryId,
            ':sort_order' => $nextSortOrder++,
            ':created_by' => isset($sourceEntry['created_by']) && (int) $sourceEntry['created_by'] > 0
                ? (int) $sourceEntry['created_by']
                : null,
            ':created_at' => $timestamp,
            ':updated_at' => $timestamp,
        ]);
    }
}

function workspaceAccountingEnsureCarryoverUpTo(PDO $pdo, int $workspaceId, string $periodKey): void
{
    if ($workspaceId <= 0) {
        return;
    }

    $periodKey = normalizeAccountingPeriodKey($periodKey);
    $earliestStmt = $pdo->prepare(
        'SELECT MIN(period_key)
         FROM workspace_accounting_entries
         WHERE workspace_id = :workspace_id
           AND period_key < :period_key'
    );
    $earliestStmt->execute([
        ':workspace_id' => $workspaceId,
        ':period_key' => $periodKey,
    ]);
    $earliestPeriodRaw = $earliestStmt->fetchColumn();
    if (!is_string($earliestPeriodRaw) || trim($earliestPeriodRaw) === '') {
        return;
    }

    $cursor = accountingNextPeriodKey(normalizeAccountingPeriodKey($earliestPeriodRaw));
    while ($cursor <= $periodKey) {
        workspaceAccountingEnsurePeriodCarryover($pdo, $workspaceId, $cursor);
        if ($cursor === $periodKey) {
            break;
        }
        $cursor = accountingNextPeriodKey($cursor);
    }
}

function workspaceAccountingOpeningBalanceOverrides(
    PDO $pdo,
    int $workspaceId,
    string $periodKey
): array {
    $periodKey = normalizeAccountingPeriodKey($periodKey);
    $stmt = $pdo->prepare(
        'SELECT period_key, opening_balance_cents
         FROM workspace_accounting_periods
         WHERE workspace_id = :workspace_id
           AND period_key <= :period_key'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':period_key' => $periodKey,
    ]);
    $rows = $stmt->fetchAll();

    $overrides = [];
    foreach ($rows as $row) {
        $rowPeriod = normalizeAccountingPeriodKey((string) ($row['period_key'] ?? ''));
        $overrides[$rowPeriod] = normalizeDueAmountCents($row['opening_balance_cents'] ?? null) ?? 0;
    }

    return $overrides;
}

function workspaceAccountingFirstRelevantPeriodKey(
    PDO $pdo,
    int $workspaceId,
    string $periodKey
): ?string {
    $periodKey = normalizeAccountingPeriodKey($periodKey);
    $stmt = $pdo->prepare(
        'SELECT MIN(period_key) AS first_period
         FROM (
            SELECT period_key
            FROM workspace_accounting_entries
            WHERE workspace_id = :workspace_id
              AND period_key <= :period_key
            UNION ALL
            SELECT period_key
            FROM workspace_accounting_periods
            WHERE workspace_id = :workspace_id
              AND period_key <= :period_key
         ) periods'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':period_key' => $periodKey,
    ]);
    $firstPeriodRaw = $stmt->fetchColumn();
    if (!is_string($firstPeriodRaw) || trim($firstPeriodRaw) === '') {
        return null;
    }

    return normalizeAccountingPeriodKey($firstPeriodRaw);
}

function workspaceAccountingOpeningBalanceCents(?int $workspaceId = null, ?string $periodKey = null): int
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return 0;
    }

    $periodKey = normalizeAccountingPeriodKey($periodKey);
    $pdo = db();
    workspaceAccountingEnsureCarryoverUpTo($pdo, $workspaceId, $periodKey);

    $firstPeriod = workspaceAccountingFirstRelevantPeriodKey($pdo, $workspaceId, $periodKey);
    if ($firstPeriod === null) {
        return 0;
    }

    $openingOverrides = workspaceAccountingOpeningBalanceOverrides($pdo, $workspaceId, $periodKey);
    $openingBalance = $openingOverrides[$firstPeriod] ?? 0;
    if ($firstPeriod === $periodKey) {
        return $openingBalance;
    }

    $cursor = $firstPeriod;
    while ($cursor < $periodKey) {
        $periodEntries = workspaceAccountingEntriesListRaw($pdo, $workspaceId, $cursor);
        $periodSummary = accountingSummary($periodEntries, $openingBalance);
        $nextPeriod = accountingNextPeriodKey($cursor);
        $openingBalance = $openingOverrides[$nextPeriod] ?? (int) ($periodSummary['final_balance_cents'] ?? 0);
        $cursor = $nextPeriod;
    }

    return $openingBalance;
}

function setWorkspaceAccountingOpeningBalance(
    PDO $pdo,
    int $workspaceId,
    ?string $periodKey,
    $amountInput,
    ?int $updatedBy = null
): int {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    $periodKey = normalizeAccountingPeriodKey($periodKey);
    $amountCents = normalizeDueAmountCents($amountInput);
    if ($amountCents === null) {
        throw new RuntimeException('Informe um saldo atual valido.');
    }

    $updatedAt = nowIso();

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_accounting_periods (
                workspace_id,
                period_key,
                opening_balance_cents,
                updated_by,
                updated_at
            ) VALUES (
                :workspace_id,
                :period_key,
                :opening_balance_cents,
                :updated_by,
                :updated_at
            )
            ON CONFLICT (workspace_id, period_key)
            DO UPDATE SET
                opening_balance_cents = EXCLUDED.opening_balance_cents,
                updated_by = EXCLUDED.updated_by,
                updated_at = EXCLUDED.updated_at'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_accounting_periods (
                workspace_id,
                period_key,
                opening_balance_cents,
                updated_by,
                updated_at
            ) VALUES (
                :workspace_id,
                :period_key,
                :opening_balance_cents,
                :updated_by,
                :updated_at
            )
            ON CONFLICT(workspace_id, period_key)
            DO UPDATE SET
                opening_balance_cents = excluded.opening_balance_cents,
                updated_by = excluded.updated_by,
                updated_at = excluded.updated_at'
        );
    }

    $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
    $stmt->bindValue(':period_key', $periodKey, PDO::PARAM_STR);
    $stmt->bindValue(':opening_balance_cents', $amountCents, PDO::PARAM_INT);
    if ($updatedBy !== null && $updatedBy > 0) {
        $stmt->bindValue(':updated_by', $updatedBy, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':updated_by', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':updated_at', $updatedAt, PDO::PARAM_STR);
    $stmt->execute();

    return $amountCents;
}

function workspaceAccountingEntriesList(
    ?int $workspaceId = null,
    ?string $periodKey = null,
    ?string $entryType = null
): array {
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return [];
    }

    $periodKey = normalizeAccountingPeriodKey($periodKey);
    $pdo = db();
    workspaceAccountingEnsureCarryoverUpTo($pdo, $workspaceId, $periodKey);
    return workspaceAccountingEntriesListRaw(
        $pdo,
        $workspaceId,
        $periodKey,
        $entryType !== null ? normalizeAccountingEntryType($entryType) : null
    );
}

function workspaceAccountingEntriesByType(array $entries): array
{
    $grouped = [
        'expense' => [],
        'income' => [],
    ];

    foreach ($entries as $entry) {
        $entryType = normalizeAccountingEntryType((string) ($entry['entry_type'] ?? 'expense'));
        if (!array_key_exists($entryType, $grouped)) {
            $grouped[$entryType] = [];
        }
        $grouped[$entryType][] = $entry;
    }

    return $grouped;
}

function createWorkspaceAccountingEntry(
    PDO $pdo,
    int $workspaceId,
    ?string $periodKey,
    string $entryType,
    string $label,
    $amountInput,
    int $isSettled = 0,
    ?int $createdBy = null,
    int $isInstallment = 0,
    ?string $installmentProgress = null,
    $totalAmountInput = null,
    $installmentNumberInput = null,
    $installmentTotalInput = null
): int {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    $periodKey = normalizeAccountingPeriodKey($periodKey);
    $entryType = normalizeAccountingEntryType($entryType);
    $label = normalizeAccountingEntryLabel($label);
    if ($label === '') {
        throw new RuntimeException('Informe um nome para o registro.');
    }
    $amountPayload = resolveAccountingEntryAmounts(
        $amountInput,
        $totalAmountInput,
        $isInstallment === 1 ? 1 : 0,
        $installmentProgress,
        $installmentNumberInput,
        $installmentTotalInput
    );
    $settledFlag = $isSettled === 1 ? 1 : 0;

    $sortOrderStmt = $pdo->prepare(
        'SELECT COALESCE(MAX(sort_order), 0)
         FROM workspace_accounting_entries
         WHERE workspace_id = :workspace_id
           AND period_key = :period_key
           AND entry_type = :entry_type'
    );
    $sortOrderStmt->execute([
        ':workspace_id' => $workspaceId,
        ':period_key' => $periodKey,
        ':entry_type' => $entryType,
    ]);
    $nextSortOrder = ((int) $sortOrderStmt->fetchColumn()) + 1;
    $createdAt = nowIso();

    if (dbDriverName($pdo) === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_accounting_entries (
                workspace_id,
                period_key,
                entry_type,
                label,
                amount_cents,
                total_amount_cents,
                is_installment,
                installment_number,
                installment_total,
                is_settled,
                sort_order,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :workspace_id,
                :period_key,
                :entry_type,
                :label,
                :amount_cents,
                :total_amount_cents,
                :is_installment,
                :installment_number,
                :installment_total,
                :is_settled,
                :sort_order,
                :created_by,
                :created_at,
                :updated_at
            )
            RETURNING id'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO workspace_accounting_entries (
                workspace_id,
                period_key,
                entry_type,
                label,
                amount_cents,
                total_amount_cents,
                is_installment,
                installment_number,
                installment_total,
                is_settled,
                sort_order,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :workspace_id,
                :period_key,
                :entry_type,
                :label,
                :amount_cents,
                :total_amount_cents,
                :is_installment,
                :installment_number,
                :installment_total,
                :is_settled,
                :sort_order,
                :created_by,
                :created_at,
                :updated_at
            )'
        );
    }

    $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
    $stmt->bindValue(':period_key', $periodKey, PDO::PARAM_STR);
    $stmt->bindValue(':entry_type', $entryType, PDO::PARAM_STR);
    $stmt->bindValue(':label', $label, PDO::PARAM_STR);
    $stmt->bindValue(':amount_cents', (int) $amountPayload['amount_cents'], PDO::PARAM_INT);
    $stmt->bindValue(':total_amount_cents', (int) $amountPayload['total_amount_cents'], PDO::PARAM_INT);
    $stmt->bindValue(':is_installment', (int) $amountPayload['is_installment'], PDO::PARAM_INT);
    $stmt->bindValue(':installment_number', (int) $amountPayload['installment_number'], PDO::PARAM_INT);
    $stmt->bindValue(':installment_total', (int) $amountPayload['installment_total'], PDO::PARAM_INT);
    $stmt->bindValue(':is_settled', $settledFlag, PDO::PARAM_INT);
    $stmt->bindValue(':sort_order', $nextSortOrder, PDO::PARAM_INT);
    if ($createdBy !== null && $createdBy > 0) {
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
    $stmt->bindValue(':updated_at', $createdAt, PDO::PARAM_STR);
    $stmt->execute();

    if (dbDriverName($pdo) === 'pgsql') {
        return (int) $stmt->fetchColumn();
    }

    return (int) $pdo->lastInsertId();
}

function updateWorkspaceAccountingEntry(
    PDO $pdo,
    int $workspaceId,
    int $entryId,
    string $label,
    $amountInput,
    int $isSettled = 0,
    int $isInstallment = 0,
    ?string $installmentProgress = null,
    $totalAmountInput = null,
    $installmentNumberInput = null,
    $installmentTotalInput = null
): void {
    if ($workspaceId <= 0 || $entryId <= 0) {
        throw new RuntimeException('Registro invalido.');
    }

    $label = normalizeAccountingEntryLabel($label);
    if ($label === '') {
        throw new RuntimeException('Informe um nome para o registro.');
    }
    $amountPayload = resolveAccountingEntryAmounts(
        $amountInput,
        $totalAmountInput,
        $isInstallment === 1 ? 1 : 0,
        $installmentProgress,
        $installmentNumberInput,
        $installmentTotalInput
    );
    $settledFlag = $isSettled === 1 ? 1 : 0;

    $stmt = $pdo->prepare(
        'UPDATE workspace_accounting_entries
         SET label = :label,
             amount_cents = :amount_cents,
             total_amount_cents = :total_amount_cents,
             is_installment = :is_installment,
             installment_number = :installment_number,
             installment_total = :installment_total,
             is_settled = :is_settled,
             updated_at = :updated_at
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':label' => $label,
        ':amount_cents' => (int) $amountPayload['amount_cents'],
        ':total_amount_cents' => (int) $amountPayload['total_amount_cents'],
        ':is_installment' => (int) $amountPayload['is_installment'],
        ':installment_number' => (int) $amountPayload['installment_number'],
        ':installment_total' => (int) $amountPayload['installment_total'],
        ':is_settled' => $settledFlag,
        ':updated_at' => nowIso(),
        ':id' => $entryId,
        ':workspace_id' => $workspaceId,
    ]);

    if ($stmt->rowCount() <= 0) {
        $existsStmt = $pdo->prepare(
            'SELECT 1
             FROM workspace_accounting_entries
             WHERE id = :id
               AND workspace_id = :workspace_id
             LIMIT 1'
        );
        $existsStmt->execute([
            ':id' => $entryId,
            ':workspace_id' => $workspaceId,
        ]);
        if (!$existsStmt->fetchColumn()) {
            throw new RuntimeException('Registro nao encontrado.');
        }
    }
}

function deleteWorkspaceAccountingEntry(PDO $pdo, int $workspaceId, int $entryId): void
{
    if ($workspaceId <= 0 || $entryId <= 0) {
        throw new RuntimeException('Registro invalido.');
    }

    $stmt = $pdo->prepare(
        'DELETE FROM workspace_accounting_entries
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':id' => $entryId,
        ':workspace_id' => $workspaceId,
    ]);

    if ($stmt->rowCount() <= 0) {
        throw new RuntimeException('Registro nao encontrado.');
    }
}

function accountingSummary(array $entries, int $openingBalanceCents): array
{
    $expenseTotal = 0;
    $expensePaid = 0;
    $incomeTotal = 0;
    $incomeReceived = 0;

    foreach ($entries as $entry) {
        $entryType = normalizeAccountingEntryType((string) ($entry['entry_type'] ?? 'expense'));
        $amountCents = normalizeDueAmountCents($entry['amount_cents'] ?? null) ?? 0;
        $isSettled = ((int) ($entry['is_settled'] ?? 0)) === 1;

        if ($entryType === 'income') {
            $incomeTotal += $amountCents;
            if ($isSettled) {
                $incomeReceived += $amountCents;
            }
        } else {
            $expenseTotal += $amountCents;
            if ($isSettled) {
                $expensePaid += $amountCents;
            }
        }
    }

    $expenseRemaining = max(0, $expenseTotal - $expensePaid);
    $incomeRemaining = max(0, $incomeTotal - $incomeReceived);
    $currentBalance = $incomeReceived - $expensePaid;
    $finalBalance = $openingBalanceCents + $currentBalance;

    return [
        'expense_total_cents' => $expenseTotal,
        'expense_paid_cents' => $expensePaid,
        'expense_remaining_cents' => $expenseRemaining,
        'income_total_cents' => $incomeTotal,
        'income_received_cents' => $incomeReceived,
        'income_remaining_cents' => $incomeRemaining,
        'current_balance_cents' => $currentBalance,
        'opening_balance_cents' => $openingBalanceCents,
        'final_balance_cents' => $finalBalance,
        'expense_total_display' => dueAmountLabelFromCents($expenseTotal),
        'expense_paid_display' => dueAmountLabelFromCents($expensePaid),
        'expense_remaining_display' => dueAmountLabelFromCents($expenseRemaining),
        'income_total_display' => dueAmountLabelFromCents($incomeTotal),
        'income_received_display' => dueAmountLabelFromCents($incomeReceived),
        'income_remaining_display' => dueAmountLabelFromCents($incomeRemaining),
        'current_balance_display' => dueAmountLabelFromSignedCents($currentBalance),
        'opening_balance_display' => dueAmountLabelFromCents($openingBalanceCents),
        'final_balance_display' => dueAmountLabelFromCents($finalBalance),
    ];
}

function defaultTaskStatusDefinitions(): array
{
    return [
        ['key' => 'todo', 'label' => 'A fazer', 'color' => '#6EA5E9'],
        ['key' => 'in_progress', 'label' => 'Em andamento', 'color' => '#E8A15D'],
        ['key' => 'review', 'label' => 'Revisão'],
        ['key' => 'done', 'label' => 'Concluído'],
    ];
}

function defaultTaskReviewStatusKey(): ?string
{
    return 'review';
}

function taskStatusDefaultColorForKind(string $kind): string
{
    return match (trim($kind)) {
        'todo' => '#6EA5E9',
        'review' => '#9C84E6',
        'done' => '#61BE92',
        default => '#E8A15D',
    };
}

function taskStatusColorPalette(): array
{
    return [
        '#6EA5E9' => 'Azul',
        '#E8A15D' => 'Laranja',
        '#9C84E6' => 'Roxo',
        '#61BE92' => 'Verde',
        '#D67A78' => 'Vermelho',
        '#E3C86B' => 'Amarelo',
        '#59AFC0' => 'Ciano',
        '#8C99AD' => 'Cinza',
    ];
}

function taskStatusColorPaletteValues(): array
{
    return array_keys(taskStatusColorPalette());
}

function normalizeTaskStatusColor(string $value, ?string $fallbackKind = null): string
{
    $fallback = taskStatusDefaultColorForKind((string) $fallbackKind);
    $normalized = strtoupper(trim($value));
    if ($normalized === '') {
        return $fallback;
    }

    if (preg_match('/^#[0-9A-F]{3}$/', $normalized)) {
        $normalized = sprintf(
            '#%1$s%1$s%2$s%2$s%3$s%3$s',
            $normalized[1],
            $normalized[2],
            $normalized[3]
        );
    }

    return preg_match('/^#[0-9A-F]{6}$/', $normalized) ? $normalized : $fallback;
}

function normalizeTaskStatusPaletteColor(string $value, ?string $fallbackKind = null): string
{
    $paletteValues = taskStatusColorPaletteValues();
    if ($paletteValues === []) {
        return normalizeTaskStatusColor($value, $fallbackKind);
    }

    $fallbackColor = taskStatusDefaultColorForKind((string) $fallbackKind);
    $normalized = normalizeTaskStatusColor($value, $fallbackKind);
    if (in_array($normalized, $paletteValues, true)) {
        return $normalized;
    }

    if (!preg_match('/^#[0-9A-F]{6}$/', $normalized)) {
        return in_array($fallbackColor, $paletteValues, true)
            ? $fallbackColor
            : $paletteValues[0];
    }

    [$sourceRed, $sourceGreen, $sourceBlue] = hexColorToRgbComponents($normalized);
    $nearestColor = in_array($fallbackColor, $paletteValues, true)
        ? $fallbackColor
        : $paletteValues[0];
    $nearestDistance = PHP_INT_MAX;

    foreach ($paletteValues as $paletteColor) {
        [$targetRed, $targetGreen, $targetBlue] = hexColorToRgbComponents($paletteColor);
        $distance = (($sourceRed - $targetRed) ** 2)
            + (($sourceGreen - $targetGreen) ** 2)
            + (($sourceBlue - $targetBlue) ** 2);
        if ($distance < $nearestDistance) {
            $nearestDistance = $distance;
            $nearestColor = $paletteColor;
        }
    }

    return $nearestColor;
}

function hexColorToRgbComponents(string $value): array
{
    $color = ltrim(normalizeTaskStatusColor($value), '#');
    return [
        hexdec(substr($color, 0, 2)),
        hexdec(substr($color, 2, 2)),
        hexdec(substr($color, 4, 2)),
    ];
}

function mixHexColors(string $source, string $target, float $targetWeight = 0.5): string
{
    $targetWeight = max(0.0, min(1.0, $targetWeight));
    [$sourceRed, $sourceGreen, $sourceBlue] = hexColorToRgbComponents($source);
    [$targetRed, $targetGreen, $targetBlue] = hexColorToRgbComponents($target);

    $mixChannel = static function (int $from, int $to) use ($targetWeight): int {
        return (int) round(($from * (1 - $targetWeight)) + ($to * $targetWeight));
    };

    return sprintf(
        '#%02X%02X%02X',
        $mixChannel($sourceRed, $targetRed),
        $mixChannel($sourceGreen, $targetGreen),
        $mixChannel($sourceBlue, $targetBlue)
    );
}

function taskStatusCssVars(string $color): string
{
    $normalized = normalizeTaskStatusColor($color);
    [$red, $green, $blue] = hexColorToRgbComponents($normalized);
    $textColor = mixHexColors($normalized, '#24466F', 0.72);

    return sprintf(
        '--wf-status-color: %1$s; --wf-status-rgb: %2$d, %3$d, %4$d; --task-status-rgb: %2$d, %3$d, %4$d; --wf-status-text: %5$s;',
        $normalized,
        $red,
        $green,
        $blue,
        $textColor
    );
}

function normalizeWorkspaceTaskStatusLabel(string $label, string $fallback = ''): string
{
    $label = preg_replace('/\s+/u', ' ', trim($label)) ?? trim($label);
    if ($label === '') {
        $label = preg_replace('/\s+/u', ' ', trim($fallback)) ?? trim($fallback);
    }
    if ($label === '') {
        $label = 'Novo status';
    }
    if (mb_strlen($label) > 40) {
        $label = mb_substr($label, 0, 40);
    }

    return uppercaseFirstCharacter($label);
}

function workspaceTaskStatusKeyCandidateFromLabel(string $label): string
{
    $candidate = trim(mb_strtolower($label));
    if ($candidate === '') {
        return '';
    }

    if (function_exists('iconv')) {
        $asciiCandidate = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $candidate);
        if (is_string($asciiCandidate) && trim($asciiCandidate) !== '') {
            $candidate = $asciiCandidate;
        }
    }

    $candidate = preg_replace('/[^a-z0-9]+/i', '_', $candidate) ?? $candidate;
    $candidate = trim((string) $candidate, '_');
    return mb_strtolower($candidate);
}

function generateWorkspaceTaskStatusKey(array $existingKeys, string $label): string
{
    $existingMap = [];
    foreach ($existingKeys as $existingKey) {
        $normalizedExistingKey = workspaceTaskStatusKeyCandidateFromLabel((string) $existingKey);
        if ($normalizedExistingKey !== '') {
            $existingMap[$normalizedExistingKey] = true;
        }
    }

    $baseKey = workspaceTaskStatusKeyCandidateFromLabel($label);
    if ($baseKey === '' || isset($existingMap[$baseKey])) {
        $baseKey = 'status';
    }

    $key = $baseKey;
    $suffix = 2;
    while (isset($existingMap[$key])) {
        $key = $baseKey . '_' . $suffix;
        $suffix++;
    }

    return $key;
}

function normalizeWorkspaceTaskStatusDefinitions(array $definitions, ?string $reviewStatusKey = null): array
{
    $defaultDefinitions = defaultTaskStatusDefinitions();
    $rawDefinitions = [];

    foreach ($definitions as $definition) {
        if (!is_array($definition)) {
            continue;
        }

        $rawDefinitions[] = [
            'key' => trim((string) ($definition['key'] ?? '')),
            'label' => trim((string) ($definition['label'] ?? $definition['name'] ?? '')),
            'color' => trim((string) ($definition['color'] ?? '')),
        ];
    }

    if (count($rawDefinitions) < 2) {
        $rawDefinitions = $defaultDefinitions;
    }

    $firstLabel = normalizeWorkspaceTaskStatusLabel(
        (string) ($rawDefinitions[0]['label'] ?? ''),
        (string) ($defaultDefinitions[0]['label'] ?? 'A fazer')
    );
    $lastRawDefinition = $rawDefinitions[count($rawDefinitions) - 1] ?? [];
    $lastLabel = normalizeWorkspaceTaskStatusLabel(
        (string) ($lastRawDefinition['label'] ?? ''),
        (string) ($defaultDefinitions[count($defaultDefinitions) - 1]['label'] ?? 'Concluído')
    );

    $normalizedList = [[
        'key' => 'todo',
        'label' => $firstLabel,
        'color' => (string) ($rawDefinitions[0]['color'] ?? ''),
    ]];
    $existingKeys = ['todo', 'done'];

    foreach (array_slice($rawDefinitions, 1, -1) as $definition) {
        $label = normalizeWorkspaceTaskStatusLabel(
            (string) ($definition['label'] ?? ''),
            (string) ($definition['key'] ?? 'Status')
        );
        $candidateKey = workspaceTaskStatusKeyCandidateFromLabel((string) ($definition['key'] ?? ''));
        if ($candidateKey === '' || in_array($candidateKey, ['todo', 'done'], true) || in_array($candidateKey, $existingKeys, true)) {
            $candidateKey = generateWorkspaceTaskStatusKey($existingKeys, $label);
        }

        $normalizedList[] = [
            'key' => $candidateKey,
            'label' => $label,
            'color' => (string) ($definition['color'] ?? ''),
        ];
        $existingKeys[] = $candidateKey;
    }

    $normalizedList[] = [
        'key' => 'done',
        'label' => $lastLabel,
        'color' => (string) ($lastRawDefinition['color'] ?? ''),
    ];

    $reviewKey = workspaceTaskStatusKeyCandidateFromLabel((string) $reviewStatusKey);
    if (!in_array($reviewKey, array_column(array_slice($normalizedList, 1, -1), 'key'), true)) {
        $reviewKey = null;
    }

    $options = [];
    $metaByKey = [];
    $orderByKey = [];
    $lastIndex = count($normalizedList) - 1;

    foreach ($normalizedList as $index => $definition) {
        $key = (string) ($definition['key'] ?? '');
        $label = normalizeWorkspaceTaskStatusLabel((string) ($definition['label'] ?? ''), $key);
        $kind = 'in_progress';
        if ($index === 0) {
            $kind = 'todo';
        } elseif ($index === $lastIndex) {
            $kind = 'done';
        } elseif ($reviewKey !== null && $key === $reviewKey) {
            $kind = 'review';
        }

        $colorFallbackKind = match ($key) {
            'todo' => 'todo',
            'done' => 'done',
            'review' => 'review',
            default => $kind,
        };
        $color = normalizeTaskStatusPaletteColor((string) ($definition['color'] ?? ''), $colorFallbackKind);
        $cssVars = taskStatusCssVars($color);

        $options[$key] = $label;
        $orderByKey[$key] = $index + 1;
        $metaByKey[$key] = [
            'key' => $key,
            'label' => $label,
            'color' => $color,
            'css_vars' => $cssVars,
            'kind' => $kind,
            'order' => $index + 1,
            'is_locked' => $index === 0 || $index === $lastIndex,
            'is_review' => $reviewKey !== null && $key === $reviewKey,
        ];
        $normalizedList[$index]['label'] = $label;
        $normalizedList[$index]['color'] = $color;
        $normalizedList[$index]['css_vars'] = $cssVars;
        $normalizedList[$index]['kind'] = $kind;
        $normalizedList[$index]['order'] = $index + 1;
        $normalizedList[$index]['is_locked'] = $index === 0 || $index === $lastIndex;
        $normalizedList[$index]['is_review'] = $reviewKey !== null && $key === $reviewKey;
    }

    return [
        'list' => $normalizedList,
        'options' => $options,
        'meta_by_key' => $metaByKey,
        'order_by_key' => $orderByKey,
        'todo_status_key' => 'todo',
        'done_status_key' => 'done',
        'review_status_key' => $reviewKey,
        'default_status_key' => 'todo',
    ];
}

function encodeWorkspaceTaskStatusDefinitions(array $definitions): string
{
    $normalized = normalizeWorkspaceTaskStatusDefinitions($definitions);
    $payload = array_map(
        static fn (array $definition): array => [
            'key' => (string) ($definition['key'] ?? ''),
            'label' => (string) ($definition['label'] ?? ''),
            'color' => normalizeTaskStatusPaletteColor(
                (string) ($definition['color'] ?? ''),
                (string) ($definition['kind'] ?? 'in_progress')
            ),
        ],
        $normalized['list'] ?? []
    );

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($encoded) && $encoded !== '' ? $encoded : '[]';
}

function workspaceTaskStatusDuplicateColors(array $definitions): array
{
    $counts = [];
    $duplicates = [];

    foreach ($definitions as $definition) {
        if (!is_array($definition)) {
            continue;
        }

        $kind = trim((string) ($definition['kind'] ?? 'in_progress'));
        $color = normalizeTaskStatusPaletteColor((string) ($definition['color'] ?? ''), $kind);
        if ($color === '') {
            continue;
        }

        $counts[$color] = (int) ($counts[$color] ?? 0) + 1;
        if ($counts[$color] > 1) {
            $duplicates[$color] = true;
        }
    }

    return array_keys($duplicates);
}

function &taskStatusConfigCacheStore(): array
{
    static $cache = [];
    return $cache;
}

function clearTaskStatusConfigCache(?int $workspaceId = null): void
{
    $cache = &taskStatusConfigCacheStore();
    if ($workspaceId !== null && $workspaceId > 0) {
        unset($cache[$workspaceId]);
        return;
    }

    $cache = [];
}

function taskStatusConfig(?int $workspaceId = null, ?array $workspace = null): array
{
    $workspaceId = $workspaceId && $workspaceId > 0
        ? $workspaceId
        : (int) ($workspace['id'] ?? activeWorkspaceId() ?? 0);

    if ($workspaceId <= 0) {
        return normalizeWorkspaceTaskStatusDefinitions(
            defaultTaskStatusDefinitions(),
            defaultTaskReviewStatusKey()
        );
    }

    $cache = &taskStatusConfigCacheStore();
    if ($workspace === null && isset($cache[$workspaceId])) {
        return $cache[$workspaceId];
    }

    if (!$workspace || (int) ($workspace['id'] ?? 0) !== $workspaceId) {
        $workspace = workspaceById($workspaceId);
    }

    $definitions = defaultTaskStatusDefinitions();
    $reviewStatusKey = defaultTaskReviewStatusKey();

    if ($workspace) {
        $rawJson = trim((string) ($workspace['task_statuses_json'] ?? ''));
        if ($rawJson !== '') {
            try {
                $decoded = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $definitions = $decoded;
                }
            } catch (Throwable $e) {
                $definitions = defaultTaskStatusDefinitions();
            }
        }

        $reviewStatusKeyValue = trim((string) ($workspace['task_review_status_key'] ?? ''));
        $reviewStatusKey = $reviewStatusKeyValue !== '' ? $reviewStatusKeyValue : null;
    }

    $config = normalizeWorkspaceTaskStatusDefinitions($definitions, $reviewStatusKey);
    $cache[$workspaceId] = $config;
    return $config;
}

function taskStatusMeta(string $value, ?int $workspaceId = null, ?array $workspace = null): array
{
    $config = taskStatusConfig($workspaceId, $workspace);
    $normalizedValue = array_key_exists($value, $config['options'])
        ? $value
        : (string) ($config['todo_status_key'] ?? 'todo');

    return $config['meta_by_key'][$normalizedValue]
        ?? [
            'key' => $normalizedValue,
            'label' => $config['options'][$normalizedValue] ?? $normalizedValue,
            'color' => taskStatusDefaultColorForKind('todo'),
            'css_vars' => taskStatusCssVars(taskStatusDefaultColorForKind('todo')),
            'kind' => 'todo',
            'order' => 1,
            'is_locked' => true,
            'is_review' => false,
        ];
}

function taskStatusLabel(string $value, ?int $workspaceId = null, ?array $workspace = null): string
{
    return (string) (taskStatusMeta($value, $workspaceId, $workspace)['label'] ?? $value);
}

function taskStatusKind(string $value, ?int $workspaceId = null, ?array $workspace = null): string
{
    return (string) (taskStatusMeta($value, $workspaceId, $workspace)['kind'] ?? 'todo');
}

function taskStatusOrder(string $value, ?int $workspaceId = null, ?array $workspace = null): int
{
    return (int) (taskStatusMeta($value, $workspaceId, $workspace)['order'] ?? 1);
}

function taskDoneStatusKey(?int $workspaceId = null, ?array $workspace = null): string
{
    return (string) (taskStatusConfig($workspaceId, $workspace)['done_status_key'] ?? 'done');
}

function taskReviewStatusKey(?int $workspaceId = null, ?array $workspace = null): ?string
{
    $reviewStatusKey = trim((string) (taskStatusConfig($workspaceId, $workspace)['review_status_key'] ?? ''));
    return $reviewStatusKey !== '' ? $reviewStatusKey : null;
}

function taskPriorityOrder(string $value): int
{
    return match (normalizeTaskPriority($value)) {
        'urgent' => 1,
        'high' => 2,
        'medium' => 3,
        'low' => 4,
        default => 99,
    };
}

function taskStatusKindFromTask(array $task, ?int $workspaceId = null, ?array $workspace = null): string
{
    $statusKind = trim((string) ($task['status_kind'] ?? ''));
    if (in_array($statusKind, ['todo', 'in_progress', 'review', 'done'], true)) {
        return $statusKind;
    }

    return taskStatusKind((string) ($task['status'] ?? ''), $workspaceId, $workspace);
}

function taskStatusOrderFromTask(array $task, ?int $workspaceId = null, ?array $workspace = null): int
{
    $statusOrder = (int) ($task['status_order'] ?? 0);
    if ($statusOrder > 0) {
        return $statusOrder;
    }

    return taskStatusOrder((string) ($task['status'] ?? ''), $workspaceId, $workspace);
}

function taskDoneStatusFromTask(array $task, ?int $workspaceId = null, ?array $workspace = null): bool
{
    return taskStatusKindFromTask($task, $workspaceId, $workspace) === 'done';
}

function workspaceUpdateTaskStatusConfiguration(
    PDO $pdo,
    int $workspaceId,
    array $definitions,
    ?string $reviewStatusKey = null,
    ?string $removeStatusKey = null,
    ?string $newStatusLabel = null,
    ?string $newStatusColor = null
): array {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    ensureWorkspaceTaskStatusSchema($pdo);

    $config = normalizeWorkspaceTaskStatusDefinitions($definitions, $reviewStatusKey);
    $workingList = $config['list'];
    $workingReviewStatusKey = $config['review_status_key'];
    $removeStatusKey = workspaceTaskStatusKeyCandidateFromLabel((string) $removeStatusKey);

    if ($removeStatusKey !== '') {
        $removeIndex = null;
        foreach ($workingList as $index => $definition) {
            if ((string) ($definition['key'] ?? '') === $removeStatusKey) {
                $removeIndex = $index;
                break;
            }
        }

        if ($removeIndex !== null && $removeIndex > 0 && $removeIndex < count($workingList) - 1) {
            $fallbackIndex = $removeIndex - 1;
            if ($fallbackIndex < 0 || $fallbackIndex === $removeIndex) {
                $fallbackIndex = min(count($workingList) - 1, $removeIndex + 1);
            }

            $fallbackKey = (string) ($workingList[$fallbackIndex]['key'] ?? 'todo');
            $remapStmt = $pdo->prepare(
                'UPDATE tasks
                 SET status = :fallback_status
                 WHERE workspace_id = :workspace_id
                   AND status = :removed_status'
            );
            $remapStmt->execute([
                ':fallback_status' => $fallbackKey,
                ':workspace_id' => $workspaceId,
                ':removed_status' => $removeStatusKey,
            ]);

            array_splice($workingList, $removeIndex, 1);
            if ($workingReviewStatusKey === $removeStatusKey) {
                $workingReviewStatusKey = null;
            }
        }
    }

    $newStatusLabel = trim((string) $newStatusLabel);
    if ($newStatusLabel !== '') {
        $normalizedLabel = normalizeWorkspaceTaskStatusLabel($newStatusLabel, 'Novo status');
        $newKey = generateWorkspaceTaskStatusKey(
            array_map(static fn (array $definition): string => (string) ($definition['key'] ?? ''), $workingList),
            $normalizedLabel
        );
        array_splice(
            $workingList,
            max(1, count($workingList) - 1),
            0,
            [[
                'key' => $newKey,
                'label' => $normalizedLabel,
                'color' => normalizeTaskStatusPaletteColor((string) $newStatusColor, 'in_progress'),
            ]]
        );
    }

    $normalizedConfig = normalizeWorkspaceTaskStatusDefinitions($workingList, $workingReviewStatusKey);
    $duplicateColors = workspaceTaskStatusDuplicateColors((array) ($normalizedConfig['list'] ?? []));
    if ($duplicateColors !== []) {
        $palette = taskStatusColorPalette();
        $duplicateLabels = array_map(
            static fn (string $color): string => (string) ($palette[$color] ?? $color),
            $duplicateColors
        );
        throw new RuntimeException(
            'Cada status precisa ter uma cor diferente. Cores repetidas: ' . implode(', ', $duplicateLabels) . '.'
        );
    }

    $updateStmt = $pdo->prepare(
        'UPDATE workspaces
         SET task_statuses_json = :task_statuses_json,
             task_review_status_key = :task_review_status_key,
             updated_at = :updated_at
         WHERE id = :workspace_id'
    );
    $updateStmt->execute([
        ':task_statuses_json' => encodeWorkspaceTaskStatusDefinitions($normalizedConfig['list']),
        ':task_review_status_key' => $normalizedConfig['review_status_key'],
        ':updated_at' => nowIso(),
        ':workspace_id' => $workspaceId,
    ]);

    clearTaskStatusConfigCache($workspaceId);
    return $normalizedConfig;
}

function workspaceSidebarOptionalToolLabels(): array
{
    return [
        'vault' => 'Gerenciador de acessos',
        'dues' => 'Vencimentos',
        'inventory' => 'Estoque',
        'accounting' => 'Contabilidade',
    ];
}

function normalizeWorkspaceSidebarToolKey(string $value): string
{
    $normalized = trim(strtolower($value));
    return array_key_exists($normalized, workspaceSidebarOptionalToolLabels()) ? $normalized : '';
}

function normalizeWorkspaceSidebarTools(array $tools): array
{
    $normalizedTools = [];
    $seenTools = [];
    foreach ($tools as $toolKey) {
        $normalizedKey = normalizeWorkspaceSidebarToolKey((string) $toolKey);
        if ($normalizedKey === '' || isset($seenTools[$normalizedKey])) {
            continue;
        }

        $seenTools[$normalizedKey] = true;
        $normalizedTools[] = $normalizedKey;
    }

    return $normalizedTools;
}

function encodeWorkspaceSidebarTools(array $tools): string
{
    return json_encode(
        normalizeWorkspaceSidebarTools($tools),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: '[]';
}

function workspaceSidebarToolsConfig(?int $workspaceId = null, ?array $workspace = null): array
{
    $workspaceId = $workspaceId && $workspaceId > 0
        ? $workspaceId
        : (int) ($workspace['id'] ?? activeWorkspaceId() ?? 0);

    $enabledOptionalTools = [];
    if ($workspaceId > 0) {
        if (!$workspace || (int) ($workspace['id'] ?? 0) !== $workspaceId) {
            $workspace = workspaceById($workspaceId);
        }

        if ($workspace) {
            $rawJson = trim((string) ($workspace['sidebar_tools_json'] ?? ''));
            if ($rawJson !== '') {
                try {
                    $decoded = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $enabledOptionalTools = normalizeWorkspaceSidebarTools($decoded);
                    }
                } catch (Throwable $e) {
                    $enabledOptionalTools = [];
                }
            }
        }
    }

    $optionalLabels = workspaceSidebarOptionalToolLabels();
    $optionalKeys = array_keys($optionalLabels);
    $availableToAdd = array_values(array_filter(
        $optionalKeys,
        static fn (string $toolKey): bool => !in_array($toolKey, $enabledOptionalTools, true)
    ));

    return [
        'enabled' => array_merge(['tasks'], $enabledOptionalTools),
        'enabled_optional' => $enabledOptionalTools,
        'available_to_add' => $availableToAdd,
        'optional_labels' => $optionalLabels,
    ];
}

function workspaceEnabledDashboardViews(?int $workspaceId = null, ?array $workspace = null, bool $includeUsers = true): array
{
    $sidebarConfig = workspaceSidebarToolsConfig($workspaceId, $workspace);
    $views = ['overview', 'tasks'];
    foreach ((array) ($sidebarConfig['enabled_optional'] ?? []) as $toolView) {
        $normalizedView = normalizeWorkspaceSidebarToolKey((string) $toolView);
        if ($normalizedView === '') {
            continue;
        }

        $views[] = $normalizedView;
    }

    if ($includeUsers) {
        $views[] = 'users';
    }

    return array_values(array_unique($views));
}

function workspaceUpdateSidebarToolsConfiguration(PDO $pdo, int $workspaceId, array $sidebarTools): array
{
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    ensureWorkspaceTaskStatusSchema($pdo);
    $normalizedTools = normalizeWorkspaceSidebarTools($sidebarTools);
    $encodedTools = encodeWorkspaceSidebarTools($normalizedTools);

    $updateStmt = $pdo->prepare(
        'UPDATE workspaces
         SET sidebar_tools_json = :sidebar_tools_json,
             updated_at = :updated_at
         WHERE id = :workspace_id'
    );
    $updateStmt->execute([
        ':sidebar_tools_json' => $encodedTools,
        ':updated_at' => nowIso(),
        ':workspace_id' => $workspaceId,
    ]);

    $workspace = workspaceById($workspaceId);
    if ($workspace !== null) {
        $workspace['sidebar_tools_json'] = $encodedTools;
    }

    return workspaceSidebarToolsConfig($workspaceId, $workspace);
}

function taskStatuses(?int $workspaceId = null, ?array $workspace = null): array
{
    return taskStatusConfig($workspaceId, $workspace)['options'];
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

function taskTitleTagPresets(): array
{
    return [
        'Reels',
        'Story',
        'Captação',
        'Reunião',
    ];
}

function taskTitleTagPalette(): array
{
    return [
        '#6967AE',
        '#D1495B',
        '#F28F3B',
        '#E9C46A',
        '#2A9D8F',
        '#4CC9F0',
        '#4361EE',
        '#3A0CA3',
        '#B5179E',
        '#F72585',
        '#6C757D',
        '#2B9348',
        '#0077B6',
        '#E76F51',
        '#8D99AE',
        '#8338EC',
        '#00B4D8',
        '#588157',
        '#EF476F',
        '#118AB2',
    ];
}

function taskTitleTagDefaultColor(): string
{
    return '#6967AE';
}

function normalizeTaskTitleTagOptionsList(array $values): array
{
    $normalizedOptions = [];
    $seen = [];
    foreach ($values as $value) {
        $normalized = normalizeTaskTitleTag((string) $value);
        if ($normalized === '') {
            continue;
        }

        $key = mb_strtolower($normalized, 'UTF-8');
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $normalizedOptions[] = $normalized;
    }

    return array_values($normalizedOptions);
}

function normalizeTaskStatus(string $value, ?int $workspaceId = null, ?array $workspace = null): string
{
    return (string) (taskStatusMeta($value, $workspaceId, $workspace)['key'] ?? 'todo');
}

function normalizeTaskPriority(string $value): string
{
    return array_key_exists($value, taskPriorities()) ? $value : 'medium';
}

function uppercaseFirstCharacter(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (preg_match('/^(\s*)(.+)$/us', $value, $parts) !== 1) {
        return $value;
    }

    $leading = (string) ($parts[1] ?? '');
    $content = (string) ($parts[2] ?? '');
    if ($content === '') {
        return $value;
    }

    $first = mb_substr($content, 0, 1);
    $rest = mb_substr($content, 1);

    return $leading . mb_strtoupper($first) . $rest;
}

function normalizeTaskTitle(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return uppercaseFirstCharacter($value);
}

function normalizeTaskTitleTag(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (mb_strlen($value) > 40) {
        $value = mb_substr($value, 0, 40);
    }

    return uppercaseFirstCharacter($value);
}

function normalizeTaskTitleTagColor(string $value): string
{
    $normalized = strtoupper(trim($value));
    if (!preg_match('/^#[0-9A-F]{6}$/', $normalized)) {
        return taskTitleTagDefaultColor();
    }

    return in_array($normalized, taskTitleTagPalette(), true)
        ? $normalized
        : taskTitleTagDefaultColor();
}

function normalizeTaskTitleTagColorMap(array $tagColors): array
{
    $normalizedMap = [];
    foreach ($tagColors as $tag => $color) {
        $normalizedTag = normalizeTaskTitleTag((string) $tag);
        if ($normalizedTag === '') {
            continue;
        }

        $normalizedKey = mb_strtolower($normalizedTag, 'UTF-8');
        $normalizedMap[$normalizedKey] = normalizeTaskTitleTagColor((string) $color);
    }

    return $normalizedMap;
}

function taskTitleTagColorsMetaKey(int $workspaceId): string
{
    return 'workspace_' . max(0, $workspaceId) . '_task_title_tag_colors_v1';
}

function taskTitleTagOptionsMetaKey(int $workspaceId): string
{
    return 'workspace_' . max(0, $workspaceId) . '_task_title_tag_options_v1';
}

function taskTitleTagOptionsByWorkspace(int $workspaceId, ?PDO $pdo = null): array
{
    $fallback = normalizeTaskTitleTagOptionsList(taskTitleTagPresets());
    if ($workspaceId <= 0) {
        return $fallback;
    }

    $pdo = $pdo instanceof PDO ? $pdo : db();
    $raw = appMetaGet($pdo, taskTitleTagOptionsMetaKey($workspaceId));
    if (!is_string($raw) || trim($raw) === '') {
        return $fallback;
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $decoded = [];
    }

    if (!is_array($decoded)) {
        return $fallback;
    }

    return normalizeTaskTitleTagOptionsList($decoded);
}

function hasTaskTitleTagOptionsByWorkspace(int $workspaceId, ?PDO $pdo = null): bool
{
    if ($workspaceId <= 0) {
        return false;
    }

    $pdo = $pdo instanceof PDO ? $pdo : db();
    $raw = appMetaGet($pdo, taskTitleTagOptionsMetaKey($workspaceId));
    return is_string($raw) && trim($raw) !== '';
}

function saveTaskTitleTagOptionsByWorkspace(PDO $pdo, int $workspaceId, array $options): void
{
    if ($workspaceId <= 0) {
        return;
    }

    $normalizedOptions = normalizeTaskTitleTagOptionsList($options);
    $encodedOptions = json_encode(
        $normalizedOptions,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if (!is_string($encodedOptions) || $encodedOptions === '') {
        $encodedOptions = '[]';
    }

    appMetaSet(
        $pdo,
        taskTitleTagOptionsMetaKey($workspaceId),
        $encodedOptions
    );
}

function addTaskTitleTagOptionForWorkspace(PDO $pdo, int $workspaceId, string $tag): array
{
    $normalizedTag = normalizeTaskTitleTag($tag);
    if ($workspaceId <= 0 || $normalizedTag === '') {
        return taskTitleTagOptionsByWorkspace($workspaceId, $pdo);
    }

    $options = taskTitleTagOptionsByWorkspace($workspaceId, $pdo);
    $options[] = $normalizedTag;
    $options = normalizeTaskTitleTagOptionsList($options);
    saveTaskTitleTagOptionsByWorkspace($pdo, $workspaceId, $options);

    return $options;
}

function removeTaskTitleTagOptionForWorkspace(PDO $pdo, int $workspaceId, string $tag): array
{
    $normalizedTag = normalizeTaskTitleTag($tag);
    if ($workspaceId <= 0 || $normalizedTag === '') {
        return taskTitleTagOptionsByWorkspace($workspaceId, $pdo);
    }

    $targetKey = mb_strtolower($normalizedTag, 'UTF-8');
    $options = array_values(array_filter(
        taskTitleTagOptionsByWorkspace($workspaceId, $pdo),
        static function ($value) use ($targetKey): bool {
            return mb_strtolower(normalizeTaskTitleTag((string) $value), 'UTF-8') !== $targetKey;
        }
    ));
    $options = normalizeTaskTitleTagOptionsList($options);
    saveTaskTitleTagOptionsByWorkspace($pdo, $workspaceId, $options);

    return $options;
}

function taskTitleTagColorsByWorkspace(int $workspaceId, ?PDO $pdo = null): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    $pdo = $pdo instanceof PDO ? $pdo : db();
    $raw = appMetaGet($pdo, taskTitleTagColorsMetaKey($workspaceId));
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        $decoded = [];
    }

    return is_array($decoded)
        ? normalizeTaskTitleTagColorMap($decoded)
        : [];
}

function saveTaskTitleTagColorsByWorkspace(PDO $pdo, int $workspaceId, array $tagColors): void
{
    if ($workspaceId <= 0) {
        return;
    }

    $normalizedMap = normalizeTaskTitleTagColorMap($tagColors);
    $encodedMap = json_encode(
        $normalizedMap,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if (!is_string($encodedMap) || $encodedMap === '') {
        $encodedMap = '{}';
    }

    appMetaSet(
        $pdo,
        taskTitleTagColorsMetaKey($workspaceId),
        $encodedMap
    );
}

function setTaskTitleTagColorForWorkspace(PDO $pdo, int $workspaceId, string $tag, string $color): array
{
    $normalizedTag = normalizeTaskTitleTag($tag);
    if ($workspaceId <= 0 || $normalizedTag === '') {
        return [];
    }

    $colorMap = taskTitleTagColorsByWorkspace($workspaceId, $pdo);
    $colorMap[mb_strtolower($normalizedTag, 'UTF-8')] = normalizeTaskTitleTagColor($color);
    saveTaskTitleTagColorsByWorkspace($pdo, $workspaceId, $colorMap);

    return $colorMap;
}

function taskTitleTagColorForTag(string $tag, array $tagColors = []): string
{
    $normalizedTag = normalizeTaskTitleTag($tag);
    if ($normalizedTag === '') {
        return taskTitleTagDefaultColor();
    }

    $normalizedMap = normalizeTaskTitleTagColorMap($tagColors);
    $normalizedKey = mb_strtolower($normalizedTag, 'UTF-8');
    if (isset($normalizedMap[$normalizedKey])) {
        return normalizeTaskTitleTagColor((string) $normalizedMap[$normalizedKey]);
    }

    $palette = taskTitleTagPalette();
    if (!$palette) {
        return taskTitleTagDefaultColor();
    }

    $hash = abs((int) crc32($normalizedTag));
    $index = $hash % count($palette);
    return normalizeTaskTitleTagColor((string) ($palette[$index] ?? taskTitleTagDefaultColor()));
}

function normalizeTaskSubtaskTitle(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (mb_strlen($value) > 120) {
        $value = mb_substr($value, 0, 120);
    }

    return uppercaseFirstCharacter($value);
}

function taskSubtasksValueToList($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value)) {
        return [];
    }

    $raw = trim($value);
    if ($raw === '') {
        return [];
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable $e) {
        $lines = preg_split('/\R/u', $raw) ?: [];
        return array_values(array_filter($lines, static fn ($line) => trim((string) $line) !== ''));
    }
}

function normalizeTaskSubtasks($value, int $maxItems = 40, bool $enforceDependency = false): array
{
    $items = [];
    $source = taskSubtasksValueToList($value);

    foreach ($source as $entry) {
        if (count($items) >= $maxItems) {
            break;
        }

        $title = '';
        $done = false;
        if (is_array($entry)) {
            $title = normalizeTaskSubtaskTitle((string) ($entry['title'] ?? $entry['name'] ?? ''));
            $done = !empty($entry['done']) || !empty($entry['completed']) || !empty($entry['checked']);
        } else {
            $title = normalizeTaskSubtaskTitle((string) $entry);
            $done = false;
        }

        if ($title === '') {
            continue;
        }

        $items[] = [
            'title' => $title,
            'done' => $done,
        ];
    }

    if ($enforceDependency) {
        $allowDone = true;
        foreach ($items as &$item) {
            if (!$allowDone) {
                $item['done'] = false;
            }
            if (empty($item['done'])) {
                $allowDone = false;
            }
        }
        unset($item);
    }

    return $items;
}

function decodeTaskSubtasks($value, bool $enforceDependency = false): array
{
    return normalizeTaskSubtasks($value, 40, $enforceDependency);
}

function encodeTaskSubtasks(array $subtasks, bool $enforceDependency = false): string
{
    return json_encode(
        normalizeTaskSubtasks($subtasks, 40, $enforceDependency),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: '[]';
}

function taskSubtasksProgress(array $subtasks, bool $enforceDependency = false): array
{
    $normalized = normalizeTaskSubtasks($subtasks, 40, $enforceDependency);
    $total = count($normalized);
    $completed = 0;
    foreach ($normalized as $item) {
        if (!empty($item['done'])) {
            $completed++;
        }
    }

    $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

    return [
        'total' => $total,
        'completed' => $completed,
        'pending' => max(0, $total - $completed),
        'percent' => max(0, min(100, $percent)),
        'is_complete' => $total > 0 && $completed >= $total,
    ];
}

function applyTaskSubtasksCompletionStatus(
    string $status,
    array $subtasks,
    ?int $workspaceId = null,
    ?array $workspace = null
): string
{
    $normalizedStatus = normalizeTaskStatus($status, $workspaceId, $workspace);
    $progress = taskSubtasksProgress($subtasks, false);
    $statusKind = taskStatusKind($normalizedStatus, $workspaceId, $workspace);

    if ($progress['is_complete'] && !in_array($statusKind, ['review', 'done'], true)) {
        return taskReviewStatusKey($workspaceId, $workspace) ?? taskDoneStatusKey($workspaceId, $workspace);
    }

    return $normalizedStatus;
}

function normalizeVaultEntryLabel(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (mb_strlen($value) > 120) {
        $value = mb_substr($value, 0, 120);
    }

    return uppercaseFirstCharacter($value);
}

function normalizeVaultFieldValue(string $value, int $maxLength): string
{
    $value = trim($value);
    if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
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
    ?string $overdueSinceDate = null,
    ?int $workspaceId = null,
    ?array $workspace = null
): array {
    $status = normalizeTaskStatus($status, $workspaceId, $workspace);
    $priority = normalizeTaskPriority($priority);
    $overdueFlag = $overdueFlag === 1 ? 1 : 0;
    $overdueSinceDate = dueDateForStorage($overdueSinceDate);

    if (taskStatusKind($status, $workspaceId, $workspace) === 'done' || $dueDate === null) {
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

function taskHistoryByTaskIds(array $taskIds, int $limitPerTask = 30): array
{
    $ids = array_values(array_unique(array_map('intval', $taskIds)));
    $ids = array_values(array_filter($ids, static fn (int $id) => $id > 0));
    if (!$ids) {
        return [];
    }

    $limitPerTask = max(1, min($limitPerTask, 300));
    $grouped = [];
    $countsByTaskId = [];
    $pdo = db();
    $chunkSize = 220;

    foreach (array_chunk($ids, $chunkSize) as $taskIdChunk) {
        if (!$taskIdChunk) {
            continue;
        }

        $params = [];
        $placeholders = [];
        foreach (array_values($taskIdChunk) as $index => $taskId) {
            $paramName = ':task_id_' . $index;
            $placeholders[] = $paramName;
            $params[$paramName] = (int) $taskId;
        }

        $sql = 'SELECT
                    h.id,
                    h.task_id,
                    h.event_type,
                    h.payload_json,
                    h.created_at,
                    u.name AS actor_name
                FROM task_history h
                LEFT JOIN users u ON u.id = h.actor_user_id
                WHERE h.task_id IN (' . implode(', ', $placeholders) . ')
                ORDER BY h.task_id ASC, h.created_at DESC, h.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $taskId = (int) ($row['task_id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }

            $currentCount = $countsByTaskId[$taskId] ?? 0;
            if ($currentCount >= $limitPerTask) {
                continue;
            }

            if (!isset($grouped[$taskId])) {
                $grouped[$taskId] = [];
            }

            $row['payload'] = decodeTaskHistoryPayload($row['payload_json'] ?? null);
            unset($row['payload_json']);
            $grouped[$taskId][] = $row;
            $countsByTaskId[$taskId] = $currentCount + 1;
        }
    }

    return $grouped;
}

function taskHasActiveRevisionRequest(?string $description, array $history): bool
{
    $currentDescription = trim((string) $description);
    if ($currentDescription === '') {
        return false;
    }

    $stack = [];
    $orderedEntries = array_reverse($history);
    foreach ($orderedEntries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $eventType = trim((string) ($entry['event_type'] ?? ''));
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];

        if ($eventType === 'revision_requested') {
            $previousDescription = trim((string) ($payload['previous_description'] ?? ''));
            $newDescription = trim((string) ($payload['new_description'] ?? ''));
            if ($previousDescription === '' || $newDescription === '' || $previousDescription === $newDescription) {
                continue;
            }

            $stack[] = [
                'previous_description' => $previousDescription,
                'new_description' => $newDescription,
            ];
            continue;
        }

        if ($eventType !== 'revision_removed') {
            continue;
        }

        $removedDescription = trim((string) ($payload['removed_description'] ?? ''));
        $restoredDescription = trim((string) ($payload['restored_description'] ?? ''));
        if ($removedDescription === '') {
            continue;
        }

        for ($index = count($stack) - 1; $index >= 0; $index--) {
            $candidate = $stack[$index];
            $matchesRemoved = (string) ($candidate['new_description'] ?? '') === $removedDescription;
            $matchesRestored = $restoredDescription === ''
                || (string) ($candidate['previous_description'] ?? '') === $restoredDescription;
            if (!$matchesRemoved || !$matchesRestored) {
                continue;
            }

            array_splice($stack, $index, 1);
            break;
        }
    }

    if (!$stack) {
        return false;
    }

    $latestActiveRevision = $stack[count($stack) - 1];
    return trim((string) ($latestActiveRevision['new_description'] ?? '')) === $currentDescription;
}

function taskNotificationEventTypes(): array
{
    return [
        'created',
        'title_changed',
        'title_tag_changed',
        'status_changed',
        'priority_changed',
        'due_date_changed',
        'group_changed',
        'assignees_changed',
        'subtasks_changed',
        'revision_requested',
        'revision_removed',
        'overdue_started',
        'overdue_cleared',
    ];
}

function taskNotificationMessageFromHistory(array $historyEntry, int $viewerUserId): array
{
    $eventType = trim((string) ($historyEntry['event_type'] ?? ''));
    $payload = is_array($historyEntry['payload'] ?? null) ? $historyEntry['payload'] : [];
    $taskTitle = normalizeTaskTitle((string) ($historyEntry['task_title'] ?? ''));
    if ($taskTitle === '') {
        $taskTitle = 'Tarefa';
    }

    $actorName = normalizeUserDisplayName((string) ($historyEntry['actor_name'] ?? ''));
    $actorPrefix = $actorName !== '' ? $actorName . ' ' : 'Alguem ';

    switch ($eventType) {
        case 'created':
            return [
                'title' => 'Nova tarefa atribuida',
                'message' => $actorPrefix . 'criou a tarefa "' . $taskTitle . '".',
            ];

        case 'assignees_changed':
            $oldAssigneeIds = normalizeAssigneeIds(
                is_array($payload['old'] ?? null) ? $payload['old'] : []
            );
            $newAssigneeIds = normalizeAssigneeIds(
                is_array($payload['new'] ?? null) ? $payload['new'] : []
            );
            $wasAssigned = in_array($viewerUserId, $oldAssigneeIds, true);
            $isAssigned = in_array($viewerUserId, $newAssigneeIds, true);
            if (!$wasAssigned && $isAssigned) {
                return [
                    'title' => 'Voce foi atribuido',
                    'message' => $actorPrefix . 'atribuiu voce a "' . $taskTitle . '".',
                ];
            }

            return [
                'title' => 'Responsaveis atualizados',
                'message' => $actorPrefix . 'atualizou responsaveis em "' . $taskTitle . '".',
            ];

        case 'revision_requested':
            return [
                'title' => 'Solicitacao de revisao',
                'message' => $actorPrefix . 'solicitou ajuste em "' . $taskTitle . '".',
            ];

        case 'revision_removed':
            return [
                'title' => 'Solicitacao de revisao removida',
                'message' => $actorPrefix . 'removeu o ajuste de "' . $taskTitle . '".',
            ];

        case 'overdue_started':
            return [
                'title' => 'Tarefa em atraso',
                'message' => '"' . $taskTitle . '" entrou em atraso.',
            ];

        case 'overdue_cleared':
            return [
                'title' => 'Atraso removido',
                'message' => $actorPrefix . 'removeu o atraso de "' . $taskTitle . '".',
            ];

        case 'status_changed':
            return [
                'title' => 'Status atualizado',
                'message' => $actorPrefix . 'alterou o status de "' . $taskTitle . '".',
            ];

        case 'priority_changed':
            return [
                'title' => 'Prioridade atualizada',
                'message' => $actorPrefix . 'alterou a prioridade de "' . $taskTitle . '".',
            ];

        case 'due_date_changed':
            return [
                'title' => 'Prazo atualizado',
                'message' => $actorPrefix . 'alterou o prazo de "' . $taskTitle . '".',
            ];

        default:
            return [
                'title' => 'Tarefa atualizada',
                'message' => $actorPrefix . 'alterou "' . $taskTitle . '".',
            ];
    }
}

function taskIdsAssignedToUser(int $workspaceId, int $userId): array
{
    if ($workspaceId <= 0 || $userId <= 0) {
        return [];
    }

    $pdo = db();
    $driver = dbDriverName($pdo);

    try {
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare(
                'SELECT DISTINCT t.id
                 FROM tasks t
                 LEFT JOIN LATERAL jsonb_array_elements_text(
                    CASE
                        WHEN t.assignee_ids_json IS NULL OR BTRIM(t.assignee_ids_json) = \'\' THEN \'[]\'::jsonb
                        ELSE t.assignee_ids_json::jsonb
                    END
                 ) assignee(value) ON true
                 WHERE t.workspace_id = :workspace_id
                   AND (
                        t.assigned_to = :user_id
                        OR assignee.value = :user_id_text
                   )'
            );
            $stmt->execute([
                ':workspace_id' => $workspaceId,
                ':user_id' => $userId,
                ':user_id_text' => (string) $userId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT DISTINCT t.id
                 FROM tasks t
                 LEFT JOIN json_each(
                    CASE
                        WHEN t.assignee_ids_json IS NULL OR TRIM(t.assignee_ids_json) = \'\' THEN \'[]\'
                        ELSE t.assignee_ids_json
                    END
                 ) j ON 1 = 1
                 WHERE t.workspace_id = :workspace_id
                   AND (
                        t.assigned_to = :user_id
                        OR CAST(j.value AS INTEGER) = :user_id
                   )'
            );
            $stmt->execute([
                ':workspace_id' => $workspaceId,
                ':user_id' => $userId,
            ]);
        }

        $rows = $stmt->fetchAll();
        $taskIds = [];
        foreach ($rows as $row) {
            $taskId = (int) ($row['id'] ?? 0);
            if ($taskId > 0) {
                $taskIds[$taskId] = $taskId;
            }
        }

        if ($taskIds) {
            return array_values($taskIds);
        }
    } catch (Throwable $e) {
        // Fallback below keeps compatibility for environments without JSON SQL helpers.
    }

    $fallbackStmt = $pdo->prepare(
        'SELECT id, assigned_to, assignee_ids_json
         FROM tasks
         WHERE workspace_id = :workspace_id'
    );
    $fallbackStmt->execute([':workspace_id' => $workspaceId]);
    $rows = $fallbackStmt->fetchAll();

    $taskIds = [];
    foreach ($rows as $row) {
        $taskId = (int) ($row['id'] ?? 0);
        if ($taskId <= 0) {
            continue;
        }

        $assigneeIds = decodeAssigneeIds(
            $row['assignee_ids_json'] ?? null,
            isset($row['assigned_to']) ? (int) $row['assigned_to'] : null
        );

        if (in_array($userId, $assigneeIds, true)) {
            $taskIds[$taskId] = $taskId;
        }
    }

    return array_values($taskIds);
}

function latestTaskHistoryIdForWorkspace(int $workspaceId): int
{
    if ($workspaceId <= 0) {
        return 0;
    }

    $stmt = db()->prepare(
        'SELECT MAX(h.id)
         FROM task_history h
         INNER JOIN tasks t ON t.id = h.task_id
         WHERE t.workspace_id = :workspace_id'
    );
    $stmt->execute([':workspace_id' => $workspaceId]);
    return (int) $stmt->fetchColumn();
}

function taskNotificationsForUser(
    int $workspaceId,
    int $userId,
    int $sinceHistoryId = 0,
    int $limit = 40
): array {
    if ($workspaceId <= 0 || $userId <= 0) {
        return [];
    }

    $taskIds = taskIdsAssignedToUser($workspaceId, $userId);
    if (!$taskIds) {
        return [];
    }

    $sinceHistoryId = max(0, $sinceHistoryId);
    $limit = max(1, min($limit, 100));
    $eventTypes = taskNotificationEventTypes();

    $taskPlaceholders = [];
    $eventPlaceholders = [];
    $params = [
        ':workspace_id' => $workspaceId,
        ':since_history_id' => $sinceHistoryId,
        ':viewer_user_id' => $userId,
    ];

    foreach (array_values($taskIds) as $index => $taskId) {
        $param = ':task_' . $index;
        $taskPlaceholders[] = $param;
        $params[$param] = (int) $taskId;
    }

    foreach (array_values($eventTypes) as $index => $eventType) {
        $param = ':event_' . $index;
        $eventPlaceholders[] = $param;
        $params[$param] = $eventType;
    }

    if (!$taskPlaceholders || !$eventPlaceholders) {
        return [];
    }

    $sql = 'SELECT
                h.id AS history_id,
                h.task_id,
                h.event_type,
                h.payload_json,
                h.created_at,
                h.actor_user_id,
                actor.name AS actor_name,
                t.title AS task_title
            FROM task_history h
            INNER JOIN tasks t ON t.id = h.task_id
            LEFT JOIN users actor ON actor.id = h.actor_user_id
            WHERE t.workspace_id = :workspace_id
              AND h.id > :since_history_id
              AND h.task_id IN (' . implode(', ', $taskPlaceholders) . ')
              AND h.event_type IN (' . implode(', ', $eventPlaceholders) . ')
              AND (h.actor_user_id IS NULL OR h.actor_user_id <> :viewer_user_id)
            ORDER BY h.id ASC
            LIMIT ' . $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $notifications = [];
    foreach ($rows as $row) {
        $payload = decodeTaskHistoryPayload($row['payload_json'] ?? null);
        $entry = [
            'history_id' => (int) ($row['history_id'] ?? 0),
            'task_id' => (int) ($row['task_id'] ?? 0),
            'event_type' => trim((string) ($row['event_type'] ?? '')),
            'payload' => $payload,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'actor_name' => (string) ($row['actor_name'] ?? ''),
            'task_title' => normalizeTaskTitle((string) ($row['task_title'] ?? '')),
        ];

        if ($entry['history_id'] <= 0 || $entry['task_id'] <= 0 || $entry['event_type'] === '') {
            continue;
        }

        $messageParts = taskNotificationMessageFromHistory($entry, $userId);
        $notifications[] = [
            'history_id' => $entry['history_id'],
            'task_id' => $entry['task_id'],
            'event_type' => $entry['event_type'],
            'title' => (string) ($messageParts['title'] ?? 'Notificacao'),
            'message' => (string) ($messageParts['message'] ?? ''),
            'created_at' => $entry['created_at'],
            'actor_name' => $entry['actor_name'],
            'task_title' => $entry['task_title'],
        ];
    }

    return $notifications;
}

function applyOverdueTaskPolicy(?int $workspaceId = null): int
{
    $pdo = db();
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return 0;
    }
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $updatedAt = nowIso();

    $select = $pdo->prepare(
        'SELECT id, status, due_date, overdue_flag, overdue_since_date
         FROM tasks
         WHERE workspace_id = :workspace_id
           AND COALESCE(NULLIF(CAST(due_date AS TEXT), \'\'), \'\') <> \'\'
           AND CAST(due_date AS TEXT) < :today'
    );
    $select->execute([
        ':workspace_id' => $workspaceId,
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
         WHERE id = :id
           AND workspace_id = :workspace_id'
    );

    $changed = 0;
    foreach ($rows as $row) {
        $taskId = (int) ($row['id'] ?? 0);
        if ($taskId <= 0) {
            continue;
        }
        if (taskStatusKind((string) ($row['status'] ?? ''), $workspaceId) === 'done') {
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
            ':workspace_id' => $workspaceId,
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

function overduePolicyLastRunMetaKey(int $workspaceId): string
{
    return sprintf('overdue_policy_last_run_date_workspace_%d', $workspaceId);
}

function applyOverdueTaskPolicyIfNeeded(?int $workspaceId = null): int
{
    $pdo = db();
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return 0;
    }

    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    static $checkedByWorkspaceId = [];
    if (($checkedByWorkspaceId[$workspaceId] ?? null) === $today) {
        return 0;
    }

    $lastRun = dueDateForStorage(appMetaGet($pdo, overduePolicyLastRunMetaKey($workspaceId)));
    if ($lastRun === $today) {
        $checkedByWorkspaceId[$workspaceId] = $today;
        return 0;
    }

    $changed = applyOverdueTaskPolicy($workspaceId);
    appMetaSet($pdo, overduePolicyLastRunMetaKey($workspaceId), $today);
    $checkedByWorkspaceId[$workspaceId] = $today;

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

    return uppercaseFirstCharacter($value);
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

function referenceValueToList($value): array
{
    if (is_string($value)) {
        $raw = trim($value);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $split = preg_split('/\R+/u', $raw);
        return is_array($split) ? $split : [];
    }

    if (!is_array($value)) {
        return [$value];
    }

    return $value;
}

function normalizeHttpReferenceValue(string $value): ?string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    if (mb_strlen($trimmed) > 1000) {
        $trimmed = mb_substr($trimmed, 0, 1000);
    }

    $hasExplicitScheme = preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $trimmed) === 1;
    $candidate = $hasExplicitScheme ? $trimmed : ('https://' . $trimmed);

    $validated = filter_var($candidate, FILTER_VALIDATE_URL);
    if ($validated === false) {
        return null;
    }

    $scheme = strtolower((string) parse_url($validated, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    return $validated;
}

function normalizeReferenceUrlList($value, int $maxItems = 20): array
{
    $result = [];

    foreach (referenceValueToList($value) as $item) {
        $normalized = normalizeHttpReferenceValue((string) $item);
        if ($normalized === null) {
            continue;
        }

        $result[$normalized] = $normalized;
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

function normalizeReferenceImageList($value, int $maxItems = 20, int $maxDataUrlLength = 2000000): array
{
    $result = [];

    foreach (referenceValueToList($value) as $item) {
        $raw = trim((string) $item);
        if ($raw === '') {
            continue;
        }

        if (preg_match('/^data:image\//i', $raw) === 1) {
            $compact = (string) preg_replace('/\s+/u', '', $raw);
            if ($compact === '') {
                continue;
            }
            if (mb_strlen($compact) > $maxDataUrlLength) {
                continue;
            }
            if (preg_match('/^data:image\/[a-z0-9.+-]+;base64,[a-z0-9+\/=]+$/i', $compact) !== 1) {
                continue;
            }

            $result[$compact] = $compact;
        } else {
            $normalizedUrl = normalizeHttpReferenceValue($raw);
            if ($normalizedUrl === null) {
                continue;
            }

            $result[$normalizedUrl] = $normalizedUrl;
        }

        if (count($result) >= $maxItems) {
            break;
        }
    }

    return array_values($result);
}

function encodeReferenceImageList(array $images): string
{
    return json_encode(
        normalizeReferenceImageList($images),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: '[]';
}

function decodeReferenceImageList($value): array
{
    return normalizeReferenceImageList($value);
}

function findTaskGroupByName(string $groupName, ?int $workspaceId = null): ?string
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return null;
    }

    $needle = mb_strtolower(normalizeTaskGroupName($groupName));

    foreach (taskGroupsList($workspaceId) as $existingName) {
        if (mb_strtolower($existingName) === $needle) {
            return $existingName;
        }
    }

    return null;
}

function defaultTaskGroupName(?int $workspaceId = null): string
{
    $pdo = db();
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return 'Geral';
    }

    $rowStmt = $pdo->prepare(
        'SELECT name
         FROM task_groups
         WHERE workspace_id = :workspace_id
         ORDER BY id ASC
         LIMIT 1'
    );
    $rowStmt->execute([':workspace_id' => $workspaceId]);
    $row = $rowStmt->fetch();
    $groupName = trim((string) ($row['name'] ?? ''));
    if ($groupName !== '') {
        return normalizeTaskGroupName($groupName);
    }

    $taskStmt = $pdo->prepare(
        "SELECT group_name
         FROM tasks
         WHERE workspace_id = :workspace_id
           AND group_name IS NOT NULL
           AND group_name <> ''
         ORDER BY id ASC
         LIMIT 1"
    );
    $taskStmt->execute([':workspace_id' => $workspaceId]);
    $taskRow = $taskStmt->fetch();
    $taskGroupName = trim((string) ($taskRow['group_name'] ?? ''));
    if ($taskGroupName !== '') {
        $normalized = normalizeTaskGroupName($taskGroupName);
        upsertTaskGroup($pdo, $normalized, null, $workspaceId);
        return $normalized;
    }

    upsertTaskGroup($pdo, 'Geral', null, $workspaceId);
    return 'Geral';
}

function isProtectedTaskGroupName(string $groupName, ?int $workspaceId = null): bool
{
    return mb_strtolower(normalizeTaskGroupName($groupName)) === mb_strtolower(defaultTaskGroupName($workspaceId));
}

function taskGroupPermissionOverridesForUser(int $workspaceId, int $userId): array
{
    if ($workspaceId <= 0 || $userId <= 0) {
        return [];
    }

    $role = workspaceRoleForUser($userId, $workspaceId);
    if ($role === null) {
        return [];
    }

    static $cache = [];
    $cacheKey = $workspaceId . ':' . $userId;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = db()->prepare(
        'SELECT group_name, can_view, can_access
         FROM task_group_permissions
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $groupKey = mb_strtolower(normalizeTaskGroupName((string) ($row['group_name'] ?? 'Geral')));
        $canView = normalizePermissionFlag($row['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($row['can_access'] ?? 1) : 0;
        $map[$groupKey] = [
            'can_view' => $canView === 1,
            'can_access' => $canAccess === 1,
        ];
    }

    $cache[$cacheKey] = $map;
    return $map;
}

function vaultGroupPermissionOverridesForUser(int $workspaceId, int $userId): array
{
    if ($workspaceId <= 0 || $userId <= 0) {
        return [];
    }

    $role = workspaceRoleForUser($userId, $workspaceId);
    if ($role === null || $role === 'admin') {
        return [];
    }

    static $cache = [];
    $cacheKey = $workspaceId . ':' . $userId;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = db()->prepare(
        'SELECT group_name, can_view, can_access
         FROM workspace_vault_group_permissions
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $groupKey = mb_strtolower(normalizeVaultGroupName((string) ($row['group_name'] ?? 'Geral')));
        $canView = normalizePermissionFlag($row['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($row['can_access'] ?? 1) : 0;
        $map[$groupKey] = [
            'can_view' => $canView === 1,
            'can_access' => $canAccess === 1,
        ];
    }

    $cache[$cacheKey] = $map;
    return $map;
}

function dueGroupPermissionOverridesForUser(int $workspaceId, int $userId): array
{
    if ($workspaceId <= 0 || $userId <= 0) {
        return [];
    }

    $role = workspaceRoleForUser($userId, $workspaceId);
    if ($role === null || $role === 'admin') {
        return [];
    }

    static $cache = [];
    $cacheKey = $workspaceId . ':' . $userId;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $stmt = db()->prepare(
        'SELECT group_name, can_view, can_access
         FROM workspace_due_group_permissions
         WHERE workspace_id = :workspace_id
           AND user_id = :user_id'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':user_id' => $userId,
    ]);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $groupKey = mb_strtolower(normalizeDueGroupName((string) ($row['group_name'] ?? 'Geral')));
        $canView = normalizePermissionFlag($row['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($row['can_access'] ?? 1) : 0;
        $map[$groupKey] = [
            'can_view' => $canView === 1,
            'can_access' => $canAccess === 1,
        ];
    }

    $cache[$cacheKey] = $map;
    return $map;
}

function taskGroupPermissionForUser(int $workspaceId, string $groupName, int $userId): array
{
    if ($workspaceId <= 0 || $userId <= 0) {
        return ['can_view' => false, 'can_access' => false];
    }

    $role = workspaceRoleForUser($userId, $workspaceId);
    if ($role === null) {
        return ['can_view' => false, 'can_access' => false];
    }

    $groupKey = mb_strtolower(normalizeTaskGroupName($groupName));
    $overrides = taskGroupPermissionOverridesForUser($workspaceId, $userId);
    $permission = $overrides[$groupKey] ?? ['can_view' => true, 'can_access' => true];
    $canView = !empty($permission['can_view']);
    $canAccess = $canView && !empty($permission['can_access']);

    return ['can_view' => $canView, 'can_access' => $canAccess];
}

function vaultGroupPermissionForUser(int $workspaceId, string $groupName, int $userId): array
{
    if ($workspaceId <= 0 || $userId <= 0) {
        return ['can_view' => false, 'can_access' => false];
    }

    $role = workspaceRoleForUser($userId, $workspaceId);
    if ($role === null) {
        return ['can_view' => false, 'can_access' => false];
    }
    if ($role === 'admin') {
        return ['can_view' => true, 'can_access' => true];
    }

    $groupKey = mb_strtolower(normalizeVaultGroupName($groupName));
    $overrides = vaultGroupPermissionOverridesForUser($workspaceId, $userId);
    $permission = $overrides[$groupKey] ?? ['can_view' => true, 'can_access' => true];
    $canView = !empty($permission['can_view']);
    $canAccess = $canView && !empty($permission['can_access']);

    return ['can_view' => $canView, 'can_access' => $canAccess];
}

function dueGroupPermissionForUser(int $workspaceId, string $groupName, int $userId): array
{
    if ($workspaceId <= 0 || $userId <= 0) {
        return ['can_view' => false, 'can_access' => false];
    }

    $role = workspaceRoleForUser($userId, $workspaceId);
    if ($role === null) {
        return ['can_view' => false, 'can_access' => false];
    }
    if ($role === 'admin') {
        return ['can_view' => true, 'can_access' => true];
    }

    $groupKey = mb_strtolower(normalizeDueGroupName($groupName));
    $overrides = dueGroupPermissionOverridesForUser($workspaceId, $userId);
    $permission = $overrides[$groupKey] ?? ['can_view' => true, 'can_access' => true];
    $canView = !empty($permission['can_view']);
    $canAccess = $canView && !empty($permission['can_access']);

    return ['can_view' => $canView, 'can_access' => $canAccess];
}

function userCanViewTaskGroup(int $userId, int $workspaceId, string $groupName): bool
{
    return taskGroupPermissionForUser($workspaceId, $groupName, $userId)['can_view'];
}

function userCanAccessTaskGroup(int $userId, int $workspaceId, string $groupName): bool
{
    return taskGroupPermissionForUser($workspaceId, $groupName, $userId)['can_access'];
}

function userCanViewVaultGroup(int $userId, int $workspaceId, string $groupName): bool
{
    return vaultGroupPermissionForUser($workspaceId, $groupName, $userId)['can_view'];
}

function userCanAccessVaultGroup(int $userId, int $workspaceId, string $groupName): bool
{
    return vaultGroupPermissionForUser($workspaceId, $groupName, $userId)['can_access'];
}

function userCanViewDueGroup(int $userId, int $workspaceId, string $groupName): bool
{
    return dueGroupPermissionForUser($workspaceId, $groupName, $userId)['can_view'];
}

function userCanAccessDueGroup(int $userId, int $workspaceId, string $groupName): bool
{
    return dueGroupPermissionForUser($workspaceId, $groupName, $userId)['can_access'];
}

function taskGroupPermissionsByUser(int $workspaceId, string $groupName): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    $groupName = normalizeTaskGroupName($groupName);
    $stmt = db()->prepare(
        'SELECT user_id, can_view, can_access
         FROM task_group_permissions
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:group_name))'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':group_name' => $groupName,
    ]);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        $canView = normalizePermissionFlag($row['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($row['can_access'] ?? 1) : 0;
        $map[$userId] = [
            'can_view' => $canView === 1,
            'can_access' => $canAccess === 1,
        ];
    }

    return $map;
}

function vaultGroupPermissionsByUser(int $workspaceId, string $groupName): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    $groupName = normalizeVaultGroupName($groupName);
    $stmt = db()->prepare(
        'SELECT user_id, can_view, can_access
         FROM workspace_vault_group_permissions
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:group_name))'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':group_name' => $groupName,
    ]);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        $canView = normalizePermissionFlag($row['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($row['can_access'] ?? 1) : 0;
        $map[$userId] = [
            'can_view' => $canView === 1,
            'can_access' => $canAccess === 1,
        ];
    }

    return $map;
}

function dueGroupPermissionsByUser(int $workspaceId, string $groupName): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    $groupName = normalizeDueGroupName($groupName);
    $stmt = db()->prepare(
        'SELECT user_id, can_view, can_access
         FROM workspace_due_group_permissions
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:group_name))'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':group_name' => $groupName,
    ]);
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $row) {
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }
        $canView = normalizePermissionFlag($row['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($row['can_access'] ?? 1) : 0;
        $map[$userId] = [
            'can_view' => $canView === 1,
            'can_access' => $canAccess === 1,
        ];
    }

    return $map;
}

function taskGroupPermissionsByUserMapByGroup(int $workspaceId): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    static $cache = [];
    if (array_key_exists($workspaceId, $cache)) {
        return $cache[$workspaceId];
    }

    $stmt = db()->prepare(
        'SELECT group_name, user_id, can_view, can_access
         FROM task_group_permissions
         WHERE workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
    ]);
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $groupName = normalizeTaskGroupName((string) ($row['group_name'] ?? 'Geral'));
        $canView = normalizePermissionFlag($row['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($row['can_access'] ?? 1) : 0;

        if (!isset($grouped[$groupName])) {
            $grouped[$groupName] = [];
        }
        $grouped[$groupName][$userId] = [
            'can_view' => $canView === 1,
            'can_access' => $canAccess === 1,
        ];
    }

    $cache[$workspaceId] = $grouped;
    return $grouped;
}

function vaultGroupPermissionsByUserMapByGroup(int $workspaceId): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    static $cache = [];
    if (array_key_exists($workspaceId, $cache)) {
        return $cache[$workspaceId];
    }

    $stmt = db()->prepare(
        'SELECT group_name, user_id, can_view, can_access
         FROM workspace_vault_group_permissions
         WHERE workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
    ]);
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $groupName = normalizeVaultGroupName((string) ($row['group_name'] ?? 'Geral'));
        $canView = normalizePermissionFlag($row['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($row['can_access'] ?? 1) : 0;

        if (!isset($grouped[$groupName])) {
            $grouped[$groupName] = [];
        }
        $grouped[$groupName][$userId] = [
            'can_view' => $canView === 1,
            'can_access' => $canAccess === 1,
        ];
    }

    $cache[$workspaceId] = $grouped;
    return $grouped;
}

function dueGroupPermissionsByUserMapByGroup(int $workspaceId): array
{
    if ($workspaceId <= 0) {
        return [];
    }

    static $cache = [];
    if (array_key_exists($workspaceId, $cache)) {
        return $cache[$workspaceId];
    }

    $stmt = db()->prepare(
        'SELECT group_name, user_id, can_view, can_access
         FROM workspace_due_group_permissions
         WHERE workspace_id = :workspace_id'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
    ]);
    $rows = $stmt->fetchAll();

    $grouped = [];
    foreach ($rows as $row) {
        $userId = (int) ($row['user_id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $groupName = normalizeDueGroupName((string) ($row['group_name'] ?? 'Geral'));
        $canView = normalizePermissionFlag($row['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($row['can_access'] ?? 1) : 0;

        if (!isset($grouped[$groupName])) {
            $grouped[$groupName] = [];
        }
        $grouped[$groupName][$userId] = [
            'can_view' => $canView === 1,
            'can_access' => $canAccess === 1,
        ];
    }

    $cache[$workspaceId] = $grouped;
    return $grouped;
}

function saveTaskGroupPermissions(
    PDO $pdo,
    int $workspaceId,
    string $groupName,
    array $permissionsByUserId,
    array $workspaceRolesByUserId
): void {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    $groupName = normalizeTaskGroupName($groupName);

    $deleteStmt = $pdo->prepare(
        'DELETE FROM task_group_permissions
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:group_name))'
    );
    $deleteStmt->execute([
        ':workspace_id' => $workspaceId,
        ':group_name' => $groupName,
    ]);

    $insertStmt = $pdo->prepare(
        'INSERT INTO task_group_permissions (workspace_id, group_name, user_id, can_view, can_access, updated_at)
         VALUES (:workspace_id, :group_name, :user_id, :can_view, :can_access, :updated_at)'
    );
    $updatedAt = nowIso();

    foreach ($permissionsByUserId as $rawUserId => $permissionRow) {
        $userId = (int) $rawUserId;
        if ($userId <= 0 || !isset($workspaceRolesByUserId[$userId])) {
            continue;
        }

        $canView = normalizePermissionFlag($permissionRow['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($permissionRow['can_access'] ?? 1) : 0;

        if ($canView === 1 && $canAccess === 1) {
            continue;
        }

        $insertStmt->execute([
            ':workspace_id' => $workspaceId,
            ':group_name' => $groupName,
            ':user_id' => $userId,
            ':can_view' => $canView,
            ':can_access' => $canAccess,
            ':updated_at' => $updatedAt,
        ]);
    }
}

function saveVaultGroupPermissions(
    PDO $pdo,
    int $workspaceId,
    string $groupName,
    array $permissionsByUserId,
    array $workspaceRolesByUserId
): void {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    $groupName = normalizeVaultGroupName($groupName);

    $deleteStmt = $pdo->prepare(
        'DELETE FROM workspace_vault_group_permissions
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:group_name))'
    );
    $deleteStmt->execute([
        ':workspace_id' => $workspaceId,
        ':group_name' => $groupName,
    ]);

    $insertStmt = $pdo->prepare(
        'INSERT INTO workspace_vault_group_permissions (workspace_id, group_name, user_id, can_view, can_access, updated_at)
         VALUES (:workspace_id, :group_name, :user_id, :can_view, :can_access, :updated_at)'
    );
    $updatedAt = nowIso();

    foreach ($permissionsByUserId as $rawUserId => $permissionRow) {
        $userId = (int) $rawUserId;
        if ($userId <= 0 || !isset($workspaceRolesByUserId[$userId])) {
            continue;
        }

        $role = normalizeWorkspaceRole((string) $workspaceRolesByUserId[$userId]);
        if ($role === 'admin') {
            continue;
        }

        $canView = normalizePermissionFlag($permissionRow['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($permissionRow['can_access'] ?? 1) : 0;

        if ($canView === 1 && $canAccess === 1) {
            continue;
        }

        $insertStmt->execute([
            ':workspace_id' => $workspaceId,
            ':group_name' => $groupName,
            ':user_id' => $userId,
            ':can_view' => $canView,
            ':can_access' => $canAccess,
            ':updated_at' => $updatedAt,
        ]);
    }
}

function saveDueGroupPermissions(
    PDO $pdo,
    int $workspaceId,
    string $groupName,
    array $permissionsByUserId,
    array $workspaceRolesByUserId
): void {
    if ($workspaceId <= 0) {
        throw new RuntimeException('Workspace invalido.');
    }

    $groupName = normalizeDueGroupName($groupName);

    $deleteStmt = $pdo->prepare(
        'DELETE FROM workspace_due_group_permissions
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:group_name))'
    );
    $deleteStmt->execute([
        ':workspace_id' => $workspaceId,
        ':group_name' => $groupName,
    ]);

    $insertStmt = $pdo->prepare(
        'INSERT INTO workspace_due_group_permissions (workspace_id, group_name, user_id, can_view, can_access, updated_at)
         VALUES (:workspace_id, :group_name, :user_id, :can_view, :can_access, :updated_at)'
    );
    $updatedAt = nowIso();

    foreach ($permissionsByUserId as $rawUserId => $permissionRow) {
        $userId = (int) $rawUserId;
        if ($userId <= 0 || !isset($workspaceRolesByUserId[$userId])) {
            continue;
        }

        $role = normalizeWorkspaceRole((string) $workspaceRolesByUserId[$userId]);
        if ($role === 'admin') {
            continue;
        }

        $canView = normalizePermissionFlag($permissionRow['can_view'] ?? 1);
        $canAccess = $canView === 1 ? normalizePermissionFlag($permissionRow['can_access'] ?? 1) : 0;

        if ($canView === 1 && $canAccess === 1) {
            continue;
        }

        $insertStmt->execute([
            ':workspace_id' => $workspaceId,
            ':group_name' => $groupName,
            ':user_id' => $userId,
            ':can_view' => $canView,
            ':can_access' => $canAccess,
            ':updated_at' => $updatedAt,
        ]);
    }
}

function renameTaskGroupPermissions(PDO $pdo, int $workspaceId, string $oldGroupName, string $newGroupName): void
{
    if ($workspaceId <= 0) {
        return;
    }

    $oldGroupName = normalizeTaskGroupName($oldGroupName);
    $newGroupName = normalizeTaskGroupName($newGroupName);
    if (mb_strtolower($oldGroupName) === mb_strtolower($newGroupName)) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE task_group_permissions
         SET group_name = :new_group_name,
             updated_at = :updated_at
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:old_group_name))'
    );
    $stmt->execute([
        ':new_group_name' => $newGroupName,
        ':updated_at' => nowIso(),
        ':workspace_id' => $workspaceId,
        ':old_group_name' => $oldGroupName,
    ]);
}

function renameVaultGroupPermissions(PDO $pdo, int $workspaceId, string $oldGroupName, string $newGroupName): void
{
    if ($workspaceId <= 0) {
        return;
    }

    $oldGroupName = normalizeVaultGroupName($oldGroupName);
    $newGroupName = normalizeVaultGroupName($newGroupName);
    if (mb_strtolower($oldGroupName) === mb_strtolower($newGroupName)) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE workspace_vault_group_permissions
         SET group_name = :new_group_name,
             updated_at = :updated_at
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:old_group_name))'
    );
    $stmt->execute([
        ':new_group_name' => $newGroupName,
        ':updated_at' => nowIso(),
        ':workspace_id' => $workspaceId,
        ':old_group_name' => $oldGroupName,
    ]);
}

function renameDueGroupPermissions(PDO $pdo, int $workspaceId, string $oldGroupName, string $newGroupName): void
{
    if ($workspaceId <= 0) {
        return;
    }

    $oldGroupName = normalizeDueGroupName($oldGroupName);
    $newGroupName = normalizeDueGroupName($newGroupName);
    if (mb_strtolower($oldGroupName) === mb_strtolower($newGroupName)) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE workspace_due_group_permissions
         SET group_name = :new_group_name,
             updated_at = :updated_at
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:old_group_name))'
    );
    $stmt->execute([
        ':new_group_name' => $newGroupName,
        ':updated_at' => nowIso(),
        ':workspace_id' => $workspaceId,
        ':old_group_name' => $oldGroupName,
    ]);
}

function deleteTaskGroupPermissions(PDO $pdo, int $workspaceId, string $groupName): void
{
    if ($workspaceId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM task_group_permissions
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:group_name))'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':group_name' => normalizeTaskGroupName($groupName),
    ]);
}

function deleteVaultGroupPermissions(PDO $pdo, int $workspaceId, string $groupName): void
{
    if ($workspaceId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM workspace_vault_group_permissions
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:group_name))'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':group_name' => normalizeVaultGroupName($groupName),
    ]);
}

function deleteDueGroupPermissions(PDO $pdo, int $workspaceId, string $groupName): void
{
    if ($workspaceId <= 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM workspace_due_group_permissions
         WHERE workspace_id = :workspace_id
           AND LOWER(TRIM(group_name)) = LOWER(TRIM(:group_name))'
    );
    $stmt->execute([
        ':workspace_id' => $workspaceId,
        ':group_name' => normalizeDueGroupName($groupName),
    ]);
}

function upsertTaskGroup(PDO $pdo, string $groupName, ?int $createdBy = null, ?int $workspaceId = null): string
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        throw new RuntimeException('Workspace ativo nao encontrado para salvar grupo.');
    }

    $normalizedName = normalizeTaskGroupName($groupName);
    $now = nowIso();
    $driver = dbDriverName($pdo);

    if ($driver === 'pgsql') {
        $stmt = $pdo->prepare(
            'INSERT INTO task_groups (workspace_id, name, created_by, created_at)
             VALUES (:workspace_id, :name, :created_by, :created_at)
             ON CONFLICT (workspace_id, name) DO NOTHING'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO task_groups (workspace_id, name, created_by, created_at)
             VALUES (:workspace_id, :name, :created_by, :created_at)'
        );
    }

    $stmt->bindValue(':workspace_id', $workspaceId, PDO::PARAM_INT);
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

function taskGroupsList(?int $workspaceId = null): array
{
    $pdo = db();
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return ['Geral'];
    }

    $groups = [];

    $storedStmt = $pdo->prepare(
        'SELECT name
         FROM task_groups
         WHERE workspace_id = :workspace_id
         ORDER BY name ASC'
    );
    $storedStmt->execute([':workspace_id' => $workspaceId]);
    $storedRows = $storedStmt->fetchAll();
    foreach ($storedRows as $row) {
        $groupName = normalizeTaskGroupName((string) ($row['name'] ?? 'Geral'));
        $groups[$groupName] = $groupName;
    }

    $rowsStmt = $pdo->prepare(
        'SELECT DISTINCT group_name
         FROM tasks
         WHERE workspace_id = :workspace_id
           AND group_name IS NOT NULL
           AND group_name <> \'\'
         ORDER BY group_name ASC'
    );
    $rowsStmt->execute([':workspace_id' => $workspaceId]);
    $rows = $rowsStmt->fetchAll();

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

function allTasks(?int $workspaceId = null): array
{
    $workspaceId = $workspaceId && $workspaceId > 0 ? $workspaceId : activeWorkspaceId();
    if ($workspaceId === null) {
        return [];
    }

    $sql = 'SELECT
                t.*,
                creator.name AS creator_name,
                creator.email AS creator_email
            FROM tasks t
            INNER JOIN users creator ON creator.id = t.created_by
            WHERE t.workspace_id = :workspace_id';

    $pdo = db();
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':workspace_id' => $workspaceId]);
    $tasks = $stmt->fetchAll();
    $statusConfig = taskStatusConfig($workspaceId);
    $usersById = usersMapById($workspaceId);
    $historyByTaskId = taskHistoryByTaskIds(
        array_map(static fn ($task) => (int) ($task['id'] ?? 0), $tasks),
        24
    );

    foreach ($tasks as &$task) {
        $task['title'] = normalizeTaskTitle((string) ($task['title'] ?? ''));
        $task['title_tag'] = normalizeTaskTitleTag((string) ($task['title_tag'] ?? ''));
        $task['status'] = normalizeTaskStatus((string) ($task['status'] ?? 'todo'), $workspaceId);
        $taskStatusMeta = $statusConfig['meta_by_key'][$task['status']] ?? taskStatusMeta($task['status'], $workspaceId);
        $task['status_label'] = (string) ($taskStatusMeta['label'] ?? $task['status']);
        $task['status_kind'] = (string) ($taskStatusMeta['kind'] ?? 'todo');
        $task['status_order'] = (int) ($taskStatusMeta['order'] ?? 1);
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
        $task['reference_images'] = decodeReferenceImageList($task['reference_images_json'] ?? null);
        $task['subtasks_dependency_enabled'] = normalizePermissionFlag($task['subtasks_dependency_enabled'] ?? 0);
        $task['subtasks'] = decodeTaskSubtasks(
            $task['subtasks_json'] ?? null,
            ((int) $task['subtasks_dependency_enabled']) === 1
        );
        $task['subtasks_progress'] = taskSubtasksProgress(
            $task['subtasks'],
            ((int) $task['subtasks_dependency_enabled']) === 1
        );
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
                        'status' => normalizeTaskStatus((string) ($task['status'] ?? 'todo'), $workspaceId),
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

    usort(
        $tasks,
        static function (array $left, array $right): int {
            $leftGroup = normalizeTaskGroupName((string) ($left['group_name'] ?? 'Geral'));
            $rightGroup = normalizeTaskGroupName((string) ($right['group_name'] ?? 'Geral'));
            $groupCompare = strnatcasecmp($leftGroup, $rightGroup);
            if ($groupCompare !== 0) {
                return $groupCompare;
            }

            $leftStatusOrder = (int) ($left['status_order'] ?? 99);
            $rightStatusOrder = (int) ($right['status_order'] ?? 99);
            if ($leftStatusOrder !== $rightStatusOrder) {
                return $leftStatusOrder <=> $rightStatusOrder;
            }

            $leftPriorityOrder = taskPriorityOrder((string) ($left['priority'] ?? 'medium'));
            $rightPriorityOrder = taskPriorityOrder((string) ($right['priority'] ?? 'medium'));
            if ($leftPriorityOrder !== $rightPriorityOrder) {
                return $leftPriorityOrder <=> $rightPriorityOrder;
            }

            $leftDueDate = dueDateForStorage((string) ($left['due_date'] ?? ''));
            $rightDueDate = dueDateForStorage((string) ($right['due_date'] ?? ''));
            if ($leftDueDate === null && $rightDueDate !== null) {
                return 1;
            }
            if ($leftDueDate !== null && $rightDueDate === null) {
                return -1;
            }
            if ($leftDueDate !== null && $rightDueDate !== null && $leftDueDate !== $rightDueDate) {
                return strcmp($leftDueDate, $rightDueDate);
            }

            return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
        }
    );

    return $tasks;
}

function tasksByStatus(array $tasks, ?int $workspaceId = null, ?array $workspace = null): array
{
    $grouped = [];
    foreach (array_keys(taskStatuses($workspaceId, $workspace)) as $status) {
        $grouped[$status] = [];
    }

    foreach ($tasks as $task) {
        $status = normalizeTaskStatus((string) ($task['status'] ?? ''), $workspaceId, $workspace);
        $grouped[$status][] = $task;
    }

    return $grouped;
}

function filterTasks(
    array $tasks,
    ?string $groupFilter,
    ?int $creatorFilterId,
    ?int $assigneeFilterId = null
): array
{
    $groupFilter = $groupFilter ? normalizeTaskGroupName($groupFilter) : null;
    $creatorFilterId = $creatorFilterId && $creatorFilterId > 0 ? $creatorFilterId : null;
    $assigneeFilterId = $assigneeFilterId && $assigneeFilterId > 0 ? $assigneeFilterId : null;

    if ($groupFilter === null && $creatorFilterId === null && $assigneeFilterId === null) {
        return $tasks;
    }

    $filtered = [];

    foreach ($tasks as $task) {
        $taskGroup = normalizeTaskGroupName((string) ($task['group_name'] ?? 'Geral'));
        if ($groupFilter !== null && $taskGroup !== $groupFilter) {
            continue;
        }

        if ($creatorFilterId !== null) {
            $taskCreatorId = isset($task['created_by']) ? (int) $task['created_by'] : null;
            if ($taskCreatorId !== $creatorFilterId) {
                continue;
            }
        }

        if ($assigneeFilterId !== null) {
            $taskAssigneeIds = $task['assignee_ids'] ?? [];
            if (!is_array($taskAssigneeIds)) {
                $taskAssigneeIds = [];
            }
            $taskAssigneeIds = array_values(array_unique(array_map('intval', $taskAssigneeIds)));
            if (!$taskAssigneeIds && isset($task['assigned_to'])) {
                $assignedToId = (int) $task['assigned_to'];
                if ($assignedToId > 0) {
                    $taskAssigneeIds[] = $assignedToId;
                }
            }
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
    $fullLabel = taskHumanDateLabel($date);
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

function taskHumanDateLabel(DateTimeImmutable $date, ?DateTimeImmutable $referenceDate = null): string
{
    $referenceDate = $referenceDate instanceof DateTimeImmutable
        ? $referenceDate
        : new DateTimeImmutable('today');

    $monthNames = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro',
    ];

    $day = (int) $date->format('j');
    $monthNumber = (int) $date->format('n');
    $monthLabel = $monthNames[$monthNumber] ?? $date->format('m');
    $label = $day . ' de ' . $monthLabel;

    if ($date->format('Y') !== $referenceDate->format('Y')) {
        $label .= ' de ' . $date->format('Y');
    }

    return $label;
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
        if (taskDoneStatusFromTask($task)) {
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
        if (in_array($userId, $taskAssigneeIds, true) && !taskDoneStatusFromTask($task)) {
            $count++;
        }
    }
    return $count;
}
