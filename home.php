<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function stripeRequestForm(string $method, string $url, array $payload, string $secretKey): array
{
    $method = strtoupper(trim($method));
    if (!in_array($method, ['GET', 'POST'], true)) {
        throw new RuntimeException('Método Stripe inválido.');
    }

    $encodedPayload = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    $requestUrl = $url;
    $content = '';

    if ($method === 'GET' && $encodedPayload !== '') {
        $requestUrl .= (str_contains($requestUrl, '?') ? '&' : '?') . $encodedPayload;
    }

    if ($method === 'POST') {
        $content = $encodedPayload;
    }

    $headers = [
        'Authorization: Bearer ' . $secretKey,
    ];

    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Content-Length: ' . strlen($content);
    }

    $responseBody = '';
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($requestUrl);
        if ($ch === false) {
            throw new RuntimeException('Falha ao inicializar o cliente HTTP.');
        }

        $curlOptions = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ];

        if ($method === 'POST') {
            $curlOptions[CURLOPT_POSTFIELDS] = $content;
        }

        curl_setopt_array($ch, $curlOptions);

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Falha de conexão com Stripe: ' . $error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("
", $headers),
                'content' => $content,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($requestUrl, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException('Falha ao conectar com a API da Stripe.');
        }

        $responseHeaders = $http_response_header ?? [];
        foreach ($responseHeaders as $headerLine) {
            if (preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', (string) $headerLine, $matches)) {
                $statusCode = (int) ($matches[1] ?? 0);
                break;
            }
        }
    }

    $decoded = json_decode((string) $responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Resposta inválida recebida da Stripe.');
    }

    if ($statusCode >= 400) {
        $errorMessage = trim((string) ($decoded['error']['message'] ?? 'Não foi possível processar a requisição Stripe.'));
        throw new RuntimeException($errorMessage !== '' ? $errorMessage : 'Não foi possível processar a requisição Stripe.');
    }

    return $decoded;
}

function syncSubscriptionFromStripeSession(PDO $pdo, int $userId, array $checkoutSession): void
{
    if ($userId <= 0) {
        return;
    }

    $subscription = $checkoutSession['subscription'] ?? null;
    if (is_string($subscription) && $subscription !== '') {
        $subscription = ['id' => $subscription];
    }

    $rawPayload = json_encode($checkoutSession, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sessionMetadata = is_array($checkoutSession['metadata'] ?? null) ? $checkoutSession['metadata'] : [];
    $subscriptionMetadata = is_array($subscription['metadata'] ?? null) ? $subscription['metadata'] : [];
    $planAttributes = billingPlanAttributesFromStripeMetadata(array_merge($sessionMetadata, $subscriptionMetadata));

    upsertUserSubscription($pdo, $userId, [
        'stripe_customer_id' => trim((string) ($checkoutSession['customer'] ?? '')),
        'stripe_subscription_id' => trim((string) ($subscription['id'] ?? '')),
        'stripe_checkout_session_id' => trim((string) ($checkoutSession['id'] ?? '')),
        'plan_key' => $planAttributes['plan_key'] ?? '',
        'billing_interval' => $planAttributes['billing_interval'] ?? '',
        'max_users' => $planAttributes['max_users'] ?? 0,
        'subscription_status' => trim((string) ($subscription['status'] ?? 'inactive')) ?: 'inactive',
        'checkout_status' => trim((string) ($checkoutSession['status'] ?? '')),
        'trial_end' => stripeTimestampToIso($subscription['trial_end'] ?? null),
        'current_period_end' => stripeTimestampToIso($subscription['current_period_end'] ?? null),
        'cancel_at' => stripeTimestampToIso($subscription['cancel_at'] ?? null),
        'raw_payload_json' => is_string($rawPayload) && $rawPayload !== '' ? $rawPayload : '{}',
    ]);
}

function billingPlanCheckoutPath(string $planKey, string $billingInterval = 'year'): string
{
    return appPath(
        'home?action=checkout&plan='
        . rawurlencode(normalizeBillingPlanKey($planKey))
        . '&interval='
        . rawurlencode(normalizeBillingInterval($billingInterval))
    );
}

function billingPlanMailtoPath(array $plan): string
{
    $email = trim((string) ($plan['contact_email'] ?? 'suporte@bexon.com.br'));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = 'suporte@bexon.com.br';
    }

    $subject = rawurlencode('Consulta Enterprise - Bexon');
    $body = rawurlencode(
        "Olá, equipe Bexon.\n\nTenho interesse no plano Enterprise para uma equipe com mais de 15 usuários.\n\nNome:\nEmpresa:\nQuantidade aproximada de usuários:\nMensagem:"
    );

    return 'mailto:' . $email . '?subject=' . $subject . '&body=' . $body;
}

function billingPlanActionPath(array $plan, string $billingInterval = 'year'): string
{
    if (($plan['checkout_enabled'] ?? true) === false || trim((string) ($plan['contact_email'] ?? '')) !== '') {
        return billingPlanMailtoPath($plan);
    }

    return billingPlanCheckoutPath((string) ($plan['key'] ?? 'solo'), $billingInterval);
}

function billingMoneyLabel(int $amountCents, bool $compactWholeAmount = false): string
{
    $amountCents = max(0, $amountCents);
    if ($compactWholeAmount && $amountCents % 100 === 0) {
        return 'R$ ' . number_format($amountCents / 100, 0, ',', '.');
    }

    return 'R$ ' . number_format($amountCents / 100, 2, ',', '.');
}

function billingPriceParts(string $priceLabel): array
{
    $priceLabel = trim($priceLabel);
    if (preg_match('/^R\$\s*(.+)$/u', $priceLabel, $matches)) {
        return [
            'currency' => 'R$',
            'amount' => trim((string) ($matches[1] ?? '')),
        ];
    }

    return [
        'currency' => '',
        'amount' => $priceLabel,
    ];
}

