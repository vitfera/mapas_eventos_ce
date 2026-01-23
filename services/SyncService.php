<?php
/**
 * Serviço de Sincronização com a API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/redis.php';
require_once __DIR__ . '/MapaCulturalAPI.php';

class SyncService {
    private $db;
    private $cache;
    private $api;
    private $syncLogId;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->cache = RedisCache::getInstance();
        $this->api = new MapaCulturalAPI();
    }
    
    /**
     * Sincroniza eventos da API para o banco
     */
    public function syncEvents() {
        // Inicia log de sincronização
        $this->syncLogId = $this->createSyncLog();
        
        try {
            $this->updateSyncLog('em_progresso');
            
            $stats = [
                'total' => 0,
                'novos' => 0,
                'atualizados' => 0,
                'erros' => 0
            ];
            
            // Busca todos os eventos da API
            $events = $this->api->getAllEvents(function($page, $pageCount, $total) {
                echo "Página $page: $pageCount eventos | Total: $total\n";
            });
            
            $stats['total'] = count($events);
            
            // Processa cada evento
            foreach ($events as $event) {
                try {
                    $resultado = $this->processEvent($event);
                    
                    if ($resultado === 'novo') {
                        $stats['novos']++;
                    } elseif ($resultado === 'atualizado') {
                        $stats['atualizados']++;
                    }
                    
                } catch (Exception $e) {
                    $stats['erros']++;
                    error_log("Erro ao processar evento ID {$event['id']}: " . $e->getMessage());
                }
            }
            
            // Finaliza log
            $this->finalizeSyncLog('concluido', $stats);
            
            // Limpa cache
            $this->invalidateCache();
            
            return $stats;
            
        } catch (Exception $e) {
            $this->finalizeSyncLog('erro', null, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Processa um evento individual
     */
    private function processEvent($apiEvent) {
        $externalId = $apiEvent['id'];
        $data = $this->extractEventData($apiEvent);

        // Ignora eventos sem nome válido
        if (empty($data['nome']) || trim($data['nome']) === '' || $data['nome'] === 'Sem nome') {
            return 'ignorado';
        }

        // Verifica se já existe
        $stmt = $this->db->prepare("SELECT id FROM eventos WHERE external_id = ?");
        $stmt->execute([$externalId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Atualiza
            $this->updateEvent($existing['id'], $data);
            $eventoId = $existing['id'];
            $resultado = 'atualizado';
        } else {
            // Insere novo
            $eventoId = $this->insertEvent($data);
            $resultado = 'novo';
        }

        // Processa linguagens
        if (!empty($apiEvent['terms']['linguagem'])) {
            $this->syncEventLanguages($eventoId, $apiEvent['terms']['linguagem']);
        }

        return $resultado;
    }
    
    /**
     * Extrai dados do evento da API para formato do banco
     */
    private function extractEventData($apiEvent) {
        // Helper para truncar strings
        $truncate = function($str, $length) {
            if ($str === null) return null;
            return mb_substr($str, 0, $length);
        };
        
        $nome = isset($apiEvent['name']) ? trim($apiEvent['name']) : '';
        if ($nome === '' || $nome === null) {
            $nome = 'Sem nome';
        }
        $municipio = isset($apiEvent['En_Municipio']) ? trim($apiEvent['En_Municipio']) : '';
        if ($municipio === '' || $municipio === null) {
            $municipio = 'Não informado';
        }
        
        // Processa datas do evento
        $dataInicio = null;
        $dataFim = null;
        if (!empty($apiEvent['occurrences']) && is_array($apiEvent['occurrences'])) {
            $firstOccurrence = $apiEvent['occurrences'][0] ?? null;
            if ($firstOccurrence) {
                $dataInicio = $firstOccurrence['startsOn'] ?? null;
                $dataFim = $firstOccurrence['endsOn'] ?? $firstOccurrence['startsOn'] ?? null;
            }
        }
        
        return [
            'external_id' => $apiEvent['id'],
            'nome' => $truncate($nome, 255),
            'descricao' => $apiEvent['shortDescription'] ?? $apiEvent['longDescription'] ?? null,
            'local' => $truncate($apiEvent['location']['address'] ?? null, 255),
            'municipio' => $truncate($municipio, 100),
            'cep' => $truncate($apiEvent['En_CEP'] ?? null, 20),
            'latitude' => !empty($apiEvent['location']['latitude']) ? (float)$apiEvent['location']['latitude'] : null,
            'longitude' => !empty($apiEvent['location']['longitude']) ? (float)$apiEvent['location']['longitude'] : null,
            'telefone' => $truncate($apiEvent['telefonePublico'] ?? null, 50),
            'email' => $truncate($apiEvent['emailPublico'] ?? null, 255),
            'site' => $truncate($apiEvent['site'] ?? null, 255),
            'acessibilidade' => !empty($apiEvent['acessibilidade']) ? 1 : 0,
            'classificacao_etaria' => $truncate($apiEvent['classificacaoEtaria'] ?? null, 50),
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim
        ];
    }
    
    /**
     * Insere novo evento
     */
    private function insertEvent($data) {
        // Remove chaves que não devem estar no INSERT
        unset($data['id']);
        
        $sql = "INSERT INTO eventos (external_id, nome, descricao, local, municipio, cep, 
                latitude, longitude, telefone, email, site, acessibilidade, classificacao_etaria,
                data_inicio, data_fim, created_at, updated_at)
                VALUES (:external_id, :nome, :descricao, :local, :municipio, :cep,
                :latitude, :longitude, :telefone, :email, :site, :acessibilidade, 
                :classificacao_etaria, :data_inicio, :data_fim, NOW(), NOW())";
        
        $stmt = $this->db->prepare($sql);
        
        // Bind dos parâmetros explicitamente
        $stmt->bindValue(':external_id', $data['external_id'], PDO::PARAM_INT);
        $stmt->bindValue(':nome', $data['nome'], PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $data['descricao'], $data['descricao'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':local', $data['local'], $data['local'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':municipio', $data['municipio'], $data['municipio'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':cep', $data['cep'], $data['cep'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':latitude', $data['latitude'], $data['latitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':longitude', $data['longitude'], $data['longitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':telefone', $data['telefone'], $data['telefone'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], $data['email'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':site', $data['site'], $data['site'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':acessibilidade', $data['acessibilidade'], PDO::PARAM_INT);
        $stmt->bindValue(':classificacao_etaria', $data['classificacao_etaria'], $data['classificacao_etaria'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':data_inicio', $data['data_inicio'], $data['data_inicio'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':data_fim', $data['data_fim'], $data['data_fim'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        
        $stmt->execute();
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Atualiza evento existente
     */
    private function updateEvent($id, $data) {
        $sql = "UPDATE eventos SET 
                nome = :nome,
                descricao = :descricao,
                local = :local,
                municipio = :municipio,
                cep = :cep,
                latitude = :latitude,
                longitude = :longitude,
                telefone = :telefone,
                email = :email,
                site = :site,
                acessibilidade = :acessibilidade,
                classificacao_etaria = :classificacao_etaria,
                data_inicio = :data_inicio,
                data_fim = :data_fim,
                updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        
        // Bind dos parâmetros explicitamente
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':nome', $data['nome'], PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $data['descricao'], $data['descricao'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':local', $data['local'], $data['local'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':municipio', $data['municipio'], $data['municipio'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':cep', $data['cep'], $data['cep'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':latitude', $data['latitude'], $data['latitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':longitude', $data['longitude'], $data['longitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':telefone', $data['telefone'], $data['telefone'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], $data['email'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':site', $data['site'], $data['site'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':acessibilidade', $data['acessibilidade'], PDO::PARAM_INT);
        $stmt->bindValue(':classificacao_etaria', $data['classificacao_etaria'], $data['classificacao_etaria'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':data_inicio', $data['data_inicio'], $data['data_inicio'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':data_fim', $data['data_fim'], $data['data_fim'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        
        $stmt->execute();
    }
    
    /**
     * Sincroniza linguagens de um evento
     */
    private function syncEventLanguages($eventoId, $linguagens) {
        // Remove linguagens antigas
        $stmt = $this->db->prepare("DELETE FROM eventos_linguagens WHERE evento_id = ?");
        $stmt->execute([$eventoId]);
        
        // Insere novas linguagens
        foreach ($linguagens as $linguagemName) {
            // Busca ou cria linguagem
            $linguagemId = $this->getOrCreateLanguage($linguagemName);
            
            if ($linguagemId) {
                $stmt = $this->db->prepare("INSERT IGNORE INTO eventos_linguagens (evento_id, linguagem_id) VALUES (?, ?)");
                $stmt->execute([$eventoId, $linguagemId]);
            }
        }
    }
    
    /**
     * Busca ou cria linguagem
     */
    private function getOrCreateLanguage($nome) {
        $stmt = $this->db->prepare("SELECT id FROM linguagens WHERE nome = ?");
        $stmt->execute([$nome]);
        $linguagem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($linguagem) {
            return $linguagem['id'];
        }
        
        // Cria nova linguagem
        $stmt = $this->db->prepare("INSERT INTO linguagens (nome) VALUES (?)");
        $stmt->execute([$nome]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Cria registro de log de sincronização
     */
    private function createSyncLog() {
        $stmt = $this->db->prepare("INSERT INTO sync_logs (status, started_at) VALUES ('iniciado', NOW())");
        $stmt->execute();
        return $this->db->lastInsertId();
    }
    
    /**
     * Atualiza status do log
     */
    private function updateSyncLog($status) {
        $stmt = $this->db->prepare("UPDATE sync_logs SET status = ? WHERE id = ?");
        $stmt->execute([$status, $this->syncLogId]);
    }
    
    /**
     * Finaliza log de sincronização
     */
    private function finalizeSyncLog($status, $stats = null, $mensagem = null) {
        $sql = "UPDATE sync_logs SET 
                status = :status,
                total_eventos = :total,
                eventos_novos = :novos,
                eventos_atualizados = :atualizados,
                eventos_erro = :erros,
                mensagem = :mensagem,
                finished_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'status' => $status,
            'total' => $stats['total'] ?? 0,
            'novos' => $stats['novos'] ?? 0,
            'atualizados' => $stats['atualizados'] ?? 0,
            'erros' => $stats['erros'] ?? 0,
            'mensagem' => $mensagem,
            'id' => $this->syncLogId
        ]);
    }
    
    /**
     * Invalida cache após sincronização
     */
    private function invalidateCache() {
        if ($this->cache->isConnected()) {
            $this->cache->deletePattern('eventos:*');
            $this->cache->deletePattern('stats:*');
            $this->cache->deletePattern('linguagens:*');
        }
    }
    
    /**
     * Obtém último log de sincronização
     */
    public function getLastSyncLog() {
        $stmt = $this->db->query("SELECT * FROM sync_logs ORDER BY started_at DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
