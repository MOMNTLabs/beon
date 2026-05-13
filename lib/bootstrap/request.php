<?php
declare(strict_types=1);

function bootstrapRawEnvValue(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function bootstrapRequestIsHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $forwardedProto === 'https';
}

function bootstrapRequestHostName(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    return strtolower($host);
}

function bootstrapUrlHostName(string $url): string
{
    $host = trim((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    if ($host === '') {
        return '';
    }

    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    return strtolower($host);
}

function bootstrapRootCookieDomainCandidate(): string
{
    $configuredDomain = trim((string) bootstrapRawEnvValue('COOKIE_DOMAIN', ''));
    if ($configuredDomain !== '') {
        return ltrim(strtolower($configuredDomain), '.');
    }

    $siteHost = bootstrapUrlHostName((string) bootstrapRawEnvValue('SITE_URL', ''));
    if ($siteHost !== '') {
        return $siteHost;
    }

    $appHost = bootstrapUrlHostName((string) bootstrapRawEnvValue('APP_URL', ''));
    if ($appHost === '') {
        return '';
    }

    if (str_starts_with($appHost, 'app.') && substr_count($appHost, '.') >= 2) {
        return substr($appHost, 4);
    }

    return $appHost;
}

function bootstrapConfiguredCookieDomain(): string
{
    $currentHost = bootstrapRequestHostName();
    $rootDomain = bootstrapRootCookieDomainCandidate();
    if ($rootDomain === '') {
        return '';
    }

    if (
        $currentHost === $rootDomain
        || str_ends_with($currentHost, '.' . $rootDomain)
    ) {
        return $rootDomain;
    }

    return '';
}
