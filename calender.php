<?php
require_once __DIR__ . "/auth_check.php";

if (($_SESSION["role"] ?? "") !== "admin") {
    die("Access denied");
}

$year  = (int)($_GET["year"] ?? date("Y"));
$month = (int)($_GET["month"] ?? date("m"));
if ($month < 1 || $month > 12) $month = (int)date("m");

$firstDay = strtotime("$year-$month-01");
$daysInMonth = (int)date("t", $firstDay);
$startWeekday = (int)date("N", $firstDay); // 1=Mon ... 7=Sun

// Fetch interview counts per day for this month
$stmt = $conn->prepare("
  SELECT DATE(created_at) AS d, COUNT(*) AS total
  FROM interview_forms
  WHERE YEAR(created_at)=:y AND MONTH(created_at)=:m
  GROUP BY DATE(created_at)
");
$stmt->execute(["y" => $year, "m" => $month]);
$counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$title = "Interview Calendar";
require_once __DIR__ . "/header.php";

function busyBadge(int $count): array {
    if ($count >= 6) return ["danger", "Busy"];
    if ($count >= 3) return ["warning", "Medium"];
    if ($count >= 1) return ["success", "Low"];
    return ["secondary", "None"];
}
?>

<h3 class="ws-page-title mb-3">
  Interview Calendar (<?= $year ?>-<?= str_pad((string)$month, 2, "0", STR_PAD_LEFT) ?>)
</h3>

<div class="d-flex gap-2 mb-3">
  <a class="btn btn-outline-secondary"
     href="?year=<?= ($month==1 ? $year-1 : $year) ?>&month=<?= ($month==1 ? 12 : $month-1) ?>">← Prev</a>

  <a class="btn btn-outline-secondary"
     href="?year=<?= ($month==12 ? $year+1 : $year) ?>&month=<?= ($month==12 ? 1 : $month+1) ?>">Next →</a>
</div>

<div class="card p-3">
  <table class="table table-bordered text-center mb-0">
    <tr class="table-dark">
      <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
    </tr>
    <tr>
      <?php
      $cell = 1;

      // Empty cells before day 1
      for ($i = 1; $i < $startWeekday; $i++) {
          echo "<td></td>";
          $cell++;
      }

      for ($day = 1; $day <= $daysInMonth; $day++, $cell++) {
          $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
          $count = (int)($counts[$date] ?? 0);
          [$color, $label] = busyBadge($count);

          echo "<td>";
          echo "<strong>$day</strong><br>";

          if ($count > 0) {
              // ✅ 跳到真实存在的文件：calander_day.php
              echo "<a class='badge bg-$color' href='calander_day.php?date=$date'>$count ($label)</a>";
          } else {
              echo "<span class='badge bg-secondary'>0</span>";
          }

          echo "</td>";

          if ($cell % 7 == 0 && $day != $daysInMonth) {
              echo "</tr><tr>";
          }
      }

      // Fill remaining cells
      while ($cell % 7 != 1) {
          echo "<td></td>";
          $cell++;
      }
      ?>
    </tr>
  </table>
</div>

<div class="mt-3 ws-muted">
  <b>Legend:</b>
  <span class="badge bg-success">Low (1–2)</span>
  <span class="badge bg-warning">Medium (3–5)</span>
  <span class="badge bg-danger">Busy (6+)</span>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
