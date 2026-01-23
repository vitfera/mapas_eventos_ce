<?php
/**
 * API Endpoint: Lista eventos do banco de dados
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/redis.php';

try {
    $db = Database::getInstance()->getConnection();
    $cache = RedisCache::getInstance();
    
    // Parâmetros
    $municipio = $_GET['municipio'] ?? null;
    $linguagem = $_GET['linguagem'] ?? null;
    $periodo = $_GET['periodo'] ?? 'todos'; // futuros, passados, todos
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    // Chave de cache
    $cacheKey = "eventos:" . md5(json_encode(['municipio' => $municipio, 'linguagem' => $linguagem, 'periodo' => $periodo, 'page' => $page, 'limit' => $limit]));
    
    // Tenta buscar do cache
    if ($cache->isConnected()) {
        $cached = $cache->get($cacheKey);
        if ($cached) {
            echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }
    
    // Query base
    $sql = "SELECT 
                e.*,
                GROUP_CONCAT(DISTINCT l.nome SEPARATOR ', ') as linguagens
            FROM eventos e
            LEFT JOIN eventos_linguagens el ON e.id = el.evento_id
            LEFT JOIN linguagens l ON el.linguagem_id = l.id";
    
    $where = [];
    $params = [];
    
    // Filtros
    if ($municipio) {
        $where[] = "e.municipio = :municipio";
        $params['municipio'] = $municipio;
    }
    
    if ($linguagem) {
        $where[] = "l.nome = :linguagem";
        $params['linguagem'] = $linguagem;
    }
    
    // Filtro de período
    if ($periodo === 'futuros') {
        $where[] = "e.data_inicio >= NOW()";
    } elseif ($periodo === 'passados') {
        $where[] = "e.data_fim < NOW()";
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " GROUP BY e.id ORDER BY e.nome ASC LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total de registros
    $countSql = "SELECT COUNT(DISTINCT e.id) as total FROM eventos e";
    if ($linguagem) {
        $countSql .= " LEFT JOIN eventos_linguagens el ON e.id = el.evento_id
                       LEFT JOIN linguagens l ON el.linguagem_id = l.id";
    }
    if (!empty($where)) {
        $countSql .= " WHERE " . implode(" AND ", $where);
    }
    
    $countStmt = $db->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(":$key", $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Formata resposta
    $response = [
        'success' => true,
        'data' => $eventos,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ];
    
    // Salva no cache (1 hora)
    if ($cache->isConnected()) {
        $cache->set($cacheKey, $response, 3600);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
