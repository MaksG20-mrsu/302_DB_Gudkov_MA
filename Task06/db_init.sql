PRAGMA foreign_keys = ON;

BEGIN TRANSACTION;

DROP TABLE IF EXISTS works;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS time_off;
DROP TABLE IF EXISTS work_schedule;
DROP TABLE IF EXISTS master_services;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS masters;

-- Справочник мастеров СТО (уволенный мастер не удаляется, заполняется fire_date)
CREATE TABLE masters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    middle_name TEXT,
    phone TEXT,
    hire_date TEXT NOT NULL DEFAULT (date('now')),
    fire_date TEXT,
    salary_percent REAL NOT NULL DEFAULT 20.0
        CHECK (salary_percent >= 0.0 AND salary_percent <= 100.0),
    CHECK (fire_date IS NULL OR fire_date >= hire_date)
);

-- Справочник услуг: название, продолжительность (минуты) и цена
CREATE TABLE services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    duration_minutes INTEGER NOT NULL CHECK (duration_minutes > 0),
    price REAL NOT NULL CHECK (price >= 0.0)
);

-- Услуги, которые может выполнять каждый мастер (M:N)
CREATE TABLE master_services (
    master_id INTEGER NOT NULL REFERENCES masters(id) ON DELETE CASCADE,
    service_id INTEGER NOT NULL REFERENCES services(id) ON DELETE CASCADE,
    PRIMARY KEY (master_id, service_id)
);

-- Индивидуальный график работы мастера: день недели (1 - пн ... 7 - вс)
CREATE TABLE work_schedule (
    master_id INTEGER NOT NULL REFERENCES masters(id) ON DELETE CASCADE,
    day_of_week INTEGER NOT NULL CHECK (day_of_week BETWEEN 1 AND 7),
    start_time TEXT NOT NULL CHECK (start_time GLOB '[0-2][0-9]:[0-5][0-9]'),
    end_time TEXT NOT NULL CHECK (end_time GLOB '[0-2][0-9]:[0-5][0-9]'),
    PRIMARY KEY (master_id, day_of_week),
    CHECK (start_time < end_time)
);

-- Отпуска и нерабочие дни мастера
CREATE TABLE time_off (
    master_id INTEGER NOT NULL REFERENCES masters(id) ON DELETE CASCADE,
    day TEXT NOT NULL,
    reason TEXT,
    PRIMARY KEY (master_id, day)
);

-- Предварительная запись к мастеру
CREATE TABLE appointments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    master_id INTEGER NOT NULL REFERENCES masters(id) ON DELETE RESTRICT,
    service_id INTEGER NOT NULL REFERENCES services(id) ON DELETE RESTRICT,
    client_name TEXT NOT NULL,
    appointment_date TEXT NOT NULL,
    start_time TEXT NOT NULL CHECK (start_time GLOB '[0-2][0-9]:[0-5][0-9]'),
    status TEXT NOT NULL DEFAULT 'planned'
        CHECK (status IN ('planned', 'done', 'cancelled')),
    UNIQUE (master_id, appointment_date, start_time)
);

-- Фактически выполненные работы
CREATE TABLE works (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    master_id INTEGER NOT NULL REFERENCES masters(id) ON DELETE RESTRICT,
    service_id INTEGER NOT NULL REFERENCES services(id) ON DELETE RESTRICT,
    work_date TEXT NOT NULL,
    price REAL NOT NULL CHECK (price >= 0.0),
    appointment_id INTEGER UNIQUE REFERENCES appointments(id) ON DELETE SET NULL
);

CREATE INDEX idx_masters_last_name ON masters(last_name);
CREATE INDEX idx_works_date ON works(work_date);
CREATE INDEX idx_works_master ON works(master_id);
CREATE INDEX idx_appointments_date ON appointments(appointment_date);
CREATE INDEX idx_appointments_master ON appointments(master_id);

