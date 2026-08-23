-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Tempo de geração: 23/08/2026 às 10:02
-- Versão do servidor: 11.4.12-MariaDB-deb12
-- Versão do PHP: 8.1.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `hclod_eng`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelos_casas`
--

CREATE TABLE `modelos_casas` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Despejando dados para a tabela `modelos_casas`
--

INSERT INTO `modelos_casas` (`id`, `nome`, `descricao`, `criado_em`) VALUES
(1, 'Modelo Padrão PFUI Caixa Econômica', 'Cronograma físico-financeiro de 20 etapas padrão Caixa', '2026-08-22 22:40:49');

-- --------------------------------------------------------

--
-- Estrutura para tabela `modelos_etapas`
--

CREATE TABLE `modelos_etapas` (
  `id` int(11) NOT NULL,
  `modelo_id` int(11) NOT NULL,
  `ordem` int(11) NOT NULL,
  `nome_etapa` varchar(255) NOT NULL,
  `peso_percentual` decimal(5,2) NOT NULL DEFAULT 0.00,
  `valor_estimado` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Despejando dados para a tabela `modelos_etapas`
--

INSERT INTO `modelos_etapas` (`id`, `modelo_id`, `ordem`, `nome_etapa`, `peso_percentual`, `valor_estimado`) VALUES
(1, 1, 1, 'Barracão + lig. provisórias (água/luz) + projetos/aprovs.', 3.92, 0.00),
(2, 1, 2, 'Infraestrutura (estacas, brocas, baldrames, sapatas)', 6.12, 0.00),
(3, 1, 3, 'Supraestrutura (Vigas, pilares, cintas, escadas)', 12.65, 0.00),
(4, 1, 4, 'Paredes e Painéis', 10.20, 0.00),
(5, 1, 5, 'Esquadrias', 6.45, 0.00),
(6, 1, 6, 'Vidros e Plásticos', 2.37, 0.00),
(7, 1, 7, 'Coberturas (estrutura e telhas)', 7.76, 0.00),
(8, 1, 8, 'Impermeabilizações', 1.88, 0.00),
(9, 1, 9, 'Revestimentos Internos', 8.82, 0.00),
(10, 1, 10, 'Forros', 0.00, 0.00),
(11, 1, 11, 'Revestimentos Externos', 4.90, 0.00),
(12, 1, 12, 'Pinturas', 6.12, 0.00),
(13, 1, 13, 'Pisos', 9.06, 0.00),
(14, 1, 14, 'Acabamentos (soleiras, rodapés, peitoril etc.)', 1.22, 0.00),
(15, 1, 15, 'Instalações Elétricas e Telefônicas', 4.49, 0.00),
(16, 1, 16, 'Instalações Hidráulicas', 3.92, 0.00),
(17, 1, 17, 'Instalações: Esgoto e Águas Pluviais', 4.00, 0.00),
(18, 1, 18, 'Louças e Metais', 4.73, 0.00),
(19, 1, 19, 'Complementos (limpeza final e calafete)', 1.39, 0.00),
(20, 1, 20, 'Outros (discriminar em Serviços Adicionais)', 0.00, 0.00);

-- --------------------------------------------------------

--
-- Estrutura para tabela `obras`
--

CREATE TABLE `obras` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `modelo_id` int(11) DEFAULT NULL,
  `endereco_obra` varchar(255) NOT NULL,
  `valor_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `valor_terreno` decimal(12,2) DEFAULT 0.00,
  `valor_subsidio` decimal(12,2) DEFAULT 0.00,
  `valor_entrada` decimal(12,2) DEFAULT 0.00,
  `sobra_construcao` decimal(12,2) DEFAULT 0.00,
  `progresso_total` decimal(5,2) DEFAULT 0.00,
  `arquivo_projeto` varchar(255) DEFAULT NULL,
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Despejando dados para a tabela `obras`
--

