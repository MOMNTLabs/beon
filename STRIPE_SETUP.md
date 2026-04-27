# Configuração do Stripe (Bexon)

Este projeto agora usa **cadastro/login antes do checkout** e salva o estado de assinatura em `user_subscriptions`.

## 1) Variáveis de ambiente

Configure no ambiente da aplicação:

- `STRIPE_SECRET_KEY=sk_test_...`
- `STRIPE_PRICE_ID=price_...` (recomendado)
  - Alternativa: `STRIPE_PRODUCT_ID=prod_...` (fallback legado)
- `STRIPE_WEBHOOK_SECRET=whsec_...`
- `APP_URL=https://seu-dominio.com` (para gerar URLs absolutas corretas)
- Opcional: `APP_ENFORCE_BILLING=true` para bloquear acesso ao app sem assinatura/trial ativo

## 2) Produto e preço no Stripe

1. No Dashboard Stripe, crie (ou reutilize) um produto.
2. Crie um **preço recorrente mensal** para o produto.
3. Copie o `price_id` (`price_...`) e configure em `STRIPE_PRICE_ID`.

> O checkout usa `mode=subscription` com `trial_period_days=7`.

## 3) Webhook no Stripe

Crie um endpoint no Stripe apontando para:

- `https://seu-dominio.com/stripe-webhook`

Eventos mínimos recomendados:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.expired`
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`

Depois, copie o segredo do endpoint (`whsec_...`) para `STRIPE_WEBHOOK_SECRET`.

## 4) Fluxo esperado

1. Usuário clica em iniciar teste.
2. Se não estiver logado, é enviado para cadastro/login com `next=home?action=checkout`.
3. Checkout cria sessão Stripe com `client_reference_id` e metadata do usuário.
4. Stripe retorna em `home?action=checkout_success&session_id=...`.
5. App sincroniza a sessão e os webhooks atualizam o status final da assinatura.

## 5) Teste rápido local

1. Rode migrações (`php scripts/migrate.php`).
2. Configure as variáveis Stripe em ambiente de teste.
3. Faça cadastro, inicie checkout e conclua com cartão de teste.
4. Verifique tabela `user_subscriptions`.
5. Com `APP_ENFORCE_BILLING=true`, confirme bloqueio sem trial/assinatura ativa.

## 6) Cartões de teste úteis (Stripe)

- Sucesso: `4242 4242 4242 4242`
- Qualquer data futura, CVC qualquer, CEP qualquer.

Consulte sempre a documentação oficial do Stripe para casos avançados (SCA, falhas de cobrança, invoices etc.).
