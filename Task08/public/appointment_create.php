<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
$pdo = getDbConnection();

$message = '';
$error = '';

$selectedServiceId = isset($_REQUEST['service_id']) && ctype_digit((string)$_REQUEST['service_id'])
    ? (int)$_REQUEST['service_id']
    : 0;

$selectedMasterId = isset($_REQUEST['master_id']) && ctype_digit((string)$_REQUEST['master_id'])
    ? (int)$_REQUEST['master_id']
    : 0;

// Обработка записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking'])) {
    $slot = trim((string)($_POST['slot'] ?? '')); // "YYYY-MM-DD|HH:MM"
    $clientName = trim((string)($_POST['client_name'] ?? ''));

    if ($selectedServiceId <= 0 || $selectedMasterId <= 0 || $slot === '' || $clientName === '') {
        $error = 'Заполните все поля для оформления записи.';
    } else {
        $parts = explode('|', $slot);
        if (count($parts) !== 2) {
            $error = 'Некорректный формат времени.';
        } else {
            [$appDate, $startTime] = $parts;
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (master_id, service_id, client_name, appointment_date, start_time, status)
                    VALUES (:master_id, :service_id, :client_name, :appointment_date, :start_time, 'planned')
                ");
                $stmt->execute([
                    ':master_id' => $selectedMasterId,
                    ':service_id' => $selectedServiceId,
                    ':client_name' => $clientName,
                    ':appointment_date' => $appDate,
                    ':start_time' => $startTime,
                ]);
                $message = "Запись успешно создана на {$appDate} в {$startTime}.";
                $selectedServiceId = 0;
                $selectedMasterId = 0;
            } catch (Throwable $e) {
                $error = 'Ошибка при сохранении записи: ' . $e->getMessage();
            }
        }
    }
}

// 1. Справочник услуг
$services = $pdo->query("SELECT id, name, duration_minutes, price FROM services ORDER BY name ASC")->fetchAll();
$selectedService = null;
foreach ($services as $srv) {
    if ((int)$srv['id'] === $selectedServiceId) {
        $selectedService = $srv;
        break;
    }
}

