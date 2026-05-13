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

    return appUrl('?' . http_build_query($query) . '#' . $panel);
}

function authAllowsDirectRegisterForRedirectPath(string $nextPath): bool
{
    $workspaceInviteRequest = validWorkspaceEmailInvitationRequestFromPath($nextPath);
    if (!empty($workspaceInviteRequest)) {
        return (int) ($workspaceInviteRequest['existing_user_id'] ?? 0) <= 0;
    }

    return true;
}

function normalizeGoogleAuthIntent(string $intent): string
{
    $intent = strtolower(trim($intent));
    return $intent === 'register' ? 'register' : 'login';
}

function googleOAuthClientId(): string
{
    return trim((string) (envValue('GOOGLE_CLIENT_ID') ?? envValue('GOOGLE_OAUTH_CLIENT_ID') ?? ''));
}

function googleOAuthClientSecret(): string
{
    return trim((string) (envValue('GOOGLE_CLIENT_SECRET') ?? envValue('GOOGLE_OAUTH_CLIENT_SECRET') ?? ''));
}

function googleOAuthRedirectUri(): string
{
    $configured = trim((string) (envValue('GOOGLE_OAUTH_REDIRECT_URI') ?? envValue('GOOGLE_REDIRECT_URI') ?? ''));
    return $configured !== '' ? $configured : appUrl('?action=google_callback');
}

function googleOAuthConfigured(): bool
{
    return googleOAuthClientId() !== '' && googleOAuthClientSecret() !== '';
}

function setPendingGoogleRegistration(array $profile, string $nextPath): void
{
    $_SESSION['pending_google_registration'] = [
        'google_id' => trim((string) ($profile['google_id'] ?? '')),
        'email' => strtolower(trim((string) ($profile['email'] ?? ''))),
        'name' => normalizeUserDisplayName((string) ($profile['name'] ?? '')),
        'next' => safeRedirectPath($nextPath, appPlansPath()),
        'created_at' => time(),
    ];
}

function pendingGoogleRegistration(): ?array
{
    $pending = $_SESSION['pending_google_registration'] ?? null;
    if (!is_array($pending)) {
        return null;
    }

    $createdAt = (int) ($pending['created_at'] ?? 0);
    $googleId = trim((string) ($pending['google_id'] ?? ''));
    $email = strtolower(trim((string) ($pending['email'] ?? '')));
    if (
        $createdAt <= 0
        || (time() - $createdAt) > GOOGLE_OAUTH_STATE_TTL_SECONDS
        || $googleId === ''
        || $email === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        clearPendingGoogleRegistration();
        return null;
    }

    return [
        'google_id' => $googleId,
        'email' => $email,
        'name' => normalizeUserDisplayName((string) ($pending['name'] ?? '')),
        'next' => safeRedirectPath((string) ($pending['next'] ?? ''), appPlansPath()),
    ];
}

function clearPendingGoogleRegistration(): void
{
    unset($_SESSION['pending_google_registration']);
}

function googleOAuthStartUrl(string $intent, string $nextPath): string
{
    $intent = normalizeGoogleAuthIntent($intent);
    $nextPath = safeRedirectPath($nextPath, appDefaultAfterLoginPath());
    $query = [
        'action' => 'google_auth',
        'intent' => $intent,
    ];

    if ($nextPath !== '') {
        $query['next'] = $nextPath;
    }

    return appUrl('?' . http_build_query($query));
}

function googleOAuthIssueState(string $intent, string $nextPath): string
{
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_oauth_state'] = [
        'state_hash' => hash('sha256', $state),
        'intent' => normalizeGoogleAuthIntent($intent),
        'next' => safeRedirectPath($nextPath, appDefaultAfterLoginPath()),
        'created_at' => time(),
    ];

    return $state;
}

