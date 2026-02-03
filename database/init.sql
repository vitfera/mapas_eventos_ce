-- Criação das tabelas do Mapa Cultural do Ceará

-- Tabela de eventos culturais
CREATE TABLE IF NOT EXISTS eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    external_id INT UNIQUE COMMENT 'ID do evento na API do Mapa Cultural',
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    local VARCHAR(255),
    local_nome VARCHAR(255),
    municipio VARCHAR(100),
    cep VARCHAR(10),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    telefone VARCHAR(50),
    email VARCHAR(100),
    site VARCHAR(255),
    acessibilidade BOOLEAN DEFAULT 0,
    classificacao_etaria VARCHAR(50),
    tags TEXT COMMENT 'Tags do evento separadas por vírgula',
    data_inicio DATETIME,
    data_fim DATETIME,
    hora_inicio TIME,
    hora_fim TIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_municipio (municipio),
    INDEX idx_external_id (external_id),
    INDEX idx_data_inicio (data_inicio),
    INDEX idx_data_fim (data_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de linguagens culturais
CREATE TABLE IF NOT EXISTS linguagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir linguagens padrão
INSERT INTO linguagens (nome, descricao) VALUES
('Artes Visuais', 'Exposições, mostras, instalações'),
('Teatro', 'Peças, performances, dramaturgia'),
('Música', 'Shows, concertos, recitais'),
('Dança', 'Espetáculos, apresentações de dança'),
('Literatura', 'Lançamentos, saraus, leituras'),
('Cinema', 'Exibições, mostras, festivais'),
('Fotografia', 'Exposições fotográficas'),
('Artesanato', 'Feiras, oficinas, exposições'),
('Cultura Popular', 'Festas, celebrações tradicionais'),
('Patrimônio Cultural', 'Eventos de valorização do patrimônio')
ON DUPLICATE KEY UPDATE nome=nome;

-- Tabela de relacionamento entre eventos e linguagens
CREATE TABLE IF NOT EXISTS eventos_linguagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    linguagem_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    FOREIGN KEY (linguagem_id) REFERENCES linguagens(id) ON DELETE CASCADE,
    UNIQUE KEY unique_evento_linguagem (evento_id, linguagem_id),
    INDEX idx_evento (evento_id),
    INDEX idx_linguagem (linguagem_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de selos
CREATE TABLE IF NOT EXISTS selos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    external_id INT UNIQUE COMMENT 'ID do selo na API do Mapa Cultural',
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nome (nome),
    INDEX idx_external_id (external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de relacionamento entre eventos e selos
CREATE TABLE IF NOT EXISTS eventos_selos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    selo_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    FOREIGN KEY (selo_id) REFERENCES selos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_evento_selo (evento_id, selo_id),
    INDEX idx_evento (evento_id),
    INDEX idx_selo (selo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de sincronização (log da API)
CREATE TABLE IF NOT EXISTS sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    total_eventos INT DEFAULT 0,
    eventos_novos INT DEFAULT 0,
    eventos_atualizados INT DEFAULT 0,
    eventos_erro INT DEFAULT 0,
    status ENUM('iniciado', 'em_progresso', 'concluido', 'erro') DEFAULT 'iniciado',
    mensagem TEXT,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de municípios do Ceará
CREATE TABLE IF NOT EXISTS municipios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    codigo_ibge VARCHAR(7),
    populacao INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir principais municípios
INSERT INTO municipios (nome, codigo_ibge) VALUES
('Fortaleza', '2304400'),
('Juazeiro do Norte', '2307304'),
('Sobral', '2313500'),
('Crato', '2304103')
ON DUPLICATE KEY UPDATE nome=nome;

-- View para estatísticas rápidas
CREATE OR REPLACE VIEW vw_estatisticas AS
SELECT 
    COUNT(DISTINCT e.id) as total_eventos,
    COUNT(DISTINCT e.municipio) as total_municipios,
    COUNT(DISTINCT el.linguagem_id) as total_linguagens,
    SUM(CASE WHEN e.acessibilidade = TRUE THEN 1 ELSE 0 END) as total_acessibilidade,
    SUM(CASE WHEN e.data_inicio >= NOW() THEN 1 ELSE 0 END) as eventos_futuros,
    SUM(CASE WHEN e.data_fim < NOW() THEN 1 ELSE 0 END) as eventos_passados
FROM eventos e
LEFT JOIN eventos_linguagens el ON e.id = el.evento_id;

-- View para distribuição por linguagem
CREATE OR REPLACE VIEW vw_distribuicao_linguagens AS
SELECT 
    l.nome as linguagem,
    COUNT(el.evento_id) as total
FROM linguagens l
LEFT JOIN eventos_linguagens el ON l.id = el.linguagem_id
GROUP BY l.id, l.nome
ORDER BY total DESC;

-- View para distribuição por município
CREATE OR REPLACE VIEW vw_distribuicao_municipios AS
SELECT 
    COALESCE(e.municipio, 'Não informado') as municipio,
    COUNT(*) as total
FROM eventos e
GROUP BY e.municipio
ORDER BY total DESC;
