<?php
declare(strict_types=1);

function googleDriveClientId(): string
{
    return trim((string) (envValue('GOOGLE_DRIVE_CLIENT_ID') ?? envValue('GOOGLE_CLIENT_ID') ?? envValue('GOOGLE_OAUTH_CLIENT_ID') ?? ''));
}

function googleDriveClientSecret(): string
{
    return trim((string) (envValue('GOOGLE_DRIVE_CLIENT_SECRET') ?? envValue('GOOGLE_CLIENT_SECRET') ?? envValue('GOOGLE_OAUTH_CLIENT_SECRET') ?? ''));
}

function googleDriveApiKey(): string
{
    return trim((string) (envValue('GOOGLE_DRIVE_API_KEY') ?? envValue('GOOGLE_API_KEY') ?? ''));
}

function googleDriveAppId(): string
{
    return trim((string) (envValue('GOOGLE_DRIVE_APP_ID') ?? envValue('GOOGLE_CLOUD_PROJECT_NUMBER') ?? ''));
}

function googleDriveRedirectUri(): string
{
    $configured = trim((string) (envValue('GOOGLE_DRIVE_REDIRECT_URI') ?? ''));
    return $configured !== '' ? $configured : appUrl('?action=google_drive_callback');
}

function googleDriveScopes(): string
{
    return trim((string) (envValue('GOOGLE_DRIVE_SCOPES') ?? 'https://www.googleapis.com/auth/drive.file'));
}

function googleDriveOAuthConfigured(): bool
{
    return googleDriveClientId() !== '' && googleDriveClientSecret() !== '';
}

function googleDrivePickerConfigured(): bool
{
    return googleDriveApiKey() !== '';
}

function googleDriveIssueState(string $nextPath): string
{
    $state = bin2hex(random_bytes(32));
    $_SESSION['google_drive_oauth_state'] = [
        'state_hash' => hash('sha256', $state),
        'next' => safeRedirectPath($nextPath, dashboardPath('tasks')),
        'created_at' => time(),
    ];

    return $state;
}

function googleDriveConsumeState(string $state): array
{
    $state = trim($state);
    $stored = $_SESSION['google_drive_oauth_state'] ?? null;
    unset($_SESSION['google_drive_oauth_state']);

    if (!is_array($stored) || $state === '') {
        throw new RuntimeException('Sessao do Google Drive expirada. Tente novamente.');
    }

    $createdAt = (int) ($stored['created_at'] ?? 0);
    if ($createdAt <= 0 || (time() - $createdAt) > GOOGLE_OAUTH_STATE_TTL_SECONDS) {
        throw new RuntimeException('Sessao do Google Drive expirada. Tente novamente.');
    }

    $expectedHash = (string) ($stored['state_hash'] ?? '');
    if ($expectedHash === '' || !hash_equals($expectedHash, hash('sha256', $state))) {
        throw new RuntimeException('Sessao do Google Drive invalida. Tente novamente.');
    }

    return [
        'next' => safeRedirectPath((string) ($stored['next'] ?? ''), dashboardPath('tasks')),
    ];
}

function googleDriveAuthUrl(string $nextPath = ''): string
{
    $nextPath = safeRedirectPath($nextPath, dashboardPath('tasks'));
    return appPath('?' . http_build_query([
        'action' => 'google_drive_auth',
        'next' => $nextPath,
    ], '', '&', PHP_QUERY_RFC3986));
}

