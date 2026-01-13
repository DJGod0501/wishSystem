<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    exit('DB connection $conn not found');
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function loadLabel(int $n): array {
    if ($n === 0) return ['None', 'secondary'];
    if ($n <= 2)  return ['Light', 'success'];
    if ($n <= 4)  return ['Normal', 'warning'];
    return ['Busy', 'danger'];
}

// Month
$ym = $_GET['ym'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');

$year  = (int)substr($ym, 0, 4);
$month = (int)substr($ym, 5, 2);
if ($month < 1 || $month > 12) {
    $year = (int)date('Y');
    $month = (int)date('m');
}
$firstDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int)$firstDay->format('t');
$startWeekday = (int)$firstDay->format('N'); // 1=Mon

$rangeStart = $firstDay->format('Y-m-01 00:00:00');
$rangeEnd   = $firstDay->modify('+1 month')->format('Y-m-01 00:00:00');

// ✅ Unscheduled list (no in-person & no online time)
$unscheduledStmt = $conn->prepare("
  SELECT form_id, name, phone, position, interview_stage, created_at
  FROM interview_forms
  WHERE (interview_in_person_at IS NULL AND interview_online_at IS NULL)
    AND interview_stage IN ('new','scheduled')
  ORDER BY created_at DESC, form_id DESC
  LIMIT 20
");
$unscheduledStmt->execute();
$unscheduled = $unscheduledStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Monthly counts (fix HY093 by distinct param names)
$sql = "
  SELECT d,
         SUM(in_person_cnt) AS in_person_cnt,
         SUM(online_cnt)    AS online_cnt
  FROM (
    SELECT DATE(interview_in_person_at) AS d,
           COUNT(*) AS in_person_cnt,
           0 AS online_cnt
    FROM interview_forms
    WHERE interview_in_person_at IS NOT NULL
      AND interview_in_person_at >= :s1 AND interview_in_person_at < :e1
    GROUP BY DATE(interview_in_person_at)

    UNION ALL

    SELECT DATE(interview_online_at) AS d,
           0 AS in_person_cnt,
           COUNT(*) AS online_cnt
    FROM interview_forms
    WHERE interview_online_at IS NOT NULL
      AND interview_online_at >= :s2 AND interview_online_at < :e2
    GROUP BY DATE(interview_online_at)
  ) x
  GROUP BY d
";
$stmt = $conn->prepare($sql);
$stmt->execute([
    ':s1' => $rangeStart, ':e1' => $rangeEnd,
    ':s2' => $rangeStart, ':e2' => $rangeEnd,
]);

$counts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $d = (string)$row['d'];
    $inPerson = (int)($row['in_person_cnt'] ?? 0);
    $online   = (int)($row['online_cnt'] ?? 0);
    $counts[$d] = [
        'in_person' => $inPerson,
        'online' => $online,
        'total' => $inPerson + $online,
    ];
}

$prevYm = $firstDay->modify('-1 month')->format('Y-m');
$nextYm = $firstDay->modify('+1 month')->format('Y-m');

$pageTitle = 'Admin Calendar';
require_once __DIR__ . '/header.php';
?>

<div class="container my-4">

  <?php if ($unscheduled): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center">
      <div>
        <div class="fw-semibold">Unscheduled interviews (need scheduling)</div>
        <div class="small">These forms have no in-person/online time yet.</div>
      </div>
      <a class="btn btn-sm btn-outline-dark" href="/wishSystem/form.php">Go to All Forms</a>
    </div>

    <div class="card mb-3">
      <div class="card-header fw-semibold">
        Unscheduled (latest 20)
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:90px;">ID</th>
                <th>Candidate</th>
                <th style="width:140px;">Phone</th>
                <th style="width:180px;">Position</th>
                <th style="width:120px;">Stage</th>
                <th style="width:200px;">Created</th>
                <th class="text-end" style="width:160px;">Action</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($unscheduled as $u): ?>
              <tr>
                <td><?= (int)$u['form_id'] ?></td>
                <td><?= h((string)($u['name'] ?? '')) ?></td>
                <td><?= h((string)($u['phone'] ?? '')) ?></td>
                <td><?= h((string)($u['position'] ?? '')) ?></td>
                <td><span class="badge bg-secondary"><?= h((string)($u['interview_stage'] ?? '')) ?></span></td>
                <td><?= h((string)($u['created_at'] ?? '')) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="/wishSystem/form_detail.php?form_id=<?= (int)$u['form_id'] ?>">Detail</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

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
        for ($i = 1; $i < $startWeekday; $i++) echo '<td class="bg-light"></td>';

        $day = 1;
        $cell = $startWeekday;

        while ($day <= $daysInMonth) {
            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);

            $inPerson = $counts[$dateStr]['in_person'] ?? 0;
            $online   = $counts[$dateStr]['online'] ?? 0;
            $total    = $counts[$dateStr]['total'] ?? 0;

            [$label, $color] = loadLabel($total);

            echo '<td style="height:110px; vertical-align:top">';
            echo '<div class="fw-bold">' . (int)$day . '</div>';
            echo '<span class="badge text-bg-' . h($color) . '">Total ' . (int)$total . '</span><br>';
            echo '<small>' . h($label) . '</small><br>';
            echo '<div class="mt-1">';
            echo '<span class="badge text-bg-primary me-1">In ' . (int)$inPerson . '</span>';
            echo '<span class="badge text-bg-info">On ' . (int)$online . '</span>';
            echo '</div>';
            echo '<a class="btn btn-sm btn-outline-primary mt-2" href="/wishSystem/calendar_day.php?date=' . h($dateStr) . '">View</a>';
            echo '</td>';

            $day++; $cell++;
            if ($cell > 7 && $day <= $daysInMonth) { echo '</tr><tr>'; $cell = 1; }
        }

        if ($cell !== 1) for ($i = $cell; $i <= 7; $i++) echo '<td class="bg-light"></td>';
        ?>
      </tr>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
