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
    <link rel="icon" type="image/png" href="assets/Bexon---Logo-Symbol.png?v=1">
    <link rel="shortcut icon" href="assets/Bexon---Logo-Symbol.png?v=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css?v=<?= e($stylesAssetVersion) ?>">
    <link rel="stylesheet" href="assets/theme-bexon.css?v=<?= e($themeBexonAssetVersion) ?>">
    <link rel="stylesheet" href="assets/vendas.css?v=<?= e($salesAssetVersion) ?>">
</head>
<body class="is-sales-page">
    <div class="sales-page">
        <header class="sales-header">
            <div class="sales-container sales-header-inner">
                <a href="vendas.php" class="sales-brand" aria-label="<?= e(APP_NAME) ?>">
                    <img src="assets/Bexon - Logo Horizontal.png?v=1" alt="<?= e(APP_NAME) ?>" width="116" height="29">
                </a>
                <nav class="sales-nav" aria-label="Navegacao principal">
                    <a href="#recursos">Recursos</a>
                    <a href="#fluxo">Como funciona</a>
                    <a href="#planos">Planos</a>
                </nav>
                <div class="sales-header-actions">
                    <a href="index.php?auth=login" class="sales-btn sales-btn-ghost">Entrar</a>
                    <a href="index.php?auth=register" class="sales-btn sales-btn-primary">Comecar gratis</a>
                </div>
            </div>
        </header>

        <main>
            <section class="sales-hero">
                <div class="sales-container sales-hero-grid">
                    <div class="sales-hero-copy">
                        <span class="sales-eyebrow">Operacao clara para equipes em crescimento</span>
                        <h1>Gestao de tarefas e execucao em um fluxo simples.</h1>
                        <p>
                            O Bexon organiza tarefas, revisoes, acessos e rotinas de operacao
                            em uma unica visao. Menos ruido, mais entrega.
                        </p>
                        <div class="sales-hero-actions">
                            <a href="index.php?auth=register" class="sales-btn sales-btn-primary">Criar workspace</a>
                            <a href="index.php?auth=login" class="sales-btn sales-btn-secondary">Ver demo no app</a>
                        </div>
                        <ul class="sales-trust-list">
                            <li>Setup rapido</li>
                            <li>Controle por status e prioridade</li>
                            <li>Workspace para times e operacao pessoal</li>
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
                                    <p>Validar custos do mes</p>
                                </article>
                                <article class="sales-preview-column is-review">
                                    <h3>Revisao</h3>
                                    <p>Aprovar automacoes</p>
                                    <p>Conferir permissoes</p>
                                </article>
                            </div>
                            <div class="sales-preview-metrics">
                                <div>
                                    <span>Concluidas</span>
                                    <strong>78%</strong>
                                </div>
                                <div>
                                    <span>Tempo medio</span>
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
                            <h3>Quadro de status configuravel</h3>
                            <p>Personalize etapas, cores e ordem para refletir seu fluxo real.</p>
                        </article>
                        <article class="sales-feature-card">
                            <h3>Permissoes por grupo</h3>
                            <p>Controle acesso e visibilidade por area com granularidade.</p>
                        </article>
                        <article class="sales-feature-card">
                            <h3>Operacao integrada</h3>
                            <p>Unifique tarefas, vencimentos, inventario e financeiro no mesmo lugar.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="fluxo" class="sales-section">
                <div class="sales-container sales-steps-shell">
                    <div class="sales-section-head">
                        <span class="sales-eyebrow">Como funciona</span>
                        <h2>Um processo direto, do primeiro login ate a entrega.</h2>
                    </div>
                    <div class="sales-steps-grid">
                        <article class="sales-step-card">
                            <span>01</span>
                            <h3>Crie seu workspace</h3>
                            <p>Comece com um espaco pessoal ou convide o time em minutos.</p>
                        </article>
                        <article class="sales-step-card">
                            <span>02</span>
                            <h3>Defina status e grupos</h3>
                            <p>Ajuste etapas, permissao e visao de cada parte da operacao.</p>
                        </article>
                        <article class="sales-step-card">
                            <span>03</span>
                            <h3>Execute com clareza</h3>
                            <p>Acompanhe progresso, revisoes e prioridade sem perder contexto.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="planos" class="sales-section">
                <div class="sales-container">
                    <div class="sales-section-head">
                        <span class="sales-eyebrow">Planos</span>
                        <h2>Preco simples para comecar rapido.</h2>
                    </div>
                    <div class="sales-pricing-grid">
                        <article class="sales-pricing-card">
                            <h3>Starter</h3>
                            <p class="sales-price">R$ 0<span>/mes</span></p>
                            <ul>
                                <li>Workspace pessoal</li>
                                <li>Status customizavel</li>
                                <li>Ferramentas essenciais</li>
                            </ul>
                            <a href="index.php?auth=register" class="sales-btn sales-btn-secondary">Comecar sem custo</a>
                        </article>
                        <article class="sales-pricing-card is-highlight">
                            <h3>Equipe</h3>
                            <p class="sales-price">R$ 49<span>/mes</span></p>
                            <ul>
                                <li>Membros ilimitados</li>
                                <li>Permissoes por grupo</li>
                                <li>Dashboard completo</li>
                            </ul>
                            <a href="index.php?auth=register" class="sales-btn sales-btn-primary">Criar workspace</a>
                        </article>
                    </div>
                </div>
            </section>

            <section class="sales-section sales-final-cta">
                <div class="sales-container">
                    <div class="sales-cta-box">
                        <h2>Pronto para centralizar sua operacao no Bexon?</h2>
                        <p>Crie sua conta agora e monte o fluxo ideal do seu time em poucos minutos.</p>
                        <div class="sales-hero-actions">
                            <a href="index.php?auth=register" class="sales-btn sales-btn-primary">Criar conta</a>
                            <a href="index.php?auth=login" class="sales-btn sales-btn-ghost">Entrar no app</a>
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
