<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (configuredSiteUrl() !== '' && requestTargetsConfiguredAppHost()) {
    header('Location: ' . siteUrl('cookies'));
    exit;
}

$legal = legalConfig();
$stylesAssetVersion = assetVersion('assets/styles.css', '103');
$themeBexonAssetVersion = assetVersion('assets/theme-bexon.css');
$complianceAssetVersion = assetVersion('assets/compliance.js');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pol&iacute;tica de Cookies - <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="shortcut icon" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/styles.css?v=' . $stylesAssetVersion)) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/theme-bexon.css?v=' . $themeBexonAssetVersion)) ?>">
    <script src="<?= e(appPath('assets/compliance.js?v=' . $complianceAssetVersion)) ?>" defer></script>
</head>
<body class="is-legal-page">
    <main class="legal-page-shell">
        <header class="legal-page-header">
            <a href="<?= e(sitePath('home')) ?>" class="legal-brand" aria-label="<?= e(APP_NAME) ?>">
                <img src="<?= e(appPath('assets/Bexon - Logo Horizontal.png?v=1')) ?>" alt="<?= e(APP_NAME) ?>">
            </a>
            <nav class="legal-nav" aria-label="Documentos legais">
                <a href="<?= e(sitePath('privacidade')) ?>">Privacidade</a>
                <a href="<?= e(sitePath('termos')) ?>">Termos</a>
                <a href="<?= e(sitePath('cookies')) ?>" aria-current="page">Cookies</a>
                <a href="<?= e(sitePath('dados')) ?>">Meus dados</a>
            </nav>
        </header>

        <article class="legal-document">
            <p class="legal-eyebrow">Transpar&ecirc;ncia de navega&ccedil;&atilde;o</p>
            <h1>Pol&iacute;tica de Cookies</h1>
            <p class="legal-updated">Atualizada em <?= e((string) ($legal['updated_at'] ?? '29/04/2026')) ?>.</p>

            <section>
                <h2>1. O que s&atilde;o cookies</h2>
                <p>Cookies s&atilde;o pequenos arquivos ou identificadores armazenados no navegador para permitir funcionamento, seguran&ccedil;a, prefer&ecirc;ncias e medi&ccedil;&otilde;es de uso de um site ou aplica&ccedil;&atilde;o.</p>
            </section>

            <section>
                <h2>2. Cookies usados pelo <?= e(APP_NAME) ?></h2>
                <div class="legal-table-wrap">
                    <table class="legal-table">
                        <thead>
                            <tr>
                                <th>Cookie</th>
                                <th>Finalidade</th>
                                <th>Prazo</th>
                                <th>Categoria</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code><?= e(REMEMBER_COOKIE_NAME) ?></code></td>
                                <td>Lembrar o login quando solicitado pelo usu&aacute;rio.</td>
                                <td>At&eacute; <?= e((string) REMEMBER_TOKEN_DAYS) ?> dias.</td>
                                <td>Essencial/funcional.</td>
                            </tr>
                            <tr>
                                <td><code><?= e(LAST_WORKSPACE_COOKIE_NAME) ?></code></td>
                                <td>Recordar o &uacute;ltimo workspace usado na conta.</td>
                                <td>At&eacute; <?= e((string) LAST_WORKSPACE_COOKIE_DAYS) ?> dias.</td>
                                <td>Essencial/funcional.</td>
                            </tr>
                            <tr>
                                <td><code>bexon_cookie_consent</code></td>
                                <td>Registrar sua prefer&ecirc;ncia sobre o aviso de cookies.</td>
                                <td>At&eacute; 180 dias.</td>
                                <td>Essencial/funcional.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section>
                <h2>3. Cookies de terceiros</h2>
                <p>O pagamento pode ser conclu&iacute;do em ambiente de terceiros, como Stripe, que aplica suas pr&oacute;prias pol&iacute;ticas de privacidade e cookies. Caso ferramentas de an&aacute;lise ou marketing sejam adicionadas ao <?= e(APP_NAME) ?>, esta pol&iacute;tica dever&aacute; ser atualizada antes do uso.</p>
            </section>

            <section>
                <h2>4. Como gerenciar cookies</h2>
                <p>Voc&ecirc; pode apagar ou bloquear cookies pelo navegador. O bloqueio de cookies essenciais pode impedir login, autentica&ccedil;&atilde;o, seguran&ccedil;a e prefer&ecirc;ncias da aplica&ccedil;&atilde;o.</p>
                <p><button type="button" class="legal-inline-button" data-cookie-preferences>Abrir prefer&ecirc;ncias de cookies</button></p>
            </section>
        </article>
    </main>
</body>
</html>
