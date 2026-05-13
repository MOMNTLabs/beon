<?php
declare(strict_types=1);

function legalConfig(): array
{
    $supportEmail = legalContactEmail(envValue('LEGAL_SUPPORT_EMAIL') ?? envValue('MAIL_REPLY_TO'));

    $companyName = trim((string) envValue('LEGAL_COMPANY_NAME', ''));
    $tradeName = trim((string) envValue('LEGAL_TRADE_NAME', APP_NAME));

    return [
        'company_name' => $companyName,
        'trade_name' => $tradeName !== '' ? $tradeName : APP_NAME,
        'cnpj' => trim((string) envValue('LEGAL_CNPJ', '')),
        'address' => trim((string) envValue('LEGAL_ADDRESS', '')),
        'support_email' => $supportEmail,
        'privacy_email' => $supportEmail,
        'dpo_name' => trim((string) envValue('LEGAL_DPO_NAME', '')),
        'updated_at' => trim((string) envValue('LEGAL_UPDATED_AT', '29/04/2026')),
    ];
}

function legalContactEmail(?string $value, string $fallback = 'suporte@bexon.com.br'): string
{
    $email = trim((string) $value);
    if ($email === '' || stripos($email, 'workform') !== false) {
        return $fallback;
    }

    return $email;
}

function legalValue(string $key, string $fallback = 'A definir'): string
{
    $config = legalConfig();
    $value = trim((string) ($config[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
}

function legalMailto(string $subject): string
{
    $email = legalValue('privacy_email', legalValue('support_email', 'suporte@bexon.com.br'));
    return 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode($subject);
}
