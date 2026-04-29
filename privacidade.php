<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

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
    <title>Pol&iacute;tica de Privacidade - <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="shortcut icon" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/styles.css?v=' . $stylesAssetVersion)) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/theme-bexon.css?v=' . $themeBexonAssetVersion)) ?>">
    <script src="<?= e(appPath('assets/compliance.js?v=' . $complianceAssetVersion)) ?>" defer></script>
</head>
<body class="is-legal-page">
    <main class="legal-page-shell">
        <header class="legal-page-header">
            <a href="<?= e(appPath('home')) ?>" class="legal-brand" aria-label="<?= e(APP_NAME) ?>">
                <img src="<?= e(appPath('assets/Bexon - Logo Horizontal.png?v=1')) ?>" alt="<?= e(APP_NAME) ?>">
            </a>
            <nav class="legal-nav" aria-label="Documentos legais">
                <a href="<?= e(appPath('privacidade')) ?>" aria-current="page">Privacidade</a>
                <a href="<?= e(appPath('termos')) ?>">Termos</a>
                <a href="<?= e(appPath('cookies')) ?>">Cookies</a>
                <a href="<?= e(appPath('dados')) ?>">Meus dados</a>
            </nav>
        </header>

        <article class="legal-document">
            <p class="legal-eyebrow">LGPD e transpar&ecirc;ncia</p>
            <h1>Pol&iacute;tica de Privacidade</h1>
            <p class="legal-updated">Atualizada em <?= e((string) ($legal['updated_at'] ?? '29/04/2026')) ?>.</p>

            <section>
                <h2>1. Quem controla seus dados</h2>
                <p>
                    O <?= e(APP_NAME) ?> &eacute; operado por <?= e(legalValue('company_name')) ?>,
                    CNPJ <?= e(legalValue('cnpj')) ?>, com endere&ccedil;o em <?= e(legalValue('address')) ?>.
                    Para assuntos de privacidade e prote&ccedil;&atilde;o de dados, use
                    <a href="<?= e(legalMailto('Privacidade e dados pessoais - ' . APP_NAME)) ?>"><?= e(legalValue('privacy_email')) ?></a>.
                </p>
            </section>

            <section>
                <h2>2. Quais dados tratamos</h2>
                <p>Podemos tratar dados fornecidos por voc&ecirc; ou gerados pelo uso da plataforma, incluindo:</p>
                <ul>
                    <li>dados de cadastro, como nome, e-mail, senha criptografada e foto de perfil;</li>
                    <li>dados de workspace, membros, permiss&otilde;es, tarefas, prazos, hist&oacute;rico e arquivos ou links adicionados;</li>
                    <li>dados operacionais inseridos pelo cliente, como estoque, vencimentos, receitas, despesas e anota&ccedil;&otilde;es;</li>
                    <li>dados de assinatura e pagamento processados por provedores como Stripe;</li>
                    <li>cookies funcionais, identificadores de sess&atilde;o e registros t&eacute;cnicos necess&aacute;rios para seguran&ccedil;a.</li>
                </ul>
            </section>

            <section>
                <h2>3. Para que usamos os dados</h2>
                <p>Usamos os dados para criar e manter contas, autenticar acesso, entregar as funcionalidades contratadas, organizar workspaces, processar assinaturas, prestar suporte, prevenir fraude, cumprir obriga&ccedil;&otilde;es legais e melhorar a estabilidade do servi&ccedil;o.</p>
            </section>

            <section>
                <h2>4. Bases legais</h2>
                <p>O tratamento pode ocorrer para execu&ccedil;&atilde;o de contrato, cumprimento de obriga&ccedil;&atilde;o legal ou regulat&oacute;ria, exerc&iacute;cio regular de direitos, leg&iacute;timo interesse e consentimento quando aplic&aacute;vel, sempre conforme a Lei Geral de Prote&ccedil;&atilde;o de Dados Pessoais.</p>
            </section>

            <section>
                <h2>5. Compartilhamento</h2>
                <p>Podemos compartilhar dados com fornecedores necess&aacute;rios &agrave; opera&ccedil;&atilde;o do servi&ccedil;o, como hospedagem, banco de dados, envio de e-mails, processamento de pagamento, preven&ccedil;&atilde;o a fraude, suporte e autoridades quando houver obriga&ccedil;&atilde;o legal.</p>
            </section>

            <section>
                <h2>6. Cookies</h2>
                <p>Usamos cookies essenciais para manter sess&atilde;o, lembrar login quando solicitado e preservar o workspace ativo. Detalhes est&atilde;o na <a href="<?= e(appPath('cookies')) ?>">Pol&iacute;tica de Cookies</a>.</p>
            </section>

            <section>
                <h2>7. Reten&ccedil;&atilde;o e exclus&atilde;o</h2>
                <p>Os dados s&atilde;o mantidos enquanto a conta estiver ativa, enquanto forem necess&aacute;rios para prestar o servi&ccedil;o ou enquanto houver obriga&ccedil;&atilde;o legal, regulat&oacute;ria, fiscal, contratual ou de defesa de direitos. Ao final, os dados podem ser exclu&iacute;dos, anonimizados ou mantidos apenas pelo prazo legal aplic&aacute;vel.</p>
            </section>

            <section>
                <h2>8. Seus direitos</h2>
                <p>Voc&ecirc; pode solicitar confirma&ccedil;&atilde;o de tratamento, acesso, corre&ccedil;&atilde;o, anonimiza&ccedil;&atilde;o, bloqueio, elimina&ccedil;&atilde;o, portabilidade, informa&ccedil;&otilde;es sobre compartilhamento e revoga&ccedil;&atilde;o de consentimento. Para iniciar uma solicita&ccedil;&atilde;o, acesse <a href="<?= e(appPath('dados')) ?>">Meus dados e privacidade</a>.</p>
            </section>

            <section>
                <h2>9. Seguran&ccedil;a</h2>
                <p>Adotamos medidas t&eacute;cnicas e administrativas para reduzir riscos de acesso indevido, perda, altera&ccedil;&atilde;o ou divulga&ccedil;&atilde;o n&atilde;o autorizada. Nenhuma plataforma &eacute; isenta de riscos, por isso tamb&eacute;m recomendamos senhas fortes, controle de permiss&otilde;es e cuidado ao inserir informa&ccedil;&otilde;es sens&iacute;veis.</p>
            </section>

            <section>
                <h2>10. Atualiza&ccedil;&otilde;es</h2>
                <p>Esta pol&iacute;tica pode ser atualizada para refletir mudan&ccedil;as legais, operacionais ou de produto. A vers&atilde;o vigente fica sempre dispon&iacute;vel nesta p&aacute;gina.</p>
            </section>
        </article>
    </main>
</body>
</html>
