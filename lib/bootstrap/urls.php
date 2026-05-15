<?php
declare(strict_types=1);

function currentScriptBasePath(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $scriptDirectory = str_replace('\\', '/', dirname($scriptName));
    $scriptDirectory = rtrim($scriptDirectory, '/');
    if ($scriptDirectory === '' || $scriptDirectory === '.' || $scriptDirectory === '/') {
        return '';
    }

    return $scriptDirectory;
}

function requestHostName(): string
{
    return bootstrapRequestHostName();
}

function requestAuthority(): string
{
    return trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}

function normalizedUrlBase(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return '';
    }

    $host = trim((string) ($parts['host'] ?? ''));
    if ($host === '') {
        return '';
    }

    $scheme = strtolower(trim((string) ($parts['scheme'] ?? (requestIsHttps() ? 'https' : 'http'))));
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $path = (string) ($parts['path'] ?? '');
    $path = preg_replace('~/index\.php/?$~i', '', $path) ?? $path;
    $path = '/' . ltrim($path, '/');
    $path = rtrim($path, '/');
    $path = $path === '/' ? '' : $path;

    return $scheme . '://' . strtolower($host) . $port . $path;
}

function urlBasePath(string $url): string
{
    $normalizedUrl = normalizedUrlBase($url);
    if ($normalizedUrl === '') {
        return '';
    }

    $path = (string) (parse_url($normalizedUrl, PHP_URL_PATH) ?? '');
    $path = '/' . ltrim($path, '/');
    $path = rtrim($path, '/');
    return $path === '/' ? '' : $path;
}

function urlOrigin(string $url): string
{
    $normalizedUrl = normalizedUrlBase($url);
    if ($normalizedUrl === '') {
        return '';
    }

    $parts = parse_url($normalizedUrl);
    if ($parts === false) {
        return '';
    }

    $scheme = strtolower(trim((string) ($parts['scheme'] ?? 'http')));
    $host = strtolower(trim((string) ($parts['host'] ?? '')));
    if ($host === '') {
        return '';
    }

    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    return $scheme . '://' . $host . $port;
}

function requestHostMatchesUrlHost(string $url): bool
{
    $urlHost = bootstrapUrlHostName($url);
    if ($urlHost === '') {
        return false;
    }

    return requestHostName() === $urlHost;
}

function configuredAppUrl(): string
{
    return normalizedUrlBase((string) envValue('APP_URL', ''));
}

function configuredSiteUrl(): string
{
    $siteUrl = normalizedUrlBase((string) envValue('SITE_URL', ''));
    if ($siteUrl !== '') {
        return $siteUrl;
    }

    $appUrl = configuredAppUrl();
    if ($appUrl === '') {
        return '';
    }

    $parts = parse_url($appUrl);
    if ($parts === false) {
        return '';
    }

    $host = strtolower(trim((string) ($parts['host'] ?? '')));
    if ($host === '' || !str_starts_with($host, 'app.') || substr_count($host, '.') < 2) {
        return '';
    }

    $scheme = strtolower(trim((string) ($parts['scheme'] ?? 'https')));
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    return $scheme . '://' . substr($host, 4) . $port;
}

function appBasePath(): string
{
    $configuredAppUrl = configuredAppUrl();
    if (
        $configuredAppUrl !== ''
        && (PHP_SAPI === 'cli' || requestHostMatchesUrlHost($configuredAppUrl))
    ) {
        return urlBasePath($configuredAppUrl);
    }

    return currentScriptBasePath();
}

function siteBasePath(): string
{
    $configuredSiteUrl = configuredSiteUrl();
    if (
        $configuredSiteUrl !== ''
        && (PHP_SAPI === 'cli' || requestHostMatchesUrlHost($configuredSiteUrl))
    ) {
        return urlBasePath($configuredSiteUrl);
    }

    return currentScriptBasePath();
}