INSERT INTO `obras` (`id`, `cliente_id`, `modelo_id`, `endereco_obra`, `valor_total`, `valor_terreno`, `valor_subsidio`, `valor_entrada`, `sobra_construcao`, `progresso_total`, `arquivo_projeto`, `criado_em`) VALUES
(1, 2, 1, 'Av. Antonio Campopiano, 841 - Res. Nova Rocca 2 - Guariba - SP, 14840-400', 168000.00, 70000.00, 0.00, 0.00, 98000.00, 6.37, NULL, '2026-08-22 22:45:06');

-- --------------------------------------------------------

--
-- Estrutura para tabela `obra_documentos`
--

CREATE TABLE `obra_documentos` (
  `id` int(11) NOT NULL,
  `obra_id` int(11) NOT NULL,
  `nome_documento` varchar(255) NOT NULL,
  `caminho_arquivo` varchar(255) NOT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `obra_etapas`
--

CREATE TABLE `obra_etapas` (
  `id` int(11) NOT NULL,
  `obra_id` int(11) NOT NULL,
  `ordem` int(11) NOT NULL,
  `nome_etapa` varchar(255) NOT NULL,
  `peso_percentual` decimal(5,2) NOT NULL DEFAULT 0.00,
  `valor_etapa` decimal(12,2) NOT NULL DEFAULT 0.00,
  `progresso` decimal(5,2) NOT NULL DEFAULT 0.00,
  `concluido` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Despejando dados para a tabela `obra_etapas`
--

INSERT INTO `obra_etapas` (`id`, `obra_id`, `ordem`, `nome_etapa`, `peso_percentual`, `valor_etapa`, `progresso`, `concluido`) VALUES
(1, 1, 1, 'Barracão + lig. provisórias (água/luz) + projetos/aprovs.', 3.92, 3841.60, 100.00, 1),
(2, 1, 2, 'Infraestrutura (estacas, brocas, baldrames, sapatas)', 6.12, 5997.60, 40.00, 0),
(3, 1, 3, 'Supraestrutura (Vigas, pilares, cintas, escadas)', 12.65, 12397.00, 0.00, 0),
(4, 1, 4, 'Paredes e Painéis', 10.20, 9996.00, 0.00, 0),
(5, 1, 5, 'Esquadrias', 6.45, 6321.00, 0.00, 0),
(6, 1, 6, 'Vidros e Plásticos', 2.37, 2322.60, 0.00, 0),
(7, 1, 7, 'Coberturas (estrutura e telhas)', 7.76, 7604.80, 0.00, 0),
(8, 1, 8, 'Impermeabilizações', 1.88, 1842.40, 0.00, 0),
(9, 1, 9, 'Revestimentos Internos', 8.82, 8643.60, 0.00, 0),
(10, 1, 10, 'Forros', 0.00, 0.00, 0.00, 0),
(11, 1, 11, 'Revestimentos Externos', 4.90, 4802.00, 0.00, 0),
(12, 1, 12, 'Pinturas', 6.12, 5997.60, 0.00, 0),
(13, 1, 13, 'Pisos', 9.06, 8878.80, 0.00, 0),
(14, 1, 14, 'Acabamentos (soleiras, rodapés, peitoril etc.)', 1.22, 1195.60, 0.00, 0),
(15, 1, 15, 'Instalações Elétricas e Telefônicas', 4.49, 4400.20, 0.00, 0),
(16, 1, 16, 'Instalações Hidráulicas', 3.92, 3841.60, 0.00, 0),
(17, 1, 17, 'Instalações: Esgoto e Águas Pluviais', 4.00, 3920.00, 0.00, 0),
(18, 1, 18, 'Louças e Metais', 4.73, 4635.40, 0.00, 0),
(19, 1, 19, 'Complementos (limpeza final e calafete)', 1.39, 1362.20, 0.00, 0),
(20, 1, 20, 'Outros (discriminar em Serviços Adicionais)', 0.00, 0.00, 0.00, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `obra_fotos`
--

CREATE TABLE `obra_fotos` (
  `id` int(11) NOT NULL,
  `obra_id` int(11) NOT NULL,
  `etapa_id` int(11) DEFAULT NULL,
  `caminho_foto` varchar(255) NOT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `obra_taxas`
--

CREATE TABLE `obra_taxas` (
  `id` int(11) NOT NULL,
  `obra_id` int(11) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` decimal(12,2) DEFAULT 0.00,
  `pago` tinyint(1) DEFAULT 0,
  `arquivo_comprovante` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `cpf` varchar(20) DEFAULT NULL,
  `usuario` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('admin','cliente') NOT NULL DEFAULT 'cliente',
  `criado_em` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id`, `nome`, `cpf`, `usuario`, `email`, `telefone`, `endereco`, `senha`, `tipo`, `criado_em`) VALUES
(1, 'Administrador', NULL, 'admin', NULL, NULL, NULL, '$2y$10$upZY8dEU5XfkhaIRC4/7QuQLmZYhgofwX6pA2wkUTR1KFPxuEpzyi', 'admin', '2026-08-22 20:15:09'),
(2, 'ADRIANA CARDOSO DA VEIGA', '393.902.728-66', NULL, NULL, NULL, NULL, '$2y$10$YPXCZyFPZJ3ZDwFWVnhq0.UxUriQhVHngbS05pJlR3BC2GRp3gDva', 'cliente', '2026-08-22 22:42:10');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `modelos_casas`
--
ALTER TABLE `modelos_casas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `modelos_etapas`
--
ALTER TABLE `modelos_etapas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `modelo_id` (`modelo_id`);

--
-- Índices de tabela `obras`
--
ALTER TABLE `obras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `modelo_id` (`modelo_id`);

--
-- Índices de tabela `obra_documentos`
--
ALTER TABLE `obra_documentos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `obra_id` (`obra_id`);

--
-- Índices de tabela `obra_etapas`
--
ALTER TABLE `obra_etapas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `obra_id` (`obra_id`);

--
-- Índices de tabela `obra_fotos`
--
ALTER TABLE `obra_fotos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `obra_id` (`obra_id`);

--
-- Índices de tabela `obra_taxas`
--
ALTER TABLE `obra_taxas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `obra_id` (`obra_id`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `modelos_casas`
--
ALTER TABLE `modelos_casas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `modelos_etapas`
--
ALTER TABLE `modelos_etapas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de tabela `obras`
--
ALTER TABLE `obras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `obra_documentos`
--
ALTER TABLE `obra_documentos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `obra_etapas`
--
ALTER TABLE `obra_etapas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de tabela `obra_fotos`
--
ALTER TABLE `obra_fotos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `obra_taxas`
--
ALTER TABLE `obra_taxas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `modelos_etapas`
--
ALTER TABLE `modelos_etapas`
  ADD CONSTRAINT `modelos_etapas_ibfk_1` FOREIGN KEY (`modelo_id`) REFERENCES `modelos_casas` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `obras`
--
ALTER TABLE `obras`
  ADD CONSTRAINT `obras_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `obras_ibfk_2` FOREIGN KEY (`modelo_id`) REFERENCES `modelos_casas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `obra_documentos`
--
ALTER TABLE `obra_documentos`
  ADD CONSTRAINT `obra_documentos_ibfk_1` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `obra_etapas`
--
ALTER TABLE `obra_etapas`
  ADD CONSTRAINT `obra_etapas_ibfk_1` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `obra_fotos`
--
ALTER TABLE `obra_fotos`
  ADD CONSTRAINT `obra_fotos_ibfk_1` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `obra_taxas`
--
ALTER TABLE `obra_taxas`
  ADD CONSTRAINT `obra_taxas_ibfk_1` FOREIGN KEY (`obra_id`) REFERENCES `obras` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
