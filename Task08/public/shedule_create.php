<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
$pdo = getDbConnection();

$message = '';
$error = '';

$daysOfWeek = [
    1 => 'Понедельник',
    2 => 'Вторник',
    3 => 'Среда',
    4 => 'Четверг',
    5 => 'Пятница',
    6 => 'Суббота',
    7 => 'Воскресенье',
];

$masters = $pdo->query("
    SELECT id, last_name || ' ' || first_name || COALESCE(' ' || middle_name, '') AS full_name
    FROM masters
    WHERE fire_date IS NULL
    ORDER BY last_name ASC
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $masterId = (int)($_POST['master_id'] ?? 0);
    $dayOfWeek = (int)($_POST['day_of_week'] ?? 0);
    $startTime = trim((string)($_POST['start_time'] ?? ''));
    $endTime = trim((string)($_POST['end_time'] ?? ''));

    if ($masterId <= 0 || !isset($daysOfWeek[$dayOfWeek]) || $startTime === '' || $endTime === '') {
        $error = 'Заполните все поля формы.';
    } elseif ($startTime >= $endTime) {
        $error = 'Время начала работы должно быть меньше времени окончания.';
    } else {
        try {
            // Обновляем смену на выбранный день недели, если она уже задана, либо добавляем новую
            $stmt = $pdo->prepare("
                INSERT INTO work_schedule (master_id, day_of_week, start_time, end_time)
                VALUES (:master_id, :day_of_week, :start_time, :end_time)
                ON CONFLICT(master_id, day_of_week) DO UPDATE SET
                    start_time = excluded.start_time,
                    end_time = excluded.end_time
            ");
            $stmt->execute([
                ':master_id' => $masterId,
                ':day_of_week' => $dayOfWeek,
                ':start_time' => $startTime,
                ':end_time' => $endTime,
            ]);
            $message = 'График работы мастера успешно сохранён.';
        } catch (Throwable $e) {
            $error = 'Ошибка при сохранении графика: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>График работы мастера</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; max-width: 600px; }
        .field { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.3rem; font-weight: bold; }
        select, input { width: 100%; padding: 0.4rem; box-sizing: border-box; }
        .msg { color: green; margin-bottom: 1rem; font-weight: bold; }
        .err { color: red; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <p><a href="index.php">&larr; На главную</a></p>
    <h1>График работы мастера</h1>

    <?php if ($message !== ''): ?>
        <div class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="field">
            <label for="master_id">Мастер *</label>
            <select name="master_id" id="master_id" required>
                <option value="">-- Выберите мастера --</option>
                <?php foreach ($masters as $master): ?>
                    <option value="<?= (int)$master['id'] ?>">
                        <?= htmlspecialchars($master['full_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="day_of_week">День недели *</label>
            <select name="day_of_week" id="day_of_week" required>
                <option value="">-- Выберите день недели --</option>
                <?php foreach ($daysOfWeek as $num => $name): ?>
                    <option value="<?= $num ?>"><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="start_time">Время начала работы *</label>
            <input type="time" id="start_time" name="start_time" value="09:00" required>
        </div>
        <div class="field">
            <label for="end_time">Время окончания работы *</label>
            <input type="time" id="end_time" name="end_time" value="18:00" required>
        </div>
        <button type="submit">Сохранить график</button>
    </form>
</body>
</html>