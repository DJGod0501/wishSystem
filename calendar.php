<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  exit('Forbidden');
}

// db.php 里一般是 $conn (PDO)
if (!isset($conn) || !($conn instanceof PDO)) {
  http_response_code(500);
  exit('DB connection $conn not found in db.php');
}

$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');

if ($year < 2000 || $year > 2100) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');

$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$startOfGrid = $firstDay->modify('monday this week');
$endOfMonth = $firstDay->modify('first day of next month');
$endOfGrid = $endOfMonth->modify('sunday this week')->modify('+1 day'); // exclusive

$monthStart = $firstDay->format('Y-m-01 00:00:00');
$monthEndEx = $endOfMonth->format('Y-m-01 00:00:00'); // exclusive

$stmt = $conn->prepare("
  SELECT DATE(interview_date) AS d, COUNT(*) AS cnt
  FROM interview_forms
  WHERE interview_date IS NOT NULL
    AND interview_date >= :start AND interview_date < :end
  GROUP BY DATE(interview_date)
");
$stmt->execute([':start' => $monthStart, ':end' => $monthEndEx]);

$dayCount = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $dayCount[$r['d']] = (int)$r['cnt'];
}

function badge_for(int $cnt): array {
  if ($cnt >= 6) return ['Busy', 'bg-danger'];
  if ($cnt >= 3) return ['Normal', 'bg-warning text-dark'];
  if ($cnt >= 1) return ['Light', 'bg-success'];
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
      <a class="btn btn-outline-secondary" href="calendar.php?y=<?= (int)$prev->format('Y') ?>&m=<?= (int)$prev->format('n') ?>">← Prev</a>
      <a class="btn btn-outline-secondary" href="calendar.php">Today</a>
      <a class="btn btn-outline-secondary" href="calendar.php?y=<?= (int)$next->format('Y') ?>&m=<?= (int)$next->format('n') ?>">Next →</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered align-middle" style="table-layout: fixed;">
      <thead class="table-light">
        <tr class="text-center">
          <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $cursor = $startOfGrid;
      while ($cursor < $endOfGrid) {
        echo "<tr>\n";
        for ($i = 0; $i < 7; $i++) {
          $dateKey = $cursor->format('Y-m-d');
          $inMonth = ((int)$cursor->format('n') === $month);
          $cnt = $dayCount[$dateKey] ?? 0;
          [$label, $badgeClass] = badge_for($cnt);

          $cellStyle = $inMonth ? '' : 'background:#f8f9fa;';
          $todayBorder = ($dateKey === $todayKey) ? 'border:2px solid #0d6efd;' : '';

          echo "<td style='vertical-align:top; height:110px; $cellStyle $todayBorder'>";
          echo "<div class='d-flex justify-content-between'>";
          echo "<a class='text-decoration-none fw-semibold' href='calendar_day.php?date=" . urlencode($dateKey) . "'>" . (int)$cursor->format('j') . "</a>";
          if ($cnt > 0) echo "<span class='badge $badgeClass'>$label</span>";
          echo "</div>";

          if ($cnt > 0) {
            echo "<div class='mt-2 small text-muted'>Interviews: <b>$cnt</b></div>";
          } else {
            echo "<div class='mt-2 small text-muted'>No interviews</div>";
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

  <div class="small text-muted mt-2">
    只有设置了 <b>interview_date</b> 的记录才会出现在月历。
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
