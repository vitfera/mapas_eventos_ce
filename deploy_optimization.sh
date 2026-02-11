#!/bin/bash
# =========================================================
# Script de Deploy das Otimizações de Performance
# =========================================================
# Este script aplica as otimizações de sincronização
# em produção de forma segura
# =========================================================

echo "========================================="
echo "Deploy de Otimizações de Performance"
echo "========================================="
echo ""

# Cores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verifica se está no diretório correto
if [ ! -f "docker-compose.yml" ]; then
    echo -e "${RED}ERRO: Execute este script na raiz do projeto!${NC}"
    exit 1
fi

echo -e "${YELLOW}1. Fazendo backup do banco de dados...${NC}"
docker compose exec -T db mysqldump -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" mapacultural > backup_pre_optimization_$(date +%Y%m%d_%H%M%S).sql
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Backup criado com sucesso!${NC}"
else
    echo -e "${RED}✗ Falha ao criar backup! Abortando...${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}2. Aplicando migration de índices otimizados...${NC}"
docker compose exec -T db mysql -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" mapacultural < database/migrations/optimize_sync_performance.sql
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Índices otimizados criados!${NC}"
else
    echo -e "${RED}✗ Falha ao criar índices!${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}3. Verificando índices criados...${NC}"
docker compose exec db mysql -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" mapacultural -e "SHOW INDEX FROM eventos WHERE Key_name LIKE 'idx_%';"
docker compose exec db mysql -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" mapacultural -e "SHOW INDEX FROM eventos_linguagens WHERE Key_name LIKE 'idx_%';"
docker compose exec db mysql -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" mapacultural -e "SHOW INDEX FROM eventos_selos WHERE Key_name LIKE 'idx_%';"

echo ""
echo -e "${YELLOW}4. Estatísticas do banco ANTES da sincronização otimizada...${NC}"
docker compose exec db mysql -u root -p"${MYSQL_ROOT_PASSWORD:-root_password}" mapacultural -e "
    SELECT 
        (SELECT COUNT(*) FROM eventos) as total_eventos,
        (SELECT COUNT(*) FROM linguagens) as total_linguagens,
        (SELECT COUNT(*) FROM selos) as total_selos,
        (SELECT COUNT(*) FROM eventos_linguagens) as total_relacionamentos_linguagens,
        (SELECT COUNT(*) FROM eventos_selos) as total_relacionamentos_selos;
"

echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}Deploy concluído com sucesso!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo "Próximos passos:"
echo "1. Execute a sincronização: docker compose exec app php cron/sync_eventos.php"
echo "2. Monitore os logs: tail -f logs/sync.log"
echo "3. Compare o tempo de execução com sincronizações anteriores"
echo ""
echo "Melhorias esperadas:"
echo "- Redução de ~1 hora para ~2-5 minutos em 12 mil eventos"
echo "- Redução de queries de ~36k+ para ~50 queries"
echo "- Uso de transações em lote de 500 eventos"
echo ""