function billingPlanPriceLabel(array $plan, string $billingInterval = 'year'): string
{
    $customPriceLabel = trim((string) ($plan['price_label'] ?? ''));
    if ($customPriceLabel !== '') {
        return $customPriceLabel;
    }

    $billingInterval = normalizeBillingInterval($billingInterval);
    $priceCents = $billingInterval === 'year'
        ? billingPlanAnnualMonthlyEquivalentCents($plan)
        : billingPlanChargeCents($plan, 'month');

    return billingMoneyLabel($priceCents);
}

function billingPlanPriceSuffix(array $plan): string
{
    return trim((string) ($plan['price_label'] ?? '')) !== '' ? '' : '/mês';
}

function billingPlanBillingNote(array $plan, string $billingInterval = 'year'): string
{
    if (trim((string) ($plan['price_label'] ?? '')) !== '') {
        return '';
    }

    if (normalizeBillingInterval($billingInterval) === 'year') {
        return 'cobrado anualmente ' . billingMoneyLabel(billingPlanChargeCents($plan, 'year'), true);
    }

    return 'cobrança mensal';
}

function billingPlanUsersLabel(array $plan): string
{
    $customUsersLabel = trim((string) ($plan['users_label'] ?? ''));
    if ($customUsersLabel !== '') {
        return $customUsersLabel;
    }

    $maxUsers = max(0, (int) ($plan['max_users'] ?? 0));
    if ($maxUsers <= 1) {
        return '1 usuário';
    }

    return 'Até ' . $maxUsers . ' usuários';
}

function billingPlanTrialNote(array $plan, string $billingInterval = 'year'): string
{
    $customTrialNote = trim((string) ($plan['trial_note'] ?? ''));
    if ($customTrialNote !== '') {
        return $customTrialNote;
    }

    if (normalizeBillingInterval($billingInterval) === 'year') {
        return (string) ($plan['annual_savings_label'] ?? 'Economize no anual');
    }

    return '7 dias grátis para testar';
}

function stripePriceEnvKeyForPlan(string $planKey, string $billingInterval = 'year'): string
{
    $planEnvKey = strtoupper(normalizeBillingPlanKey($planKey));
    return normalizeBillingInterval($billingInterval) === 'year'
        ? 'STRIPE_' . $planEnvKey . '_ANNUAL_PRICE_ID'
        : 'STRIPE_' . $planEnvKey . '_PRICE_ID';
}

function stripePriceEnvKeysForPlan(string $planKey, string $billingInterval = 'year'): array
{
    $planEnvKey = strtoupper(normalizeBillingPlanKey($planKey));
    if (normalizeBillingInterval($billingInterval) === 'year') {
        return [
            'STRIPE_' . $planEnvKey . '_ANNUAL_PRICE_ID',
            'STRIPE_' . $planEnvKey . '_YEARLY_PRICE_ID',
        ];
    }

    return [
        'STRIPE_' . $planEnvKey . '_MONTHLY_PRICE_ID',
        'STRIPE_' . $planEnvKey . '_PRICE_ID',
    ];
}

function configuredStripeBillingIdForPlan(string $planKey, string $billingInterval = 'year'): string
{
    $planKey = normalizeBillingPlanKey($planKey);
    $billingInterval = normalizeBillingInterval($billingInterval);
    foreach (stripePriceEnvKeysForPlan($planKey, $billingInterval) as $envKey) {
        $planPriceId = trim((string) (envValue($envKey) ?? ''));
        if ($planPriceId !== '') {
            return $planPriceId;
        }
    }

    if ($billingInterval === 'month' && $planKey === billingDefaultPlanKey()) {
        $legacyPriceId = trim((string) (envValue('STRIPE_PRICE_ID') ?? ''));
        if ($legacyPriceId !== '') {
            return $legacyPriceId;
        }
    }

    return trim((string) (envValue('STRIPE_PRODUCT_ID') ?? ''));
}

function stripePriceMatchesBillingPlan(array $price, array $plan, string $billingInterval = 'year'): bool
{
    $planKey = normalizeBillingPlanKey((string) ($plan['key'] ?? ''), null);
    if ($planKey === '') {
        return false;
    }

    $metadata = is_array($price['metadata'] ?? null) ? $price['metadata'] : [];
    $metadataAttributes = billingPlanAttributesFromStripeMetadata($metadata);
    $metadataPlanKey = (string) ($metadataAttributes['plan_key'] ?? '');
    if ($metadataPlanKey === $planKey) {
        $metadataInterval = (string) ($metadataAttributes['billing_interval'] ?? '');
        if ($metadataInterval !== '' && $metadataInterval !== normalizeBillingInterval($billingInterval)) {
            return false;
        }

        return true;
    }

    $candidates = [
        (string) ($price['lookup_key'] ?? ''),
        (string) ($price['nickname'] ?? ''),
    ];
    foreach ($candidates as $candidate) {
        if (normalizeBillingPlanKey($candidate, null) === $planKey) {
            return true;
        }
    }

    return false;
}

function stripePriceIdFromProductForPlan(string $productId, array $plan, string $secretKey, string $billingInterval = 'year'): string
{
    $productId = trim($productId);
    if ($productId === '' || !str_starts_with($productId, 'prod_')) {
        return '';
    }

    $billingInterval = normalizeBillingInterval($billingInterval);
    $prices = stripeRequestForm(
        'GET',
        'https://api.stripe.com/v1/prices',
        [
            'product' => $productId,
            'active' => 'true',
            'type' => 'recurring',
            'limit' => 100,
        ],
        $secretKey
    );

    foreach ((array) ($prices['data'] ?? []) as $price) {
        if (!is_array($price) || !stripePriceMatchesBillingPlan($price, $plan, $billingInterval)) {
            continue;
        }

        $recurring = is_array($price['recurring'] ?? null) ? $price['recurring'] : [];
        if (($recurring['interval'] ?? '') !== $billingInterval) {
            continue;
        }

        $priceId = trim((string) ($price['id'] ?? ''));
        if (str_starts_with($priceId, 'price_')) {
            return $priceId;
        }
    }

    return '';
}

