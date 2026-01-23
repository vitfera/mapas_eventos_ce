<?php
/**
 * Configuração e gerenciamento de cache Redis
 */

class RedisCache {
    private static $instance = null;
    private $redis;
    private $connected = false;
    
    private $host;
    private $port;
    private $password;
    
    private function __construct() {
        $this->host = getenv('REDIS_HOST') ?: 'localhost';
        $this->port = getenv('REDIS_PORT') ?: 6379;
        $this->password = getenv('REDIS_PASSWORD') ?: null;
        
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->redis = new Redis();
            $this->connected = $this->redis->connect($this->host, $this->port, 2.5);
            
            if ($this->connected && $this->password) {
                $this->redis->auth($this->password);
            }
            
            if ($this->connected) {
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao conectar ao Redis: " . $e->getMessage());
            $this->connected = false;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Verifica se está conectado
     */
    public function isConnected() {
        return $this->connected;
    }
    
    /**
     * Armazena valor no cache
     */
    public function set($key, $value, $ttl = 3600) {
        if (!$this->connected) return false;
        
        try {
            return $this->redis->setex($key, $ttl, $value);
        } catch (Exception $e) {
            error_log("Erro ao salvar no Redis: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recupera valor do cache
     */
    public function get($key) {
        if (!$this->connected) return null;
        
        try {
            $value = $this->redis->get($key);
            return $value === false ? null : $value;
        } catch (Exception $e) {
            error_log("Erro ao ler do Redis: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Remove valor do cache
     */
    public function delete($key) {
        if (!$this->connected) return false;
        
        try {
            return $this->redis->del($key) > 0;
        } catch (Exception $e) {
            error_log("Erro ao deletar do Redis: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove múltiplas chaves por padrão
     */
    public function deletePattern($pattern) {
        if (!$this->connected) return false;
        
        try {
            $keys = $this->redis->keys($pattern);
            if (!empty($keys)) {
                return $this->redis->del($keys) > 0;
            }
            return true;
        } catch (Exception $e) {
            error_log("Erro ao deletar padrão do Redis: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se chave existe
     */
    public function exists($key) {
        if (!$this->connected) return false;
        
        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            error_log("Erro ao verificar chave no Redis: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Incrementa contador
     */
    public function increment($key, $amount = 1) {
        if (!$this->connected) return false;
        
        try {
            return $this->redis->incrBy($key, $amount);
        } catch (Exception $e) {
            error_log("Erro ao incrementar no Redis: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpa todo o cache
     */
    public function flush() {
        if (!$this->connected) return false;
        
        try {
            return $this->redis->flushDB();
        } catch (Exception $e) {
            error_log("Erro ao limpar Redis: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtém informações do Redis
     */
    public function info() {
        if (!$this->connected) return null;
        
        try {
            return $this->redis->info();
        } catch (Exception $e) {
            error_log("Erro ao obter info do Redis: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Armazena em cache com callback
     * Se não existir no cache, executa callback e armazena o resultado
     */
    public function remember($key, $ttl, callable $callback) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    // Prevenir clonagem
    private function __clone() {}
    
    // Prevenir unserialize
    public function __wakeup() {
        throw new Exception("Não é possível unserialize singleton");
    }
}
