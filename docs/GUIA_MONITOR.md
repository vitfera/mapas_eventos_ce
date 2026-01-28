# Guia do Monitor de Sincronização

## Visão Geral

O Monitor de Sincronização é uma interface web que permite acompanhar em tempo real os logs do processo de sincronização de eventos culturais do Mapa Cultural do Ceará.

## Acesso

Para acessar o monitor, abra no navegador:

```
http://localhost:10500/monitor.html
```

Ou em produção:
```
https://seu-dominio.com/monitor.html
```

## Funcionalidades

### 1. Visualização de Logs em Tempo Real

- **Auto-atualização**: Por padrão, os logs são atualizados automaticamente a cada 5 segundos
- **Últimas 200 linhas**: Exibe as últimas 200 linhas do arquivo de log
- **Scroll automático**: Sempre rola para a última linha quando novos logs aparecem
- **Colorização**: Diferentes tipos de mensagens são destacadas com cores:
  - 🔴 **Vermelho**: Mensagens de erro
  - 🟢 **Verde**: Mensagens de sucesso e conclusão
  - 🔵 **Azul**: Mensagens informativas
  - 🟡 **Amarelo**: Cabeçalhos e separadores

### 2. Status da Sincronização

O badge no topo do monitor indica o status atual:

- **🔄 Sincronizando...** (Laranja): Sincronização em andamento
- **✓ Concluído** (Verde): Última sincronização concluída com sucesso
- **✗ Erro** (Vermelho): Ocorreu um erro na sincronização
- **Aguardando...** (Cinza): Nenhuma sincronização iniciada

### 3. Estatísticas em Tempo Real

Quando disponíveis nos logs, o monitor exibe:

- **Total de Eventos**: Quantidade total processada
- **Novos**: Eventos adicionados ao banco
- **Atualizados**: Eventos existentes atualizados
- **Erros**: Quantidade de erros encontrados

### 4. Controles

#### Atualizar Agora
Força uma atualização imediata dos logs, independente do intervalo de auto-atualização.

#### Limpar Visualização
Limpa apenas a tela do monitor (não apaga o arquivo de log).

#### Auto-atualizar
Checkbox que ativa/desativa a atualização automática a cada 5 segundos.

## Exemplos de Uso

### Monitorar Sincronização Manual

1. Abra o monitor no navegador
2. Execute a sincronização manual:
   ```bash
   docker compose exec php php /var/www/html/cron/sync_eventos.php
   ```
3. Acompanhe o progresso em tempo real no monitor

### Verificar Logs de Sincronização Agendada

1. Acesse o monitor após o horário da cron job
2. Verifique se a sincronização foi concluída com sucesso
3. Analise estatísticas de eventos novos/atualizados/erros

### Identificar Problemas

O monitor ajuda a identificar rapidamente:

- **Erros de API**: Timeouts, códigos HTTP inválidos
- **Erros de banco**: Problemas de conexão ou queries
- **Eventos problemáticos**: IDs específicos que causam erros
- **Performance**: Tempo de processamento por página

## Tipos de Mensagens nos Logs

### Cabeçalhos de Seção
```
========================================
INÍCIO DA SINCRONIZAÇÃO
========================================
```

### Mensagens Informativas
```
[2026-01-27 14:30:00] Iniciando busca de eventos da API...
[2026-01-27 14:30:02] Página 1: 100 eventos | Total: 100
[2026-01-27 14:30:05] Total de eventos encontrados: 3245
```

### Resumo de Sincronização
```
====== RESUMO DA SINCRONIZAÇÃO ======
Total de eventos: 3245
Novos: 127
Atualizados: 3118
Erros: 0
```

### Mensagens de Erro
```
[2026-01-27 14:35:12] ERRO ao processar evento ID 1234: Invalid data format
[2026-01-27 14:35:15] ERRO NA SINCRONIZAÇÃO: Connection timeout
```

### Conclusão
```
[2026-01-27 14:40:00] Sincronização concluída com sucesso!
```

## Estrutura Técnica

### Backend (API)

O monitor consome o endpoint `/api/logs.php` que:

- Lê o arquivo `logs/sync.log`
- Retorna as últimas N linhas (padrão: 200)
- Usa `tail` para performance em arquivos grandes
- Retorna JSON com os logs e metadados

**Endpoint:**
```
GET /api/logs.php?lines=200
```

**Resposta:**
```json
{
  "success": true,
  "logs": "conteúdo dos logs...",
  "lineCount": 200,
  "requestedLines": 200,
  "info": {
    "exists": true,
    "size": 45678,
    "modified": 1706371200,
    "readable": true
  },
  "timestamp": "2026-01-27 14:30:00"
}
```

### Frontend

Interface construída com:

- **HTML5**: Estrutura semântica
- **CSS3**: Estilização com tema dark (IDE-like)
- **Vanilla JavaScript**: Sem dependências externas
- **Fetch API**: Requisições assíncronas

### Atualização Automática

```javascript
// Intervalo de 5 segundos
setInterval(loadLogs, 5000);
```

## Alternativas de Monitoramento

### 1. Terminal (Recomendado para DevOps)

```bash
# Acompanhar logs em tempo real
tail -f logs/sync.log

# Últimas 50 linhas
tail -n 50 logs/sync.log

# Filtrar apenas erros
grep "ERRO" logs/sync.log

# Contar eventos novos
grep "Novos:" logs/sync.log | tail -1
```

### 2. Docker Logs (Container)

```bash
# Logs do container PHP
docker compose logs -f php

# Logs de sincronização específica
docker compose exec php tail -f /var/www/html/logs/sync.log
```

### 3. Arquivos de Log

Local do arquivo:
```
/Applications/MAMP/htdocs/mapas_eventos/logs/sync.log
```

