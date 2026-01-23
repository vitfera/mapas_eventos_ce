<?php
/**
 * Script para sincronizar eventos da API
 * Pode ser executado via CLI ou agendado no cron
 */

require_once __DIR__ . '/../services/SyncService.php';

// Configurar para execução CLI
set_time_limit(0);
ini_set('memory_limit', '512M');

echo "=== Sincronização de Eventos Culturais ===\n";
echo "Início: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $sync = new SyncService();
    
    echo "Buscando eventos da API...\n";
    $stats = $sync->syncEvents();
    
    echo "\n=== Resultado da Sincronização ===\n";
    echo "Total de eventos: {$stats['total']}\n";
    echo "Novos: {$stats['novos']}\n";
    echo "Atualizados: {$stats['atualizados']}\n";
    echo "Erros: {$stats['erros']}\n";
    echo "\nFim: " . date('Y-m-d H:i:s') . "\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\nERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
