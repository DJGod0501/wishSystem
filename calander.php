<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

if (isset($conn) && !isset($pdo)) $pdo = $conn;
if (!isset($pdo)) die('DB connection not found');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

if ($month < 1 || $month > 12) $month = (int)date('m');

$start = sprintf('%04d-%02d-01', $year, $month);
$end   = (new DateTime($start))->modify('+1 month')->format('Y-m-d');

// Fetch per-day per-stage counts
$stmt = $pdo->prepare("
    SELECT
        COALESCE(interview_date, DATE(created_at)) AS day,
        interview_stage,
        COUNT(*) AS total
    FROM interview_forms
    WHERE COALESCE(interview_date, DATE(created_at)) >= :start
      AND COALESCE(interview_date, DATE(created_at)) < :end
    GROUP BY day, interview_stage
");
$stmt->execute([':start' => $start, ':end' => $end]);

$counts = []; // $counts['YYYY-MM-DD'][stage] = total
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d = $r['day'];
    $s = $r['interview_stage'];
    $counts[$d][$s] = (int)$r['total'];
}

$stageLabels = [
    'new' => 'new',
    'scheduled' => 'scheduled',
    'interviewed' => 'interviewed',
    'passed' => 'passed',
    'failed' => 'failed',
    'no_show' => 'no_show',
    'withdrawn' => 'withdrawn',
];

$firstDay = new DateTime($start);
$daysInMonth = (int)$firstDay->format('t');
$startWeekday = (int)$firstDay->format('N'); // 1..7 (Mon..Sun)

// prev/next links
$prev = (new DateTime($start))->modify('-1 month');
$next = (new DateTime($start))->modify('+1 month');
$prevY = (int)$prev->format('Y'); $prevM = (int)$prev->format('m');
$nextY = (int)$next->format('Y'); $nextM = (int)$next->format('m');

include 'header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Admin Calendar: <?= htmlspecialchars($year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT)) ?></h4>
  <div>
    <a class="btn btn-outline-secondary btn-sm" href="calender.php?year=<?= $prevY ?>&month=<?= $prevM ?>">‹ Prev</a>
    <a class="btn btn-outline-secondary btn-sm" href="calender.php?year=<?= $nextY ?>&month=<?= $nextM ?>">Next ›</a>
  </div>
</div>

<table class="table table-bordered text-center align-middle">
  <thead>
    <tr>
      <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
    </tr>
  </thead>
  <tbody>
    <tr>
<?php
$cell = 1;

// empty cells before first day
for ($i = 1; $i < $startWeekday; $i++, $cell++) {
    echo "<td style='height:120px'></td>";
}

// calendar cells
for ($day = 1; $day <= $daysInMonth; $day++, $cell++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

    echo "<td style='height:120px; vertical-align:top;'>";

    echo "<div class='d-flex justify-content-between align-items-start'>";
    echo "<strong><a href='calander_day.php?day={$date}'>{$day}</a></strong>";
    echo "</div>";

    if (!empty($counts[$date])) {
        foreach ($stageLabels as $stage => $label) {
            if (!isset($counts[$date][$stage])) continue;
            $total = $counts[$date][$stage];
            echo "<div class='calendar-cell stage-{$stage} mt-1'>{$label} ({$total})</div>";
        }
    }

    echo "</td>";

    if ($cell % 7 === 0 && $day !== $daysInMonth) {
        echo "</tr><tr>";
    }
}

// fill trailing empty cells
while (($cell - 1) % 7 !== 0) {
    echo "<td style='height:120px'></td>";
    $cell++;
}
?>
    </tr>
  </tbody>
</table>

<?php include 'footer.php'; ?>
