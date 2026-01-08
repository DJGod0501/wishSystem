<?php
require_once __DIR__ . "/auth_check.php";

if (($_SESSION["role"] ?? "") !== "admin") {
    die("Access denied");
}

$date = $_GET["date"] ?? "";

// ✅ 严格验证 YYYY-MM-DD
$d = DateTime::createFromFormat("Y-m-d", $date);
if (!$d || $d->format("Y-m-d") !== $date) {
    die("Invalid date");
}

$stmt = $conn->prepare("
    SELECT f.form_id, f.name, f.position, f.created_at, u.name AS staff
    FROM interview_forms f
    JOIN users u ON f.user_id = u.user_id
    WHERE DATE(f.created_at) = :d
    ORDER BY f.created_at DESC
");
$stmt->execute(["d" => $date]);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = "Interviews on $date";
require_once __DIR__ . "/header.php";
?>

<h3 class="ws-page-title mb-3">Interviews on <?= htmlspecialchars($date) ?></h3>

<a class="btn btn-outline-secondary mb-3" href="calender.php">← Back</a>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Candidate</th>
          <th>Position</th>
          <th>Time</th>
          <th>Staff</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$list): ?>
          <tr><td colspan="5" class="text-muted">No interviews found on this date.</td></tr>
        <?php endif; ?>

        <?php foreach ($list as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r["name"]) ?></td>
            <td><?= htmlspecialchars($r["position"]) ?></td>
            <td><?= htmlspecialchars($r["created_at"]) ?></td>
            <td><?= htmlspecialchars($r["staff"]) ?></td>
            <td>
              <a class="btn btn-sm btn-primary" href="form_detail.php?id=<?= (int)$r["form_id"] ?>">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
