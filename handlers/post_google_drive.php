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

function googleDriveRedirectUri(): string
{
    $configured = trim((string) (envValue('GOOGLE_DRIVE_REDIRECT_URI') ?? ''));
    return $configured !== '' ? $configured : googleOAuthRedirectUri();
}

function googleDriveUsesSharedCallback(): bool
{
    return googleDriveRedirectUri() === googleOAuthRedirectUri();
}

function googleDriveStateMatches(string $state): bool
{
    $state = trim($state);
    $stored = $_SESSION['google_drive_oauth_state'] ?? null;
    if (!is_array($stored) || $state === '') {
        return false;
    }

    $createdAt = (int) ($stored['created_at'] ?? 0);
    if ($createdAt <= 0 || (time() - $createdAt) > GOOGLE_OAUTH_STATE_TTL_SECONDS) {
        return false;
    }

    $expectedHash = (string) ($stored['state_hash'] ?? '');
    return $expectedHash !== '' && hash_equals($expectedHash, hash('sha256', $state));
}

function googleDriveShouldHandleSharedCallback(string $state): bool
{
    return googleDriveUsesSharedCallback() && googleDriveStateMatches($state);
}

function googleDriveScopes(): string
{
    return trim((string) (envValue('GOOGLE_DRIVE_SCOPES') ?? 'https://www.googleapis.com/auth/drive.readonly'));
}

