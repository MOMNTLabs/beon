<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (configuredSiteUrl() !== '' && requestTargetsConfiguredAppHost()) {
    header('Location: ' . siteUrl('dados'));
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
    <title>Meus Dados e Privacidade - <?= e(APP_NAME) ?></title>
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
                <a href="<?= e(sitePath('cookies')) ?>">Cookies</a>
                <a href="<?= e(sitePath('dados')) ?>" aria-current="page">Meus dados</a>
            </nav>
        </header>

        <article class="legal-document">
            <p class="legal-eyebrow">Direitos do titular</p>
            <h1>Meus dados e privacidade</h1>
            <p class="legal-updated">Canal para exerc&iacute;cio de direitos previstos na LGPD.</p>

            <section>
                <h2>O que voc&ecirc; pode solicitar</h2>
                <ul>
                    <li>confirma&ccedil;&atilde;o de tratamento e acesso aos seus dados;</li>
                    <li>corre&ccedil;&atilde;o de dados incompletos, inexatos ou desatualizados;</li>
                    <li>portabilidade, quando aplic&aacute;vel;</li>
                    <li>anonimiza&ccedil;&atilde;o, bloqueio ou elimina&ccedil;&atilde;o de dados desnecess&aacute;rios ou excessivos;</li>
                    <li>informa&ccedil;&otilde;es sobre compartilhamento com terceiros;</li>
                    <li>revoga&ccedil;&atilde;o de consentimento, quando o tratamento depender de consentimento.</li>
                </ul>
            </section>

            <section>
                <h2>Como abrir uma solicita&ccedil;&atilde;o</h2>
                <p>Envie um e-mail para <a href="<?= e(legalMailto('Solicitacao LGPD - ' . APP_NAME)) ?>"><?= e(legalValue('privacy_email')) ?></a> informando seu nome, e-mail cadastrado e o tipo de solicita&ccedil;&atilde;o. Podemos pedir confirma&ccedil;&atilde;o de identidade antes de atender ao pedido para proteger sua conta.</p>
                <p><a class="legal-action" href="<?= e(legalMailto('Solicitacao LGPD - ' . APP_NAME)) ?>">Enviar solicita&ccedil;&atilde;o por e-mail</a></p>
            </section>

            <section>
                <h2>Prazos e limita&ccedil;&otilde;es</h2>
                <p>As solicita&ccedil;&otilde;es ser&atilde;o avaliadas conforme a LGPD e podem ser limitadas quando houver obriga&ccedil;&atilde;o legal de reten&ccedil;&atilde;o, necessidade de preven&ccedil;&atilde;o a fraude, seguran&ccedil;a, exerc&iacute;cio regular de direitos ou preserva&ccedil;&atilde;o de dados de outros usu&aacute;rios e workspaces.</p>
            </section>

            <section>
                <h2>Configura&ccedil;&otilde;es da conta</h2>
                <p>Usu&aacute;rios logados tamb&eacute;m podem atualizar nome, foto, senha e participa&ccedil;&atilde;o em workspaces na &aacute;rea de configura&ccedil;&otilde;es da conta.</p>
                <p><a class="legal-action legal-action-secondary" href="<?= e(appUrl('account-settings')) ?>">Abrir configura&ccedil;&otilde;es da conta</a></p>
            </section>
        </article>
    </main>
</body>
</html>
