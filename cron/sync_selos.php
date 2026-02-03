<?php
/**
 * Script para sincronizar selos da API
 * Popula a tabela de selos com todos os selos disponíveis na API
 */

require_once __DIR__ . '/../services/MapaCulturalAPI.php';
require_once __DIR__ . '/../config/database.php';

set_time_limit(0);

echo "=== Sincronização de Selos ===\n";
echo "Início: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $api = new MapaCulturalAPI();
    $db = Database::getInstance()->getConnection();
    
    echo "Buscando selos da API...\n";
    $seals = $api->getSeals();
    
    echo "Total de selos encontrados: " . count($seals) . "\n\n";
    
    $stats = [
        'novos' => 0,
        'atualizados' => 0,
        'erros' => 0
    ];
    
    foreach ($seals as $sealData) {
        try {
            $externalId = $sealData['id'] ?? null;
            $nome = $sealData['name'] ?? 'Selo sem nome';
            $descricao = $sealData['shortDescription'] ?? null;
            
            // Verifica se já existe (por external_id ou nome)
            if ($externalId) {
                $stmt = $db->prepare("SELECT id FROM selos WHERE external_id = ?");
                $stmt->execute([$externalId]);
            } else {
                $stmt = $db->prepare("SELECT id FROM selos WHERE nome = ?");
                $stmt->execute([$nome]);
            }
            
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Atualiza
                $stmt = $db->prepare("UPDATE selos SET nome = ?, descricao = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$nome, $descricao, $existing['id']]);
                $stats['atualizados']++;
                echo "✓ Atualizado: $nome (ID: {$existing['id']})\n";
            } else {
                // Insere novo
                $stmt = $db->prepare("INSERT INTO selos (external_id, nome, descricao) VALUES (?, ?, ?)");
                $stmt->execute([$externalId, $nome, $descricao]);
                $stats['novos']++;
                echo "+ Novo: $nome (ID: " . $db->lastInsertId() . ")\n";
            }
            
        } catch (Exception $e) {
            $stats['erros']++;
            echo "✗ Erro ao processar selo '{$nome}': " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Resultado da Sincronização ===\n";
    echo "Novos: {$stats['novos']}\n";
    echo "Atualizados: {$stats['atualizados']}\n";
    echo "Erros: {$stats['erros']}\n";
    echo "\nFim: " . date('Y-m-d H:i:s') . "\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\nERRO: " . $e->getMessage() . "\n";
    exit(1);
}
