# Desenvolvimento com Docker

Antes de iniciar o Sail, configure no `.env`:

- `DB_HOST=mysql`, `DB_USERNAME` diferente de `root` e uma `DB_PASSWORD` forte.
- `DB_ROOT_PASSWORD` forte e diferente da senha do usuario da aplicacao.
- `DB_DATABASE` com o nome do banco desejado.

O Laravel, o Vite e o MySQL sao publicados somente em `127.0.0.1`. As senhas
sao obrigatorias e o usuario root nao e criado para acesso remoto em bancos
novos.

Se o volume `sail-mysql` ja existia, alterar o `.env` nao altera os usuarios
ou senhas ja gravados no MySQL. Faca backup e migre as credenciais no proprio
banco antes de recriar o servico: entre interativamente com
`docker exec -it my_business-mysql-1 mysql -u root -p`, defina uma senha forte
para `root`@`localhost`, configure um usuario de aplicacao nao-root com acesso
ao banco e ao banco de testes, e remova `root`@`%` depois de confirmar que nao
e necessario. Atualize as senhas correspondentes no `.env`. A remocao pode ser
feita com `DROP USER IF EXISTS 'root'@'%';`. Nao remova o volume: ele contem
os dados existentes.

## Relatorio mensal pelo Dashboard

No calendario do Dashboard, selecione o mes e o ano e use **Gerar relatorio PDF**
no cartao da empresa. O download usa o mesmo extrato mensal enviado por e-mail
no primeiro dia do mes, com os dados apenas da empresa autenticada.
O PDF usa o PIN configurado em **Minha Conta > Lembretes e relatorios automaticos**.
Configure esse PIN antes do primeiro download. Nao e necessario ativar os envios
por e-mail; gerar o PDF nao envia mensagens nem altera o historico dos envios.

Endpoint autenticado: `GET /api/dashboard/monthly-report?month=11&year=2026`.
Mes e ano sao obrigatorios (1 a 12 e 2000 a 2100); erros retornam JSON e o
sucesso retorna o arquivo `application/pdf`.

## Comissoes no resumo do Dashboard

O indicador **Comissoes do mes** fica no **Resumo** e soma as comissoes pagas
e pendentes pela data da comissao no mes selecionado. **Saidas acumuladas**
soma os lancamentos financeiros de saida do mesmo periodo, incluindo as
comissoes lancadas como saida (como as importadas da planilha).
O lucro e entradas menos saidas: nao se subtrai o indicador de comissoes
novamente. Uma comissao sem lancamento de saida nao entra nesse total de saidas.

## Periodo padrao de Oportunidades

O periodo inicial vai do primeiro dia do mes ate hoje. A data de hoje vem
do servidor, no fuso configurado em `app.timezone`, e e a mesma usada pela
validacao da API. Assim, a virada de dia no navegador em outro fuso nao
seleciona uma data futura para a aplicacao. O intervalo maximo permanece
de dois anos e datas finais futuras sao bloqueadas.

## Calculos das Curiosidades

Cada cartao de Curiosidades no Dashboard tem um icone de informacao. Passe o
cursor para ver a dica ou clique para abrir as regras especificas do indicador.
O modal explica a elegibilidade, o periodo selecionado, os anos considerados
quando disponiveis, o tratamento de empates e dias sem entradas e os dados
adicionais mostrados quando nao existe historico do mes corrente.

As curiosidades de medias mensais e por dia da semana apresentam entradas
e saidas separadamente, sem subtrair os gastos das entradas. A media de saidas
usa os mesmos anos e dias da media de entradas, incluindo zeros e o corte
ate hoje quando o mes selecionado e o corrente. As saidas incluem comissoes
com lancamento financeiro de saida, sem contar a comissao novamente.
Sem historico comparavel, o mes corrente em andamento mostra tambem os totais
de entradas e saidas e as medias semanais ate hoje. Ranking e melhor dia
de vendas continuam baseados apenas em entradas, nao em lucro.
O grafico semanal ocupa uma linha completa no desktop. Cada dia combina
entradas (verde) e saidas (vermelho) na mesma barra, com os valores abaixo
e o saldo medio (entradas menos saidas) ao lado. A escala e comum a todos
os dias, baseada na maior soma de entradas e saidas medias.

# Start docker
```
sail up -d
```

# Gerar chave da aplicação
sail artisan key:generate

# Rodar migrations
sail artisan migrate

# Instalar dependências NPM (se tiver Vue.js)
sail npm install

# Compilar assets
sail npm run dev

# Ver status dos containers
docker ps

# Ver logs
sail logs

# Parar containers
sail down

# Iniciar novamente
sail up -d

# Entrar no container
sail shell

# Rodar comandos Artisan
sail artisan migrate
sail artisan tinker

# Rodar testes
sail test

# Limpar cache
sail artisan cache:clear
sail artisan config:clear

# você pode usar: 
art migrate
mfs  # Limpa e recria banco com seeders


# Rodar Vite em modo desenvolvimento (hot reload)
sail npm run dev

# Build para produção
sail npm run build

# Rodar em background (tmux/screen)
# Vamos usar 2 terminais separados:



# enviar notificação diaria 
sail artisan notifications:send-reminders --channel=daily --force --ignore-history --user_email=luanacarvalhomesquita@gmail.com

# gerar nova senha paar user:
docker compose exec laravel.test php artisan tinker --execute='$user = \App\Models\User::where("email", "admin@meunegocio.cloud")->firstOrFail(); $password = \Illuminate\Support\Str::random(24); $user->forceFill(["password" => \Illuminate\Support\Facades\Hash::make($password)])->save(); echo "Nova senha: {$password}\n";'
docker compose exec laravel.test php artisan tinker --execute='$user = \App\Models\User::where("email", "luanacarvalhomesquita@gmail.com")->firstOrFail(); $password = \Illuminate\Support\Str::random(24); $user->forceFill(["password" => \Illuminate\Support\Facades\Hash::make($password)])->save(); echo "Nova senha: {$password}\n";'


