<?php
/**
 * Cliente para API do Mapa Cultural do Ceará
 */

class MapaCulturalAPI {
    private $baseUrl;
    private $timeout;
    private $cache;
    
    public function __construct() {
        $this->baseUrl = getenv('API_URL') ?: 'https://mapacultural.secult.ce.gov.br/api';
        $this->timeout = getenv('API_TIMEOUT') ?: 30;
        
        // Tenta usar cache Redis se disponível
        if (class_exists('RedisCache')) {
            $this->cache = RedisCache::getInstance();
        }
    }
    
    /**
     * Busca eventos da API
     */
    public function getEvents($params = []) {
        $defaultParams = [
            '@select' => 'id,name,shortDescription,longDescription,location,En_Municipio,En_Estado,En_CEP,acessibilidade,site,emailPublico,telefonePublico,classificacaoEtaria,terms,occurrences,seals',
            '@files' => '(avatar.avatarMedium,avatar.avatarBig):url',
            '@order' => 'name ASC',
            '@seals' => '32'  // Filtrar apenas eventos com selo 32
        ];
        
        $params = array_merge($defaultParams, $params);
        
        return $this->makeRequest('/event/find', $params);
    }
    
    /**
     * Busca um evento específico por ID
     */
    public function getEvent($id) {
        return $this->makeRequest("/event/findOne", ['@select' => 'id,name,shortDescription,longDescription,location,acessibilidade,classificacaoEtaria,terms,occurrences']);
    }
    
    /**
     * Busca termos/taxonomias
     */
    public function getTerms($taxonomy = 'linguagem') {
        $cacheKey = "api:terms:$taxonomy";
        
        if ($this->cache && $this->cache->isConnected()) {
            $cached = $this->cache->get($cacheKey);
            if ($cached) return $cached;
        }
        
        $result = $this->makeRequest('/term/list', ['taxonomy' => $taxonomy]);
        
        if ($this->cache && $this->cache->isConnected()) {
            $this->cache->set($cacheKey, $result, 86400); // 24h
        }
        
        return $result;
    }
    
    /**
     * Faz requisição à API
     */
    private function makeRequest($endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: MapaEventosCE/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Erro cURL: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API retornou código HTTP $httpCode");
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Busca com paginação
     */
    public function getEventsPaginated($page = 1, $limit = 100, $filters = []) {
        $params = array_merge($filters, [
            '@page' => $page,
            '@limit' => $limit
        ]);
        
        return $this->getEvents($params);
    }
    
    /**
     * Busca todos os eventos (com paginação automática)
     */
    public function getAllEvents($onProgress = null) {
        $allEvents = [];
        $page = 1;
        $limit = 100;
        
        do {
            try {
                $events = $this->getEventsPaginated($page, $limit);
                
                if (empty($events)) {
                    break;
                }
                
                $allEvents = array_merge($allEvents, $events);
                
                if (is_callable($onProgress)) {
                    $onProgress($page, count($events), count($allEvents));
                }
                
                $page++;
                
                // Pequeno delay para não sobrecarregar a API
                usleep(500000); // 0.5 segundos
                
            } catch (Exception $e) {
                error_log("Erro ao buscar página $page: " . $e->getMessage());
                break;
            }
            
        } while (count($events) === $limit);
        
        return $allEvents;
    }
}
