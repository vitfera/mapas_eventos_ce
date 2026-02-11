-- =========================================================
-- MIGRATION: Otimização de Performance para Sincronização
-- =========================================================
-- Melhora drasticamente a performance com +12 mil eventos
-- Adiciona índices compostos e otimiza queries de sincronização
-- Data: 2026-02-11
-- =========================================================

-- 1. Índices compostos para eventos (melhora JOINs)
ALTER TABLE eventos
ADD INDEX idx_external_created (external_id, created_at),
ADD INDEX idx_municipio_data (municipio, data_inicio),
ADD INDEX idx_dates_range (data_inicio, data_fim);

-- 2. Índices compostos para eventos_linguagens (melhora DELETEs e INSERTs em lote)
ALTER TABLE eventos_linguagens
ADD INDEX idx_evento_linguagem_created (evento_id, linguagem_id, created_at);

-- 3. Índices compostos para eventos_selos (melhora DELETEs e INSERTs em lote)
ALTER TABLE eventos_selos
ADD INDEX idx_evento_selo_created (evento_id, selo_id, created_at);

-- 4. Índice composto para selos (busca por external_id e nome)
ALTER TABLE selos
ADD INDEX idx_external_nome (external_id, nome);

-- 5. Otimiza tabela de sync_logs para consultas rápidas
ALTER TABLE sync_logs
ADD INDEX idx_status_started (status, started_at DESC);

-- 6. Analisa e otimiza todas as tabelas após adicionar índices
ANALYZE TABLE eventos;
ANALYZE TABLE eventos_linguagens;
ANALYZE TABLE eventos_selos;
ANALYZE TABLE linguagens;
ANALYZE TABLE selos;
ANALYZE TABLE sync_logs;

-- =========================================================
-- VERIFICAÇÃO DOS ÍNDICES CRIADOS
-- =========================================================
-- Execute estas queries para verificar se os índices foram criados:
-- 
-- SHOW INDEX FROM eventos;
-- SHOW INDEX FROM eventos_linguagens;
-- SHOW INDEX FROM eventos_selos;
-- SHOW INDEX FROM selos;
-- SHOW INDEX FROM sync_logs;
--
-- =========================================================
