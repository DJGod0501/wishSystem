<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

/**
 * Minimal authz (fallback). If you already have require_admin() helper,
 * you can replace this block with your own.
 */
function require_admin_fallback(): void {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
}
require_admin_fallback();

/**
 * CSRF fallback helpers if your csrf.php uses different names.
 * If your project already provides csrf_token()/csrf_validate(), these won't be used.
 */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}
if (!function_exists('csrf_validate')) {
    function csrf_validate(?string $token): bool {
        return isset($_SESSION['_csrf']) && is_string($token) && hash_equals($_SESSION['_csrf'], $token);
    }
}

$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');

if ($year < 2000 || $year > 2100) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');

$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$startOfGrid = $firstDay->modify('monday this week'); // grid starts Monday
$endOfMonth = $firstDay->modify('first day of next month');
$endOfGrid = $endOfMonth->modify('sunday this week')->modify('+1 day'); // exclusive end

$startStr = $firstDay->format('Y-m-01');
$endStrExclusive = $endOfMonth->format('Y-m-01'); // first day next month

// Fetch per-day counts for the month, key = YYYY-MM-DD
$sql = "
SELECT
  DATE(interview_date) AS d,
  COUNT(*) AS total,
  SUM(interview_stage = 'scheduled') AS scheduled_cnt,
  SUM(interview_stage = 'interviewed') AS interviewed_cnt,
  SUM(interview_stage IN ('failed','no_show','withdrawn')) AS closed_cnt
FROM interview_forms
WHERE deleted_at IS NULL
  AND interview_date IS NOT NULL
  AND interview_date >= :start
  AND interview_date < :end
GROUP BY DATE(interview_date)
";
$stmt = $conn->prepare($sql);
$stmt->execute([':start' => $startStr, ':end' => $endStrExclusive]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dayMap = [];
foreach ($rows as $r) {
    $dayMap[$r['d']] = [
        'total' => (int)$r['total'],
        'scheduled' => (int)$r['scheduled_cnt'],
        'interviewed' => (int)$r['interviewed_cnt'],
        'closed' => (int)$r['closed_cnt'],
    ];
}

$prev = $firstDay->modify('-1 month');
$next = $firstDay->modify('+1 month');

function day_badge(int $count): array {
    // Tweak thresholds to your workflow
    if ($count >= 6)  return ['Busy', 'bg-danger'];
    if ($count >= 3)  return ['Normal', 'bg-warning text-dark'];
    if ($count >= 1)  return ['Light', 'bg-success'];
    return ['', ''];
}

require_once __DIR__ . '/header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Admin Calendar — <?= htmlspecialchars($firstDay->format('F Y')) ?></h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary"
         href="calender.php?year=<?= (int)$prev->format('Y') ?>&month=<?= (int)$prev->format('n') ?>">← Prev</a>
      <a class="btn btn-outline-secondary"
         href="calender.php?year=<?= (int)date('Y') ?>&month=<?= (int)date('n') ?>">Today</a>
      <a class="btn btn-outline-secondary"
         href="calender.php?year=<?= (int)$next->format('Y') ?>&month=<?= (int)$next->format('n') ?>">Next →</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered align-middle" style="table-layout: fixed;">
      <thead class="table-light">
        <tr>
          <th class="text-center">Mon</th>
          <th class="text-center">Tue</th>
          <th class="text-center">Wed</th>
          <th class="text-center">Thu</th>
          <th class="text-center">Fri</th>
          <th class="text-center">Sat</th>
          <th class="text-center">Sun</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $cursor = $startOfGrid;
      $today = (new DateTimeImmutable('today'))->format('Y-m-d');

      while ($cursor < $endOfGrid) {
          echo "<tr>\n";
          for ($i = 0; $i < 7; $i++) {
              $dateKey = $cursor->format('Y-m-d');
              $inMonth = ($cursor->format('n') == $month);
              $cnt = $dayMap[$dateKey]['total'] ?? 0;
              [$label, $badgeClass] = day_badge($cnt);

              $cellClasses = [];
              if (!$inMonth) $cellClasses[] = 'bg-light';
              if ($dateKey === $today) $cellClasses[] = 'border border-3 border-primary';

              $cellClassStr = implode(' ', $cellClasses);
              $link = "calender_day.php?date=" . urlencode($dateKey);

              echo '<td class="' . htmlspecialchars($cellClassStr) . '" style="height: 110px; vertical-align: top;">';
              echo '<div class="d-flex justify-content-between">';
              echo '<a href="' . htmlspecialchars($link) . '" class="text-decoration-none fw-semibold">'
                   . (int)$cursor->format('j') . '</a>';
              if ($cnt > 0) {
                  echo '<span class="badge ' . htmlspecialchars($badgeClass) . '">' . htmlspecialchars($label) . '</span>';
              }
              echo '</div>';

              if ($cnt > 0) {
                  $scheduled = $dayMap[$dateKey]['scheduled'] ?? 0;
                  $interviewed = $dayMap[$dateKey]['interviewed'] ?? 0;
                  $closed = $dayMap[$dateKey]['closed'] ?? 0;

                  echo '<div class="mt-2 small text-muted">';
                  echo 'Total: <b>' . (int)$cnt . '</b><br>';
                  echo 'Scheduled: ' . (int)$scheduled . '<br>';
                  echo 'Interviewed: ' . (int)$interviewed . '<br>';
                  echo 'Closed: ' . (int)$closed;
                  echo '</div>';
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

  <div class="mt-3 small text-muted">
    Thresholds: Light(1–2), Normal(3–5), Busy(6+). You can change them in <code>day_badge()</code>.
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
