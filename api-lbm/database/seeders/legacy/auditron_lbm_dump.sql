-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 15 sep. 2026 à 07:48
-- Version du serveur : 11.8.9-MariaDB-log
-- Version de PHP : 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `u332279927_auditronx_lbm`
--

-- --------------------------------------------------------

--
-- Structure de la table `accreditations`
--

CREATE TABLE `accreditations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nom` varchar(255) NOT NULL,
  `groupe` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `accreditations` (`id`, `nom`, `groupe`, `created_at`, `updated_at`) VALUES
(1, 'Chef d établissement', 'Administration', NULL, NULL),
(2, 'Censeur Francophone', 'francophone', NULL, NULL),
(3, 'Censeur Anglophone', 'anglophone', NULL, NULL),
(5, 'Surveillant Général', '*', NULL, NULL),
(6, 'C/SSS', 'SSS', NULL, NULL),
(7, 'C/SOS', 'SOS', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

CREATE TABLE `classes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nom` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `niveau` varchar(255) DEFAULT NULL,
  `specialite` varchar(255) DEFAULT NULL,
  `effectif` int(11) DEFAULT 0,
  `section` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `classes` (`id`, `nom`, `code`, `niveau`, `specialite`, `effectif`, `section`, `created_at`, `updated_at`) VALUES
(1, 'Form 1', NULL, '1', NULL, 0, 'Anglophone', NULL, NULL),
(2, 'Form 1 Bil', NULL, '1', NULL, 0, 'Anglophone', NULL, NULL),
(3, 'Form 2', NULL, '2', NULL, 0, 'Anglophone', NULL, NULL),
(4, 'Form 2 Bil', NULL, '2', NULL, 0, 'Anglophone', NULL, NULL),
(5, 'Form3', NULL, '3', NULL, 0, 'Anglophone', NULL, NULL),
(6, 'Form3 Bil', NULL, '3', NULL, 0, 'Anglophone', NULL, NULL),
(7, 'Form 4 Arts', NULL, '4', 'Arts', 0, 'Anglophone', NULL, NULL),
(8, 'Form 4 Science', NULL, '4', 'Science', 0, 'Anglophone', NULL, NULL),
(9, 'Form4 Bil', NULL, '4', NULL, 0, 'Anglophone', NULL, NULL),
(10, 'Form 5 Art', NULL, '5', 'Art', 0, 'Anglophone', NULL, NULL),
(11, 'Form 5 Science', NULL, '5', 'Science', 0, 'Anglophone', NULL, NULL),
(12, 'Lower six Arts', NULL, '6', 'Arts', 0, 'Anglophone', NULL, NULL),
(13, 'Lower six Science', NULL, '6', 'Science', 0, 'Anglophone', NULL, NULL),
(14, 'Upper Six Arts', NULL, '7', 'Arts', 0, 'Anglophone', NULL, NULL),
(15, 'Upper Six Sc', NULL, '7', 'Science', 0, 'Anglophone', NULL, NULL),
(16, '6e A', NULL, '6e', NULL, 0, 'Francophone', NULL, NULL),
(17, '6e B', NULL, '6e', NULL, 0, 'Francophone', NULL, NULL),
(18, '6eBil', NULL, '6e', NULL, 0, 'Francophone', NULL, NULL),
(19, '5e A', NULL, '5e', NULL, 0, 'Francophone', NULL, NULL),
(20, '5e B', NULL, '5e', NULL, 0, 'Francophone', NULL, NULL),
(21, '5eBil', NULL, '5e', NULL, 0, 'Francophone', NULL, NULL),
(22, '4e All', NULL, '4e', NULL, 0, 'Francophone', NULL, NULL),
(23, '4e Ara', NULL, '4e', NULL, 0, 'Francophone', NULL, NULL),
(24, '4e Chin', NULL, '4e', NULL, 0, 'Francophone', NULL, NULL),
(25, '4e Esp', NULL, '4e', NULL, 0, 'Francophone', NULL, NULL),
(26, '4eBil', NULL, '4e', NULL, 0, 'Francophone', NULL, NULL),
(27, '3e All', NULL, '3e', NULL, 0, 'Francophone', NULL, NULL),
(28, '3eAra', NULL, '3e', NULL, 0, 'Francophone', NULL, NULL),
(29, '3e Chin', NULL, '3e', NULL, 0, 'Francophone', NULL, NULL),
(30, '3e Esp', NULL, '3e', NULL, 0, 'Francophone', NULL, NULL),
(31, '3eBil', NULL, '3e', NULL, 0, 'Francophone', NULL, NULL),
(32, '2nde All', NULL, '2nde', NULL, 0, 'Francophone', NULL, NULL),
(33, '2ndeAra', NULL, '2nde', NULL, 0, 'Francophone', NULL, NULL),
(34, '2ndeChi', NULL, '2nde', NULL, 0, 'Francophone', NULL, NULL),
(35, '2ndeEsp', NULL, '2nde', NULL, 0, 'Francophone', NULL, NULL),
(36, '2ndeBil', NULL, '2nde', NULL, 0, 'Francophone', NULL, NULL),
(37, '2ndeC', NULL, '2nde', NULL, 0, 'Francophone', NULL, NULL),
(38, '1ièreAra', NULL, '1ière', NULL, 0, 'Francophone', NULL, NULL),
(39, '1ière All', NULL, '1ière', NULL, 0, 'Francophone', NULL, NULL),
(40, '1ière Chi', NULL, '1ière', NULL, 0, 'Francophone', NULL, NULL),
(41, '1ière Esp', NULL, '1ière', NULL, 0, 'Francophone', NULL, NULL),
(42, '1ièreBil', NULL, '1ière', NULL, 0, 'Francophone', NULL, NULL),
(43, '1ière C', NULL, '1ière', NULL, 0, 'Francophone', NULL, NULL),
(44, '1ière D', NULL, '1ière', NULL, 0, 'Francophone', NULL, NULL),
(45, 'Tle A4 All', NULL, 'Tle', 'A4', 0, 'Francophone', NULL, NULL),
(46, 'Tle A4 Ara', NULL, 'Tle', 'A4', 0, 'Francophone', NULL, NULL),
(47, 'Tle A4 Chi', NULL, 'Tle', 'A4', 0, 'Francophone', NULL, NULL),
(48, 'Tle A4 Esp', NULL, 'Tle', 'A4', 0, 'Francophone', NULL, NULL),
(49, 'Tle ABI', NULL, 'Tle', NULL, 0, 'Francophone', NULL, NULL),
(50, 'Tle C', NULL, 'Tle', NULL, 0, 'Francophone', NULL, NULL),
(51, 'Tle D', NULL, 'Tle', NULL, 0, 'Francophone', NULL, NULL),
(62, 'Form 5 Bil', NULL, NULL, 'Bil', 0, 'anglophone', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `disciplines`
--

CREATE TABLE `disciplines` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nom` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `coefficient` decimal(5,2) DEFAULT 1.00,
  `description` text DEFAULT NULL,
  `departement` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `isTP` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `disciplines` (`id`, `nom`, `code`, `coefficient`, `description`, `departement`, `created_at`, `updated_at`, `isTP`) VALUES
(1, 'Biology', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(2, 'H. Biology', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(3, 'Chemistry', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 1),
(4, 'Physics', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 1),
(5, 'Geology', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 1),
(6, 'Computer', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 1),
(7, 'ICT', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 1),
(8, 'Maths', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(9, 'A. Math', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(10, 'Further maths', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(11, 'Philosophy', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(12, 'English L.', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(13, 'Literature', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(14, 'French', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(15, 'History', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(16, 'Economics', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(17, 'Commerce', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(18, 'Geography', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(19, 'Citizenship', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(20, 'Logic', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 0),
(21, 'Sports and PE', NULL, 1.00, NULL, 'sss', NULL, NULL, 1),
(22, 'Manual labour', NULL, 1.00, NULL, 'anglophone', NULL, NULL, 1),
(168, 'ANGLAIS', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(169, 'ALLEMAND', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(170, 'ARABE', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(171, 'ESPAGNOL', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(172, 'CHINOIS', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(173, 'LATIN', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(174, 'FRANÇAIS', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(175, 'HISTOIRE', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(176, 'GEOGRAPHIE', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(177, 'ECM', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(178, 'CITIZENSHIP', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(179, 'PHILOSOPHIE', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(180, 'PCT', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(181, 'SVTEEHB', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(182, 'INFORMATIQUE', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(183, 'MATHEMATIQUES', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(184, 'EPS', NULL, 1.00, NULL, 'sss', NULL, NULL, 1),
(185, 'TM', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(186, 'ESF', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(187, 'DESSIN D\'ART', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(188, 'MANUAL LABOR', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(189, 'LITERATURE AWARENESS', NULL, 1.00, NULL, 'francophone', NULL, NULL, 0),
(190, 'PHYSIQUE', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(191, 'CHIMIE', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(192, 'LANGUE FRANCAISE', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(193, 'LITTERATURE FRANCAISE', NULL, 1.00, NULL, 'francophone', NULL, NULL, 1),
(194, 'Orientation Scolaire', NULL, 1.00, NULL, 'sos', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Structure de la table `enseignants`
--

CREATE TABLE `enseignants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nom` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `rfid_uid` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `fonction` varchar(255) DEFAULT NULL,
  `specialite` varchar(255) DEFAULT NULL,
  `matricule` varchar(255) DEFAULT NULL,
  `section` varchar(255) DEFAULT NULL,
  `grade` varchar(255) DEFAULT NULL,
  `anciennete` int(11) DEFAULT NULL,
  `prise_de_service` date DEFAULT NULL,
  `dob` varchar(255) DEFAULT NULL,
  `pob` varchar(255) DEFAULT NULL,
  `region_or` varchar(255) DEFAULT NULL,
  `dept_or` varchar(255) DEFAULT NULL,
  `arr_or` varchar(255) DEFAULT NULL,
  `prise_de_service2` varchar(255) DEFAULT NULL,
  `tel` varchar(255) DEFAULT NULL,
  `sit_mat` varchar(255) DEFAULT NULL,
  `poste` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `enseignants` (`id`, `nom`, `email`, `rfid_uid`, `created_at`, `updated_at`, `fonction`, `specialite`, `matricule`, `section`, `grade`, `anciennete`, `prise_de_service`, `dob`, `pob`, `region_or`, `dept_or`, `arr_or`, `prise_de_service2`, `tel`, `sit_mat`, `poste`) VALUES
(416, 'KILOH MARCEL JAB', 'kiloh.marcel.jab@auditron.lbm', '2', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'CENSEUR', 'CHEMISTRY', '636270-S', 'administration', 'PLEG', NULL, '2015-02-08', '1982-02-09', 'NKAMBE', 'Nord-Ouest', 'Donga-Mantung', 'Nkambe', '2007-04-03', '675344726/698431462', 'Marié(e)', 1),
(417, 'ABBA ETIENNE', 'abba.etienne@auditron.lbm', '3', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Surveillant Général', 'GEOGRAPHIE', '782467-A', 'administration', 'PCEG', NULL, '2016-04-08', '1989-04-10', 'DARNA', 'Extreme-Nord', 'Mayo-Danay', 'Kaï-Kaï', '2013-04-02', '698038046', 'Marié(e)', 1),
(418, 'ALANG UKAH JOSEPH', 'jukalang@yahoo.com', '4', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'CENCEUR', 'ECONOMICS', 'X-040152', 'administration', 'PLETP', NULL, '2025-04-12', '1979-11-03', 'ESU', 'Nord-Ouest', 'Menchum', 'Fungom', '2016-04-03', '677492487', 'Marié(e)', 1),
(419, 'DAIGA BENJAMIN', 'benjamindaiga@gmail.com', '120', '2025-11-07 15:17:17', '2026-01-12 14:07:20', 'Censeur', 'EDUCATION PHYSIQUE ET SPORTIVE', '666038-F', 'administration', 'MPEPS', NULL, '2025-04-12', '1986-08-26', 'DATCHEKA', 'Extreme-Nord', 'Mayo-Danay', 'Datchéka', '2008-04-08', '674504558', 'Célibataire', 1),
(420, 'NFON VICTOR MABUH', 'nfon.victor.mabuh@auditron.lbm', '6', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'CENSEUR', 'CHEMISTRY', '704188-A', 'administration', 'PCEG', NULL, '2019-03-09', '1987-05-09', 'DJOTTIN', 'Nord-Ouest', 'Bui', 'Noni', '2010-08-03', '677219922', 'Marié(e)', 1),
(421, 'IBRAHIMA ELHADJI BABA', 'elhadjibabaibrahima@gmail.com', '7', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'CENSEUR', 'INFORMATIQUE', '774019-C', 'administration', 'PLEG', NULL, '2025-04-12', '1986-02-06', 'BELEL', 'Adamaoua', 'Vina', 'Belel', '2013-04-02', '699991728', 'Marié(e)', 1),
(422, 'NYAMBALE RENE', 'nyambale.rene@auditron.lbm', '8', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'INTENDANT', '', '630914-G', 'administration', 'ACA', NULL, '2022-06-12', '1967-01-06', 'NGAOUNDERE', 'Adamaoua', 'Vina', 'Ngaoundéré 1er', '2007-01-01', '675974801', 'Marié(e)', 1),
(423, 'AMINOU OUSMANOU', 'ousmanouaminou68@gmail.com', '9', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'C/SOS', 'ORIENTATION SCOLAIRE', '757992-D', 'administration', 'COSUP', NULL, '2019-06-12', '1983-07-12', 'GUIDER', 'Nord', 'Mayo-Louti', 'Guider', '2011-03-01', '699233875', 'Marié(e)', 1),
(424, 'TANTO MASHELINE NKAAH', 'tanto.masheline.nkaah@auditron.lbm', '10', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Surveillant Général', 'BIOLOGY', '719917-P', 'administration', 'PCEG', NULL, '2022-08-12', '1987-11-07', 'DJOTTIN', 'Nord-Ouest', 'Bui', 'Noni', '2010-08-03', '674790822', 'Célibataire', 1),
(425, 'SALI SOUAIBOU', 'sali.souaibou@auditron.lbm', '11', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Surveillant Général', 'SCIENCES DE LA VIE ET DE LA TERRE', '757259-L', 'administration', 'PLEG', NULL, '2017-02-08', '1978-07-12', 'REY-BOUBA', 'Nord', 'Mayo-Rey', 'Rey-Bouba', '2011-03-01', '676025490', 'Célibataire', 1),
(426, 'PATAKERE SIAKBANG JOEL', 'siakbang@yahoo.fr', '12', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'CENSEUR', 'SCIENCES DE LA VIE ET DE LA TERRE', '761027-A', 'administration', 'PLEG', NULL, '2024-04-09', '1977-09-12', 'YAOUNDE', 'Extreme-Nord', 'Mayo-Kani', 'Kaélé', '2011-03-01', '696284375', 'Marié(e)', 1),
(427, 'WAVOUM DIEUDONNE', 'dwavoum@gmail.com', '13', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'DIRECTEUR pi', 'GEOGRAPHIE', '768500-J', 'administration', 'PLEG', NULL, '2015-05-05', '1982-03-04', 'MAYO-DARLE', 'Adamaoua', 'Mayo-Banyo', 'Bankim', '2013-07-10', '690524356', 'Marié(e)', 1),
(428, 'DJONG-YANG SAMUEL', 'djongyangsamuel16@gmail.com', '14', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'C/SSS', 'EDUCATION PHYSIQUE ET SPORTIVE', 'E-052203', 'administration', 'Professeur d\'Education Physique et Sportive', NULL, '2025-04-12', '1985-04-08', 'BOGO', 'Extreme-Nord', 'Mayo-Danay', 'Datchéka', '2015-03-09', '695956755', 'Célibataire', 1),
(429, 'ABDOULBAGUI HAMAN', 'abdoulbagui.haman@auditron.lbm', '15', '2025-11-07 15:17:17', '2026-01-11 12:53:57', 'Enseignant', 'ORIENTATION SCOLAIRE', 'B-093529', NULL, 'CPOSUP', NULL, '2020-02-11', '1984-11-06', 'TIBATI', 'Adamaoua', 'Mayo-Banyo', 'Banyo', '2019-09-09', '693654152', 'Marié(e)', 1),
(430, 'ACHU MERCY MEGHA', 'achumercymegha@gmail.com', '17', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'ECONOMICS', 'F-128519', 'anglophone', 'PCEG', NULL, '2020-03-10', '1987-04-02', 'PINYIN', 'Nord-Ouest', 'Mezam', 'Santa', '2019-01-10', '670719852', 'Célibataire', 1),
(431, 'ADIDJATOU', 'adidjatou@auditron.lbm', '18', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'ARABE', '771140-U', 'francophone', 'PLEG', NULL, '2019-03-09', '1986-11-04', 'NGAOUNDERE', 'Adamaoua', 'Vina', 'Ngaoundéré 1er', '2013-04-02', '694544548', 'Marié(e)', 1),
(432, 'AHMADOU NAYIBI', 'ahmadou.nayibi@auditron.lbm', '19', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'ESPAGNOL', 'W-127075', 'francophone', 'PLEG', NULL, '2020-05-10', '1994-02-04', 'TARAM-SIRI-BANYO', 'Adamaoua', 'Mayo-Banyo', 'Banyo', '2019-01-10', '676241311', 'Célibataire', 1),
(433, 'AMOH LOVERT NJOM', 'amoh.lovert.njom@auditron.lbm', '20', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'ECONOMICS', 'O-147798', 'anglophone', 'PLEG', NULL, '2023-03-09', '1989-07-07', 'ACHA-TUGI', 'Nord-Ouest', 'Momo', 'Mbengwi', '2020-01-12', '674740822', 'Célibataire', 1),
(434, 'ASSE ASSE PAUL DANIEL', 'asse.asse.paul.daniel@auditron.lbm', '21', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'GEOGRAPHIE', 'J-189361', 'francophone', 'PLEG', NULL, '2024-06-09', '1999-07-08', 'YAOUNDE', 'Sud', 'Mvila', 'Ebolowa 1er', '2023-11-09', '693742952', 'Célibataire', 1),
(435, 'ATCAM MACELUS TABIT', 'atcam.macelus.tabit@auditron.lbm', '22', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'LAW', 'J-201786', 'anglophone', 'Professeur des Lycées d\'Enseignement Technique et Professionnel', NULL, '2023-09-12', '1994-03-08', 'MUYUKA', 'Nord-Ouest', 'Momo', 'Widikum', '2025-05-11', '677208883', 'Célibataire', 1),
(436, 'ATEH CYNTHIA ENGOH', 'engohcynthia@gmail.com', '23', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'HISTORY', 'T-222719', 'anglophone', 'PCEG', NULL, '2024-04-11', '1999-03-12', 'ACHA-TUGI', 'Nord-Ouest', 'Momo', 'Mbengwi', '2026-05-10', '693450208', 'Célibataire', 1),
(437, 'BASSI EMBENG AUDREY LYDIE', 'bassi.embeng.audrey.lydie@auditron.lbm', '24', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'SCIENCES DE LA VIE ET DE LA TERRE', 'X-843378', 'francophone', 'PLEG', NULL, '2023-01-10', '1998-02-02', 'MBALMAYO', 'Centre', 'Mbam-et-Inoubou', 'Ndikiniméki', '2024-06-09', '674460259/696593374', 'Célibataire', 1),
(438, 'BELBARA KAOKAMLA', 'kaokamlabelbara@gmail.cm', '25', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'GEOGRAPHIE', 'A-148187', 'francophone', 'PLEG', NULL, '2025-12-01', '1990-07-12', 'KOFIDE', 'Extreme-Nord', 'Mayo-Kani', 'Porhi', '2020-01-12', '698615861', 'Marié(e)', 1),
(439, 'BEOE DJAPEA YANNICK', 'beoe.djapea.yannick@auditron.lbm', '26', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'enseignant', 'SCIENCES DE LA VIE ET DE LA TERRE', 'E-159679', 'francophone', 'PLEG', NULL, '2022-06-01', '1989-07-06', 'MOLOUNDOU', 'Est', 'Boumba-et-Ngoko', 'Moloundou', '2022-08-07', '694527174', 'Célibataire', 1),
(440, 'BISONG TAMBI NCHENGEAMBA', 'bisongtambi02@gmail.com', '27', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'COMPUTER SCIENCE', 'P-081591', 'anglophone', 'Professeur des Collèges d\'Enseignement Technique et Professionnel', NULL, '2024-06-09', '1986-01-10', 'KUMBA', 'Sud-Ouest', 'Manyu', 'Upper Bayang', '2019-02-03', '670728438', 'Marié(e)', 1),
(441, 'BISSA ESSOLA JEANNETTE', 'bissa95@gmail.com', '28', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'GEOGRAPHIE', 'G-187982', 'francophone', 'PLEG', NULL, '2024-06-09', '1995-05-08', 'YAOUNDE', 'Centre', 'Nyong-et-Mfoumou', 'Endom', '2025-03-07', '697853866', 'Célibataire', 1),
(443, 'DJOUMBA DOUNA NIQUESE', 'djoumba.douna.niquese@auditron.lbm', '30', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Enseignant', 'LETTRES MODERNES ANGLAISES', 'W-074559', 'anglophone', 'PLEG', NULL, '2017-02-02', '1992-05-10', 'MBE', 'Adamaoua', 'Vina', 'Mbé', '2018-06-01', '679943281', 'Célibataire', 1),
(444, 'ESSENGUE EBANDA JACQUELINE CLAUDIA', 'essengue.ebanda.jacqueline.claudia@auditron.lbm', '31', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'PHILOSOPHIE', 'O-147527', 'francophone', 'PLEG', NULL, '2023-04-01', '1994-07-07', 'OMVAN', 'Centre', 'Méfou-et-Afamba', 'Mfou', '2023-01-12', '694826690', 'Célibataire', 1),
(445, 'FAISSAL DANDI', 'dandifaissal@gmail.com', '32', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'ARABE', 'M-148460', 'francophone', 'PCEG', NULL, '2021-11-01', '1996-09-07', 'MOKONG', 'Extreme-Nord', 'Mayo-Tsanaga', 'Mokolo', '2021-03-12', '698253946', 'Célibataire', 1),
(446, 'FIRIDA SABINE BEATRICE', 'firida.sabine.beatrice@auditron.lbm', '33', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'LANGUE ANGLAISE ET LITTERATURE D\'EXPRESSION ANGLAISE', 'J-148467', 'anglophone', 'PCEG', NULL, '2021-03-12', '1993-11-05', 'YAOUNDE', 'Extreme-Nord', 'Mayo-Danay', 'Yagoua', '2021-03-12', '690825301', 'Célibataire', 1),
(447, 'YANMANGAJEAN PHILEMON', 'yanmangajean.philemon@auditron.lbm', '73', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'SCIENCES DE LA VIE ET DE LA TERRE', '753930-C', 'francophone', 'PLEG', NULL, '2024-01-04', '1985-01-05', 'BORAI', 'Extreme-Nord', 'Mayo-Danay', 'Guéré', '2011-03-01', '697704747', 'Marié(e)', 1),
(448, 'HAMIDOU ANDRE', 'hamidouandre5@gmail.com', '35', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'SCIENCES DE LA VIE ET DE LA TERRE', '784976-A', 'francophone', 'PLEG', NULL, '2015-12-01', '1980-07-12', 'TAPARE-BEKA', 'Nord', 'Faro', 'Béka', '2013-03-03', '676744737', 'Marié(e)', 1),
(449, 'INOUSSA BIDISSE', 'inoussa.bidisse@auditron.lbm', '36', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'CO', 'ORIENTATION SCOLAIRE', 'U-050581', 'SOS', 'COSUP', NULL, '2024-07-08', '1986-11-06', 'MAROUA', 'Adamaoua', 'Vina', 'Mbé', '2014-07-12', '699887568', 'Marié(e)', 1),
(450, 'KAMRO NJENOYOM RAHILA', 'kamro@gmail.com', '37', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Enseignant', 'EDUCATION PHYSIQUE ET SPORTIVE', '722804-Y', 'sss', 'MPEPS', NULL, '2020-02-11', '1980-06-04', 'GAROUA', 'Nord', 'Bénoué', 'Garoua 1er', '2008-04-08', '693548115/674078833', 'Célibataire', 1),
(451, 'LANDI JACQUELINE', 'landi.jacqueline@auditron.lbm', '41', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Enseignant', 'LANGUE FRANCAISE ET LITTERATURES D\'EXPRESSION FRANCAISE', 'D-034813', 'francophone', 'PCEG', NULL, '2015-04-13', '1991-03-10', 'MAMFE', 'Adamaoua', 'Vina', 'MBE', '2015-04-13', '697835764', 'Célibataire', 1),
(452, 'KELBE', 'kelbe@auditron.lbm', '39', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'CHIMIE', '770635-Q', 'francophone', 'PLEG', NULL, '2012-07-09', '1985-07-05', 'DIGAYA', 'Extreme-Nord', 'Mayo-Sava', 'Tokombéré', '2014-03-01', '678332323/691587177', 'Marié(e)', 1),
(453, 'LAMBIV KELEN WIYSANYUY', 'lambiv.kelen.wiysanyuy@auditron.lbm', '40', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'ECONOMICS', '784204-U', 'anglophone', 'Professeur des Lycées d\'Enseignement Technique et Professionnel', NULL, '2015-05-07', '1986-03-11', 'KUMBO', 'Nord-Ouest', 'Bui', 'Kumbo', '2016-05-04', '677362141', 'Marié(e)', 1),
(454, 'MADJOU DABOULE FELICITE', 'madjou.daboule.felicite@auditron.lbm', '42', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'PHILOSOPHIE', 'M-005321', 'francophone', 'PLEG', NULL, '2021-07-09', '1986-02-10', 'NGAOUNDERE', 'Extreme-Nord', 'Mayo-Kani', 'Kaélé', '2015-02-04', '699792481/683354824', 'Marié(e)', 1),
(455, 'MAINISSO VALERIE', 'mainisso.valerie@auditron.lbm', '43', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'ESPAGNOL', 'I-016932', 'francophone', 'PLEG', NULL, '2022-01-09', '1986-06-01', 'GUIDIGUIS', 'Extreme-Nord', 'Mayo-Danay', 'Datchéka', '2015-02-04', '697742957/674332195', 'Marié(e)', 1),
(456, 'MAKU JUSTINA', 'maku.justina@auditron.lbm', '44', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'LANGUE ANGLAISE ET LITTERATURE D\'EXPRESSION ANGLAISE', 'E-038241', 'anglophone', 'PCEG', NULL, '2024-09-09', '1992-04-02', 'BANTENG', 'Sud-Ouest', 'Lebialem', 'Wabane', '2016-01-04', '670354174', 'Marié(e)', 1),
(457, 'MALKINA KAISSISSOU', 'malkina.kaississou@auditron.lbm', '45', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'HISTOIRE', 'R-005850', 'francophone', 'PLEG', NULL, '2015-07-09', '1987-03-12', 'YAOUNDE', 'Extreme-Nord', 'Mayo-Danay', 'Guéré', '2019-12-09', '699251804', 'Célibataire', 1),
(458, 'MBOGUE JACQUES-BONAVENTURE', 'jacques.mbogue@yahoo.fr', '46', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'MATHEMATIQUES', '790603-M', 'francophone', 'PCEG', NULL, '2015-10-01', '1987-05-03', 'LOGBIKOY', 'Littoral', 'Sanaga-Maritime', 'Nyanon', '2013-06-12', '694885756', 'Célibataire', 1),
(459, 'MBORORO THECLE', 'mbororo.thecle@auditron.lbm', '47', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'EDUCATION PHYSIQUE ET SPORTIVE', 'E-150307', 'sss', 'MPEPS', NULL, '2022-03-01', '1990-05-09', 'MAROUA', 'Extreme-Nord', 'Mayo-Kani', 'Kaélé', '2020-10-08', '699074584', 'Célibataire', 1),
(460, 'MIZINGOU VONDOU', 'mizingou.vondou@auditron.lbm', '48', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'HISTOIRE', 'F-188061', 'francophone', 'PLEG', NULL, '2024-07-10', '1996-11-08', 'LAM-FIGUIL', 'Nord', 'Mayo-Louti', 'Figuil', '2024-06-09', '655475027', 'Célibataire', 1),
(461, 'MOHAMADOU DJOUBAIROU', 'mohamadou.djoubairou@auditron.lbm', '49', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'LANGUE FRANCAISE ET LITTERATURES D\'EXPRESSION FRANCAISE', 'M-016253', 'francophone', 'PLEG', NULL, '2023-03-09', '1985-02-08', 'NGAOUNDERE', 'Adamaoua', 'Vina', 'Ngaoundéré 1er', '2017-02-04', '652548865', 'Marié(e)', 1),
(462, 'NDASHI ISOPHA KA\'AH', 'nisopha01@gmail.com', '50', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'PHYSICS', 'V-010858', 'anglophone', 'PLEG', NULL, '2024-12-01', '1994-05-07', 'BABA 1', 'Nord-Ouest', 'Ngo-Ketunjia', 'Babessi', '2017-06-07', '676370480', 'Marié(e)', 1),
(463, 'NDZI EMMACULATE YINYU', 'ndzi.emmaculate.yinyu@auditron.lbm', '51', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'HISTORY', 'X-036196', 'anglophone', 'PCEG', NULL, '2015-08-01', '1986-09-03', 'BANSO', 'Nord-Ouest', 'Donga-Mantung', 'Ndu', '2014-05-12', '679366036', 'Célibataire', 1),
(464, 'NGAIBE ALLEN NELSON MBIYDZENYUY', 'ngaibeallennelson@gmail.com', '52', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'GEOLOGY', 'M-091291', 'anglophone', 'PCEG', NULL, '2025-05-09', '1987-05-02', 'KUMBO', 'Nord-Ouest', 'Bui', 'Kumbo', '2022-08-07', '674704618', 'Marié(e)', 1),
(465, 'NGO NLOGA FRANCOISE NADEGE', 'ngo.nloga.francoise.nadege@auditron.lbm', '53', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'GEOGRAPHIE', 'G-091321', 'francophone', 'PCEG', NULL, '2019-01-03', '1993-09-08', 'IBONG', 'Littoral', 'Sanaga-Maritime', 'Ndom', '2018-08-12', '696662371', 'Célibataire', 1),
(466, 'NGO TJOMP DEBORAH EMMANUELLE', 'ngo.tjomp.deborah.emmanuelle@auditron.lbm', '54', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'LANGUE FRANCAISE ET LITTERATURES D\'EXPRESSION FRANCAISE', 'O-051853', 'francophone', 'PLEG', NULL, '2017-10-01', '1992-07-07', 'YAOUNDE', 'Centre', 'Nyong-et-Kellé', 'Dibang', '2017-10-12', '697683206', 'Célibataire', 1),
(467, 'NGONG CEDRIC NSOMBI', 'ngong.cedric.nsombi@auditron.lbm', '55', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'COMPUTER SCIENCE', 'B-131543', 'anglophone', 'PLETP', NULL, '2023-10-04', '1996-12-04', 'MBINGO', 'Nord-Ouest', 'Boyo', 'Fundong', '2019-08-10', '651041532', 'Célibataire', 1),
(468, 'NGONGNANG LAWRENCE CHEFOR', 'delawngongfor@gmail.com', '56', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'COMPUTER SCIENCE', 'O-093381', 'anglophone', 'PLETP', NULL, '2020-03-11', '1990-04-01', 'AWING-SANTA', 'Nord-Ouest', 'Mezam', 'Santa', '2023-07-03', '670626303', 'Marié(e)', 1),
(469, 'NWAMBE KELVIN EFUBAI', 'nwambe.kelvin.efubai@auditron.lbm', '57', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'PHILOSOPHY', 'B-086586', 'anglophone', 'PCEG', NULL, '2024-05-09', '1993-04-06', 'TOMBEL', 'Nord-Ouest', 'Donga-Mantung', 'Ako', '2024-06-09', '678940669', 'Célibataire', 1),
(470, 'OKALA STEVE FELICIEN', 'maitrestevyg@mail.com', '58', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'GEOGRAPHIE', 'U-080662', 'francophone', 'PLEG', NULL, '2022-09-09', '1993-04-04', 'YAOUNDE', 'Centre', 'Lekié', 'Sa\'a', '2018-10-01', '671074474/694087183', 'Célibataire', 1),
(471, 'OUMAROU SANDA', 'oumarou.sanda@auditron.lbm', '59', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'ALLEMAND', '782724-J', 'francophone', 'PCEG', NULL, '2013-09-09', '1989-07-12', 'DJOHONG', 'Adamaoua', 'Mbéré', 'Djohong', '2015-11-12', '679123796', 'Marié(e)', 1),
(472, 'POUNA CASIMIR', 'pounacasimir@gmai.com', '60', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'SCIENCES DE LA VIE ET DE LA TERRE', 'O-074740', 'francophone', 'PCEG', NULL, '2023-02-09', '1990-05-09', 'GAROUA', 'Nord', 'Mayo-Rey', 'Touboro', '2018-08-01', '696154942', 'Célibataire', 1),
(473, 'ROUKAYATOU AWALOU', 'roukayatou.awalou@auditron.lbm', '61', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Enseignant', 'LETTRE FRANCAISE ET LITTERATURE D\'EXPRESSION FRANCAISE', 'E-131662', 'francophone', 'PLEG', NULL, '2020-09-10', '1990-07-06', 'MBARANG-MEIGANGA', 'Adamaoua', 'Mbéré', 'Meiganga', '2020-12-08', '699173531', 'Célibataire', 1),
(474, 'SAIKAO', 'saikaosai@gmail.com', '62', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'LANGUE FRANCAISE ET LITTERATURES D\'EXPRESSION FRANCAISE', 'Z-188092', 'francophone', 'PLEG', NULL, '2024-04-01', '1996-02-11', 'TIBATI', 'Nord', 'Mayo-Rey', 'Rey-Bouba', '2024-06-09', '698582042', 'Marié(e)', 1),
(475, 'TABITHA THERESE', 'tabitha.therese@auditron.lbm', '63', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'LANGUE FRANCAISE ET LITTERATURES D\'EXPRESSION FRANCAISE', '770383-J', 'francophone', 'PCEG', NULL, '2012-10-04', '1981-11-09', 'MBE', 'Adamaoua', 'Vina', 'Mbé', '2013-03-03', '695186547/683132376', 'Célibataire', 1),
(476, 'TAIKAO DE SOUKOUMKAYA', 'tdesoukoumkaya@gmail.com', '64', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Enseignant', 'GEOGRAPHIE', '760136-S', 'francophone', 'PCEG', NULL, '2018-06-09', '1983-08-10', 'DOUKOULA', 'Extreme-Nord', 'Mayo-Danay', 'Datchéka', '2013-03-03', '697191924', 'Marié(e)', 1),
(477, 'TALATOU REMI FRU', 'talatouremifru@yahoo.com', '65', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'enseignant', 'MATHEMATICS', 'M-081440', 'anglophone', 'CCA', NULL, '2024-07-08', '1994-07-10', 'AWING-SANTA', 'Nord-Ouest', 'Mezam', 'Santa', '2018-08-05', '651937634', 'Marié(e)', 1),
(478, 'TCHOYI VERONICA FAELLE', 'tchoiy.veronica.faelle@auditron.lbm', '66', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'BIOLOGY', 'K-136677', 'anglophone', 'PLEG', NULL, '2020-02-10', '1991-11-06', 'BANGANGTE', 'Centre', 'Mbam-et-Inoubou', 'Kiiki', '2019-01-10', '654288884', 'Marié(e)', 1),
(479, 'TEDONGMO FALONE MAJOLIE', 'tedongmo.falone.majolie@auditron.lbm', '68', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Enseignant', 'GEOGRAPHY', 'M-045857', 'anglophone', 'PLEG', NULL, '2020-10-01', '1989-12-06', 'BABADJOU', 'Ouest', 'Bamboutos', 'Mbouda', '2014-09-01', '670397425', 'Célibataire', 1),
(480, 'TSOGO SABINE ESTELLE', 'tsogo.sabine.estelle@auditron.lbm', '71', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'ENSEIGNANT', 'HISTOIRE', 'B-166040', 'francophone', 'CCA', NULL, '2022-12-09', '1984-03-04', 'AKONO', 'Centre', 'Méfou-et-Akono', 'Akono', '2021-04-01', '695769626', 'Célibataire', 1),
(481, 'YUNYUY BASIL FONYUY', 'yunyuy.basil.fonyuy@auditron.lbm', '74', '2025-11-07 15:17:17', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'GEOGRAPHY', 'J-091531', 'anglophone', 'PLEG', NULL, '2023-05-09', '1992-04-03', 'KUMBO', 'Nord-Ouest', 'Bui', 'Kumbo', '2022-03-07', '676782043', 'Célibataire', 1),
(482, 'ABO NAMPIDOI GAMKAOU STEPHANE', 'abo.nampidoi.gamkaou.stephane@auditron.lbm', '16', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'enseignant', 'LATIN', 'T-162274', 'francophone', 'PCEG', NULL, NULL, '2000-03-08', 'NGAOUNDERE', 'Adamaoua', 'Mbéré', 'Meiganga', '2023-01-09', '695850332', 'Célibataire', 1),
(483, 'HAMADOU ALIM', 'hamadou.alim@auditron.lbm', '34', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'LETTRES BILINGUES', 'L-049937', 'francophone', 'PCEG', NULL, '2023-06-08', '1986-04-09', 'MOKOLO', 'Extreme-Nord', 'Mayo-Tsanaga', 'Mokolo', '2017-10-01', '653070737', 'Marié(e)', 1),
(484, 'KAN ESTHELLA SYLVIANA TEWAH', 'kan.esthella.sylviana.tewah@auditron.lbm', '38', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'Animateur Pédagogique', 'MATHEMATIQUES', 'L-073806', 'francophone', 'PLEG', NULL, '2024-07-09', '1993-10-04', 'DSCHANG', 'Nord-Ouest', 'Mezam', 'Santa', '2018-08-01', '670642487', 'Marié(e)', 1),
(485, 'TEBAH CLETUS WENIM', 'tebah.cletus.wenim@auditron.lbm', '67', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'ENSEIGNANT', 'PHYSICS', 'P-016016', 'anglophone', 'PCEG', NULL, '2025-04-09', '1990-05-02', 'BATIBO', 'Nord-Ouest', 'Momo', 'Batibo', '2019-03-11', '675359611', 'Marié(e)', 1),
(486, 'TEGHEN LAETICIA NGOH', 'teghen.laeticia.ngoh@auditron.lbm', '69', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'ENSEIGNANT', 'HISTORY', 'E-095443', 'anglophone', 'PCEG', NULL, '2018-04-12', '1995-03-01', 'EDEA', 'Nord-Ouest', 'Momo', 'Mbengwi', '2019-02-11', '650784477', 'Célibataire', 1),
(487, 'TIZI NAITTANG', 'tizinaittang@auditron.lbm', '70', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'ENSEIGNANT', 'CHINOIS', 'X-064387', 'francophone', 'PCEG', NULL, '2017-07-02', '1990-01-06', 'GUIDER', 'Nord', 'Mayo-Louti', 'Guider', '2018-08-01', '691417513', 'Célibataire', 1),
(489, 'AOULASSA MARIE NOELLE', 'aoulassa.marie.noelle@auditron.lbm', '75', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'enseignant', 'INFORMATIQUE', 'F187967', 'francophone', '', NULL, NULL, '1990-01-12', 'Maroua', 'Nord', '', '', '', '699018248', 'Célibataire', 1),
(490, 'FORSUH CALEB AKONGNUE', 'forsuh.caleb.akongnue@auditron.lbm', '76', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'enseignant', 'BIOLOGY', 'L074978', 'anglophone', '', NULL, NULL, '1995-08-03', 'Mandoumba', 'Ouest', 'Mezam', 'Bamenda 1er', '2018-10-04', '683750381', 'Célibataire', 1),
(491, 'ALI ATIKA', 'ali.atika@auditron.lbm', '77', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'enseignant', 'CHINOIS', '1063975A', 'francophone', '', NULL, NULL, '1987-08-04', 'MAGA', 'Extreme-Nord', 'Mayo-Danay', 'MAGA', '2018-08-01', '650121919', 'Célibataire', 1),
(492, 'ATEMAFAC ALPHONCIE FOTAMOH', 'atemafac.alphoncier@auditron.lbm', '78', '2025-11-07 15:25:46', '2026-01-11 12:52:16', 'Enseignant', 'CONSEILLER D\'ORIENTATION', 'R-183582', NULL, NULL, NULL, NULL, '1995-12-08', 'FOTABONLI', 'Sud-Ouest', 'Lebialem', NULL, '2024-06-09', '650393205', 'Célibataire', 1),
(493, 'MANGA CHI CLARA', 'manga.chi.clara@auditron.lbm', '79', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'enseignant', 'HISTORY', '768663R', 'anglophone', '', NULL, NULL, '1985-02-01', 'BUA', 'Nord-Ouest', 'Mezam', 'Bamenda 2ème', '2014-01-04', '670205359', 'Célibataire', 1),
(494, 'BUBOH BRIAND', 'buboh.briand@auditron.lbm', '80', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'enseignant', 'GEOLOGY', '1042281C', 'anglophone', '', NULL, NULL, '1987-10-01', 'YAOUNDE', 'Nord-Ouest', 'Mezam', '', '2016-03-03', '654806789', 'Célibataire', 1),
(495, 'NIAYAKO MOMO CAROLE DIANE', 'niayako.momo.carole.diane@auditron.lbm', '81', '2025-11-07 15:25:46', '2025-11-07 15:28:42', 'enseignant', 'MATHEMATIQUES', 'R200427', 'francophone', '', NULL, NULL, '1992-07-06', 'BERTOUA', 'Ouest', 'Menoua', 'Dschang', '2025-06-10', '696278546', 'Célibataire', 1),
(496, 'ALIOU MOHAMADOU', '', '82', NULL, NULL, 'enseignant', ' ', NULL, 'anglophone', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(498, 'ABDOUL WAHABOU', '/', '95', NULL, NULL, NULL, NULL, 'VAC', 'francophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(500, 'AOUDOU DOUGLAS', '/', '106', NULL, NULL, NULL, NULL, 'VAC', 'francophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(501, 'DIYI MADELEINE', '/', '94', NULL, NULL, NULL, NULL, 'VAC', 'francophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(504, 'DJIDERE AOUDOU MOUSSA', '/', '103', NULL, NULL, NULL, NULL, 'VAC', 'francophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(505, 'EBA EZIMBI ARNO', '/', '102', NULL, NULL, NULL, NULL, 'VAC', 'francophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(506, 'FATHER JOSEPH DOGO ANAGUEDEU', '/', '105', NULL, NULL, NULL, NULL, 'VAC', 'francophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(507, 'JUULES CESAR KOUNG', '/', '97', NULL, NULL, NULL, NULL, 'VAC', 'francophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(508, 'KEBBE KELLS', '/', '91', NULL, NULL, NULL, NULL, 'VAC', 'anglophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(509, 'KONSO OUMAROU', '/', '109', NULL, NULL, NULL, NULL, 'VAC', 'anglophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(510, 'LAISON DORIS', '/', '90', NULL, NULL, NULL, NULL, 'VAC', 'anglophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(511, 'MATCHEBAR ANNA', '/', '108', NULL, NULL, NULL, NULL, 'VAC', 'anglophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(512, 'MUZAM NAOMI', '/', '93', NULL, NULL, NULL, NULL, 'VAC', 'anglophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(513, 'NKWENTY SYLVIE', '/', '110', NULL, NULL, NULL, NULL, 'VAC', 'anglophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(514, 'NYOBE JEAN DANIEL', '/', '96', NULL, NULL, NULL, NULL, 'VAC', 'francophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(515, 'OUSSOUMANOU BABA', '/', '107', NULL, NULL, NULL, NULL, 'VAC', 'anglophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(517, 'RUBIN TANFO TANDO', '/', '92', NULL, NULL, NULL, NULL, 'VAC', 'anglophone', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(529, 'Nanga Chi Clara', 'clarananga7@gmail.com', '83', '2025-12-05 14:17:24', '2025-12-05 14:17:24', 'Enseignant', 'Pleg', '117', 'générale', NULL, 13, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1),
(532, 'Zoubo Clément', 'zouboclement@gmail.com', '111', '2026-01-16 10:56:19', '2026-01-16 11:01:47', 'Enseignant', 'Vacataire', '0001', 'générale', NULL, NULL, '2026-01-05', '1987-03-24', 'Garoua', NULL, NULL, NULL, NULL, '650625404', NULL, 1);

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `accreditation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tel` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `name`, `email`, `accreditation_id`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `tel`) VALUES
(1, 'Samuel TCHIOFFOUO TEDONGMEZA', 'artiscodec@gmail.com', 1, NULL, '$2y$12$N6u6Y7tBywSiMo6trLGSX.wFrj.scXZIZI4/P2oGcN2MUfXSLJOke', 'qiuOkR5FPQgSVLhoxgZFJZUESGrQoUzrQ4eOEx6JTRFhz3J90F8nzSiQ1Sn7', '2025-11-07 14:15:53', '2025-11-07 14:15:53', NULL),
(2, 'TCHIOFFOUO JEAN', 'tchiofjean@yahoo.fr', 1, NULL, '$2y$12$GTpFIVgex5EphR3P7uwbHusFnHXPv6CVyfQFBClEe7qe0xXiKhHLK', 'vKvMdctBtcPZ6fhaNKNaAMvevbJ3EaXrbMW6fKQpiwY7laWYYddQrrInx5YW', '2025-11-07 14:49:12', '2025-11-07 14:49:12', NULL),
(3, 'MOHAMADOU TOUKOUR', 'proviseur@lbm.aux', 1, NULL, '$2y$12$N7S225WbDPFNQereQdyY6u9VQKm0qYxA/OLbhKoG4nBPz9c2R78PW', 'SpTD8aw4BJ430wxljduP8VoHxac5yaO4sy6bNmGrkUHzhaBPAcIccjD3Rwj3', '2025-11-07 15:40:45', '2025-11-07 15:40:45', NULL),
(4, 'KILOH MARCEL JAB', 'kiloh.vp@lbm.aux', 3, NULL, '$2y$12$4hK.TtGyortHQE/.M/l6mOSdoQWQcOyrm4ZlAH55knLUc8czxiZni', 'gXCObTT9hHHV0iAd9cy4B5bbztLJbTOouShitZo3QzfK10axePEIdhvcs41k', '2025-11-07 15:41:40', '2025-11-07 15:41:40', NULL),
(5, 'ABBA ETIENNE', 'abba.sg@lbm.aux', 1, NULL, '$2y$12$tAlfBirmglKZMG5PAPf4y.Rljdk7k2Az6986xjgOR1XWMG9F3SRBS', NULL, '2025-11-07 15:42:29', '2025-11-07 15:42:29', NULL),
(6, 'ALANG UKAH JOSEPH', 'alang.vp@lbm.aux', 3, NULL, '$2y$12$.CLcLcS3i5JFPTfawNBF1uXzmZ2ZmOZoRAq7ny78pJAH4h/FpG8bK', NULL, '2025-11-07 15:43:55', '2025-11-07 15:43:55', NULL),
(7, 'NFON VICTOR MABUH', 'mabuh.vp@lbm.aux', 3, NULL, '$2y$12$p0ZTgKVakyYxMw2wuic4Zu2tt6hS5.An.QbNq/l74H6h1HZqPMyja', NULL, '2025-11-07 15:45:04', '2025-11-07 15:45:04', NULL),
(8, 'IBRAHIMA ELHADJI BABA', 'ibrahima.vp@lbm.aux', 2, NULL, '$2y$12$EiuAc44HIi1FQv/4WXcjue7NfTyGlB5U1j3vtjtdKCyZsGA1y3DEG', 'ZpcVb0C3LitcOxdRpy7pdjCz50fM14T95miQfIo8xCi1xWC0bOwA6PzornPq', '2025-11-07 15:45:59', '2025-11-07 15:45:59', NULL),
(9, 'AMINOU OUSMANOU', 'aminou.sos@lbm.aux', 1, NULL, '$2y$12$N6u6Y7tBywSiMo6trLGSX.wFrj.scXZIZI4/P2oGcN2MUfXSLJOke', NULL, '2025-11-07 15:47:40', '2025-11-07 15:47:40', NULL),
(10, 'TANTO MASHELINE NKAAH', 'tanto.sg@lbm.aux', 1, NULL, '$2y$12$XbbTJHzg/buhgTuawERq2ezbOTSFBUtsdjbDkdTI98CMdg9U1Poli', NULL, '2025-11-07 15:48:20', '2025-11-07 15:48:20', NULL),
(11, 'SALI SOUAIBOU', 'sali.sg@lbm.aux', 1, NULL, '$2y$12$G5hKxizBzcgajrUSERSaFOTMV9JTcdxBF5OV342qVEcRhTbka5TDS', NULL, '2025-11-07 15:49:01', '2025-11-07 15:49:01', NULL),
(12, 'PATAKERE SIAKBANG JOEL', 'patekere.vp@lbm.aux', 2, NULL, '$2y$12$pPWWoJqCSGl.Pj02Iapr/easLUcd4BfN3Z1t/TB4QHNdU3fD9kMJi', 'k4HYTnL1V9qlRLAakrOOsVJAJJ7lP9toGlas9nwNCvtW1nQAltNhHZoBBdgM', '2025-11-07 15:49:47', '2025-11-07 15:49:47', NULL),
(13, 'DJONG-YANG SAMUEL', 'djongyang.sss@lbm.aux', 1, NULL, '$2y$12$yd77ojdBdQUUdP3QVSji..lRIz0.3ha89I3EfQgqKqEU9vh8pyCd6', NULL, '2025-11-07 15:51:00', '2025-11-07 15:51:00', NULL),
(14, 'WAVOUM DIEUDONNE', 'sp.acc1@lbm.aux', 1, NULL, '$2y$12$w3RXse8cvOeIMFVcaSCo8OJqDCuzJodZe4DsfVBBVXj4EKCAJLdg6', NULL, '2025-12-05 13:27:25', '2025-12-05 13:27:25', NULL),
(15, 'AMADOU NAYIBI', 'sp.acc2@lbm.aux', 1, NULL, '$2y$12$oWfEeBMrQbdw5LS5twhMTO0PFhTU6IOlzpYVUzLy2XgxeC/1qYslm', 'QsYS4T28R30Es3Ps0sTr0ajUBbbzxZs4UOKsFoYTnSszYL5lnYs2E7KZy91E', '2025-12-05 13:35:57', '2025-12-05 13:35:57', NULL);
