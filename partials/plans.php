<?php
$plansBillingInterval = billingDefaultInterval();
$plansBillingPlans = publicBillingPlanDefinitions();
?>
<main class="plans-screen" id="plans">
    <section class="plans-shell" aria-labelledby="plans-title">
        <div class="plans-head">
            <img
                src="assets/Bexon - Logo Horizontal.png?v=1"
                alt="<?= e(APP_NAME) ?>"
                class="plans-brand-lockup"
                width="174"
                height="58"
            >
            <span class="plans-eyebrow">Escolha seu plano</span>
            <h1 id="plans-title">Ative seu acesso ao Bexon</h1>
            <p>Selecione um plano para continuar para o checkout seguro da Stripe.</p>
        </div>

        <div class="plans-billing-toggle" data-billing-toggle data-default-billing-interval="<?= e($plansBillingInterval) ?>" aria-label="Alternar cobranca">
            <button type="button" class="<?= $plansBillingInterval === 'year' ? 'is-active' : '' ?>" data-billing-interval="year" aria-pressed="<?= $plansBillingInterval === 'year' ? 'true' : 'false' ?>">
                Anual
                <span>Economize ate 2 meses</span>
            </button>
            <button type="button" class="<?= $plansBillingInterval === 'month' ? 'is-active' : '' ?>" data-billing-interval="month" aria-pressed="<?= $plansBillingInterval === 'month' ? 'true' : 'false' ?>">
                Mensal
            </button>
        </div>

        <div class="plans-grid">
            <?php foreach ($plansBillingPlans as $plan): ?>
                <?php
                $planKey = (string) ($plan['key'] ?? '');
                $isHighlightedPlan = $planKey === 'solo';
                $monthlyPriceLabel = appBillingPlanPriceLabel($plan, 'month');
                $annualPriceLabel = appBillingPlanPriceLabel($plan, 'year');
                $monthlyBillingNote = appBillingPlanBillingNote($plan, 'month');
                $annualBillingNote = appBillingPlanBillingNote($plan, 'year');
                $monthlyTrialNote = appBillingPlanTrialNote($plan, 'month');
                $annualTrialNote = appBillingPlanTrialNote($plan, 'year');
                $monthlyActionUrl = appBillingPlanActionUrl($plan, 'month');
                $annualActionUrl = appBillingPlanActionUrl($plan, 'year');
                $priceSuffix = appBillingPlanPriceSuffix($plan);
                $initialPriceLabel = $plansBillingInterval === 'month' ? $monthlyPriceLabel : $annualPriceLabel;
                $initialBillingNote = $plansBillingInterval === 'month' ? $monthlyBillingNote : $annualBillingNote;
                $initialTrialNote = $plansBillingInterval === 'month' ? $monthlyTrialNote : $annualTrialNote;
                $initialActionUrl = $plansBillingInterval === 'month' ? $monthlyActionUrl : $annualActionUrl;
                $initialPriceParts = appBillingPriceParts($initialPriceLabel);
                ?>
                <article
                    class="plans-card<?= $isHighlightedPlan ? ' is-highlight' : '' ?>"
                    data-plan-card
                    data-price-month="<?= e($monthlyPriceLabel) ?>"
                    data-price-year="<?= e($annualPriceLabel) ?>"
                    data-suffix="<?= e($priceSuffix) ?>"
                    data-note-month="<?= e($monthlyBillingNote) ?>"
                    data-note-year="<?= e($annualBillingNote) ?>"
                    data-trial-month="<?= e($monthlyTrialNote) ?>"
                    data-trial-year="<?= e($annualTrialNote) ?>"
                    data-action-month="<?= e($monthlyActionUrl) ?>"
                    data-action-year="<?= e($annualActionUrl) ?>"
                >
                    <div class="plans-card-head">
                        <h2><?= e((string) ($plan['name'] ?? 'Plano')) ?></h2>
                        <span><?= e((string) ($plan['badge'] ?? appBillingPlanUsersLabel($plan))) ?></span>
                    </div>

                    <div class="plans-price-block">
                        <p class="plans-price<?= $priceSuffix === '' ? ' is-consult-price' : '' ?>">
                            <span data-plan-price-value>
                                <?php if ($initialPriceParts['currency'] !== ''): ?>
                                    <span class="plans-price-currency"><?= e($initialPriceParts['currency']) ?></span>
                                <?php endif; ?>
                                <span class="plans-price-amount"><?= e($initialPriceParts['amount']) ?></span>
                            </span>
                            <?php if ($priceSuffix !== ''): ?>
                                <span data-plan-price-suffix><?= e($priceSuffix) ?></span>
                            <?php endif; ?>
                        </p>
                        <?php if ($initialBillingNote !== ''): ?>
                            <p class="plans-billing-note" data-plan-billing-note><?= e($initialBillingNote) ?></p>
                        <?php else: ?>
                            <p class="plans-billing-note" data-plan-billing-note hidden></p>
                        <?php endif; ?>
                        <p class="plans-trial-note" data-plan-trial-note><?= e($initialTrialNote) ?></p>
                    </div>

                    <p class="plans-summary"><?= e((string) ($plan['summary'] ?? '')) ?></p>
                    <p class="plans-limit"><?= e(appBillingPlanUsersLabel($plan)) ?></p>

                    <a href="<?= e($initialActionUrl) ?>" class="btn btn-pill btn-accent btn-block plans-action" data-plan-action>
                        <?= e((string) ($plan['cta'] ?? 'Escolher plano')) ?>
                    </a>

                    <ul class="plans-features">
                        <?php foreach ((array) ($plan['features'] ?? []) as $feature): ?>
                            <li><?= e((string) $feature) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="plans-legal">
            Ao contratar, voce concorda com os
            <a href="<?= e(siteUrl('termos')) ?>">Termos de Uso</a>
            e a
            <a href="<?= e(siteUrl('privacidade')) ?>">Politica de Privacidade</a>.
        </p>
    </section>
</main>
