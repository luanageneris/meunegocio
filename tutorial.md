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

## Animacao de entrada do Dashboard

Calendario, cartoes laterais e Curiosidades surgem com opacidade e deslocamento
suaves em 240 ms, com pequenos intervalos entre cartoes (ate 345 ms no total).
A animacao usa apenas CSS, nao espera pelas APIs nem bloqueia os controles,
e nao reinicia ao atualizar os dados ou trocar o mes. A preferencia de
movimento reduzido do dispositivo desativa esse efeito.

## Relatorios pelo Dashboard

No calendario do Dashboard, selecione o mes e o ano e use **Gerar relatorio PDF**
no cartao da empresa. O menu **Mensal** baixa o mesmo extrato mensal enviado
por e-mail no primeiro dia do mes; **Anual** baixa o mesmo resumo anual
enviado por e-mail, usando o ano selecionado e todos os seus meses.
Ambos usam apenas dados da empresa autenticada.
O relatorio anual nao inclui feriados ou pontos facultativos; o mensal
mantem a indicacao dessas datas conforme a localizacao autorizada.
O PDF usa o PIN configurado em **Minha Conta > Lembretes e relatorios automaticos**.
Configure esse PIN antes do primeiro download. Nao e necessario ativar os envios
por e-mail; gerar o PDF nao envia mensagens nem altera o historico dos envios.

Endpoints autenticados:
- `GET /api/dashboard/monthly-report?month=11&year=2026`: mes e ano obrigatorios.
- `GET /api/dashboard/yearly-report?year=2026`: apenas ano obrigatorio.

Mes deve estar entre 1 e 12 e ano entre 2000 e 2100; erros retornam JSON e o
sucesso retorna o arquivo `application/pdf`. Gerar relatorios nao envia
e-mails nem altera o historico dos envios agendados.

## Comissoes no resumo do Dashboard

O indicador **Comissoes do mes** fica no **Resumo** e soma as comissoes pagas
e pendentes pela data da comissao no mes selecionado. **Saidas acumuladas**
soma os lancamentos financeiros de saida do mesmo periodo, incluindo as
comissoes lancadas como saida (como as importadas da planilha).
O lucro e entradas menos saidas: nao se subtrai o indicador de comissoes
novamente. Uma comissao sem lancamento de saida nao entra nesse total de saidas.

## Periodo padrao de Oportunidades

Em **Minha Conta > Dados da empresa > Dias de funcionamento**, os dias
selecionados possuem uma marca de confirmacao. Alteracoes ficam pendentes
ate clicar em **Salvar alteracoes** dentro do cartao; o proprio campo confirma os dias salvos.
**Dados da empresa** e **Endereco** possuem botoes e confirmacoes independentes:
salvar um cartao nao salva nem descarta alteracoes pendentes no outro.
Mantenha a migration `2026_11_17_000001_add_opening_days_to_companies_table`
aplicada para persistir essa configuracao.

O periodo inicial vai do primeiro dia do mes ate hoje. A data de hoje vem
do servidor, no fuso configurado em `app.timezone`, e e a mesma usada pela
validacao da API. Assim, a virada de dia no navegador em outro fuso nao
seleciona uma data futura para a aplicacao. O intervalo maximo permanece
de dois anos e datas finais futuras sao bloqueadas.
Os cartoes Entradas, Dias da semana e Fluxo de caixa tambem possuem icones
de informacao com modais de calculo. Cada explicacao usa o periodo e a
categoria do resultado exibido e detalha a comparacao, as medias e a faixa
de possivel impacto. O grafico semanal considera os dias de funcionamento;
os totais financeiros continuam incluindo todos os movimentos do intervalo.

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
O cartao do melhor dia tambem mostra o melhor dia do mesmo mes no ano
imediatamente anterior ao ano selecionado, se houver entradas positivas.
Esse destaque usa o mes completo (nunca datas futuras), soma os lancamentos
de cada data e, em empate, escolhe a mais antiga. Pode aparecer mesmo sem
entradas no ano selecionado; nao ha substituicao por anos mais antigos.
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


