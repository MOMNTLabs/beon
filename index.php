<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();

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

                $_SESSION['user_id'] = createUser(
                    $pdo,
                    $name,
                    $email,
                    password_hash($password, PASSWORD_DEFAULT),
                    nowIso()
                );
                session_regenerate_id(true);
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

                $_SESSION['user_id'] = (int) $userRow['id'];
                session_regenerate_id(true);
                flash('success', 'Login realizado.');
                redirectTo('index.php');

            case 'logout':
                unset($_SESSION['user_id']);
                session_regenerate_id(true);
                flash('success', 'Sessão encerrada.');
                redirectTo('index.php');

            case 'create_task':
            case 'update_task':
                $authUser = requireAuth();
                $taskId = (int) ($_POST['task_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                $description = trim((string) ($_POST['description'] ?? ''));
                $status = normalizeTaskStatus((string) ($_POST['status'] ?? 'todo'));
                $priority = normalizeTaskPriority((string) ($_POST['priority'] ?? 'medium'));
                $dueDate = dueDateForStorage($_POST['due_date'] ?? null);
                $assignedTo = (int) ($_POST['assigned_to'] ?? 0);
                $assignedTo = $assignedTo > 0 ? $assignedTo : null;

                if ($title === '') {
                    throw new RuntimeException('O título da tarefa é obrigatório.');
                }
                if (mb_strlen($title) > 140) {
                    throw new RuntimeException('O título deve ter no máximo 140 caracteres.');
                }
                if ($assignedTo !== null) {
                    $check = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
                    $check->execute([':id' => $assignedTo]);
                    if (!$check->fetch()) {
                        throw new RuntimeException('Responsável inválido.');
                    }
                }

                if ($action === 'create_task') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO tasks (title, description, status, priority, due_date, created_by, assigned_to, created_at, updated_at)
                         VALUES (:t, :d, :s, :p, :dd, :cb, :at, :c, :u)'
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
                        ':c' => $now,
                        ':u' => $now,
                    ]);
                    flash('success', 'Tarefa criada.');
                    redirectTo('index.php#board');
                }

                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
                }
                $stmt = $pdo->prepare(
                    'UPDATE tasks
                     SET title = :t, description = :d, status = :s, priority = :p, due_date = :dd, assigned_to = :at, updated_at = :u
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':t' => $title,
                    ':d' => $description,
                    ':s' => $status,
                    ':p' => $priority,
                    ':dd' => $dueDate,
                    ':at' => $assignedTo,
                    ':u' => nowIso(),
                    ':id' => $taskId,
                ]);
                flash('success', 'Tarefa atualizada.');
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
                requireAuth();
                $taskId = (int) ($_POST['task_id'] ?? 0);
                if ($taskId <= 0) {
                    throw new RuntimeException('Tarefa inválida.');
                }
                $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id');
                $stmt->execute([':id' => $taskId]);
                flash('success', 'Tarefa removida.');
                redirectTo('index.php#board');

            default:
                throw new RuntimeException('Ação inválida.');
        }
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirectTo('index.php');
    }
}

$currentUser = currentUser();
$flashes = getFlashes();
$statusOptions = taskStatuses();
$priorityOptions = taskPriorities();
$users = usersList();
$tasks = $currentUser ? allTasks() : [];
$groupedTasks = $currentUser ? tasksByStatus($tasks) : [];
$stats = $currentUser ? dashboardStats($tasks) : ['total' => 0, 'done' => 0, 'due_today' => 0, 'urgent' => 0];
$myOpenTasks = $currentUser ? countMyAssignedTasks($tasks, (int) $currentUser['id']) : 0;
$completionRate = $stats['total'] > 0 ? (int) round(($stats['done'] / $stats['total']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
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