function stripeLineItemForBillingPlan(array $plan, string $secretKey, string $billingInterval = 'year'): array
{
    $planKey = normalizeBillingPlanKey((string) ($plan['key'] ?? ''));
    $billingInterval = normalizeBillingInterval($billingInterval);
    $stripeBillingId = configuredStripeBillingIdForPlan($planKey, $billingInterval);
    if ($stripeBillingId === '') {
        throw new RuntimeException(sprintf(
            'Preço Stripe não configurado para o plano %s. Defina %s ou STRIPE_PRODUCT_ID.',
            (string) ($plan['name'] ?? $planKey),
            stripePriceEnvKeyForPlan($planKey, $billingInterval)
        ));
    }

    $lineItem = ['quantity' => 1];
    if (str_starts_with($stripeBillingId, 'price_')) {
        $lineItem['price'] = $stripeBillingId;
        return $lineItem;
    }

    if (!str_starts_with($stripeBillingId, 'prod_')) {
        throw new RuntimeException('ID Stripe inválido. Use price_... nas variáveis de plano ou prod_... em STRIPE_PRODUCT_ID.');
    }

    $resolvedPriceId = stripePriceIdFromProductForPlan($stripeBillingId, $plan, $secretKey, $billingInterval);
    if ($resolvedPriceId !== '') {
        $lineItem['price'] = $resolvedPriceId;
        return $lineItem;
    }

    $lineItem['price_data'] = [
        'currency' => 'brl',
        'unit_amount' => billingPlanChargeCents($plan, $billingInterval),
        'product' => $stripeBillingId,
        'recurring' => [
            'interval' => $billingInterval,
        ],
    ];

    return $lineItem;
}

$stylesAssetVersion = is_file(__DIR__ . '/assets/styles.css')
    ? (string) filemtime(__DIR__ . '/assets/styles.css')
    : '1';
$themeBexonAssetVersion = is_file(__DIR__ . '/assets/theme-bexon.css')
    ? (string) filemtime(__DIR__ . '/assets/theme-bexon.css')
    : '1';
$salesAssetVersion = is_file(__DIR__ . '/assets/home.css')
    ? (string) filemtime(__DIR__ . '/assets/home.css')
    : '1';
$pdo = db();
$billingPlans = publicBillingPlanDefinitions();
$checkoutBillingPlans = array_filter(
    $billingPlans,
    static fn (array $plan): bool => ($plan['checkout_enabled'] ?? true) !== false
);
$defaultPlanKey = billingDefaultPlanKey();
$defaultPlanKey = isset($checkoutBillingPlans[$defaultPlanKey]) ? $defaultPlanKey : 'solo';
$defaultBillingInterval = billingDefaultInterval();
$rawRequestedPlanKey = normalizeBillingPlanKey((string) ($_GET['plan'] ?? $defaultPlanKey));
$rawRequestedBillingInterval = normalizeBillingInterval((string) ($_GET['interval'] ?? $defaultBillingInterval));
$requestedPlanKey = isset($checkoutBillingPlans[$rawRequestedPlanKey]) ? $rawRequestedPlanKey : $defaultPlanKey;
$requestedBillingInterval = $rawRequestedBillingInterval;
$selectedPlan = $checkoutBillingPlans[$requestedPlanKey] ?? $checkoutBillingPlans[$defaultPlanKey] ?? $checkoutBillingPlans['solo'];
$recommendedCheckoutPath = billingPlanCheckoutPath('solo', $defaultBillingInterval);
$checkoutAction = trim((string) ($_GET['action'] ?? ''));
$authPath = appPath('?auth=login#login');
$appEntryPath = $authPath;