function googleOAuthConsumeState(string $state): array
{
    $state = trim($state);
    $stored = $_SESSION['google_oauth_state'] ?? null;
    unset($_SESSION['google_oauth_state']);

    if (!is_array($stored) || $state === '') {
        throw new RuntimeException('Sessao do Google expirada. Tente novamente.');
    }

    $createdAt = (int) ($stored['created_at'] ?? 0);
    if ($createdAt <= 0 || (time() - $createdAt) > GOOGLE_OAUTH_STATE_TTL_SECONDS) {
        throw new RuntimeException('Sessao do Google expirada. Tente novamente.');
    }

    $expectedHash = (string) ($stored['state_hash'] ?? '');
    if ($expectedHash === '' || !hash_equals($expectedHash, hash('sha256', $state))) {
        throw new RuntimeException('Sessao do Google invalida. Tente novamente.');
    }

    return [
        'intent' => normalizeGoogleAuthIntent((string) ($stored['intent'] ?? 'login')),
        'next' => safeRedirectPath((string) ($stored['next'] ?? ''), appDefaultAfterLoginPath()),
    ];
}

function googleOAuthRequest(string $method, string $url, array $headers = [], array $payload = [], int $timeoutSeconds = 15): array
{
    $method = strtoupper(trim($method));
    if (!in_array($method, ['GET', 'POST'], true)) {
        throw new RuntimeException('Metodo OAuth invalido.');
    }

    $encodedPayload = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    $requestUrl = $url;
    $content = '';
    if ($method === 'GET' && $encodedPayload !== '') {
        $requestUrl .= (str_contains($requestUrl, '?') ? '&' : '?') . $encodedPayload;
    } elseif ($method === 'POST') {
        $content = $encodedPayload;
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Content-Length: ' . strlen($content);
    }

    $responseBody = '';
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($requestUrl);
        if ($ch === false) {
            throw new RuntimeException('Falha ao inicializar OAuth.');
        }

        $curlOptions = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
        ];
        if ($method === 'POST') {
            $curlOptions[CURLOPT_POSTFIELDS] = $content;
        }

        curl_setopt_array($ch, $curlOptions);
        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error !== '' ? $error : 'Falha na conexao com Google.');
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $method === 'POST' ? $content : '',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($requestUrl, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException('Falha na conexao com Google.');
        }

        $responseHeaders = $http_response_header ?? [];
        foreach ($responseHeaders as $headerLine) {
            if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', (string) $headerLine, $matches)) {
                $statusCode = (int) ($matches[1] ?? 0);
                break;
            }
        }
    }

    return [
        'status_code' => $statusCode,
        'body' => (string) $responseBody,
    ];
}

function googleOAuthDecodeJsonResponse(array $response, string $fallbackError): array
{
    $body = (string) ($response['body'] ?? '');
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException($fallbackError);
    }

    $statusCode = (int) ($response['status_code'] ?? 0);
    if ($statusCode < 200 || $statusCode >= 300) {
        $errorMessage = trim((string) ($decoded['error_description'] ?? $decoded['error'] ?? $fallbackError));
        throw new RuntimeException($errorMessage !== '' ? $errorMessage : $fallbackError);
    }

    return $decoded;
}

function googleOAuthExchangeCode(string $code): array
{
    $response = googleOAuthRequest('POST', 'https://oauth2.googleapis.com/token', [], [
        'code' => $code,
        'client_id' => googleOAuthClientId(),
        'client_secret' => googleOAuthClientSecret(),
        'redirect_uri' => googleOAuthRedirectUri(),
        'grant_type' => 'authorization_code',
    ]);

    $tokenData = googleOAuthDecodeJsonResponse($response, 'Nao foi possivel validar o login com Google.');
    if (trim((string) ($tokenData['access_token'] ?? '')) === '') {
        throw new RuntimeException('Google nao retornou token de acesso.');
    }

    return $tokenData;
}