-- Тестовые данные
INSERT INTO masters (id, first_name, last_name, middle_name, phone, hire_date, fire_date, salary_percent) VALUES
    (1, 'Иван',    'Петров',   'Сергеевич',   '+7 917 100-11-01', '2019-03-04', NULL,         25.0),
    (2, 'Сергей',  'Козлов',   'Андреевич',   '+7 917 100-11-02', '2020-06-15', NULL,         20.0),
    (3, 'Андрей',  'Морозов',  'Ильич',       '+7 917 100-11-03', '2018-11-02', NULL,         30.0),
    (4, 'Дмитрий', 'Волков',   'Олегович',   '+7 917 100-11-04', '2021-02-08', NULL,         15.0),
    (5, 'Михаил',  'Соколов',  'Павлович',   '+7 917 100-11-05', '2022-09-19', NULL,         20.0),
    (6, 'Николай', 'Фролов',   'Викторович', '+7 917 100-11-06', '2017-04-10', '2024-12-31', 25.0);

INSERT INTO services (id, name, duration_minutes, price) VALUES
    (1, 'Замена масла и масляного фильтра', 30,  1200.0),
    (2, 'Замена воздушного фильтра',        15,  600.0),
    (3, 'Замена тормозных колодок',         60,  2500.0),
    (4, 'Замена ремня ГРМ',                 180, 6000.0),
    (5, 'Ремонт ходовой части',             120, 3500.0),
    (6, 'Развал-схождение',                 60,  2200.0),
    (7, 'Замена аккумулятора',              15,  400.0),
    (8, 'Компьютерная диагностика',         45,  1500.0),
    (9, 'Шиномонтаж (комплект)',            90,  2800.0);

INSERT INTO master_services (master_id, service_id) VALUES
    (1, 1), (1, 2), (1, 7), (1, 8),
    (2, 1), (2, 2), (2, 3), (2, 7),
    (3, 3), (3, 4), (3, 5), (3, 8),
    (4, 1), (4, 7), (4, 9),
    (5, 6), (5, 8), (5, 9),
    (6, 1), (6, 2), (6, 8);

INSERT INTO work_schedule (master_id, day_of_week, start_time, end_time) VALUES
    (1, 1, '09:00', '18:00'), (1, 2, '09:00', '18:00'), (1, 3, '09:00', '18:00'),
    (1, 4, '09:00', '18:00'), (1, 5, '09:00', '15:00'),
    (2, 1, '10:00', '19:00'), (2, 2, '10:00', '19:00'), (2, 3, '10:00', '19:00'),
    (2, 4, '10:00', '19:00'), (2, 5, '10:00', '19:00'), (2, 6, '10:00', '14:00'),
    (3, 1, '08:00', '17:00'), (3, 2, '08:00', '17:00'), (3, 3, '08:00', '17:00'),
    (3, 4, '08:00', '17:00'), (3, 5, '08:00', '17:00'), (3, 6, '09:00', '13:00'),
    (4, 1, '09:00', '18:00'), (4, 2, '09:00', '18:00'), (4, 3, '09:00', '18:00'),
    (4, 4, '09:00', '18:00'), (4, 5, '09:00', '18:00'),
    (5, 2, '11:00', '20:00'), (5, 3, '11:00', '20:00'), (5, 4, '11:00', '20:00'),
    (5, 5, '11:00', '20:00'), (5, 6, '11:00', '20:00'), (5, 7, '11:00', '16:00'),
    (6, 1, '09:00', '18:00'), (6, 2, '09:00', '18:00'), (6, 3, '09:00', '18:00'),
    (6, 4, '09:00', '18:00'), (6, 5, '09:00', '18:00');

INSERT INTO time_off (master_id, day, reason) VALUES
    (1, '2026-11-25', 'отпуск'),
    (2, '2026-11-26', 'выходной'),
    (3, '2026-11-27', 'отпуск'),
    (5, '2026-11-28', 'больничный');

