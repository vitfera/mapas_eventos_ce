# Sincronização de Selos

## Visão Geral

O sistema agora sincroniza automaticamente os selos do Mapa Cultural junto com os eventos. Os selos são certificações ou categorizações especiais que podem ser aplicadas aos eventos culturais.

## Estrutura de Dados

### Tabela: `selos`
- `id` - ID interno do banco
- `external_id` - ID do selo na API do Mapa Cultural
- `nome` - Nome do selo
- `descricao` - Descrição breve do selo
- `created_at` / `updated_at` - Timestamps

### Tabela: `eventos_selos`
Relacionamento many-to-many entre eventos e selos.

## Como Funciona

### 1. Sincronização Inicial de Selos

Para popular a tabela de selos pela primeira vez:

```bash
docker compose exec app php /var/www/html/cron/sync_selos.php
```

Este script:
- Busca todos os selos disponíveis na API
- Cria ou atualiza os registros na tabela `selos`
- Usa `external_id` quando disponível, senão usa `nome` como identificador único

### 2. Sincronização de Eventos

Durante a sincronização de eventos, os selos são automaticamente vinculados:

```bash
docker compose exec app php /var/www/html/cron/sync_eventos.php
```

O processo:
1. Para cada evento, verifica se possui selos (`seals` array)
2. Remove os selos antigos do evento
3. Para cada selo:
   - Busca o selo por `external_id` (se disponível)
   - Senão, busca por `nome`
   - Cria o selo se não existir
   - Vincula o evento ao selo na tabela `eventos_selos`

## Problema Identificado e Solução

### Problema
A API do Mapa Cultural retorna selos nos eventos, mas o campo `id` do selo pode vir vazio quando consultado via listagem de eventos. O `id` só é retornado corretamente ao consultar a lista de selos diretamente.

### Solução Implementada
1. **Script separado para selos**: Criado `sync_selos.php` que busca selos diretamente da API
2. **Fallback por nome**: Se o `external_id` não estiver disponível, usa o `nome` como identificador
3. **Validação melhorada**: Verifica se o selo tem pelo menos o campo `name` antes de processar
4. **Update inteligente**: Atualiza o `external_id` quando ele estiver disponível posteriormente

## API Endpoint

### GET /api/selos.php

Retorna lista de todos os selos cadastrados:

```json
{
  "success": true,
  "data": [
    {
      "id": 32,
      "name": "Carnaval Fortaleza 2026",
      "shortDescription": "..."
    }
  ],
  "total": 27,
  "timestamp": "2026-02-03 14:34:19"
}
```

## Estatísticas Atuais

- **Total de selos**: 27
- **Eventos com selos**: 554 relacionamentos
- **Cache**: 24 horas

## Manutenção

### Limpar Cache
```bash
docker compose exec app php -r "
require_once '/var/www/html/config/redis.php';
\$cache = RedisCache::getInstance();
\$cache->flush();
"
```

### Verificar Selos no Banco
```sql
-- Total de selos
SELECT COUNT(*) FROM selos;

-- Eventos por selo
SELECT s.nome, COUNT(es.evento_id) as total_eventos
FROM selos s
LEFT JOIN eventos_selos es ON s.id = es.selo_id
GROUP BY s.id
ORDER BY total_eventos DESC;
```

## Agendamento (Cron)

Recomenda-se executar os scripts na seguinte ordem:

```cron
# Sincroniza selos (1x por dia)
0 2 * * * docker compose exec app php /var/www/html/cron/sync_selos.php

# Sincroniza eventos (a cada 6 horas)
0 */6 * * * docker compose exec app php /var/www/html/cron/sync_eventos.php
```
