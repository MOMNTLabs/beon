# Stripe setup (Bexon)

## Variáveis de ambiente obrigatórias

- `STRIPE_SECRET_KEY`: chave secreta da Stripe (`sk_test_...` ou `sk_live_...`).
- `STRIPE_PRICE_ID`: ID do preço recorrente (`price_...`).
  - Fallback suportado: `STRIPE_PRODUCT_ID` (`prod_...`) para criar `price_data` inline.
- `STRIPE_WEBHOOK_SECRET`: segredo da assinatura do endpoint webhook (`whsec_...`).
- `APP_URL`: URL pública da aplicação (ex.: `https://seu-app.up.railway.app`).

## Variável opcional

- `APP_ENFORCE_BILLING=true|false`
  - Quando `true`, bloqueia acesso ao dashboard (`index.php`) para usuários sem assinatura/trial ativa e redireciona para `home`.

## Fluxo recomendado de configuração

1. Crie um Produto na Stripe Dashboard (opcional, caso use `STRIPE_PRODUCT_ID`).
2. Crie um Price recorrente mensal (recomendado) e salve o `price_...` em `STRIPE_PRICE_ID`.
3. Configure `APP_URL` com o domínio de produção real.
4. Publique o endpoint webhook em:
   - `POST {APP_URL}/stripe-webhook`
5. Na Stripe, registre os eventos:
   - `checkout.session.completed`
   - `checkout.session.async_payment_succeeded`
   - `checkout.session.expired`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
6. Copie o segredo webhook (`whsec_...`) para `STRIPE_WEBHOOK_SECRET`.

## Teste local

1. Defina variáveis locais (`STRIPE_SECRET_KEY`, `STRIPE_PRICE_ID`, `STRIPE_WEBHOOK_SECRET`, `APP_URL`).
2. Rode migração:
   - `php scripts/migrate.php`
3. Inicie app localmente.
4. Use Stripe CLI para encaminhar eventos:
   - `stripe listen --forward-to http://localhost:8000/stripe-webhook`
5. Faça um checkout de teste pelo botão “Iniciar 7 dias grátis”.
6. Confira a tabela `user_subscriptions` atualizando após:
   - retorno `home?action=checkout_success&session_id=...`
   - eventos webhook.

## Railway (produção)

No Railway, configure no serviço web:

- `STRIPE_SECRET_KEY`
- `STRIPE_PRICE_ID` (ou `STRIPE_PRODUCT_ID`)
- `STRIPE_WEBHOOK_SECRET`
- `APP_URL`
- `APP_ENFORCE_BILLING` (opcional)

Depois, faça deploy e valide:

- Usuário deslogado vai para `index.php?auth=login&next=home?action=checkout#login` ao tentar trial.
- Retorno da Stripe cai em `home?action=checkout_success&session_id=...`.
- Webhook em `/stripe-webhook` recebe e sincroniza status de assinatura.
