# �️ Dashboard de Eventos Culturais do Ceará

Dashboard interativo para visualização e análise de **eventos culturais** do Ceará, com dados sincronizados da [API do Mapa Cultural](https://mapacultural.secult.ce.gov.br).

![Badge](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)
![Badge](https://img.shields.io/badge/MariaDB-11.2-003545?logo=mariadb)
![Badge](https://img.shields.io/badge/Redis-7-DC382D?logo=redis)
![Badge](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker)

## ✨ Funcionalidades

- 📊 **Dashboard interativo** com estatísticas em tempo real de eventos culturais
- 🗄️ **Persistência de dados** com MariaDB (eventos sincronizados)
- ⚡ **Cache Redis** para performance otimizada
- 🔄 **Sincronização automática** com a API do Mapa Cultural
- 🎯 **Filtros avançados** por município e linguagem
- 📈 **Gráficos** de distribuição de eventos por linguagem
- 📅 **Filtros de período** (eventos futuros, passados, todos)
- 📂 **Exportação CSV** dos eventos culturais
- 🌐 **API RESTful** para consulta e integração
- 🐳 **100% Dockerizado** - pronto para produção no Coolify

## 🚀 Início Rápido

### Desenvolvimento Local

```bash
# 1. Clone o repositório
git clone https://github.com/vitfera/mapas_eventos_ce.git
cd mapas_eventos_ce

# 2. Configure as variáveis de ambiente
cp .env.example .env

# 3. Inicie os containers
docker compose up -d

# 4. Acesse o dashboard
open http://localhost:10500

# 5. Execute a primeira sincronização
docker compose exec app php cron/sync_eventos.php
```

### Produção (Coolify)

1. Crie os serviços no Coolify:
   - **Database**: MariaDB 11.2
   - **Cache**: Redis 7 (opcional mas recomendado)
   
2. Faça deploy da aplicação usando o `Dockerfile`

3. Configure as variáveis de ambiente:
   ```env
   DB_HOST=<mariadb-service>
   DB_NAME=mapa_eventos
   DB_USER=<user>
   DB_PASSWORD=<password>
   REDIS_HOST=<redis-service>
   API_URL=https://mapacultural.secult.ce.gov.br/api
   ```

4. Execute a primeira sincronização via console

## 📁 Estrutura do Projeto

```
mapas_eventos/
├── api/                      # Endpoints REST
│   ├── eventos.php          # Lista eventos culturais (filtros + paginação)
│   ├── stats.php            # Estatísticas dos eventos
│   └── sync.php             # Sincronização manual
├── assets/
│   ├── script.js            # Frontend JavaScript (carrega eventos)
│   └── styles.css           # Estilos CSS (Tailwind + custom)
├── config/
│   ├── database.php         # Singleton PDO (MariaDB)
│   └── redis.php            # Singleton Redis (cache)
├── cron/
│   ├── sync_eventos.php     # CLI: sincroniza eventos da API
│   └── crontab.example      # Agendamento automático
├── database/
│   └── init.sql             # Schema: tabelas + views + municípios
├── services/
│   ├── MapaCulturalAPI.php  # Cliente da API do Mapa Cultural
│   └── SyncService.php      # Lógica de sincronização de eventos
├── docker-compose.yml       # Ambiente local (4 containers)
├── Dockerfile               # Imagem PHP 8.2 + Apache
├── .coolify.yml             # Config deploy Coolify
└── index.html               # Dashboard principal
```

## 🔌 APIs Disponíveis

### GET /api/eventos.php

Lista eventos culturais com paginação e filtros.

**Parâmetros:**
- `page` (int): Página atual (padrão: 1)
- `limit` (int): Eventos por página (padrão: 50, máx: 100)
- `municipio` (string): Filtrar por município (ex: "Fortaleza")
- `linguagem` (string): Filtrar por linguagem (ex: "Música")
- `periodo` (string): Filtrar por período: "futuros", "passados", "todos" (padrão: "todos")

**Exemplo:**
```bash
curl "http://localhost:10500/api/eventos.php?page=1&limit=20&municipio=Fortaleza&periodo=futuros"
```

**Resposta:**
```json
{
  "success": true,
  "data": [...],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 6372,
    "pages": 319
  }
}
```

### GET /api/stats.php

Retorna estatísticas agregadas dos eventos culturais.

**Resposta:**
```json
{
  "success": true,
  "geral": {
    "total_eventos": 6372,
    "total_municipios": 206,
    "total_linguagens": 67,
    "total_acessibilidade": 5059,
    "eventos_futuros": 1234,
    "eventos_passados": 5138
  },
  "linguagens": [
    {"linguagem": "Música", "total": 1234},
    {"linguagem": "Teatro", "total": 890}
  ],
  "municipios": [
    {"municipio": "Fortaleza", "total": 1015},
    {"municipio": "Juazeiro do Norte", "total": 121}
  ],
  "last_sync": {
    "total_eventos": 6758,
    "status": "concluido",
    "finished_at": "2026-01-12 14:08:57"
  }
}
```

### POST /api/sync.php

Executa sincronização manual de eventos com a API do Mapa Cultural.

**Exemplo:**
```bash
curl -X POST http://localhost:10500/api/sync.php
```

**Resposta:**
```json
{
  "success": true,
  "message": "Sincronização concluída",
  "data": {
    "total": 6758,
    "novos": 5,
    "atualizados": 6753,
    "erros": 0
  }
}
```

## ⚙️ Sincronização Automática

Configure o cron para sincronização periódica de eventos:

```bash
# Editar crontab do container
docker compose exec app crontab -e

# Adicionar linha (sincronizar a cada 6 horas)
0 */6 * * * cd /var/www/html && php cron/sync_eventos.php >> /var/log/sync.log 2>&1
```

**Outras opções de agendamento:**
```bash
# Diariamente às 3h da manhã
0 3 * * * cd /var/www/html && php cron/sync_eventos.php >> /var/log/sync.log 2>&1

# Toda segunda-feira às 2h
0 2 * * 1 cd /var/www/html && php cron/sync_eventos.php >> /var/log/sync.log 2>&1
```

Mais exemplos em `cron/crontab.example`.

## 🗄️ Banco de Dados

### Principais Tabelas

- **espacos**: Dados completos dos espaços culturais (nome, endereço, município, CEP, acessibilidade, etc)
- **areas_atuacao**: Áreas de atuação cultural (Música, Teatro, Dança, Artes Visuais, etc)
- **espacos_areas**: Relacionamento N:N (um espaço pode ter várias áreas)
- **municipios**: Lista de municípios do Ceará
- **sync_logs**: Histórico de sincronizações (timestamp, total processado, erros)

### Views para Performance

- **vw_estatisticas**: Estatísticas gerais (total de espaços, municípios, áreas)
- **vw_distribuicao_areas**: Contagem de espaços por área de atuação
- **vw_distribuicao_municipios**: Contagem de espaços por município

## 🔧 Configuração

### Variáveis de Ambiente

```env
# Database (MariaDB)
DB_HOST=db                    # Nome do serviço no Docker
DB_NAME=mapa_espacos          # Nome do banco de dados
DB_USER=mapa_user             # Usuário do banco
DB_PASSWORD=mapa_pass         # Senha do usuário
DB_ROOT_PASSWORD=root_pass    # Senha do root MySQL

# Redis (Cache)
REDIS_HOST=redis              # Nome do serviço Redis
REDIS_PORT=6379               # Porta padrão do Redis

# API Externa (Mapa Cultural do Ceará)
API_URL=https://mapacultural.secult.ce.gov.br/api
API_TIMEOUT=30                # Timeout em segundos

# PHP
PHP_MEMORY_LIMIT=512M         # Necessário para sincronizar 6k+ espaços
PHP_MAX_EXECUTION_TIME=300    # 5 minutos para sync completo
```

## 🐳 Docker Services

- **app**: PHP 8.2 + Apache + extensões (PDO MySQL, Redis)
- **db**: MariaDB 11.2 (armazena ~6.400 espaços culturais)
- **redis**: Redis 7 Alpine (cache de queries e estatísticas)
- **phpmyadmin**: Interface web para gerenciar o banco (porta 8081)

## 📊 Monitoramento

### Logs de Sincronização

```bash
# Ver logs do último sync
docker compose logs app | tail -100

# Monitorar em tempo real
docker compose logs -f app
```

### Verificar Status

```sql
-- Acessar phpMyAdmin em http://localhost:8081

-- Verificar última sincronização de espaços
SELECT * FROM sync_logs ORDER BY started_at DESC LIMIT 1;

-- Estatísticas gerais
SELECT * FROM vw_estatisticas;

-- Total de espaços por município
SELECT * FROM vw_distribuicao_municipios ORDER BY total DESC LIMIT 10;

-- Total de espaços por área de atuação
SELECT * FROM vw_distribuicao_areas ORDER BY total DESC LIMIT 10;

-- Espaços com acessibilidade
SELECT COUNT(*) as total FROM espacos WHERE acessibilidade = 1;
```

## 🛠️ Desenvolvimento

### Adicionar Nova Funcionalidade

1. **Backend (API)**: Criar novo endpoint em `api/`
   - Exemplo: `api/espaco_detalhe.php` para detalhes de um espaço
   
2. **Frontend**: Atualizar `assets/script.js`
   - Adicionar função para consumir novo endpoint
   
3. **Banco de Dados**: Modificar `database/init.sql` se necessário
   - Adicionar novas tabelas ou campos
   
4. **Serviços**: Criar lógica de negócio em `services/`
   - Exemplo: filtros avançados, exportações customizadas

### Testar Localmente

```bash
# Reiniciar aplicação
docker compose restart app

# Rebuild completo
docker compose down
docker compose up --build -d

# Acessar container
docker compose exec app bash
```

## 📝 Licença

MIT License - Livre para uso e modificação.

## 🤝 Contribuindo

1. Fork o projeto: [github.com/vitfera/mapas_espacos_ce](https://github.com/vitfera/mapas_espacos_ce)
2. Crie uma branch (`git checkout -b feature/nova-funcionalidade`)
3. Commit suas mudanças (`git commit -m 'Adiciona nova funcionalidade'`)
4. Push para a branch (`git push origin feature/nova-funcionalidade`)
5. Abra um Pull Request

## 📧 Suporte

Para dúvidas ou problemas:
- 📖 Consulte a [documentação do Docker](README_DOCKER.md)
- 📊 Consulte o [guia do dashboard](GUIA_DASHBOARD.md)
- 🐛 Abra uma [issue no GitHub](https://github.com/vitfera/mapas_espacos_ce/issues)
- 💡 Sugestões são bem-vindas via Pull Request

---

**Desenvolvido para análise e visualização de espaços culturais do Ceará** 🎭🎨🎵

**Autor:** Victor Ferreira ([@vitfera](https://github.com/vitfera))
