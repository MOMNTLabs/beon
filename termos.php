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
    <title>Termos de Uso - <?= e(APP_NAME) ?></title>
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
                <a href="<?= e(appPath('privacidade')) ?>">Privacidade</a>
                <a href="<?= e(appPath('termos')) ?>" aria-current="page">Termos</a>
                <a href="<?= e(appPath('cookies')) ?>">Cookies</a>
                <a href="<?= e(appPath('dados')) ?>">Meus dados</a>
            </nav>
        </header>

        <article class="legal-document">
            <p class="legal-eyebrow">Contrato de uso</p>
            <h1>Termos de Uso</h1>
            <p class="legal-updated">Atualizados em <?= e((string) ($legal['updated_at'] ?? '29/04/2026')) ?>.</p>

            <section>
                <h2>1. Sobre o servi&ccedil;o</h2>
                <p>O <?= e(APP_NAME) ?> &eacute; uma plataforma de organiza&ccedil;&atilde;o e gest&atilde;o de rotinas, tarefas, workspaces, vencimentos, estoque, contabilidade simples, acessos e colabora&ccedil;&atilde;o em equipe.</p>
            </section>

            <section>
                <h2>2. Contratante e fornecedor</h2>
                <p>O servi&ccedil;o &eacute; fornecido por <?= e(legalValue('company_name')) ?>, CNPJ <?= e(legalValue('cnpj')) ?>, com atendimento pelo e-mail <a href="mailto:<?= e(legalValue('support_email')) ?>"><?= e(legalValue('support_email')) ?></a>.</p>
            </section>

            <section>
                <h2>3. Cadastro e conta</h2>
                <p>Ao criar uma conta, voc&ecirc; declara que as informa&ccedil;&otilde;es fornecidas s&atilde;o corretas, que manter&aacute; seus dados atualizados e que &eacute; respons&aacute;vel por preservar a confidencialidade da sua senha e pelo uso feito a partir da sua conta.</p>
            </section>

            <section>
                <h2>4. Assinaturas, teste e cobran&ccedil;a</h2>
                <p>Planos pagos podem incluir per&iacute;odo de teste gratuito, cobran&ccedil;a mensal ou anual e limites por quantidade de usu&aacute;rios. A cobran&ccedil;a &eacute; processada por provedor externo de pagamento. Valores, periodicidade, trial e limites s&atilde;o apresentados antes da contrata&ccedil;&atilde;o.</p>
            </section>

            <section>
                <h2>5. Cancelamento e arrependimento</h2>
                <p>O cliente pode solicitar cancelamento pelo suporte. Em contrata&ccedil;&otilde;es realizadas pela internet, o direito de arrependimento poder&aacute; ser exercido nos termos da legisla&ccedil;&atilde;o aplic&aacute;vel. Solicita&ccedil;&otilde;es devem ser enviadas para <a href="mailto:<?= e(legalValue('support_email')) ?>"><?= e(legalValue('support_email')) ?></a>.</p>
            </section>

            <section>
                <h2>6. Uso adequado</h2>
                <p>Voc&ecirc; n&atilde;o deve usar o <?= e(APP_NAME) ?> para atividades ilegais, violar direitos de terceiros, tentar acessar contas de outros usu&aacute;rios, explorar vulnerabilidades, enviar c&oacute;digo malicioso ou inserir conte&uacute;do que voc&ecirc; n&atilde;o tem autoriza&ccedil;&atilde;o para tratar.</p>
            </section>

            <section>
                <h2>7. Dados inseridos pelo cliente</h2>
                <p>Voc&ecirc; &eacute; respons&aacute;vel pelos dados que cadastra na plataforma e pelas permiss&otilde;es dadas aos membros dos workspaces. Ao usar recursos como gerenciador de acessos, evite inserir informa&ccedil;&otilde;es desnecess&aacute;rias e restrinja o acesso apenas a pessoas autorizadas.</p>
            </section>

            <section>
                <h2>8. Disponibilidade</h2>
                <p>Buscamos manter o servi&ccedil;o est&aacute;vel e seguro, mas podem ocorrer indisponibilidades por manuten&ccedil;&atilde;o, atualiza&ccedil;&otilde;es, falhas de infraestrutura, terceiros ou eventos fora do controle razo&aacute;vel.</p>
            </section>

            <section>
                <h2>9. Propriedade intelectual</h2>
                <p>A marca, interface, c&oacute;digo, design, textos e demais elementos do <?= e(APP_NAME) ?> pertencem aos seus titulares. O uso da plataforma n&atilde;o transfere propriedade intelectual ao cliente.</p>
            </section>

            <section>
                <h2>10. Privacidade</h2>
                <p>O tratamento de dados pessoais segue a <a href="<?= e(appPath('privacidade')) ?>">Pol&iacute;tica de Privacidade</a> e a <a href="<?= e(appPath('cookies')) ?>">Pol&iacute;tica de Cookies</a>.</p>
            </section>

            <section>
                <h2>11. Atualiza&ccedil;&otilde;es dos termos</h2>
                <p>Estes termos podem ser alterados para refletir mudan&ccedil;as no produto, no modelo comercial ou na legisla&ccedil;&atilde;o. A vers&atilde;o vigente fica dispon&iacute;vel nesta p&aacute;gina.</p>
            </section>
        </article>
    </main>
</body>
</html>
