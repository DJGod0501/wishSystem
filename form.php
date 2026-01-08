<?php
require_once __DIR__ . "/auth_check.php";
if (($_SESSION["role"] ?? "") !== "admin") die("Access denied");

$title = "All Interview Forms";
require_once __DIR__ . "/header.php";

$q = trim($_GET["q"] ?? "");
$from = trim($_GET["from"] ?? "");
$to = trim($_GET["to"] ?? "");

$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

function isValidDate($s) {
    $d = DateTime::createFromFormat("Y-m-d", $s);
    return $d && $d->format("Y-m-d") === $s;
}

$where = [];
$params = [];

// Search filter
if ($q !== "") {
    $where[] = "(f.name LIKE :q OR f.phone LIKE :q OR f.position LIKE :q OR u.name LIKE :q OR u.email LIKE :q)";
    $params["q"] = "%$q%";
}

// Date range filter (created_at)
if ($from !== "" && isValidDate($from)) {
    $where[] = "DATE(f.created_at) >= :from";
    $params["from"] = $from;
}
if ($to !== "" && isValidDate($to)) {
    $where[] = "DATE(f.created_at) <= :to";
    $params["to"] = $to;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Count total
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM interview_forms f
    JOIN users u ON u.user_id = f.user_id
    $whereSql
");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Fetch rows
$stmt = $conn->prepare("
    SELECT f.form_id, f.name, f.phone, f.position, f.created_at,
           u.name AS staff_name, u.email AS staff_email
    FROM interview_forms f
    JOIN users u ON u.user_id = f.user_id
    $whereSql
    ORDER BY f.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Helper for pagination links
function buildQuery($overrides = []) {
    $all = array_merge($_GET, $overrides);
    return http_build_query($all);
}
?>

<h3 class="ws-page-title mb-3">All Interview Forms</h3>

<div class="card p-3 mb-3">
  <form class="row g-2 align-items-end" method="get">
    <div class="col-md-5">
      <label class="form-label">Search</label>
      <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Name / Phone / Position / Staff">
    </div>
    <div class="col-md-3">
      <label class="form-label">From</label>
      <input class="form-control" type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">To</label>
      <input class="form-control" type="date" name="to" value="<?= htmlspecialchars($to) ?>">
    </div>
    <div class="col-md-1 d-grid">
      <button class="btn btn-primary">Go</button>
    </div>

    <div class="col-12">
      <a class="btn btn-outline-secondary btn-sm" href="form.php">Reset</a>
      <span class="ws-muted ms-2">Results: <?= $total ?></span>
    </div>
  </form>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>ID</th>
          <th>Candidate</th>
          <th>Phone</th>
          <th>Position</th>
          <th>Staff</th>
          <th>Date</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="text-muted">No records found.</td></tr>
        <?php endif; ?>

        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r["form_id"] ?></td>
            <td><?= htmlspecialchars($r["name"]) ?></td>
            <td><?= htmlspecialchars($r["phone"]) ?></td>
            <td><?= htmlspecialchars($r["position"]) ?></td>
            <td>
              <?= htmlspecialchars($r["staff_name"]) ?><br>
              <span class="ws-muted"><?= htmlspecialchars($r["staff_email"]) ?></span>
            </td>
            <td><?= htmlspecialchars($r["created_at"]) ?></td>
            <td>
              <a class="btn btn-sm btn-primary" href="form_detail.php?id=<?= (int)$r["form_id"] ?>">View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<nav class="mt-3">
  <ul class="pagination">
    <li class="page-item <?= ($page <= 1) ? "disabled" : "" ?>">
      <a class="page-link" href="?<?= buildQuery(["page" => $page - 1]) ?>">Prev</a>
    </li>

    <?php
      $start = max(1, $page - 2);
      $end = min($totalPages, $page + 2);
      for ($p = $start; $p <= $end; $p++):
    ?>
      <li class="page-item <?= ($p === $page) ? "active" : "" ?>">
        <a class="page-link" href="?<?= buildQuery(["page" => $p]) ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>

    <li class="page-item <?= ($page >= $totalPages) ? "disabled" : "" ?>">
      <a class="page-link" href="?<?= buildQuery(["page" => $page + 1]) ?>">Next</a>
    </li>
  </ul>
</nav>

<?php require_once __DIR__ . "/footer.php"; ?>
