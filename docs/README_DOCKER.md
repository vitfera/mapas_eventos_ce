# 🐳 Guia Docker - Dashboard de Eventos Culturais do Ceará

## 📋 Pré-requisitos

- Docker Desktop instalado (v20.10 ou superior)
- Docker Compose V2 (integrado ao Docker CLI)

## 🚀 Desenvolvimento Local

### 1. Clonar e configurar

```bash
cd /Applications/MAMP/htdocs/mapas_eventos
cp .env.example .env
```

### 2. Iniciar containers

```bash
# Construir e iniciar todos os serviços
docker compose up -d

# Ver logs
docker compose logs -f

# Parar containers
docker compose down
```

### 3. Acessar aplicação

- **Aplicação**: http://localhost:10500
- **phpMyAdmin**: http://localhost:10501
- **Banco de dados**: localhost:3307

### 4. Credenciais padrão

**Banco de dados:**
- Host: `localhost` (ou `db` dentro do Docker)
- Porta: `3306` (externa) / `3306` (interna)
- Database: `mapas_eventos`
- Usuário: `mapas_user`
- Senha: `mapas_password`
- Root: `root_password`

**phpMyAdmin:**
- URL: http://localhost:10501
- Servidor: `db`
- Usuário: `mapas_user`
- Senha: `mapas_password`

## 🌐 Deploy no Coolify

### 1. Criar serviço MariaDB no Coolify

1. **Novo Recurso** → **Database** → **MariaDB**
2. Configurar:
   - Nome: `mapas-eventos-db`
   - Versão: `11.2` ou latest
   - Database: `mapas_eventos`
   - Usuário: `mapas_user`
   - Senha: Gerar senha segura
3. **Deploy** e anotar o hostname interno (ex: `mapas-eventos-db`)

### 2. Criar aplicação PHP

1. **Novo Recurso** → **Application**
2. Configurar:
   - Tipo: **Dockerfile**
   - Repositório: seu repositório Git
   - Branch: `main`
   - Dockerfile: `Dockerfile` (padrão)
   - Port: `80`

### 3. Configurar variáveis de ambiente da aplicação

No painel da aplicação, adicionar:

```env
DB_HOST=mapas-eventos-db
DB_PORT=3306
DB_DATABASE=mapas_eventos
DB_USERNAME=mapas_user
DB_PASSWORD=<senha-criada-no-passo-1>
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=redis_password
API_URL=https://mapacultural.secult.ce.gov.br/api
APP_ENV=production
APP_DEBUG=false
```

### 4. Inicializar banco de dados

Após primeiro deploy, executar SQL inicial:

1. Acessar o serviço MariaDB no Coolify
2. **Execute Command** ou conectar via cliente MySQL
3. Executar conteúdo de `database/init.sql`

Ou via terminal:

```bash
# Copiar arquivo SQL para o container do banco
docker cp database/init.sql <mariadb-container-id>:/tmp/init.sql

# Executar SQL
docker exec <mariadb-container-id> mysql -u mapas_user -p mapas_eventos < /tmp/init.sql
```

### 5. Deploy e verificar

1. **Deploy** da aplicação
2. Aguardar build e inicialização
3. Acessar URL fornecida pelo Coolify
4. Verificar logs em caso de erro

### 6. Configurar domínio (opcional)

1. No painel da aplicação → **Domains**
2. Adicionar domínio customizado
3. Certificado SSL será gerado automaticamente

## 🛠️ Comandos úteis

### Docker Compose

```bash
# Rebuild containers
docker compose up -d --build

# Ver logs de um serviço específico
docker compose logs -f app
docker compose logs -f db

# Executar comando no container
docker compose exec app bash
docker compose exec db mysql -u mapas_user -p

# Parar e remover tudo (incluindo volumes)
docker compose down -v

# Ver status dos containers
docker compose ps
```

### Banco de dados

```bash
# Backup do banco
docker compose exec db mysqldump -u mapas_user -pmapas_password mapas_eventos > backup.sql

# Restaurar backup
docker compose exec -T db mysql -u mapas_user -pmapas_password mapas_eventos < backup.sql

# Acessar MySQL CLI
docker compose exec db mysql -u mapas_user -pmapas_password mapas_eventos
```

### Desenvolvimento

```bash
# Reiniciar apenas a aplicação
docker compose restart app

# Ver uso de recursos
docker stats

# Limpar volumes órfãos
docker volume prune
```

## 📂 Estrutura de arquivos

```
mapas_eventos/
├── Dockerfile                 # Imagem da aplicação (usado no Coolify)
├── docker-compose.yml         # Desenvolvimento local (não usado no Coolify)
├── .dockerignore             # Arquivos ignorados no build
├── .env.example              # Exemplo de variáveis
├── database/
│   └── init.sql              # Schema do banco
├── config/
│   ├── database.php          # Conexão PDO
│   └── redis.php             # Configuração Redis
├── services/
│   ├── MapaCulturalAPI.php   # Cliente API
│   └── SyncService.php       # Serviço de sincronização
├── api/
│   ├── eventos.php           # Endpoint de eventos
│   ├── stats.php             # Endpoint de estatísticas
│   └── sync.php              # Endpoint de sincronização
├── assets/
│   ├── script.js             # JavaScript
│   └── styles.css            # Estilos
├── index.html                # Frontend
└── cron/
    └── sync_eventos.php      # Script de sincronização
```

## 🔄 Diferença entre ambientes

### Desenvolvimento Local (Docker Compose)
- Usa `docker-compose.yml`
- Inclui MariaDB, App e phpMyAdmin
- Tudo em um único arquivo
- Comando: `docker compose up -d`

### Produção (Coolify)
- Usa apenas `Dockerfile`
- Banco de dados criado como serviço separado no Coolify
- Aplicação usa variáveis de ambiente para conectar
- Deploy via Git push ou interface Coolify

## 🔧 Troubleshooting

### Porta já em uso

```bash
# Alterar portas no docker-compose.yml
ports:
  - "8090:80"  # ao invés de 8080
```

### Container não inicia

```bash
# Ver logs detalhados
docker compose logs --tail=100 app
docker compose logs --tail=100 db
```

### Banco não conecta

```bash
# Verificar se o banco está pronto
docker compose exec db mysqladmin ping -h localhost

# Verificar rede
docker network ls
docker network inspect mapas_espacos_mapas_network
```

### Reset completo

```bash
# Parar tudo e remover volumes
docker compose down -v

# Remover imagens
docker compose down --rmi all -v

# Reconstruir do zero
docker compose up -d --build
```

## 🔒 Segurança em Produção

1. **Alterar senhas padrão** no `.env`
2. **Usar HTTPS** com certificado SSL
3. **Configurar firewall** para portas específicas
4. **Backups automáticos** do banco de dados
5. **Monitorar logs** regularmente
6. **Atualizar imagens** periodicamente

## 📊 Monitoramento

```bash
# CPU e memória em tempo real
docker stats

# Espaço em disco
docker system df

# Logs com timestamp
docker compose logs -f --timestamps
```

## 🔄 Atualização

```bash
# Pull novas imagens
docker compose pull

# Rebuild e restart
docker compose up -d --build
```