function googleOAuthFetchUserInfo(string $accessToken): array
{
    $response = googleOAuthRequest(
        'GET',
        'https://openidconnect.googleapis.com/v1/userinfo',
        ['Authorization: Bearer ' . $accessToken]
    );

    $profile = googleOAuthDecodeJsonResponse($response, 'Nao foi possivel carregar os dados da conta Google.');
    $googleId = trim((string) ($profile['sub'] ?? ''));
    $email = strtolower(trim((string) ($profile['email'] ?? '')));
    $emailVerifiedRaw = $profile['email_verified'] ?? false;
    $emailVerified = $emailVerifiedRaw === true
        || $emailVerifiedRaw === 1
        || strtolower(trim((string) $emailVerifiedRaw)) === 'true'
        || trim((string) $emailVerifiedRaw) === '1';

    if ($googleId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Conta Google sem e-mail valido.');
    }
    if (!$emailVerified) {
        throw new RuntimeException('Confirme o e-mail da sua conta Google antes de entrar.');
    }

    $name = normalizeUserDisplayName((string) ($profile['name'] ?? ''));
    if ($name === '') {
        $name = normalizeUserDisplayName((string) strtok($email, '@'));
    }
    if ($name === '') {
        $name = $email;
    }

    return [
        'google_id' => $googleId,
        'email' => $email,
        'name' => $name,
    ];
}

function handleGoogleOAuthStart(PDO $pdo): void
{
    $nextPath = safeRedirectPath((string) ($_GET['next'] ?? ''), appDefaultAfterLoginPath());
    $intent = normalizeGoogleAuthIntent((string) ($_GET['intent'] ?? 'login'));
    $redirectPanel = $intent === 'register' ? 'register' : 'login';

    if ($intent === 'register' && !authAllowsDirectRegisterForRedirectPath($nextPath)) {
        flash('error', 'Escolha um plano para criar sua conta.');
        redirectTo(authErrorRedirectPath('login', appDefaultAfterLoginPath()));
    }

    if (!googleOAuthConfigured()) {
        flash('error', 'Login com Google ainda nao configurado. Defina GOOGLE_CLIENT_ID e GOOGLE_CLIENT_SECRET.');
        redirectTo(authErrorRedirectPath($redirectPanel, $nextPath));
    }

    $state = googleOAuthIssueState($intent, $nextPath);
    $authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => googleOAuthClientId(),
        'redirect_uri' => googleOAuthRedirectUri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    ], '', '&', PHP_QUERY_RFC3986);

    header('Location: ' . $authorizationUrl);
    exit;
}

function handleGoogleOAuthCallback(PDO $pdo): void
{
    $nextPath = appDefaultAfterLoginPath();
    $intent = 'login';

    try {
        $state = (string) ($_GET['state'] ?? '');
        $statePayload = googleOAuthConsumeState($state);
        $nextPath = (string) ($statePayload['next'] ?? appDefaultAfterLoginPath());
        $intent = normalizeGoogleAuthIntent((string) ($statePayload['intent'] ?? 'login'));

        $googleError = trim((string) ($_GET['error'] ?? ''));
        if ($googleError !== '') {
            throw new RuntimeException('Login com Google cancelado ou nao autorizado.');
        }

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            throw new RuntimeException('Google nao retornou o codigo de autorizacao.');
        }
        if (!googleOAuthConfigured()) {
            throw new RuntimeException('Login com Google ainda nao configurado.');
        }

        $workspaceInviteRequest = validWorkspaceEmailInvitationRequestFromPath($nextPath);
        $isWorkspaceInviteRedirect = is_array($workspaceInviteRequest);
        $isCheckoutRedirect = str_starts_with($nextPath, 'home?action=checkout');

        $tokenData = googleOAuthExchangeCode($code);
        $googleProfile = googleOAuthFetchUserInfo((string) ($tokenData['access_token'] ?? ''));
        $googleId = (string) $googleProfile['google_id'];
        $email = (string) $googleProfile['email'];
        $name = (string) $googleProfile['name'];

        if ($isWorkspaceInviteRedirect) {
            $invitedEmail = strtolower(trim((string) ($workspaceInviteRequest['invited_email'] ?? '')));
            if ($invitedEmail === '' || $email !== $invitedEmail) {
                throw new RuntimeException('Entre com a conta Google do mesmo e-mail que recebeu o convite.');
            }
        }

        $userRow = userByGoogleId($pdo, $googleId);
        $createdUser = false;
        if (!$userRow) {
            $userRow = userByEmail($pdo, $email);
            if ($userRow) {
                linkGoogleAccountForUser($pdo, (int) $userRow['id'], $googleId);
            }
        }

        if (!$userRow) {
            if (!authAllowsDirectRegisterForRedirectPath($nextPath)) {
                throw new RuntimeException('Conta nao encontrada. Escolha um plano para criar sua conta.');
            }

            if ($intent !== 'register') {
                $pendingRegistrationNextPath = $isWorkspaceInviteRedirect ? $nextPath : appPlansPath();
                setPendingGoogleRegistration($googleProfile, $pendingRegistrationNextPath);
                flash('error', 'Esta conta Google ainda nao esta cadastrada. Confirme se deseja cadastrar essa conta.');
                redirectTo(authErrorRedirectPath('register', $pendingRegistrationNextPath));
            }

            $userId = createGoogleUser($pdo, $name, $email, $googleId, nowIso());
            $userRow = userById($userId);
            $createdUser = true;
        }

        $userId = (int) ($userRow['id'] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException('Nao foi possivel identificar o usuario.');
        }

        if ($isCheckoutRedirect && $createdUser) {
            setPendingCheckoutUserId($userId);
            flash('success', 'Conta criada com Google. Conclua o checkout para liberar o acesso ao app.');
            redirectTo($nextPath);
        }

        $canBypassBillingGate = $isWorkspaceInviteRedirect
            && strtolower(trim((string) ($workspaceInviteRequest['invited_email'] ?? ''))) === $email;
        if (envFlag('APP_ENFORCE_BILLING', false) && !userHasAppAccess($userId) && !$canBypassBillingGate) {
            if ($isCheckoutRedirect) {
                logoutUser();
                setPendingCheckoutUserId($userId);
                redirectTo($nextPath);
            }

            loginUser($userId, true);
            flash('success', 'Escolha um plano para liberar o acesso ao app.');
            redirectTo(appPlansPath());
        }

        loginUser($userId, true);
        flash('success', $createdUser ? 'Conta criada com Google.' : 'Login com Google realizado.');
        redirectToAppClearingInheritedFragment($nextPath);
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirectTo(authErrorRedirectPath($intent === 'register' ? 'register' : 'login', $nextPath));
    }
}