function normalizeDashboardViewKey(string $view): string
{
    $normalized = strtolower(trim($view));
    if ($normalized === 'dues') {
        return 'accounting';
    }

    return in_array($normalized, ['overview', 'tasks', 'vault', 'inventory', 'accounting', 'users'], true)
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

function appDefaultAfterLoginPath(): string
{
    return dashboardPath('tasks');
}

function appPlansPath(): string
{
    return '?action=plans';
}

function canonicalizeSiteRelativePath(string $path): string
{
    $trimmedPath = trim($path);
    if ($trimmedPath === '') {
        return '';
    }

    if (preg_match('~^/?([a-z0-9-]+)\.php(?=$|[?#])~i', $trimmedPath, $matches)) {
        $matchedScript = strtolower((string) ($matches[1] ?? ''));
        $matchedScript = $matchedScript === 'vendas' ? 'home' : $matchedScript;
        $suffix = (string) substr($trimmedPath, strlen((string) ($matches[0] ?? '')));
        if (in_array($matchedScript, ['home', 'index'], true)) {
            $trimmedPath = $suffix;
        } elseif ($suffix === '' || $suffix[0] === '?' || $suffix[0] === '#') {
            $trimmedPath = $matchedScript . $suffix;
        } else {
            $trimmedPath = $matchedScript . '/' . ltrim($suffix, '/');
        }
    }

    if (preg_match('~^/?(?:home|vendas)(?=$|[/?#])~i', $trimmedPath, $matches)) {
        $prefix = (string) ($matches[0] ?? '');
        $suffix = (string) substr($trimmedPath, strlen($prefix));
        $trimmedPath = $suffix;
    }

    return $trimmedPath;
}

function buildAppPathFromBase(string $path, string $basePath): string
{
    $trimmedPath = trim($path);
    $baseRoot = $basePath !== '' ? $basePath . '/' : '/';

    if ($trimmedPath === '') {
        return $baseRoot;
    }

    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $trimmedPath) || str_starts_with($trimmedPath, '//')) {
        return $trimmedPath;
    }

    if (preg_match('~^/?([a-z0-9-]+)\.php(?=$|[?#])~i', $trimmedPath, $matches)) {
        $matchedScript = (string) ($matches[1] ?? '');
        $routeSlug = strtolower($matchedScript);
        if ($routeSlug === 'vendas') {
            $routeSlug = 'home';
        }

        $suffix = (string) substr($trimmedPath, strlen((string) ($matches[0] ?? '')));
        if ($routeSlug === 'index') {
            $trimmedPath = $suffix;
        } elseif ($suffix === '' || $suffix[0] === '?' || $suffix[0] === '#') {
            $trimmedPath = $routeSlug . $suffix;
        } else {
            $trimmedPath = $routeSlug . '/' . ltrim($suffix, '/');
        }

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

function buildSitePathFromBase(string $path, string $basePath): string
{
    $trimmedPath = canonicalizeSiteRelativePath($path);
    $baseRoot = $basePath !== '' ? $basePath . '/' : '/';

    if ($trimmedPath === '') {
        return $baseRoot;
    }

    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $trimmedPath) || str_starts_with($trimmedPath, '//')) {
        return $trimmedPath;
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

function appPath(string $path = ''): string
{
    return buildAppPathFromBase($path, appBasePath());
}

function sitePath(string $path = ''): string
{
    return buildSitePathFromBase($path, siteBasePath());
}

function appEntryUrl(): string
{
    $configuredAppUrl = configuredAppUrl();
    if ($configuredAppUrl !== '') {
        return $configuredAppUrl;
    }

    $scheme = requestIsHttps() ? 'https' : 'http';
    $host = requestAuthority();
    return $scheme . '://' . $host . appBasePath();
}

function siteEntryUrl(): string
{
    $configuredSiteUrl = configuredSiteUrl();
    if ($configuredSiteUrl !== '') {
        return $configuredSiteUrl;
    }

    $scheme = requestIsHttps() ? 'https' : 'http';
    $host = requestAuthority();
    return $scheme . '://' . $host . siteBasePath();
}

function appUrl(string $path = ''): string
{
    $configuredAppUrl = configuredAppUrl();
    $origin = urlOrigin($configuredAppUrl !== '' ? $configuredAppUrl : appEntryUrl());
    $relativePath = buildAppPathFromBase(
        $path,
        $configuredAppUrl !== '' ? urlBasePath($configuredAppUrl) : appBasePath()
    );
    if ($origin === '') {
        return $relativePath;
    }

    return $origin . $relativePath;
}

function siteUrl(string $path = ''): string
{
    $configuredSiteUrl = configuredSiteUrl();
    $origin = urlOrigin($configuredSiteUrl !== '' ? $configuredSiteUrl : siteEntryUrl());
    $relativePath = buildSitePathFromBase(
        $path,
        $configuredSiteUrl !== '' ? urlBasePath($configuredSiteUrl) : siteBasePath()
    );
    if ($origin === '') {
        return $relativePath;
    }

    return $origin . $relativePath;
}

function requestUriPath(): string
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '/');
    return $path === '' ? '/' : $path;
}