No container:
```
/var/www/html/logs/sync.log
```

## Solução de Problemas

### Monitor não carrega logs

**Verificar permissões:**
```bash
ls -la logs/sync.log
chmod 644 logs/sync.log
```

**Verificar se o arquivo existe:**
```bash
test -f logs/sync.log && echo "Existe" || echo "Não existe"
```

**Verificar API:**
```bash
curl http://localhost:10500/api/logs.php
```

### Auto-atualização não funciona

1. Verifique se o checkbox está marcado
2. Abra o Console do navegador (F12) para ver erros
3. Verifique se `/api/logs.php` está acessível
4. Verifique se há erros CORS

### Logs vazios

Se o monitor mostra "Nenhum log disponível":

1. Execute uma sincronização manual
2. Verifique se o diretório `logs/` existe
3. Verifique permissões de escrita
4. Verifique se o SyncService está escrevendo logs

### Erro 500 na API

```bash
# Verificar logs do PHP
docker compose logs php

# Verificar permissões
ls -la logs/

# Verificar sintaxe do PHP
docker compose exec php php -l /var/www/html/api/logs.php
```

## Performance

### Otimizações Implementadas

- **Tail em vez de leitura completa**: Lê apenas últimas linhas
- **Cache desabilitado**: Sempre retorna dados frescos
- **Intervalo moderado**: 5s evita sobrecarga
- **Scroll otimizado**: Usa `scrollHeight` nativo
- **Detecção de mudanças**: Só atualiza DOM se logs mudaram

### Limites

- **Linhas exibidas**: Máximo 200 (configurável até 1000)
- **Tamanho do arquivo**: Funciona bem até ~10MB
- **Intervalos**: Mínimo recomendado de 3s

## Integração com Sistema

### Fluxo Completo

1. **Cron Job** executa `sync_eventos.php`
2. **SyncService** escreve logs em `logs/sync.log`
3. **API** (`logs.php`) lê o arquivo
4. **Monitor** exibe em tempo real
5. **Usuário** acompanha o progresso

### Diagrama de Comunicação

```
┌─────────────┐      ┌──────────────┐      ┌──────────┐
│ Cron Job    │─────▶│ SyncService  │─────▶│ sync.log │
└─────────────┘      └──────────────┘      └─────┬────┘
                                                  │
                     ┌─────────────────────────────┘
                     │
                     ▼
                ┌─────────┐      ┌──────────────┐
                │logs.php │◀─────│ monitor.html │
                └─────────┘      └──────────────┘
```

## Boas Práticas

### Para Administradores

- ✅ Monitore sincronizações agendadas
- ✅ Arquive logs antigos mensalmente
- ✅ Configure alertas para erros críticos
- ✅ Valide estatísticas (novos/atualizados)

### Para Desenvolvedores

- ✅ Use o monitor durante desenvolvimento
- ✅ Teste sincronizações locais antes de deploy
- ✅ Verifique performance de novas features
- ✅ Documente novos tipos de logs

## Rotação de Logs

Para evitar crescimento excessivo do arquivo:

```bash
# Criar arquivo de rotação
cat > /etc/logrotate.d/mapas-eventos << 'EOF'
/caminho/para/logs/sync.log {
    daily
    rotate 7
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
}
EOF
```

## Segurança

### Considerações

- ⚠️ **Logs podem conter dados sensíveis**: IDs, nomes, endereços
- ⚠️ **Acesso público**: Considere autenticação em produção
- ⚠️ **Tamanho do arquivo**: Implemente rotação

### Proteção Recomendada

Em `.htaccess` para Apache:

```apache
<Files "monitor.html">
    AuthType Basic
    AuthName "Monitor de Sincronização"
    AuthUserFile /caminho/.htpasswd
    Require valid-user
</Files>
```

Em nginx:

```nginx
location /monitor.html {
    auth_basic "Monitor de Sincronização";
    auth_basic_user_file /etc/nginx/.htpasswd;
}
```

## Recursos Futuros

Possíveis melhorias:

- [ ] Filtros por tipo de mensagem (erro/sucesso/info)
- [ ] Busca em tempo real nos logs
- [ ] Exportar logs filtrados
- [ ] Gráfico de performance ao longo do tempo
- [ ] Notificações push quando houver erros
- [ ] Comparação entre sincronizações
- [ ] Endpoint para limpar logs via API
- [ ] Histórico de sincronizações
- [ ] Métricas de performance (tempo médio, etc)

## Comandos Úteis

### Testar API de Logs

```bash
# Teste básico
curl http://localhost:10500/api/logs.php

# Com parâmetro de linhas
curl http://localhost:10500/api/logs.php?lines=50

# Formatado
curl -s http://localhost:10500/api/logs.php | jq .
```

### Gerenciar Logs

```bash
# Ver tamanho do arquivo
du -h logs/sync.log

# Limpar logs
> logs/sync.log

# Backup de logs
cp logs/sync.log logs/sync_backup_$(date +%Y%m%d).log

# Compactar logs antigos
gzip logs/sync_backup_*.log
```

### Docker

```bash
# Acessar logs dentro do container
docker compose exec php cat /var/www/html/logs/sync.log

# Copiar logs do container
docker compose cp php:/var/www/html/logs/sync.log ./

# Limpar logs no container
docker compose exec php sh -c "> /var/www/html/logs/sync.log"
```

## Suporte

Para problemas ou sugestões:

1. Verifique este guia primeiro
2. Consulte logs de erro do navegador (F12)
3. Verifique logs do servidor/container
4. Verifique permissões de arquivos
5. Abra uma issue no repositório

---

**Última atualização:** Janeiro 2026  
**Versão do Monitor:** 1.0  
**Projeto:** Mapa Cultural do Ceará - Eventos

