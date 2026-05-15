<?php
declare(strict_types=1);

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

function logEmailDeliveryFallback(
    string $channel,
    string $filePath,
    array $context,
    string $body
): array {
    $contextLines = [];
    foreach ($context as $label => $value) {
        $normalizedLabel = trim((string) $label);
        if ($normalizedLabel === '') {
            continue;
        }

        $contextLines[] = $normalizedLabel . ': ' . trim((string) $value);
    }

    if (appCanWriteDiagnosticFiles()) {
        ensureStorage();
        $logEntry = implode("\n", array_merge([
            str_repeat('=', 72),
            'Timestamp: ' . nowIso(),
        ], $contextLines, [
            '',
            $body,
            '',
        ]));
        file_put_contents($filePath, $logEntry, FILE_APPEND | LOCK_EX);

        return [
            'sent' => false,
            'logged_to_file' => true,
            'logged_to_app_log' => false,
            'log_path' => $filePath,
            'log_channel' => $channel,
        ];
    }

    $summary = [];
    foreach ($context as $label => $value) {
        $normalizedLabel = strtolower(trim((string) $label));
        if (!in_array($normalizedLabel, ['to', 'subject', 'provider', 'provider error', 'workspace'], true)) {
            continue;
        }

        $summary[] = $normalizedLabel . '=' . preg_replace('/\s+/', ' ', trim((string) $value));
    }

    error_log(sprintf(
        '[mail-fallback:%s] Delivery unavailable. %s',
        $channel,
        implode(' | ', $summary)
    ));

    return [
        'sent' => false,
        'logged_to_file' => false,
        'logged_to_app_log' => true,
        'log_channel' => $channel,
    ];
}

function deliveryFallbackNotice(array $delivery, string $localRelativePath): string
{
    if (!empty($delivery['logged_to_file'])) {
        return ' Se o envio não estiver configurado neste ambiente, confira o arquivo ' . $localRelativePath . '.';
    }

    if (!empty($delivery['logged_to_app_log'])) {
        return ' Se o envio não estiver configurado neste ambiente, confira os logs da aplicação.';
    }

    return '';
}

function sendPasswordResetEmail(string $email, string $name, string $resetUrl, string $expiresAt): array
{
    $email = strtolower(trim($email));
    $name = trim($name);
    if ($email === '') {
        return ['sent' => false, 'logged_to_file' => false];
    }

    $subject = APP_NAME . ' | Redefinição de senha';
    $body = implode("\n", [
        'Oi' . ($name !== '' ? ' ' . $name : '') . ',',
        '',
        'Recebemos um pedido para redefinir a senha da sua conta no ' . APP_NAME . '.',
        'Use o link abaixo para cadastrar uma nova senha:',
        $resetUrl,
        '',
        'Este link expira em ' . $expiresAt . '.',
        'Se você não fez esse pedido, pode ignorar esta mensagem.',
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

    return logEmailDeliveryFallback(
        'password-reset',
        PASSWORD_RESET_LOG_PATH,
        [
            'To' => $email,
            'Subject' => $subject,
            'Expires At' => $expiresAt,
            'Provider' => (string) ($resendResult['provider'] ?? 'mail'),
            'Provider Error' => (string) ($resendResult['error'] ?? ($resendResult['response_body'] ?? '')),
        ],
        $body
    );
}

function sendWorkspaceInvitationEmail(
    string $email,
    string $workspaceName,
    string $inviterName,
    string $inviteUrl,
    string $expiresAt
): array {
    $email = strtolower(trim($email));
    $workspaceName = trim($workspaceName);
    $inviterName = trim($inviterName);
    if ($email === '') {
        return ['sent' => false, 'logged_to_file' => false];
    }

    $subject = APP_NAME . ' | Convite para workspace';
    $body = implode("\n", [
        'Oi,',
        '',
        ($inviterName !== '' ? $inviterName : 'Um administrador')
            . ' convidou você para acessar o workspace '
            . ($workspaceName !== '' ? '"' . $workspaceName . '"' : 'na ' . APP_NAME)
            . '.',
        'Use o link abaixo para entrar ou criar sua conta e aceitar o convite:',
        $inviteUrl,
        '',
        'Este link expira em ' . $expiresAt . '.',
        'Se você não esperava este convite, ignore esta mensagem.',
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

    return logEmailDeliveryFallback(
        'workspace-invite',
        WORKSPACE_INVITATION_LOG_PATH,
        [
            'To' => $email,
            'Subject' => $subject,
            'Workspace' => $workspaceName,
            'Expires At' => $expiresAt,
            'Provider' => (string) ($resendResult['provider'] ?? 'mail'),
            'Provider Error' => (string) ($resendResult['error'] ?? ($resendResult['response_body'] ?? '')),
        ],
        $body
    );
}
