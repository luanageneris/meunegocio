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

O banco exige senhas nao vazias. Use senhas fortes e diferentes para o usuario
da aplicacao e para o root. Alteracoes no arquivo de ambiente nao atualizam
automaticamente credenciais em um volume MySQL existente: faca backup e altere
os usuarios no banco antes de atualizar o ambiente. Para volumes existentes,
confirme que o usuario de aplicacao nao usa root e remova o usuario remoto
`root`@`%` somente depois de verificar que nao e necessario. A configuracao de
root apenas local e aplicada automaticamente somente a bancos novos.

## 2) HTTPS obrigatorio

A stack publica o Nginx apenas em `127.0.0.1:8080` (ou `WEB_PORT`). Ela nao
termina TLS. Instale no mesmo host um proxy reverso com certificado valido para
o dominio, escutando nas portas publicas 80 e 443; a porta 80 deve apenas
redirecionar para HTTPS. Configure o proxy para encaminhar o trafego HTTPS para
`http://127.0.0.1:8080`, preservando o host valido, e rejeite hosts inesperados.
O Nginx da stack informa ao PHP que as requisicoes sao HTTPS e os cookies de
sessao sao obrigatoriamente seguros. Nao exponha a porta interna diretamente.

Exemplo de blocos para um Nginx instalado no host (substitua o dominio e os
caminhos por um certificado real):

```nginx
server {
    listen 80;
    server_name seu-dominio.com;
    return 301 https://seu-dominio.com$request_uri;
}

server {
    listen 443 ssl;
    server_name seu-dominio.com;
    ssl_certificate /etc/letsencrypt/live/seu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/seu-dominio.com/privkey.pem;
    client_max_body_size 25M;

    location / {
        proxy_set_header Host $host;
        proxy_pass http://127.0.0.1:8080;
    }
}
```

Configure tambem um servidor padrao que rejeite hosts nao reconhecidos. Se
alterar `WEB_PORT`, atualize o destino `proxy_pass`. Confirme que HTTPS, login
e redirecionamentos funcionam antes de liberar o acesso publico. Sem o proxy
HTTPS, a stack nao esta pronta para receber trafego de producao. IPs de clientes
nao sao propagados para a aplicacao por esse exemplo.

## 3) Build e subida

docker compose -f compose.prod.yaml --env-file .env.production build --pull

docker compose -f compose.prod.yaml --env-file .env.production pull web mysql

docker compose -f compose.prod.yaml --env-file .env.production up -d

## 4) Migracoes

Opcao A: executar uma vez manualmente

docker compose -f compose.prod.yaml --env-file .env.production exec app php artisan migrate --force

Opcao B: automatica no bootstrap

- Em .env.production, definir RUN_MIGRATIONS=true
- Subir stack
- Depois voltar RUN_MIGRATIONS=false

## 5) Permissoes e cache

O entrypoint de producao ja executa:

- php artisan storage:link
- php artisan config:cache
- php artisan route:cache
- php artisan view:cache

## 6) Hardening aplicado

- Banco sem porta publicada no host
- Nginx publicado somente em loopback; HTTPS termina no proxy externo
- Cookies de sessao seguros, HTTP-only e criptografados
- Nginx em filesystem read-only
- Nginx executado como usuario sem privilegios na porta interna 8080
- no-new-privileges em app/web, com capacidades removidas e apenas CHOWN,
  DAC_OVERRIDE, SETGID e SETUID adicionadas ao app para preparar volumes,
  gerar caches e iniciar o PHP-FPM
- OPcache habilitado para performance
- Headers de seguranca no Nginx

## 7) Atualizacao de versao

1. Atualize o codigo no servidor
2. Rode build
3. Suba novamente com up -d
4. Rode migrate --force se necessario

O volume `app_code` conserva o codigo existente e o entrypoint inicializa esse
volume apenas quando ainda nao existe `artisan`. Portanto, recriar o container
nao atualiza sozinho o codigo em um volume ja populado. Antes de uma
atualizacao, faca backup e planeje a substituicao do volume de codigo; nunca
apague os volumes `app_storage`, `app_cache` ou `mysql_data` para atualizar a
imagem. Sem essa etapa, correcoes no codigo podem nao entrar em vigor.

## 8) Observacoes

- O Sail continua sendo a opcao para desenvolvimento local.
- Esta stack foi feita para execucao produtiva.
