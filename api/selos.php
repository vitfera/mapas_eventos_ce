<?php
/**
 * API Endpoint: Lista selos disponíveis
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

require_once __DIR__ . '/../services/MapaCulturalAPI.php';
require_once __DIR__ . '/../config/redis.php';

try {
    $cache = RedisCache::getInstance();
    $cacheKey = 'api:seals:list';
    
    // Tenta buscar do cache
    if ($cache->isConnected()) {
        $cached = $cache->get($cacheKey);
        if ($cached) {
            echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    // Busca selos da API
    $api = new MapaCulturalAPI();
    $seals = $api->getSeals();
    
    $response = [
        'success' => true,
        'data' => $seals,
        'total' => count($seals),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Salva no cache por 24h
    if ($cache->isConnected()) {
        $cache->set($cacheKey, $response, 86400);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
