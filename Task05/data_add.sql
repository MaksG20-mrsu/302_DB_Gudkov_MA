PRAGMA foreign_keys = ON;

BEGIN TRANSACTION;

-- 1. Добавление 5 пользователей (себя и четырех сокурсников)
-- Системное время вычисляется динамически с помощью функции datetime('now')
INSERT INTO users (name, email, gender, register_date, occupation_id)
VALUES 
    ('Nikita Ganin', 'ganin.n@mrsu.ru', 'male', datetime('now'), (SELECT id FROM occupations WHERE name = 'student')),
    ('Ivan Ivanov', 'ivanov.i@mrsu.ru', 'male', datetime('now'), (SELECT id FROM occupations WHERE name = 'student')),
    ('Aleksey Smirnov', 'smirnov.a@mrsu.ru', 'male', datetime('now'), (SELECT id FROM occupations WHERE name = 'student')),
    ('Daria Popova', 'popova.d@mrsu.ru', 'female', datetime('now'), (SELECT id FROM occupations WHERE name = 'student')),
    ('Elena Sokolova', 'sokolova.e@mrsu.ru', 'female', datetime('now'), (SELECT id FROM occupations WHERE name = 'student'));

-- 2. Добавление 3 фильмов различных жанров
INSERT INTO movies (title, year) VALUES ('Oppenheimer', 2023);
INSERT INTO movies (title, year) VALUES ('Dune: Part Two', 2024);
INSERT INTO movies (title, year) VALUES ('Spider-Man: Across the Spider-Verse', 2023);

-- 3. Добавление связей между фильмами и жанрами (без жестко заданных идентификаторов)
INSERT INTO movie_genres (movie_id, genre_id)
VALUES 
    ((SELECT id FROM movies WHERE title = 'Oppenheimer'), (SELECT id FROM genres WHERE name = 'Drama')),
    ((SELECT id FROM movies WHERE title = 'Dune: Part Two'), (SELECT id FROM genres WHERE name = 'Sci-Fi')),
    ((SELECT id FROM movies WHERE title = 'Dune: Part Two'), (SELECT id FROM genres WHERE name = 'Adventure')),
    ((SELECT id FROM movies WHERE title = 'Spider-Man: Across the Spider-Verse'), (SELECT id FROM genres WHERE name = 'Animation')),
    ((SELECT id FROM movies WHERE title = 'Spider-Man: Across the Spider-Verse'), (SELECT id FROM genres WHERE name = 'Action'));

-- 4. Добавление 3 отзывов от своего имени к созданным фильмам
INSERT INTO ratings (user_id, movie_id, rating, timestamp)
VALUES
    (
        (SELECT id FROM users WHERE email = 'ganin.n@mrsu.ru'),
        (SELECT id FROM movies WHERE title = 'Oppenheimer'),
        4.8,
        strftime('%s', 'now')
    ),
    (
        (SELECT id FROM users WHERE email = 'ganin.n@mrsu.ru'),
        (SELECT id FROM movies WHERE title = 'Dune: Part Two'),
        5.0,
        strftime('%s', 'now')
    ),
    (
        (SELECT id FROM users WHERE email = 'ganin.n@mrsu.ru'),
        (SELECT id FROM movies WHERE title = 'Spider-Man: Across the Spider-Verse'),
        4.9,
        strftime('%s', 'now')
    );

COMMIT;