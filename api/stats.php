<?php
/**
 * API Endpoint: Estatísticas
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/redis.php';

try {
    $db = Database::getInstance()->getConnection();
    $cache = RedisCache::getInstance();
    
    $cacheKey = "stats:geral";
    
    // Tenta buscar do cache
    if ($cache->isConnected()) {
        $cached = $cache->get($cacheKey);
        if ($cached) {
            echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    // Estatísticas gerais
    $stmt = $db->query("SELECT * FROM vw_estatisticas");
    $geral = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Distribuição por linguagem
    $stmt = $db->query("SELECT * FROM vw_distribuicao_linguagens");
    $linguagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Distribuição por município
    $stmt = $db->query("SELECT * FROM vw_distribuicao_municipios");
    $municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Último sync
    $stmt = $db->query("SELECT * FROM sync_logs ORDER BY started_at DESC LIMIT 1");
    $lastSync = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'geral' => $geral,
        'linguagens' => $linguagens,
        'municipios' => $municipios,
        'last_sync' => $lastSync
    ];
    
    // Salva no cache (30 minutos)
    if ($cache->isConnected()) {
        $cache->set($cacheKey, $response, 1800);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
