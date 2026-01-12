<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  exit('Forbidden');
}

$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');

$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$startOfGrid = $firstDay->modify('monday this week');
$endOfMonth = $firstDay->modify('first day of next month');
$endOfGrid = $endOfMonth->modify('sunday this week')->modify('+1 day');

$startStr = $firstDay->format('Y-m-01');
$endStr  = $endOfMonth->format('Y-m-01'); // exclusive

// ✅ count per day for the month
$sql = "
SELECT DATE(interview_date) AS d, COUNT(*) AS total
FROM interview_forms
WHERE interview_date IS NOT NULL
  AND interview_date >= :start AND interview_date < :end
GROUP BY DATE(interview_date)
";
$stmt = $conn->prepare($sql);
$stmt->execute([':start' => $startStr, ':end' => $endStr]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dayCount = [];
foreach ($rows as $r) {
  $dayCount[$r['d']] = (int)$r['total'];
}

function day_badge(int $count): array {
  if ($count >= 6) return ['Busy', 'bg-danger'];
  if ($count >= 3) return ['Normal', 'bg-warning text-dark'];
  if ($count >= 1) return ['Light', 'bg-success'];
  return ['', ''];
}

$prev = $firstDay->modify('-1 month');
$next = $firstDay->modify('+1 month');
$todayKey = (new DateTimeImmutable('today'))->format('Y-m-d');

require_once __DIR__ . '/header.php';
?>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Admin Calendar — <?= htmlspecialchars($firstDay->format('F Y')) ?></h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="calender.php?year=<?= (int)$prev->format('Y') ?>&month=<?= (int)$prev->format('n') ?>">← Prev</a>
      <a class="btn btn-outline-secondary" href="calender.php?year=<?= (int)date('Y') ?>&month=<?= (int)date('n') ?>">Today</a>
      <a class="btn btn-outline-secondary" href="calender.php?year=<?= (int)$next->format('Y') ?>&month=<?= (int)$next->format('n') ?>">Next →</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered align-middle" style="table-layout: fixed;">
      <thead class="table-light">
        <tr>
          <th class="text-center">Mon</th><th class="text-center">Tue</th><th class="text-center">Wed</th>
          <th class="text-center">Thu</th><th class="text-center">Fri</th><th class="text-center">Sat</th><th class="text-center">Sun</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $cursor = $startOfGrid;
      while ($cursor < $endOfGrid) {
        echo "<tr>\n";
        for ($i = 0; $i < 7; $i++) {
          $dateKey = $cursor->format('Y-m-d');
          $inMonth = ($cursor->format('n') == $month);
          $cnt = $dayCount[$dateKey] ?? 0;

          [$label, $badgeClass] = day_badge($cnt);

          $cellClasses = [];
          if (!$inMonth) $cellClasses[] = 'bg-light';
          if ($dateKey === $todayKey) $cellClasses[] = 'border border-3 border-primary';

          $link = "calender_day.php?date=" . urlencode($dateKey);

          echo '<td class="' . htmlspecialchars(implode(' ', $cellClasses)) . '" style="height: 110px; vertical-align: top;">';
          echo '<div class="d-flex justify-content-between">';
          echo '<a href="' . htmlspecialchars($link) . '" class="text-decoration-none fw-semibold">' . (int)$cursor->format('j') . '</a>';
          if ($cnt > 0) echo '<span class="badge ' . htmlspecialchars($badgeClass) . '">' . htmlspecialchars($label) . '</span>';
          echo '</div>';

          if ($cnt > 0) {
            echo '<div class="mt-2 small text-muted">Interviews: <b>' . (int)$cnt . '</b></div>';
          }

          echo "</td>\n";
          $cursor = $cursor->modify('+1 day');
        }
        echo "</tr>\n";
      }
      ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
