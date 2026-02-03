<?php
/**
 * API Endpoint: Lista selos disponíveis
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/redis.php';

try {
    $db = Database::getInstance()->getConnection();
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
    
    // Busca selos do banco de dados
    $sql = "SELECT id, external_id, nome as name, descricao as shortDescription 
            FROM selos 
            ORDER BY nome ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $seals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formata resposta usando external_id como id
    $formattedSeals = array_map(function($seal) {
        return [
            'id' => $seal['external_id'],
            'name' => $seal['name'],
            'shortDescription' => $seal['shortDescription']
        ];
    }, $seals);
    
    $response = [
        'success' => true,
        'data' => $formattedSeals,
        'total' => count($formattedSeals),
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
