<?php
require_once __DIR__ . "/auth_check.php";
if (($_SESSION["role"] ?? "") !== "admin") die("Access denied");

$title = "Staff Performance";
require_once __DIR__ . "/header.php";

$rows = $conn->query("
  SELECT u.user_id, u.name, u.email, u.status, u.last_submission_date,
         COUNT(f.form_id) AS total_submissions,
         SUM(CASE WHEN MONTH(f.created_at)=MONTH(CURDATE()) AND YEAR(f.created_at)=YEAR(CURDATE()) THEN 1 ELSE 0 END) AS month_submissions
  FROM users u
  LEFT JOIN interview_forms f ON f.user_id = u.user_id
  WHERE u.role='online_posting'
  GROUP BY u.user_id
  ORDER BY month_submissions DESC, total_submissions DESC
")->fetchAll();
?>

<h3 class="ws-page-title mb-3">Staff Performance</h3>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Staff</th>
          <th>Status</th>
          <th>Last Submission</th>
          <th>This Month</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td>
            <?= htmlspecialchars($r["name"]) ?><br>
            <span class="ws-muted"><?= htmlspecialchars($r["email"]) ?></span>
          </td>
          <td>
            <span class="badge bg-<?= ($r["status"]==="active") ? "success" : "danger" ?>">
              <?= htmlspecialchars($r["status"]) ?>
            </span>
          </td>
          <td><?= htmlspecialchars($r["last_submission_date"] ?? "-") ?></td>
          <td><b><?= (int)$r["month_submissions"] ?></b></td>
          <td><?= (int)$r["total_submissions"] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