function googleDriveOAuthConfigured(): bool
{
    return googleDriveClientId() !== '' && googleDriveClientSecret() !== '';
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

function googleDriveAppendResumeOpenPath(string $path): string
{
    $path = safeRedirectPath($path, dashboardPath('tasks'));
    $parts = parse_url($path);
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    $query['google_drive_browser_resume_open'] = '1';

    $normalized = (string) ($parts['path'] ?? dashboardPath('tasks'));
    $queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    if ($queryString !== '') {
        $normalized .= '?' . $queryString;
    }

    return safeRedirectPath($normalized, dashboardPath('tasks'));
}

function googleDriveTokenRow(PDO $pdo, int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    googleDriveEnsureSchema($pdo);

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

function googleDriveGrantedScopes(?array $tokenRow): array
{
    $raw = trim((string) ($tokenRow['scope'] ?? ''));
    if ($raw === '') {
        return [];
    }

    $parts = preg_split('/\s+/', $raw) ?: [];
    $scopes = [];
    foreach ($parts as $part) {
        $scope = trim((string) $part);
        if ($scope === '') {
            continue;
        }
        $scopes[$scope] = true;
    }

    return array_keys($scopes);
}

function googleDriveTokenSupportsBrowser(?array $tokenRow): bool
{
    $grantedScopes = googleDriveGrantedScopes($tokenRow);
    if ($grantedScopes === []) {
        return false;
    }

    foreach ($grantedScopes as $scope) {
        if ($scope === 'https://www.googleapis.com/auth/drive' || $scope === 'https://www.googleapis.com/auth/drive.readonly') {
            return true;
        }
    }

    return false;
}

function googleDriveEnsureSchema(PDO $pdo): void
{
    static $ensured = false;

    if ($ensured) {
        return;
    }

    ensureGoogleDriveSchema($pdo);
    $ensured = true;
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

function googleDriveEscapeQueryValue(string $value): string
{
    return str_replace(["\\", "'"], ["\\\\", "\\'"], trim($value));
}

function googleDriveIsFolderMimeType(string $mimeType): bool
{
    return trim($mimeType) === 'application/vnd.google-apps.folder';
}

function googleDriveIsMediaMimeType(string $mimeType): bool
{
    $normalized = strtolower(trim($mimeType));
    return str_starts_with($normalized, 'image/') || str_starts_with($normalized, 'video/');
}

function googleDriveBrowserItemAllowed(array $file): bool
{
    $mimeType = trim((string) ($file['mimeType'] ?? ''));
    return googleDriveIsFolderMimeType($mimeType) || googleDriveIsMediaMimeType($mimeType);
}

function googleDriveFetchBrowserFolder(string $accessToken, string $fileId): array
{
    $fileId = googleDriveNormalizeFileId($fileId);
    if ($fileId === '') {
        throw new RuntimeException('Pasta do Google Drive invalida.');
    }

    $file = googleDriveApiJson(
        'GET',
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId),
        $accessToken,
        [
            'fields' => 'id,name,mimeType,parents',
            'supportsAllDrives' => 'true',
        ],
        'Nao foi possivel abrir a pasta do Google Drive.'
    );

    if (!googleDriveIsFolderMimeType((string) ($file['mimeType'] ?? ''))) {
        throw new RuntimeException('O item selecionado nao e uma pasta do Google Drive.');
    }

    return $file;
}

function googleDriveBrowserRootLabel(string $root): string
{
    return $root === 'shared_with_me' ? 'Compartilhados comigo' : 'Meu Drive';
}

function googleDriveBuildBrowserBreadcrumb(string $accessToken, string $root, string $folderId = ''): array
{
    $trail = [[
        'id' => '',
        'label' => googleDriveBrowserRootLabel($root),
    ]];

    $folderId = googleDriveNormalizeFileId($folderId);
    if ($folderId === '') {
        return $trail;
    }

    $chain = [];
    $visited = [];
    $cursor = $folderId;
    for ($depth = 0; $depth < 12 && $cursor !== ''; $depth++) {
        if (isset($visited[$cursor])) {
            break;
        }
        $visited[$cursor] = true;

        $folder = googleDriveFetchBrowserFolder($accessToken, $cursor);
        $chain[] = [
            'id' => $cursor,
            'label' => trim((string) ($folder['name'] ?? 'Pasta')),
        ];

        $parents = $folder['parents'] ?? [];
        if (!is_array($parents) || $parents === []) {
            break;
        }

        $nextParent = googleDriveNormalizeFileId((string) ($parents[0] ?? ''));
        if ($nextParent === '' || $nextParent === 'root') {
            break;
        }
        $cursor = $nextParent;
    }

    if ($chain !== []) {
        $trail = array_merge($trail, array_reverse($chain));
    }

    return $trail;
}

function googleDriveNormalizeBrowserItem(array $file): array
{
    $fileId = googleDriveNormalizeFileId((string) ($file['id'] ?? ''));
    if ($fileId === '') {
        throw new RuntimeException('Arquivo do Google Drive invalido.');
    }

    $mimeType = trim((string) ($file['mimeType'] ?? ''));
    $isFolder = googleDriveIsFolderMimeType($mimeType);
    $ownerLabel = '';
    $owners = $file['owners'] ?? [];
    if (is_array($owners) && isset($owners[0]) && is_array($owners[0])) {
        $ownerLabel = trim((string) ($owners[0]['displayName'] ?? ''));
    }

    $item = [
        'id' => $fileId,
        'name' => trim((string) ($file['name'] ?? 'Arquivo')),
        'mime_type' => $mimeType,
        'is_folder' => $isFolder,
        'can_select' => !$isFolder && googleDriveIsMediaMimeType($mimeType),
        'owner' => $ownerLabel,
        'modified_at' => trim((string) ($file['modifiedTime'] ?? '')),
        'shared' => !empty($file['shared']),
    ];

    if (!$isFolder) {
        $item['download_url'] = appUrl('?action=google_drive_download&file_id=' . rawurlencode($fileId));
        $item['thumbnail_url'] = appUrl('?action=google_drive_thumbnail&file_id=' . rawurlencode($fileId));
    }

    return $item;
}

function googleDriveListBrowserItems(string $accessToken, string $root, string $folderId = '', string $search = '', string $pageToken = ''): array
{
    $root = $root === 'shared_with_me' ? 'shared_with_me' : 'my_drive';
    $folderId = googleDriveNormalizeFileId($folderId);
    $search = trim($search);
    $pageToken = trim($pageToken);

    $conditions = ['trashed = false'];
    if ($folderId !== '') {
        $conditions[] = "'" . googleDriveEscapeQueryValue($folderId) . "' in parents";
    } elseif ($root === 'shared_with_me') {
        $conditions[] = 'sharedWithMe = true';
    } else {
        $conditions[] = "'root' in parents";
    }

    if ($search !== '') {
        $conditions[] = "name contains '" . googleDriveEscapeQueryValue(mb_substr($search, 0, 80)) . "'";
    }

    $payload = [
        'q' => implode(' and ', $conditions),
        'fields' => 'nextPageToken,files(id,name,mimeType,parents,thumbnailLink,modifiedTime,owners(displayName),shared)',
        'pageSize' => '100',
        'supportsAllDrives' => 'true',
        'includeItemsFromAllDrives' => 'true',
        'spaces' => 'drive',
        'corpora' => 'user',
    ];
    if ($pageToken !== '') {
        $payload['pageToken'] = $pageToken;
    }

    $response = googleDriveApiJson(
        'GET',
        'https://www.googleapis.com/drive/v3/files',
        $accessToken,
        $payload,
        'Nao foi possivel listar os arquivos do Google Drive.'
    );

    $files = [];
    foreach (($response['files'] ?? []) as $rawFile) {
        if (!is_array($rawFile) || !googleDriveBrowserItemAllowed($rawFile)) {
            continue;
        }
        $files[] = googleDriveNormalizeBrowserItem($rawFile);
    }

    usort($files, static function (array $left, array $right): int {
        $leftFolder = !empty($left['is_folder']);
        $rightFolder = !empty($right['is_folder']);
        if ($leftFolder !== $rightFolder) {
            return $leftFolder ? -1 : 1;
        }

        return strnatcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
    });

    return [
        'items' => $files,
        'next_page_token' => trim((string) ($response['nextPageToken'] ?? '')),
    ];
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
    $thumbnailProxyUrl = appUrl('?action=google_drive_thumbnail&file_id=' . rawurlencode($fileId));
    $item = [
        'provider' => 'google_drive',
        'file_id' => $fileId,
        'name' => $name,
        'mime_type' => $mimeType,
        'download_url' => $downloadUrl,
        'thumbnail_url' => $thumbnailProxyUrl,
    ];

    foreach ([
        'web_view_link' => $file['webViewLink'] ?? '',
        'src' => str_starts_with(strtolower($mimeType), 'image/') ? $downloadUrl : $thumbnailProxyUrl,
    ] as $key => $value) {
        $normalizedUrl = normalizeHttpReferenceValue((string) $value);
        if ($normalizedUrl !== null) {
            $item[$key] = $normalizedUrl;
        }
    }

    return $item;
}

function googleDriveProxyBinaryUrl(string $url, string $accessToken, string $fallbackContentType = 'application/octet-stream'): void
{
    $url = trim($url);
    if ($url === '') {
        http_response_code(404);
        exit;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $statusCode = 200;
    $contentType = $fallbackContentType;
    $contentLength = null;
    $responseBody = null;

    if (function_exists('curl_init')) {
        $responseHeaders = [];
        $ch = curl_init($url);
        if ($ch === false) {
            http_response_code(502);
            exit;
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $trimmed = trim($headerLine);
                if ($trimmed !== '' && str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($headerLine);
            },
        ]);

        $responseBody = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $infoContentType = trim((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        if ($infoContentType !== '') {
            $contentType = $infoContentType;
        } elseif (!empty($responseHeaders['content-type'])) {
            $contentType = (string) $responseHeaders['content-type'];
        }
        if (!empty($responseHeaders['content-length']) && ctype_digit((string) $responseHeaders['content-length'])) {
            $contentLength = (string) $responseHeaders['content-length'];
        }
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: Bearer ' . $accessToken . "\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        foreach ($responseHeaders as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $headerLine, $matches) === 1) {
                $statusCode = (int) $matches[1];
                continue;
            }
            if (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = trim((string) substr($headerLine, 13));
                continue;
            }
            if (stripos($headerLine, 'Content-Length:') === 0) {
                $lengthValue = trim((string) substr($headerLine, 15));
                if ($lengthValue !== '' && ctype_digit($lengthValue)) {
                    $contentLength = $lengthValue;
                }
            }
        }
    }

    if ($statusCode < 200 || $statusCode >= 300 || !is_string($responseBody) || $responseBody === '') {
        http_response_code($statusCode >= 400 ? $statusCode : 404);
        exit;
    }

    header('Content-Type: ' . ($contentType !== '' ? $contentType : $fallbackContentType));
    header('Cache-Control: private, max-age=300');
    if (is_string($contentLength) && $contentLength !== '') {
        header('Content-Length: ' . $contentLength);
    }

    echo $responseBody;
    exit;
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
    $shouldResumeDriveBrowser = false;

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
        $shouldResumeDriveBrowser = true;
        flash('success', 'Google Drive conectado.');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
    }

    if ($shouldResumeDriveBrowser) {
        $nextPath = googleDriveAppendResumeOpenPath($nextPath);
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
    $rangeHeader = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $downloadUrl = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?' . http_build_query([
        'alt' => 'media',
        'supportsAllDrives' => 'true',
    ], '', '&', PHP_QUERY_RFC3986);

    $statusCode = 200;
    $contentType = $mimeType;
    $contentLength = '';
    $contentRange = '';
    $acceptRanges = 'bytes';
    $responseBody = null;

    if (function_exists('curl_init')) {
        $responseHeaders = [];
        $ch = curl_init($downloadUrl);
        if ($ch === false) {
            http_response_code(502);
            exit;
        }
        $requestHeaders = ['Authorization: Bearer ' . $accessToken];
        if ($rangeHeader !== '') {
            $requestHeaders[] = 'Range: ' . $rangeHeader;
        }
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $trimmed = trim($headerLine);
                if ($trimmed !== '' && str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($headerLine);
            },
        ]);

        $responseBody = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $infoContentType = trim((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        if ($infoContentType !== '') {
            $contentType = $infoContentType;
        } elseif (!empty($responseHeaders['content-type'])) {
            $contentType = (string) $responseHeaders['content-type'];
        }
        if (!empty($responseHeaders['content-length']) && ctype_digit((string) $responseHeaders['content-length'])) {
            $contentLength = (string) $responseHeaders['content-length'];
        }
        if (!empty($responseHeaders['content-range'])) {
            $contentRange = (string) $responseHeaders['content-range'];
        }
        if (!empty($responseHeaders['accept-ranges'])) {
            $acceptRanges = (string) $responseHeaders['accept-ranges'];
        }
        curl_close($ch);
    } else {
        $requestHeader = 'Authorization: Bearer ' . $accessToken . "\r\n";
        if ($rangeHeader !== '') {
            $requestHeader .= 'Range: ' . $rangeHeader . "\r\n";
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $requestHeader,
                'timeout' => 60,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = @file_get_contents($downloadUrl, false, $context);
        $responseHeaders = $http_response_header ?? [];
        foreach ($responseHeaders as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $headerLine, $matches) === 1) {
                $statusCode = (int) $matches[1];
                continue;
            }
            if (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = trim((string) substr($headerLine, 13));
                continue;
            }
            if (stripos($headerLine, 'Content-Length:') === 0) {
                $lengthValue = trim((string) substr($headerLine, 15));
                if ($lengthValue !== '' && ctype_digit($lengthValue)) {
                    $contentLength = $lengthValue;
                }
                continue;
            }
            if (stripos($headerLine, 'Content-Range:') === 0) {
                $contentRange = trim((string) substr($headerLine, 14));
                continue;
            }
            if (stripos($headerLine, 'Accept-Ranges:') === 0) {
                $acceptRanges = trim((string) substr($headerLine, 14));
            }
        }
    }

    if ($statusCode < 200 || $statusCode >= 300 || !is_string($responseBody) || $responseBody === '') {
        http_response_code($statusCode >= 400 ? $statusCode : 502);
        exit;
    }

    http_response_code($statusCode);
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: inline; filename="' . addcslashes($safeName, '"\\') . '"');
    header('Cache-Control: private, max-age=120');
    header('Accept-Ranges: ' . ($acceptRanges !== '' ? $acceptRanges : 'bytes'));
    if ($contentLength !== '') {
        header('Content-Length: ' . $contentLength);
    }
    if ($contentRange !== '') {
        header('Content-Range: ' . $contentRange);
    }

    echo $responseBody;
    exit;
}

