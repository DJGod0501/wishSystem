<?php
require_once __DIR__ . "/auth_check.php";
if (($_SESSION["role"] ?? "") !== "admin") die("Access denied");

function isValidDate($s) {
    $d = DateTime::createFromFormat("Y-m-d", $s);
    return $d && $d->format("Y-m-d") === $s;
}

$download = (int)($_GET["download"] ?? 0);
$from = trim($_GET["from"] ?? "");
$to = trim($_GET["to"] ?? "");
$staff_id = (int)($_GET["staff_id"] ?? 0);

if ($download === 1) {
    $where = [];
    $params = [];

    if ($from !== "" && isValidDate($from)) {
        $where[] = "DATE(f.created_at) >= :from";
        $params["from"] = $from;
    }
    if ($to !== "" && isValidDate($to)) {
        $where[] = "DATE(f.created_at) <= :to";
        $params["to"] = $to;
    }
    if ($staff_id > 0) {
        $where[] = "f.user_id = :staff_id";
        $params["staff_id"] = $staff_id;
    }

    $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=wishsystem_interviews_filtered.csv');

    $out = fopen("php://output", "w");

    fputcsv($out, [
        "form_id","created_at",
        "staff_name","staff_email",
        "candidate_name","age","gender","phone","position","transportation","education","area","expected_salary","work_experience","start_date",
        "english_writing","english_speaking","malay_writing","malay_speaking","chinese_writing","chinese_speaking"
    ]);

    $sql = "
      SELECT f.form_id, f.created_at,
             u.name AS staff_name, u.email AS staff_email,
             f.name AS candidate_name, f.age, f.gender, f.phone, f.position, f.transportation, f.education, f.area, f.expected_salary, f.work_experience, f.start_date,
             ls.english_writing, ls.english_speaking, ls.malay_writing, ls.malay_speaking, ls.chinese_writing, ls.chinese_speaking
      FROM interview_forms f
      JOIN users u ON u.user_id = f.user_id
      LEFT JOIN language_skills ls ON ls.form_id = f.form_id
      $whereSql
      ORDER BY f.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}

// UI page
$title = "Export CSV";
require_once __DIR__ . "/header.php";

$staffList = $conn->query("
  SELECT user_id, name, email
  FROM users
  WHERE role='online_posting'
  ORDER BY name ASC
")->fetchAll();
?>

<h3 class="ws-page-title mb-3">Export Interview Data (CSV)</h3>

<div class="card p-4">
  <form method="get" class="row g-3">
    <input type="hidden" name="download" value="1">

    <div class="col-md-4">
      <label class="form-label">From Date</label>
      <input class="form-control" type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">To Date</label>
      <input class="form-control" type="date" name="to" value="<?= htmlspecialchars($to) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">Staff (Optional)</label>
      <select class="form-select" name="staff_id">
        <option value="0">All Staff</option>
        <?php foreach ($staffList as $s): ?>
          <option value="<?= (int)$s["user_id"] ?>" <?= ($staff_id === (int)$s["user_id"]) ? "selected" : "" ?>>
            <?= htmlspecialchars($s["name"]) ?> (<?= htmlspecialchars($s["email"]) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <button class="btn btn-primary">Download CSV</button>
      <a class="btn btn-outline-secondary" href="export_csv.php">Reset</a>
    </div>

    <div class="col-12 ws-muted">
      Tip: Leave filters empty to export all data.
    </div>
  </form>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
