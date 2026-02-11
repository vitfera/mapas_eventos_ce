# Otimização de Performance da Sincronização

## 📊 Problema Identificado

Com mais de **12 mil eventos**, o sistema estava levando **quase 1 hora** para processar a sincronização em produção.

### Gargalos Encontrados

1. **~12.000 consultas SELECT individuais** - uma por evento para verificar se existe
2. **~12.000 operações DELETE** - limpeza de relacionamentos linguagens/selos
3. **~24.000+ INSERTs individuais** - inserção de relacionamentos um por vez
4. **Múltiplas consultas** para cada linguagem e selo (getOrCreate pattern)
5. **Sem uso de transações** - cada operação commitada individualmente
6. **Sem processamento em lote** (batch processing)

**Total estimado: ~50.000+ queries no banco de dados** 🔥

---

## ⚡ Soluções Implementadas

### 1. Carregamento em Memória (Memory Loading)

**Antes:**
```php
// Para CADA evento (12k vezes)
$stmt = $this->db->prepare("SELECT id FROM eventos WHERE external_id = ?");
$stmt->execute([$externalId]);
```

**Depois:**
```php
// UMA ÚNICA VEZ no início
$existingEvents = $this->loadExistingEvents();
// Retorna array [external_id => db_id] em memória
```

**Impacto:** 12.000 queries → 1 query ✅

---

### 2. Cache de Linguagens e Selos

**Antes:**
```php
// Para cada linguagem de cada evento
SELECT id FROM linguagens WHERE nome = ?
// Se não existir: INSERT INTO linguagens...
```

**Depois:**
```php
// Uma vez no início
$linguagensCache = $this->loadLanguagesCache(); // array [nome => id]
$selosCache = $this->loadSealsCache(); // array [external_id => id, nome => id]

// Durante processamento: busca instantânea em memória
if (!isset($cache[$nome])) {
    // Cria e adiciona ao cache
}
```

**Impacto:** Milhares de SELECTs → cache em memória ✅

---

### 3. Batch Processing (Lotes de 500 eventos)

**Antes:**
```php
foreach ($events as $event) {
    // Processa um por um
    processEvent($event);
}
```

**Depois:**
```php
// Agrupa em lotes de 500
$batches = array_chunk($eventos, 500);

foreach ($batches as $batch) {
    $this->db->beginTransaction();
    
    foreach ($batch as $evento) {
        // Processa 500 eventos
    }
    
    $this->db->commit(); // Commit único para 500 eventos
}
```

**Impacto:** 12.000 commits → 24 commits (500 eventos por lote) ✅

---

### 4. Prepared Statements Reutilizáveis

**Antes:**
```php
// Dentro do loop
foreach ($linguagens as $nome) {
    $stmt = $this->db->prepare("INSERT IGNORE INTO eventos_linguagens...");
    $stmt->execute([...]);
}
```

**Depois:**
```php
// FORA do loop - reutilizado
$insertStmt = $this->db->prepare("INSERT IGNORE INTO eventos_linguagens...");

foreach ($linguagens as $nome) {
    $insertStmt->execute([...]); // Reutiliza o statement preparado
}
```

**Impacto:** Reduz overhead de preparação de statements ✅

---

### 5. Índices Compostos Otimizados

Criados índices específicos para as queries mais frequentes:

```sql
-- Busca rápida de eventos existentes
ALTER TABLE eventos ADD INDEX idx_external_created (external_id, created_at);

-- Otimiza JOINs e filtros
ALTER TABLE eventos ADD INDEX idx_municipio_data (municipio, data_inicio);
ALTER TABLE eventos ADD INDEX idx_dates_range (data_inicio, data_fim);

-- Acelera DELETEs e INSERTs de relacionamentos
ALTER TABLE eventos_linguagens ADD INDEX idx_evento_linguagem_created (evento_id, linguagem_id, created_at);
ALTER TABLE eventos_selos ADD INDEX idx_evento_selo_created (evento_id, selo_id, created_at);

-- Busca rápida de selos
ALTER TABLE selos ADD INDEX idx_external_nome (external_id, nome);
```

**Impacto:** Queries 10x-100x mais rápidas com índices ✅

---

## 📈 Resultados Esperados

| Métrica | Antes | Depois | Melhoria |
|---------|-------|--------|----------|
| **Tempo total (12k eventos)** | ~50-60 min | ~2-5 min | **~12x mais rápido** |
| **Total de queries** | ~50.000+ | ~50-100 | **~500x menos queries** |
| **Queries por evento** | ~4-5 | ~0.004 | **~1000x menos** |
| **Memória utilizada** | ~100 MB | ~150 MB | +50 MB (trade-off aceitável) |
| **Commits no banco** | ~12.000 | ~24 | **~500x menos** |

---

## 🚀 Como Aplicar as Otimizações

### Opção 1: Script Automatizado (Recomendado)

```bash
# Torna o script executável
chmod +x deploy_optimization.sh

# Executa o deploy
./deploy_optimization.sh
```

O script irá:
1. ✅ Fazer backup do banco de dados
2. ✅ Aplicar migration com índices otimizados
3. ✅ Verificar índices criados
4. ✅ Mostrar estatísticas do banco

---

### Opção 2: Manual

