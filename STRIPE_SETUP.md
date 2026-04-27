# Stripe setup (Bexon)

## Variaveis de ambiente obrigatorias

- `STRIPE_SECRET_KEY`: chave secreta da Stripe (`sk_test_...` ou `sk_live_...`).
- `STRIPE_PRICE_ID`: ID do preco recorrente (`price_...`).
  - Fallback suportado: `STRIPE_PRODUCT_ID` (`prod_...`) para criar `price_data` inline.
- `STRIPE_WEBHOOK_SECRET`: segredo da assinatura do endpoint webhook (`whsec_...`).
- `APP_URL`: URL publica da aplicacao (ex.: `https://seu-app.up.railway.app`).

## Variaveis opcionais

- `APP_ENFORCE_BILLING=true|false`
  - Quando `true`, bloqueia acesso ao dashboard (`index.php`) para usuarios sem assinatura/trial ativa e redireciona para `home`.
- `APP_BILLING_GUEST_EMAILS=email1@dominio.com,email2@dominio.com`
  - Libera acesso sem checkout para convidados gratuitos.
  - Tambem aceita `APP_GUEST_EMAILS`.

## Fluxo recomendado de configuracao

1. Crie um Produto na Stripe Dashboard (opcional, caso use `STRIPE_PRODUCT_ID`).
2. Crie um Price recorrente mensal (recomendado) e salve o `price_...` em `STRIPE_PRICE_ID`.
3. Configure `APP_URL` com o dominio de producao real.
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

1. Defina variaveis locais (`STRIPE_SECRET_KEY`, `STRIPE_PRICE_ID`, `STRIPE_WEBHOOK_SECRET`, `APP_URL`).
2. Rode migracao:
   - `php scripts/migrate.php`
3. Inicie app localmente.
4. Use Stripe CLI para encaminhar eventos:
   - `stripe listen --forward-to http://localhost:8000/stripe-webhook`
5. Faca um checkout de teste pelo botao "Iniciar 7 dias gratis".
6. Confira a tabela `user_subscriptions` atualizando apos:
   - retorno `home?action=checkout_success&session_id=...`
   - eventos webhook.

## Railway (producao)

No Railway, configure no servico web:

- `STRIPE_SECRET_KEY`
- `STRIPE_PRICE_ID` (ou `STRIPE_PRODUCT_ID`)
- `STRIPE_WEBHOOK_SECRET`
- `APP_URL`
- `APP_ENFORCE_BILLING` (opcional)
- `APP_BILLING_GUEST_EMAILS` (opcional, para convidados gratuitos)

Depois, faca deploy e valide:

- Usuario deslogado vai para `index.php?auth=login&next=home?action=checkout#login` ao tentar trial.
- Retorno da Stripe cai em `home?action=checkout_success&session_id=...`.
- Webhook em `/stripe-webhook` recebe e sincroniza status de assinatura.