function googleDriveTokenRow(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT user_id, access_token, refresh_token, expires_at, scope, token_type, created_at, updated_at
         FROM user_google_drive_tokens
         WHERE user_id = :user_id
         LIMIT 1'
    );
    $stmt->execute([':user_id' => $userId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function googleDriveConnected(PDO $pdo, int $userId): bool
{
    $row = googleDriveTokenRow($pdo, $userId);
    return is_array($row) && trim((string) ($row['refresh_token'] ?? '')) !== '';
}

function googleDriveDecryptTokenValue(?string $value): string
{
    $value = (string) ($value ?? '');
    if ($value === '') {
        return '';
    }

    return vaultDecryptSecret($value);
}

function googleDriveSaveTokenData(PDO $pdo, int $userId, array $tokenData, ?array $existingRow = null): void
{
    if ($userId <= 0) {
        throw new RuntimeException('Usuario invalido.');
    }

    $existingRow ??= googleDriveTokenRow($pdo, $userId);
    $accessToken = trim((string) ($tokenData['access_token'] ?? ''));
    if ($accessToken === '') {
        throw new RuntimeException('Google Drive nao retornou token de acesso.');
    }

    $refreshToken = trim((string) ($tokenData['refresh_token'] ?? ''));
    if ($refreshToken === '' && is_array($existingRow)) {
        $refreshToken = googleDriveDecryptTokenValue((string) ($existingRow['refresh_token'] ?? ''));
    }
    if ($refreshToken === '') {
        throw new RuntimeException('Google Drive nao retornou permissao offline. Tente conectar novamente.');
    }

    $expiresIn = max(60, (int) ($tokenData['expires_in'] ?? 3600));
    $expiresAt = time() + $expiresIn - 60;
    $scope = trim((string) ($tokenData['scope'] ?? ($existingRow['scope'] ?? googleDriveScopes())));
    $tokenType = trim((string) ($tokenData['token_type'] ?? ($existingRow['token_type'] ?? 'Bearer')));
    if ($tokenType === '') {
        $tokenType = 'Bearer';
    }

    $now = nowIso();
    if (is_array($existingRow)) {
        $stmt = $pdo->prepare(
            'UPDATE user_google_drive_tokens
             SET access_token = :access_token,
                 refresh_token = :refresh_token,
                 expires_at = :expires_at,
                 scope = :scope,
                 token_type = :token_type,
                 updated_at = :updated_at
             WHERE user_id = :user_id'
        );
        $stmt->execute([
            ':access_token' => vaultEncryptSecret($accessToken),
            ':refresh_token' => vaultEncryptSecret($refreshToken),
            ':expires_at' => $expiresAt,
            ':scope' => $scope,
            ':token_type' => $tokenType,
            ':updated_at' => $now,
            ':user_id' => $userId,
        ]);
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO user_google_drive_tokens (
            user_id, access_token, refresh_token, expires_at, scope, token_type, created_at, updated_at
        ) VALUES (
            :user_id, :access_token, :refresh_token, :expires_at, :scope, :token_type, :created_at, :updated_at
        )'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':access_token' => vaultEncryptSecret($accessToken),
        ':refresh_token' => vaultEncryptSecret($refreshToken),
        ':expires_at' => $expiresAt,
        ':scope' => $scope,
        ':token_type' => $tokenType,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

function googleDriveRefreshAccessToken(PDO $pdo, int $userId, array $row): string
{
    $refreshToken = googleDriveDecryptTokenValue((string) ($row['refresh_token'] ?? ''));
    if ($refreshToken === '') {
        throw new RuntimeException('Conecte o Google Drive novamente.');
    }
    if (!googleDriveOAuthConfigured()) {
        throw new RuntimeException('Google Drive ainda nao configurado. Defina GOOGLE_DRIVE_CLIENT_ID e GOOGLE_DRIVE_CLIENT_SECRET.');
    }

    $response = googleOAuthRequest('POST', 'https://oauth2.googleapis.com/token', [], [
        'client_id' => googleDriveClientId(),
        'client_secret' => googleDriveClientSecret(),
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
    $tokenData = googleOAuthDecodeJsonResponse($response, 'Nao foi possivel renovar acesso ao Google Drive.');
    googleDriveSaveTokenData($pdo, $userId, $tokenData, $row);

    return trim((string) ($tokenData['access_token'] ?? ''));
}

function googleDriveAccessToken(PDO $pdo, int $userId): string
{
    $row = googleDriveTokenRow($pdo, $userId);
    if (!is_array($row)) {
        throw new RuntimeException('Conecte o Google Drive para escolher arquivos.');
    }

    $expiresAt = (int) ($row['expires_at'] ?? 0);
    $accessToken = googleDriveDecryptTokenValue((string) ($row['access_token'] ?? ''));
    if ($accessToken !== '' && $expiresAt > (time() + 45)) {
        return $accessToken;
    }

    return googleDriveRefreshAccessToken($pdo, $userId, $row);
}

function googleDriveApiJson(string $method, string $url, string $accessToken, array $payload = [], string $fallbackError = 'Falha ao acessar Google Drive.'): array
{
    $response = googleOAuthRequest(
        $method,
        $url,
        ['Authorization: Bearer ' . $accessToken],
        $payload,
        25
    );

    return googleOAuthDecodeJsonResponse($response, $fallbackError);
}

function googleDriveNormalizeFileId(string $fileId): string
{
    $fileId = trim($fileId);
    return preg_match('/^[A-Za-z0-9_-]{6,220}$/', $fileId) === 1 ? $fileId : '';
}

function googleDriveFetchFileMetadata(string $accessToken, string $fileId): array
{
    $fileId = googleDriveNormalizeFileId($fileId);
    if ($fileId === '') {
        throw new RuntimeException('Arquivo do Google Drive invalido.');
    }

    return googleDriveApiJson(
        'GET',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId),
        $accessToken,
        [
            'fields' => 'id,name,mimeType,thumbnailLink,webViewLink,webContentLink,iconLink,size,capabilities/canDownload',
            'supportsAllDrives' => 'true',
        ],
        'Nao foi possivel ler o arquivo do Google Drive.'
    );
}

function googleDriveMediaItemFromFile(array $file): array
{
    $fileId = googleDriveNormalizeFileId((string) ($file['id'] ?? ''));
    if ($fileId === '') {
        throw new RuntimeException('Arquivo do Google Drive invalido.');
    }

    $mimeType = trim((string) ($file['mimeType'] ?? ''));
    if ($mimeType !== '' && !str_starts_with(strtolower($mimeType), 'image/') && !str_starts_with(strtolower($mimeType), 'video/')) {
        throw new RuntimeException('Selecione apenas imagens ou videos do Google Drive.');
    }

    $name = trim((string) ($file['name'] ?? 'Arquivo do Drive'));
    if ($name === '') {
        $name = 'Arquivo do Drive';
    }

    $downloadUrl = appUrl('?action=google_drive_download&file_id=' . rawurlencode($fileId));
    $item = [
        'provider' => 'google_drive',
        'file_id' => $fileId,
        'name' => $name,
        'mime_type' => $mimeType,
        'download_url' => $downloadUrl,
    ];

    foreach ([
        'thumbnail_url' => $file['thumbnailLink'] ?? '',
        'web_view_link' => $file['webViewLink'] ?? '',
        'src' => str_starts_with(strtolower($mimeType), 'image/') ? $downloadUrl : ($file['thumbnailLink'] ?? ''),
    ] as $key => $value) {
        $normalizedUrl = normalizeHttpReferenceValue((string) $value);
        if ($normalizedUrl !== null) {
            $item[$key] = $normalizedUrl;
        }
    }

    return $item;
}

function handleGoogleDriveOAuthStart(PDO $pdo): void
{
    $authUser = requireAuth();
    if ((int) ($authUser['id'] ?? 0) <= 0) {
        throw new RuntimeException('Sessao expirada. Faca login novamente.');
    }
    if (!googleDriveOAuthConfigured()) {
        flash('error', 'Google Drive ainda nao configurado. Defina GOOGLE_DRIVE_CLIENT_ID e GOOGLE_DRIVE_CLIENT_SECRET.');
        redirectTo(dashboardPath('tasks'));
    }

    $nextPath = safeRedirectPath((string) ($_GET['next'] ?? ''), dashboardPath('tasks'));
    $state = googleDriveIssueState($nextPath);
    $authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => googleDriveClientId(),
        'redirect_uri' => googleDriveRedirectUri(),
        'response_type' => 'code',
        'scope' => googleDriveScopes(),
        'state' => $state,
        'access_type' => 'offline',
        'prompt' => 'consent select_account',
        'include_granted_scopes' => 'true',
    ], '', '&', PHP_QUERY_RFC3986);

    header('Location: ' . $authorizationUrl);
    exit;
}

function handleGoogleDriveOAuthCallback(PDO $pdo): void
{
    $nextPath = dashboardPath('tasks');

    try {
        $statePayload = googleDriveConsumeState((string) ($_GET['state'] ?? ''));
        $nextPath = (string) ($statePayload['next'] ?? dashboardPath('tasks'));

        $googleError = trim((string) ($_GET['error'] ?? ''));
        if ($googleError !== '') {
            throw new RuntimeException('Conexao com Google Drive cancelada ou nao autorizada.');
        }

        $authUser = requireAuth();
        $userId = (int) ($authUser['id'] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException('Sessao expirada. Faca login novamente.');
        }
        if (!googleDriveOAuthConfigured()) {
            throw new RuntimeException('Google Drive ainda nao configurado.');
        }

        $code = trim((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            throw new RuntimeException('Google Drive nao retornou o codigo de autorizacao.');
        }

        $response = googleOAuthRequest('POST', 'https://oauth2.googleapis.com/token', [], [
            'code' => $code,
            'client_id' => googleDriveClientId(),
            'client_secret' => googleDriveClientSecret(),
            'redirect_uri' => googleDriveRedirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        $tokenData = googleOAuthDecodeJsonResponse($response, 'Nao foi possivel conectar o Google Drive.');
        googleDriveSaveTokenData($pdo, $userId, $tokenData);
        flash('success', 'Google Drive conectado.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    redirectTo($nextPath);
}

function handleGoogleDriveDownload(PDO $pdo): void
{
    $authUser = requireAuth();
    $userId = (int) ($authUser['id'] ?? 0);
    if ($userId <= 0) {
        http_response_code(401);
        exit;
    }

    $fileId = googleDriveNormalizeFileId((string) ($_GET['file_id'] ?? ''));
    if ($fileId === '') {
        http_response_code(404);
        exit;
    }

    $accessToken = googleDriveAccessToken($pdo, $userId);
    $file = googleDriveFetchFileMetadata($accessToken, $fileId);
    $mimeType = trim((string) ($file['mimeType'] ?? 'application/octet-stream'));
    if (str_starts_with(strtolower($mimeType), 'application/vnd.google-apps.')) {
        throw new RuntimeException('Este tipo de arquivo do Google Drive precisa ser exportado antes do download.');
    }

    $canDownload = $file['capabilities']['canDownload'] ?? true;
    if ($canDownload === false || (is_string($canDownload) && strtolower($canDownload) === 'false')) {
        throw new RuntimeException('Sua conta nao tem permissao para baixar este arquivo.');
    }

    $name = trim((string) ($file['name'] ?? 'drive-file'));
    if ($name === '') {
        $name = 'drive-file';
    }
    $safeName = str_replace(['\\', '/', "\r", "\n"], '-', $name);
    if ($mimeType === '') {
        $mimeType = 'application/octet-stream';
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . addcslashes($safeName, '"\\') . '"');
    header('Cache-Control: private, max-age=120');

    $downloadUrl = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?' . http_build_query([
        'alt' => 'media',
        'supportsAllDrives' => 'true',
    ], '', '&', PHP_QUERY_RFC3986);

    if (function_exists('curl_init')) {
        $ch = curl_init($downloadUrl);
        if ($ch === false) {
            http_response_code(502);
            exit;
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk): int {
                echo $chunk;
                return strlen($chunk);
            },
        ]);
        curl_exec($ch);
        curl_close($ch);
        exit;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer ' . $accessToken . "\r\n",
            'timeout' => 60,
            'ignore_errors' => true,
        ],
    ]);
    $stream = @fopen($downloadUrl, 'rb', false, $context);
    if (!is_resource($stream)) {
        http_response_code(502);
        exit;
    }
    fpassthru($stream);
    fclose($stream);
    exit;
}

function handleGoogleDrivePostAction(PDO $pdo, string $action): bool
{
    switch ($action) {
        case 'google_drive_picker_token':
            $authUser = requireAuth();
            $userId = (int) ($authUser['id'] ?? 0);
            if (!googleDriveOAuthConfigured()) {
                respondJson([
                    'ok' => true,
                    'configured' => false,
                    'connected' => false,
                    'picker_configured' => googleDrivePickerConfigured(),
                    'error' => 'Google Drive ainda nao configurado.',
                ]);
            }
            if (!googleDrivePickerConfigured()) {
                respondJson([
                    'ok' => true,
                    'configured' => true,
                    'connected' => googleDriveConnected($pdo, $userId),
                    'picker_configured' => false,
                    'auth_url' => googleDriveAuthUrl((string) ($_POST['next'] ?? dashboardPath('tasks'))),
                ]);
            }
            if (!googleDriveConnected($pdo, $userId)) {
                respondJson([
                    'ok' => true,
                    'configured' => true,
                    'connected' => false,
                    'picker_configured' => true,
                    'auth_url' => googleDriveAuthUrl((string) ($_POST['next'] ?? dashboardPath('tasks'))),
                ]);
            }

            $accessToken = googleDriveAccessToken($pdo, $userId);
            respondJson([
                'ok' => true,
                'configured' => true,
                'connected' => true,
                'picker_configured' => true,
                'access_token' => $accessToken,
                'developer_key' => googleDriveApiKey(),
                'app_id' => googleDriveAppId(),
                'scope' => googleDriveScopes(),
            ]);

        case 'google_drive_file_metadata':
            $authUser = requireAuth();
            $userId = (int) ($authUser['id'] ?? 0);
            $accessToken = googleDriveAccessToken($pdo, $userId);

            $rawIds = (string) ($_POST['file_ids'] ?? '');
            $decoded = json_decode($rawIds, true);
            if (is_array($decoded)) {
                $fileIds = $decoded;
            } else {
                $fileIds = preg_split('/[\s,]+/', $rawIds) ?: [];
            }

            $items = [];
            $seen = [];
            foreach ($fileIds as $rawId) {
                $fileId = googleDriveNormalizeFileId((string) $rawId);
                if ($fileId === '' || isset($seen[$fileId])) {
                    continue;
                }
                $seen[$fileId] = true;
                $items[] = googleDriveMediaItemFromFile(googleDriveFetchFileMetadata($accessToken, $fileId));
                if (count($items) >= 20) {
                    break;
                }
            }

            respondJson([
                'ok' => true,
                'media' => normalizeReferenceImageList($items),
            ]);

        case 'google_drive_disconnect':
            $authUser = requireAuth();
            $userId = (int) ($authUser['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM user_google_drive_tokens WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $userId]);
            respondJson([
                'ok' => true,
                'message' => 'Google Drive desconectado.',
            ]);
    }

    return false;
}
