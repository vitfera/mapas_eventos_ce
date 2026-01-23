<?php
/**
 * API Endpoint: Dispara sincronização
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../services/SyncService.php';

// Aumenta limites para sync
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '512M');

try {
    $sync = new SyncService();
    
    // Verifica se já há sync em andamento
    $lastSync = $sync->getLastSyncLog();
    
    if ($lastSync && $lastSync['status'] === 'em_progresso') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Sincronização já em andamento',
            'sync' => $lastSync
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Executa sincronização
    $stats = $sync->syncEvents();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sincronização concluída com sucesso',
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
