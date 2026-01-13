<?php
// calendar.php (Admin)
// Simplified UI version:
// - Remove "Unscheduled (latest 20)" block
// - Hide "Total 0 / None"
// - Only show badges + View button when that date has interviews

declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';

// ====== AUTH / ROLE CHECK (adjust if your project uses helper functions) ======
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// ====== Month handling ======
$tz = new DateTimeZone('Asia/Kuala_Lumpur'); // adjust if needed
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

// ====== Fetch counts per day (in-person & online) ======
// We count interview_in_person_at and interview_online_at separately.
// Each interview time counts once for that date.
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

// ====== Render ======
$pageTitle = "Calendar";
require_once __DIR__ . '/header.php';
?>

<div class="container my-4">

  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h2 class="mb-0"><?php echo htmlspecialchars($monthStart->format('F Y')); ?></h2>
      <div class="text-muted small">Admin Calendar</div>
    </div>

    <div class="btn-group" role="group" aria-label="Calendar navigation">
      <a class="btn btn-outline-secondary" href="calendar.php?month=<?php echo urlencode($prevMonth); ?>">← Prev</a>
      <a class="btn btn-outline-secondary" href="calendar.php?month=<?php echo urlencode($thisMonth); ?>">Today</a>
      <a class="btn btn-outline-secondary" href="calendar.php?month=<?php echo urlencode($nextMonth); ?>">Next →</a>
    </div>
  </div>

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
                $cellClasses[] = $isCurrentMonth ? '' : 'table-light text-muted';
                if ($isToday) $cellClasses[] = 'border border-2 border-primary';
                $cellClassStr = trim(implode(' ', array_filter($cellClasses)));

                echo '<td class="' . htmlspecialchars($cellClassStr) . '" style="height:130px;">';

                // Date number (always shown)
                echo '<div class="fw-semibold mb-2" style="font-size:18px;">' . (int)$cursor->format('j') . '</div>';

                // Only show badges + View if there are interviews on that day
                if ($total > 0) {
                    echo '<div class="d-flex justify-content-center gap-2 flex-wrap mb-2">';
                    if ($inCnt > 0) {
                        echo '<span class="badge bg-primary">F2F ' . $inCnt . '</span>';
                    }
                    if ($onCnt > 0) {
                        echo '<span class="badge bg-info text-dark">Online ' . $onCnt . '</span>';
                    }
                    echo '</div>';

                    echo '<a class="btn btn-sm btn-outline-primary" href="calendar_day.php?date=' . urlencode($dateKey) . '">View</a>';
                }

                echo "</td>";

                $cursor->modify('+1 day');
            }
            echo "</tr>";

            // Stop early if next row is completely after month end (optional)
            if ($cursor > $gridEnd) {
                break;
            }
        }
        ?>
      </tbody>
    </table>
  </div>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
