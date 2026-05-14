# Deploy e operacao

Este projeto pode rodar localmente com fallback para SQLite e arquivos em `storage/`, mas em ambientes de producao o comportamento agora fica mais restrito.

## Regras de producao

- Use PostgreSQL via `DATABASE_URL` ou variaveis `PG*`.
- Nao dependa de fallback para SQLite em producao.
- Defina `APP_VAULT_ENCRYPTION_KEY` com uma chave fixa fora do repositorio.
- Mantenha `APP_AUTO_MIGRATE=false` em producao.
- Execute migracoes de forma explicita no deploy.

## Variaveis recomendadas

```env
APP_ENV=production
APP_URL=https://app.bexon.com.br
SITE_URL=https://bexon.com.br
COOKIE_DOMAIN=bexon.com.br

DATABASE_URL=postgres://USER:PASSWORD@HOST:5432/DBNAME?sslmode=require
# ou use PGHOST / PGPORT / PGDATABASE / PGUSER / PGPASSWORD / PGSSLMODE=require

APP_VAULT_ENCRYPTION_KEY=base64:CHAVE_FIXA_DE_32_BYTES
APP_AUTO_MIGRATE=false
```

Importante: nessas variaveis, nao envolva os valores com aspas no painel do provedor. Exemplo correto: `APP_URL=https://app.bexon.com.br`. Exemplo que pode quebrar integrações: `APP_URL="https://app.bexon.com.br"`.

## Google OAuth e Google Drive

Se o app usar login com Google ou anexo via Google Drive, o cliente OAuth do Google Cloud precisa autorizar exatamente os callbacks usados pelo app.

Variaveis comuns:

```env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_DRIVE_SCOPES=https://www.googleapis.com/auth/drive.readonly

# opcional: so use se quiser um callback exclusivo para o Drive
GOOGLE_DRIVE_REDIRECT_URI=https://app.bexon.com.br/?action=google_drive_callback
```

Notas:

- O navegador proprio do Google Drive no Bexon nao precisa mais de `GOOGLE_DRIVE_API_KEY` nem `GOOGLE_DRIVE_APP_ID`.
- Se usuarios antigos tiverem conectado o Drive com `drive.file`, sera preciso reconectar para liberar a navegacao completa por pastas.

Callbacks autorizados no Google Cloud:

- Login com Google: `https://app.bexon.com.br/?action=google_callback`
- Google Drive: por padrao usa o mesmo callback do login (`https://app.bexon.com.br/?action=google_callback`)
- Google Drive com callback dedicado: autorize tambem `https://app.bexon.com.br/?action=google_drive_callback` se `GOOGLE_DRIVE_REDIRECT_URI` estiver definido com essa URL

Se o Google mostrar `Erro 400: redirect_uri_mismatch`, o `redirect_uri` enviado pelo app nao esta cadastrado exatamente na tela de credenciais do projeto no Google Cloud. Revise esquema (`https`), host, query string e barra final.

## Comandos operacionais

Valide o ambiente de producao antes do deploy:

```bash
php scripts/check-production-env.php
```

Rode esse preflight no mesmo runtime do deploy. Ele verifica tambem se `pdo_pgsql` esta carregado.

Rode o release completo com preflight + migracao:

```bash
php scripts/release.php
```

Se quiser rodar apenas as migracoes de forma explicita:

```bash
php scripts/migrate.php
```

Se precisar executar a politica de tarefas vencidas manualmente:

```bash
php scripts/run-overdue-policy.php
```

## Fallback de e-mail

- Em ambiente local, quando o envio de e-mail nao estiver configurado, o conteudo continua sendo gravado em `storage/password-reset-mails.log` e `storage/workspace-invite-mails.log`.
- Em producao, esse fallback nao grava arquivos locais. O app registra apenas o erro operacional nos logs da aplicacao.

## Overrides opcionais

Estas flags existem para cenarios especiais e nao devem ser a configuracao padrao de producao:

```env
APP_ALLOW_SQLITE_FALLBACK=true
APP_ALLOW_FILE_VAULT_KEY=true
APP_DIAGNOSTIC_FILES=true
APP_PRODUCTION_GUARDS=true
```
