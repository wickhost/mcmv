-- Criação do banco de dados (opcional)
CREATE DATABASE IF NOT EXISTS `gestao_obras` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gestao_obras`;

-- 1. Tabela de Usuários (Administradores/Técnicos e Clientes)
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome` VARCHAR(150) NOT NULL,
  `cpf` VARCHAR(14) NOT NULL UNIQUE,
  `telefone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `senha` VARCHAR(255) NOT NULL,
  `tipo` ENUM('admin', 'cliente') NOT NULL DEFAULT 'cliente',
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabela de Modelos / Modelos Padrão de Etapas
CREATE TABLE IF NOT EXISTS `etapas_modelo` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nome_modelo` VARCHAR(100) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabela de Etapas dos Modelos Padrão
CREATE TABLE IF NOT EXISTS `etapas_modelo_itens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `modelo_id` INT NOT NULL,
  `ordem` INT NOT NULL DEFAULT 1,
  `nome_etapa` VARCHAR(150) NOT NULL,
  `peso_percentual` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`modelo_id`) REFERENCES `etapas_modelo`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabela Principal de Obras
CREATE TABLE IF NOT EXISTS `obras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` INT NOT NULL,
  `endereco_obra` VARCHAR(255) NOT NULL,
  `valor_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `valor_terreno` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `valor_subsidio` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `valor_entrada` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sobra_construcao` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `progresso_total` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `arquivo_projeto` VARCHAR(255) DEFAULT NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`cliente_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabela das Etapas Específicas de Cada Obra
CREATE TABLE IF NOT EXISTS `obra_etapas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `obra_id` INT NOT NULL,
  `ordem` INT NOT NULL DEFAULT 1,
  `nome_etapa` VARCHAR(150) NOT NULL,
  `peso_percentual` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `valor_etapa` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `progresso` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `concluido` TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (`obra_id`) REFERENCES `obras`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tabela de Fotos do Avanço da Obra
CREATE TABLE IF NOT EXISTS `obra_fotos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `obra_id` INT NOT NULL,
  `caminho_foto` VARCHAR(255) NOT NULL,
  `descricao` VARCHAR(255) DEFAULT NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`obra_id`) REFERENCES `obras`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Tabela de Comprovantes e Documentos
CREATE TABLE IF NOT EXISTS `obra_documentos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `obra_id` INT NOT NULL,
  `nome_documento` VARCHAR(150) NOT NULL,
  `caminho_arquivo` VARCHAR(255) NOT NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`obra_id`) REFERENCES `obras`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserção do Primeiro Usuário Administrador (Padrão)
-- CPF: 00000000000 | Senha: admin
INSERT INTO `usuarios` (`nome`, `cpf`, `telefone`, `email`, `senha`, `tipo`) 
VALUES ('Administrador Master', '00000000000', '(00) 00000-0000', 'admin@sistema.com', '$2y$10$w4rN3a65i0/k2O4uK5a3A.A6bQ682c3R6p332.yGv0O2d1r4e3q1C', 'admin')
ON DUPLICATE KEY UPDATE `id`=`id`;