function handleAuthPostAction(PDO $pdo, string $action, string &$redirectPathOnError): bool
{
    $nextPath = safeRedirectPath((string) ($_POST['next'] ?? ''), appDefaultAfterLoginPath());
    $isCheckoutRedirect = str_starts_with($nextPath, 'home?action=checkout');
    $workspaceInviteRequest = validWorkspaceEmailInvitationRequestFromPath($nextPath);
    $isWorkspaceInviteRedirect = is_array($workspaceInviteRequest);

    switch ($action) {
        case 'google_register_confirm':
            $pendingGoogleRegistration = pendingGoogleRegistration();
            $pendingNextPath = safeRedirectPath(
                (string) ($pendingGoogleRegistration['next'] ?? ''),
                appPlansPath()
            );
            $redirectPathOnError = authErrorRedirectPath('register', $pendingNextPath);

            if (!$pendingGoogleRegistration) {
                throw new RuntimeException('Sessao do Google expirada. Tente novamente.');
            }

            $googleId = (string) $pendingGoogleRegistration['google_id'];
            $email = (string) $pendingGoogleRegistration['email'];
            $name = (string) ($pendingGoogleRegistration['name'] ?: $email);

            $userRow = userByGoogleId($pdo, $googleId);
            if (!$userRow) {
                $userRow = userByEmail($pdo, $email);
                if ($userRow) {
                    linkGoogleAccountForUser($pdo, (int) $userRow['id'], $googleId);
                }
            }

            if (!$userRow) {
                $userId = createGoogleUser($pdo, $name, $email, $googleId, nowIso());
                $userRow = userById($userId);
            }

            clearPendingGoogleRegistration();

            $userId = (int) ($userRow['id'] ?? 0);
            if ($userId <= 0) {
                throw new RuntimeException('Nao foi possivel criar a conta com Google.');
            }

            loginUser($userId, true);
            flash('success', 'Conta criada com Google. Escolha um plano para continuar.');
            redirectTo($pendingNextPath);

        case 'google_register_cancel':
            clearPendingGoogleRegistration();
            flash('success', 'Cadastro com Google cancelado.');
            redirectTo(authErrorRedirectPath('login', appDefaultAfterLoginPath()));

        case 'register':
            $redirectPathOnError = authErrorRedirectPath('register', $nextPath);

            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
            $acceptedTerms = isset($_POST['accept_terms']) && (string) $_POST['accept_terms'] === '1';

            if ($name === '' || $email === '' || $password === '') {
                throw new RuntimeException('Preencha nome, e-mail e senha.');
            }
            if (!$acceptedTerms) {
                throw new RuntimeException('Para criar a conta, aceite os Termos de Uso e a Politica de Privacidade.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Informe um e-mail válido.');
            }
            if ($isWorkspaceInviteRedirect) {
                $invitedEmail = strtolower(trim((string) ($workspaceInviteRequest['invited_email'] ?? '')));
                if ($invitedEmail === '' || $email !== $invitedEmail) {
                    throw new RuntimeException('Use o e-mail do convite para criar a conta.');
                }
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

            if ($isCheckoutRedirect) {
                setPendingCheckoutUserId($newUserId);
                flash('success', 'Conta criada com sucesso. Conclua o checkout para liberar o acesso ao app.');
                redirectTo($nextPath);
            }

            loginUser($newUserId, true);
            flash('success', $isWorkspaceInviteRedirect ? 'Conta criada. Vamos concluir o convite.' : 'Conta criada com sucesso.');
            redirectToAppClearingInheritedFragment($nextPath);

        case 'login':
            $redirectPathOnError = authErrorRedirectPath('login', $nextPath);

            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            if ($email === '' || $password === '') {
                throw new RuntimeException('Informe e-mail e senha.');
            }
            if ($isWorkspaceInviteRedirect) {
                $invitedEmail = strtolower(trim((string) ($workspaceInviteRequest['invited_email'] ?? '')));
                if ($invitedEmail === '' || $email !== $invitedEmail) {
                    throw new RuntimeException('Entre com o mesmo e-mail que recebeu o convite.');
                }
            }

            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $userRow = $stmt->fetch();
            if (!$userRow || !password_verify($password, (string) $userRow['password_hash'])) {
                throw new RuntimeException('Credenciais inválidas.');
            }

            $userId = (int) $userRow['id'];
            $canBypassBillingGate = $isWorkspaceInviteRedirect
                && strtolower(trim((string) ($workspaceInviteRequest['invited_email'] ?? ''))) === $email;
            if (envFlag('APP_ENFORCE_BILLING', false) && !userHasAppAccess($userId) && !$canBypassBillingGate) {
                if ($isCheckoutRedirect) {
                    logoutUser();
                    setPendingCheckoutUserId($userId);
                    redirectTo($nextPath);
                }

                loginUser($userId, true);
                flash('success', 'Escolha um plano para liberar o acesso ao app.');
                redirectTo(appPlansPath());
            }

            loginUser($userId, true);
            flash('success', 'Login realizado.');
            redirectToAppClearingInheritedFragment($nextPath);

        case 'logout':
            logoutUser();
            flash('success', 'Sessão encerrada.');
            redirectTo(siteUrl('home'));

        case 'request_password_reset':
            $redirectPathOnError = appUrl('?auth=forgot-password#forgot-password');
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
            $requestPasswordResetMessage .= deliveryFallbackNotice($delivery, 'storage/password-reset-mails.log');

            flash('success', $requestPasswordResetMessage);
            redirectTo(appUrl('?auth=login#login'));

        case 'perform_password_reset':
            $selector = trim((string) ($_POST['selector'] ?? ''));
            $token = trim((string) ($_POST['token'] ?? ''));
            $redirectPathOnError = ($selector !== '' && $token !== '')
                ? passwordResetPath($selector, $token, true)
                : appUrl('?auth=forgot-password#forgot-password');

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
            redirectTo(appUrl('?auth=login#login'));
    }

    return in_array($action, [
        'register',
        'login',
        'logout',
        'google_register_confirm',
        'google_register_cancel',
        'request_password_reset',
        'perform_password_reset',
    ], true);
}
