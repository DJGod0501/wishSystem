<?php
// ===== FORCE ERROR DISPLAY (DEV ONLY) =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== LOAD CORE =====
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

// ===== FIX: map DB connection =====
/*
 * Your db.php does NOT define $pdo
 * It defines $conn (confirmed by error)
 * We normalize it here
 */
if (isset($conn) && !isset($pdo)) {
    $pdo = $conn;
}

if (!isset($pdo)) {
    die('Database connection not found');
}

// ===== AUTH CHECK =====
if (!isset($_SESSION['user_id'])) {
    die('No session');
}

$userId = $_SESSION['user_id'];

// ===== DATE =====
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

$start = sprintf('%04d-%02d-01', $year, $month);
$end   = (new DateTime($start))->modify('+1 month')->format('Y-m-d');

// ===== QUERY =====
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(interview_date, DATE(created_at)) AS day,
        interview_stage,
        COUNT(*) AS total
    FROM interview_forms
    WHERE user_id = :uid
      AND COALESCE(interview_date, DATE(created_at)) >= :start
      AND COALESCE(interview_date, DATE(created_at)) < :end
    GROUP BY day, interview_stage
");
$stmt->execute([
    ':uid'   => $userId,
    ':start'=> $start,
    ':end'  => $end
]);

$data = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $data[$row['day']][] = $row;
}

// ===== CALENDAR META =====
$first = new DateTime($start);
$daysInMonth  = (int)$first->format('t');
$startWeekday = (int)$first->format('N');

// ===== HEADER =====
include 'header.php';
?>

<h4>My Interview Calendar</h4>

<table class="table table-bordered text-center">
<tr>
<th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
</tr>
<tr>

<?php
$cell = 1;

// empty cells
for ($i = 1; $i < $startWeekday; $i++, $cell++) {
    echo '<td></td>';
}

// days
for ($day = 1; $day <= $daysInMonth; $day++, $cell++) {

    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

    echo '<td valign="top">';
    echo "<strong>
            <a href='my_calander_day.php?day={$date}'>
                {$day}
            </a>
          </strong>";

    if (!empty($data[$date])) {
        foreach ($data[$date] as $r) {
            echo "<div class='calendar-cell stage-{$r['interview_stage']} mt-1'>
                    {$r['interview_stage']} ({$r['total']})
                  </div>";
        }
    }

    echo '</td>';

    if ($cell % 7 === 0) {
        echo '</tr><tr>';
    }
}
?>

</tr>
</table>

<?php include 'footer.php'; ?>
