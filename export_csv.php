<?php
require_once __DIR__ . "/auth_check.php";
if (($_SESSION["role"] ?? "") !== "admin") die("Access denied");

if (session_status() === PHP_SESSION_NONE) session_start();

// CSRF token for download (GET)
function csrf_token(): string {
    if (empty($_SESSION["csrf_token"])) $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    return $_SESSION["csrf_token"];
}
function csrf_check_get(): bool {
    $t = $_GET["token"] ?? "";
    return $t && hash_equals($_SESSION["csrf_token"] ?? "", $t);
}

function isValidDate($s) {
    $d = DateTime::createFromFormat("Y-m-d", $s);
    return $d && $d->format("Y-m-d") === $s;
}

// Prevent CSV injection (Excel formula)
function safe_csv($value): string {
    $v = (string)$value;
    if (preg_match('/^[=\+\-@]/', $v)) return "'".$v;
    return $v;
}

$download = (int)($_GET["download"] ?? 0);
$from = trim($_GET["from"] ?? "");
$to = trim($_GET["to"] ?? "");
$staff_id = (int)($_GET["staff_id"] ?? 0);

if ($download === 1) {
    if (!csrf_check_get()) {
        http_response_code(403);
        exit("Invalid token.");
    }

    $where = [];
    $params = [];

    if ($from !== "" && isValidDate($from)) {
        $where[] = "DATE(f.created_at) >= :from";
        $params[":from"] = $from;
    }
    if ($to !== "" && isValidDate($to)) {
        $where[] = "DATE(f.created_at) <= :to";
        $params[":to"] = $to;
    }
    if ($staff_id > 0) {
        $where[] = "f.user_id = :staff";
        $params[":staff"] = $staff_id;
    }

    $sql = "
    SELECT
      f.form_id,
      u.name AS staff_name,
      u.email AS staff_email,
      f.name, f.age, f.gender, f.phone, f.position, f.transportation, f.education, f.area,
      f.expected_salary, f.work_experience, f.start_date, f.created_at,
      f.interview_stage, f.interview_date,
      ls.english_writing, ls.english_speaking,
      ls.malay_writing, ls.malay_speaking,
      ls.chinese_writing, ls.chinese_speaking
    FROM interview_forms f
    JOIN users u ON u.user_id = f.user_id
    LEFT JOIN language_skills ls ON ls.form_id = f.form_id
    ";

    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY f.created_at DESC";

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=wishsystem_export_" . date("Ymd_His") . ".csv");
    header("Pragma: no-cache");
    header("Expires: 0");

    $out = fopen("php://output", "w");

    $headers = [
      "form_id","staff_name","staff_email",
      "candidate_name","age","gender","phone","position","transportation","education","area",
      "expected_salary","work_experience","start_date","submitted_at",
      "interview_stage","interview_date",
      "english_writing","english_speaking",
      "malay_writing","malay_speaking",
      "chinese_writing","chinese_speaking"
    ];
    fputcsv($out, $headers);

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row = [
          $r["form_id"],
          safe_csv($r["staff_name"]),
          safe_csv($r["staff_email"]),
          safe_csv($r["name"]),
          $r["age"],
          safe_csv($r["gender"]),
          safe_csv($r["phone"]),
          safe_csv($r["position"]),
          safe_csv($r["transportation"]),
          safe_csv($r["education"]),
          safe_csv($r["area"]),
          safe_csv($r["expected_salary"]),
          safe_csv($r["work_experience"]),
          safe_csv($r["start_date"]),
          $r["created_at"],
          safe_csv($r["interview_stage"] ?? "new"),
          $r["interview_date"],
          $r["english_writing"], $r["english_speaking"],
          $r["malay_writing"], $r["malay_speaking"],
          $r["chinese_writing"], $r["chinese_speaking"],
        ];
        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}

// UI page
$title = "Export CSV";
require_once __DIR__ . "/header.php";

// staff list
$staff = $conn->query("SELECT user_id, name FROM users WHERE role='online_posting' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// token for download link
$token = csrf_token();
?>

<h3 class="ws-page-title">Export Interview Data (CSV)</h3>

<div class="card">
  <div class="card-body">
    <form method="get" class="row g-3">
      <div class="col-md-4">
        <label class="form-label">From (submitted date)</label>
        <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">To (submitted date)</label>
        <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Staff (optional)</label>
        <select class="form-select" name="staff_id">
          <option value="0">All Staff</option>
          <?php foreach ($staff as $s): ?>
            <option value="<?= (int)$s["user_id"] ?>" <?= ($staff_id == (int)$s["user_id"]) ? "selected" : "" ?>>
              <?= htmlspecialchars($s["name"]) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 d-flex gap-2 flex-wrap">
        <button class="btn btn-primary" name="download" value="1">Download CSV</button>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
