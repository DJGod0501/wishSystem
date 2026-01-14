<?php
declare(strict_types=1);

// calendar.php (Admin)
// UX rules:
// - Remove big "Unscheduled (latest 20)" table (too complex)
// - Show only a small top reminder: "Unscheduled count"
// - Badges (F2F/Online) only when that date has interviews
// - View button ALWAYS shown (so admin can click any date)

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

// Admin guard
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ====== Month handling ======
$tz = new DateTimeZone('Asia/Kuala_Lumpur');
$today = new DateTime('now', $tz);

$monthParam = isset($_GET['month']) ? trim((string)$_GET['month']) : '';
if ($monthParam !== '' && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $currentMonth = DateTime::createFromFormat('Y-m-d', $monthParam . '-01', $tz);
    if ($currentMonth === false) {
        $currentMonth = new DateTime($today->format('Y-m-01'), $tz);
    }
} else {
    $currentMonth = new DateTime($today->format('Y-m-01'), $tz);
}

// Range: [monthStart, nextMonthStart)
$monthStart = (clone $currentMonth)->setTime(0, 0, 0);
$nextMonthStart = (clone $monthStart)->modify('+1 month');
$monthEndExclusive = (clone $nextMonthStart);

// Calendar grid start (Monday-based)
$firstDayOfMonth = (clone $monthStart);
$firstWeekday = (int)$firstDayOfMonth->format('N'); // 1=Mon..7=Sun
$gridStart = (clone $firstDayOfMonth)->modify('-' . ($firstWeekday - 1) . ' days'); // back to Monday

// Calendar grid end (6 weeks max)
$gridEnd = (clone $gridStart)->modify('+41 days'); // 42 cells total

// Prev/Next month links
$prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');
$thisMonth = $today->format('Y-m');

// ====== Unscheduled count (both times NULL) ======
$unscheduledCount = 0;
try {
    $unscheduledCount = (int)$conn->query("
        SELECT COUNT(*)
        FROM interview_forms
        WHERE interview_in_person_at IS NULL
          AND interview_online_at IS NULL
    ")->fetchColumn();
} catch (Throwable $e) {
    // keep silent for UI; if DB has issues, other parts will show anyway
}

// ====== Fetch counts per day (in-person & online) ======
$countsByDate = []; // 'YYYY-MM-DD' => ['in' => int, 'on' => int]

$sql = "
SELECT day_key, SUM(in_cnt) AS in_person_cnt, SUM(on_cnt) AS online_cnt
FROM (
    SELECT DATE(interview_in_person_at) AS day_key, COUNT(*) AS in_cnt, 0 AS on_cnt
    FROM interview_forms
    WHERE interview_in_person_at IS NOT NULL
      AND interview_in_person_at >= :start1
      AND interview_in_person_at <  :end1
    GROUP BY DATE(interview_in_person_at)

    UNION ALL

    SELECT DATE(interview_online_at) AS day_key, 0 AS in_cnt, COUNT(*) AS on_cnt
    FROM interview_forms
    WHERE interview_online_at IS NOT NULL
      AND interview_online_at >= :start2
      AND interview_online_at <  :end2
    GROUP BY DATE(interview_online_at)
) t
GROUP BY day_key
";

$stmt = $conn->prepare($sql);
$params = [
    ':start1' => $monthStart->format('Y-m-d H:i:s'),
    ':end1'   => $monthEndExclusive->format('Y-m-d H:i:s'),
    ':start2' => $monthStart->format('Y-m-d H:i:s'),
    ':end2'   => $monthEndExclusive->format('Y-m-d H:i:s'),
];
$stmt->execute($params);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dayKey = (string)$row['day_key']; // YYYY-MM-DD
    $countsByDate[$dayKey] = [
        'in' => (int)$row['in_person_cnt'],
        'on' => (int)$row['online_cnt'],
    ];
}

$pageTitle = "Calendar";
require_once __DIR__ . '/header.php';
?>

<div class="container my-4">

  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h2 class="mb-0"><?= h($monthStart->format('F Y')) ?></h2>
      <div class="text-muted small">Admin Calendar</div>
    </div>

    <div class="btn-group" role="group" aria-label="Calendar navigation">
      <a class="btn btn-outline-secondary" href="calendar.php?month=<?= urlencode($prevMonth) ?>">← Prev</a>
      <a class="btn btn-outline-secondary" href="calendar.php?month=<?= urlencode($thisMonth) ?>">Today</a>
      <a class="btn btn-outline-secondary" href="calendar.php?month=<?= urlencode($nextMonth) ?>">Next →</a>
    </div>
  </div>

  <?php if ($unscheduledCount > 0): ?>
    <div class="alert alert-light border d-flex justify-content-between align-items-center">
      <div class="text-muted">
        Unscheduled interviews: <b><?= (int)$unscheduledCount ?></b>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="form.php">Go to All Forms</a>
    </div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-bordered align-middle text-center" style="min-width: 900px;">
      <thead class="table-light">
        <tr>
          <th style="width:14.28%;">Mon</th>
          <th style="width:14.28%;">Tue</th>
          <th style="width:14.28%;">Wed</th>
          <th style="width:14.28%;">Thu</th>
          <th style="width:14.28%;">Fri</th>
          <th style="width:14.28%;">Sat</th>
          <th style="width:14.28%;">Sun</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $cursor = clone $gridStart;

        for ($week = 0; $week < 6; $week++) {
            echo "<tr>";

            for ($d = 0; $d < 7; $d++) {
                $dateKey = $cursor->format('Y-m-d');
                $isCurrentMonth = ($cursor->format('Y-m') === $monthStart->format('Y-m'));
                $isToday = ($dateKey === $today->format('Y-m-d'));

                $inCnt = $countsByDate[$dateKey]['in'] ?? 0;
                $onCnt = $countsByDate[$dateKey]['on'] ?? 0;
                $total = $inCnt + $onCnt;

                $cellClasses = [];
                if (!$isCurrentMonth) $cellClasses[] = 'table-light text-muted';
                if ($isToday) $cellClasses[] = 'border border-2 border-primary';
                $cellClassStr = trim(implode(' ', $cellClasses));

                echo '<td class="' . h($cellClassStr) . '" style="height:130px;">';

                // Date number (always shown)
                echo '<div class="fw-semibold mb-2" style="font-size:18px;">' . (int)$cursor->format('j') . '</div>';

                // Badges only when interviews exist
                if ($total > 0) {
                    echo '<div class="d-flex justify-content-center gap-2 flex-wrap mb-2">';
                    if ($inCnt > 0) {
                        echo '<span class="badge bg-primary">F2F ' . (int)$inCnt . '</span>';
                    }
                    if ($onCnt > 0) {
                        echo '<span class="badge bg-info text-dark">Online ' . (int)$onCnt . '</span>';
                    }
                    echo '</div>';
                }

                // View ALWAYS available (so user can click any date)
                echo '<a class="btn btn-sm btn-outline-primary" href="calendar_day.php?date=' . urlencode($dateKey) . '">View</a>';

                echo "</td>";

                $cursor->modify('+1 day');
            }

            echo "</tr>";

            if ($cursor > $gridEnd) break;
        }
        ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
