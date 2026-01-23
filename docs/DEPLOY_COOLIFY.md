# 🚀 Deploy no Coolify - Dashboard de Eventos Culturais

## 📋 Visão Geral

Este guia mostra como fazer deploy do Dashboard de Eventos Culturais do Ceará no Coolify usando apenas o Dockerfile do GitHub. A aplicação será dividida em três serviços:

1. **Banco de dados MariaDB** (serviço gerenciado do Coolify)
2. **Redis** (cache para melhor performance)
3. **Aplicação PHP** (construída a partir do Dockerfile do GitHub)

## 🎯 Pré-requisitos

- Acesso a uma instância Coolify
- Repositório GitHub: `https://github.com/seu-usuario/mapas_eventos`
- 10 minutos para configuração

## 📝 Passo 1: Criar o Banco de Dados MariaDB

### 1.1. Adicionar novo Database

1. No painel do Coolify, clique em **+ Add**
2. Selecione **Database**
3. Escolha **MariaDB**

### 1.2. Configurar o banco

Preencha os campos:

| Campo | Valor |
|-------|-------|
| **Name** | `mapas-eventos-db` |
| **Version** | `11.2` (ou latest) |
| **Database Name** | `mapas_eventos` |
| **Database User** | `mapas_user` |
| **Database Password** | _Gere uma senha forte_ |
| **Root Password** | _Gere uma senha forte_ |

> 💡 **Dica**: Use o gerador de senhas do Coolify para criar senhas seguras.

### 1.3. Anotar informações

Após criar o banco, **copie e guarde**:

- ✅ Nome do serviço interno (hostname): `mapas-eventos-db`
- ✅ Senha do usuário `mapas_user`
- ✅ Senha do root

### 1.4. Deploy do banco

1. Clique em **Deploy**
2. Aguarde o banco ficar online (status: **Running**)
3. Isso pode levar 1-2 minutos

## 📝 Passo 2: Criar a Aplicação PHP

### 2.1. Adicionar novo Application

1. No painel do Coolify, clique em **+ Add**
2. Selecione **Application**
3. Escolha **Public Repository**

### 2.2. Configurar repositório

Preencha os campos:

| Campo | Valor |
|-------|-------|
| **Repository URL** | `https://github.com/seu-usuario/mapas_eventos` |
| **Branch** | `main` |
| **Name** | `mapas-eventos-app` |

### 2.3. Configurar build

Na seção **Build**:

| Campo | Valor |
|-------|-------|
| **Build Pack** | `Dockerfile` |
| **Dockerfile Location** | `Dockerfile` |
| **Docker Build Context** | `/` |0

### 2.4. Configurar portas

Na seção **Ports**:

| Campo | Valor |
|-------|-------|
| **Port** | `80` |
| **Protocol** | `http` |

### 2.5. Configurar variáveis de ambiente

Na seção **Environment Variables**, adicione:

```env
DB_HOST=mapas-eventos-db
DB_PORT=3306
DB_DATABASE=mapas_eventos
DB_USERNAME=mapas_user
DB_PASSWORD=<senha-do-passo-1.2>
REDIS_HOST=mapas-eventos-redis
REDIS_PORT=6379
REDIS_PASSWORD=redis_password
API_URL=https://mapacultural.secult.ce.gov.br/api
API_TIMEOUT=30
APP_ENV=production
APP_DEBUG=false
```

> ⚠️ **Importante**: Substitua `<senha-do-passo-1.2>` pela senha real que você criou no Passo 1.2.

## 📝 Passo 3: Adicionar Redis Cache

### 3.1. Adicionar Redis ao projeto

1. No mesmo projeto da aplicação, clique em **+ Add**
2. Selecione **Database**
3. Escolha **Redis**

### 3.2. Configurar Redis

| Campo | Valor |
|-------|-------|
| **Name** | `mapas-eventos-redis` |
| **Version** | `7-alpine` |

### 3.3. Deploy do Redis

1. Clique em **Deploy**
2. Aguarde ficar online

### 3.4. Atualizar variável de ambiente da aplicação

Volte para a aplicação e verifique se as variáveis do Redis estão corretas:

```env
REDIS_HOST=mapas-espacos-redis
REDIS_PORT=6379
```

## 📝 Passo 4: Inicializar o Banco de Dados

### 4.1. Fazer o primeiro deploy da aplicação

