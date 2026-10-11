cat ~/.ssh/github_actions_hostinger_deploy.pub | ssh -p SUA_PORTA_SSH SEU_USUARIO@SEU_HOST "mkdir -p ~/.ssh; chmod 700 ~/.ssh; cat >> ~/.ssh/authorized_keys; chmod 600 ~/.ssh/authorized_keys"
# Deploy Automatico na Hostinger (merge na production)

Este guia cobre o seu cenario real:

- Projeto ja esta rodando em `~/domains/meunegocio.cloud/public_html`
- Pasta atual nao esta conectada a git
- Voce precisa manter o mesmo caminho para nao quebrar o start
- Nao pode perder o `.env`

Objetivo: toda vez que houver merge/push na branch `production`, o servidor atualizar codigo e publicar automaticamente.

Importante para o seu cenario atual:

- O servidor Hostinger nao tem `npm`.
- O build do frontend deve ser feito no GitHub Actions (CI), nao no servidor.

---

## 1) Estrategia segura (sem trocar caminho)

Vamos:

1. Fazer backup do `.env` e de arquivos sensiveis
2. Inicializar git na mesma pasta `public_html`
3. Conectar com o repositorio remoto
4. Criar script de deploy no servidor
5. Criar workflow no GitHub Actions para executar o script por SSH

Assim, o app continua no mesmo path.

---

## 2) Preparacao no servidor (rodar uma unica vez)

Conecte por SSH e rode:

```bash
cd ~/domains/meunegocio.cloud/public_html

# 2.1 Backup de seguranca
tar -czf ~/backup_public_html_before_git_$(date +%Y%m%d_%H%M%S).tar.gz .
cp .env ~/env_backup_$(date +%Y%m%d_%H%M%S)

# opcional: backup de uploads/publicos, se existirem arquivos do usuario
[ -d storage/app/public ] && tar -czf ~/backup_storage_public_$(date +%Y%m%d_%H%M%S).tar.gz storage/app/public || true
```

---

## 3) Transformar a pasta atual em pasta git (mesmo diretorio)

Ainda em `~/domains/meunegocio.cloud/public_html`:

```bash
# 3.1 Inicializa git
git init

# 3.2 Configura branch local production
git checkout -b production

# 3.3 Aponta para seu repo (SSH)
git remote add origin git@github.com:SEU_USUARIO_OU_ORG/SEU_REPO.git

# 3.4 Busca refs remotas
git fetch origin
```

Agora faca o alinhamento com o remoto de forma segura para preservar `.env`:

```bash
# guarda .env fora da pasta
cp .env /tmp/my_business.env.keep

# remove tudo menos .git (limpeza controlada)
find . -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +

# baixa estado exato da production remota
git checkout -B production origin/production

# restaura .env
cp /tmp/my_business.env.keep .env
rm -f /tmp/my_business.env.keep
```

Observacao: este processo mantem o caminho e troca somente o conteudo para o codigo versionado correto.

---

## 4) Instalar dependencias e validar app (primeira sincronizacao)

No servidor:

```bash
cd ~/domains/meunegocio.cloud/public_html
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Se o `APP_KEY` ja existe no `.env`, voce pode pular o `key:generate --force`.

Se aparecer `npm: command not found`, esta correto para o seu servidor (isso sera tratado no workflow da etapa 7).

---

## 5) Criar script de deploy no servidor

Crie arquivo `~/domains/meunegocio.cloud/public_html/deploy-production.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$HOME/domains/meunegocio.cloud/public_html"
BRANCH="production"

cd "$APP_DIR"

# lock para evitar deploy simultaneo
exec 9>"$APP_DIR/.deploy.lock"
flock -n 9 || { echo "Deploy ja em execucao"; exit 1; }

echo "[1/6] Atualizando codigo..."
git fetch origin --prune
git checkout -B production "origin/$BRANCH" || git checkout -B production origin/main
git reset --hard HEAD
git clean -fd -e .env

# Garante que .env existe
if [ ! -f .env ]; then
  echo "ERRO: .env nao encontrado em $APP_DIR"
  exit 1
fi

echo "[2/6] Composer install..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "[3/6] Migracoes..."
php artisan migrate --force

echo "[4/6] Limpeza e link..."
php artisan storage:link || true
php artisan optimize:clear

