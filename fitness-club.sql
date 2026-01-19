-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-8.0
-- Время создания: Янв 10 2026 г., 02:12
-- Версия сервера: 8.0.35
-- Версия PHP: 8.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `fitness-club`
--

-- --------------------------------------------------------

--
-- Структура таблицы `attendance`
--

CREATE TABLE `attendance` (
  `id` int NOT NULL,
  `booking_id` int NOT NULL,
  `attended` tinyint(1) DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Заметки тренера',
  `marked_by` int DEFAULT NULL COMMENT 'Кто отметил (тренер/админ)',
  `marked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `attendance`
--

INSERT INTO `attendance` (`id`, `booking_id`, `attended`, `notes`, `marked_by`, `marked_at`) VALUES
(4, 20, 1, 'Круто позанимался', 11, '2025-12-05 16:30:44'),
(6, 25, 1, 'Хорошо занимался, красавец!!!!', 10, '2026-01-09 12:17:42'),
(7, 68, NULL, 'кто это', 10, '2026-01-09 11:57:07');

-- --------------------------------------------------------

--
-- Структура таблицы `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `workout_id` int NOT NULL,
  `subscription_id` int DEFAULT NULL COMMENT 'Использованный абонемент',
  `status` enum('created','confirmed','cancelled','attended','missed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'created',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `bookings`
--

INSERT INTO `bookings` (`id`, `client_id`, `workout_id`, `subscription_id`, `status`, `created_at`, `updated_at`) VALUES
(20, 14, 13, 13, 'attended', '2025-12-01 01:05:00', '2025-12-05 16:18:46'),
(23, 14, 19, 13, 'cancelled', '2025-12-01 01:20:00', '2025-12-05 09:23:21'),
(25, 14, 11, 13, 'attended', '2025-12-04 07:35:05', '2025-12-14 22:36:53'),
(26, 14, 12, 13, 'cancelled', '2025-12-04 07:35:51', '2025-12-05 09:54:45'),
(27, 14, 14, 13, 'cancelled', '2025-12-04 07:38:20', '2025-12-05 09:56:36'),
(28, 14, 15, 13, 'cancelled', '2025-12-04 07:38:41', '2025-12-05 09:46:45'),
(29, 14, 16, 13, 'cancelled', '2025-12-04 07:41:10', '2025-12-05 09:43:38'),
(30, 14, 18, 13, 'confirmed', '2025-12-04 07:45:49', '2025-12-04 07:45:49'),
(67, 13, 26, 22, 'cancelled', '2026-01-07 14:16:43', '2026-01-09 12:09:46'),
(68, 37, 28, 23, 'cancelled', '2026-01-09 11:54:48', '2026-01-09 12:50:59');

--
-- Триггеры `bookings`
--
DELIMITER $$
CREATE TRIGGER `after_booking_cancelled` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    -- Когда запись отменяется, уменьшаем счетчик участников
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
        UPDATE workouts 
        SET current_participants = GREATEST(0, current_participants - 1)
        WHERE id = NEW.workout_id;
        
        -- Возвращаем посещение на абонемент
        IF NEW.subscription_id IS NOT NULL THEN
            UPDATE subscriptions 
            SET visits_left = LEAST(visits_total, visits_left + 1)
            WHERE id = NEW.subscription_id;
        END IF;
    END IF;
    
    -- Если запись снова активируется (из cancelled в confirmed)
    IF NEW.status = 'confirmed' AND OLD.status = 'cancelled' THEN
        UPDATE workouts 
        SET current_participants = current_participants + 1
        WHERE id = NEW.workout_id;
        
        -- Снова списываем посещение
        IF NEW.subscription_id IS NOT NULL THEN
            UPDATE subscriptions 
            SET visits_left = GREATEST(0, visits_left - 1)
            WHERE id = NEW.subscription_id;
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_booking_insert` AFTER INSERT ON `bookings` FOR EACH ROW BEGIN
    -- Увеличиваем счетчик участников
    UPDATE workouts 
    SET current_participants = current_participants + 1
    WHERE id = NEW.workout_id;
    
    -- Если статус created, не списываем посещение
    -- Посещение спишется только при подтверждении
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_booking_insert` BEFORE INSERT ON `bookings` FOR EACH ROW BEGIN
    DECLARE existing_active_booking_id INT;
    DECLARE available_visits INT;
    DECLARE subscription_status VARCHAR(20);
    DECLARE workout_date DATE;
    
    -- Проверяем доступность тренировки
    SELECT w.workout_date INTO workout_date
    FROM workouts w
    WHERE w.id = NEW.workout_id;
    
    IF workout_date < CURDATE() THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Нельзя записаться на прошедшую тренировку';
    END IF;
    
    -- Проверяем, есть ли уже АКТИВНАЯ (не отмененная) запись
    SELECT id INTO existing_active_booking_id
    FROM bookings 
    WHERE client_id = NEW.client_id 
      AND workout_id = NEW.workout_id
      AND status != 'cancelled'  -- Разрешаем только если статус 'cancelled'
    LIMIT 1;
    
    IF existing_active_booking_id IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Вы уже записаны на эту тренировку';
    END IF;
    
    -- Проверяем абонемент, если он указан
    IF NEW.subscription_id IS NOT NULL THEN
        SELECT visits_left, status 
        INTO available_visits, subscription_status
        FROM subscriptions 
        WHERE id = NEW.subscription_id;
        
        IF subscription_status != 'active' THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Абонемент не активен';
        END IF;
        
        IF available_visits <= 0 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Нет доступных посещений на абонементе';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `type` enum('booking_confirmed','reminder','schedule_change','subscription_expiry','review_status') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `related_id` int DEFAULT NULL COMMENT 'ID связанной сущности (тренировка, отзыв и т.д.)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `is_read`, `related_id`, `created_at`) VALUES
(40, 14, 'review_status', 'Отзыв одобрен', 'Ваш отзыв на тренировку был одобрен и опубликован. Спасибо за обратную связь!', 1, 17, '2025-12-05 08:25:57'),
(42, 14, 'review_status', 'Отзыв отклонен', 'Ваш отзыв на тренировку был отклонен. Причина: Слишком красиво стелит', 0, 11, '2025-12-05 08:44:43'),
(44, 14, 'booking_confirmed', 'Запись на тренировку', 'Вы записаны на тренировку: \"ULTRA HARD KILL Тренировка\" (2025-12-08 в 15:25)', 0, NULL, '2025-12-05 09:21:58'),
(45, 14, 'schedule_change', 'Изменения в тренировке \'ULTRA HARD KILL Тренировка\' (08.12.2025 в 16:25)', 'В тренировке \'ULTRA HARD KILL Тренировка\' изменились: время проведения', 0, 21, '2025-12-05 09:22:36'),
(47, 10, 'schedule_change', 'Тренировка отменена', 'Тренировка \'Йога для начинающих\' от 07.12.2025 в 10:00 отменена.', 0, 17, '2025-12-05 09:34:37'),
(48, 14, 'schedule_change', 'Изменения в тренировке \'Кардио-зарядка\' (06.12.2025 в 07:30)', 'Тренировка \'Кардио-зарядка\' (06.12.2025 в 07:30) отменена. Посещение возвращено на ваш абонемент.', 0, 15, '2025-12-05 09:46:45'),
(50, 12, 'schedule_change', 'Тренировка отменена', 'Тренировка \'Кардио-зарядка\' от 06.12.2025 в 07:30 отменена.', 0, 15, '2025-12-05 09:46:45'),
(51, 14, 'schedule_change', 'Изменения в тренировке \'Вечерняя йога\' (05.12.2025 в 20:00)', 'В тренировке \'Вечерняя йога\' изменились: время проведения', 0, 12, '2025-12-05 09:53:54'),
(52, 10, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'Вечерняя йога\' от 05.12.2025 произошли изменения: время проведения', 0, 12, '2025-12-05 09:53:54'),
(53, 14, 'schedule_change', 'Изменения в тренировке \'Вечерняя йога\' (01.12.2025 в 20:00)', 'В тренировке \'Вечерняя йога\' изменились: время проведения', 0, 12, '2025-12-05 09:54:10'),
(54, 10, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'Вечерняя йога\' от 01.12.2025 произошли изменения: время проведения', 0, 12, '2025-12-05 09:54:10'),
(55, 14, 'schedule_change', 'Изменения в тренировке \'Вечерняя йога\' (06.12.2025 в 20:00)', 'В тренировке \'Вечерняя йога\' изменились: время проведения', 0, 12, '2025-12-05 09:54:40'),
(56, 10, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'Вечерняя йога\' от 06.12.2025 произошли изменения: время проведения', 0, 12, '2025-12-05 09:54:40'),
(57, 14, 'schedule_change', 'Изменения в тренировке \'Вечерняя йога\' (06.12.2025 в 20:00)', 'Тренировка \'Вечерняя йога\' (06.12.2025 в 20:00) отменена. Посещение возвращено на ваш абонемент.', 0, 12, '2025-12-05 09:54:45'),
(59, 10, 'schedule_change', 'Тренировка отменена', 'Тренировка \'Вечерняя йога\' от 06.12.2025 в 20:00 отменена.', 0, 12, '2025-12-05 09:54:45'),
(60, 14, 'schedule_change', 'Изменения в тренировке \'CrossFit\' (05.12.2025 в 18:00)', 'В тренировке \'CrossFit\' изменились: время проведения', 0, 14, '2025-12-05 09:56:30'),
(61, 11, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'CrossFit\' от 05.12.2025 произошли изменения: время проведения', 0, 14, '2025-12-05 09:56:30'),
(62, 14, 'schedule_change', 'Изменения в тренировке \'CrossFit\' (05.12.2025 в 18:00)', 'Тренировка \'CrossFit\' (05.12.2025 в 18:00) отменена. Посещение возвращено на ваш абонемент.', 0, 14, '2025-12-05 09:56:36'),
(64, 11, 'schedule_change', 'Тренировка отменена', 'Тренировка \'CrossFit\' от 05.12.2025 в 18:00 отменена.', 0, 14, '2025-12-05 09:56:36'),
(65, 14, 'review_status', 'Отзыв отклонен', 'Ваш отзыв на тренировку был отклонен.', 0, 17, '2025-12-05 10:03:35'),
(66, 10, 'schedule_change', 'Новая тренировка добавлена', 'Вам назначена новая тренировка: \'Крутая\' на 12.12.2025 в 16:22', 0, 22, '2025-12-05 10:22:47'),
(69, 10, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'Крутая\' от 05.12.2025 произошли изменения: время проведения', 0, 22, '2025-12-05 10:25:49'),
(71, 10, 'schedule_change', 'Изменение статуса тренировки', 'Тренировка \'Крутая\' от 05.12.2025: Тренировка проведена', 0, 22, '2025-12-05 10:25:49'),
(72, 14, 'schedule_change', 'Изменения в тренировке \'Силовая тренировка\' (05.12.2025 в 16:00)', 'В тренировке \'Силовая тренировка\' изменились: время проведения', 0, 13, '2025-12-05 10:28:40'),
(73, 11, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'Силовая тренировка\' от 05.12.2025 произошли изменения: время проведения', 0, 13, '2025-12-05 10:28:40'),
(74, 14, 'schedule_change', 'Изменения в тренировке \'Силовая тренировка\' (05.12.2025 в 20:25)', 'В тренировке \'Силовая тренировка\' изменились: время проведения', 0, 13, '2025-12-05 15:20:10'),
(75, 11, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'Силовая тренировка\' от 05.12.2025 произошли изменения: время проведения', 0, 13, '2025-12-05 15:20:10'),
(76, 11, 'schedule_change', 'Новая тренировка добавлена', 'Вам назначена новая тренировка: \'Дневной кач\' на 2025-12-06 в 15:00', 0, 23, '2025-12-05 16:04:24'),
(80, 11, 'schedule_change', 'Новая тренировка добавлена', 'Вам назначена новая тренировка: \'1231\' на 18.12.2025 в 02:40', 0, 24, '2025-12-13 15:35:37'),
(81, 11, 'schedule_change', 'Тренировка удалена', 'Тренировка \'1231\' от 18.12.2025 в 02:40 удалена из расписания.', 0, NULL, '2025-12-13 15:35:47'),
(83, 10, 'schedule_change', 'Новая тренировка добавлена', 'Вам назначена новая тренировка: \'ай\' на 2025-12-15 в 23:27', 0, 25, '2025-12-13 16:28:15'),
(84, 14, 'review_status', 'Отзыв одобрен', 'Ваш отзыв на тренировку был одобрен и опубликован. Спасибо за обратную связь!', 0, 11, '2025-12-21 11:24:13'),
(85, 11, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'Дневной кач\' от 26.12.2025 произошли изменения: время проведения', 0, 23, '2025-12-21 11:39:52'),
(86, 11, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'Дневной кач\' от 31.12.2025 произошли изменения: время проведения', 0, 23, '2025-12-21 11:40:19'),
(87, 14, 'booking_confirmed', 'Запись на тренировку', 'Вы записаны на тренировку: \"Дневной кач\" (2025-12-31 в 15:00)', 0, NULL, '2025-12-21 12:21:09'),
(88, 10, 'schedule_change', 'Новая тренировка добавлена', 'Вам назначена новая тренировка: \'Вечерний кач\' на 2026-01-07 в 17:45', 0, 26, '2026-01-06 11:45:46'),
(89, 10, 'schedule_change', 'Новая тренировка добавлена', 'Вам назначена новая тренировка: \'выфв\' на 2026-01-19 в 18:12', 0, 27, '2026-01-06 12:13:56'),
(90, 10, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'Вечерний кач\' от 01.01.2026 произошли изменения: время проведения', 0, 26, '2026-01-06 21:01:12'),
(91, 10, 'schedule_change', 'Изменения в тренировке', 'В тренировке \'Вечерний кач\' от 07.01.2026 произошли изменения: время проведения', 0, 26, '2026-01-06 21:01:19'),
(92, 13, 'booking_confirmed', 'Запись на тренировку', 'Вы записаны на тренировку: \"Вечерний кач\" (2026-01-07 в 17:45)', 0, NULL, '2026-01-07 14:00:43'),
(93, 13, 'booking_confirmed', 'Запись на тренировку', 'Вы записаны на тренировку: \"Вечерний кач\" (2026-01-07 в 17:45)', 0, NULL, '2026-01-07 14:10:40'),
(94, 13, 'booking_confirmed', 'Запись на тренировку', 'Вы записаны на тренировку: \"Вечерний кач\" (2026-01-07 в 17:45)', 0, NULL, '2026-01-07 14:14:44'),
(95, 13, 'booking_confirmed', 'Запись на тренировку', 'Вы записаны на тренировку: \"Вечерний кач\" (2026-01-07 в 17:45)', 0, NULL, '2026-01-07 14:16:32'),
(97, 10, 'schedule_change', 'Новая тренировка добавлена', 'Вам назначена новая тренировка: \'Вечер тяжелого жима лёжа\' на 2026-01-10 в 17:04', 0, 28, '2026-01-09 11:05:16'),
(98, 37, 'booking_confirmed', 'Запись на тренировку', 'Вы записаны на тренировку: \"Вечер тяжелого жима лёжа\" (2026-01-10 в 17:04)', 0, NULL, '2026-01-09 11:54:48'),
(99, 13, 'booking_confirmed', 'Запись на тренировку', 'Вы записаны на тренировку: \"Вечер тяжелого жима лёжа\" (2026-01-10 в 17:04)', 0, NULL, '2026-01-09 12:09:56');

-- --------------------------------------------------------

--
-- Структура таблицы `reviews`
--

CREATE TABLE `reviews` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `trainer_id` int NOT NULL,
  `workout_id` int NOT NULL,
  `rating` tinyint NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `moderation_status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `moderated_by` int DEFAULT NULL COMMENT 'Администратор, проверивший отзыв',
  `moderation_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Причина отклонения',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `moderated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `reviews`
--

INSERT INTO `reviews` (`id`, `client_id`, `trainer_id`, `workout_id`, `rating`, `comment`, `moderation_status`, `moderated_by`, `moderation_comment`, `created_at`, `moderated_at`) VALUES
(10, 13, 10, 11, 5, 'Отличная тренировка! Мария очень профессиональный тренер, все объясняет доступно.', 'approved', 9, NULL, '2025-12-01 02:00:00', '2025-12-05 08:44:58'),
(11, 14, 11, 13, 4, 'Хорошая силовая тренировка, но хотелось бы больше разнообразия в упражнениях.', 'approved', 9, NULL, '2025-12-01 02:05:00', '2025-12-21 11:24:13'),
(13, 13, 10, 12, 5, 'Вечерняя йога - это что-то! После работы именно то, что нужно для расслабления.', 'approved', 9, NULL, '2025-12-01 02:15:00', '2025-12-01 03:15:00'),
(14, 14, 11, 14, 4, 'Интенсивно, потно, эффективно! CrossFit от Ивана - это вызов самому себе.', 'approved', 9, NULL, '2025-12-01 02:20:00', '2025-12-01 03:20:00'),
(16, 13, 10, 17, 5, 'Йога для начинающих - идеально для новичков! Все понятно и доступно.', 'rejected', 9, 'нет', '2025-12-01 02:30:00', '2025-12-13 15:27:58'),
(17, 14, 11, 18, 5, 'Хорошая мужская тренировка, чувствую прогресс.', 'approved', 9, NULL, '2025-12-01 02:35:00', '2025-12-13 15:27:34');

-- --------------------------------------------------------

--
-- Структура таблицы `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Разовый, месячный, годовой и т.д.',
  `visits_total` int NOT NULL COMMENT 'Общее количество посещений',
  `visits_left` int NOT NULL COMMENT 'Оставшиеся посещения',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','frozen') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `client_id`, `type`, `visits_total`, `visits_left`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(12, 13, '3months', 12, 3, '2025-11-15', '2026-02-13', 'expired', '2025-11-14 23:00:00'),
(13, 14, 'year', 52, 45, '2025-10-01', '2026-09-30', 'active', '2025-10-01 00:00:00'),
(15, 13, 'month', 4, 0, '2025-10-01', '2025-10-31', 'expired', '2025-10-01 02:00:00'),
(18, 13, 'year', 52, 0, '2025-12-13', '2026-12-13', 'expired', '2025-12-13 16:05:04'),
(20, 30, 'month', 4, 4, '2026-01-06', '2026-02-05', 'active', '2026-01-06 11:57:05'),
(22, 13, 'month', 4, 4, '2026-01-07', '2026-02-06', 'active', '2026-01-07 14:00:28'),
(23, 37, 'month', 4, 4, '2026-01-09', '2026-02-08', 'active', '2026-01-09 11:54:41');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Хэш пароля (bcrypt)',
  `role` enum('client','trainer','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'client',
  `first_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `middle_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Описание (особенно для тренеров)',
  `specialization` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Специализация тренера',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `role`, `first_name`, `last_name`, `middle_name`, `phone`, `avatar_url`, `description`, `specialization`, `active`, `created_at`, `updated_at`) VALUES
(9, 'admin@fitness.ru', '$2y$10$D9Tptf7AJK79VelxYeTnAuIyRyyPG7Rdhn0fEY3dHpKlwyUQDDSAq', 'admin', 'Александр', 'Петров', 'Иванович', '+79161234567', NULL, 'Главный администратор фитнес-клуба', NULL, 1, '2025-12-01 00:00:00', '2025-12-04 06:18:42'),
(10, 'trainer1@fitness.ru', '$2y$10$D9Tptf7AJK79VelxYeTnAuIyRyyPG7Rdhn0fEY3dHpKlwyUQDDSAq', 'trainer', 'Мария', 'Сидорова', 'Алексеевна', '+79162345678', '/uploads/avatars/avatar_10_1765643437.jpg', 'Опытный тренер по йоге и пилатесу. Стаж 8 лет.', 'Йога, Пилатес, Стретчинг', 1, '2025-12-01 00:05:00', '2025-12-21 10:53:39'),
(11, 'trainer2@fitness.ru', '$2y$10$D9Tptf7AJK79VelxYeTnAuIyRyyPG7Rdhn0fEY3dHpKlwyUQDDSAq', 'trainer', 'Иван', 'Козлов', 'Олегович', '+79163456789', '/uploads/avatars/avatar_11_1764952328.jpg', 'Сертифицированный тренер по силовым тренировкам. Чемпион области по пауэрлифтингу.', 'Силовые тренировки, Функциональный тренинг, CrossFit', 1, '2025-12-01 00:10:00', '2025-12-05 16:32:10'),
(12, 'trainer3@fitness.ru', '$2y$10$D9Tptf7AJK79VelxYeTnAuIyRyyPG7Rdhn0fEY3dHpKlwyUQDDSAq', 'trainer', 'Ольга', 'Иванова', 'Дмитриевна', '+79164567890', '/uploads/avatars/avatar_12_1767818437.jpg', 'Специалист по кардио-тренировкам и снижению веса. Дипломированный нутрициолог.', 'Кардио-тренировки, Снижение веса, ЗОЖ', 1, '2025-12-01 00:15:00', '2026-01-07 20:40:37'),
(13, 'client1@fitness.ru', '$2y$10$D9Tptf7AJK79VelxYeTnAuIyRyyPG7Rdhn0fEY3dHpKlwyUQDDSAq', 'client', 'Анна', 'Смирнова', 'Викторовна', '+79165678901', NULL, 'Регулярно посещаю тренировки', NULL, 1, '2025-12-01 00:20:00', '2025-12-13 16:17:41'),
(14, 'client2@fitness.ru', '$2y$10$D9Tptf7AJK79VelxYeTnAuIyRyyPG7Rdhn0fEY3dHpKlwyUQDDSAq', 'client', 'Дмитрий', 'Васильев', 'Андреевич', '+79166789012', NULL, 'Люблю активный образ жизни', NULL, 1, '2025-12-01 00:25:00', '2025-12-04 06:19:12'),
(27, 'client123@fitness.ru', '$2y$10$ebd5xW4niINOjJzQSi4qW.Dqy2KbAPlpK8edVvUpCLtg/13LPjp7u', 'client', '1234', '1234', NULL, NULL, NULL, NULL, NULL, 1, '2025-12-15 09:37:05', '2025-12-15 09:37:05'),
(28, 'ekb@client.ru', '$2y$10$MMW68VBF5hrTaJzGSfYGk.wAYM/OPvAYvX/My4bEYQpqgDfh1FVoi', 'client', 'Екб', 'Клиент', NULL, NULL, NULL, NULL, NULL, 1, '2025-12-21 09:27:48', '2025-12-21 09:27:48'),
(30, 'ex@ex.ru', '$2y$10$KS02H5fijNefCgXQQHx.F.Rpol8wj2.nWdcwF6GVAY7.AQ71AL06K', 'client', 'ex', 'ex', NULL, '89909009090', NULL, NULL, NULL, 1, '2026-01-06 11:36:50', '2026-01-06 11:36:50'),
(37, 'new@new.ru', '$2y$10$xN08nA2T7xRvNAipMfWtTOcMUZB5fpPh1Z5nbydaqCh1RKwr.6mpm', 'client', 'new', 'new', NULL, NULL, NULL, NULL, NULL, 1, '2026-01-09 11:03:57', '2026-01-09 11:03:57');

-- --------------------------------------------------------

--
-- Структура таблицы `workouts`
--

CREATE TABLE `workouts` (
  `id` int NOT NULL,
  `trainer_id` int NOT NULL,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `workout_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_participants` int DEFAULT '10',
  `current_participants` int DEFAULT '0',
  `status` enum('scheduled','cancelled','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'scheduled',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `workouts`
--

INSERT INTO `workouts` (`id`, `trainer_id`, `title`, `description`, `workout_date`, `start_time`, `end_time`, `max_participants`, `current_participants`, `status`, `created_at`, `updated_at`) VALUES
(11, 10, 'Утренняя йога', 'Расслабляющая утренняя практика для пробуждения тела', '2025-12-05', '08:00:00', '09:00:00', 15, 3, 'completed', '2025-12-01 00:00:00', '2025-12-20 16:53:02'),
(12, 10, 'Вечерняя йога', 'Снятие напряжения', '2025-12-06', '20:00:00', '23:00:00', 10, 8, 'cancelled', '2025-12-01 00:05:00', '2025-12-05 09:54:45'),
(13, 11, 'Силовая тренировка', 'Работа с весом, развитие силы и выносливости', '2025-12-05', '20:25:00', '22:30:00', 10, 6, 'completed', '2025-12-01 00:10:00', '2025-12-05 16:18:18'),
(14, 11, 'CrossFit', 'Высокоинтенсивная круговая тренировка', '2025-12-05', '18:00:00', '22:00:00', 12, 9, 'cancelled', '2025-12-01 00:15:00', '2025-12-05 09:56:36'),
(15, 12, 'Кардио-зарядка', 'Активное утро для бодрости на весь день', '2025-12-06', '07:30:00', '08:30:00', 20, 11, 'cancelled', '2025-12-01 00:20:00', '2025-12-05 09:46:45'),
(16, 12, 'Интервальная тренировка', 'Сжигание калорий, развитие выносливости', '2025-12-06', '17:00:00', '18:00:00', 15, 9, 'cancelled', '2025-12-01 00:25:00', '2025-12-05 09:43:38'),
(17, 10, 'Йога для начинающих', 'Базовые асаны, правильное дыхание', '2025-12-07', '10:00:00', '11:00:00', 15, 3, 'cancelled', '2025-12-01 00:30:00', '2025-12-05 09:34:37'),
(18, 11, 'Тренировка для мужчин', 'Развитие мышечной массы', '2025-12-07', '11:00:00', '12:30:00', 10, 8, 'scheduled', '2025-12-01 00:35:00', '2025-12-13 15:34:38'),
(19, 12, 'Тренировка для женщин', 'Тонизирование мышц, работа над проблемными зонами', '2025-12-07', '12:00:00', '13:00:00', 15, 11, 'cancelled', '2025-12-01 00:40:00', '2025-12-05 09:23:21'),
(20, 10, 'Йога-нидра', 'Глубокая релаксация и медитация', '2025-12-08', '20:00:00', '21:00:00', 20, 15, 'completed', '2025-12-01 00:45:00', '2025-12-05 09:24:59'),
(21, 10, 'ULTRA HARD KILL Тренировка', 'Умрем и станем качками', '2025-12-08', '16:25:00', '17:35:00', 2, 0, 'cancelled', '2025-12-05 09:21:16', '2025-12-05 09:23:09'),
(22, 10, 'Крутая', NULL, '2025-12-05', '12:22:00', '14:22:00', 1, 0, 'completed', '2025-12-05 10:22:47', '2025-12-13 15:09:11'),
(23, 11, 'Дневной кач', 'Раскачаемся, попьем свежий juice', '2025-12-31', '15:00:00', '16:40:00', 2, 1, 'scheduled', '2025-12-05 16:04:24', '2025-12-21 12:21:09'),
(25, 10, 'ай', 'аф', '2025-12-15', '23:27:00', '23:30:00', 4, 0, 'cancelled', '2025-12-13 16:28:15', '2025-12-13 16:28:25'),
(26, 10, 'Вечерний кач', 'Очень хорошо потренируемся', '2026-01-07', '17:45:00', '18:45:00', 12, 0, 'scheduled', '2026-01-06 11:45:46', '2026-01-09 12:09:46'),
(27, 10, 'выфв', 'выфв', '2026-01-19', '18:12:00', '15:12:00', 6, 0, 'cancelled', '2026-01-06 12:13:56', '2026-01-06 12:15:04'),
(28, 10, 'Вечер тяжелого жима лёжа', 'Будем качать грудь, руки и грыжу в позвоночнике', '2026-01-10', '17:04:00', '18:04:00', 6, 0, 'scheduled', '2026-01-09 11:05:16', '2026-01-09 12:50:59');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`booking_id`),
  ADD KEY `marked_by` (`marked_by`);

--
-- Индексы таблицы `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_booking` (`client_id`,`workout_id`),
  ADD KEY `subscription_id` (`subscription_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_workout` (`workout_id`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Индексы таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `workout_id` (`workout_id`),
  ADD KEY `moderated_by` (`moderated_by`),
  ADD KEY `idx_trainer_status` (`trainer_id`,`moderation_status`),
  ADD KEY `idx_moderation_status` (`moderation_status`);

--
-- Индексы таблицы `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_client_status` (`client_id`,`status`),
  ADD KEY `idx_end_date` (`end_date`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_email` (`email`);

--
-- Индексы таблицы `workouts`
--
ALTER TABLE `workouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_trainer_date` (`trainer_id`,`workout_date`),
  ADD KEY `idx_date_status` (`workout_date`,`status`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT для таблицы `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT для таблицы `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT для таблицы `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT для таблицы `workouts`
--
ALTER TABLE `workouts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`workout_id`) REFERENCES `workouts` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`);

--
-- Ограничения внешнего ключа таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`workout_id`) REFERENCES `workouts` (`id`),
  ADD CONSTRAINT `reviews_ibfk_4` FOREIGN KEY (`moderated_by`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `workouts`
--
ALTER TABLE `workouts`
  ADD CONSTRAINT `workouts_ibfk_1` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
