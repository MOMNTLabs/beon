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

    upsertUserSubscription($pdo, $userId, [
        'stripe_customer_id' => trim((string) ($checkoutSession['customer'] ?? '')),
        'stripe_subscription_id' => trim((string) ($subscription['id'] ?? '')),
        'stripe_checkout_session_id' => trim((string) ($checkoutSession['id'] ?? '')),
        'subscription_status' => trim((string) ($subscription['status'] ?? 'inactive')) ?: 'inactive',
        'checkout_status' => trim((string) ($checkoutSession['status'] ?? '')),
        'trial_end' => stripeTimestampToIso($subscription['trial_end'] ?? null),
        'current_period_end' => stripeTimestampToIso($subscription['current_period_end'] ?? null),
        'cancel_at' => stripeTimestampToIso($subscription['cancel_at'] ?? null),
        'raw_payload_json' => is_string($rawPayload) && $rawPayload !== '' ? $rawPayload : '{}',
    ]);
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
$salesIllustrationVersion = is_file(__DIR__ . '/assets/sales-hero-illustration.svg')
    ? (string) filemtime(__DIR__ . '/assets/sales-hero-illustration.svg')
    : '1';

$pdo = db();
$currentUser = currentUser();
$checkoutAction = trim((string) ($_GET['action'] ?? ''));
$stripeBillingId = trim((string) (envValue('STRIPE_PRICE_ID') ?? envValue('STRIPE_PRODUCT_ID') ?? ''));
$checkoutPath = appPath('home?action=checkout');
$loginPath = $currentUser ? appPath('#tasks') : appPath('?auth=login');

