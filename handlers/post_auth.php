<?php
declare(strict_types=1);

function authErrorRedirectPath(string $panel, string $nextPath): string
{
    $query = [
        'auth' => $panel,
    ];

    if ($nextPath !== '') {
        $query['next'] = $nextPath;
    }

    return 'index.php?' . http_build_query($query) . '#' . $panel;
}

function handleAuthPostAction(PDO $pdo, string $action, string &$redirectPathOnError): bool
{
    $nextPath = safeRedirectPath((string) ($_POST['next'] ?? ''), 'index.php');

    switch ($action) {
        case 'register':
            $redirectPathOnError = authErrorRedirectPath('register', $nextPath);

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
            redirectTo($nextPath);

        case 'login':
            $redirectPathOnError = authErrorRedirectPath('login', $nextPath);

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

            $userId = (int) $userRow['id'];
            if (envFlag('APP_ENFORCE_BILLING', false) && !userHasBillingAccess($userId)) {
                logoutUser();
                setPendingCheckoutUserId($userId);
                if (str_starts_with($nextPath, 'home?action=checkout')) {
                    redirectTo($nextPath);
                }
                redirectTo('home?checkout=required#planos');
            }

            loginUser($userId, true);
            flash('success', 'Login realizado.');
            redirectTo($nextPath);

        case 'logout':
            logoutUser();
            flash('success', 'Sessão encerrada.');
            redirectTo('home');

        case 'request_password_reset':
            $redirectPathOnError = 'index.php?auth=forgot-password#forgot-password';
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Informe um e-mail valido.');
            }

            $delivery = ['logged_to_file' => false];
            $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $userRow = $stmt->fetch();

            if ($userRow) {
                $passwordResetToken = issuePasswordResetToken((int) $userRow['id']);
                $delivery = sendPasswordResetEmail(
                    (string) ($userRow['email'] ?? ''),
                    (string) ($userRow['name'] ?? ''),
                    (string) ($passwordResetToken['url'] ?? ''),
                    (string) ($passwordResetToken['expires_at'] ?? '')
                );
            }

            $requestPasswordResetMessage = 'Se o e-mail estiver cadastrado, enviamos as instrucoes para redefinir a senha.';
            if (!empty($delivery['logged_to_file'])) {
                $requestPasswordResetMessage .= ' Se o envio não estiver configurado neste ambiente, confira o arquivo storage/password-reset-mails.log.';
            }

            flash('success', $requestPasswordResetMessage);
            redirectTo('index.php?auth=login#login');

        case 'perform_password_reset':
            $selector = trim((string) ($_POST['selector'] ?? ''));
            $token = trim((string) ($_POST['token'] ?? ''));
            $redirectPathOnError = ($selector !== '' && $token !== '')
                ? passwordResetPath($selector, $token, false)
                : 'index.php?auth=forgot-password#forgot-password';

            $newPassword = (string) ($_POST['new_password'] ?? '');
            $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');
            if ($selector === '' || $token === '') {
                throw new RuntimeException('Link de redefinição inválido.');
            }
            if ($newPassword === '' || $newPasswordConfirm === '') {
                throw new RuntimeException('Preencha os campos de senha.');
            }
            if (mb_strlen($newPassword) < 6) {
                throw new RuntimeException('A nova senha deve ter pelo menos 6 caracteres.');
            }
            if ($newPassword !== $newPasswordConfirm) {
                throw new RuntimeException('A confirmação da nova senha não confere.');
            }

            $passwordResetRow = validPasswordResetRequest($selector, $token);
            if (!$passwordResetRow) {
                throw new RuntimeException('Este link de redefinição e inválido ou expirou.');
            }

            $userId = (int) ($passwordResetRow['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new RuntimeException('Usuário inválido para redefinição de senha.');
            }

            $stmt = $pdo->prepare(
                'UPDATE users
                 SET password_hash = :password_hash
                 WHERE id = :id'
            );
            $stmt->execute([
                ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':id' => $userId,
            ]);

            deletePasswordResetTokensForUser($userId);
            deleteRememberTokensForUser($userId);

            $sessionUser = currentUser();
            if ($sessionUser && (int) ($sessionUser['id'] ?? 0) === $userId) {
                logoutUser();
            }

            flash('success', 'Senha redefinida com sucesso. Entre com a nova senha.');
            redirectTo('index.php?auth=login#login');
    }

    return in_array($action, [
        'register',
        'login',
        'logout',
        'request_password_reset',
        'perform_password_reset',
    ], true);
}
