<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/header.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] === 'admin') {
    // staff only (online_posting)
    // 如果你允许 admin 也看，就把这段删掉
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$db = null;
if (isset($pdo) && $pdo instanceof PDO) $db = $pdo;
if (isset($conn) && $conn instanceof PDO) $db = $conn;

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');

$firstDay = $month . '-01';
$startTs = strtotime($firstDay);
$daysInMonth = (int)date('t', $startTs);
$startWeekday = (int)date('N', $startTs);

$prevMonth = date('Y-m', strtotime('-1 month', $startTs));
$nextMonth = date('Y-m', strtotime('+1 month', $startTs));

// badge counts per day (staff submissions by created_at date)
$counts = [];
if ($db && $userId > 0) {
    $monthStart = $month . '-01 00:00:00';
    $monthEnd = date('Y-m-t', $startTs) . ' 23:59:59';

    $stmt = $db->prepare("
        SELECT DATE(created_at) AS d, COUNT(*) AS c
        FROM interview_forms
        WHERE user_id = ? AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
    ");
    $stmt->execute([$userId, $monthStart, $monthEnd]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $counts[$r['d']] = (int)$r['c'];
    }
}
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a class="btn btn-outline-secondary" href="my_calender.php?month=<?= h($prevMonth) ?>">&laquo; Prev</a>
    <h3 class="mb-0"><?= h(date('F Y', $startTs)) ?></h3>
    <a class="btn btn-outline-secondary" href="my_calender.php?month=<?= h($nextMonth) ?>">Next &raquo;</a>
  </div>

  <table class="table table-bordered text-center align-middle">
    <thead class="table-light">
      <tr>
        <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <?php
        for ($i = 1; $i < $startWeekday; $i++) echo '<td></td>';

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $cellDate = sprintf('%s-%02d', $month, $day);
            $badge = $counts[$cellDate] ?? 0;

            echo '<td>';
            echo '<div class="d-flex justify-content-between align-items-start">';
            echo '<a class="fw-semibold text-decoration-none" href="my_calender_day.php?date=' . h($cellDate) . '">';
            echo $day;
            echo '</a>';
            if ($badge > 0) echo '<span class="badge bg-success">' . $badge . '</span>';
            echo '</div>';
            echo '</td>';

            if ((($day + $startWeekday - 1) % 7) === 0) echo '</tr><tr>';
        }

        $endCells = (7 - (($daysInMonth + $startWeekday - 1) % 7)) % 7;
        for ($i = 0; $i < $endCells; $i++) echo '<td></td>';
        ?>
      </tr>
    </tbody>
  </table>

  <div class="text-muted small">Tip: click a date to view your submissions (by created_at).</div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