1. Na aplicação, clique em **Deploy**
2. Aguarde o build e deploy (3-5 minutos)
3. O build falhará se o banco não tiver schema, mas isso é esperado

### 4.2. Executar SQL inicial

**Opção A: Via terminal do Coolify**

1. No serviço MariaDB, clique em **Terminal**
2. Execute:

```bash
mysql -u mapas_user -p mapas_espacos
```

3. Cole o conteúdo de `database/init.sql` (disponível no repositório)
4. Digite `exit` para sair

**Opção B: Via arquivo**

1. Copie o conteúdo de `database/init.sql` do GitHub
2. No serviço MariaDB, clique em **Execute Command**
3. Cole e execute:

```bash
mysql -u mapas_user -p'<sua-senha>' mapas_espacos << 'EOF'
-- Cole aqui o conteúdo completo do database/init.sql
EOF
```

### 4.3. Verificar schema

Execute no terminal do MariaDB:

```sql
USE mapas_eventos;
SHOW TABLES;
```

Você deve ver:

```
+---------------------------+
| Tables_in_mapas_eventos   |
+---------------------------+
| eventos                   |
| eventos_linguagens        |
| linguagens                |
| municipios                |
| sync_logs                 |
| vw_distribuicao_linguagens|
| vw_distribuicao_municipios|
| vw_estatisticas           |
+---------------------------+
```

## 📝 Passo 5: Fazer Deploy Final

### 5.1. Redeploy da aplicação

1. Na aplicação, clique em **Redeploy**
2. Aguarde o build e deploy
3. Monitore os logs

### 5.2. Verificar logs

Durante o deploy, verifique:

```bash
# Deve mostrar conexão com banco
✓ Database connected
✓ Redis connected
✓ Application started
```

### 5.3. Acessar a aplicação

1. No painel da aplicação, copie a **URL pública**
2. Acesse no navegador
3. Você verá o dashboard (ainda sem dados)

## 📝 Passo 6: Sincronizar Dados Iniciais

### 6.1. Executar sincronização via terminal

1. Na aplicação, clique em **Terminal**
2. Execute:

```bash
php cron/sync_eventos.php
```

3. Aguarde a sincronização (deve levar 1-3 minutos)
4. Você verá o progresso:

```
Sincronização iniciada...
Processando eventos com selo 32...
...
✓ Sincronização concluída: 475 eventos processados
```

### 6.2. Verificar no dashboard

1. Recarregue a URL pública
2. Você deve ver:
   - ✅ Total de Eventos: 475
   - ✅ Municípios: ~50
   - ✅ Linguagens: ~25
   - ✅ Com Acessibilidade: variável

## 📝 Passo 7: Configurar Domínio (Opcional)

### 7.1. Adicionar domínio customizado

1. Na aplicação, vá para **Domains**
2. Clique em **+ Add Domain**
3. Digite seu domínio: `eventos.seudominio.com`
4. Clique em **Add**

### 7.2. Configurar DNS

No seu provedor DNS (Cloudflare, etc):

1. Adicione um registro **A** ou **CNAME**
2. Aponte para o IP/hostname do Coolify
3. Aguarde propagação (5-30 minutos)

### 7.3. Certificado SSL

O Coolify gera certificado Let's Encrypt automaticamente:

1. Aguarde alguns minutos após adicionar o domínio
2. O status mudará para **SSL: Active**
3. Acesse via HTTPS: `https://eventos.seudominio.com`

## 📝 Passo 8: Configurar Sincronização Automática

### 8.1. Criar cron job no Coolify

1. Na aplicação, vá para **Scheduled Tasks**
2. Clique em **+ Add Task**

### 8.2. Configurar task

| Campo | Valor |
|-------|-------|
| **Name** | `Sincronizar Eventos Culturais` |
| **Command** | `php cron/sync_eventos.php` |
| **Schedule** | `0 */6 * * *` (a cada 6 horas) |
| **Enabled** | ✅ |

Outras opções de schedule:

- **A cada hora**: `0 * * * *`
- **Diariamente às 3h**: `0 3 * * *`
- **A cada 12 horas**: `0 */12 * * *`

### 8.3. Testar execução

1. Clique em **Run Now**
2. Veja os logs em **Scheduled Tasks History**
3. Verifique se executou com sucesso

## ✅ Checklist de Verificação

Após completar todos os passos:

