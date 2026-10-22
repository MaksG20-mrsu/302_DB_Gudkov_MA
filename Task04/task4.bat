-- 1. Пары пользователей, оценивших один и тот же фильм (первые 100)
SELECT DISTINCT
    u1.name AS user1,
    u2.name AS user2,
    m.title AS movie_title
FROM ratings r1
JOIN ratings r2 ON r1.movie_id = r2.movie_id AND r1.user_id < r2.user_id
JOIN users u1 ON u1.id = r1.user_id
JOIN users u2 ON u2.id = r2.user_id
JOIN movies m ON m.id = r1.movie_id
LIMIT 100;

-- 2. 10 самых свежих оценок от разных пользователей
SELECT m.title,
       u.name,
       r.rating,
       date(r.timestamp, 'unixepoch') AS review_date
FROM ratings r
JOIN users u ON u.id = r.user_id
JOIN movies m ON m.id = r.movie_id
GROUP BY r.user_id
HAVING r.timestamp = MAX(r.timestamp)
ORDER BY r.timestamp DESC
LIMIT 10;

-- 3. Фильмы с максимальным и минимальным средним рейтингом + «Рекомендуем»
WITH avg_ratings AS (
    SELECT m.id,
           m.title,
           m.year,
           AVG(r.rating) AS avg_rating
    FROM movies m
    JOIN ratings r ON r.movie_id = m.id
    GROUP BY m.id
),
extremes AS (
    SELECT * FROM avg_ratings WHERE avg_rating = (SELECT MAX(avg_rating) FROM avg_ratings)
    UNION ALL
    SELECT * FROM avg_ratings WHERE avg_rating = (SELECT MIN(avg_rating) FROM avg_ratings)
)
SELECT title,
       year,
       avg_rating,
       CASE WHEN avg_rating = (SELECT MAX(avg_rating) FROM avg_ratings) THEN 'Да' ELSE 'Нет' END AS "Рекомендуем"
FROM extremes
ORDER BY year, title;

-- 4. Количество и средняя оценка от женщин за 2010–2012 гг.
SELECT COUNT(*) AS rating_count,
       AVG(r.rating) AS avg_rating
FROM ratings r
JOIN users u ON u.id = r.user_id
WHERE u.gender = 'F'
  AND date(r.timestamp, 'unixepoch') BETWEEN '2010-01-01' AND '2012-12-31';

-- 5. Список фильмов со средней оценкой и местом в рейтинге (первые 20)
SELECT m.title,
       m.year,
       AVG(r.rating) AS avg_rating,
       RANK() OVER (ORDER BY AVG(r.rating) DESC) AS rating_rank
FROM movies m
JOIN ratings r ON r.movie_id = m.id
GROUP BY m.id
ORDER BY m.year, m.title
LIMIT 20;

-- 6. 10 последних зарегистрированных пользователей в формате "Фамилия Имя|Дата"
SELECT
    SUBSTR(u.name, INSTR(u.name, ' ') + 1) || ' ' || SUBSTR(u.name, 1, INSTR(u.name, ' ') - 1) AS "Фамилия Имя",
    '|' || u.register_date AS "|Дата регистрации"
FROM users u
ORDER BY u.register_date DESC
LIMIT 10;

-- 7. Таблица умножения через рекурсивный CTE
WITH RECURSIVE multiplication(i, j) AS (
    SELECT 1, 1
    UNION ALL
    SELECT i, j + 1 FROM multiplication WHERE j < 10
    UNION ALL
    SELECT i + 1, 1 FROM multiplication WHERE j = 10 AND i < 10
)
SELECT i || 'x' || j || '=' || (i * j) AS result
FROM multiplication
ORDER BY i, j;

-- 8. Все жанры фильмов через рекурсивный CTE
WITH RECURSIVE split_genres(id, genre, rest) AS (
    SELECT id,
           SUBSTR(genres, 1, INSTR(genres || '|', '|') - 1) AS genre,
           SUBSTR(genres, INSTR(genres || '|', '|') + 1) AS rest
    FROM movies
    UNION ALL
    SELECT id,
           SUBSTR(rest, 1, INSTR(rest || '|', '|') - 1),
           SUBSTR(rest, INSTR(rest || '|', '|') + 1)
    FROM split_genres
    WHERE rest != ''
)
SELECT DISTINCT genre FROM split_genres ORDER BY genre;