function handleGoogleDriveThumbnail(PDO $pdo): void
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
    $mimeType = trim((string) ($file['mimeType'] ?? ''));
    $thumbnailUrl = normalizeHttpReferenceValue((string) ($file['thumbnailLink'] ?? ''));

    if ($thumbnailUrl !== null) {
        googleDriveProxyBinaryUrl($thumbnailUrl, $accessToken, 'image/jpeg');
    }

    if (str_starts_with(strtolower($mimeType), 'image/')) {
        handleGoogleDriveDownload($pdo);
    }

    http_response_code(404);
    exit;
}

function handleGoogleDrivePostAction(PDO $pdo, string $action): bool
{
    switch ($action) {
        case 'google_drive_browser_session':
            $authUser = requireAuth();
            $userId = (int) ($authUser['id'] ?? 0);
            $nextPath = (string) ($_POST['next'] ?? dashboardPath('tasks'));
            if (!googleDriveOAuthConfigured()) {
                respondJson([
                    'ok' => true,
                    'configured' => false,
                    'connected' => false,
                    'browser_ready' => false,
                    'error' => 'Google Drive ainda nao configurado.',
                ]);
            }

            $tokenRow = googleDriveTokenRow($pdo, $userId);
            if (!is_array($tokenRow) || !googleDriveConnected($pdo, $userId)) {
                respondJson([
                    'ok' => true,
                    'configured' => true,
                    'connected' => false,
                    'browser_ready' => false,
                    'auth_url' => googleDriveAuthUrl($nextPath),
                ]);
            }

            if (!googleDriveTokenSupportsBrowser($tokenRow)) {
                respondJson([
                    'ok' => true,
                    'configured' => true,
                    'connected' => true,
                    'browser_ready' => false,
                    'reconnect_required' => true,
                    'auth_url' => googleDriveAuthUrl($nextPath),
                    'message' => 'Reconecte o Google Drive para autorizar a navegacao completa por pastas.',
                ]);
            }

            respondJson([
                'ok' => true,
                'configured' => true,
                'connected' => true,
                'browser_ready' => true,
            ]);

        case 'google_drive_browser_list':
            $authUser = requireAuth();
            $userId = (int) ($authUser['id'] ?? 0);
            $tokenRow = googleDriveTokenRow($pdo, $userId);
            if (!is_array($tokenRow) || !googleDriveConnected($pdo, $userId)) {
                throw new RuntimeException('Conecte o Google Drive para navegar pelas midias.');
            }
            if (!googleDriveTokenSupportsBrowser($tokenRow)) {
                throw new RuntimeException('Reconecte o Google Drive para autorizar a navegacao completa por pastas.');
            }

            $root = trim((string) ($_POST['root'] ?? ''));
            $root = $root === 'shared_with_me' ? 'shared_with_me' : 'my_drive';
            $folderId = googleDriveNormalizeFileId((string) ($_POST['folder_id'] ?? ''));
            $search = trim((string) ($_POST['search'] ?? ''));
            $pageToken = trim((string) ($_POST['page_token'] ?? ''));

            $accessToken = googleDriveAccessToken($pdo, $userId);
            if ($folderId !== '') {
                googleDriveFetchBrowserFolder($accessToken, $folderId);
            }

            $listing = googleDriveListBrowserItems($accessToken, $root, $folderId, $search, $pageToken);
            respondJson([
                'ok' => true,
                'root' => $root,
                'folder_id' => $folderId,
                'breadcrumbs' => googleDriveBuildBrowserBreadcrumb($accessToken, $root, $folderId),
                'items' => $listing['items'],
                'next_page_token' => $listing['next_page_token'],
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
            googleDriveEnsureSchema($pdo);
            $stmt = $pdo->prepare('DELETE FROM user_google_drive_tokens WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $userId]);
            respondJson([
                'ok' => true,
                'message' => 'Google Drive desconectado.',
            ]);
    }

    return false;
}