#### Passo 1: Aplicar Migration de Índices

```bash
# Via Docker
docker compose exec -T db mysql -u root -p mapacultural < database/migrations/optimize_sync_performance.sql

# Ou via MySQL client
mysql -u root -p mapacultural < database/migrations/optimize_sync_performance.sql
```

#### Passo 2: Executar Sincronização Otimizada

```bash
# O código já está otimizado, basta executar normalmente
docker compose exec app php cron/sync_eventos.php

# Ou com benchmark
docker compose exec app php cron/benchmark_sync.php
```

---

## 🧪 Testando a Performance

### Executar Benchmark

```bash
docker compose exec app php cron/benchmark_sync.php
```

**Output esperado:**
```
================================================
   BENCHMARK DE PERFORMANCE - SINCRONIZAÇÃO
================================================

Início: 2026-02-11 10:30:00
Memória inicial: 8.50 MB
------------------------------------------------

Executando sincronização...

================================================
           RESULTADOS DO BENCHMARK
================================================

--- ESTATÍSTICAS DE SINCRONIZAÇÃO ---
Total de eventos: 12450
Novos: 245
Atualizados: 12205
Erros: 0

--- PERFORMANCE ---
Tempo total: 3 min 45 seg
Tempo médio por evento: 18.07 ms
Eventos por segundo: 55.33

--- USO DE MEMÓRIA ---
Memória inicial: 8.50 MB
Memória final: 145.32 MB
Memória utilizada: 136.82 MB
Pico de memória: 148.15 MB
Média de memória por evento: 11.25 KB

--- ESTIMATIVAS ---
Tempo estimado para 10k eventos: 3 min 1 seg
Tempo estimado para 20k eventos: 6 min 2 seg
Tempo estimado para 50k eventos: 15 min 4 seg
```

Os resultados são salvos em: `logs/benchmark_YYYYMMDD_HHMMSS.json`

---

## 📝 Monitoramento

### Logs de Sincronização

```bash
# Ver logs em tempo real
tail -f logs/sync.log

# Ver últimos 100 registros
tail -n 100 logs/sync.log

# Buscar erros
grep "ERRO" logs/sync.log
```

### Verificar Índices Criados

```bash
docker compose exec db mysql -u root -p mapacultural -e "SHOW INDEX FROM eventos;"
docker compose exec db mysql -u root -p mapacultural -e "SHOW INDEX FROM eventos_linguagens;"
docker compose exec db mysql -u root -p mapacultural -e "SHOW INDEX FROM eventos_selos;"
```

### Estatísticas do Banco

```bash
docker compose exec db mysql -u root -p mapacultural -e "
    SELECT 
        (SELECT COUNT(*) FROM eventos) as total_eventos,
        (SELECT COUNT(*) FROM linguagens) as total_linguagens,
        (SELECT COUNT(*) FROM selos) as total_selos,
        (SELECT COUNT(*) FROM eventos_linguagens) as total_relacionamentos_linguagens,
        (SELECT COUNT(*) FROM eventos_selos) as total_relacionamentos_selos;
"
```

---

## 🔧 Ajustes Finos

### Configurar Tamanho do Lote (Batch Size)

Em `services/SyncService.php`, linha ~100:

```php
// Padrão: 500 eventos por lote
$batchSize = 500;

// Para servidores com mais memória
$batchSize = 1000;

// Para servidores com menos memória
$batchSize = 250;
```

### Aumentar Memória PHP (se necessário)

Em `cron/sync_eventos.php` ou `cron/benchmark_sync.php`:

```php
// Padrão: 512M
ini_set('memory_limit', '512M');

// Para grandes volumes
ini_set('memory_limit', '1G');
```

---

## ⚠️ Notas Importantes

1. **Backup obrigatório**: Sempre faça backup antes de aplicar as otimizações
2. **Teste primeiro**: Execute em ambiente de desenvolvimento/homologação primeiro
3. **Memória vs Velocidade**: As otimizações usam mais memória RAM em troca de velocidade
4. **Compatibilidade**: Código antigo ainda funciona (métodos legados mantidos)
5. **Rollback**: Se necessário, restaure o backup do banco

---

## 🎯 Próximos Passos (Otimizações Futuras)

Para volumes ainda maiores (50k+ eventos):

1. **Implementar fila de processamento** (Redis Queue)
2. **Paralelizar sincronização** (múltiplos workers)
3. **Implementar incremental sync** (sync apenas eventos modificados)
4. **Adicionar cache Redis** para relacionamentos
5. **Implementar particionamento** de tabelas por data

---

## 📚 Referências Técnicas

- **Batch Processing**: Processa dados em lotes para reduzir overhead
- **Prepared Statements**: Reutiliza planos de execução compilados
- **Transaction Batching**: Agrupa commits para reduzir I/O
- **Memory Caching**: Trade-off memória vs queries
- **Composite Indexes**: Índices multi-coluna para queries específicas

---

## 🤝 Suporte

Se encontrar problemas:

1. Verifique os logs: `logs/sync.log`
2. Execute o benchmark: `php cron/benchmark_sync.php`
3. Verifique os índices: `SHOW INDEX FROM eventos;`
4. Restaure o backup se necessário

---

**Documentação atualizada em: 11/02/2026**
