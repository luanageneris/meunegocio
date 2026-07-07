# Deploy Docker em Producao

Este projeto possui uma stack de producao separada do Sail (desenvolvimento).

## Arquivos

- Dockerfile.prod
- compose.prod.yaml
- docker/prod/nginx/default.conf
- docker/prod/php/php.ini
- docker/prod/php/opcache.ini
- docker/prod/entrypoint.sh
- .env.production.example

## 1) Preparar variaveis

1. Copie o exemplo de ambiente:

cp .env.production.example .env.production

2. Defina valores fortes para:

- APP_KEY (gerar com php artisan key:generate --show)
- DB_PASSWORD
- DB_ROOT_PASSWORD
- APP_URL
- SANCTUM_STATEFUL_DOMAINS
- SESSION_DOMAIN

## 2) Build e subida

docker compose -f compose.prod.yaml --env-file .env.production build

docker compose -f compose.prod.yaml --env-file .env.production up -d

## 3) Migracoes

Opcao A: executar uma vez manualmente

docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan migrate --force

Opcao B: automatica no bootstrap

- Em .env.production, definir RUN_MIGRATIONS=true
- Subir stack
- Depois voltar RUN_MIGRATIONS=false

## 4) Permissoes e cache

O entrypoint de producao ja executa:

- php artisan storage:link
- php artisan config:cache
- php artisan route:cache
- php artisan view:cache

## 5) Hardening aplicado

- Banco sem porta publicada no host
- Nginx em filesystem read-only
- no-new-privileges + cap_drop ALL em app/web
- OPcache habilitado para performance
- Headers de seguranca no Nginx

## 6) Atualizacao de versao

1. Atualize o codigo no servidor
2. Rode build
3. Suba novamente com up -d
4. Rode migrate --force se necessario

## 7) Observacoes

- O Sail continua sendo a opcao para desenvolvimento local.
- Esta stack foi feita para execucao produtiva.
