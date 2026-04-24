<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function stripePostForm(string $url, array $payload, string $secretKey): array
{
    $body = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
    $headers = [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($body),
    ];

    $responseBody = '';
    $statusCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Falha ao inicializar o cliente HTTP.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);

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
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
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
        $errorMessage = trim((string) ($decoded['error']['message'] ?? 'Não foi possível iniciar o checkout Stripe.'));
        throw new RuntimeException($errorMessage !== '' ? $errorMessage : 'Não foi possível iniciar o checkout Stripe.');
    }

    return $decoded;
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

$currentUser = currentUser();
$checkoutAction = trim((string) ($_GET['action'] ?? ''));
$stripeBillingId = trim((string) (envValue('STRIPE_PRICE_ID') ?? envValue('STRIPE_PRODUCT_ID') ?? 'prod_UOPXAsaQr5J2aA'));
$checkoutPath = appPath('home?action=checkout');
$loginPath = $currentUser ? appPath('#tasks') : appPath('?auth=login');

if ($checkoutAction === 'checkout') {
    try {
        $stripeSecretKey = trim((string) (envValue('STRIPE_SECRET_KEY') ?? envValue('STRIPE_API_KEY') ?? ''));
        if ($stripeSecretKey === '') {
            throw new RuntimeException('Checkout Stripe não configurado. Defina STRIPE_SECRET_KEY no ambiente.');
        }
        if ($stripeBillingId === '') {
            throw new RuntimeException('Identificador Stripe não configurado. Defina STRIPE_PRICE_ID (ou STRIPE_PRODUCT_ID) no ambiente.');
        }

        $entryUrl = rtrim(appEntryUrl(), '/');
        $successUrl = appEntryUrl() . appPath('home?checkout=success');
        $cancelUrl = appEntryUrl() . appPath('home?checkout=cancel');

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
            'subscription_data' => [
                'trial_period_days' => 7,
            ],
        ];

        if (!empty($currentUser['email'])) {
            $checkoutPayload['customer_email'] = (string) $currentUser['email'];
        }

        $checkoutSession = stripePostForm('https://api.stripe.com/v1/checkout/sessions', $checkoutPayload, $stripeSecretKey);
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

$checkoutStatus = trim((string) ($_GET['checkout'] ?? ''));
$checkoutNotice = null;
if ($checkoutStatus === 'success') {
        $checkoutNotice = [
        'type' => 'success',
        'message' => 'Checkout concluído. Seu teste grátis de 7 dias foi ativado.',
    ];
} elseif ($checkoutStatus === 'cancel') {
    $checkoutNotice = [
        'type' => 'error',
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
                        <span class="sales-eyebrow">Opera&ccedil;&atilde;o clara para equipes em crescimento</span>
                        <h1>Gest&atilde;o de tarefas e execu&ccedil;&atilde;o em um fluxo simples.</h1>
                        <p>
                            O Bexon organiza tarefas, revis&otilde;es, acessos e rotinas de opera&ccedil;&atilde;o
                            em uma &uacute;nica vis&atilde;o. Menos ru&iacute;do, mais entrega.
                        </p>
                        <div class="sales-hero-actions">
                            <a href="<?= e($checkoutPath) ?>" class="sales-btn sales-btn-primary">Iniciar 7 dias gr&aacute;tis</a>
                            <a href="<?= e($loginPath) ?>" class="sales-btn sales-btn-secondary">Ver demo no app</a>
                        </div>
                        <ul class="sales-trust-list">
                            <li>7 dias gr&aacute;tis</li>
                            <li>R$ 19,90/m&ecirc;s ap&oacute;s o teste</li>
                            <li>Cancele quando quiser</li>
                        </ul>
                    </div>

                    <div class="sales-hero-preview" aria-hidden="true">
                        <div class="sales-preview-window">
                            <div class="sales-preview-head">
                                <div class="sales-preview-dots">
                                    <span></span><span></span><span></span>
                                </div>
                                <strong>Workspace Bexon</strong>
                            </div>
                            <div class="sales-preview-board">
                                <article class="sales-preview-column is-todo">
                                    <h3>A fazer</h3>
                                    <p>Revisar checklist de onboarding</p>
                                    <p>Ajustar rotas de entregas</p>
                                </article>
                                <article class="sales-preview-column is-progress">
                                    <h3>Em andamento</h3>
                                    <p>Atualizar quadro de vendas</p>
                                    <p>Validar custos do m&ecirc;s</p>
                                </article>
                                <article class="sales-preview-column is-review">
                                    <h3>Revis&atilde;o</h3>
                                    <p>Aprovar automa&ccedil;&otilde;es</p>
                                    <p>Conferir permiss&otilde;es</p>
                                </article>
                            </div>
                            <div class="sales-preview-metrics">
                                <div>
                                    <span>Conclu&iacute;das</span>
                                    <strong>78%</strong>
                                </div>
                                <div>
                                    <span>Tempo m&eacute;dio</span>
                                    <strong>-31%</strong>
                                </div>
                                <div>
                                    <span>Visibilidade</span>
                                    <strong>100%</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="recursos" class="sales-section">
                <div class="sales-container">
                    <div class="sales-section-head">
                        <span class="sales-eyebrow">Recursos principais</span>
                        <h2>Tudo que seu time precisa, sem camada extra de complexidade.</h2>
                    </div>
                    <div class="sales-feature-grid">
                        <article class="sales-feature-card">
                            <h3>Quadro de status configur&aacute;vel</h3>
                            <p>Personalize etapas, cores e ordem para refletir seu fluxo real.</p>
                        </article>
                        <article class="sales-feature-card">
                            <h3>Permiss&otilde;es por grupo</h3>
                            <p>Controle acesso e visibilidade por &aacute;rea com granularidade.</p>
                        </article>
                        <article class="sales-feature-card">
                            <h3>Opera&ccedil;&atilde;o integrada</h3>
                            <p>Unifique tarefas, vencimentos, invent&aacute;rio e financeiro no mesmo lugar.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="fluxo" class="sales-section">
                <div class="sales-container sales-steps-shell">
                    <div class="sales-section-head">
                        <span class="sales-eyebrow">Como funciona</span>
                        <h2>Um processo direto, do primeiro login at&eacute; a entrega.</h2>
                    </div>
                    <div class="sales-steps-grid">
                        <article class="sales-step-card">
                            <span>01</span>
                            <h3>Inicie seu teste gr&aacute;tis</h3>
                            <p>Ative 7 dias gr&aacute;tis em poucos cliques com checkout seguro da Stripe.</p>
                        </article>
                        <article class="sales-step-card">
                            <span>02</span>
                            <h3>Defina status e grupos</h3>
                            <p>Ajuste etapas, permiss&atilde;o e vis&atilde;o de cada parte da opera&ccedil;&atilde;o.</p>
                        </article>
                        <article class="sales-step-card">
                            <span>03</span>
                            <h3>Escale com previsibilidade</h3>
                            <p>Depois do teste, continue por R$ 19,90/m&ecirc;s sem surpresa na cobran&ccedil;a.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="planos" class="sales-section">
                <div class="sales-container">
                    <div class="sales-section-head">
                        <span class="sales-eyebrow">Plano</span>
                        <h2>7 dias gr&aacute;tis e depois R$ 19,90 por m&ecirc;s.</h2>
                    </div>
                    <div class="sales-pricing-grid is-single">
                        <article class="sales-pricing-card is-highlight">
                            <h3>Bexon Pro</h3>
                            <p class="sales-price">R$ 19,90<span>/m&ecirc;s</span></p>
                            <p class="sales-price-note">Voc&ecirc; s&oacute; paga ap&oacute;s o per&iacute;odo de teste gr&aacute;tis de 7 dias.</p>
                            <ul>
                                <li>Projetos e tarefas ilimitados</li>
                                <li>Permiss&otilde;es por grupo</li>
                                <li>Dashboard completo</li>
                                <li>Suporte cont&iacute;nuo</li>
                            </ul>
                            <a href="<?= e($checkoutPath) ?>" class="sales-btn sales-btn-primary">Ativar teste gr&aacute;tis</a>
                        </article>
                    </div>
                </div>
            </section>

            <section class="sales-section sales-final-cta">
                <div class="sales-container">
                    <div class="sales-cta-box">
                        <h2>Pronto para centralizar sua opera&ccedil;&atilde;o no Bexon?</h2>
                        <p>Comece com 7 dias gr&aacute;tis. Depois, continue por apenas R$ 19,90/m&ecirc;s.</p>
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
