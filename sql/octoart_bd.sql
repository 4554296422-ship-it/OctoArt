-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 19/10/2026 às 03:06
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `octoart_bd`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes`
--

CREATE TABLE `avaliacoes` (
  `id_avaliacao` int(11) NOT NULL,
  `id_pedido` int(11) DEFAULT NULL,
  `id_avaliador` int(11) NOT NULL,
  `id_avaliado` int(11) NOT NULL,
  `nota` int(11) NOT NULL,
  `comentario` text DEFAULT NULL,
  `data_avaliacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avisos`
--

CREATE TABLE `avisos` (
  `id_aviso` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `mensagem` text NOT NULL,
  `id_admin` int(11) NOT NULL,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `ativo` tinyint(4) DEFAULT 1,
  `destino` enum('artistas','clientes','ambos') NOT NULL DEFAULT 'ambos'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nome`, `descricao`) VALUES
(1, 'Ilustração', 'Desenhos autorais, personagens e capas'),
(2, 'Design Gráfico', 'Identidade visual, branding e peças gráficas'),
(3, 'Fotografia', 'Ensaios, eventos e fotografia de produto'),
(4, 'Web Design e UI/UX', 'Design de interfaces e experiência do usuário'),
(5, 'Modelagem 3D', 'Modelos para impressão 3D, jogos e produtos');

-- --------------------------------------------------------

--
-- Estrutura para tabela `comentarios_projeto`
--

