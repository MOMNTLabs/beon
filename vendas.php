<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$stylesAssetVersion = is_file(__DIR__ . '/assets/styles.css')
    ? (string) filemtime(__DIR__ . '/assets/styles.css')
    : '1';
$themeBexonAssetVersion = is_file(__DIR__ . '/assets/theme-bexon.css')
    ? (string) filemtime(__DIR__ . '/assets/theme-bexon.css')
    : '1';
$salesAssetVersion = is_file(__DIR__ . '/assets/vendas.css')
    ? (string) filemtime(__DIR__ . '/assets/vendas.css')
    : '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> - Vendas</title>
    <link rel="icon" type="image/png" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="shortcut icon" href="<?= e(appPath('assets/Bexon---Logo-Symbol.png?v=1')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(appPath('assets/styles.css?v=' . $stylesAssetVersion)) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/theme-bexon.css?v=' . $themeBexonAssetVersion)) ?>">
    <link rel="stylesheet" href="<?= e(appPath('assets/vendas.css?v=' . $salesAssetVersion)) ?>">
</head>
<body class="is-sales-page">
    <div class="sales-page" id="top">
        <header class="sales-header">
            <div class="sales-container sales-header-inner">
                <a href="<?= e(appPath('vendas.php')) ?>" class="sales-brand" aria-label="<?= e(APP_NAME) ?>">
                    <img src="<?= e(appPath('assets/Bexon - Logo Horizontal.png?v=1')) ?>" alt="<?= e(APP_NAME) ?>">
                </a>
                <nav class="sales-nav" aria-label="Navegação principal">
                    <a href="#recursos">Recursos</a>
                    <a href="#fluxo">Como funciona</a>
                    <a href="#planos">Planos</a>
                </nav>
                <div class="sales-header-actions">
                    <a href="<?= e(appPath('?auth=login')) ?>" class="sales-btn sales-btn-ghost">Entrar</a>
                    <a href="<?= e(appPath('?auth=register')) ?>" class="sales-btn sales-btn-primary">Começar grátis</a>
                </div>
            </div>
        </header>

        <main>
            <section class="sales-hero">
                <div class="sales-container sales-hero-grid">
                    <div class="sales-hero-copy">
                        <span class="sales-eyebrow">Operação clara para equipes em crescimento</span>
                        <h1>Gestão de tarefas e execução em um fluxo simples.</h1>
                        <p>
                            O Bexon organiza tarefas, revisões, acessos e rotinas de operação
                            em uma única visão. Menos ruído, mais entrega.
                        </p>
                        <div class="sales-hero-actions">
                            <a href="<?= e(appPath('?auth=register')) ?>" class="sales-btn sales-btn-primary">Criar workspace</a>
                            <a href="<?= e(appPath('?auth=login')) ?>" class="sales-btn sales-btn-secondary">Ver demo no app</a>
                        </div>
                        <ul class="sales-trust-list">
                            <li>Setup rápido</li>
                            <li>Controle por status e prioridade</li>
                            <li>Workspace para times e operação pessoal</li>
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
                                    <p>Validar custos do mês</p>
                                </article>
                                <article class="sales-preview-column is-review">
                                    <h3>Revisão</h3>
                                    <p>Aprovar automações</p>
                                    <p>Conferir permissões</p>
                                </article>
                            </div>
                            <div class="sales-preview-metrics">
                                <div>
                                    <span>Concluídas</span>
                                    <strong>78%</strong>
                                </div>
                                <div>
                                    <span>Tempo médio</span>
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
                            <h3>Quadro de status configurável</h3>
                            <p>Personalize etapas, cores e ordem para refletir seu fluxo real.</p>
                        </article>
                        <article class="sales-feature-card">
                            <h3>Permissões por grupo</h3>
                            <p>Controle acesso e visibilidade por área com granularidade.</p>
                        </article>
                        <article class="sales-feature-card">
                            <h3>Operação integrada</h3>
                            <p>Unifique tarefas, vencimentos, inventário e financeiro no mesmo lugar.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="fluxo" class="sales-section">
                <div class="sales-container sales-steps-shell">
                    <div class="sales-section-head">
                        <span class="sales-eyebrow">Como funciona</span>
                        <h2>Um processo direto, do primeiro login até a entrega.</h2>
                    </div>
                    <div class="sales-steps-grid">
                        <article class="sales-step-card">
                            <span>01</span>
                            <h3>Crie seu workspace</h3>
                            <p>Comece com um espaço pessoal ou convide o time em minutos.</p>
                        </article>
                        <article class="sales-step-card">
                            <span>02</span>
                            <h3>Defina status e grupos</h3>
                            <p>Ajuste etapas, permissão e visão de cada parte da operação.</p>
                        </article>
                        <article class="sales-step-card">
                            <span>03</span>
                            <h3>Execute com clareza</h3>
                            <p>Acompanhe progresso, revisões e prioridade sem perder contexto.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="planos" class="sales-section">
                <div class="sales-container">
                    <div class="sales-section-head">
                        <span class="sales-eyebrow">Planos</span>
                        <h2>Preço simples para começar rápido.</h2>
                    </div>
                    <div class="sales-pricing-grid">
                        <article class="sales-pricing-card">
                            <h3>Starter</h3>
                            <p class="sales-price">R$ 0<span>/mês</span></p>
                            <ul>
                                <li>Workspace pessoal</li>
                                <li>Status customizável</li>
                                <li>Ferramentas essenciais</li>
                            </ul>
                            <a href="<?= e(appPath('?auth=register')) ?>" class="sales-btn sales-btn-secondary">Começar sem custo</a>
                        </article>
                        <article class="sales-pricing-card is-highlight">
                            <h3>Equipe</h3>
                            <p class="sales-price">R$ 49<span>/mês</span></p>
                            <ul>
                                <li>Membros ilimitados</li>
                                <li>Permissões por grupo</li>
                                <li>Dashboard completo</li>
                            </ul>
                            <a href="<?= e(appPath('?auth=register')) ?>" class="sales-btn sales-btn-primary">Criar workspace</a>
                        </article>
                    </div>
                </div>
            </section>

            <section class="sales-section sales-final-cta">
                <div class="sales-container">
                    <div class="sales-cta-box">
                        <h2>Pronto para centralizar sua operação no Bexon?</h2>
                        <p>Crie sua conta agora e monte o fluxo ideal do seu time em poucos minutos.</p>
                        <div class="sales-hero-actions">
                            <a href="<?= e(appPath('?auth=register')) ?>" class="sales-btn sales-btn-primary">Criar conta</a>
                            <a href="<?= e(appPath('?auth=login')) ?>" class="sales-btn sales-btn-ghost">Entrar no app</a>
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
