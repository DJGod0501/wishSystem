<?php
require_once 'auth_check.php';
require_once 'db.php';

// 仅 staff 可用（如果你 staff role 叫 online_posting）
if (($_SESSION['role'] ?? '') !== 'online_posting') {
    http_response_code(403);
    exit('Forbidden');
}

$userId = (int)($_SESSION['user_id'] ?? 0);

// 月份参数
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
if ($year < 2000 || $year > 2100) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');

$firstDay = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth = (int)date('t', strtotime($firstDay));
$firstWeekday = (int)date('N', strtotime($firstDay)); // 1=Mon ... 7=Sun

$prevTs = strtotime("$firstDay -1 month");
$nextTs = strtotime("$firstDay +1 month");

// 抓取本月：按 interview_date 统计数量 + stage breakdown
$sql = "
SELECT 
  interview_date,
  interview_stage,
  COUNT(*) AS cnt
FROM interview_forms
WHERE user_id = :uid
  AND interview_date IS NOT NULL
  AND interview_date >= :start
  AND interview_date <  :end
GROUP BY interview_date, interview_stage
";
$start = $firstDay;
$end = date('Y-m-d', strtotime("$firstDay +1 month"));

$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':uid' => $userId,
  ':start' => $start,
  ':end' => $end
]);

$map = []; // date => ['total'=>x, 'stages'=>['new'=>1...]]
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d = $row['interview_date'];
    $stage = $row['interview_stage'];
    $cnt = (int)$row['cnt'];

    if (!isset($map[$d])) $map[$d] = ['total' => 0, 'stages' => []];
    $map[$d]['total'] += $cnt;
    $map[$d]['stages'][$stage] = ($map[$d]['stages'][$stage] ?? 0) + $cnt;
}

function stage_badge_class(string $stage): string {
    return match($stage) {
        'new' => 'bg-secondary',
        'scheduled' => 'bg-primary',
        'interviewed' => 'bg-info',
        'passed' => 'bg-success',
        'failed' => 'bg-danger',
        'no_show' => 'bg-warning text-dark',
        'withdrawn' => 'bg-dark',
        default => 'bg-secondary',
    };
}

// 用于日历格子主色：按优先级挑一个 stage 代表当天“最重要状态”
function pick_main_stage(array $stages): string {
    $priority = ['failed','no_show','withdrawn','passed','interviewed','scheduled','new'];
    foreach ($priority as $s) {
        if (!empty($stages[$s])) return $s;
    }
    return 'new';
}

require_once 'header.php';
?>

<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <h4 class="mb-1">My Interview Calendar</h4>
      <div class="text-muted small">Shows your submitted candidates by interview date & stage.</div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary"
         href="my_calendar.php?y=<?= (int)date('Y',$prevTs) ?>&m=<?= (int)date('n',$prevTs) ?>">
        ◀ Prev
      </a>
      <a class="btn btn-outline-secondary" href="my_calendar.php">This Month</a>
      <a class="btn btn-outline-secondary"
         href="my_calendar.php?y=<?= (int)date('Y',$nextTs) ?>&m=<?= (int)date('n',$nextTs) ?>">
        Next ▶
      </a>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong><?= htmlspecialchars(date('F Y', strtotime($firstDay))) ?></strong>
      <div class="d-flex flex-wrap gap-2 small">
        <span class="badge bg-secondary">New</span>
        <span class="badge bg-primary">Scheduled</span>
        <span class="badge bg-info">Interviewed</span>
        <span class="badge bg-success">Passed</span>
        <span class="badge bg-danger">Failed</span>
        <span class="badge bg-warning text-dark">No-show</span>
        <span class="badge bg-dark">Withdrawn</span>
      </div>
    </div>

    <div class="card-body p-2">
      <div class="row g-2 text-center fw-semibold small mb-2">
        <div class="col">Mon</div><div class="col">Tue</div><div class="col">Wed</div><div class="col">Thu</div><div class="col">Fri</div><div class="col">Sat</div><div class="col">Sun</div>
      </div>

      <?php
      $day = 1;
      $cell = 1;

      // 总格子数：至少 6 行 * 7 列更稳定
      $totalCells = 42;

      for ($i=0; $i < $totalCells; $i++) {
          if ($i % 7 == 0) echo '<div class="row g-2 mb-2">';

          $content = '';
          $isCurrentMonth = false;

          if ($i >= ($firstWeekday - 1) && $day <= $daysInMonth) {
              $isCurrentMonth = true;
              $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);

              $info = $map[$dateStr] ?? null;
              $total = $info['total'] ?? 0;
              $stages = $info['stages'] ?? [];

              $mainStage = $total > 0 ? pick_main_stage($stages) : 'new';
              $badgeClass = $total > 0 ? stage_badge_class($mainStage) : 'bg-light text-dark';

              $content .= '<div class="d-flex justify-content-between align-items-start">';
              $content .= '<div class="fw-bold">'. $day .'</div>';

              if ($total > 0) {
                  $content .= '<span class="badge '.$badgeClass.'">'. $total .'</span>';
              } else {
                  $content .= '<span class="text-muted small"> </span>';
              }
              $content .= '</div>';

              if ($total > 0) {
                  // 小型 stage 分布
                  $mini = [];
                  foreach (['new','scheduled','interviewed','passed','failed','no_show','withdrawn'] as $s) {
                      if (!empty($stages[$s])) {
                          $mini[] = '<span class="badge '.stage_badge_class($s).' me-1 mb-1">'.(int)$stages[$s].'</span>';
                      }
                  }
                  $content .= '<div class="mt-2 small">'.implode('', $mini).'</div>';

                  // 点击去当天列表
                  $content = '<a class="text-decoration-none text-dark" href="my_calendar_day.php?date='.urlencode($dateStr).'">'.$content.'</a>';
              }
              $day++;
          }

          $cellClasses = 'col';
          $boxClasses = 'border rounded p-2 h-100';
          if (!$isCurrentMonth) {
              $boxClasses .= ' bg-light';
              $content = '&nbsp;';
          }

          echo '<div class="'.$cellClasses.'"><div class="'.$boxClasses.'" style="min-height:96px;">'.$content.'</div></div>';

          if ($i % 7 == 6) echo '</div>';
      }
      ?>
    </div>
  </div>

  <div class="text-muted small mt-3">
    Tip: Only forms with <code>interview_date</code> will appear here. Admin can set interview date/stage in the detail page (next enhancement).
  </div>
</div>

<?php require_once 'footer.php'; ?>
