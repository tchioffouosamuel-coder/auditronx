-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 15 sep. 2026 à 07:50
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
-- Base de données : `u332279927_auditron_lcm`
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
(2, 'Censeur', 'générale', NULL, NULL),
(3, 'Service des Sports', 'Service des sports', NULL, NULL),
(5, 'Surveillant Général', '*', NULL, NULL);

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
(62, '6e A', '6A', '6ème', 'Général', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(63, '6e B', '6B', '6ème', 'Général', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(64, '6e BIL', '6BIL', '6ème', 'Bilingue', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(65, '6e C', '6C', '6ème', 'Général', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(66, '6e D', '6D', '6ème', 'Général', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(67, '5e A', '5A', '5ème', 'Général', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(68, '5e B', '5B', '5ème', 'Général', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(69, '5e BIL', '5BIL', '5ème', 'Bilingue', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(70, '5e C', '5C', '5ème', 'Général', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(71, '4e ALL', '4ALL', '4ème', 'Allemand', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(72, '4e ARA', '4ARA', '4ème', 'Arabe', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(73, '4e BIL ALL', '4BILALL', '4ème', 'Bilingue Allemand', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(74, '4e BIL ARA', '4BILARA', '4ème', 'Bilingue Arabe', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(75, '4e BIL CHI', '4BILCHI', '4ème', 'Bilingue Chinois', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(76, '4e BIL ESP', '4BILESP', '4ème', 'Bilingue Espagnol', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(77, '4e BIL Italien', '4BILITA', '4ème', 'Bilingue Italien', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(78, '4e CHI', '4CHI', '4ème', 'Chinois', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(79, '4e ESP A', '4ESPA', '4ème', 'Espagnol A', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(80, '4e ESP B', '4ESPB', '4ème', 'Espagnol B', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(81, '4e ITAL', '4ITAL', '4ème', 'Italien', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(82, '4è Latin', '4LAT', '4ème', 'Latin', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(83, '3e ALL', '3ALL', '3ème', 'Allemand', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(84, '3e ARA', '3ARA', '3ème', 'Arabe', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(85, '3e BIL ALL', '3BILALL', '3ème', 'Bilingue Allemand', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(86, '3e BIL Arab', '3BILARA', '3ème', 'Bilingue Arabe', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(87, '3e BIL CHI', '3BILCHI', '3ème', 'Bilingue Chinois', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(88, '3e BIL ESP', '3BILESP', '3ème', 'Bilingue Espagnol', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(89, '3e CHI', '3CHI', '3ème', 'Chinois', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(90, '3e ESP A', '3ESPA', '3ème', 'Espagnol A', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(91, '3e ESP B', '3ESPB', '3ème', 'Espagnol B', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(92, '3e Latin', '3LAT', '3ème', 'Latin', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(93, '2nde ABI ESP', '2ABIESP', 'Seconde', 'ABI Espagnol', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(94, '2nde ABI ALL', '2ABIALL', 'Seconde', 'ABI Allemand', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(95, '2nde ABI ARA', '2ABIARA', 'Seconde', 'ABI Arabe', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(96, '2nde ABI CHI', '2ABICHI', 'Seconde', 'ABI Chinois', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(97, '2nde A4 ALL', '2A4ALL', 'Seconde', 'A4 Allemand', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(98, '2nde A4 ARA', '2A4ARA', 'Seconde', 'A4 Arabe', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(99, '2nde A4 CHI', '2A4CHI', 'Seconde', 'A4 Chinois', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(100, '2nde A4 ESP A', '2A4ESPA', 'Seconde', 'A4 Espagnol A', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(101, '2nde A4 ESP B', '2A4ESPB', 'Seconde', 'A4 Espagnol B', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(102, '2nde C1', '2C1', 'Seconde', 'C1', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(103, '2nde C2', '2C2', 'Seconde', 'C2', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(104, '1ère A4 ALL', '1A4ALL', 'Première', 'A4 Allemand', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(105, '1ère A4 ARA', '1A4ARA', 'Première', 'A4 Arabe', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(106, '1ère A4 CHI', '1A4CHI', 'Première', 'A4 Chinois', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(107, '1ère A4 ESP A', '1A4ESPA', 'Première', 'A4 Espagnol A', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(108, '1ère A4 ESP B', '1A4ESPB', 'Première', 'A4 Espagnol B', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(109, '1ère C', '1C', 'Première', 'C', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(110, '1ère D1', '1D1', 'Première', 'D1', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(111, '1ère D2', '1D2', 'Première', 'D2', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(112, 'Tle A4 ALL', 'TA4ALL', 'Terminale', 'A4 Allemand', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(113, 'Tle A4 ARA', 'TA4ARA', 'Terminale', 'A4 Arabe', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(114, 'Tle A4 CHI', 'TA4CHI', 'Terminale', 'A4 Chinois', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(115, 'Tle A4 ESP', 'TA4ESP', 'Terminale', 'A4 Espagnol', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(116, 'Tle C', 'TC', 'Terminale', 'C', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48'),
(117, 'Tle D', 'TD', 'Terminale', 'D', 0, NULL, '2025-10-18 01:07:48', '2025-10-18 01:07:48');

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
(168, 'FRANÇAIS', 'FRA', 5.00, 'Langue et littérature française', 'Lettres', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(169, 'ANGLAIS', 'ANG', 3.00, 'Langue anglaise', 'Langues', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(170, 'MATHS', 'MAT', 5.00, 'Mathématiques', 'Sciences Exactes', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(171, 'HIST/GEO/ECM', 'HGE', 3.00, 'Histoire, Géographie et Education à la Citoyenneté et à la Morale', 'Sciences Humaines', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(172, 'ALLEMAND', 'ALL', 3.00, 'Langue allemande', 'Langues', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(173, 'ESPAGNOL', 'ESP', 3.00, 'Langue espagnole', 'Langues', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(174, 'ARABE', 'ARA', 3.00, 'Langue arabe', 'Langues', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(175, 'PHILOSOPHIE', 'PHI', 4.00, 'Philosophie', 'Lettres', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(176, 'INFORMATIQUE', 'INF', 2.00, 'Informatique et Technologies de l\'Information', 'Sciences et Technologies', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 1),
(177, 'SCIENCES', 'PHY', 4.00, 'Physique et Chimie', 'Sciences Exactes', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 1),
(178, 'SVT', 'SVT', 4.00, 'Sciences de la Vie et de la Terre', 'Sciences de la Vie', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 1),
(179, 'EPS', 'EPS', 2.00, 'Education Physique et Sportive', 'ss', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 1),
(180, 'CHINOIS', 'CHI', 3.00, 'Langue chinoise', 'Langues', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(181, 'ITALIEN', 'ITA', 3.00, 'Langue italienne', 'Langues', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(182, 'LATIN', 'LAT', 2.00, 'Latin', 'Langues Anciennes', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(183, 'TLC', 'TLC', 2.00, 'Techniques de Communication et Langues Nationales', 'Lettres', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(184, 'ART', 'ART', 2.00, 'Arts Plastiques et Education Artistique', 'Arts', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 1),
(185, 'TM', 'TM', 2.00, 'Travaux Manuels', 'Technique', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 1),
(186, 'ESF', 'ESF', 2.00, 'Economie Sociale et Familiale', 'Sciences Humaines', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 1),
(187, 'ORIENTATION SCOLAIRE', 'ORI', 1.00, 'Orientation et Conseil Scolaire', 'Administration Pédagogique', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 0),
(188, 'PCT', 'PCT', 4.00, 'Physique et Chimie', 'Sciences Exactes', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 1),
(189, 'PHYSIQUE', 'PHY', 4.00, 'Physique et Chimie', 'Sciences Exactes', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 1),
(190, 'CHIMIE', 'CHIM', 4.00, 'Physique et Chimie', 'Sciences Exactes', '2025-10-18 01:14:27', '2025-10-18 01:14:27', 1),
(191, 'LANGUE ET CULTURE NATIONALE', 'LCN', 1.00, NULL, '*', NULL, NULL, 0),
(192, 'MANUAL LABOUR', 'MALB', 1.00, NULL, '*', NULL, NULL, 0),
(193, 'CITIZENSHIP', 'CITZ', 1.00, NULL, '*', NULL, NULL, 0);

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
(321, 'BOUBA BORIS', 'b.boris@lycee.cm', '3', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Censeur', NULL, '0748431T', 'Administration', 'PLEG', NULL, '2011-01-03', NULL, NULL, NULL, NULL, NULL, '2017-09-10', NULL, NULL, 2),
(322, 'MAIGARI MBARANDI', 'm.mbarandi@lycee.cm', '4', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Censeur', NULL, '0727609A', 'Administration', 'PLEG', NULL, '2011-01-03', NULL, NULL, NULL, NULL, NULL, '2018-12-11', NULL, NULL, 2),
(323, 'HOHI CLARISSE MIREILLE', 'h.clarisse@lycee.cm', '5', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Censeur', NULL, '0653802H', 'Administration', 'PLEG', NULL, '2008-04-17', NULL, NULL, NULL, NULL, NULL, '2017-09-12', NULL, NULL, 2),
(324, 'REMAILA RIGOBERT', 'r.rigobert@lycee.cm', '6', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Censeur', NULL, '0771242E', 'Administration', 'PLEG', NULL, '2013-02-14', NULL, NULL, NULL, NULL, NULL, '2024-01-08', NULL, NULL, 2),
(325, 'TCHOUFA NZOUENJA ERIC PATRIC', 't.eric@lycee.cm', '7', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Censeur', NULL, '0666981M', 'Administration', 'PLEG', NULL, '2008-06-02', NULL, NULL, NULL, NULL, NULL, '2015-08-24', NULL, NULL, 2),
(326, 'OUSSOUMANOU AHMADOU', 'o.ahmadou@lycee.cm', '8', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Censeur', NULL, '0771553A', 'Administration', 'PLEG', NULL, '2019-08-30', NULL, NULL, NULL, NULL, NULL, '2024-09-06', NULL, NULL, 2),
(327, 'FIMANOU TACKA SOPHIE Epse DOURA', 'f.sophie@lycee.cm', '9', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant Général', NULL, '07482324J', 'Administration', 'PLEG', NULL, '2011-01-03', NULL, NULL, NULL, NULL, NULL, '2014-08-26', NULL, NULL, 2),
(328, 'ZE ANDA MARTIAL', 'z.martial@lycee.cm', '10', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant Général', NULL, '0667083B', 'Administration', 'PCEG', NULL, '2008-06-03', NULL, NULL, NULL, NULL, NULL, '2012-08-27', NULL, NULL, 2),
(329, 'BELLO ERNEST', 'b.ernest@lycee.cm', '11', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Intendant', NULL, '0589946I', 'Administration', 'IEG', NULL, '1990-09-25', NULL, NULL, NULL, NULL, NULL, '2017-08-28', NULL, NULL, 2),
(330, 'SAIDOU DOURA CHRISTOPHE', 's.christophe@lycee.cm', '12', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant Général', NULL, '0727445C', 'Administration', 'PLEG', NULL, '2011-01-03', NULL, NULL, NULL, NULL, NULL, '2015-05-25', NULL, NULL, 2),
(331, 'EBONGUE NDONGUE ALEXANDRE', 'e.alexandre@lycee.cm', '13', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant Général', NULL, '0753900M', 'Administration', 'PLEG', NULL, '2011-01-03', NULL, NULL, NULL, NULL, NULL, '2015-09-04', NULL, NULL, 2),
(332, 'GOD MAILA LEOPOLD JOSUE', 'g.leopold@lycee.cm', '14', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant Général', NULL, '0770985W', 'Administration', 'PCEG', NULL, '2013-02-04', NULL, NULL, NULL, NULL, NULL, '2018-12-05', NULL, NULL, 2),
(333, 'NDOUWE RESSALA', 'n.ressala@lycee.cm', '15', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant Général', NULL, '0634661T', 'Administration', 'PLEG', NULL, '2004-05-07', NULL, NULL, NULL, NULL, NULL, '2024-01-05', NULL, NULL, 2),
(334, 'VROUMSIA RAYMOND', 'v.raymond@lycee.cm', '16', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant Général', NULL, '0774497F', 'Administration', 'PCEG', NULL, '2013-02-04', NULL, NULL, NULL, NULL, NULL, '2018-12-10', NULL, NULL, 2),
(335, 'WAHALE PASCAL', 'w.pascal@lycee.cm', '17', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant Général', NULL, '1009432W', 'Administration', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2024-09-06', NULL, NULL, 2),
(337, 'DOBA OLIVIER', 'd.olivier@lycee.cm', '19', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'CHEF SCE SPORTS SCOLAIRES', NULL, '1034945R', 'Administration', 'PEPS', NULL, '2014-10-21', NULL, NULL, NULL, NULL, NULL, '2020-01-13', NULL, NULL, 2),
(338, 'MVEING SERAPHIN', 'm.seraphin@lycee.cm', '20', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'CS APPS', NULL, '0762195J', 'Administration', 'MEPS', NULL, '2011-08-29', NULL, NULL, NULL, NULL, NULL, '2024-01-05', NULL, NULL, 2),
(340, 'DJAINABOU AMADOU', 'djainabou.amadou@lycee.cm', '22', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'FRANÇAIS', '1040254H', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(341, 'KENGNI DJOUATSA NADIA REINE', 'kengni.nadia@lycee.cm', '23', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'FRANÇAIS', 'VAC', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(342, 'YING YANG MAÏDEDO EDWIGE VIVIANE', 'ying.edwige@lycee.cm', '24', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'FRANÇAIS', '0774535A', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(343, 'WOUDAMI SIMON', 'woudami.simon@lycee.cm', '25', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'ANGLAIS', '0774535A', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(344, 'MINKULU ESTHER', 'minkulu.esther@lycee.cm', '26', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'ANGLAIS', '1200648L', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(345, 'MARSOU OLIVIER KAIMISSINA', 'marsou.olivier@lycee.cm', '27', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'MATHS', '1005756Q', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(346, 'ASTA CHRISTIANE', 'asta.christiane@lycee.cm', '28', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'MATHS', '0767240M', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(348, 'BOYOMO BOYOMO JEAN PAUL', 'boyomo.jean@lycee.cm', '30', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'MATHS', '1005705T', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(349, 'POULKE MARIE GISÈLE', 'poulke.marie@lycee.cm', '31', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'HIST/GEO/ECM', '1034430B', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(350, 'ZOULAIHA', 'zoulaiha@lycee.cm', '32', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'HIST/GEO/ECM', '1005593A', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(351, 'YOUSSOUFA DIEUDONNÉ', 'youssoufa.dieudonne@lycee.cm', '33', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'HIST/GEO/ECM', '1005583Z', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(352, 'HAMADOU', 'hamadou@lycee.cm', '34', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'HIST/GEO/ECM', '1039358Y', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(353, 'MOHAMADOU SANI', 'mohamadou.sani@lycee.cm', '35', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'HIST/GEO/ECM', '1034856Z', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(354, 'NGUIENDOU MVOUTTI CHRISTELLE épse LECKE', 'nguiendou.christelle@lycee.cm', '36', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'HIST/GEO/ECM', '1086753J', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(355, 'ABDOUL KARIMOU', 'abdoul.karimou@lycee.cm', '37', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'HIST/GEO/ECM', '1005797L', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(356, 'KIKMO EPERVIER', 'kikmo.epervier@lycee.cm', '38', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'HIST/GEO/ECM', '0782504E', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(357, 'ABBA JONATHAN', 'abba.jonathan@lycee.cm', '39', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'ESPAGNOL', '0767107W', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(358, 'NSOHNWE MBOUKA DOLARICE', 'nsohnwe.dolarice@lycee.cm', '40', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'ESPAGNOL', '1086792U', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(359, 'MOHAMADOU AMINOU', 'mohamadou.aminou@lycee.cm', '41', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'ARABE', '1094671R', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(360, 'HADIDJA AMADOU', 'hadidja.amadou@lycee.cm', '42', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'ARABE', '1187899Y', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(361, 'KOMNANG ARIEL CLAUDE', 'komnang.ariel@lycee.cm', '43', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'PHILOSOPHIE', '0790444P', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(362, 'ABDOURAMAN YAKOUBOU', 'abdouraman.yakoubou@lycee.cm', '44', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'PHILOSOPHIE', '1047103Q', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(363, 'ABDOUL AZIZ FECHAL', 'abdoul.aziz@lycee.cm', '45', '2025-10-18 01:07:48', '2025-11-10 21:46:50', 'Animateur Pédagogique', 'INFORMATIQUE', '1036603T', 'générale', 'PLEG', 10, '2015-04-29', '1984-11-28', 'NGAZI', 'ADAMAOUA', 'MBERE', 'MEIGANGA', '2015-04-13', '679376730', 'M1', 2),
(364, 'MBITA KOULOU CHARLES', 'mbita.charles@lycee.cm', '46', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'INFORMATIQUE', '1147602V', 'générale', 'PCET P', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(365, 'DANG HANS FREDDY VALDEZ', 'dang.hans@lycee.cm', '47', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'INFORMATIQUE', '183870J', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(366, 'ESSAME EKOTTO ANGELO', 'essame.angelo@lycee.cm', '48', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'INFORMATIQUE', '1223399R', 'générale', 'PLET P', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(367, 'DJONTU KENGNE RICHARD', 'djontu.richard@lycee.cm', '49', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SCIENCES PHYSIQUES', '0782914M', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(368, 'PALOUMA WADIEBE EMMANUEL', 'palouma.emmanuel@lycee.cm', '50', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SCIENCES PHYSIQUES', '0787659P', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(369, 'SOGODOK EMMANUEL', 'sogodok.emmanuel@lycee.cm', '51', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SCIENCES PHYSIQUES', 'VAC', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(370, 'NENKAM ELVIS DIEUDONNÉ', 'nenkam.elvis@lycee.cm', '52', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SCIENCES PHYSIQUES', '0757550E', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(371, 'GNAZOKE PAUL', 'gnazoke.paul@lycee.cm', '53', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SCIENCES PHYSIQUES', '1013055R', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(372, 'ABDOULAYE SALI', 'abdoulaye.sali@lycee.cm', '54', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SCIENCES PHYSIQUES', '1006398A', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(373, 'ZOUBO CLÉMENT', 'zoubo.clement@lycee.cm', '55', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SCIENCES PHYSIQUES', '1047098Q', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(374, 'GUISWÉ DAVID', 'guiswe.david@lycee.cm', '56', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SCIENCES PHYSIQUES', '1007207Z', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(375, 'MAHAMAT DAKSOUANGA', 'mahamat.daksouanga@lycee.cm', '57', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SVT', '0734870P', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(376, 'MAMOUDOU SAIDOU SAIDOU', 'mamoudou.saidou@lycee.cm', '58', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SVT', '1005754Y', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(377, 'LECKE ALAIN', 'lecke.alain@lycee.cm', '59', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SVT', '0781198M', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(378, 'LINIDA SYLVIE ép MAÏGARI MBARANDI', 'linida.sylvie@lycee.cm', '60', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'SVT', '1052087B', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(379, 'HASSAN HAMADAMA AIMÉ', 'hassan.aime@lycee.cm', '61', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'EPS', '1048886W', 'ss', 'PAEPS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(380, 'MARAKE NINA', 'marake.nina@lycee.cm', '62', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'EPS', '1044328A', 'ss', 'MEPS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(381, 'NGAPOUT MAMA', 'ngapout.mama@lycee.cm', '63', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'CHINOIS', '1043946Q', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(382, 'Enseignant CHINOIS 2', 'chinois2@lycee.cm', '64', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'CHINOIS', '1084042Z', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(383, 'Enseignant CHINOIS 3', 'chinois3@lycee.cm', '65', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'CHINOIS', '1086841J', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(384, 'MOHAMADOU GAMBO', 'mohamadou.gambo@lycee.cm', '66', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'ITALIEN', '1198770N', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(385, 'NSIMI ONGUENE MATHIEUX', 'nsimi.mathieux@lycee.cm', '67', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'LATIN', '1095087V', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(386, 'POUTOUMGNIGNI FADIMATOU ZARAHOU', 'poutoumgnigni.fadimatou@lycee.cm', '68', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant', 'TLC', 'VAC', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(387, 'MEIRO GARBA VICTORINE', 'meiro.victorine@lycee.cm', '69', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Secrétaire', NULL, 'VAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(389, 'MONGOM NINA CLÉMENCE', 'mongom.clemence@lycee.cm', '71', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Infirmière', NULL, 'VAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(390, 'ABAG SERASIN EMMANUEL', 'abag.emmanuel@lycee.cm', '72', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant de secteur', NULL, 'VAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(391, 'WAI-MBE WANG-FEO SIMON', 'waimbe.simon@lycee.cm', '73', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant de secteur', NULL, 'VAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(392, 'POUM ETIENNE', 'poum.etienne@lycee.cm', '74', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Surveillant de secteur', NULL, 'VAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(393, 'MAÏWA MARCEL', 'maiwa.marcel@lycee.cm', '75', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Gardien de nuit', NULL, 'VAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(394, 'GOURDA YOUMANIGUE', 'gourda.youmanigue@lycee.cm', '76', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Gardien de nuit', NULL, 'VAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(395, 'WADJIRI DOKO ALAIN', 'wadjiri.alain@lycee.cm', '77', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Gardien de nuit', NULL, 'VAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(396, 'ABBA PHILIPPE', 'abba.philippe@lycee.cm', '78', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'ANGLAIS', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(397, 'AOUDOU DJIDERE', 'aoudou.djidere@lycee.cm', '79', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'ANGLAIS', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(398, 'DJIDER AOUDOU MOUSSA', 'djider.moussa@lycee.cm', '80', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'ANGLAIS', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(399, 'DOUIH OUSSEINI DIANE', 'douih.diane@lycee.cm', '81', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'ANGLAIS', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(400, 'GUIDADI LAURENT', 'guidadi.laurent@lycee.cm', '82', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'ANGLAIS', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(401, 'MIADDA DOUMDJA BLANDINE', 'miadda.blandine@lycee.cm', '83', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'ANGLAIS', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(402, 'NGOMBA EPANE IDRISS PASCAL', 'ngomba.idriss@lycee.cm', '84', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'FRANÇAIS', 'VAC', 'générale', 'PLEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(403, 'HARANG NGA JÉRÉMIE', 'harang.jeremie@lycee.cm', '85', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'FRANÇAIS', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(404, 'WAYANG NESTOR', 'wayang.nestor@lycee.cm', '86', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'FRANÇAIS', 'VAC', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(405, 'DIRO HAMADOU', 'diro.hamadou@lycee.cm', '87', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'FRANÇAIS', 'VAC', 'générale', 'IEMP', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(406, 'IYAWA KISITO BONIFACE', 'iyawa.kisito@lycee.cm', '88', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'FRANÇAIS', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(407, 'NGOMNA ROGER', 'ngomna.roger@lycee.cm', '89', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'ALLEMAND', 'VAC', 'générale', 'PCEG', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(408, 'MME HOUNSEBE', 'hounsebe@lycee.cm', '90', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'HIST/GEO', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(409, 'NGOUNTE PATOUMA', 'ngounte.patouma2@lycee.cm', '91', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'HISTOIRE', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(410, 'HIBOUAKEU FOFACK', 'hibouakeu.fofack@lycee.cm', '92', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'GEOGRAPHIE', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(411, 'HAMADOU SAMSON', 'hamadou.samson@lycee.cm', '93', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'LANGUES ET CULTURES NATIONALES', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(412, 'EDIMO', 'edimo@lycee.cm', '94', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'EPS', 'VAC', 'ss', 'MEPS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(413, 'NDONGMUH LIBERTÉ', 'ndongmuh.liberte@lycee.cm', '95', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'EPS', 'VAC', 'ss', 'PEPS', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(414, 'MME MARSOU', 'marsou@lycee.cm', '96', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'ART, ESF', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(415, 'MME WIWA', 'wiwa@lycee.cm', '97', '2025-10-18 01:07:48', '2025-10-18 01:07:48', 'Enseignant Vacataire', 'ART, ESF', 'VAC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(425, 'MARAKE NINA', 'm.nina@lycee.cm', '98', '2025-11-10 12:32:41', '2025-11-10 12:32:41', 'Enseignant', 'MEPS', '1044328A', 'générale', NULL, 10, '2025-09-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(426, 'MME WIWA Epse YINGWE', 'w.wiwa@lycee.cm', '99', '2025-11-10 20:43:11', '2025-11-10 20:43:11', 'Enseignant', 'IET', 'VAC', 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(427, 'Mme ADJIMI SAMBO', 'a.sambo@lycee.cm', '100', '2025-11-10 20:55:32', '2025-11-10 20:55:32', 'Vacataire', 'VAC', 'VAC', 'générale', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(428, 'GUIATEU EPSE EPANE', 'g.epane@lycee.cm', '70', '2025-11-11 22:02:56', '2025-11-11 22:02:56', 'Vacataire', NULL, 'VACATAIRE', 'générale', NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(429, 'Mme ADOUA', 'adoua@lycee.cm', '101', '2025-11-11 22:09:38', '2025-11-11 22:09:38', 'Conseille d\'orientation', NULL, NULL, 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(430, 'KAMDEM TCHUAM JEAN FRANÇOIS', 'kamdem@lycee.cm', '102', '2025-11-11 22:21:00', '2025-11-11 22:21:00', 'Conseille d\'orientation', 'CPO SUP', NULL, 'générale', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(431, 'WAÏBE Simon', 'marceltabouli@gmail.com', '1', '2025-11-18 10:06:36', '2025-11-18 10:06:36', 'Vacataire', NULL, NULL, NULL, NULL, NULL, '2025-09-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(432, 'BOUBA PANENG PANENG', 'b.paneng@lycee.cm', '18', '2025-11-21 08:24:21', '2025-11-21 08:24:21', 'Enseignant', 'PCEG', '1005705T', 'générale', NULL, 12, '2025-09-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(435, 'REMAILA RIGOBERT', 'herrremailarigo@gmail.com', '103', '2026-01-27 07:51:17', '2026-01-27 07:51:17', 'Censeur', 'PLEG', '0771242E', NULL, NULL, 13, '2025-09-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2),
(436, 'Xee', 'admin@sygest.fgt', '2', '2026-02-16 16:06:34', '2026-02-16 16:06:34', 'Enseignant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2);

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
(1, 'SuperAdmin', 'artiscodec@gmail.com', 1, NULL, '$2y$12$xydyQBn7i17mVBOVCvFInOXciODghNHJO4s9HDiE/YhTvJVTpZypa', NULL, NULL, NULL, NULL),
(2, 'Superman', 'tchiofjean1@yahoo.fr', 1, NULL, '$2y$12$xydyQBn7i17mVBOVCvFInOXciODghNHJO4s9HDiE/YhTvJVTpZypa', 'SgsM5RuWiXOTZzVhNETtwHpivZCzPurQVP46JBgXBqlgZvN4Xwh9fTbCDvI2', NULL, '2025-11-08 04:05:34', NULL),
(3, 'BOUBA BORIS', 'b.boris@lycee.cm', 2, NULL, '$2y$12$X3e2DOCJIENnKr6REZynQ.Ik6lvid6letjLqlVSagBRoNwD6PclMa', NULL, '2025-11-05 05:10:47', '2025-11-05 05:10:47', NULL),
(4, 'MAIGARI MBARANDI', 'm.mbarandi@lycee.cm', 2, NULL, '$2y$12$MzZGcHzynE1slMVp8EDjruk8qMnfGIvTVHWYWHPJ1TpnPeI.GfG2u', NULL, '2025-11-05 05:11:48', '2025-11-05 05:11:48', NULL),
(5, 'HOHI CLARISSE MIREILLE', 'h.clarisse@lycee.cm', 2, NULL, '$2y$12$YKPOskzVDj7Qet8/haFhteWg1r7FaS9lQgysnZlcEn7ddRzZpfnLa', 'ZY6UE4uh87khpJEQNn9RGnzzDAsRRbToUhUc3gUDnREUtlbsTgPVCoOYAUWg', '2025-11-05 05:12:47', '2025-11-05 05:12:47', NULL),
(6, 'REMAILA RIGOBERT', 'r.rigobert@lycee.cm', 2, NULL, '$2y$12$JosYAp0iQRj7/3PKZw7we.DEWGJ2gs2Dlf6Yv2bbfOM/JSMnxKDdq', NULL, '2025-11-05 05:13:35', '2025-11-05 05:13:35', NULL),
(7, 'TCHOUFA NZOUENJA ERIC PATRIC', 't.eric@lycee.cm', 2, NULL, '$2y$12$DhBepBRzPd/M.fHYzznPnuj.y9HPwCohvrEQNGHtZl6Dppgag2Jd2', 'VhO0FU9MiNFpCiqUT70cRL74b4kQbB6HJUjUW6TForwgucYbFCiXUxyHvufV', '2025-11-05 05:14:12', '2025-11-05 05:14:12', NULL),
(8, 'OUSSOUMANOU AHMADOU', 'o.ahmadou@lycee.cm', 2, NULL, '$2y$12$E7ltNIVWENZ1kLnWdz4T3ueNAiCh85N5/IeDTzpaWJuUKrR5PP6TC', NULL, '2025-11-05 05:14:50', '2025-11-05 05:14:50', NULL),
(9, 'DOBA OLIVIER', 'd.olivier@lycee.cm', 3, NULL, '$2y$12$R8i0wrBOMzEoQsK14LQiiuUAxxWww/vEwCxcMM9pfPYbTqRizU3lq', NULL, '2025-11-05 05:15:32', '2025-11-05 05:15:32', NULL),
(10, 'FIMANOU TACKA SOPHIE Epse DOURA', 'f.sophie@lycee.cm', 1, NULL, '$2y$12$dmuklSPNEk6zil53htquseZc57x8S5bWOFU.cB/8n6qFLUB4Ol5J2', NULL, '2025-11-05 05:16:33', '2025-11-05 05:16:33', NULL),
(11, 'ZE ANDA MARTIAL', 'z.martial@lycee.cm', 1, NULL, '$2y$12$9vI2Ivms9h8NLWUPLhl0VeOTP6hJdOyvduZXrlMRGnG2JMEE8Nxlq', NULL, '2025-11-05 05:17:24', '2025-11-05 05:17:24', NULL),
(12, 'SAIDOU DOURA CHRISTOPHE', 's.christophe@lycee.cm', 1, NULL, '$2y$12$nm13u7ojCmnC1CFXyYTyzeei/KvBYKw63Eyt9D0cK1ret5hSnmk1i', NULL, '2025-11-05 05:18:04', '2025-11-05 05:18:04', NULL),
(13, 'EBONGUE NDONGUE ALEXANDRE', 'e.alexandre@lycee.cm', 1, NULL, '$2y$12$j2Ms7we0ZumfkrwS72fDVuL7uezpHYWbcBuXUbSGpdK0xiuO3/jQW', NULL, '2025-11-05 05:18:33', '2025-11-05 05:18:33', NULL),
(14, 'GOD MAILA LEOPOLD JOSUE', 'g.leopold@lycee.cm', 1, NULL, '$2y$12$dACJly59vzoht4FYaqoxH.CtQYq.fjHCQhJ03BTxmexfp/mUAvyL6', NULL, '2025-11-05 05:19:05', '2025-11-05 05:19:05', NULL),
(15, 'NDOUWE RESSALA', 'n.ressala@lycee.cm', 1, NULL, '$2y$12$drIBY3ndpDZ7sXleVzrnIeP8Gv1DvmlA3XOL6LMETHJTnedXBA0aK', NULL, '2025-11-05 05:19:43', '2025-11-05 05:19:43', NULL),
(16, 'VROUMSIA RAYMOND', 'v.raymond@lycee.cm', 1, NULL, '$2y$12$l3hwBdberITNtaAA/G0pIOyA7WHJoqPtCGUWLgST/1c//o08VSQRy', NULL, '2025-11-05 05:20:48', '2025-11-05 05:20:48', NULL),
(17, 'WAHALE PASCAL', 'w.pascal@lycee.cm', 1, NULL, '$2y$12$mM.a0f979rh3xDUKRR6fjuHyJoEffq7y0w6ZE/N.Y0nGxaZ9OeEd6', 'aTiVXjbD2dGiEtOhgx1WZw88OusrnU2T6V2CVo0r5cxo0mfAmzJ4bn4Z3aK4', '2025-11-05 05:21:23', '2025-11-05 05:21:23', NULL),
(18, 'MVEINKEMI MVEINDJI GUIY SYMPLICE', 'proviseur@lycee.cm', 1, NULL, '$2y$12$rNfc5B8PcZ.rJSR6qaBf3e2JRvuc3IMgEErdgPGpZj54MG/ZX8cbm', 'gwJ5X2RAJnCmlm60pJjE6PqfbfBJxqxwB06JQvZ6LdpCVzaXpggAdlMSkHnZ', '2025-11-05 05:23:52', '2025-11-05 05:23:52', NULL),
(19, 'SuperAdmin', 'joshuatchioffouo@gmail.com', 1, NULL, '$2y$12$xydyQBn7i17mVBOVCvFInOXciODghNHJO4s9HDiE/YhTvJVTpZypa', 'SgsM5RuWiXOTZzVhNETtwHpivZCzPurQVP46JBgXBqlgZvN4Xwh9fTbCDvI2', NULL, NULL, NULL);
