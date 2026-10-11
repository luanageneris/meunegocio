# Deploy sem Docker na Hostinger (Hospedagem Compartilhada)

Este guia e para publicar este projeto Laravel + Vue na Hostinger sem Docker.

## Requisitos minimos

- PHP 8.4 no servidor (obrigatorio pelo lock do Composer)
- Extensoes PHP: bcmath, ctype, fileinfo, gd, intl, json, mbstring, openssl, pdo_mysql, tokenizer, xml, zip
- MySQL ativo
- Acesso SSH (recomendado) ou terminal no hPanel

## 1) Gerar pacote de producao localmente

No projeto:

```bash
chmod +x scripts/prepare-hostinger-shared.sh
./scripts/prepare-hostinger-shared.sh
```

Resultado:

- Pasta pronta: dist/hostinger_release
- Arquivo compactado: dist/my_business_hostinger_YYYYMMDD_HHMMSS.tar.gz

Esse pacote leva apenas o codigo-fonte e os arquivos de configuracao. As dependencias PHP e Node serao instaladas na Hostinger.

## 2) Subir para a Hostinger

Suba o conteudo da pasta dist/hostinger_release para uma pasta de aplicacao no servidor, por exemplo:

- ~/apps/my_business

Nao suba o arquivo .env local. Crie um .env novo no servidor.

Depois de subir os arquivos, entre na pasta da aplicacao e rode:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

## 3) Configurar Document Root

Opcao recomendada:

- Configure o dominio para apontar para: ~/apps/my_business/public

Se o painel não permitir apontar direto para public, use um subdominio que aceite custom document root.

## 4) Configurar .env no servidor

Copie de .env.production.example e ajuste:

- APP_ENV=production
- APP_DEBUG=false
- APP_URL=https://seu-dominio
- DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
- GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI (se usar login Google)
- SANCTUM_STATEFUL_DOMAINS
- SESSION_DOMAIN

## 5) Comandos finais no servidor

Dentro da pasta da aplicacao:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 6) Permissoes

Garanta escrita em:

- storage
- bootstrap/cache

Exemplo (quando permitido):

```bash
chmod -R ug+rwx storage bootstrap/cache
```

## 7) Atualizacao de versao

A cada nova release:

1. Gere novo pacote com scripts/prepare-hostinger-shared.sh
2. Suba arquivos para a pasta da aplicacao
3. Rode no servidor:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Observacoes importantes

- Em producao, não envie node_modules.
- Em producao sem Docker, não envie vendor; instale no servidor.
- O build do frontend usa @laravel/vite-plugin-wayfinder, entao o servidor precisa ter vendor instalado antes de npm run build.
