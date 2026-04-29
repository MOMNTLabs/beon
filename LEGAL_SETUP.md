# Configuracao legal do Bexon

As paginas publicas de conformidade usam variaveis de ambiente para exibir os dados oficiais da empresa.
Preencha estes valores no ambiente de producao antes de divulgar ou vender o servico.

## Variaveis obrigatorias

```env
LEGAL_COMPANY_NAME="Razao social da empresa"
LEGAL_TRADE_NAME="Bexon"
LEGAL_CNPJ="00.000.000/0000-00"
LEGAL_ADDRESS="Endereco completo da empresa"
LEGAL_SUPPORT_EMAIL="suporte@bexon.com.br"
LEGAL_DPO_NAME="Nome do encarregado ou area responsavel"
LEGAL_UPDATED_AT="29/04/2026"
```

O canal unico de contato exibido nas paginas publicas e `suporte@bexon.com.br`, inclusive para privacidade, LGPD e solicitacoes de dados.

## Chave do cofre de acessos

O gerenciador de acessos grava senhas criptografadas no banco. Em producao, defina uma chave fixa e guarde-a fora do repositorio:

```env
APP_VAULT_ENCRYPTION_KEY="base64:cole-aqui-uma-chave-aleatoria-de-32-bytes"
```

Se a variavel nao existir, o Bexon cria uma chave local em `storage/vault.key`. Essa alternativa funciona para desenvolvimento, mas em producao a chave deve ficar em segredo de ambiente ou cofre de segredos.

## Paginas publicas

- `/privacidade`: Politica de Privacidade
- `/termos`: Termos de Uso
- `/cookies`: Politica de Cookies
- `/dados`: Canal de direitos do titular

## Observacoes importantes

- Revise os textos com assessoria juridica antes de publicar.
- Atualize a Politica de Cookies antes de adicionar analytics, pixels, tags de marketing ou ferramentas de rastreamento.
- Nao perca a chave do cofre: sem ela, as senhas armazenadas nao poderao ser descriptografadas.
