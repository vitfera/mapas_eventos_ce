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
    private $logFile;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->cache = RedisCache::getInstance();
        $this->api = new MapaCulturalAPI();
        $this->logFile = __DIR__ . '/../logs/sync.log';
    }
    
    /**
     * Escreve mensagem no arquivo de log
     */
    private function writeLog($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
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
            
            $this->writeLog("========================================");
            $this->writeLog("INÍCIO DA SINCRONIZAÇÃO OTIMIZADA");
            $this->writeLog("========================================");
            $this->writeLog("Iniciando busca de eventos da API...");
            
            // Busca todos os eventos da API
            $events = $this->api->getAllEvents(function($page, $pageCount, $total) {
                $this->writeLog("Página $page: $pageCount eventos | Total: $total");
            });
            
            $this->writeLog("Total de eventos encontrados: " . count($events));
            $stats['total'] = count($events);
            
            // ========================================
            // OTIMIZAÇÃO: Carrega todos eventos existentes em memória (1 query vs 12k queries)
            // ========================================
            $this->writeLog("Carregando eventos existentes do banco...");
            $existingEvents = $this->loadExistingEvents();
            $this->writeLog("Total de eventos existentes: " . count($existingEvents));
            
            // ========================================
            // OTIMIZAÇÃO: Carrega linguagens e selos em cache
            // ========================================
            $this->writeLog("Carregando linguagens e selos em cache...");
            $linguagensCache = $this->loadLanguagesCache();
            $selosCache = $this->loadSealsCache();
            $this->writeLog("Cache carregado: " . count($linguagensCache) . " linguagens, " . count($selosCache) . " selos");
            
            // Separa eventos em novos e para atualizar
            $novosEventos = [];
            $eventosParaAtualizar = [];
            
            $this->writeLog("Separando eventos novos e para atualização...");
            foreach ($events as $event) {
                try {
                    $externalId = $event['id'];
                    $data = $this->extractEventData($event);
                    
                    // Ignora eventos sem nome válido
                    if (empty($data['nome']) || trim($data['nome']) === '' || $data['nome'] === 'Sem nome') {
                        continue;
                    }
                    
                    // Armazena dados extras
                    $data['_linguagens'] = $event['terms']['linguagem'] ?? [];
                    $data['_selos'] = $event['seals'] ?? [];
                    
                    if (isset($existingEvents[$externalId])) {
                        $data['_db_id'] = $existingEvents[$externalId];
                        $eventosParaAtualizar[] = $data;
                    } else {
                        $novosEventos[] = $data;
                    }
                    
                } catch (Exception $e) {
                    $stats['erros']++;
                    $this->writeLog("Erro ao processar evento ID {$event['id']}: " . $e->getMessage());
                }
            }
            
            $this->writeLog("Eventos a inserir: " . count($novosEventos));
            $this->writeLog("Eventos a atualizar: " . count($eventosParaAtualizar));
            
            // ========================================
            // OTIMIZAÇÃO: Processa em lotes (batches)
            // ========================================
            $batchSize = 500;
            
            // Insere novos em lotes
            if (count($novosEventos) > 0) {
                $this->writeLog("Inserindo novos eventos em lotes de $batchSize...");
                $novosInseridos = $this->batchInsertEvents($novosEventos, $batchSize, $linguagensCache, $selosCache);
                $stats['novos'] = $novosInseridos;
                $this->writeLog("Novos eventos inseridos: $novosInseridos");
            }
            
            // Atualiza existentes em lotes
            if (count($eventosParaAtualizar) > 0) {
                $this->writeLog("Atualizando eventos existentes em lotes de $batchSize...");
                $atualizados = $this->batchUpdateEvents($eventosParaAtualizar, $batchSize, $linguagensCache, $selosCache);
                $stats['atualizados'] = $atualizados;
                $this->writeLog("Eventos atualizados: $atualizados");
            }
            
            // Finaliza log
            $this->finalizeSyncLog('concluido', $stats);
            
            // Limpa cache
            $this->invalidateCache();
            
            $this->writeLog("\n====== RESUMO DA SINCRONIZAÇÃO ======");
            $this->writeLog("Total de eventos: {$stats['total']}");
            $this->writeLog("Novos: {$stats['novos']}");
            $this->writeLog("Atualizados: {$stats['atualizados']}");
            $this->writeLog("Erros: {$stats['erros']}");
            $this->writeLog("======================================");
            $this->writeLog("Sincronização concluída com sucesso!");
            $this->writeLog("\n");
            
            return $stats;
            
        } catch (Exception $e) {
            $this->writeLog("ERRO NA SINCRONIZAÇÃO: " . $e->getMessage());
            $this->finalizeSyncLog('erro', null, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * OTIMIZAÇÃO: Carrega todos eventos existentes em memória
     * Retorna array [external_id => db_id]
     */
    private function loadExistingEvents() {
        $stmt = $this->db->query("SELECT id, external_id FROM eventos");
        $existing = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing[$row['external_id']] = $row['id'];
        }
        return $existing;
    }
    
    /**
     * OTIMIZAÇÃO: Carrega linguagens em cache
     * Retorna array [nome => id]
     */
    private function loadLanguagesCache() {
        $stmt = $this->db->query("SELECT id, nome FROM linguagens");
        $cache = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cache[$row['nome']] = $row['id'];
        }
        return $cache;
    }
    
    /**
     * OTIMIZAÇÃO: Carrega selos em cache
     * Retorna array [external_id => id] e [nome => id]
     */
    private function loadSealsCache() {
        $stmt = $this->db->query("SELECT id, external_id, nome FROM selos");
        $cache = ['by_id' => [], 'by_name' => []];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['external_id']) {
                $cache['by_id'][$row['external_id']] = $row['id'];
            }
            $cache['by_name'][$row['nome']] = $row['id'];
        }
        return $cache;
    }
    
    /**
     * OTIMIZAÇÃO: Insere eventos em lotes com transações
     */
    private function batchInsertEvents($eventos, $batchSize, &$linguagensCache, &$selosCache) {
        $total = 0;
        $batches = array_chunk($eventos, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            try {
                $this->db->beginTransaction();
                
                foreach ($batch as $data) {
                    $linguagens = $data['_linguagens'];
                    $selos = $data['_selos'];
                    unset($data['_linguagens'], $data['_selos']);
                    
                    $eventoId = $this->insertEvent($data);
                    
                    // Processa relacionamentos
                    if (!empty($linguagens)) {
                        $this->batchSyncLanguages($eventoId, $linguagens, $linguagensCache);
                    }
                    if (!empty($selos)) {
                        $this->batchSyncSeals($eventoId, $selos, $selosCache);
                    }
                    
                    $total++;
                }
                
                $this->db->commit();
                $this->writeLog("Lote " . ($batchIndex + 1) . "/" . count($batches) . " inserido: " . count($batch) . " eventos");
                
            } catch (Exception $e) {
                $this->db->rollBack();
                $this->writeLog("ERRO no lote " . ($batchIndex + 1) . ": " . $e->getMessage());
                throw $e;
            }
        }
        
        return $total;
    }
    
    /**
     * OTIMIZAÇÃO: Atualiza eventos em lotes com transações
     */
    private function batchUpdateEvents($eventos, $batchSize, &$linguagensCache, &$selosCache) {
        $total = 0;
        $batches = array_chunk($eventos, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            try {
                $this->db->beginTransaction();
                
                foreach ($batch as $data) {
                    $eventoId = $data['_db_id'];
                    $linguagens = $data['_linguagens'];
                    $selos = $data['_selos'];
                    unset($data['_db_id'], $data['_linguagens'], $data['_selos']);
                    
                    $this->updateEvent($eventoId, $data);
                    
                    // Processa relacionamentos
                    if (!empty($linguagens)) {
                        $this->batchSyncLanguages($eventoId, $linguagens, $linguagensCache);
                    }
                    if (!empty($selos)) {
                        $this->batchSyncSeals($eventoId, $selos, $selosCache);
                    }
                    
                    $total++;
                }
                
                $this->db->commit();
                $this->writeLog("Lote " . ($batchIndex + 1) . "/" . count($batches) . " atualizado: " . count($batch) . " eventos");
                
            } catch (Exception $e) {
                $this->db->rollBack();
                $this->writeLog("ERRO no lote " . ($batchIndex + 1) . ": " . $e->getMessage());
                throw $e;
            }
        }
        
        return $total;
    }
    
    /**
     * OTIMIZAÇÃO: Sincroniza linguagens usando cache
     */
    private function batchSyncLanguages($eventoId, $linguagens, &$cache) {
        // Remove antigas
        $stmt = $this->db->prepare("DELETE FROM eventos_linguagens WHERE evento_id = ?");
        $stmt->execute([$eventoId]);
        
        if (empty($linguagens)) {
            return;
        }
        
        // Prepara statement para reutilização
        $insertStmt = $this->db->prepare("INSERT IGNORE INTO eventos_linguagens (evento_id, linguagem_id) VALUES (?, ?)");
        $createStmt = $this->db->prepare("INSERT INTO linguagens (nome) VALUES (?)");
        
        foreach ($linguagens as $nome) {
            // Busca no cache
            if (!isset($cache[$nome])) {
                // Cria nova e adiciona ao cache
                $createStmt->execute([$nome]);
                $cache[$nome] = $this->db->lastInsertId();
            }
            
            // Insere relacionamento
            $insertStmt->execute([$eventoId, $cache[$nome]]);
        }
    }
    
    /**
     * OTIMIZAÇÃO: Sincroniza selos usando cache
     */
    private function batchSyncSeals($eventoId, $selos, &$cache) {
        // Remove antigos
        $stmt = $this->db->prepare("DELETE FROM eventos_selos WHERE evento_id = ?");
        $stmt->execute([$eventoId]);
        
        if (empty($selos)) {
            return;
        }
        
        // Prepara statements para reutilização
        $insertStmt = $this->db->prepare("INSERT IGNORE INTO eventos_selos (evento_id, selo_id) VALUES (?, ?)");
        $createStmt = $this->db->prepare("INSERT INTO selos (external_id, nome, descricao) VALUES (?, ?, ?)");
        $updateStmt = $this->db->prepare("UPDATE selos SET nome = ?, descricao = ?, updated_at = NOW() WHERE id = ?");
        
        foreach ($selos as $seloData) {
            if (!is_array($seloData) || empty($seloData['name'])) {
                continue;
            }
            
            $externalId = !empty($seloData['id']) ? $seloData['id'] : null;
            $nome = $seloData['name'];
            $descricao = $seloData['shortDescription'] ?? null;
            
            $seloId = null;
            
            // Busca por external_id no cache
            if ($externalId && isset($cache['by_id'][$externalId])) {
                $seloId = $cache['by_id'][$externalId];
                // Atualiza se mudou
                $updateStmt->execute([$nome, $descricao, $seloId]);
            }
            // Busca por nome no cache
            elseif (isset($cache['by_name'][$nome])) {
                $seloId = $cache['by_name'][$nome];
            }
            // Cria novo
            else {
                $createStmt->execute([$externalId, $nome, $descricao]);
                $seloId = $this->db->lastInsertId();
                
                // Adiciona ao cache
                if ($externalId) {
                    $cache['by_id'][$externalId] = $seloId;
                }
                $cache['by_name'][$nome] = $seloId;
            }
            
            // Insere relacionamento
            if ($seloId) {
                $insertStmt->execute([$eventoId, $seloId]);
            }
        }
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
        
        // Processa datas e local do evento através das occurrences
        $dataInicio = null;
        $dataFim = null;
        $horaInicio = null;
        $horaFim = null;
        $localNome = null;
        
        if (!empty($apiEvent['occurrences']) && is_array($apiEvent['occurrences'])) {
            $firstOccurrence = $apiEvent['occurrences'][0] ?? null;
            
            if ($firstOccurrence && is_array($firstOccurrence)) {
                // Os dados estão dentro do objeto 'rule'
                $rule = $firstOccurrence['rule'] ?? [];
                
                $dataInicio = $rule['startsOn'] ?? null;
                // Se 'until' estiver vazio, usa startsOn como data_fim
                $dataFim = !empty($rule['until']) ? $rule['until'] : ($rule['startsOn'] ?? null);
                $horaInicio = $rule['startsAt'] ?? null;
                $horaFim = $rule['endsAt'] ?? null;
                
                // Extrai nome do espaço
                if (isset($firstOccurrence['space']['name'])) {
                    $localNome = $firstOccurrence['space']['name'];
                }
            }
        }
        
        // Processa tags
        $tags = null;
        if (!empty($apiEvent['terms']['tag']) && is_array($apiEvent['terms']['tag'])) {
            $tags = implode(', ', $apiEvent['terms']['tag']);
        }
        
        return [
            'external_id' => $apiEvent['id'],
            'nome' => $truncate($nome, 255),
            'descricao' => $apiEvent['shortDescription'] ?? $apiEvent['longDescription'] ?? null,
            'local' => $truncate($apiEvent['location']['address'] ?? null, 255),
            'local_nome' => $truncate($localNome, 255),
            'municipio' => $truncate($municipio, 100),
            'cep' => $truncate($apiEvent['En_CEP'] ?? null, 20),
            'latitude' => !empty($apiEvent['location']['latitude']) ? (float)$apiEvent['location']['latitude'] : null,
            'longitude' => !empty($apiEvent['location']['longitude']) ? (float)$apiEvent['location']['longitude'] : null,
            'telefone' => $truncate($apiEvent['telefonePublico'] ?? null, 50),
            'email' => $truncate($apiEvent['emailPublico'] ?? null, 255),
            'site' => $truncate($apiEvent['site'] ?? null, 255),
            'acessibilidade' => !empty($apiEvent['acessibilidade']) ? 1 : 0,
            'classificacao_etaria' => $truncate($apiEvent['classificacaoEtaria'] ?? null, 50),
            'tags' => $tags,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'hora_inicio' => $horaInicio,
            'hora_fim' => $horaFim
        ];
    }
    
    /**
     * Insere novo evento
     */
    private function insertEvent($data) {
        // Remove chaves que não devem estar no INSERT
        unset($data['id']);
        
        $sql = "INSERT INTO eventos (external_id, nome, descricao, local, local_nome, municipio, cep, 
                latitude, longitude, telefone, email, site, acessibilidade, classificacao_etaria, tags,
                data_inicio, data_fim, hora_inicio, hora_fim, created_at, updated_at)
                VALUES (:external_id, :nome, :descricao, :local, :local_nome, :municipio, :cep,
                :latitude, :longitude, :telefone, :email, :site, :acessibilidade, 
                :classificacao_etaria, :tags, :data_inicio, :data_fim, :hora_inicio, :hora_fim, NOW(), NOW())";
        
        $stmt = $this->db->prepare($sql);
        
        // Bind dos parâmetros explicitamente
        $stmt->bindValue(':external_id', $data['external_id'], PDO::PARAM_INT);
        $stmt->bindValue(':nome', $data['nome'], PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $data['descricao'], $data['descricao'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':local', $data['local'], $data['local'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':local_nome', $data['local_nome'], $data['local_nome'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':municipio', $data['municipio'], $data['municipio'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':cep', $data['cep'], $data['cep'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':latitude', $data['latitude'], $data['latitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':longitude', $data['longitude'], $data['longitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':telefone', $data['telefone'], $data['telefone'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], $data['email'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':site', $data['site'], $data['site'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':acessibilidade', $data['acessibilidade'], PDO::PARAM_INT);
        $stmt->bindValue(':classificacao_etaria', $data['classificacao_etaria'], $data['classificacao_etaria'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':tags', $data['tags'], $data['tags'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':data_inicio', $data['data_inicio'], $data['data_inicio'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':data_fim', $data['data_fim'], $data['data_fim'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':hora_inicio', $data['hora_inicio'], $data['hora_inicio'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':hora_fim', $data['hora_fim'], $data['hora_fim'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        
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
                local_nome = :local_nome,
                municipio = :municipio,
                cep = :cep,
                latitude = :latitude,
                longitude = :longitude,
                telefone = :telefone,
                email = :email,
                site = :site,
                acessibilidade = :acessibilidade,
                classificacao_etaria = :classificacao_etaria,
                tags = :tags,
                data_inicio = :data_inicio,
                data_fim = :data_fim,
                hora_inicio = :hora_inicio,
                hora_fim = :hora_fim,
                updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        
        // Bind dos parâmetros explicitamente
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':nome', $data['nome'], PDO::PARAM_STR);
        $stmt->bindValue(':descricao', $data['descricao'], $data['descricao'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':local', $data['local'], $data['local'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':local_nome', $data['local_nome'], $data['local_nome'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':municipio', $data['municipio'], $data['municipio'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':cep', $data['cep'], $data['cep'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':latitude', $data['latitude'], $data['latitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':longitude', $data['longitude'], $data['longitude'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':telefone', $data['telefone'], $data['telefone'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':email', $data['email'], $data['email'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':site', $data['site'], $data['site'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':acessibilidade', $data['acessibilidade'], PDO::PARAM_INT);
        $stmt->bindValue(':classificacao_etaria', $data['classificacao_etaria'], $data['classificacao_etaria'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':tags', $data['tags'], $data['tags'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':data_inicio', $data['data_inicio'], $data['data_inicio'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':data_fim', $data['data_fim'], $data['data_fim'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':hora_inicio', $data['hora_inicio'], $data['hora_inicio'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':hora_fim', $data['hora_fim'], $data['hora_fim'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        
        $stmt->execute();
    }
    
    
    /**
     * Sincroniza linguagens de um evento (MÉTODO LEGADO - mantido para compatibilidade)
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
     * Busca ou cria linguagem (MÉTODO LEGADO - mantido para compatibilidade)
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
     * Sincroniza selos de um evento (MÉTODO LEGADO - mantido para compatibilidade)
     */
    private function syncEventSeals($eventoId, $selos) {
        // Remove selos antigos
        $stmt = $this->db->prepare("DELETE FROM eventos_selos WHERE evento_id = ?");
        $stmt->execute([$eventoId]);
        
        // Insere novos selos
        foreach ($selos as $seloData) {
            // Verifica se tem pelo menos o nome do selo
            if (is_array($seloData) && !empty($seloData['name'])) {
                // Busca ou cria selo
                $seloId = $this->getOrCreateSeal($seloData);
                
                if ($seloId) {
                    $stmt = $this->db->prepare("INSERT IGNORE INTO eventos_selos (evento_id, selo_id) VALUES (?, ?)");
                    $stmt->execute([$eventoId, $seloId]);
                }
            }
        }
    }
    
    
    /**
     * Busca ou cria selo (MÉTODO LEGADO - mantido para compatibilidade)
     */
    private function getOrCreateSeal($seloData) {
        $externalId = !empty($seloData['id']) ? $seloData['id'] : null;
        $nome = $seloData['name'] ?? 'Selo sem nome';
        $descricao = $seloData['shortDescription'] ?? null;
        
        // Primeiro tenta buscar por external_id se existir
        if ($externalId) {
            $stmt = $this->db->prepare("SELECT id FROM selos WHERE external_id = ?");
            $stmt->execute([$externalId]);
            $selo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($selo) {
                // Atualiza nome e descrição se mudaram
                $stmt = $this->db->prepare("UPDATE selos SET nome = ?, descricao = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$nome, $descricao, $selo['id']]);
                return $selo['id'];
            }
        }
        
        // Se não encontrou por ID, busca por nome
        $stmt = $this->db->prepare("SELECT id, external_id FROM selos WHERE nome = ?");
        $stmt->execute([$nome]);
        $selo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($selo) {
            // Se tinha external_id vazio e agora tem, atualiza
            if ($externalId && !$selo['external_id']) {
                $stmt = $this->db->prepare("UPDATE selos SET external_id = ?, descricao = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$externalId, $descricao, $selo['id']]);
            }
            return $selo['id'];
        }
        
        // Cria novo selo
        $stmt = $this->db->prepare("INSERT INTO selos (external_id, nome, descricao) VALUES (?, ?, ?)");
        $stmt->execute([$externalId, $nome, $descricao]);
        
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
