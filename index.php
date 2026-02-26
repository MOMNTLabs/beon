<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();

function requestExpectsJson(): bool
{
    $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    if ($xhr === 'xmlhttprequest') {
        return true;
    }

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    return str_contains($accept, 'application/json');
}

function respondJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function dashboardSummaryPayloadForUser(int $userId): array
{
    $allTasks = allTasks();
    $stats = dashboardStats($allTasks);
    $myOpenTasks = countMyAssignedTasks($allTasks, $userId);
    $completionRate = $stats['total'] > 0 ? (int) round(($stats['done'] / $stats['total']) * 100) : 0;

    return [
        'total' => (int) $stats['total'],
        'done' => (int) $stats['done'],
        'completion_rate' => $completionRate,
        'due_today' => (int) $stats['due_today'],
        'urgent' => (int) $stats['urgent'],
        'my_open' => (int) $myOpenTasks,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        verifyCsrf();

        switch ($action) {
            case 'register':
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $password = (string) ($_POST['password'] ?? '');
                $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

                if ($name === '' || $email === '' || $password === '') {
                    throw new RuntimeException('Preencha nome, e-mail e senha.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Informe um e-mail válido.');
                }
                if (mb_strlen($password) < 6) {
                    throw new RuntimeException('A senha deve ter pelo menos 6 caracteres.');
                }
                if ($password !== $passwordConfirm) {
                    throw new RuntimeException('A confirmação de senha não confere.');
                }

                $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $check->execute([':email' => $email]);
                if ($check->fetch()) {
                    throw new RuntimeException('Este e-mail já está cadastrado.');
                }

                $newUserId = createUser(
                    $pdo,
                    $name,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    nowIso()
                );
                loginUser($newUserId, true);
                flash('success', 'Conta criada com sucesso.');
                redirectTo('index.php');

            case 'login':
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                $password = (string) ($_POST['password'] ?? '');
                if ($email === '' || $password === '') {
                    throw new RuntimeException('Informe e-mail e senha.');
                }

                $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $userRow = $stmt->fetch();
                if (!$userRow || !password_verify($password, (string) $userRow['password_hash'])) {
                    throw new RuntimeException('Credenciais inválidas.');
                }

                loginUser((int) $userRow['id'], true);
                flash('success', 'Login realizado.');
                redirectTo('index.php');

            case 'logout':
                logoutUser();
                flash('success', 'Sessão encerrada.');
                redirectTo('index.php');

            case 'create_group':
                $authUser = requireAuth();
                $groupName = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));

                if (findTaskGroupByName($groupName) !== null) {
                    throw new RuntimeException('Este grupo jÃ¡ existe.');
                }

                upsertTaskGroup($pdo, $groupName, (int) $authUser['id']);
                flash('success', 'Grupo criado.');
                redirectTo('index.php#tasks');

            case 'delete_group':
                requireAuth();
                $groupName = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findTaskGroupByName($groupName);

                if ($existingGroupName === null) {
                    throw new RuntimeException('Grupo nao encontrado.');
                }

                if (mb_strtolower($existingGroupName) === 'geral') {
                    throw new RuntimeException('O grupo Geral nao pode ser removido.');
                }

                $countStmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE group_name = :group_name');
                $countStmt->execute([':group_name' => $existingGroupName]);
                $taskCount = (int) $countStmt->fetchColumn();

                if ($taskCount > 0) {
                    throw new RuntimeException('So e possivel excluir grupos vazios.');
                }

                $deleteStmt = $pdo->prepare('DELETE FROM task_groups WHERE name = :name');
                $deleteStmt->execute([':name' => $existingGroupName]);

                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'group_name' => $existingGroupName,
                    ]);
                }

                flash('success', 'Grupo removido.');
                redirectTo('index.php#tasks');

            case 'create_task':
            case 'update_task':
                $authUser = requireAuth();
                $isAutosave = $action === 'update_task' && (string) ($_POST['autosave'] ?? '') === '1';
                $usersById = usersMapById();
                $taskId = (int) ($_POST['task_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $status = normalizeTaskStatus((string) ($_POST['status'] ?? 'todo'));
                $priority = normalizeTaskPriority((string) ($_POST['priority'] ?? 'medium'));
                $dueDate = dueDateForStorage($_POST['due_date'] ?? null);
                if ($action === 'create_task' && $dueDate === null) {
                    $dueDate = (new DateTimeImmutable('today'))->format('Y-m-d');
                }
                $groupName = normalizeTaskGroupName((string) ($_POST['group_name'] ?? ''));
                $existingGroupName = findTaskGroupByName($groupName);
                if ($existingGroupName !== null) {
                    $groupName = $existingGroupName;
                }
                $rawAssigneeValues = $_POST['assigned_to'] ?? [];
                if (!is_array($rawAssigneeValues)) {
                    $rawAssigneeValues = [$rawAssigneeValues];
                }
                $submittedAssigneeIds = normalizeAssigneeIds($rawAssigneeValues);
                $assigneeIds = normalizeAssigneeIds($rawAssigneeValues, $usersById);
                $assignedTo = $assigneeIds[0] ?? null;
                $assigneeIdsJson = encodeAssigneeIds($assigneeIds);
                upsertTaskGroup($pdo, $groupName, (int) $authUser['id']);

                if ($title === '') {
                    throw new RuntimeException('O título da tarefa é obrigatório.');
                }
                if (mb_strlen($title) > 140) {
                    throw new RuntimeException('O título deve ter no máximo 140 caracteres.');
                }
                if (count($submittedAssigneeIds) !== count($assigneeIds)) {
                    throw new RuntimeException('Um ou mais responsáveis selecionados são inválidos.');
                }

                if ($action === 'create_task') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO tasks (title, description, status, priority, due_date, created_by, assigned_to, assignee_ids_json, group_name, created_at, updated_at)
                         VALUES (:t, :d, :s, :p, :dd, :cb, :at, :aj, :g, :c, :u)'
                    );
                    $now = nowIso();
                    $stmt->execute([
                        ':t' => $title,
                        ':d' => $description,
                        ':s' => $status,
                        ':p' => $priority,
                        ':dd' => $dueDate,
                        ':cb' => (int) $authUser['id'],
                        ':at' => $assignedTo,
                        ':aj' => $assigneeIdsJson,
                        ':g' => $groupName,
                        ':c' => $now,
                        ':u' => $now,
                    ]);
                    flash('success', 'Tarefa criada.');
                    redirectTo('index.php#tasks');
                }

                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
                }
                $stmt = $pdo->prepare(
                    'UPDATE tasks
                     SET title = :t,
                         description = :d,
                         status = :s,
                         priority = :p,
                         due_date = :dd,
                         assigned_to = :at,
                         assignee_ids_json = :aj,
                         group_name = :g,
                         updated_at = :u
                     WHERE id = :id'
                );
                $updatedAt = nowIso();
                $stmt->execute([
                    ':t' => $title,
                    ':d' => $description,
                    ':s' => $status,
                    ':p' => $priority,
                    ':dd' => $dueDate,
                    ':at' => $assignedTo,
                    ':aj' => $assigneeIdsJson,
                    ':g' => $groupName,
                    ':u' => $updatedAt,
                    ':id' => $taskId,
                ]);
                if ($isAutosave && requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'task' => [
                            'id' => $taskId,
                            'group_name' => $groupName,
                            'due_date' => $dueDate,
                            'updated_at' => $updatedAt,
                            'updated_at_label' => (new DateTimeImmutable($updatedAt))->format('d/m H:i'),
                        ],
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id']),
                    ]);
                }
                if (!$isAutosave) {
                    flash('success', 'Tarefa atualizada.');
                }
                redirectTo('index.php#task-' . $taskId);

            case 'move_task':
                requireAuth();
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
                }
                $status = normalizeTaskStatus((string) ($_POST['status'] ?? 'todo'));
                $stmt = $pdo->prepare('UPDATE tasks SET status = :s, updated_at = :u WHERE id = :id');
                $stmt->execute([':s' => $status, ':u' => nowIso(), ':id' => $taskId]);
                flash('success', 'Status atualizado.');
                redirectTo('index.php#task-' . $taskId);

            case 'delete_task':
                $authUser = requireAuth();
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
                }
                $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id');
                $stmt->execute([':id' => $taskId]);
                if (requestExpectsJson()) {
                    respondJson([
                        'ok' => true,
                        'task_id' => $taskId,
                        'dashboard' => dashboardSummaryPayloadForUser((int) $authUser['id']),
                    ]);
                }
                flash('success', 'Tarefa removida.');
                redirectTo('index.php#tasks');

            default:
                throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $e) {
        if (requestExpectsJson()) {
            respondJson([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
        flash('error', $e->getMessage());
        redirectTo('index.php');
    }
}

$currentUser = currentUser();
$flashes = getFlashes();
$statusOptions = taskStatuses();
$priorityOptions = taskPriorities();
$users = usersList();
$statusFilter = isset($_GET['status']) && trim((string) $_GET['status']) !== ''
    ? normalizeTaskStatus((string) $_GET['status'])
    : null;
$assigneeFilterId = isset($_GET['assignee']) ? (int) $_GET['assignee'] : null;
$assigneeFilterId = $assigneeFilterId && $assigneeFilterId > 0 ? $assigneeFilterId : null;

$allTasks = $currentUser ? allTasks() : [];
$tasks = $currentUser ? filterTasks($allTasks, $statusFilter, $assigneeFilterId) : [];
$taskGroups = $currentUser ? taskGroupsList() : ['Geral'];
$showEmptyGroups = $currentUser && $statusFilter === null && $assigneeFilterId === null;
$tasksGroupedByGroup = $currentUser ? tasksByGroup($tasks, $showEmptyGroups ? $taskGroups : null) : [];
$stats = $currentUser ? dashboardStats($allTasks) : ['total' => 0, 'done' => 0, 'due_today' => 0, 'urgent' => 0];
$myOpenTasks = $currentUser ? countMyAssignedTasks($allTasks, (int) $currentUser['id']) : 0;
$completionRate = $stats['total'] > 0 ? (int) round(($stats['done'] / $stats['total']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/logo-lockup.svg?v=2">
    <link rel="shortcut icon" href="assets/logo-lockup.svg?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css?v=1">
    <script src="assets/app.js?v=1" defer></script>
</head>
<body class="<?= $currentUser ? 'is-dashboard' : 'is-auth' ?>">
    <div class="bg-layer bg-layer-one" aria-hidden="true"></div>
    <div class="bg-layer bg-layer-two" aria-hidden="true"></div>
    <div class="grain" aria-hidden="true"></div>

    <div class="app-shell">
        <?php if ($flashes): ?>
            <div class="flash-stack" aria-live="polite">
                <?php foreach ($flashes as $flash): ?>
                    <div class="flash flash-<?= e((string) ($flash['type'] ?? 'info')) ?>" data-flash>
                        <span><?= e((string) ($flash['message'] ?? '')) ?></span>
                        <button type="button" class="flash-close" data-flash-close aria-label="Fechar">×</button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$currentUser): ?>
            <?php include __DIR__ . '/partials/auth.php'; ?>
        <?php else: ?>
            <?php include __DIR__ . '/partials/dashboard.php'; ?>
        <?php endif; ?>
    </div>
</body>
</html>
