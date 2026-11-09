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