if ($checkoutAction === 'checkout') {
    if (!$currentUser) {
        redirectTo('index.php?auth=login&next=' . urlencode('home?action=checkout') . '#login');
    }

    try {
        $stripeSecretKey = trim((string) (envValue('STRIPE_SECRET_KEY') ?? envValue('STRIPE_API_KEY') ?? ''));
        if ($stripeSecretKey === '') {
            throw new RuntimeException('Checkout Stripe não configurado. Defina STRIPE_SECRET_KEY no ambiente.');
        }
        if ($stripeBillingId === '') {
            throw new RuntimeException('Identificador Stripe não configurado. Defina STRIPE_PRICE_ID (ou STRIPE_PRODUCT_ID) no ambiente.');
        }

        $userId = (int) ($currentUser['id'] ?? 0);
        $successUrl = appEntryUrl() . appPath('home?action=checkout_success&session_id={CHECKOUT_SESSION_ID}');
        $cancelUrl = appEntryUrl() . appPath('home?checkout=cancelled');

        $lineItem = ['quantity' => 1];
        if (str_starts_with($stripeBillingId, 'price_')) {
            $lineItem['price'] = $stripeBillingId;
        } elseif (str_starts_with($stripeBillingId, 'prod_')) {
            $lineItem['price_data'] = [
                'currency' => 'brl',
                'unit_amount' => 1990,
                'product' => $stripeBillingId,
                'recurring' => [
                    'interval' => 'month',
                ],
            ];
        } else {
            throw new RuntimeException('ID Stripe inválido. Use STRIPE_PRICE_ID (price_...) ou STRIPE_PRODUCT_ID (prod_...).');
        }

        $checkoutPayload = [
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'locale' => 'pt-BR',
            'line_items' => [$lineItem],
            'client_reference_id' => (string) $userId,
            'metadata' => [
                'bexon_user_id' => (string) $userId,
            ],
            'subscription_data' => [
                'trial_period_days' => 7,
                'metadata' => [
                    'bexon_user_id' => (string) $userId,
                ],
            ],
        ];

        if (!empty($currentUser['email'])) {
            $checkoutPayload['customer_email'] = (string) $currentUser['email'];
        }

        $checkoutSession = stripeRequestForm('POST', 'https://api.stripe.com/v1/checkout/sessions', $checkoutPayload, $stripeSecretKey);

        upsertUserSubscription($pdo, $userId, [
            'stripe_customer_id' => trim((string) ($checkoutSession['customer'] ?? '')),
            'stripe_checkout_session_id' => trim((string) ($checkoutSession['id'] ?? '')),
            'subscription_status' => 'inactive',
            'checkout_status' => 'pending_checkout',
            'raw_payload_json' => json_encode($checkoutSession, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);

        $checkoutUrl = trim((string) ($checkoutSession['url'] ?? ''));
        if ($checkoutUrl === '') {
            throw new RuntimeException('A Stripe não retornou a URL do checkout.');
        }

        header('Location: ' . $checkoutUrl);
        exit;
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirectTo('home');
    }
}

if ($checkoutAction === 'checkout_success') {
    if (!$currentUser) {
        flash('error', 'Faça login para confirmar o checkout.');
        redirectTo('index.php?auth=login&next=' . urlencode('home?action=checkout_success&session_id=' . ((string) ($_GET['session_id'] ?? ''))) . '#login');
    }

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

        syncSubscriptionFromStripeSession($pdo, (int) ($currentUser['id'] ?? 0), $checkoutSession);
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
        'message' => 'Checkout concluído. Seu teste grátis de 7 dias foi ativado.',
    ];
} elseif ($checkoutStatus === 'cancelled') {
    $checkoutNotice = [
        'type' => 'info',
        'message' => 'Checkout cancelado. Você pode tentar novamente quando quiser.',
    ];
}

$flashes = getFlashes();
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
                    <a href="<?= e($loginPath) ?>" class="sales-btn sales-btn-ghost">Entrar</a>
                    <a href="<?= e($checkoutPath) ?>" class="sales-btn sales-btn-primary">Come&ccedil;ar 7 dias gr&aacute;tis</a>
                </div>
            </div>
        </header>

        <main>
            <?php if ($checkoutNotice || !empty($flashes)): ?>
                <section class="sales-section sales-alerts">
                    <div class="sales-container">
                        <?php if ($checkoutNotice): ?>
                            <div class="flash flash-<?= e((string) $checkoutNotice['type']) ?>">
                                <span><?= e((string) $checkoutNotice['message']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($flashes as $flash): ?>
                            <div class="flash flash-<?= e((string) ($flash['type'] ?? 'info')) ?>">
                                <span><?= e((string) ($flash['message'] ?? '')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="sales-hero">
                <div class="sales-container sales-hero-grid">
                    <div class="sales-hero-copy">
                        <h1>Tarefas pessoais, do neg&oacute;cio e da equipe em um fluxo <span class="sales-title-accent">simples.</span></h1>
                        <p>
                            O Bexon une o que voc&ecirc; precisa fazer sozinho e com o time no mesmo lugar.
                            Menos troca de contexto, mais clareza para executar.
                        </p>
                        <div class="sales-hero-actions">
                            <a href="<?= e($checkoutPath) ?>" class="sales-btn sales-btn-primary">Iniciar 7 dias gr&aacute;tis</a>
                            <a href="<?= e($loginPath) ?>" class="sales-btn sales-btn-secondary">Ver demo no app</a>
                        </div>
                        <ul class="sales-trust-list">
                            <li>7 dias gr&aacute;tis</li>
                            <li>R$ 19,90/m&ecirc;s ap&oacute;s o teste</li>
                            <li>Pessoal + neg&oacute;cio + equipe</li>
                        </ul>
                    </div>

                    <div class="sales-hero-preview" aria-hidden="true">
                        <img
                            src="<?= e(appPath('assets/sales-hero-illustration.svg?v=' . $salesIllustrationVersion)) ?>"
                            alt=""
                            class="sales-preview-illustration"
                            decoding="async"
                        >
                    </div>
                </div>
            </section>

            <section id="uso" class="sales-section sales-topic sales-topic-soft">
                <div class="sales-container">
                    <div class="sales-section-head sales-section-head-alt">
                        <span class="sales-eyebrow">Feito para a rotina real</span>
                        <h2>Do pessoal ao time, tudo organizado sem complicar.</h2>
                    </div>
                    <div class="sales-usecases-grid">
                        <article class="sales-usecase-card">
                            <span class="sales-usecase-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
                                    <path d="M5 20a7 7 0 0 1 14 0"></path>
                                </svg>
                            </span>
                            <h3>Pessoal</h3>
                            <p>Organize tarefas do dia a dia, pend&ecirc;ncias e prioridades pessoais em minutos.</p>
                        </article>
                        <article class="sales-usecase-card">
                            <span class="sales-usecase-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="3" y="6" width="18" height="13" rx="2"></rect>
                                    <path d="M8 6V4h8v2"></path>
                                    <path d="M3 11h18"></path>
                                </svg>
                            </span>
                            <h3>Neg&oacute;cio</h3>
                            <p>Centralize opera&ccedil;&otilde;es, clientes e demandas do neg&oacute;cio em um quadro claro.</p>
                        </article>
                        <article class="sales-usecase-card">
                            <span class="sales-usecase-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M16 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path>
                                    <path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path>
                                    <path d="M2 20a6 6 0 0 1 12 0"></path>
                                    <path d="M13 20a5 5 0 0 1 9 0"></path>
                                </svg>
                            </span>
                            <h3>Equipe</h3>
                            <p>Delegue, acompanhe e revise tarefas da equipe sem perder a simplicidade.</p>
                        </article>
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
                            <p>Ative seu teste gr&aacute;tis e crie seu fluxo inicial sem configura&ccedil;&otilde;es complexas.</p>
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
                        <span class="sales-eyebrow">Plano</span>
                        <h2>Simplicidade total: 7 dias gr&aacute;tis e depois R$ 19,90 por m&ecirc;s.</h2>
                    </div>
                    <div class="sales-pricing-grid is-single">
                        <article class="sales-pricing-card is-highlight">
                            <h3>Bexon Pro</h3>
                            <p class="sales-price">R$ 19,90<span>/m&ecirc;s</span></p>
                            <p class="sales-price-note">Voc&ecirc; s&oacute; paga ap&oacute;s o per&iacute;odo de teste gr&aacute;tis de 7 dias.</p>
                            <ul>
                                <li>Tarefas pessoais e profissionais no mesmo lugar</li>
                                <li>Organiza&ccedil;&atilde;o simples para equipes</li>
                                <li>Fluxo visual com status e prioridades</li>
                                <li>Suporte cont&iacute;nuo sem custo extra</li>
                            </ul>
                            <a href="<?= e($checkoutPath) ?>" class="sales-btn sales-btn-primary">Ativar teste gr&aacute;tis</a>
                        </article>
                    </div>
                </div>
            </section>

            <section class="sales-section sales-final-cta sales-topic sales-topic-contrast">
                <div class="sales-container">
                    <div class="sales-cta-box">
                        <h2>Pronto para simplificar sua rotina pessoal, do neg&oacute;cio e da equipe?</h2>
                        <p>Comece com 7 dias gr&aacute;tis e veja como organizar tudo pode ser mais leve no Bexon.</p>
                        <div class="sales-hero-actions">
                            <a href="<?= e($checkoutPath) ?>" class="sales-btn sales-btn-primary">Iniciar teste gr&aacute;tis</a>
                            <a href="<?= e($loginPath) ?>" class="sales-btn sales-btn-ghost">Entrar no app</a>
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
</body>
</html>
