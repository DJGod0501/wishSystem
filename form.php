<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  exit('Forbidden');
}
if (!isset($conn) || !($conn instanceof PDO)) {
  http_response_code(500);
  exit('DB connection $conn not found');
}

if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));

$q = trim($_GET['q'] ?? '');
$params = [];
$where = "1=1";
if ($q !== '') {
  $where .= " AND (name LIKE :q OR phone LIKE :q OR position LIKE :q)";
  $params[':q'] = "%$q%";
}

$stmt = $conn->prepare("
  SELECT form_id, name, phone, position, interview_stage, interview_date, created_at
  FROM interview_forms
  WHERE $where
  ORDER BY created_at DESC
  LIMIT 500
");
$stmt->execute($params);
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

function fmt_interview(?string $raw): string {
  if (!$raw) return '-';
  try {
    $dt = new DateTimeImmutable($raw);
    return $dt->format('Y-m-d H:i') . ' (' . $dt->format('D') . ')';
  } catch(Throwable $e) {
    return $raw;
  }
}

require_once __DIR__ . '/header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">All Forms</h3>
    <a class="btn btn-outline-secondary" href="calendar.php">Calendar</a>
  </div>

  <form class="row g-2 mb-3" method="get" action="form.php">
    <div class="col-md-8">
      <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by name / phone / position">
    </div>
    <div class="col-md-4 d-flex gap-2">
      <button class="btn btn-primary" type="submit">Search</button>
      <a class="btn btn-outline-secondary" href="form.php">Reset</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:70px;">ID</th>
          <th>Candidate</th>
          <th style="width:140px;">Phone</th>
          <th style="width:180px;">Position</th>
          <th style="width:140px;">Stage</th>
          <th style="width:240px;">Interview</th>
          <th style="width:190px;">Created</th>
          <th class="text-end" style="width:340px;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($forms as $f): ?>
        <?php
          $rawDT = $f['interview_date'] ?? '';
          $dtVal = '';
          if ($rawDT) {
            try {
              $dt = new DateTimeImmutable($rawDT);
              $dtVal = $dt->format('Y-m-d\TH:i');
            } catch(Throwable $e) {}
          }
        ?>
        <tr>
          <td><?= (int)$f['form_id'] ?></td>
          <td>
            <a class="text-decoration-none" href="form_detail.php?form_id=<?= (int)$f['form_id'] ?>">
              <?= htmlspecialchars($f['name'] ?? '') ?>
            </a>
          </td>
          <td><?= htmlspecialchars($f['phone'] ?? '') ?></td>
          <td><?= htmlspecialchars($f['position'] ?? '') ?></td>
          <td><span class="badge bg-secondary"><?= htmlspecialchars($f['interview_stage'] ?? '') ?></span></td>
          <td><?= htmlspecialchars(fmt_interview($rawDT ?: null)) ?></td>
          <td><?= htmlspecialchars($f['created_at'] ?? '') ?></td>

          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="form_detail.php?form_id=<?= (int)$f['form_id'] ?>">Detail</a>

            <!-- ✅ Schedule/Edit Time -->
            <button
              class="btn btn-sm btn-primary"
              data-bs-toggle="modal"
              data-bs-target="#scheduleModal"
              data-form-id="<?= (int)$f['form_id'] ?>"
              data-name="<?= htmlspecialchars($f['name'] ?? '', ENT_QUOTES) ?>"
              data-dt="<?= htmlspecialchars($rawDT ?? '', ENT_QUOTES) ?>"
            >
              <?= $rawDT ? 'Edit Time' : 'Schedule' ?>
            </button>

            <!-- Delete -->
            <button
              class="btn btn-sm btn-danger"
              data-bs-toggle="modal"
              data-bs-target="#deleteModal"
              data-form-id="<?= (int)$f['form_id'] ?>"
              data-name="<?= htmlspecialchars($f['name'] ?? '', ENT_QUOTES) ?>"
            >
              Delete
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="small text-muted mt-2">Showing up to 500 latest results.</div>
</div>

<!-- Schedule modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="update_interview.php">
      <div class="modal-header">
        <h5 class="modal-title">Schedule / Edit Interview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf']) ?>">
        <input type="hidden" name="form_id" id="s_form_id" value="">
        <div class="mb-2 text-muted">Candidate: <b id="s_name"></b></div>

        <label class="form-label">Interview date & time</label>
        <input type="datetime-local" class="form-control" name="interview_datetime" id="s_dt" required>

        <div class="form-text mt-2">
          保存后会自动把 Stage 设为 <b>scheduled</b>，并写入 audit log。
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="delete_form.php">
      <div class="modal-header">
        <h5 class="modal-title">Delete Form</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['_csrf']) ?>">
        <input type="hidden" name="form_id" id="d_form_id" value="">
        <div class="alert alert-warning mb-0">
          Confirm delete: <b id="d_name"></b>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const scheduleModal = document.getElementById('scheduleModal');
  scheduleModal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    const id = btn.getAttribute('data-form-id');
    const name = btn.getAttribute('data-name') || '';
    const dt = btn.getAttribute('data-dt') || '';

    document.getElementById('s_form_id').value = id;
    document.getElementById('s_name').textContent = name;

    let val = '';
    if (dt) val = dt.replace(' ', 'T').slice(0, 16);
    document.getElementById('s_dt').value = val;
  });

  const deleteModal = document.getElementById('deleteModal');
  deleteModal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    document.getElementById('d_form_id').value = btn.getAttribute('data-form-id');
    document.getElementById('d_name').textContent = btn.getAttribute('data-name') || '';
  });
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
