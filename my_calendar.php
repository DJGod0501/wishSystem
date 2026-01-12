<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

// Admin guard
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

// DB guard (your project uses $conn)
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    exit('DB connection $conn not found');
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function loadLabel(int $n): array {
    if ($n === 0) return ['None', 'secondary'];
    if ($n <= 2)  return ['Light', 'success'];
    if ($n <= 4)  return ['Normal', 'warning'];
    return ['Busy', 'danger'];
}

// Month handling
$ym = $_GET['ym'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');

$year  = (int)substr($ym, 0, 4);
$month = (int)substr($ym, 5, 2);
if ($month < 1 || $month > 12) {
    $year = (int)date('Y');
    $month = (int)date('m');
    $ym = sprintf('%04d-%02d', $year, $month);
}

$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int)$firstDay->format('t');
$startWeekday = (int)$firstDay->format('N'); // 1=Mon

$rangeStart = $firstDay->format('Y-m-01 00:00:00');
$rangeEnd   = $firstDay->modify('+1 month')->format('Y-m-01 00:00:00');

// Load interview counts
$sql = "
    SELECT DATE(interview_date) AS d, COUNT(*) AS cnt
    FROM interview_forms
    WHERE interview_date IS NOT NULL
      AND interview_date >= :start
      AND interview_date < :end
    GROUP BY DATE(interview_date)
";
$stmt = $conn->prepare($sql);
$stmt->execute([':start' => $rangeStart, ':end' => $rangeEnd]);

$counts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $counts[$row['d']] = (int)$row['cnt'];
}

// Nav
$prevYm = $firstDay->modify('-1 month')->format('Y-m');
$nextYm = $firstDay->modify('+1 month')->format('Y-m');

$pageTitle = 'Admin Calendar';
require_once __DIR__ . '/header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3><?= h($firstDay->format('F Y')) ?></h3>
    <div>
      <a class="btn btn-outline-secondary btn-sm" href="/wishSystem/calendar.php?ym=<?= h($prevYm) ?>">← Prev</a>
      <a class="btn btn-outline-secondary btn-sm" href="/wishSystem/calendar.php">Today</a>
      <a class="btn btn-outline-secondary btn-sm" href="/wishSystem/calendar.php?ym=<?= h($nextYm) ?>">Next →</a>
    </div>
  </div>

  <table class="table table-bordered text-center align-middle">
    <thead class="table-light">
      <tr>
        <th>Mon</th><th>Tue</th><th>Wed</th>
        <th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <?php
        for ($i = 1; $i < $startWeekday; $i++) {
            echo '<td class="bg-light"></td>';
        }

        $day = 1;
        $cell = $startWeekday;

        while ($day <= $daysInMonth) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $cnt = $counts[$dateStr] ?? 0;
            [$label, $color] = loadLabel($cnt);

            echo '<td style="height:90px; vertical-align:top">';
            echo '<div class="fw-bold">' . (int)$day . '</div>';
            echo '<span class="badge text-bg-' . h($color) . '">' . (int)$cnt . '</span><br>';
            echo '<small>' . h($label) . '</small><br>';
            echo '<a class="btn btn-sm btn-outline-primary mt-1" href="/wishSystem/calendar_day.php?date=' . h($dateStr) . '">View</a>';
            echo '</td>';

            $day++;
            $cell++;

            if ($cell > 7 && $day <= $daysInMonth) {
                echo '</tr><tr>';
                $cell = 1;
            }
        }

        if ($cell !== 1) {
            for ($i = $cell; $i <= 7; $i++) {
                echo '<td class="bg-light"></td>';
            }
        }
        ?>
      </tr>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