// 2. Только работающие мастера (fire_date IS NULL), оказывающие выбранную услугу
$masters = [];
if ($selectedService !== null) {
    $mStmt = $pdo->prepare("
        SELECT m.id, m.last_name || ' ' || m.first_name || COALESCE(' ' || m.middle_name, '') AS full_name
        FROM masters m
        JOIN master_services ms ON m.id = ms.master_id
        WHERE ms.service_id = :service_id AND m.fire_date IS NULL
        ORDER BY m.last_name ASC
    ");
    $mStmt->execute([':service_id' => $selectedServiceId]);
    $masters = $mStmt->fetchAll();
}

// 3. Расчет доступного времени на ближайшие 7 дней по work_schedule с учётом time_off и appointments
$availableSlots = [];
if ($selectedService !== null && $selectedMasterId > 0) {
    $duration = (int)$selectedService['duration_minutes'];

    // График по дням недели (1..7)
    $schedStmt = $pdo->prepare("SELECT day_of_week, start_time, end_time FROM work_schedule WHERE master_id = :master_id");
    $schedStmt->execute([':master_id' => $selectedMasterId]);
    $scheduleByDow = [];
    foreach ($schedStmt->fetchAll() as $row) {
        $scheduleByDow[(int)$row['day_of_week']] = $row;
    }

    // Нерабочие дни (отпуска/больничные)
    $offStmt = $pdo->prepare("SELECT day FROM time_off WHERE master_id = :master_id AND day >= date('now')");
    $offStmt->execute([':master_id' => $selectedMasterId]);
    $daysOff = array_flip($offStmt->fetchAll(PDO::FETCH_COLUMN));

    // Занятые интервалы из appointments (учитываем длительность каждой записанной услуги)
    $appStmt = $pdo->prepare("
        SELECT a.appointment_date, a.start_time, s.duration_minutes
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.master_id = :master_id
          AND a.status != 'cancelled'
          AND a.appointment_date >= date('now')
    ");
    $appStmt->execute([':master_id' => $selectedMasterId]);
    $busyByDate = [];
    foreach ($appStmt->fetchAll() as $app) {
        [$h, $m] = array_map('intval', explode(':', $app['start_time']));
        $startMin = $h * 60 + $m;
        $endMin = $startMin + (int)$app['duration_minutes'];
        $busyByDate[$app['appointment_date']][] = [$startMin, $endMin];
    }

    $today = new DateTimeImmutable('today');
    for ($i = 0; $i < 7; $i++) {
        $dateObj = $today->modify("+{$i} days");
        $dateStr = $dateObj->format('Y-m-d');
        $dow = (int)$dateObj->format('N'); // 1 (пн) .. 7 (вс)

        if (!isset($scheduleByDow[$dow]) || isset($daysOff[$dateStr])) {
            continue;
        }

        [$sh, $sm] = array_map('intval', explode(':', $scheduleByDow[$dow]['start_time']));
        [$eh, $em] = array_map('intval', explode(':', $scheduleByDow[$dow]['end_time']));
        $workStart = $sh * 60 + $sm;
        $workEnd = $eh * 60 + $em;

        // Шаг сетки записи — 30 минут
        for ($t = $workStart; $t + $duration <= $workEnd; $t += 30) {
            $slotEnd = $t + $duration;
            $isBusy = false;

            foreach ($busyByDate[$dateStr] ?? [] as [$bStart, $bEnd]) {
                if ($t < $bEnd && $slotEnd > $bStart) {
                    $isBusy = true;
                    break;
                }
            }

            if (!$isBusy) {
                $timeFormatted = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
                $availableSlots[] = [
                    'value' => "{$dateStr}|{$timeFormatted}",
                    'label' => "{$dateStr} в {$timeFormatted}",
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Предварительная запись к мастеру</title>
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
    <h1>Предварительная запись к мастеру</h1>

    <?php if ($message !== ''): ?>
        <div class="msg"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="GET" action="">
        <div class="field">
            <label for="service_id">1. Услуга *</label>
            <select name="service_id" id="service_id" onchange="const m = document.getElementById('master_id'); if (m) m.value=''; this.form.submit();">
                <option value="">-- Выберите услугу --</option>
                <?php foreach ($services as $srv): ?>
                    <option value="<?= (int)$srv['id'] ?>" <?= ($selectedServiceId === (int)$srv['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($srv['name'], ENT_QUOTES, 'UTF-8') ?>
                        (<?= (int)$srv['duration_minutes'] ?> мин, <?= number_format((float)$srv['price'], 2, '.', ' ') ?> руб.)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($selectedService !== null): ?>
            <div class="field">
                <label for="master_id">2. Мастер *</label>
                <?php if (empty($masters)): ?>
                    <p>Нет доступных мастеров для этой услуги.</p>
                <?php else: ?>
                    <select name="master_id" id="master_id" onchange="this.form.submit();">
                        <option value="">-- Выберите мастера --</option>
                        <?php foreach ($masters as $m): ?>
                            <option value="<?= (int)$m['id'] ?>" <?= ($selectedMasterId === (int)$m['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['full_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </form>

    <?php if ($selectedService !== null && $selectedMasterId > 0): ?>
        <?php if (empty($availableSlots)): ?>
            <p>У выбранного мастера нет свободного времени на ближайшую неделю.</p>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="service_id" value="<?= $selectedServiceId ?>">
                <input type="hidden" name="master_id" value="<?= $selectedMasterId ?>">

                <div class="field">
                    <label for="slot">3. Доступное время *</label>
                    <select name="slot" id="slot" required>
                        <option value="">-- Выберите дату и время --</option>
                        <?php foreach ($availableSlots as $slot): ?>
                            <option value="<?= htmlspecialchars($slot['value'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($slot['label'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="client_name">ФИО клиента *</label>
                    <input type="text" id="client_name" name="client_name" required>
                </div>

                <button type="submit" name="confirm_booking" value="1">Записаться</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html> 