INSERT INTO appointments (id, master_id, service_id, client_name, appointment_date, start_time, status) VALUES
    (1,  1, 1, 'Семенов Аркадий Петрович',   '2026-11-20', '09:00', 'done'),
    (2,  1, 8, 'Тарасова Ольга Ивановна',    '2026-11-20', '11:00', 'done'),
    (3,  2, 3, 'Белов Игорь Николаевич',     '2026-11-19', '10:00', 'done'),
    (4,  3, 5, 'Гусев Павел Андреевич',      '2026-11-18', '08:00', 'done'),
    (5,  3, 4, 'Романова Вера Сергеевна',    '2026-11-17', '09:00', 'done'),
    (6,  4, 9, 'Крылов Егор Валерьевич',     '2026-11-16', '10:00', 'done'),
    (7,  5, 6, 'Мельникова Анна Львовна',    '2026-11-13', '11:00', 'done'),
    (8,  6, 8, 'Зайцев Роман Олегович',      '2026-11-12', '12:00', 'done'),
    (9,  1, 2, 'Фомина Ксения Павловна',     '2026-11-24', '13:00', 'planned'),
    (10, 2, 7, 'Дмитриев Лев Антонович',     '2026-11-24', '12:00', 'planned'),
    (11, 3, 3, 'Орлова Тамара Юрьевна',      '2026-11-25', '10:00', 'planned'),
    (12, 4, 1, 'Ефимов Станислав Игоревич',  '2026-11-25', '14:00', 'planned'),
    (13, 5, 9, 'Никитина Лидия Романовна',   '2026-11-26', '12:00', 'planned'),
    (14, 1, 7, 'Панов Григорий Тимурович',   '2026-11-27', '09:30', 'cancelled');

INSERT INTO works (id, master_id, service_id, work_date, price, appointment_id) VALUES
    (1,  1, 1, '2026-11-02', 1200.0, NULL),
    (2,  1, 2, '2026-11-03',  600.0, NULL),
    (3,  1, 7, '2026-11-05',  400.0, NULL),
    (4,  1, 8, '2026-11-09', 1500.0, NULL),
    (5,  1, 1, '2026-11-12', 1200.0, NULL),
    (6,  1, 8, '2026-11-16', 1500.0, NULL),
    (7,  1, 1, '2026-11-20', 1200.0, 1),
    (8,  1, 8, '2026-11-20', 1500.0, 2),
    (9,  2, 1, '2026-11-03', 1200.0, NULL),
    (10, 2, 2, '2026-11-04',  600.0, NULL),
    (11, 2, 7, '2026-11-06',  400.0, NULL),
    (12, 2, 3, '2026-11-10', 2500.0, NULL),
    (13, 2, 1, '2026-11-13', 1200.0, NULL),
    (14, 2, 7, '2026-11-17',  400.0, NULL),
    (15, 2, 3, '2026-11-19', 2500.0, 3),
    (16, 3, 4, '2026-11-02', 6000.0, NULL),
    (17, 3, 3, '2026-11-05', 2500.0, NULL),
    (18, 3, 8, '2026-11-07', 1500.0, NULL),
    (19, 3, 5, '2026-11-11', 3500.0, NULL),
    (20, 3, 4, '2026-11-14', 6000.0, NULL),
    (21, 3, 5, '2026-11-18', 3500.0, 4),
    (22, 3, 4, '2026-11-17', 6000.0, 5),
    (23, 4, 9, '2026-11-04', 2800.0, NULL),
    (24, 4, 1, '2026-11-06', 1200.0, NULL),
    (25, 4, 7, '2026-11-09',  400.0, NULL),
    (26, 4, 9, '2026-11-12', 2800.0, NULL),
    (27, 4, 1, '2026-11-16', 1200.0, 6),
    (28, 5, 6, '2026-11-03', 2200.0, NULL),
    (29, 5, 8, '2026-11-05', 1500.0, NULL),
    (30, 5, 9, '2026-11-07', 2800.0, NULL),
    (31, 5, 6, '2026-11-10', 2200.0, NULL),
    (32, 5, 8, '2026-11-13', 1500.0, 7),
    (33, 6, 1, '2026-11-02', 1200.0, NULL),
    (34, 6, 8, '2026-11-04', 1500.0, NULL),
    (35, 6, 2, '2026-11-06',  600.0, NULL),
    (36, 6, 1, '2026-11-10', 1200.0, NULL),
    (37, 6, 8, '2026-11-12', 1500.0, 8);

COMMIT;