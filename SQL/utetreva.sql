-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Počítač: 127.0.0.1
-- Vytvořeno: Pon 16. úno 2026, 16:47
-- Verze serveru: 10.4.32-MariaDB
-- Verze PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Databáze: `utetreva`
--

-- --------------------------------------------------------

--
-- Struktura tabulky `event`
--

CREATE TABLE `event` (
  `ID` int(11) NOT NULL,
  `Title_cs` varchar(255) NOT NULL,
  `Title_de` varchar(255) NOT NULL,
  `Title_en` varchar(255) NOT NULL,
  `Title_ru` varchar(255) NOT NULL,
  `Description_cs` varchar(255) NOT NULL,
  `Description_de` varchar(255) NOT NULL,
  `Description_en` varchar(255) NOT NULL,
  `Description_ru` varchar(255) NOT NULL,
  `Date_from` date NOT NULL,
  `Date_to` date NOT NULL,
  `Image` tinyint(1) NOT NULL,
  `Image_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `food`
--

CREATE TABLE `food` (
  `ID` int(11) NOT NULL,
  `Name_cs` varchar(255) NOT NULL,
  `Name_de` varchar(255) NOT NULL,
  `Name_en` varchar(255) NOT NULL,
  `Name_ru` varchar(255) NOT NULL,
  `Description_cs` varchar(255) NOT NULL,
  `Description_de` varchar(255) NOT NULL,
  `Description_en` varchar(255) NOT NULL,
  `Description_ru` varchar(255) NOT NULL,
  `Price` int(8) NOT NULL,
  `Category` int(16) NOT NULL,
  `Archived` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `galleries`
--

CREATE TABLE `galleries` (
  `id` int(10) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` enum('pension','restaurace','kladska') NOT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `opening_exceptions`
--

CREATE TABLE `opening_exceptions` (
  `id` int(11) NOT NULL,
  `day` date NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `opens` time DEFAULT NULL,
  `closes` time DEFAULT NULL,
  `overnight` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Vypisuji data pro tabulku `opening_exceptions`
--

INSERT INTO `opening_exceptions` (`id`, `day`, `is_closed`, `opens`, `closes`, `overnight`, `note`) VALUES
(6, '2025-12-24', 1, NULL, NULL, 0, NULL),
(7, '2025-12-23', 1, NULL, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Struktura tabulky `opening_hours`
--

CREATE TABLE `opening_hours` (
  `id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `opens` time NOT NULL,
  `closes` time NOT NULL,
  `overnight` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Vypisuji data pro tabulku `opening_hours`
--

INSERT INTO `opening_hours` (`id`, `day_of_week`, `opens`, `closes`, `overnight`) VALUES
(1, 1, '11:00:00', '22:00:00', 0),
(2, 2, '11:00:00', '22:00:00', 0),
(3, 3, '11:00:00', '22:00:00', 0),
(4, 4, '11:00:00', '22:00:00', 0),
(5, 5, '11:00:00', '22:00:00', 0),
(6, 6, '11:00:00', '22:00:00', 0),
(7, 7, '11:00:00', '22:00:00', 0);

-- --------------------------------------------------------

--
-- Struktura tabulky `reservations`
--

CREATE TABLE `reservations` (
  `ID` int(11) NOT NULL,
  `First` varchar(64) NOT NULL,
  `Second` varchar(64) NOT NULL,
  `Mail` varchar(255) NOT NULL,
  `Tel` varchar(16) NOT NULL,
  `Person` int(8) NOT NULL,
  `Child` int(11) NOT NULL,
  `Baby` int(11) NOT NULL,
  `Date_from` date NOT NULL,
  `Date_to` date NOT NULL,
  `Card_id` int(32) NOT NULL,
  `Nationality` varchar(11) NOT NULL,
  `Town` varchar(64) NOT NULL,
  `Town_part` varchar(64) NOT NULL,
  `Street` varchar(128) NOT NULL,
  `Birth` date DEFAULT NULL,
  `Dog` tinyint(1) NOT NULL,
  `Dog_count` int(11) NOT NULL,
  `Note` text NOT NULL,
  `Solved` tinyint(1) NOT NULL,
  `Old` tinyint(1) NOT NULL,
  `totalPrice` int(11) NOT NULL,
  `Deposit` int(11) NOT NULL,
  `Gdpr` datetime NOT NULL,
  `Newsletter` tinyint(1) NOT NULL,
  `unsubscribe_token` varchar(255) NOT NULL,
  `Locale` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reservation_comments`
--

CREATE TABLE `reservation_comments` (
  `ID` int(11) NOT NULL,
  `reservation_id` int(11) NOT NULL,
  `Note` text NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reservation_room`
--

CREATE TABLE `reservation_room` (
  `reservation_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `reservation_table`
--

CREATE TABLE `reservation_table` (
  `ID` int(11) NOT NULL,
  `First` varchar(64) NOT NULL,
  `Second` varchar(64) NOT NULL,
  `Mail` varchar(255) NOT NULL,
  `Tel` varchar(32) NOT NULL,
  `Date` date NOT NULL,
  `Time` time NOT NULL,
  `Person` int(11) NOT NULL,
  `Big_group` tinyint(1) NOT NULL,
  `Solved` tinyint(1) NOT NULL,
  `Note` varchar(255) NOT NULL,
  `Gdpr` datetime NOT NULL,
  `Newsletter` tinyint(1) NOT NULL,
  `Locale` varchar(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `rooms`
--

CREATE TABLE `rooms` (
  `ID` int(11) NOT NULL,
  `Name` varchar(32) NOT NULL,
  `Price` int(8) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Vypisuji data pro tabulku `rooms`
--

INSERT INTO `rooms` (`ID`, `Name`, `Price`) VALUES
(1, 'Apartmán', 3400),
(2, 'Pokoj 1', 2550),
(3, 'Pokoj 2', 1700),
(4, 'Pokoj 3', 1700),
(5, 'Pokoj 4', 1700),
(6, 'Pokoj 5', 1700);

-- --------------------------------------------------------

--
-- Struktura tabulky `room_cleaning`
--

CREATE TABLE `room_cleaning` (
  `room_id` int(11) NOT NULL,
  `day` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `table_comments`
--

CREATE TABLE `table_comments` (
  `ID` int(10) UNSIGNED NOT NULL,
  `table_id` int(11) NOT NULL,
  `Note` varchar(255) NOT NULL,
  `Created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Vypisuji data pro tabulku `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', '$2a$12$XtTGME2RWNBX00A.8PQOnu7leRhPQH5DuUHNyTiHzuOMmMrSM2YbS', 'admin'),
(2, 'kladska', '$2y$10$Ai36f9gt36UO69YEtq2yseJ5aUIz8OsVdqNeF0mFf8O2vCayeouEm', 'user');

-- --------------------------------------------------------

--
-- Struktura tabulky `voucher`
--

CREATE TABLE `voucher` (
  `ID` int(11) NOT NULL,
  `Code` int(11) DEFAULT NULL,
  `Date` date DEFAULT NULL,
  `First` varchar(64) NOT NULL,
  `Second` varchar(64) NOT NULL,
  `Mail` varchar(255) NOT NULL,
  `Amount` int(11) NOT NULL,
  `Note` varchar(255) NOT NULL,
  `Solved` tinyint(1) NOT NULL,
  `Paid` tinyint(1) NOT NULL,
  `Vs` int(32) NOT NULL,
  `Gdpr` datetime NOT NULL,
  `Newsletter` tinyint(1) NOT NULL,
  `unsubscribe_token` varchar(64) NOT NULL,
  `Locale` varchar(12) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `voucher_amount_history`
--

CREATE TABLE `voucher_amount_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `voucher_id` int(11) NOT NULL,
  `old_amount` int(11) NOT NULL,
  `new_amount` int(11) NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexy pro exportované tabulky
--

--
-- Indexy pro tabulku `event`
--
ALTER TABLE `event`
  ADD PRIMARY KEY (`ID`);

--
-- Indexy pro tabulku `food`
--
ALTER TABLE `food`
  ADD PRIMARY KEY (`ID`);

--
-- Indexy pro tabulku `galleries`
--
ALTER TABLE `galleries`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `opening_exceptions`
--
ALTER TABLE `opening_exceptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_exc` (`day`);

--
-- Indexy pro tabulku `opening_hours`
--
ALTER TABLE `opening_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_hours` (`day_of_week`,`opens`,`closes`);

--
-- Indexy pro tabulku `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`ID`);

--
-- Indexy pro tabulku `reservation_comments`
--
ALTER TABLE `reservation_comments`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_reservation_id` (`reservation_id`);

--
-- Indexy pro tabulku `reservation_room`
--
ALTER TABLE `reservation_room`
  ADD PRIMARY KEY (`reservation_id`,`room_id`),
  ADD KEY `fk_reservation_room_room` (`room_id`);

--
-- Indexy pro tabulku `reservation_table`
--
ALTER TABLE `reservation_table`
  ADD PRIMARY KEY (`ID`);

--
-- Indexy pro tabulku `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`ID`);

--
-- Indexy pro tabulku `room_cleaning`
--
ALTER TABLE `room_cleaning`
  ADD PRIMARY KEY (`room_id`,`day`),
  ADD KEY `idx_room_cleaning_day` (`day`);

--
-- Indexy pro tabulku `table_comments`
--
ALTER TABLE `table_comments`
  ADD PRIMARY KEY (`ID`);

--
-- Indexy pro tabulku `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexy pro tabulku `voucher`
--
ALTER TABLE `voucher`
  ADD PRIMARY KEY (`ID`);

--
-- Indexy pro tabulku `voucher_amount_history`
--
ALTER TABLE `voucher_amount_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_voucher_changed_at` (`voucher_id`,`changed_at`);

--
-- AUTO_INCREMENT pro tabulky
--

--
-- AUTO_INCREMENT pro tabulku `food`
--
ALTER TABLE `food`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `galleries`
--
ALTER TABLE `galleries`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `opening_exceptions`
--
ALTER TABLE `opening_exceptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pro tabulku `opening_hours`
--
ALTER TABLE `opening_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pro tabulku `reservations`
--
ALTER TABLE `reservations`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `reservation_comments`
--
ALTER TABLE `reservation_comments`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `reservation_table`
--
ALTER TABLE `reservation_table`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `rooms`
--
ALTER TABLE `rooms`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pro tabulku `table_comments`
--
ALTER TABLE `table_comments`
  MODIFY `ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pro tabulku `voucher`
--
ALTER TABLE `voucher`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `voucher_amount_history`
--
ALTER TABLE `voucher_amount_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Omezení pro exportované tabulky
--

--
-- Omezení pro tabulku `reservation_comments`
--
ALTER TABLE `reservation_comments`
  ADD CONSTRAINT `fk_reservation_comments_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `reservation_room`
--
ALTER TABLE `reservation_room`
  ADD CONSTRAINT `fk_reservation_room_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_reservation_room_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`ID`) ON UPDATE CASCADE;

--
-- Omezení pro tabulku `room_cleaning`
--
ALTER TABLE `room_cleaning`
  ADD CONSTRAINT `fk_room_cleaning_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Omezení pro tabulku `voucher_amount_history`
--
ALTER TABLE `voucher_amount_history`
  ADD CONSTRAINT `fk_vah_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `voucher` (`ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
