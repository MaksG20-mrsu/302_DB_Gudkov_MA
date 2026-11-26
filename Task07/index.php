<?php

declare(strict_types=1);

$dbPath = __DIR__ . '/sto.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$masters = $pdo->query("
    SELECT 
        id,
        last_name || ' ' || first_name || COALESCE(' ' || middle_name, '') AS full_name,
        fire_date
    FROM masters
    ORDER BY last_name ASC
")->fetchAll();

$selectedMasterId = null;
if (isset($_GET['master_id']) && $_GET['master_id'] !== '' && ctype_digit((string)$_GET['master_id'])) {
    $selectedMasterId = (int)$_GET['master_id'];
}

$sql = "
    SELECT 
        m.id AS master_id,
        m.last_name || ' ' || m.first_name || COALESCE(' ' || m.middle_name, '') AS full_name,
        w.work_date,
        s.name AS service_name,
        w.price
    FROM works w
    JOIN masters m ON w.master_id = m.id
    JOIN services s ON w.service_id = s.id
";

$params = [];
if ($selectedMasterId !== null) {
    $sql .= " WHERE m.id = :master_id";
    $params[':master_id'] = $selectedMasterId;
}

$sql .= " ORDER BY m.last_name ASC, w.work_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$works = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Услуги, оказанные мастерами СТО</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; color: #222; }
        form { margin-bottom: 1.5rem; }
        select, button { padding: 0.4rem 0.7rem; font-size: 1rem; }
        table { border-collapse: collapse; width: 100%; max-width: 950px; }
        th, td { border: 1px solid #ccc; padding: 0.6rem; text-align: left; }
        th { background-color: #f4f4f4; }
        td.num { text-align: right; }
    </style>
</head>
<body>
    <h1>Список оказанных услуг СТО</h1>

    <form method="GET" action="">
        <label for="master_id">Мастер:</label>
        <select name="master_id" id="master_id">
            <option value="">-- Все мастера --</option>
            <?php foreach ($masters as $master): ?>
                <option value="<?= (int)$master['id'] ?>" <?= ($selectedMasterId === (int)$master['id']) ? 'selected' : '' ?>>
                    [<?= (int)$master['id'] ?>] <?= htmlspecialchars($master['full_name'], ENT_QUOTES, 'UTF-8') ?><?= $master['fire_date'] !== null ? ' (уволен)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Фильтровать</button>
    </form>

    <?php if (empty($works)): ?>
        <p>Записи об оказанных услугах не найдены.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Номер мастера</th>
                    <th>ФИО</th>
                    <th>Дата работы</th>
                    <th>Услуга</th>
                    <th>Стоимость (руб.)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($works as $work): ?>
                    <tr>
                        <td class="num"><?= (int)$work['master_id'] ?></td>
                        <td><?= htmlspecialchars($work['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($work['work_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($work['service_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="num"><?= number_format((float)$work['price'], 2, '.', ' ') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>