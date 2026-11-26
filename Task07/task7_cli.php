<?php

declare(strict_types=1);

$dbPath = __DIR__ . '/sto.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    fwrite(STDERR, "Ошибка подключения к БД: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$masters = $pdo->query("
    SELECT 
        id,
        last_name,
        first_name,
        COALESCE(middle_name, '') AS middle_name,
        fire_date
    FROM masters
    ORDER BY id ASC
")->fetchAll();

if (empty($masters)) {
    echo "В базе данных нет мастеров." . PHP_EOL;
    exit(0);
}

echo "Список мастеров:" . PHP_EOL;
$validIds = [];
foreach ($masters as $master) {
    $validIds[] = (int)$master['id'];
    $fullName = trim("{$master['last_name']} {$master['first_name']} {$master['middle_name']}");
    $status = $master['fire_date'] !== null ? " (уволен {$master['fire_date']})" : '';
    echo "  [{$master['id']}] {$fullName}{$status}" . PHP_EOL;
}

echo PHP_EOL . "Введите номер мастера (или нажмите Enter для вывода всех): ";
$input = trim((string)fgets(STDIN));

$selectedMasterId = null;
if ($input !== '') {
    if (!ctype_digit($input) || !in_array((int)$input, $validIds, true)) {
        fwrite(STDERR, "Ошибка: мастер с таким номером не найден." . PHP_EOL);
        exit(1);
    }
    $selectedMasterId = (int)$input;
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
$rows = $stmt->fetchAll();

if (empty($rows)) {
    echo "Записи об оказанных услугах не найдены." . PHP_EOL;
    exit(0);
}

$headers = ['ID', 'ФИО мастера', 'Дата работы', 'Услуга', 'Стоимость'];
$widths = array_map('mb_strlen', $headers);

foreach ($rows as &$row) {
    $row['price_fmt'] = number_format((float)$row['price'], 2, '.', ' ');
    $widths[0] = max($widths[0], mb_strlen((string)$row['master_id']));
    $widths[1] = max($widths[1], mb_strlen($row['full_name']));
    $widths[2] = max($widths[2], mb_strlen($row['work_date']));
    $widths[3] = max($widths[3], mb_strlen($row['service_name']));
    $widths[4] = max($widths[4], mb_strlen($row['price_fmt']));
}
unset($row);

function mbStrPad(string $str, int $padLen, int $padType = STR_PAD_RIGHT): string
{
    $diff = $padLen - mb_strlen($str);
    if ($diff <= 0) {
        return $str;
    }
    $padding = str_repeat(' ', $diff);
    return $padType === STR_PAD_LEFT ? $padding . $str : $str . $padding;
}

function printBorder(array $widths, string $left, string $mid, string $right): void
{
    $parts = array_map(static fn(int $w): string => str_repeat('─', $w + 2), $widths);
    echo $left . implode($mid, $parts) . $right . PHP_EOL;
}

function printRow(array $cols, array $widths): void
{
    $formatted = [];
    foreach ($cols as $i => $col) {
        $align = ($i === 0 || $i === 4) ? STR_PAD_LEFT : STR_PAD_RIGHT;
        $formatted[] = ' ' . mbStrPad((string)$col, $widths[$i], $align) . ' ';
    }
    echo '│' . implode('│', $formatted) . '│' . PHP_EOL;
}

printBorder($widths, '┌', '┬', '┐');
printRow($headers, $widths);
printBorder($widths, '├', '┼', '┤');
foreach ($rows as $row) {
    printRow([
        $row['master_id'],
        $row['full_name'],
        $row['work_date'],
        $row['service_name'],
        $row['price_fmt'],
    ], $widths);
}
printBorder($widths, '└', '┴', '┘');