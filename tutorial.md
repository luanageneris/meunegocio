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