function currentRequestQuerySuffix(): string
{
    $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
    return $query !== '' ? '?' . $query : '';
}

function requestTargetsConfiguredAppHost(): bool
{
    $configuredAppUrl = configuredAppUrl();
    return $configuredAppUrl !== '' && requestHostMatchesUrlHost($configuredAppUrl);
}

function requestWantsAppShell(): bool
{
    foreach (['auth', 'next', 'view', 'task', 'group', 'created_by', 'assignee', 'accounting_period', 'pwa'] as $key) {
        if (array_key_exists($key, $_GET) && trim((string) ($_GET[$key] ?? '')) !== '') {
            return true;
        }
    }

    $action = trim((string) ($_GET['action'] ?? ''));
    if ($action === '') {
        return false;
    }

    return !in_array($action, ['checkout', 'checkout_success'], true);
}

function requestShouldRedirectToConfiguredAppHost(): bool
{
    if (configuredAppUrl() === '' || requestTargetsConfiguredAppHost()) {
        return false;
    }

    $configuredSiteHost = bootstrapUrlHostName(configuredSiteUrl());
    $configuredAppHost = bootstrapUrlHostName(configuredAppUrl());
    if (
        $configuredSiteHost !== ''
        && $configuredAppHost !== ''
        && $configuredSiteHost === $configuredAppHost
    ) {
        return false;
    }

    $requestPath = strtolower(requestUriPath());
    return basename($requestPath) === 'index.php' || requestWantsAppShell();
}

function requestShouldServePublicHomeFromIndex(): bool
{
    if (requestWantsAppShell()) {
        return false;
    }

    $requestPath = rtrim(strtolower(requestUriPath()), '/');
    $appBasePath = rtrim(strtolower(appBasePath()), '/');
    $siteBasePath = rtrim(strtolower(siteBasePath()), '/');
    $isIndexRequest = $requestPath === ''
        || $requestPath === '/'
        || basename($requestPath) === 'index.php'
        || ($appBasePath !== '' && $requestPath === $appBasePath)
        || ($siteBasePath !== '' && $requestPath === $siteBasePath);
    if (!$isIndexRequest) {
        return false;
    }

    $configuredSiteHost = bootstrapUrlHostName(configuredSiteUrl());
    $configuredAppHost = bootstrapUrlHostName(configuredAppUrl());
    $hasSeparateHosts = $configuredSiteHost !== ''
        && $configuredAppHost !== ''
        && $configuredSiteHost !== $configuredAppHost;

    if ($hasSeparateHosts && requestTargetsConfiguredAppHost()) {
        return false;
    }

    return true;
}

function safeRedirectPath(?string $path, string $fallback = 'index.php'): string
{
    $rawPath = trim((string) $path);
    if ($rawPath === '') {
        return $fallback;
    }

    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $rawPath) || str_starts_with($rawPath, '//')) {
        return $fallback;
    }

    $normalizedPath = canonicalizeAppRelativePath($rawPath);
    if ($normalizedPath === '') {
        return $fallback;
    }

    $parsedPath = parse_url($normalizedPath);
    if ($parsedPath === false) {
        return $normalizedPath;
    }

    $fragment = trim((string) ($parsedPath['fragment'] ?? ''));
    if (!in_array($fragment, ['login', 'register', 'forgot-password', 'reset-password'], true)) {
        return $normalizedPath;
    }

    $queryParams = [];
    if (isset($parsedPath['query']) && trim((string) $parsedPath['query']) !== '') {
        parse_str((string) $parsedPath['query'], $queryParams);
    }

    $authPanel = trim((string) ($queryParams['auth'] ?? ''));
    $action = trim((string) ($queryParams['action'] ?? ''));
    $shouldKeepFragment = $authPanel !== '' || in_array($action, ['reset_password', 'workspace_invite'], true);
    if ($shouldKeepFragment) {
        return $normalizedPath;
    }

    $rebuiltPath = (string) ($parsedPath['path'] ?? '');
    $queryString = http_build_query($queryParams);
    if ($queryString !== '') {
        $rebuiltPath .= '?' . $queryString;
    }

    return $rebuiltPath !== '' ? $rebuiltPath : $fallback;
}
