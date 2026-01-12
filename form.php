<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

function require_admin_fallback(): void {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
}
require_admin_fallback();

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }
}

$q = trim($_GET['q'] ?? '');
$params = [];
$where = "WHERE deleted_at IS NULL";

if ($q !== '') {
    $where .= " AND (name LIKE :q OR phone LIKE :q OR position LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$sql = "
SELECT form_id, name, phone, position, interview_stage, interview_date, created_at, user_id
FROM interview_forms
{$where}
ORDER BY created_at DESC
LIMIT 500
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">All Forms</h3>
    <a href="calender.php" class="btn btn-outline-secondary">Calendar</a>
  </div>

  <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">Form deleted.</div>
  <?php endif; ?>

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
          <th>ID</th>
          <th>Candidate</th>
          <th>Phone</th>
          <th>Position</th>
          <th>Stage</th>
          <th>Interview</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($forms as $f): ?>
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
          <td><?= htmlspecialchars($f['interview_date'] ?? '') ?></td>
          <td><?= htmlspecialchars($f['created_at'] ?? '') ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary"
               href="form_detail.php?form_id=<?= (int)$f['form_id'] ?>">Detail</a>

            <button class="btn btn-sm btn-danger"
                    data-bs-toggle="modal"
                    data-bs-target="#deleteModal"
                    data-form-id="<?= (int)$f['form_id'] ?>"
                    data-name="<?= htmlspecialchars($f['name'] ?? '', ENT_QUOTES) ?>">
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

<!-- Delete modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="delete_form.php">
      <div class="modal-header">
        <h5 class="modal-title">Delete Form</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="form_id" id="d_form_id" value="">
        <p class="mb-2">You are about to delete:</p>
        <div class="alert alert-warning mb-3">
          <b id="d_candidate"></b>
        </div>
        <label class="form-label">Reason / Note (optional)</label>
        <input type="text" class="form-control" name="note" maxlength="255" placeholder="e.g. Candidate withdrew, duplicate, spam">
        <div class="form-text">This is a soft delete (can be restored later if needed).</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger" type="submit">Confirm delete</button>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
  const modal = document.getElementById('deleteModal');
  modal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    document.getElementById('d_form_id').value = btn.getAttribute('data-form-id');
    document.getElementById('d_candidate').textContent = btn.getAttribute('data-name') || '';
  });
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