CREATE TABLE `comentarios_projeto` (
  `id_comentario` int(11) NOT NULL,
  `id_projeto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `comentario` text NOT NULL,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `curtidas_projeto`
--

CREATE TABLE `curtidas_projeto` (
  `id_curtida` int(11) NOT NULL,
  `id_projeto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `denuncias`
--

CREATE TABLE `denuncias` (
  `id_denuncia` int(11) NOT NULL,
  `id_projeto` int(11) NOT NULL,
  `id_denunciante` int(11) NOT NULL,
  `motivo` varchar(40) NOT NULL,
  `detalhes` text DEFAULT NULL,
  `status` enum('pendente','procedente','improcedente') NOT NULL DEFAULT 'pendente',
  `id_admin` int(11) DEFAULT NULL,
  `resposta_admin` text DEFAULT NULL,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `data_resolucao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `favoritos`
--

CREATE TABLE `favoritos` (
  `id_favorito` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_artista` int(11) NOT NULL,
  `data_favorito` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `imagens_projeto`
--

CREATE TABLE `imagens_projeto` (
  `id_imagem` int(11) NOT NULL,
  `id_projeto` int(11) NOT NULL,
  `storage_key` varchar(255) NOT NULL,
  `ordem` int(11) DEFAULT 0,
  `data_upload` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `imagens_projeto`
--

INSERT INTO `imagens_projeto` (`id_imagem`, `id_projeto`, `storage_key`, `ordem`, `data_upload`) VALUES
(13, 22, 'uploads/img_7f97e84a9fbb6af5.jpg', 0, '2026-10-06 08:35:16'),
(14, 22, 'uploads/img_6feb258b3183375f.jpg', 1, '2026-10-06 08:35:16'),
(15, 22, 'uploads/img_1b4a11fb097cb4b5.jpg', 2, '2026-10-06 08:35:16'),
(16, 22, 'uploads/img_c5f48154cb36773d.jpg', 3, '2026-10-06 08:35:16'),
(17, 22, 'uploads/img_52dbbbf249302617.jpg', 4, '2026-10-06 08:35:16'),
(18, 22, 'uploads/img_bfa4c396b74619cc.jpg', 5, '2026-10-06 08:35:16'),
(19, 22, 'uploads/img_2d041ee2c50d4716.jpg', 6, '2026-10-06 08:35:16'),
(20, 22, 'uploads/img_96b7c147858098ac.jpg', 7, '2026-10-06 08:35:16'),
(21, 22, 'uploads/img_6c9791859395ee19.jpg', 8, '2026-10-06 08:35:16'),
(22, 23, 'uploads/img_f57ef9124dc7b058.png', 0, '2026-10-06 08:43:43'),
(23, 23, 'uploads/img_35af7f4a413d581d.png', 1, '2026-10-06 08:43:43'),
(24, 23, 'uploads/img_ce9d1e3f53b998b7.png', 2, '2026-10-06 08:43:43'),
(25, 23, 'uploads/img_5738d5bdcc261bc7.png', 3, '2026-10-06 08:43:43'),
(26, 23, 'uploads/img_ee83122ffe30403c.png', 4, '2026-10-06 08:43:43'),
(27, 23, 'uploads/img_fb3997ae45fdf0ed.png', 5, '2026-10-06 08:43:43'),
(28, 23, 'uploads/img_e38ae950d7956a4b.png', 6, '2026-10-06 08:43:43'),
(29, 23, 'uploads/img_e9a29dd2df77c57a.png', 7, '2026-10-06 08:43:43'),
(30, 23, 'uploads/img_ec2edc3cbdbed79b.png', 8, '2026-10-06 08:43:43'),
(31, 23, 'uploads/img_c366cec723da7da3.png', 9, '2026-10-06 08:43:43'),
(32, 23, 'uploads/img_2c1dac179a235c1f.png', 10, '2026-10-06 08:43:43'),
(33, 23, 'uploads/img_c41331706465b5ff.png', 11, '2026-10-06 08:43:43'),
(34, 23, 'uploads/img_315cb0d62a555a6b.png', 12, '2026-10-06 08:43:43'),
(35, 23, 'uploads/img_4426577f5b7030a3.png', 13, '2026-10-06 08:43:43'),
(36, 23, 'uploads/img_e80a89a195147d5f.png', 14, '2026-10-06 08:43:43'),
(37, 23, 'uploads/img_3e4d16bbaa0c6704.png', 15, '2026-10-06 08:43:43'),
(38, 23, 'uploads/img_083fff74710c8349.png', 16, '2026-10-06 08:43:43'),
(39, 23, 'uploads/img_4a3ca79b94a1388f.png', 17, '2026-10-06 08:43:43'),
(40, 23, 'uploads/img_2d6cc6de02deb635.png', 18, '2026-10-06 08:43:43'),
(41, 23, 'uploads/img_8f306c274af00a8a.png', 19, '2026-10-06 08:43:43'),
(42, 23, 'uploads/img_6fe9ebdf99be15ff.png', 20, '2026-10-06 08:43:43'),
(43, 23, 'uploads/img_8eea2a5739ba3077.png', 21, '2026-10-06 08:43:43'),
(62, 25, 'uploads/img_f19c74558dc36a12.png', 0, '2026-10-06 09:09:06'),
(63, 25, 'uploads/img_a2f86200142daef9.png', 1, '2026-10-06 09:09:06'),
(64, 25, 'uploads/img_2da577acf08ba8de.png', 2, '2026-10-06 09:09:06'),
(65, 25, 'uploads/img_bdbba6b44e966667.png', 3, '2026-10-06 09:09:06'),
(66, 25, 'uploads/img_2d802029f3aac5c1.png', 4, '2026-10-06 09:09:06'),
(67, 25, 'uploads/img_d345e5ee61dfc1bd.png', 5, '2026-10-06 09:09:06'),
(68, 25, 'uploads/img_da14a2ea5e348f8d.png', 6, '2026-10-06 09:09:06'),
(69, 25, 'uploads/img_afb1b2eb1edbaa7d.png', 7, '2026-10-06 09:09:06'),
(70, 25, 'uploads/img_b3df26b4625dc1f5.png', 8, '2026-10-06 09:09:06'),
(71, 25, 'uploads/img_16bd8e4de723219c.png', 9, '2026-10-06 09:09:06'),
(72, 25, 'uploads/img_61e194c050e98493.png', 10, '2026-10-06 09:09:06'),
(73, 25, 'uploads/img_274534be6899044f.png', 11, '2026-10-06 09:09:06'),
(74, 25, 'uploads/img_64aad5372a6b48f7.png', 12, '2026-10-06 09:09:06'),
(75, 25, 'uploads/img_0c164ff78051e6f4.png', 13, '2026-10-06 09:09:06'),
(76, 25, 'uploads/img_a4095b7000a1ccd7.png', 14, '2026-10-06 09:09:06'),
(77, 25, 'uploads/img_35c29b5ddc2fc22e.png', 15, '2026-10-06 09:09:06'),
(78, 25, 'uploads/img_6388ee6259e63bb4.png', 16, '2026-10-06 09:09:06'),
(79, 25, 'uploads/img_c6ea3dcdeb752d66.png', 17, '2026-10-06 09:09:06'),
(80, 25, 'uploads/img_6d41847f4d3b842b.png', 18, '2026-10-06 09:09:06'),
(81, 25, 'uploads/img_216b0ef6bae9c6b4.png', 19, '2026-10-06 09:09:06'),
(82, 24, 'uploads/img_3a7317f50ed77a58.png', 0, '2026-10-06 09:09:21'),
(83, 24, 'uploads/img_4ccf82e501423cd4.png', 1, '2026-10-06 09:09:21'),
(84, 24, 'uploads/img_bae99670e76f44bb.png', 2, '2026-10-06 09:09:21'),
(85, 24, 'uploads/img_53455d452e6f5ea1.png', 3, '2026-10-06 09:09:21'),
(86, 24, 'uploads/img_cc5fe1b8a7a3aab5.png', 4, '2026-10-06 09:09:21'),
(87, 24, 'uploads/img_2b69f15db330d47b.png', 5, '2026-10-06 09:09:21'),
(88, 24, 'uploads/img_375c590794837045.png', 6, '2026-10-06 09:09:21'),
(89, 24, 'uploads/img_99dc48da421f4e7f.png', 7, '2026-10-06 09:09:21'),
(90, 24, 'uploads/img_7aef145ef4b2f0be.png', 8, '2026-10-06 09:09:21'),
(91, 24, 'uploads/img_828add743dbbdc4a.png', 9, '2026-10-06 09:09:21'),
(92, 24, 'uploads/img_3a61cbd8238609a4.png', 10, '2026-10-06 09:09:21'),
(93, 24, 'uploads/img_b558dbc443feb2eb.png', 11, '2026-10-06 09:09:21'),
(94, 24, 'uploads/img_ee43cd74679e341f.png', 12, '2026-10-06 09:09:21'),
(95, 24, 'uploads/img_45f654f23419ddeb.png', 13, '2026-10-06 09:09:21'),
(96, 24, 'uploads/img_100b2fc7672c9262.png', 14, '2026-10-06 09:09:21'),
(97, 24, 'uploads/img_098e6b8022373a4d.png', 15, '2026-10-06 09:09:21'),
(98, 24, 'uploads/img_c58faabd06825a5b.png', 16, '2026-10-06 09:09:21'),
(99, 24, 'uploads/img_9b37ec1f63f9cd1f.png', 17, '2026-10-06 09:09:21'),
(152, 26, 'uploads/img_40b35202d3ea18f1.jpg', 0, '2026-10-17 20:22:46'),
(153, 26, 'uploads/img_f9badd4e7f73e41c.jpg', 1, '2026-10-17 20:22:46'),
(154, 26, 'uploads/img_70a7f71dd409cc5c.png', 2, '2026-10-17 20:22:46'),
(155, 26, 'uploads/img_5207262444c55e63.png', 3, '2026-10-17 20:22:46'),
(156, 26, 'uploads/img_735e9bb85820ce01.png', 4, '2026-10-17 20:22:46'),
(157, 26, 'uploads/img_c0ad452660ffe8db.png', 5, '2026-10-17 20:22:46'),
(158, 26, 'uploads/img_da7d9f1c84a5e498.png', 6, '2026-10-17 20:22:46'),
(159, 26, 'uploads/img_5b0330db9db4652d.png', 7, '2026-10-17 20:22:46'),
(160, 26, 'uploads/img_3fc459597f82c479.png', 8, '2026-10-17 20:22:46'),
(161, 26, 'uploads/img_330716fe97cac7fa.png', 9, '2026-10-17 20:22:46'),
(162, 26, 'uploads/img_08c880a133a38a2f.png', 10, '2026-10-17 20:22:46'),
(163, 26, 'uploads/img_af90b236418e432e.png', 11, '2026-10-17 20:22:46'),
(164, 26, 'uploads/img_b629ce3ebc83dd8b.png', 12, '2026-10-17 20:22:46'),
(165, 26, 'uploads/img_db1316f1c057a04a.png', 13, '2026-10-17 20:22:46'),
(166, 26, 'uploads/img_e6127dded35c2aba.png', 14, '2026-10-17 20:22:46'),
(167, 26, 'uploads/img_37ad05bd9126320e.png', 15, '2026-10-17 20:22:46'),
(168, 26, 'uploads/img_92f8d3c48a181a7a.png', 16, '2026-10-17 20:22:46'),
(169, 26, 'uploads/img_98340aa402688c58.png', 17, '2026-10-17 20:22:46'),
(170, 26, 'uploads/img_2fa19f39c8042a79.png', 18, '2026-10-17 20:22:46'),
(171, 26, 'uploads/img_ba6a1cb85c5bc277.png', 19, '2026-10-17 20:22:46'),
(172, 26, 'uploads/img_f59dfc8cb6bd47cf.png', 20, '2026-10-17 20:22:46'),
(173, 26, 'uploads/img_c593196bb5a55a27.png', 21, '2026-10-17 20:22:46'),
(174, 26, 'uploads/img_788cadef8af6a132.png', 22, '2026-10-17 20:22:46'),
(175, 26, 'uploads/img_22f322267a4e7787.png', 23, '2026-10-17 20:22:46'),
(176, 26, 'uploads/img_f19b457abbebfcf6.png', 24, '2026-10-17 20:22:46'),
(177, 26, 'uploads/img_fd5a5bc291951832.png', 25, '2026-10-17 20:22:46'),
(178, 26, 'uploads/img_6dbea738d5344a94.png', 26, '2026-10-17 20:22:46'),
(179, 26, 'uploads/img_efcb68f9c891f572.png', 27, '2026-10-17 20:22:46'),
(180, 26, 'uploads/img_880bb1e6cd4e1aca.png', 28, '2026-10-17 20:22:46'),
(181, 26, 'uploads/img_aa1b9f9ae8a7c85f.png', 29, '2026-10-17 20:22:46'),
(182, 26, 'uploads/img_e475f06f1aae906e.png', 30, '2026-10-17 20:22:46'),
(183, 26, 'uploads/img_d71d2d06d5ef2073.png', 31, '2026-10-17 20:22:46'),
(184, 26, 'uploads/img_d20225209f582f2e.png', 32, '2026-10-17 20:22:46'),
(185, 26, 'uploads/img_5d8866f7cc5b2f7e.png', 33, '2026-10-17 20:22:46'),
(186, 26, 'uploads/img_4f5dfc52282e152b.png', 34, '2026-10-17 20:22:46'),
(187, 26, 'uploads/img_a1a462acbbf2f21b.png', 35, '2026-10-17 20:22:46'),
(188, 26, 'uploads/img_72b0d39cb11ab97d.png', 36, '2026-10-17 20:22:46'),
(189, 26, 'uploads/img_57abb31e5e5bcabb.png', 37, '2026-10-17 20:22:46'),
(190, 26, 'uploads/img_1b7db05cee57eb15.png', 38, '2026-10-17 20:22:46'),
(191, 26, 'uploads/img_bade77285f0b7c40.jpg', 39, '2026-10-17 20:22:46'),
(205, 27, 'uploads/img_f8e5dc4927cbc084.jpg', 0, '2026-10-17 20:27:21'),
(206, 27, 'uploads/img_22625d02754c0cf2.png', 1, '2026-10-17 20:27:21'),
(207, 27, 'uploads/img_7c3669290f0bfb7c.png', 2, '2026-10-17 20:27:21'),
(208, 27, 'uploads/img_23cc6e4e7193d0f8.png', 3, '2026-10-17 20:27:21'),
(209, 27, 'uploads/img_990a9748e87a7fb9.png', 4, '2026-10-17 20:27:21'),
(210, 27, 'uploads/img_4501fc08ccf30dc9.png', 5, '2026-10-17 20:27:21'),
(211, 27, 'uploads/img_39babc1f636db997.png', 6, '2026-10-17 20:27:21'),
(212, 27, 'uploads/img_1ef7b00b9b2a2d27.png', 7, '2026-10-17 20:27:21'),
(213, 27, 'uploads/img_54e1f07c1a62f90a.png', 8, '2026-10-17 20:27:21'),
(214, 27, 'uploads/img_ff2a06c48812971c.png', 9, '2026-10-17 20:27:21'),
(215, 27, 'uploads/img_934e9231e4bcdeee.png', 10, '2026-10-17 20:27:21'),
(216, 27, 'uploads/img_96c186a713860bcf.jpg', 11, '2026-10-17 20:27:21'),
(217, 28, 'uploads/img_31e9e0f42d0232d6.jpg', 0, '2026-10-17 20:27:32'),
(218, 28, 'uploads/img_246d51907920d27f.jpg', 1, '2026-10-17 20:27:32'),
(219, 28, 'uploads/img_0b58af2e1755e2ed.png', 2, '2026-10-17 20:27:32'),
(220, 28, 'uploads/img_0eeb3fea68e10907.png', 3, '2026-10-17 20:27:32'),
(221, 28, 'uploads/img_61e4441996203354.png', 4, '2026-10-17 20:27:32'),
(222, 28, 'uploads/img_c39fe7522892da56.png', 5, '2026-10-17 20:27:32'),
(223, 28, 'uploads/img_df59766b4c0131a7.png', 6, '2026-10-17 20:27:32'),
(224, 28, 'uploads/img_60f517c8ded44d81.png', 7, '2026-10-17 20:27:32'),
(225, 28, 'uploads/img_1704c09ab0b52b23.png', 8, '2026-10-17 20:27:32'),
(226, 28, 'uploads/img_721de4284aba84d4.png', 9, '2026-10-17 20:27:32'),
(227, 28, 'uploads/img_9c56cc8abb9866a0.png', 10, '2026-10-17 20:27:32'),
(228, 28, 'uploads/img_30c092badd9762e0.png', 11, '2026-10-17 20:27:32'),
(229, 28, 'uploads/img_40128dccdb811f56.jpg', 12, '2026-10-17 20:27:32'),
(230, 29, 'uploads/img_b86fa6b5eea46c2c.jpg', 0, '2026-10-17 21:13:51'),
(231, 29, 'uploads/img_4b476af45d3771b0.jpg', 1, '2026-10-17 21:13:51'),
(232, 29, 'uploads/img_2a529c4c5f9e4556.jpg', 2, '2026-10-17 21:13:51'),
(233, 29, 'uploads/img_058620a9cd0cb58f.jpg', 3, '2026-10-17 21:13:51'),
(234, 29, 'uploads/img_b24f7a9553abccfe.jpg', 4, '2026-10-17 21:13:51'),
(235, 29, 'uploads/img_599729b5b067eaa9.jpg', 5, '2026-10-17 21:13:51'),
(236, 29, 'uploads/img_79b27668e4f80539.jpg', 6, '2026-10-17 21:13:51'),
(237, 29, 'uploads/img_6be1c33f89dc39ff.jpg', 7, '2026-10-17 21:13:51'),
(238, 29, 'uploads/img_c78dc4744a61a26f.jpg', 8, '2026-10-17 21:13:51'),
(239, 29, 'uploads/img_c3e2bcc65100eae8.jpg', 9, '2026-10-17 21:13:51'),
(240, 29, 'uploads/img_ffc8381619579ace.jpg', 10, '2026-10-17 21:13:51'),
(241, 29, 'uploads/img_9d5e6bca6d87c186.jpg', 11, '2026-10-17 21:13:51'),
(242, 29, 'uploads/img_56bc5f3f437d8421.jpg', 12, '2026-10-17 21:13:51'),
(243, 29, 'uploads/img_96184a37abaf105a.jpg', 13, '2026-10-17 21:13:51'),
(244, 29, 'uploads/img_6275a00a3a775e93.jpg', 14, '2026-10-17 21:13:51'),
(245, 29, 'uploads/img_d7f415ee716ee3de.jpg', 15, '2026-10-17 21:13:51'),
(246, 29, 'uploads/img_5e0f9dab047d6c68.jpg', 16, '2026-10-17 21:13:51'),
(247, 29, 'uploads/img_dfd717acd4168571.jpg', 17, '2026-10-17 21:13:51'),
(248, 29, 'uploads/img_7e37541ed0012005.jpg', 18, '2026-10-17 21:13:51'),
(249, 29, 'uploads/img_c30224966399802a.jpg', 19, '2026-10-17 21:13:51'),
(250, 29, 'uploads/img_6c91a71801a1f2ea.jpg', 20, '2026-10-17 21:13:51'),
(251, 29, 'uploads/img_36cf04106da72c6e.jpg', 21, '2026-10-17 21:13:51'),
(252, 29, 'uploads/img_fe9844a148c4ff5c.jpg', 22, '2026-10-17 21:13:51'),
(253, 29, 'uploads/img_ff9f43a4c7294b0a.jpg', 23, '2026-10-17 21:13:51'),
(254, 29, 'uploads/img_c3d69023e52d4c99.jpg', 24, '2026-10-17 21:13:51'),
(255, 29, 'uploads/img_267fa0b153272966.jpg', 25, '2026-10-17 21:13:51'),
(256, 29, 'uploads/img_77d352b5259bce7f.jpg', 26, '2026-10-17 21:13:51'),
(257, 29, 'uploads/img_ded9281049eaabc9.jpg', 27, '2026-10-17 21:13:51'),
(258, 29, 'uploads/img_2206138da0d3932f.jpg', 28, '2026-10-17 21:13:51'),
(259, 29, 'uploads/img_a9c596dfeb7f8fd5.jpg', 29, '2026-10-17 21:13:51'),
(260, 29, 'uploads/img_1317ed3d61212653.jpg', 30, '2026-10-17 21:13:51'),
(261, 29, 'uploads/img_d1623e1e414c7e6e.jpg', 31, '2026-10-17 21:13:51'),
(262, 29, 'uploads/img_4d32ed735957e25b.jpg', 32, '2026-10-17 21:13:51'),
(263, 29, 'uploads/img_7d394c58e9190448.jpg', 33, '2026-10-17 21:13:51'),
(264, 29, 'uploads/img_b17bcc4b1a6a0106.jpg', 34, '2026-10-17 21:13:51'),
(265, 29, 'uploads/img_e0e297d98f2cefab.jpg', 35, '2026-10-17 21:13:51'),
(266, 29, 'uploads/img_b9788dcfe0d8a0d5.jpg', 36, '2026-10-17 21:13:51'),
(267, 29, 'uploads/img_93e1a1409ece67ee.jpg', 37, '2026-10-17 21:13:51'),
(268, 29, 'uploads/img_953d9a1be5b6666b.jpg', 38, '2026-10-17 21:13:51'),
(269, 29, 'uploads/img_74378f11e170503c.jpg', 39, '2026-10-17 21:13:51'),
(270, 29, 'uploads/img_c8e8b2211e8049bc.jpg', 40, '2026-10-17 21:13:51'),
(271, 29, 'uploads/img_fcf7357a9352483a.jpg', 41, '2026-10-17 21:13:51'),
(272, 29, 'uploads/img_11c51bdb78556c20.jpg', 42, '2026-10-17 21:13:51'),
(273, 30, 'uploads/img_9bf24e3b27cb7851.jpg', 0, '2026-10-17 21:22:36'),
(274, 30, 'uploads/img_0f1d74a071a87b9a.jpg', 1, '2026-10-17 21:22:36'),
(275, 30, 'uploads/img_1d1e72950783ddb2.jpg', 2, '2026-10-17 21:22:36'),
(276, 30, 'uploads/img_f2f99d2b26546320.jpg', 3, '2026-10-17 21:22:36'),
(277, 30, 'uploads/img_b3d3e3baf796a123.jpg', 4, '2026-10-17 21:22:36'),
(278, 30, 'uploads/img_7653706d5e8e5919.jpg', 5, '2026-10-17 21:22:36'),
(279, 30, 'uploads/img_dcd4fb5e0141f36a.jpg', 6, '2026-10-17 21:22:36'),
(280, 30, 'uploads/img_85b7747ee4665c4a.jpg', 7, '2026-10-17 21:22:36'),
(281, 30, 'uploads/img_954ad3a9ec2728ed.jpg', 8, '2026-10-17 21:22:36'),
(282, 30, 'uploads/img_4c9254494d2f8772.jpg', 9, '2026-10-17 21:22:36'),
(283, 30, 'uploads/img_152312c81a57948e.jpg', 10, '2026-10-17 21:22:36'),
(284, 30, 'uploads/img_58f898b9e233edeb.jpg', 11, '2026-10-17 21:22:36'),
(285, 30, 'uploads/img_3c46049267ecb94a.jpg', 12, '2026-10-17 21:22:36'),
(286, 30, 'uploads/img_40f00b70bc0e9c05.jpg', 13, '2026-10-17 21:22:36'),
(287, 30, 'uploads/img_6037957b9e69dc4d.jpg', 14, '2026-10-17 21:22:36'),
(288, 30, 'uploads/img_4970d6b2b7a4cc4f.jpg', 15, '2026-10-17 21:22:36'),
(289, 30, 'uploads/img_7764550a41ada0d9.jpg', 16, '2026-10-17 21:22:36'),
(290, 30, 'uploads/img_b5dd1223efd0d53b.jpg', 17, '2026-10-17 21:22:36');

-- --------------------------------------------------------

--
-- Estrutura para tabela `mensagens`
--

CREATE TABLE `mensagens` (
  `id_mensagem` int(11) NOT NULL,
  `id_remetente` int(11) NOT NULL,
  `id_destinatario` int(11) NOT NULL,
  `id_projeto` int(11) DEFAULT NULL,
  `mensagem` text NOT NULL,
  `data_envio` datetime DEFAULT current_timestamp(),
  `lida` tinyint(4) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `mensagens`
--

INSERT INTO `mensagens` (`id_mensagem`, `id_remetente`, `id_destinatario`, `id_projeto`, `mensagem`, `data_envio`, `lida`) VALUES
(2, 13, 12, NULL, 'OI', '2026-10-06 14:02:47', 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id_pedido` int(11) NOT NULL,
  `id_proposta` int(11) DEFAULT NULL,
  `id_cliente` int(11) NOT NULL,
  `id_artista` int(11) NOT NULL,
  `valor_total` decimal(10,2) DEFAULT NULL,
  `status` varchar(30) DEFAULT 'em_andamento',
  `data_inicio` datetime DEFAULT current_timestamp(),
  `data_entrega_real` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `perfis_artistas`
--

CREATE TABLE `perfis_artistas` (
  `id_perfil` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `bio` text DEFAULT NULL,
  `localizacao` varchar(120) DEFAULT NULL,
  `site` varchar(150) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `experiencia_anos` int(11) DEFAULT 0,
  `banner_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `perfis_artistas`
--

INSERT INTO `perfis_artistas` (`id_perfil`, `id_usuario`, `bio`, `localizacao`, `site`, `instagram`, `banner`, `experiencia_anos`, `banner_url`) VALUES
(9, 10, 'Olá me chamo Lucas, trabalho com sou designer gráfico com foco em social media e artes esportivas. Trabalho com criação de conteúdos visuais para engajamento, anúncios e identidade digital, sempre buscando unir criatividade, clareza e impacto visual.', 'Petrolândia, Brazil', 'https://twitter.com/Lucasso1022', '@Lusasso', 'uploads/img_c6466a107862aaf2.png', 0, NULL),
(10, 11, 'Designer Gráfico na LOUD Pernambuco, no Gerados pelo Amor (Paróquia da Encruzilhada) e na Pastoral da Crisma Jovem (Paróquia da Torre)', 'Recife, PE', 'https://x.com/kauanbr_pe', '@kauanbr_pe', 'uploads/img_b74bdd413d431c7d.jpg', 0, NULL),
(11, 12, 'Apaixonado por mídias e redes sociais, possuo experiência em Assessoria de Comunicação e Imprensa além de participação em eventos de ciência e tecnologia.\r\nPossuo habilidades em computação, idiomas intermediários e facilidade de comunicação e relacionamento com o público.', 'Recife, PE', 'https://x.com/dannjsll', '@dannjs', 'uploads/img_a702cd6b8411c148.jpg', 0, NULL),
(12, 13, 'Designer by @LOUDggPE, @LoudComments, @japaofc_esports\r\n and  @RedHawk_Team | “Maybe I just wasn\'t enough... I\'m sorry”', 'Criciúma, SC', 'https://x.com/Lissasmokes7_', '@lis2asmokes_', 'uploads/img_cebe98cff2fe1c6a.jpg', 0, NULL);

-- --------------------------------------------------------

--
-- Estrutura para tabela `projetos`
--

CREATE TABLE `projetos` (
  `id_projeto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `tipo` enum('portfolio','briefing') NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `conteudo` longtext DEFAULT NULL,
  `imagem_url` varchar(255) DEFAULT NULL,
  `orcamento_min` decimal(10,2) DEFAULT NULL,
  `orcamento_max` decimal(10,2) DEFAULT NULL,
  `prazo_desejado` date DEFAULT NULL,
  `status` varchar(30) DEFAULT 'aberto',
  `data_criacao` datetime DEFAULT current_timestamp(),
  `visivel` tinyint(4) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `projetos`
--

INSERT INTO `projetos` (`id_projeto`, `id_usuario`, `id_categoria`, `tipo`, `titulo`, `descricao`, `conteudo`, `imagem_url`, `orcamento_min`, `orcamento_max`, `prazo_desejado`, `status`, `data_criacao`, `visivel`) VALUES
(22, 13, 2, 'portfolio', 'EWC 2024 | LOUDggPE', 'Artes realizadas para a LOUDggPE, feitas para a equipe de Free Fire da LOUDgg, que participou da EWC', '[{\"type\":\"text\",\"content\":\"ESPORTS WORLD CUP - LOUDggPE\\n\\nEsse projeto foi realizado para a página LOUD PERNAMBUCO, uma página de fãs onde o intuito é dar mais visibilidade para a organização LOUDgg.\\nA Esports World cup é o campeonato mundial que reúne mais de 20 jogos competitivos, entre eles o Free Fire, o campeonato ocorreu na Arábia Saudita.\\n\\n[ENG-US]\\nEsse projeto foi realizado para a página LOUD PERNAMBUCO, uma página de fãs onde o intuito é dar mais visibilidade para a organização LOUDgg.\\nA Esports World cup é o campeonato mundial que reúne mais de 20 jogos competitivos, entre eles o Free Fire, o campeonato ocorreu na Arábia Saudita.\\n​​​​​​​GAMEDAY\'s\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_7f97e84a9fbb6af5.jpg\",\"uploads\\/img_6feb258b3183375f.jpg\",\"uploads\\/img_1b4a11fb097cb4b5.jpg\",\"uploads\\/img_c5f48154cb36773d.jpg\"]},{\"type\":\"text\",\"content\":\"Resumo\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_52dbbbf249302617.jpg\",\"uploads\\/img_bfa4c396b74619cc.jpg\",\"uploads\\/img_2d041ee2c50d4716.jpg\",\"uploads\\/img_96b7c147858098ac.jpg\"]},{\"type\":\"text\",\"content\":\"Campeão!\"},{\"type\":\"image\",\"url\":\"uploads\\/img_6c9791859395ee19.jpg\"},{\"type\":\"text\",\"content\":\"Obrigado!\"}]', 'uploads/img_d805040aaab41c5b.png', NULL, NULL, NULL, 'concluido', '2026-10-06 08:34:30', 1),
(23, 13, 2, 'portfolio', 'FFWSBR 2026 | LOUD CMMTS', 'Esse projeto foi realizado para a página LOUD COMMENTS, uma página de fãs onde o intuito é dar mais visibilidade para a organização LOUDgg', '[{\"type\":\"text\",\"content\":\"​​​​​​​FFWSBR 2025 - LOUD COMMENTS\\n[PT-BR]\\nEsse projeto foi realizado para a página LOUD COMMENTS, uma página de fãs onde o intuito é dar mais visibilidade para a organização LOUDgg.\\n\\nFree Fire World Series Brasil é o campeonato oficial da Garena Free Fire, nesse campeonato as 3 melhores equipes da primeira etapa (Top 1 Classificatória, Top 1 e Top 2 da Grande Final (se for o mesmo da Classificatória, o top 3 assume a vaga)) se classificam pra EWC (Esports World Cup), na Segunda Etapa, do mesmo jeito, as 3 melhores equipes se classificar para a FFWS (Free Fire World Series) campeonato mundial oficial da Garena Free Fire.\\n\\n[ENG-US]\\nThis project was created for the LOUD COMMENTS page, a fan page whose purpose is to give more visibility to the LOUDgg organization.\\n\\nFree Fire World Series Brazil is the official Garena Free Fire championship. In this championship, the 3 best teams from the first stage (Top 1 Qualifier, Top 1 and Top 2 of the Grand Final (if it is the same as the Qualifier, the top 3 takes the spot)) qualify for the EWC (Esports World Cup). In the second stage, in the same way, the 3 best teams qualify for the FFWS (Free Fire World Series), the official world championship of Garena Free Fire.\\n​​​​​​​GAMEDAY\'s\"},{\"type\":\"image\",\"url\":\"uploads\\/img_f57ef9124dc7b058.png\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_35af7f4a413d581d.png\",\"uploads\\/img_ce9d1e3f53b998b7.png\",\"uploads\\/img_5738d5bdcc261bc7.png\",\"uploads\\/img_ee83122ffe30403c.png\"]},{\"type\":\"text\",\"content\":\"BOOYAH\'s\"},{\"type\":\"image\",\"url\":\"uploads\\/img_fb3997ae45fdf0ed.png\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_e38ae950d7956a4b.png\",\"uploads\\/img_e9a29dd2df77c57a.png\",\"uploads\\/img_ec2edc3cbdbed79b.png\",\"uploads\\/img_c366cec723da7da3.png\"]},{\"type\":\"text\",\"content\":\"COMEÇA AGORA\"},{\"type\":\"image\",\"url\":\"uploads\\/img_2c1dac179a235c1f.png\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_c41331706465b5ff.png\",\"uploads\\/img_315cb0d62a555a6b.png\",\"uploads\\/img_4426577f5b7030a3.png\",\"uploads\\/img_e80a89a195147d5f.png\"]},{\"type\":\"text\",\"content\":\"MVP\"},{\"type\":\"image\",\"url\":\"uploads\\/img_3e4d16bbaa0c6704.png\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_083fff74710c8349.png\",\"uploads\\/img_4a3ca79b94a1388f.png\",\"uploads\\/img_2d6cc6de02deb635.png\",\"uploads\\/img_8f306c274af00a8a.png\"]},{\"type\":\"text\",\"content\":\"FINAL\"},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_6fe9ebdf99be15ff.png\",\"uploads\\/img_8eea2a5739ba3077.png\"]}]', 'uploads/img_b81372f7c12d05ce.png', NULL, NULL, NULL, 'concluido', '2026-10-06 08:43:43', 1),
(24, 13, 2, 'portfolio', 'COPA F.F | LOUDggPE', 'Esse projeto foi realizado para a página LOUD PERNAMBUCO, uma página de fãs onde o intuito é dar mais visibilidade para a organização LOUDgg.', '[{\"type\":\"text\",\"content\":\"FFWSBR 2025 - LOUDggPE\\n\\n[PT-BR]\\nEsse projeto foi realizado para a página LOUD PERNAMBUCO, uma página de fãs onde o intuito é dar mais visibilidade para a organização LOUDgg.\\n\\nCopa Free Fire é um campeonato oficial do jogo Garena Free Fire, ele é meio que uma Pré temporada para os times que jogam o FFWSBR, principal campeonato oficial de Free Fire.\\n\\n[ENG-US]\\nThis project was created for the LOUD PERNAMBUCO page, a fan page whose purpose is to give more visibility to the LOUDgg organization.\\n\\nThe Free Fire Cup is an official tournament for the game Garena Free Fire; it serves somewhat as a preseason for teams competing in the FFWSBR, the game\'s premier official championship.\\n\\n​​​​​​​GAMEDAY\'s\"},{\"type\":\"image\",\"url\":\"uploads\\/img_3a7317f50ed77a58.png\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_4ccf82e501423cd4.png\",\"uploads\\/img_bae99670e76f44bb.png\",\"uploads\\/img_53455d452e6f5ea1.png\",\"uploads\\/img_cc5fe1b8a7a3aab5.png\"]},{\"type\":\"text\",\"content\":\"BOOYAAH\'s\"},{\"type\":\"image\",\"url\":\"uploads\\/img_2b69f15db330d47b.png\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_375c590794837045.png\",\"uploads\\/img_99dc48da421f4e7f.png\",\"uploads\\/img_7aef145ef4b2f0be.png\",\"uploads\\/img_828add743dbbdc4a.png\"]},{\"type\":\"text\",\"content\":\"ESPECIAIS\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_3a61cbd8238609a4.png\",\"uploads\\/img_b558dbc443feb2eb.png\",\"uploads\\/img_ee43cd74679e341f.png\",\"uploads\\/img_45f654f23419ddeb.png\"]},{\"type\":\"text\",\"content\":\"FINAL\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_100b2fc7672c9262.png\",\"uploads\\/img_098e6b8022373a4d.png\",\"uploads\\/img_c58faabd06825a5b.png\",\"uploads\\/img_9b37ec1f63f9cd1f.png\"]}]', 'uploads/img_448bbd41918f889a.png', NULL, NULL, NULL, 'concluido', '2026-10-06 08:57:06', 1),
(25, 13, 2, 'portfolio', 'COPA F.F | LOUD CMMTS', 'Esse projeto foi realizado para a página LOUD COMMENTS, uma página de fãs onde o intuito é dar mais visibilidade para a organização LOUDgg.', '[{\"type\":\"text\",\"content\":\"FFWSBR 2025 - LOUD COMMENTS\\n[PT-BR]\\nEsse projeto foi realizado para a página LOUD COMMENTS, uma página de fãs onde o intuito é dar mais visibilidade para a organização LOUDgg. \\n\\nCopa Free Fire é um campeonato oficial do jogo Garena Free Fire, ele é meio que uma Pré temporada para os times que jogam o FFWSBR, principal campeonato oficial de Free Fire.\\n\\n[ENG-US]\\nThis project was created for the LOUD COMMENTS page, a fan page whose purpose is to give more visibility to the LOUDgg organization. \\n\\nThe Free Fire Cup is an official tournament for the game Garena Free Fire; it serves somewhat as a preseason for teams competing in the FFWSBR, the game\'s premier official championship.\\n\\n​​​​​​​GAMEDAY\'s\"},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_f19c74558dc36a12.png\",\"uploads\\/img_a2f86200142daef9.png\"]},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_2da577acf08ba8de.png\",\"uploads\\/img_bdbba6b44e966667.png\",\"uploads\\/img_2d802029f3aac5c1.png\",\"uploads\\/img_d345e5ee61dfc1bd.png\"]},{\"type\":\"text\",\"content\":\"BOOYAAH\'s\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_da14a2ea5e348f8d.png\",\"uploads\\/img_afb1b2eb1edbaa7d.png\",\"uploads\\/img_b3df26b4625dc1f5.png\"]},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_16bd8e4de723219c.png\",\"uploads\\/img_61e194c050e98493.png\",\"uploads\\/img_274534be6899044f.png\",\"uploads\\/img_64aad5372a6b48f7.png\"]},{\"type\":\"text\",\"content\":\"MVP\'s\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_0c164ff78051e6f4.png\",\"uploads\\/img_a4095b7000a1ccd7.png\",\"uploads\\/img_35c29b5ddc2fc22e.png\"]},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_6388ee6259e63bb4.png\",\"uploads\\/img_c6ea3dcdeb752d66.png\"]},{\"type\":\"text\",\"content\":\"FINAL\"},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_6d41847f4d3b842b.png\",\"uploads\\/img_216b0ef6bae9c6b4.png\"]}]', 'uploads/img_ce4f0afcef7c963b.png', NULL, NULL, NULL, 'concluido', '2026-10-06 09:09:06', 1),
(26, 10, 2, 'portfolio', 'LOUD BRAWL STARS (Campeonatos do ano)', 'PROJETO não oficial desenvolvido com fins de estudo, feito para uma páginas de fãs da LOUD chamada de LOUDGGPE', '[{\"type\":\"image\",\"url\":\"uploads\\/img_40b35202d3ea18f1.jpg\"},{\"type\":\"image\",\"url\":\"uploads\\/img_f9badd4e7f73e41c.jpg\"},{\"type\":\"text\",\"content\":\"CAMPEONATO 1.0\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_70a7f71dd409cc5c.png\",\"uploads\\/img_5207262444c55e63.png\",\"uploads\\/img_735e9bb85820ce01.png\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_c0ad452660ffe8db.png\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_da7d9f1c84a5e498.png\",\"uploads\\/img_5b0330db9db4652d.png\",\"uploads\\/img_3fc459597f82c479.png\",\"uploads\\/img_330716fe97cac7fa.png\"]},{\"type\":\"text\",\"content\":\"CAMPEONATO 2.0\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_08c880a133a38a2f.png\",\"uploads\\/img_af90b236418e432e.png\",\"uploads\\/img_b629ce3ebc83dd8b.png\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_db1316f1c057a04a.png\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_e6127dded35c2aba.png\",\"uploads\\/img_37ad05bd9126320e.png\",\"uploads\\/img_92f8d3c48a181a7a.png\",\"uploads\\/img_98340aa402688c58.png\"]},{\"type\":\"text\",\"content\":\"CAMPEONATO 3.0\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_2fa19f39c8042a79.png\",\"uploads\\/img_ba6a1cb85c5bc277.png\",\"uploads\\/img_f59dfc8cb6bd47cf.png\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_c593196bb5a55a27.png\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_788cadef8af6a132.png\",\"uploads\\/img_22f322267a4e7787.png\",\"uploads\\/img_f19b457abbebfcf6.png\",\"uploads\\/img_fd5a5bc291951832.png\"]},{\"type\":\"text\",\"content\":\"CAMPEONATO 4.0\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_6dbea738d5344a94.png\",\"uploads\\/img_efcb68f9c891f572.png\",\"uploads\\/img_880bb1e6cd4e1aca.png\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_aa1b9f9ae8a7c85f.png\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_e475f06f1aae906e.png\",\"uploads\\/img_d71d2d06d5ef2073.png\",\"uploads\\/img_d20225209f582f2e.png\",\"uploads\\/img_5d8866f7cc5b2f7e.png\"]},{\"type\":\"text\",\"content\":\"CAMPEONATO 5.0\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_4f5dfc52282e152b.png\",\"uploads\\/img_a1a462acbbf2f21b.png\",\"uploads\\/img_72b0d39cb11ab97d.png\"]},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_57abb31e5e5bcabb.png\",\"uploads\\/img_1b7db05cee57eb15.png\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_bade77285f0b7c40.jpg\"}]', 'uploads/img_f1a5e3c58fe27b0f.jpg', NULL, NULL, NULL, 'concluido', '2026-10-17 20:16:36', 1),
(27, 10, 2, 'portfolio', 'LOUD no VCT ÁMERICAS SPLIT 1 2025', 'PROJETO não oficial desenvolvido com fins de estudo, feito para uma páginas de fãs da LOUD chamada de LOUDGGPE', '[{\"type\":\"image\",\"url\":\"uploads\\/img_f8e5dc4927cbc084.jpg\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_22625d02754c0cf2.png\",\"uploads\\/img_7c3669290f0bfb7c.png\",\"uploads\\/img_23cc6e4e7193d0f8.png\"]},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_990a9748e87a7fb9.png\",\"uploads\\/img_4501fc08ccf30dc9.png\"]},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_39babc1f636db997.png\",\"uploads\\/img_1ef7b00b9b2a2d27.png\",\"uploads\\/img_54e1f07c1a62f90a.png\"]},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_ff2a06c48812971c.png\",\"uploads\\/img_934e9231e4bcdeee.png\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_96c186a713860bcf.jpg\"}]', 'uploads/img_84c3dc46b28ec53e.jpg', NULL, NULL, NULL, 'concluido', '2026-10-17 20:22:28', 1),
(28, 10, 2, 'portfolio', 'LOUD VALORANT SPLIT 2 (2025)', 'PROJETO não oficial desenvolvido com fins de estudo, feito para uma páginas de fãs da LOUD chamada de LOUDGGPE', '[{\"type\":\"image\",\"url\":\"uploads\\/img_31e9e0f42d0232d6.jpg\"},{\"type\":\"image\",\"url\":\"uploads\\/img_246d51907920d27f.jpg\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_0b58af2e1755e2ed.png\",\"uploads\\/img_0eeb3fea68e10907.png\",\"uploads\\/img_61e4441996203354.png\"]},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_c39fe7522892da56.png\",\"uploads\\/img_df59766b4c0131a7.png\"]},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_60f517c8ded44d81.png\",\"uploads\\/img_1704c09ab0b52b23.png\",\"uploads\\/img_721de4284aba84d4.png\"]},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_9c56cc8abb9866a0.png\",\"uploads\\/img_30c092badd9762e0.png\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_40128dccdb811f56.jpg\"}]', 'uploads/img_64357146fd63906e.jpg', NULL, NULL, NULL, 'concluido', '2026-10-17 20:27:07', 1),
(29, 11, 2, 'portfolio', 'JUN + 2º SEMESTRE 2025', 'ARTES feitas no 2º semestre de 2025 (+ Junho).', '[{\"type\":\"image\",\"url\":\"uploads\\/img_b86fa6b5eea46c2c.jpg\"},{\"type\":\"text\",\"content\":\"LOUD PERNAMBUCO\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_4b476af45d3771b0.jpg\",\"uploads\\/img_2a529c4c5f9e4556.jpg\",\"uploads\\/img_058620a9cd0cb58f.jpg\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_b24f7a9553abccfe.jpg\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_599729b5b067eaa9.jpg\",\"uploads\\/img_79b27668e4f80539.jpg\",\"uploads\\/img_6be1c33f89dc39ff.jpg\"]},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_c78dc4744a61a26f.jpg\",\"uploads\\/img_c3e2bcc65100eae8.jpg\",\"uploads\\/img_ffc8381619579ace.jpg\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_9d5e6bca6d87c186.jpg\"},{\"type\":\"image\",\"url\":\"uploads\\/img_56bc5f3f437d8421.jpg\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_96184a37abaf105a.jpg\",\"uploads\\/img_6275a00a3a775e93.jpg\",\"uploads\\/img_d7f415ee716ee3de.jpg\"]},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_5e0f9dab047d6c68.jpg\",\"uploads\\/img_dfd717acd4168571.jpg\",\"uploads\\/img_7e37541ed0012005.jpg\"]},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_c30224966399802a.jpg\",\"uploads\\/img_6c91a71801a1f2ea.jpg\",\"uploads\\/img_36cf04106da72c6e.jpg\"]},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_fe9844a148c4ff5c.jpg\",\"uploads\\/img_ff9f43a4c7294b0a.jpg\",\"uploads\\/img_c3d69023e52d4c99.jpg\"]},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_267fa0b153272966.jpg\",\"uploads\\/img_77d352b5259bce7f.jpg\",\"uploads\\/img_ded9281049eaabc9.jpg\"]},{\"type\":\"text\",\"content\":\"CRISMA JOVEM - PARÓQUIA DA TORRE\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_2206138da0d3932f.jpg\",\"uploads\\/img_a9c596dfeb7f8fd5.jpg\",\"uploads\\/img_1317ed3d61212653.jpg\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_d1623e1e414c7e6e.jpg\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_4d32ed735957e25b.jpg\",\"uploads\\/img_7d394c58e9190448.jpg\",\"uploads\\/img_b17bcc4b1a6a0106.jpg\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_e0e297d98f2cefab.jpg\"},{\"type\":\"text\",\"content\":\"ACÓLITOS - PARÓQUIA DA TORRE\"},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_b9788dcfe0d8a0d5.jpg\",\"uploads\\/img_93e1a1409ece67ee.jpg\"]},{\"type\":\"text\",\"content\":\"ARTES PESSOAIS\"},{\"type\":\"grid\",\"cols\":4,\"images\":[\"uploads\\/img_953d9a1be5b6666b.jpg\",\"uploads\\/img_74378f11e170503c.jpg\",\"uploads\\/img_c8e8b2211e8049bc.jpg\",\"uploads\\/img_fcf7357a9352483a.jpg\"]},{\"type\":\"image\",\"url\":\"uploads\\/img_11c51bdb78556c20.jpg\"},{\"type\":\"text\",\"content\":\"PELOS PRADOS E CAMPINAS, VERDEJANTES, EU VOU\"}]', 'uploads/img_11c4270e25cdd53e.jpg', NULL, NULL, NULL, 'concluido', '2026-10-17 21:13:51', 1),
(30, 11, 2, 'portfolio', 'LTA Sul \\ LTA (1 Split) - LOUD Pernambuco', '', '[{\"type\":\"image\",\"url\":\"uploads\\/img_9bf24e3b27cb7851.jpg\"},{\"type\":\"text\",\"content\":\"Entre os meses de janeiro e fevereiro de 2025, ocorreu o primeiro Split da nova Liga das Américas que coloca a América do Sul e América do Norte em confronto para decidir quem vai para os Camp\'s Internacionais de League of Legends (nesse caso para o First Stand). Com a presença da LOUD LOL nesse campeonato, fiquei responsável pelas artes da LTA Sul (Fase Conferência) e da LTA (Fase Américas) na Página LOUD Pernambuco, tanto no Twitter\\/X, como também no Instagram, por meio dos Stories.\"},{\"type\":\"text\",\"content\":\"GAMEDAY - LTA SUL\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_0f1d74a071a87b9a.jpg\",\"uploads\\/img_1d1e72950783ddb2.jpg\",\"uploads\\/img_f2f99d2b26546320.jpg\"]},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_b3d3e3baf796a123.jpg\",\"uploads\\/img_7653706d5e8e5919.jpg\",\"uploads\\/img_dcd4fb5e0141f36a.jpg\"]},{\"type\":\"text\",\"content\":\"VICTORY - LTA SUL\"},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_85b7747ee4665c4a.jpg\",\"uploads\\/img_954ad3a9ec2728ed.jpg\",\"uploads\\/img_4c9254494d2f8772.jpg\"]},{\"type\":\"grid\",\"cols\":3,\"images\":[\"uploads\\/img_152312c81a57948e.jpg\",\"uploads\\/img_58f898b9e233edeb.jpg\",\"uploads\\/img_3c46049267ecb94a.jpg\"]},{\"type\":\"text\",\"content\":\"GAMEDAY - LTA\"},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_40f00b70bc0e9c05.jpg\",\"uploads\\/img_6037957b9e69dc4d.jpg\"]},{\"type\":\"text\",\"content\":\"VICTORY - LTA\"},{\"type\":\"grid\",\"cols\":2,\"images\":[\"uploads\\/img_4970d6b2b7a4cc4f.jpg\",\"uploads\\/img_7764550a41ada0d9.jpg\"]},{\"type\":\"text\",\"content\":\"PELOS PRADOS E CAMPINAS, EU VOU\"},{\"type\":\"image\",\"url\":\"uploads\\/img_b5dd1223efd0d53b.jpg\"}]', 'uploads/img_929815cf0d6677f1.jpg', NULL, NULL, NULL, 'concluido', '2026-10-17 21:22:36', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `propostas`
--

CREATE TABLE `propostas` (
  `id_proposta` int(11) NOT NULL,
  `id_projeto` int(11) NOT NULL,
  `id_artista` int(11) NOT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `mensagem` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pendente',
  `data_envio` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('artista','cliente','ambos','admin') NOT NULL DEFAULT 'ambos',
  `telefone` varchar(30) DEFAULT NULL,
  `foto_perfil` varchar(512) DEFAULT NULL,
  `data_cadastro` datetime DEFAULT current_timestamp(),
  `ultimo_acesso` datetime DEFAULT NULL,
  `status` enum('ativo','inativo','suspenso') DEFAULT 'ativo',
  `verificado` tinyint(4) DEFAULT 0,
  `email_verificado` tinyint(4) NOT NULL DEFAULT 0,
  `token_verificacao` varchar(64) DEFAULT NULL,
  `token_reset` varchar(64) DEFAULT NULL,
  `token_reset_exp` datetime DEFAULT NULL,
  `dois_fatores` tinyint(4) NOT NULL DEFAULT 0,
  `codigo_2fa` varchar(10) DEFAULT NULL,
  `codigo_2fa_exp` datetime DEFAULT NULL,
  `google_id` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nome`, `email`, `senha`, `tipo`, `telefone`, `foto_perfil`, `data_cadastro`, `ultimo_acesso`, `status`, `verificado`, `email_verificado`, `token_verificacao`, `token_reset`, `token_reset_exp`, `dois_fatores`, `codigo_2fa`, `codigo_2fa_exp`, `google_id`) VALUES
(9, 'Administrador OctoArt', 'Octoartadm@admin.com', '$2y$10$K1gEu4KH7IUUf3EKf9sY..r9VR99FcSElH8JXsASdj9beZVG55jU6', 'admin', NULL, NULL, '2026-09-29 12:58:48', '2026-10-06 12:57:07', 'ativo', 1, 1, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(10, 'Lucas SR', 'LucasSR@gmail.com', '$2y$10$IscY0UsXL7hn.9ieXRZwxOurGZ5fqCnmxUPQTelJP8WUS90YE1jsm', 'ambos', NULL, 'uploads/img_1253c1def199f6a1.png', '2026-09-29 19:30:16', '2026-10-18 00:48:09', 'ativo', 0, 1, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(11, 'Kauan Henrique', 'KauanHenriLPE@gmail.com', '$2y$10$kgyvWyK6IQmO1Fl23VLxU.7V7r9aGNBHxJ5D547QotOWY5tk0FSGi', 'ambos', NULL, 'uploads/img_d825ca80971669ef.jpg', '2026-10-06 13:17:08', '2026-10-18 01:28:10', 'ativo', 0, 1, '19cd33a5caa252a1b3d7436c14c0d569f018874a8cac079d', NULL, NULL, 0, NULL, NULL, NULL),
(12, 'Daniel Januario', 'DannJanu@gmail.com', '$2y$10$NV291eHefFwKF4JpvGcxnOetd0WLTDqlICKPqsxLo8wsQsu6emJUG', 'ambos', NULL, 'uploads/img_92017cae1a38a459.jpg', '2026-10-06 13:21:34', NULL, 'ativo', 0, 1, '9bba6aa06c603e4564cdd9e4b07b7a8383ef2e06e359a0ce', NULL, NULL, 0, NULL, NULL, NULL),
(13, 'Adrieli \"Lissa\" Cristine', 'Adrielicristine343@gmail.com', '$2y$10$7TvU3oWkXfYlT6SmuWNu8uakvCBQYZ/5wAUayGZ7s4aa3Hgc9jwly', 'ambos', NULL, 'uploads/img_3714bbb4f21b728d.jpg', '2026-10-06 13:24:37', '2026-10-06 14:17:42', 'ativo', 0, 1, '945102bf1a1adf6247d89974967ef0b789c1960ab5edaef8', NULL, NULL, 0, NULL, NULL, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD PRIMARY KEY (`id_avaliacao`),
  ADD KEY `id_avaliador` (`id_avaliador`),
  ADD KEY `id_avaliado` (`id_avaliado`);

--
-- Índices de tabela `avisos`
--
ALTER TABLE `avisos`
  ADD PRIMARY KEY (`id_aviso`),
  ADD KEY `id_admin` (`id_admin`);

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `comentarios_projeto`
--
ALTER TABLE `comentarios_projeto`
  ADD PRIMARY KEY (`id_comentario`),
  ADD KEY `id_projeto` (`id_projeto`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Índices de tabela `curtidas_projeto`
--
ALTER TABLE `curtidas_projeto`
  ADD PRIMARY KEY (`id_curtida`),
  ADD UNIQUE KEY `uk_curtida` (`id_projeto`,`id_usuario`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Índices de tabela `denuncias`
--
ALTER TABLE `denuncias`
  ADD PRIMARY KEY (`id_denuncia`),
  ADD UNIQUE KEY `uk_denuncia` (`id_projeto`,`id_denunciante`),
  ADD KEY `id_denunciante` (`id_denunciante`);

--
-- Índices de tabela `favoritos`
--
ALTER TABLE `favoritos`
  ADD PRIMARY KEY (`id_favorito`),
  ADD UNIQUE KEY `uk_fav` (`id_cliente`,`id_artista`);

--
-- Índices de tabela `imagens_projeto`
--
ALTER TABLE `imagens_projeto`
  ADD PRIMARY KEY (`id_imagem`),
  ADD KEY `id_projeto` (`id_projeto`);

--
-- Índices de tabela `mensagens`
--
ALTER TABLE `mensagens`
  ADD PRIMARY KEY (`id_mensagem`);

--
-- Índices de tabela `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id_pedido`),
  ADD KEY `id_cliente` (`id_cliente`),
  ADD KEY `id_artista` (`id_artista`);

--
-- Índices de tabela `perfis_artistas`
--
ALTER TABLE `perfis_artistas`
  ADD PRIMARY KEY (`id_perfil`),
  ADD UNIQUE KEY `id_usuario` (`id_usuario`);

--
-- Índices de tabela `projetos`
--
ALTER TABLE `projetos`
  ADD PRIMARY KEY (`id_projeto`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_categoria` (`id_categoria`);

--
-- Índices de tabela `propostas`
--
ALTER TABLE `propostas`
  ADD PRIMARY KEY (`id_proposta`),
  ADD KEY `id_projeto` (`id_projeto`),
  ADD KEY `id_artista` (`id_artista`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  MODIFY `id_avaliacao` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `avisos`
--
ALTER TABLE `avisos`
  MODIFY `id_aviso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `comentarios_projeto`
--
ALTER TABLE `comentarios_projeto`
  MODIFY `id_comentario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `curtidas_projeto`
--
ALTER TABLE `curtidas_projeto`
  MODIFY `id_curtida` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `denuncias`
--
ALTER TABLE `denuncias`
  MODIFY `id_denuncia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `favoritos`
--
ALTER TABLE `favoritos`
  MODIFY `id_favorito` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `imagens_projeto`
--
ALTER TABLE `imagens_projeto`
  MODIFY `id_imagem` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=291;

--
-- AUTO_INCREMENT de tabela `mensagens`
--
ALTER TABLE `mensagens`
  MODIFY `id_mensagem` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id_pedido` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `perfis_artistas`
--
ALTER TABLE `perfis_artistas`
  MODIFY `id_perfil` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `projetos`
--
ALTER TABLE `projetos`
  MODIFY `id_projeto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT de tabela `propostas`
--
ALTER TABLE `propostas`
  MODIFY `id_proposta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD CONSTRAINT `avaliacoes_ibfk_1` FOREIGN KEY (`id_avaliador`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `avaliacoes_ibfk_2` FOREIGN KEY (`id_avaliado`) REFERENCES `perfis_artistas` (`id_perfil`);

--
-- Restrições para tabelas `avisos`
--
ALTER TABLE `avisos`
  ADD CONSTRAINT `avisos_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Restrições para tabelas `comentarios_projeto`
--
ALTER TABLE `comentarios_projeto`
  ADD CONSTRAINT `comentarios_projeto_ibfk_1` FOREIGN KEY (`id_projeto`) REFERENCES `projetos` (`id_projeto`) ON DELETE CASCADE,
  ADD CONSTRAINT `comentarios_projeto_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Restrições para tabelas `curtidas_projeto`
--
ALTER TABLE `curtidas_projeto`
  ADD CONSTRAINT `curtidas_projeto_ibfk_1` FOREIGN KEY (`id_projeto`) REFERENCES `projetos` (`id_projeto`) ON DELETE CASCADE,
  ADD CONSTRAINT `curtidas_projeto_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Restrições para tabelas `denuncias`
--
ALTER TABLE `denuncias`
  ADD CONSTRAINT `denuncias_ibfk_1` FOREIGN KEY (`id_projeto`) REFERENCES `projetos` (`id_projeto`) ON DELETE CASCADE,
  ADD CONSTRAINT `denuncias_ibfk_2` FOREIGN KEY (`id_denunciante`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Restrições para tabelas `imagens_projeto`
--
ALTER TABLE `imagens_projeto`
  ADD CONSTRAINT `imagens_projeto_ibfk_1` FOREIGN KEY (`id_projeto`) REFERENCES `projetos` (`id_projeto`) ON DELETE CASCADE;

--
-- Restrições para tabelas `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_cliente`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`id_artista`) REFERENCES `perfis_artistas` (`id_perfil`);

--
-- Restrições para tabelas `perfis_artistas`
--
ALTER TABLE `perfis_artistas`
  ADD CONSTRAINT `perfis_artistas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Restrições para tabelas `projetos`
--
ALTER TABLE `projetos`
  ADD CONSTRAINT `projetos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `projetos_ibfk_2` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`);

--
-- Restrições para tabelas `propostas`
--
ALTER TABLE `propostas`
  ADD CONSTRAINT `propostas_ibfk_1` FOREIGN KEY (`id_projeto`) REFERENCES `projetos` (`id_projeto`) ON DELETE CASCADE,
  ADD CONSTRAINT `propostas_ibfk_2` FOREIGN KEY (`id_artista`) REFERENCES `perfis_artistas` (`id_perfil`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