if ($checkoutAction === 'checkout') {
    $requestedPublicPlan = $billingPlans[$rawRequestedPlanKey] ?? null;
    if (is_array($requestedPublicPlan) && ($requestedPublicPlan['checkout_enabled'] ?? true) === false) {
        redirectTo('home#planos');
    }

    $checkoutUser = currentUser();
    if (!$checkoutUser) {
        $pendingCheckoutUserId = pendingCheckoutUserId();
        $checkoutUser = $pendingCheckoutUserId !== null ? userById($pendingCheckoutUserId) : null;
    }

    if (!$checkoutUser) {
        $checkoutNextPath = 'home?action=checkout&plan='
            . rawurlencode($requestedPlanKey)
            . '&interval='
            . rawurlencode($requestedBillingInterval);
        redirectTo('index.php?auth=login&next=' . urlencode($checkoutNextPath) . '#login');
    }

    $checkoutUserId = (int) ($checkoutUser['id'] ?? 0);
    if ($checkoutUserId > 0 && userHasBillingAccess($checkoutUserId)) {
        redirectTo('index.php');
    }

    try {
        $stripeSecretKey = trim((string) (envValue('STRIPE_SECRET_KEY') ?? envValue('STRIPE_API_KEY') ?? ''));
        if ($stripeSecretKey === '') {
            throw new RuntimeException('Checkout Stripe não configurado. Defina STRIPE_SECRET_KEY no ambiente.');
        }
        $userId = $checkoutUserId;
        $successUrl = appEntryUrl() . appPath('home?action=checkout_success&session_id={CHECKOUT_SESSION_ID}');
        $cancelUrl = appEntryUrl() . appPath('home?checkout=cancelled');
        $trialPeriodDays = billingTrialPeriodDays();
        $planMetadata = billingPlanMetadata($selectedPlan, $requestedBillingInterval);

        $lineItem = stripeLineItemForBillingPlan($selectedPlan, $stripeSecretKey, $requestedBillingInterval);

        $checkoutPayload = [
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'locale' => 'pt-BR',
            'line_items' => [$lineItem],
            'client_reference_id' => (string) $userId,
            'metadata' => array_merge([
                'bexon_user_id' => (string) $userId,
            ], $planMetadata),
            'subscription_data' => [
                'metadata' => array_merge([
                    'bexon_user_id' => (string) $userId,
                ], $planMetadata),
            ],
        ];
        if ($trialPeriodDays > 0 && (int) ($selectedPlan['price_cents'] ?? 0) > 0) {
            $checkoutPayload['subscription_data']['trial_period_days'] = $trialPeriodDays;
        }

        if (!empty($checkoutUser['email'])) {
            $checkoutPayload['customer_email'] = (string) $checkoutUser['email'];
        }

        $checkoutSession = stripeRequestForm('POST', 'https://api.stripe.com/v1/checkout/sessions', $checkoutPayload, $stripeSecretKey);

        upsertUserSubscription($pdo, $userId, [
            'stripe_customer_id' => trim((string) ($checkoutSession['customer'] ?? '')),
            'stripe_checkout_session_id' => trim((string) ($checkoutSession['id'] ?? '')),
            'plan_key' => $planMetadata['bexon_plan'] ?? '',
            'billing_interval' => $planMetadata['bexon_billing_interval'] ?? '',
            'max_users' => (int) ($planMetadata['bexon_max_users'] ?? 0),
            'subscription_status' => 'inactive',
            'checkout_status' => 'pending_checkout',
            'raw_payload_json' => json_encode($checkoutSession, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);

        $checkoutUrl = trim((string) ($checkoutSession['url'] ?? ''));
        if ($checkoutUrl === '') {
            throw new RuntimeException('A Stripe não retornou a URL do checkout.');
        }

        getFlashes();
        logoutUser();
        header('Location: ' . $checkoutUrl);
        exit;
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirectTo('home');
    }
}

if ($checkoutAction === 'checkout_success') {
    try {
        $stripeSecretKey = trim((string) (envValue('STRIPE_SECRET_KEY') ?? envValue('STRIPE_API_KEY') ?? ''));
        if ($stripeSecretKey === '') {
            throw new RuntimeException('Checkout Stripe não configurado. Defina STRIPE_SECRET_KEY no ambiente.');
        }

        $sessionId = trim((string) ($_GET['session_id'] ?? ''));
        if ($sessionId === '') {
            throw new RuntimeException('Sessão de checkout não informada.');
        }

        $checkoutSession = stripeRequestForm(
            'GET',
            'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId),
            ['expand[]' => 'subscription'],
            $stripeSecretKey
        );

        $currentUser = currentUser();
        $checkoutUserId = (int) ($currentUser['id'] ?? 0);
        if ($checkoutUserId <= 0) {
            $metadata = is_array($checkoutSession['metadata'] ?? null) ? $checkoutSession['metadata'] : [];
            $checkoutUserId = (int) ($metadata['bexon_user_id'] ?? ($checkoutSession['client_reference_id'] ?? 0));
        }
        if ($checkoutUserId <= 0) {
            throw new RuntimeException('Não foi possível identificar a conta do checkout.');
        }

        syncSubscriptionFromStripeSession($pdo, $checkoutUserId, $checkoutSession);
        logoutUser();
        redirectTo('home?checkout=success');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirectTo('home');
    }
}

$checkoutStatus = trim((string) ($_GET['checkout'] ?? ''));
$checkoutNotice = null;
if ($checkoutStatus === 'success') {
    $checkoutNotice = [
        'type' => 'success',
        'message' => 'Checkout concluído. Seu plano Bexon foi ativado.',
    ];
} elseif ($checkoutStatus === 'cancelled') {
    $checkoutNotice = [
        'type' => 'info',
        'message' => 'Checkout cancelado. Você pode tentar novamente quando quiser.',
    ];
} elseif ($checkoutStatus === 'required') {
    $checkoutNotice = [
        'type' => 'info',
        'message' => 'Seu checkout ainda não foi concluído. Escolha um plano para liberar o acesso ao app.',
    ];
}

$flashes = getFlashes();
$salesFlashes = $flashes;
if ($checkoutNotice) {
    array_unshift($salesFlashes, $checkoutNotice);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> - Home</title>
    <link rel="icon" type="image/png" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="shortcut icon" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(appPath('assets/styles.css?v=' . $stylesAssetVersion)) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/theme-bexon.css?v=' . $themeBexonAssetVersion)) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/home.css?v=' . $salesAssetVersion)) ?>">
</head>
<body class="is-sales-page">
    <?php if (!empty($salesFlashes)): ?>
        <div class="flash-stack" aria-live="polite">
            <?php foreach ($salesFlashes as $flash): ?>
                <div class="flash flash-<?= e((string) ($flash['type'] ?? 'info')) ?>" data-flash>
                    <span><?= e((string) ($flash['message'] ?? '')) ?></span>
                    <button type="button" class="flash-close" data-flash-close aria-label="Fechar">×</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="sales-page" id="top">
        <header class="sales-header">
            <div class="sales-container sales-header-inner">
                <a href="<?= e(appPath('home')) ?>" class="sales-brand" aria-label="<?= e(APP_NAME) ?>">
                    <img src="<?= e(appPath('assets/Bexon - Logo Horizontal.png?v=1')) ?>" alt="<?= e(APP_NAME) ?>">
                </a>
                <nav class="sales-nav" aria-label="Navega&ccedil;&atilde;o principal">
                    <a href="#recursos">Recursos</a>
                    <a href="#uso">Para quem</a>
                    <a href="#fluxo">Como funciona</a>
                    <a href="#planos">Planos</a>
                </nav>
                <div class="sales-header-actions">
                    <a href="<?= e($authPath) ?>" class="sales-btn sales-btn-ghost">Entrar</a>
                    <a href="<?= e($recommendedCheckoutPath) ?>" class="sales-btn sales-btn-primary">Testar 7 dias gr&aacute;tis</a>
                </div>
            </div>
        </header>

        <main>
            <section class="sales-hero">
                <div class="sales-container sales-hero-grid">
                    <div class="sales-hero-copy">
                        <h1>Organize sua rotina, seu neg&oacute;cio e sua equipe em um s&oacute; fluxo.</h1>
                        <p>
                            O Bexon centraliza tarefas, clientes, entregas e prioridades para voc&ecirc; trabalhar
                            sozinho ou com o time sem perder clareza.
                        </p>
                        <div class="sales-hero-actions">
                            <a href="<?= e($recommendedCheckoutPath) ?>" class="sales-btn sales-btn-primary">Testar 7 dias gr&aacute;tis</a>
                        </div>
                        <div class="sales-explore-pills" aria-label="Explorar formas de uso do Bexon">
                            <a href="#uso" data-sales-jump-scenario="pessoal">Pessoal</a>
                            <a href="#uso" data-sales-jump-scenario="negocio">Neg&oacute;cio</a>
                            <a href="#uso" data-sales-jump-scenario="equipe">Equipe</a>
                            <a href="#uso" data-sales-jump-scenario="clientes">Clientes</a>
                        </div>
                        <ul class="sales-trust-list">
                            <li>7 dias gr&aacute;tis em Solo, Team e Business</li>
                            <li>Solo anual por R$ 16,40/m&ecirc;s</li>
                            <li>Enterprise sob consulta para equipes maiores</li>
                        </ul>
                    </div>

                    <div class="sales-product-showcase" aria-hidden="true">
                        <div class="sales-product-shell">
                            <aside class="sales-product-sidebar">
                                <div class="sales-product-sidebar-brand">
                                    <img src="<?= e(appPath('assets/Bexon - Logo Horizontal Negativa.png?v=1')) ?>" alt="" decoding="async">
                                </div>
                                <span class="sales-product-sidebar-line"></span>
                                <span class="sales-product-home-dot"></span>
                                <div class="sales-product-workspace">
                                    <span>T</span>
                                    <strong>Teste MCP Works...</strong>
                                    <i></i>
                                </div>
                                <div class="sales-product-menu-item is-active">
                                    <i></i>
                                    <span>Lista de tarefas</span>
                                </div>
                                <span class="sales-product-add">+</span>
                            </aside>
                            <div class="sales-product-app">
                                <div class="sales-product-topbar">
                                    <div class="sales-product-stats">
                                        <span><small>Tarefas</small><strong>8</strong></span>
                                        <span><small>Conclu&iacute;das</small><strong>3 (38%)</strong></span>
                                        <span><small>Para hoje</small><strong>4</strong></span>
                                        <span><small>Urgentes</small><strong>1</strong></span>
                                        <span><small>Minhas abertas</small><strong>5</strong></span>
                                    </div>
                                    <div class="sales-product-user">
                                        <span>T</span>
                                        <div>
                                            <strong>Teste MCP</strong>
                                            <small>teste@bexon.com.br</small>
                                        </div>
                                    </div>
                                    <div class="sales-product-actions">
                                        <span class="sales-product-icon-bubble sales-product-icon-bell"></span>
                                        <span class="sales-product-icon-bubble sales-product-icon-gear"></span>
                                        <span class="sales-product-exit">Sair</span>
                                    </div>
                                </div>
                                <div class="sales-product-body">
                                    <header class="sales-product-heading">
                                        <div>
                                            <h3>Lista de tarefas</h3>
                                            <p>Organize prioridades, respons&aacute;veis e prazos em um fluxo claro.</p>
                                        </div>
                                        <div class="sales-product-summary">
                                            <span>4 vis&iacute;veis</span>
                                            <span>8 total</span>
                                        </div>
                                    </header>
                                    <section class="sales-product-panel">
                                        <div class="sales-product-panel-head">
                                            <strong>Operacional</strong>
                                            <span>4</span>
                                        </div>
                                        <div class="sales-task-row status-todo">
                                            <span class="sales-task-status-dot"></span>
                                            <strong>Revisar propostas</strong>
                                            <span class="sales-task-stepper">
                                                <i></i>
                                                <span class="sales-task-status-pill"><b></b>A fazer</span>
                                                <i></i>
                                            </span>
                                            <span class="sales-task-avatar">T</span>
                                            <span class="sales-task-actions"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                                        </div>
                                        <div class="sales-task-row status-progress">
                                            <span class="sales-task-status-dot"></span>
                                            <strong>Entrega do projeto</strong>
                                            <span class="sales-task-stepper">
                                                <i></i>
                                                <span class="sales-task-status-pill"><b></b>Em andamento</span>
                                                <i></i>
                                            </span>
                                            <span class="sales-task-avatar">M</span>
                                            <span class="sales-task-actions"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                                        </div>
                                        <div class="sales-task-row status-review">
                                            <span class="sales-task-status-dot"></span>
                                            <strong>Validar financeiro</strong>
                                            <span class="sales-task-stepper">
                                                <i></i>
                                                <span class="sales-task-status-pill"><b></b>Revis&atilde;o</span>
                                                <i></i>
                                            </span>
                                            <span class="sales-task-avatar">V</span>
                                            <span class="sales-task-actions"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                                        </div>
                                        <div class="sales-task-row status-done">
                                            <span class="sales-task-status-dot"></span>
                                            <strong>Atualizar clientes</strong>
                                            <span class="sales-task-stepper">
                                                <i></i>
                                                <span class="sales-task-status-pill"><b></b>Conclu&iacute;do</span>
                                                <i></i>
                                            </span>
                                            <span class="sales-task-avatar">C</span>
                                            <span class="sales-task-actions"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="uso" class="sales-section sales-topic sales-topic-soft">
                <div class="sales-container">
                    <div class="sales-section-head sales-section-head-center">
                        <span class="sales-eyebrow">O que voc&ecirc; quer organizar?</span>
                        <h2>Escolha o contexto e veja como o Bexon se adapta &agrave; sua rotina.</h2>
                    </div>
                    <div class="sales-workflow-explorer" data-sales-explorer>
                        <div class="sales-explorer-tabs" role="tablist" aria-label="Contextos de uso do Bexon">
                            <button type="button" class="is-active" role="tab" aria-selected="true" data-sales-scenario="pessoal" data-explorer-title="Priorize sua semana sem misturar tudo." data-explorer-text="Separe compromissos, pend&ecirc;ncias e tarefas pessoais sem perder o que tamb&eacute;m pertence ao trabalho." data-explorer-tasks="Revisar agenda da semana|Resolver pend&ecirc;ncias pessoais|Planejar foco de amanh&atilde;">Pessoal</button>
                            <button type="button" role="tab" aria-selected="false" data-sales-scenario="negocio" data-explorer-title="Acompanhe a opera&ccedil;&atilde;o com uma vis&atilde;o clara." data-explorer-text="Centralize demandas do neg&oacute;cio, clientes e entregas para saber o que precisa sair hoje." data-explorer-tasks="Atualizar pipeline de clientes|Conferir entrega em andamento|Organizar fluxo financeiro">Neg&oacute;cio</button>
                            <button type="button" role="tab" aria-selected="false" data-sales-scenario="equipe" data-explorer-title="Delegue sem perder o contexto." data-explorer-text="Distribua responsabilidades, acompanhe status e revise o trabalho do time sem excesso de reuni&otilde;es." data-explorer-tasks="Atribuir respons&aacute;veis|Revisar bloqueios do time|Acompanhar prazos da semana">Equipe</button>
                            <button type="button" role="tab" aria-selected="false" data-sales-scenario="clientes" data-explorer-title="Mantenha clientes e pr&oacute;ximas a&ccedil;&otilde;es vis&iacute;veis." data-explorer-text="Transforme contatos, propostas e acompanhamentos em tarefas claras dentro da mesma rotina." data-explorer-tasks="Responder novo contato|Enviar proposta comercial|Agendar retorno com cliente">Clientes</button>
                        </div>
                        <div class="sales-explorer-panel">
                            <div class="sales-explorer-copy">
                                <span data-sales-scenario-label>Pessoal</span>
                                <h3 data-sales-scenario-title>Priorize sua semana sem misturar tudo.</h3>
                                <p data-sales-scenario-text>Separe compromissos, pend&ecirc;ncias e tarefas pessoais sem perder o que tamb&eacute;m pertence ao trabalho.</p>
                                <ul data-sales-scenario-list>
                                    <li>Revisar agenda da semana</li>
                                    <li>Resolver pend&ecirc;ncias pessoais</li>
                                    <li>Planejar foco de amanh&atilde;</li>
                                </ul>
                            </div>
                            <div class="sales-scenario-preview" aria-hidden="true">
                                <div class="sales-scenario-window">
                                    <div class="sales-scenario-header">
                                        <span>Quadro Bexon</span>
                                        <strong>Em foco</strong>
                                    </div>
                                    <div class="sales-scenario-card is-active" data-sales-preview-card>Revisar agenda da semana</div>
                                    <div class="sales-scenario-card" data-sales-preview-card>Resolver pend&ecirc;ncias pessoais</div>
                                    <div class="sales-scenario-card" data-sales-preview-card>Planejar foco de amanh&atilde;</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="recursos" class="sales-section sales-topic sales-topic-soft">
                <div class="sales-container">
                    <div class="sales-section-head sales-section-head-right sales-section-head-alt">
                        <span class="sales-eyebrow">Recursos principais</span>
                        <h2>Mais organiza&ccedil;&atilde;o, menos fric&ccedil;&atilde;o na rotina pessoal e profissional.</h2>
                    </div>
                    <div class="sales-feature-grid">
                        <article class="sales-feature-card">
                            <span class="sales-feature-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M4 6h16"></path>
                                    <path d="M4 12h10"></path>
                                    <path d="M4 18h7"></path>
                                    <circle cx="18" cy="12" r="2"></circle>
                                </svg>
                            </span>
                            <h3>Quadro &uacute;nico e flex&iacute;vel</h3>
                            <p>Junte tarefas pessoais, do neg&oacute;cio e da equipe em um s&oacute; fluxo visual.</p>
                        </article>
                        <article class="sales-feature-card">
                            <span class="sales-feature-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="3" y="4" width="18" height="16" rx="3"></rect>
                                    <path d="M8 9h8"></path>
                                    <path d="M8 13h5"></path>
                                </svg>
                            </span>
                            <h3>Organiza&ccedil;&atilde;o por contexto</h3>
                            <p>Separe por prioridade, status e respons&aacute;vel sem criar um sistema complexo.</p>
                        </article>
                        <article class="sales-feature-card">
                            <span class="sales-feature-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M4 12h6l2.2 3L15 9l2 3h3"></path>
                                    <path d="M4 5h16v14H4z"></path>
                                </svg>
                            </span>
                            <h3>Execu&ccedil;&atilde;o sem ru&iacute;do</h3>
                            <p>Saiba o que fazer agora, o que delegar e o que revisar com poucos cliques.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="fluxo" class="sales-section sales-topic sales-topic-contrast">
                <div class="sales-container sales-steps-shell">
                    <div class="sales-section-head sales-section-head-alt">
                        <span class="sales-eyebrow">Como funciona</span>
                        <h2>Tr&ecirc;s passos para simplificar sua organiza&ccedil;&atilde;o pessoal e do time.</h2>
                    </div>
                    <div class="sales-steps-grid">
                        <article class="sales-step-card">
                            <span>01</span>
                            <h3>Comece em minutos</h3>
                            <p>Escolha um plano e crie seu fluxo inicial sem configura&ccedil;&otilde;es complexas.</p>
                        </article>
                        <article class="sales-step-card">
                            <span>02</span>
                            <h3>Organize por contexto</h3>
                            <p>Separe tarefas pessoais, operacionais e da equipe com status e prioridades claras.</p>
                        </article>
                        <article class="sales-step-card">
                            <span>03</span>
                            <h3>Execute com clareza</h3>
                            <p>Mantenha foco no que importa e acompanhe entregas sem perder tempo com ferramenta.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="planos" class="sales-section sales-topic sales-topic-soft">
                <div class="sales-container">
                    <div class="sales-section-head sales-section-head-center">
                        <span class="sales-eyebrow">Planos</span>
                        <h2>Organize sua rotina com o plano ideal para voc&ecirc; ou sua equipe.</h2>
                        <p>Teste gr&aacute;tis por 7 dias. Sem compromisso.</p>
                    </div>
                    <div class="sales-billing-toggle" data-billing-toggle data-default-billing-interval="<?= e($defaultBillingInterval) ?>" aria-label="Alternar cobrança">
                        <button type="button" class="<?= $defaultBillingInterval === 'year' ? 'is-active' : '' ?>" data-billing-interval="year" aria-pressed="<?= $defaultBillingInterval === 'year' ? 'true' : 'false' ?>">
                            Anual
                            <span>Economize até 2 meses</span>
                        </button>
                        <button type="button" class="<?= $defaultBillingInterval === 'month' ? 'is-active' : '' ?>" data-billing-interval="month" aria-pressed="<?= $defaultBillingInterval === 'month' ? 'true' : 'false' ?>">
                            Mensal
                        </button>
                    </div>
                    <div class="sales-pricing-grid">
                        <?php foreach ($billingPlans as $billingPlan): ?>
                            <?php
                            $billingPlanKey = (string) ($billingPlan['key'] ?? '');
                            $isHighlightedPlan = $billingPlanKey === 'solo';
                            $monthlyPriceLabel = billingPlanPriceLabel($billingPlan, 'month');
                            $annualPriceLabel = billingPlanPriceLabel($billingPlan, 'year');
                            $monthlyBillingNote = billingPlanBillingNote($billingPlan, 'month');
                            $annualBillingNote = billingPlanBillingNote($billingPlan, 'year');
                            $monthlyTrialNote = billingPlanTrialNote($billingPlan, 'month');
                            $annualTrialNote = billingPlanTrialNote($billingPlan, 'year');
                            $monthlyActionPath = billingPlanActionPath($billingPlan, 'month');
                            $annualActionPath = billingPlanActionPath($billingPlan, 'year');
                            $priceSuffix = billingPlanPriceSuffix($billingPlan);
                            $initialPriceLabel = $defaultBillingInterval === 'month' ? $monthlyPriceLabel : $annualPriceLabel;
                            $initialBillingNote = $defaultBillingInterval === 'month' ? $monthlyBillingNote : $annualBillingNote;
                            $initialTrialNote = $defaultBillingInterval === 'month' ? $monthlyTrialNote : $annualTrialNote;
                            $initialActionPath = $defaultBillingInterval === 'month' ? $monthlyActionPath : $annualActionPath;
                            $initialPriceParts = billingPriceParts($initialPriceLabel);
                            ?>
                            <article
                                class="sales-pricing-card<?= $isHighlightedPlan ? ' is-highlight' : '' ?>"
                                data-plan-card
                                data-price-month="<?= e($monthlyPriceLabel) ?>"
                                data-price-year="<?= e($annualPriceLabel) ?>"
                                data-suffix="<?= e($priceSuffix) ?>"
                                data-note-month="<?= e($monthlyBillingNote) ?>"
                                data-note-year="<?= e($annualBillingNote) ?>"
                                data-trial-month="<?= e($monthlyTrialNote) ?>"
                                data-trial-year="<?= e($annualTrialNote) ?>"
                                data-action-month="<?= e($monthlyActionPath) ?>"
                                data-action-year="<?= e($annualActionPath) ?>"
                            >
                                <div class="sales-pricing-card-head">
                                    <h3><?= e((string) ($billingPlan['name'] ?? 'Plano')) ?></h3>
                                    <?php if ($isHighlightedPlan): ?>
                                        <span class="sales-plan-badge"><?= e((string) ($billingPlan['badge'] ?? billingPlanUsersLabel($billingPlan))) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="sales-plan-pricing">
                                    <p class="sales-price<?= $priceSuffix === '' ? ' is-consult-price' : '' ?>">
                                        <span data-plan-price-value>
                                            <?php if ($initialPriceParts['currency'] !== ''): ?>
                                                <span class="sales-price-currency"><?= e($initialPriceParts['currency']) ?></span>
                                            <?php endif; ?>
                                            <span class="sales-price-amount"><?= e($initialPriceParts['amount']) ?></span>
                                        </span>
                                        <?php if ($priceSuffix !== ''): ?>
                                            <span data-plan-price-suffix><?= e($priceSuffix) ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($initialBillingNote !== ''): ?>
                                        <p class="sales-billing-note" data-plan-billing-note><?= e($initialBillingNote) ?></p>
                                    <?php else: ?>
                                        <p class="sales-billing-note" data-plan-billing-note hidden></p>
                                    <?php endif; ?>
                                    <p class="sales-trial-note" data-plan-trial-note><?= e($initialTrialNote) ?></p>
                                </div>
                                <p class="sales-price-note"><?= e((string) ($billingPlan['summary'] ?? '')) ?></p>
                                <p class="sales-plan-limit"><?= e(billingPlanUsersLabel($billingPlan)) ?></p>
                                <a href="<?= e($initialActionPath) ?>" class="sales-btn sales-btn-primary" data-plan-action>
                                    <?= e((string) ($billingPlan['cta'] ?? 'Escolher plano')) ?>
                                </a>
                                <ul>
                                    <?php foreach ((array) ($billingPlan['features'] ?? []) as $feature): ?>
                                        <li><?= e((string) $feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="sales-section sales-final-cta sales-topic sales-topic-contrast">
                <div class="sales-container">
                    <div class="sales-cta-box">
                        <h2>Pronto para simplificar sua rotina pessoal, do neg&oacute;cio e da equipe?</h2>
                        <p>Teste por 7 dias gr&aacute;tis ou fale com o suporte para montar um plano Enterprise.</p>
                        <div class="sales-hero-actions">
                            <a href="#planos" class="sales-btn sales-btn-primary">Escolher plano</a>
                            <a href="<?= e($appEntryPath) ?>" class="sales-btn sales-btn-ghost">Entrar no app</a>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="sales-footer">
            <div class="sales-container">
                <small>&copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?>. Todos os direitos reservados.</small>
            </div>
        </footer>
    </div>
    <script>
        document.addEventListener('click', function (event) {
            var target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            var button = target.closest('[data-flash-close]');
            if (!button) {
                return;
            }

            var flash = button.closest('[data-flash]');
            if (flash) {
                flash.remove();
            }
        });

        window.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-flash]').forEach(function (flash) {
                window.setTimeout(function () {
                    if (flash.isConnected) {
                        flash.remove();
                    }
                }, 5000);
            });

            var billingToggle = document.querySelector('[data-billing-toggle]');
            var billingButtons = billingToggle ? Array.from(billingToggle.querySelectorAll('[data-billing-interval]')) : [];
            var planCards = Array.from(document.querySelectorAll('[data-plan-card]'));
            var explorer = document.querySelector('[data-sales-explorer]');

            if (explorer) {
                var explorerTabs = Array.from(explorer.querySelectorAll('[data-sales-scenario]'));
                var scenarioJumps = Array.from(document.querySelectorAll('[data-sales-jump-scenario]'));
                var scenarioLabel = explorer.querySelector('[data-sales-scenario-label]');
                var scenarioTitle = explorer.querySelector('[data-sales-scenario-title]');
                var scenarioText = explorer.querySelector('[data-sales-scenario-text]');
                var scenarioList = explorer.querySelector('[data-sales-scenario-list]');
                var previewCards = Array.from(explorer.querySelectorAll('[data-sales-preview-card]'));

                function applyScenario(tab) {
                    var tasks = (tab.getAttribute('data-explorer-tasks') || '').split('|').filter(Boolean);

                    explorerTabs.forEach(function (button) {
                        var isActive = button === tab;
                        button.classList.toggle('is-active', isActive);
                        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    });

                    if (scenarioLabel) {
                        scenarioLabel.textContent = tab.textContent.trim();
                    }

                    if (scenarioTitle) {
                        scenarioTitle.textContent = tab.getAttribute('data-explorer-title') || '';
                    }

                    if (scenarioText) {
                        scenarioText.textContent = tab.getAttribute('data-explorer-text') || '';
                    }

                    if (scenarioList) {
                        scenarioList.replaceChildren();
                        tasks.forEach(function (task) {
                            var item = document.createElement('li');
                            item.textContent = task;
                            scenarioList.appendChild(item);
                        });
                    }

                    previewCards.forEach(function (card, index) {
                        card.textContent = tasks[index] || '';
                        card.hidden = !tasks[index];
                    });
                }

                explorerTabs.forEach(function (tab) {
                    tab.addEventListener('click', function () {
                        applyScenario(tab);
                    });
                });

                scenarioJumps.forEach(function (link) {
                    link.addEventListener('click', function () {
                        var scenario = link.getAttribute('data-sales-jump-scenario') || '';
                        var tab = explorerTabs.find(function (button) {
                            return button.getAttribute('data-sales-scenario') === scenario;
                        });
                        if (tab) {
                            applyScenario(tab);
                        }
                    });
                });
            }

            function applyBillingInterval(interval) {
                billingButtons.forEach(function (button) {
                    var isActive = button.getAttribute('data-billing-interval') === interval;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                planCards.forEach(function (card) {
                    var price = card.querySelector('[data-plan-price-value]');
                    var currency = price ? price.querySelector('.sales-price-currency') : null;
                    var amount = price ? price.querySelector('.sales-price-amount') : null;
                    var suffix = card.querySelector('[data-plan-price-suffix]');
                    var billingNote = card.querySelector('[data-plan-billing-note]');
                    var trialNote = card.querySelector('[data-plan-trial-note]');
                    var action = card.querySelector('[data-plan-action]');
                    var priceValue = card.getAttribute('data-price-' + interval) || '';
                    var noteValue = card.getAttribute('data-note-' + interval) || '';
                    var trialValue = card.getAttribute('data-trial-' + interval) || '';
                    var actionValue = card.getAttribute('data-action-' + interval) || '';
                    var priceMatch = priceValue.match(/^R\$\s*(.+)$/);

                    if (price && amount) {
                        if (priceMatch) {
                            if (!currency) {
                                currency = document.createElement('span');
                                currency.className = 'sales-price-currency';
                                price.insertBefore(currency, amount);
                            }
                            currency.textContent = 'R$';
                            currency.hidden = false;
                            amount.textContent = priceMatch[1];
                        } else {
                            if (currency) {
                                currency.hidden = true;
                            }
                            amount.textContent = priceValue;
                        }
                    }

                    if (suffix) {
                        suffix.hidden = (card.getAttribute('data-suffix') || '') === '';
                    }

                    if (billingNote) {
                        billingNote.textContent = noteValue;
                        billingNote.hidden = noteValue === '';
                    }

                    if (trialNote) {
                        trialNote.textContent = trialValue;
                    }

                    if (action && actionValue !== '') {
                        action.setAttribute('href', actionValue);
                    }
                });
            }

            billingButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    applyBillingInterval(button.getAttribute('data-billing-interval') || 'year');
                });
            });

            applyBillingInterval((billingToggle && billingToggle.getAttribute('data-default-billing-interval')) || 'year');
        });
    </script>
</body>
</html>