echo "[5/6] Caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[6/6] Deploy concluido com sucesso"
```

Permissao:

```bash
chmod +x ~/domains/meunegocio.cloud/public_html/deploy-production.sh
```

Teste manual:

```bash
bash ~/domains/meunegocio.cloud/public_html/deploy-production.sh
```

---

## 6) Configurar chave SSH para o GitHub Actions

### 6.1 Onde rodar cada comando

- Terminal LOCAL: seu computador pessoal
- Terminal SERVIDOR: SSH da Hostinger

O erro que voce viu (`cat github_actions_hostinger_deploy.pub: No such file or directory`) aconteceu porque o comando foi rodado no servidor, mas a chave foi criada no computador local.

### 6.2 Gerar chave no computador LOCAL

No terminal do seu computador local (nao no servidor):

```bash
ssh-keygen -t ed25519 -C "github-actions-hostinger-deploy" -f ~/.ssh/github_actions_hostinger_deploy -N ""
```

Isso cria:

- `~/.ssh/github_actions_hostinger_deploy` (chave privada)
- `~/.ssh/github_actions_hostinger_deploy.pub` (chave publica)

### 6.3 Adicionar chave publica no SERVIDOR

Ainda no computador LOCAL, rode (substitua pelos seus dados):

```bash
cat ~/.ssh/github_actions_hostinger_deploy.pub | ssh -p SUA_PORTA_SSH SEU_USUARIO@SEU_HOST "mkdir -p ~/.ssh; chmod 700 ~/.ssh; cat >> ~/.ssh/authorized_keys; chmod 600 ~/.ssh/authorized_keys"
```

Exemplo com seus dados:

```bash
cat ~/.ssh/github_actions_hostinger_deploy.pub | ssh -p 65002 u329628686@SEU_HOST_HOSTINGER "mkdir -p ~/.ssh; chmod 700 ~/.ssh; cat >> ~/.ssh/authorized_keys; chmod 600 ~/.ssh/authorized_keys"
```

### 6.4 Testar chave (LOCAL -> SERVIDOR)

No computador LOCAL:

```bash
ssh -i ~/.ssh/github_actions_hostinger_deploy -p SUA_PORTA_SSH SEU_USUARIO@SEU_HOST "echo SSH_OK"
```

Se aparecer `SSH_OK`, a chave esta funcionando.

### 6.5 Criar Secrets no GitHub

No GitHub repo > Settings > Secrets and variables > Actions, crie:

- `PROD_HOST` = host SSH da Hostinger
- `PROD_PORT` = porta SSH (geralmente 65002 ou a que seu painel mostrar)
- `PROD_USER` = seu usuario SSH (ex.: `u329628686`)
- `PROD_SSH_KEY` = conteudo completo de `~/.ssh/github_actions_hostinger_deploy` (chave privada)
- `PROD_APP_DIR` = `/home/u329628686/domains/meunegocio.cloud/public_html`

Para copiar a chave privada no LOCAL:

```bash
cat ~/.ssh/github_actions_hostinger_deploy
```

---

## 7) Workflow GitHub Actions (deploy no merge/push da production)

Este workflow faz:

1. Build do frontend no CI (onde existe Node)
2. Upload do `public/build` para o servidor
3. Execucao do `deploy-production.sh` no servidor

Crie `.github/workflows/deploy-production.yml`:

```yaml
name: Deploy Production Hostinger

on:
  push:
    branches:
      - production

concurrency:
  group: production-deploy
  cancel-in-progress: false

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: npm

      - name: Build frontend
        run: |
          npm ci
          npm run build

      - name: Upload public/build to server
        uses: appleboy/scp-action@v0.1.7
        with:
          host: ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key: ${{ secrets.PROD_SSH_KEY }}
          port: ${{ secrets.PROD_PORT }}
          source: "public/build/*"
          target: "${{ secrets.PROD_APP_DIR }}/public/build"

      - name: SSH deploy
        uses: appleboy/ssh-action@v1.2.0
        with:
          host: ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key: ${{ secrets.PROD_SSH_KEY }}
          port: ${{ secrets.PROD_PORT }}
          script_stop: true
          script: |
            bash ${{ secrets.PROD_APP_DIR }}/deploy-production.sh
```

---

## 8) Protecoes recomendadas na branch production

No GitHub (Branch protection rules):

- Require a pull request before merging
- Require approvals
- Require status checks to pass
- Restrict direct pushes (opcional, recomendado)

Assim, deploy automatico acontece somente quando entrar alteracao aprovada em `production`.

---

## 9) Rollback rapido

No servidor:

```bash
cd ~/domains/meunegocio.cloud/public_html
git log --oneline -n 10

# volta para commit anterior (troque SHA)
git reset --hard SHA_ANTERIOR

composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 10) Checklist final

- [ ] App continua no path `~/domains/meunegocio.cloud/public_html`
- [ ] `.env` preservado e valido
- [ ] `deploy-production.sh` executa sem erro via SSH manual
- [ ] Teste `ssh -i ~/.ssh/github_actions_hostinger_deploy ... "echo SSH_OK"` passou
- [ ] Secrets do GitHub configurados
- [ ] Workflow ativo em `.github/workflows/deploy-production.yml`
- [ ] Merge em `production` dispara deploy automatico