- [ ] Banco MariaDB online e com schema
- [ ] Redis online e funcionando
- [ ] Aplicação deployada com sucesso
- [ ] Dashboard acessível via URL pública
- [ ] Sincronização inicial concluída (475+ eventos)
- [ ] Estatísticas exibindo corretamente
- [ ] Gráfico de linguagens funcionando
- [ ] Tabela de eventos populada com ID, Nome, Data, Hora, Local, Tags
- [ ] Filtros funcionando
- [ ] Exportação CSV funcionando
- [ ] Sincronização automática configurada
- [ ] Domínio configurado (se aplicável)
- [ ] SSL ativo (se aplicável)

## 🔧 Troubleshooting

### Problema: Build falha

**Sintomas**: Build termina com erro

**Soluções**:
1. Verifique os logs de build
2. Confirme que o Dockerfile existe no repositório
3. Verifique se todas as extensões PHP estão instaladas

### Problema: Aplicação não conecta ao banco

**Sintomas**: Erro 500 ou "Database connection failed"

**Soluções**:
1. Verifique as variáveis de ambiente (`DB_HOST`, `DB_PASSWORD`)
2. Confirme que o banco está online
3. Teste conexão no terminal:
   ```bash
   php -r "new PDO('mysql:host=mapas-eventos-db;dbname=mapas_eventos', 'mapas_user', 'senha');"
   ```

### Problema: Dashboard vazio

**Sintomas**: Dashboard carrega mas sem dados

**Soluções**:
1. Execute sincronização manual: `php cron/sync_eventos.php`
2. Verifique se há dados no banco:
   ```sql
   SELECT COUNT(*) FROM eventos;
   ```
3. Limpe o cache Redis:
   ```bash
   php -r "
   \$redis = new Redis();
   \$redis->connect('mapas-eventos-redis', 6379);
   \$redis->flushAll();
   "
   ```

### Problema: Sincronização lenta

**Sintomas**: `sync_eventos.php` demora muito

**Soluções**:
1. Sincronização de 475 eventos deve levar 1-3 minutos
2. Se demorar mais, verifique logs para erros
3. Verifique conectividade com a API do Mapa Cultural
4. Considere aumentar API_TIMEOUT nas variáveis de ambiente

### Problema: SSL não funciona

**Sintomas**: Certificado não é gerado

**Soluções**:
1. Verifique se DNS está propagado: `nslookup espacos.seudominio.com`
2. Confirme que porta 80 e 443 estão abertas
3. Aguarde até 30 minutos
4. Veja logs do Coolify

## 📊 Monitoramento

### Logs da aplicação

```bash
# Ver logs em tempo real
docker logs -f <container-id>

# Últimas 100 linhas
docker logs --tail=100 <container-id>
```

### Uso de recursos

No painel do Coolify:
- **CPU**: Deve ficar entre 5-20% em uso normal
- **Memória**: ~200-500MB para aplicação
- **Disco**: ~50MB para aplicação, crescente para banco

### Métricas importantes

- **Eventos sincronizados**: Deve ser ~475 (eventos com selo 32)
- **Última sincronização**: Verificar regularidade
- **Taxa de erro**: Deve ser próxima de 0%
- **Campos preenchidos**: Verificar se data, hora, local e tags estão sendo extraídos

## 🔐 Segurança

### Checklist de segurança

- [ ] Senhas fortes para banco de dados
- [ ] `APP_DEBUG=false` em produção
- [ ] SSL/HTTPS ativo
- [ ] Backups automáticos configurados
- [ ] Firewall configurado no servidor
- [ ] Atualizações regulares das imagens Docker

### Backup do banco

Configure backup automático no Coolify:

1. No serviço MariaDB, vá para **Backups**
2. Configure:
   - **Frequency**: Daily
   - **Time**: 03:00
   - **Retention**: 7 days
3. Clique em **Save**

## 📚 Recursos Adicionais

- [Repositório GitHub](https://github.com/seu-usuario/mapas_eventos)
- [Documentação Docker](README_DOCKER.md)
- [Guia do Dashboard](GUIA_DASHBOARD.md)
- [API do Mapa Cultural](https://mapacultural.secult.ce.gov.br/api/)

## 🆘 Suporte

- **Issues**: https://github.com/seu-usuario/mapas_eventos/issues
- **API Mapa Cultural**: https://mapacultural.secult.ce.gov.br

---

**Última atualização**: Janeiro 2026
