<?php
require_once __DIR__ . "/auth_check.php";
if (($_SESSION["role"] ?? "") !== "admin") die("Access denied");

$title = "All Interview Forms";
require_once __DIR__ . "/header.php";

// helpers
function isValidDate($s) {
    $d = DateTime::createFromFormat("Y-m-d", $s);
    return $d && $d->format("Y-m-d") === $s;
}

$q = trim($_GET["q"] ?? "");
$from = trim($_GET["from"] ?? "");
$to = trim($_GET["to"] ?? "");
$page = (int)($_GET["page"] ?? 1);
if ($page < 1) $page = 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($q !== "") {
    $where[] = "(f.name LIKE :q OR f.phone LIKE :q OR f.position LIKE :q OR u.name LIKE :q)";
    $params[":q"] = "%".$q."%";
}
if ($from !== "" && isValidDate($from)) {
    $where[] = "DATE(f.created_at) >= :from";
    $params[":from"] = $from;
}
if ($to !== "" && isValidDate($to)) {
    $where[] = "DATE(f.created_at) <= :to";
    $params[":to"] = $to;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// total count
$stmt = $conn->prepare("
  SELECT COUNT(*) 
  FROM interview_forms f
  JOIN users u ON u.user_id = f.user_id
  $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// data
$sql = "
SELECT
  f.form_id, f.name, f.phone, f.position, f.area, f.expected_salary,
  f.created_at, f.interview_stage, f.interview_date,
  u.name AS staff_name
FROM interview_forms f
JOIN users u ON u.user_id = f.user_id
$whereSql
ORDER BY f.created_at DESC
LIMIT $perPage OFFSET $offset
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function badgeStage($stage) {
    return match($stage) {
        "new" => "bg-secondary",
        "scheduled" => "bg-primary",
        "interviewed" => "bg-info",
        "passed" => "bg-success",
        "failed" => "bg-danger",
        "no_show" => "bg-warning text-dark",
        "withdrawn" => "bg-dark",
        default => "bg-secondary",
    };
}
?>

<h3 class="ws-page-title">All Interview Forms</h3>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Search</label>
        <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Candidate/Phone/Position/Staff">
      </div>
      <div class="col-md-3">
        <label class="form-label">From</label>
        <input class="form-control" type="date" name="from" value="<?= htmlspecialchars($from) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">To</label>
        <input class="form-control" type="date" name="to" value="<?= htmlspecialchars($to) ?>">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-primary w-100">Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Candidate</th>
            <th>Position</th>
            <th>Area</th>
            <th>Salary</th>
            <th>Stage</th>
            <th>Interview Date</th>
            <th>Staff</th>
            <th>Submitted</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="9" class="text-center ws-muted py-4">No records found.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= htmlspecialchars($r["name"]) ?></div>
                  <div class="ws-muted small"><?= htmlspecialchars($r["phone"] ?? "") ?></div>
                </td>
                <td><?= htmlspecialchars($r["position"] ?? "") ?></td>
                <td><?= htmlspecialchars($r["area"] ?? "") ?></td>
                <td><?= htmlspecialchars($r["expected_salary"] ?? "") ?></td>
                <td>
                  <span class="badge <?= badgeStage($r["interview_stage"] ?? "new") ?>">
                    <?= htmlspecialchars($r["interview_stage"] ?? "new") ?>
                  </span>
                </td>
                <td><?= htmlspecialchars($r["interview_date"] ?? "") ?></td>
                <td><?= htmlspecialchars($r["staff_name"] ?? "") ?></td>
                <td class="ws-muted small"><?= htmlspecialchars($r["created_at"]) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-primary" href="form_detail.php?id=<?= (int)$r["form_id"] ?>">View</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <nav>
        <ul class="pagination justify-content-end mb-0">
          <?php
            $base = "form.php?q=".urlencode($q)."&from=".urlencode($from)."&to=".urlencode($to)."&page=";
          ?>
          <li class="page-item <?= ($page<=1) ? "disabled" : "" ?>">
            <a class="page-link" href="<?= $base.($page-1) ?>">Prev</a>
          </li>
          <li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $totalPages ?></span></li>
          <li class="page-item <?= ($page>=$totalPages) ? "disabled" : "" ?>">
            <a class="page-link" href="<?= $base.($page+1) ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
