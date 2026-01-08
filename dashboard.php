<?php
require_once __DIR__ . "/auth_check.php";

/* 只允许 admin */
if (($_SESSION["role"] ?? "") !== "admin") {
    die("Access denied");
}

$title = "Dashboard";
require_once __DIR__ . "/header.php";

/* ===============================
   KPI 统计
   =============================== */
$totalForms = $conn->query(
    "SELECT COUNT(*) FROM interview_forms"
)->fetchColumn();

$todayForms = $conn->query(
    "SELECT COUNT(*) FROM interview_forms 
     WHERE DATE(created_at) = CURDATE()"
)->fetchColumn();

$monthForms = $conn->query(
    "SELECT COUNT(*) FROM interview_forms
     WHERE MONTH(created_at)=MONTH(CURDATE())
     AND YEAR(created_at)=YEAR(CURDATE())"
)->fetchColumn();

$inactiveUsers = $conn->query(
    "SELECT COUNT(*) FROM users
     WHERE role='online_posting'
     AND status='inactive'"
)->fetchColumn();

/* ===============================
   最近 7 天 Interview 数据
   =============================== */
$labels = [];
$data = [];

$stmt = $conn->query("
    SELECT DATE(created_at) AS d, COUNT(*) AS total
    FROM interview_forms
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY DATE(created_at)
");

$rows = $stmt->fetchAll();

/* 初始化最近 7 天（没数据也显示 0） */
for ($i = 6; $i >= 0; $i--) {
    $date = date("Y-m-d", strtotime("-$i days"));
    $labels[$date] = $date;
    $data[$date] = 0;
}

/* 把真实数据塞进去 */
foreach ($rows as $r) {
    $data[$r["d"]] = (int)$r["total"];
}

/* 转成 JS 可用 */
$chartLabels = json_encode(array_values($labels));
$chartData   = json_encode(array_values($data));
?>

<h3 class="ws-page-title mb-4">Admin Dashboard</h3>

<!-- ===============================
     KPI 卡片
     =============================== -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="ws-kpi p-3">
      <div class="ws-kpi-label">Total Interviews</div>
      <div class="ws-kpi-value"><?= $totalForms ?></div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="ws-kpi p-3">
      <div class="ws-kpi-label">Today</div>
      <div class="ws-kpi-value"><?= $todayForms ?></div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="ws-kpi p-3">
      <div class="ws-kpi-label">This Month</div>
      <div class="ws-kpi-value"><?= $monthForms ?></div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="ws-kpi p-3">
      <div class="ws-kpi-label">Inactive Staff</div>
      <div class="ws-kpi-value"><?= $inactiveUsers ?></div>
    </div>
  </div>
</div>

<!-- ===============================
     柱状图（最近 7 天）
     =============================== -->
<div class="card p-4">
  <h5 class="mb-3">Interview Submissions (Last 7 Days)</h5>
  <canvas id="interviewChart" height="100"></canvas>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('interviewChart');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= $chartLabels ?>,
        datasets: [{
            label: 'Number of Interviews',
            data: <?= $chartData ?>,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
