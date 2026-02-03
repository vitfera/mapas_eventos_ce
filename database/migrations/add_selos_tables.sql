-- Migração: Adicionar tabelas de selos
-- Data: 2026-02-03

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
