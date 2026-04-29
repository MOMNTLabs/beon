# Stripe setup (Bexon)

## Variaveis de ambiente obrigatorias

- `STRIPE_SECRET_KEY`: chave secreta da Stripe (`sk_test_...` ou `sk_live_...`).
- IDs dos precos recorrentes mensais e anuais:
  - `STRIPE_SOLO_PRICE_ID`
  - `STRIPE_SOLO_ANNUAL_PRICE_ID`
  - `STRIPE_TEAM_PRICE_ID`
  - `STRIPE_TEAM_ANNUAL_PRICE_ID`
  - `STRIPE_BUSINESS_PRICE_ID`
  - `STRIPE_BUSINESS_ANNUAL_PRICE_ID`
- Fallback suportado: `STRIPE_PRODUCT_ID` (`prod_...`).
  - Com `STRIPE_PRODUCT_ID`, o app tenta localizar o preco ativo do produto usando metadata `plan=solo|team|business` e `billing_interval=month|year`.
  - Se nao encontrar metadata compativel, cria `price_data` inline com os valores configurados no app.
- O plano Enterprise nao usa checkout Stripe: o botao abre um e-mail para `suporte@bexon.com.br`.
- `STRIPE_WEBHOOK_SECRET`: segredo da assinatura do endpoint webhook (`whsec_...`).
- `APP_URL`: URL publica da aplicacao (ex.: `https://seu-app.up.railway.app`).

## Variaveis opcionais

- `APP_ENFORCE_BILLING=true|false`
  - Quando `true`, bloqueia acesso ao dashboard (`index.php`) para usuarios sem assinatura/trial ativa e redireciona para `home`.
- `APP_BILLING_GUEST_EMAILS=email1@dominio.com,email2@dominio.com`
  - Libera acesso sem checkout para convidados gratuitos. Esse e o fluxo recomendado para acessos Free/convidados.
  - Tambem aceita `APP_GUEST_EMAILS`.
- `APP_DEFAULT_BILLING_PLAN=solo`
  - Define qual plano legado usa `STRIPE_PRICE_ID`.
- `STRIPE_PRICE_ID`
  - Fallback legado mensal para o plano definido em `APP_DEFAULT_BILLING_PLAN` (por padrao, `solo`).
- `STRIPE_TRIAL_PERIOD_DAYS` ou `APP_BILLING_TRIAL_DAYS`
  - Opcional. Quando maior que zero, aplica trial nos planos pagos. O padrao atual e `7`.
- `APP_DEFAULT_BILLING_INTERVAL=year|month`
  - Define o intervalo visual padrao da home. O padrao atual e `year`.

## Planos atuais

| Plano | Mensal | Anual | Usuarios | Metadata esperada |
| --- | ---: | ---: | ---: | --- |
| Solo | R$ 19,90 | R$ 197 | 1 | `plan=solo`, `billing_interval=month|year`, `max_users=1` |
| Team | R$ 49,90 | R$ 497 | 5 | `plan=team`, `billing_interval=month|year`, `max_users=5` |
| Business | R$ 99,90 | R$ 997 | 15 | `plan=business`, `billing_interval=month|year`, `max_users=15` |
| Enterprise | Sob consulta | Sob consulta | Mais de 15 | Nao usa Stripe |

O plano Free nao fica publico na home. Para liberar acesso gratuito, adicione o e-mail em `APP_BILLING_GUEST_EMAILS`.

## Fluxo recomendado de configuracao

1. Crie um Produto na Stripe Dashboard.
2. Crie os Prices recorrentes mensais e anuais para Solo, Team e Business.
3. Em cada Price, configure metadata `plan`, `billing_interval` e `max_users` conforme a tabela acima.
4. Salve os `price_...` nas variaveis `STRIPE_SOLO_PRICE_ID`, `STRIPE_SOLO_ANNUAL_PRICE_ID`, `STRIPE_TEAM_PRICE_ID`, `STRIPE_TEAM_ANNUAL_PRICE_ID`, `STRIPE_BUSINESS_PRICE_ID` e `STRIPE_BUSINESS_ANNUAL_PRICE_ID`.
   - Alternativa: configure apenas `STRIPE_PRODUCT_ID` e mantenha os metadados dos Prices corretos.
   - Enterprise nao precisa de Price na Stripe; o contato comercial acontece por e-mail.
5. Configure `APP_URL` com o dominio de producao real.
6. Publique o endpoint webhook em:
   - `POST {APP_URL}/stripe-webhook`
7. Na Stripe, registre os eventos:
   - `checkout.session.completed`
   - `checkout.session.async_payment_succeeded`
   - `checkout.session.expired`
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
8. Copie o segredo webhook (`whsec_...`) para `STRIPE_WEBHOOK_SECRET`.

## Teste local

1. Defina variaveis locais (`STRIPE_SECRET_KEY`, os `STRIPE_*_PRICE_ID` ou `STRIPE_PRODUCT_ID`, `STRIPE_WEBHOOK_SECRET`, `APP_URL`).
2. Rode migracao:
   - `php scripts/migrate.php`
3. Inicie app localmente.
4. Use Stripe CLI para encaminhar eventos:
   - `stripe listen --forward-to http://localhost:8000/stripe-webhook`
5. Faca um checkout de teste por um dos botoes de plano na home.
6. Confira a tabela `user_subscriptions` atualizando apos:
   - retorno `home?action=checkout_success&session_id=...`
   - eventos webhook.

## Railway (producao)

No Railway, configure no servico web:

- `STRIPE_SECRET_KEY`
- `STRIPE_SOLO_PRICE_ID`
- `STRIPE_SOLO_ANNUAL_PRICE_ID`
- `STRIPE_TEAM_PRICE_ID`
- `STRIPE_TEAM_ANNUAL_PRICE_ID`
- `STRIPE_BUSINESS_PRICE_ID`
- `STRIPE_BUSINESS_ANNUAL_PRICE_ID`
- `STRIPE_PRODUCT_ID` (opcional, como fallback)
- `STRIPE_WEBHOOK_SECRET`
- `APP_URL`
- `APP_ENFORCE_BILLING` (opcional)
- `APP_BILLING_GUEST_EMAILS` (opcional, para convidados gratuitos)

Depois, faca deploy e valide:

- Usuario deslogado vai para `index.php?auth=login&next=home?action=checkout&plan=...#login` ao escolher um plano.
- Retorno da Stripe cai em `home?action=checkout_success&session_id=...`.
- Webhook em `/stripe-webhook` recebe e sincroniza status de assinatura.
