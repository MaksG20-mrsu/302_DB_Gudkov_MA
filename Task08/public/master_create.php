<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
$pdo = getDbConnection();

$message = '';
$error = '';

$services = $pdo->query("SELECT id, name, duration_minutes, price FROM services ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lastName = trim((string)($_POST['last_name'] ?? ''));
    $firstName = trim((string)($_POST['first_name'] ?? ''));
    $middleName = trim((string)($_POST['middle_name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $hireDate = trim((string)($_POST['hire_date'] ?? date('Y-m-d')));
    $salaryPercent = (float)($_POST['salary_percent'] ?? 20.0);
    $selectedServices = $_POST['services'] ?? [];

    if ($lastName === '' || $firstName === '') {
        $error = 'Заполните обязательные поля: Фамилия и Имя.';
    } elseif ($salaryPercent < 0 || $salaryPercent > 100) {
        $error = 'Процент зарплаты должен быть от 0 до 100.';
    } elseif (!is_array($selectedServices) || empty($selectedServices)) {
        $error = 'Выберите хотя бы одну услугу, которую может выполнять мастер.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO masters (first_name, last_name, middle_name, phone, hire_date, salary_percent)
                VALUES (:first_name, :last_name, :middle_name, :phone, :hire_date, :salary_percent)
            ");
            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':middle_name' => $middleName !== '' ? $middleName : null,
                ':phone' => $phone !== '' ? $phone : null,
                ':hire_date' => $hireDate !== '' ? $hireDate : date('Y-m-d'),
                ':salary_percent' => $salaryPercent,
            ]);

            $masterId = (int)$pdo->lastInsertId();

            $linkStmt = $pdo->prepare("
                INSERT INTO master_services (master_id, service_id)
                VALUES (:master_id, :service_id)
            ");
            foreach ($selectedServices as $serviceId) {
                $linkStmt->execute([
                    ':master_id' => $masterId,
                    ':service_id' => (int)$serviceId,
                ]);
            }

            $pdo->commit();
            $message = 'Мастер и перечень выполняемых им работ успешно добавлены.';
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавление мастера</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; max-width: 620px; }
        .field { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.3rem; font-weight: bold; }
        input[type="text"], input[type="date"], input[type="number"] { width: 100%; padding: 0.4rem; box-sizing: border-box; }
        .checkbox-group label { font-weight: normal; margin-bottom: 0.25rem; }
        .msg { color: green; margin-bottom: 1rem; font-weight: bold; }
        .err { color: red; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <p><a href="index.php">&larr; На главную</a></p>
    <h1>Добавление мастера СТО</h1>

    <?php if ($message !== ''): ?>
        <div class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="field">
            <label for="last_name">Фамилия *</label>
            <input type="text" id="last_name" name="last_name" required>
        </div>
        <div class="field">
            <label for="first_name">Имя *</label>
            <input type="text" id="first_name" name="first_name" required>
        </div>
        <div class="field">
            <label for="middle_name">Отчество</label>
            <input type="text" id="middle_name" name="middle_name">
        </div>
        <div class="field">
            <label for="phone">Телефон</label>
            <input type="text" id="phone" name="phone" placeholder="+7 917 000-00-00">
        </div>
        <div class="field">
            <label for="hire_date">Дата приёма на работу *</label>
            <input type="date" id="hire_date" name="hire_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="field">
            <label for="salary_percent">Процент от выручки (%)</label>
            <input type="number" id="salary_percent" name="salary_percent" min="0" max="100" step="0.5" value="20">
        </div>
        <div class="field checkbox-group">
            <label>Выполняемые услуги *</label>
            <?php foreach ($services as $service): ?>
                <label>
                    <input type="checkbox" name="services[]" value="<?= (int)$service['id'] ?>">
                    <?= htmlspecialchars($service['name'], ENT_QUOTES, 'UTF-8') ?>
                    (<?= (int)$service['duration_minutes'] ?> мин, <?= number_format((float)$service['price'], 2, '.', ' ') ?> руб.)
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit">Сохранить мастера</button>
    </form>
</body>
</html>