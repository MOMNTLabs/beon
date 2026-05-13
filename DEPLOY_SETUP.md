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
