<?php
// admin_day_manage.php (Admin Day Manager + Bulk Update)
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/header.php';

// ---- admin guard ----
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "<div class='container py-4'><div class='alert alert-danger'>Forbidden</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

// ---- DB handle compatibility: $conn or $pdo ----
$db = null;
if (isset($pdo) && $pdo instanceof PDO) $db = $pdo;
if (isset($conn) && $conn instanceof PDO) $db = $conn;

if (!$db) {
    echo "<div class='container py-4'><div class='alert alert-danger'>DB connection not found ($pdo/$conn)</div></div>";
    require_once __DIR__ . '/footer.php';
    exit;
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ---- date param ----
$date = isset($_GET['date']) ? trim((string)$_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

// ---- fetch interview list for that day (by interview_date) ----
$stmt = $db->prepare("
    SELECT f.form_id, f.name, f.phone, f.position, f.interview_stage, f.interview_date, f.created_at,
           u.name AS staff_name
    FROM interview_forms f
    LEFT JOIN users u ON u.user_id = f.user_id
    WHERE f.interview_date = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$date]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stages = [
  'new' => 'New',
  'scheduled' => 'Scheduled',
  'interviewed' => 'Interviewed',
  'passed' => 'Passed',
  'failed' => 'Failed',
  'no_show' => 'No Show',
  'withdrawn' => 'Withdrawn'
];
?>

<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h3 class="mb-1">Admin Day Manager</h3>
      <div class="text-muted">Manage candidates scheduled on a specific interview date.</div>
    </div>

    <form class="d-flex gap-2 align-items-center" method="get">
      <input type="date" name="date" class="form-control" value="<?=h($date)?>">
      <button class="btn btn-primary" type="submit">Go</button>
    </form>
  </div>

  <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div><strong><?=h($date)?></strong> — <?=count($rows)?> record(s)</div>
    <div class="small text-muted">
      Single: change fields → Save. &nbsp; Bulk: tick rows → Bulk Update.
    </div>
  </div>

  <!-- Bulk update panel -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <div class="fw-semibold">Bulk Update</div>
        <div class="small text-muted">
          Selected: <span id="selectedCount">0</span>
        </div>
      </div>

      <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Stage</label>
          <select class="form-select" id="bulkStage">
            <option value="">-- choose --</option>
            <?php foreach ($stages as $k => $label): ?>
              <option value="<?=h($k)?>"><?=h($label)?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Required for bulk update.</div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Interview Date (optional)</label>
          <input type="date" class="form-control" id="bulkDate">
          <div class="form-text">Leave empty to keep unchanged.</div>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label mb-1">Note (optional)</label>
          <input type="text" class="form-control" id="bulkNote" maxlength="255" placeholder="e.g. Bulk updated after interview session">
        </div>

        <div class="col-12 col-md-2 d-grid">
          <button type="button" class="btn btn-success" id="bulkApplyBtn">Apply</button>
        </div>
      </div>

      <div class="small mt-2" id="bulkMsg"></div>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="card">
      <div class="card-body text-muted">No interviews scheduled on this date.</div>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th style="width:40px;">
              <input class="form-check-input" type="checkbox" id="checkAll">
            </th>
            <th style="width:90px;">Form ID</th>
            <th>Candidate</th>
            <th>Phone</th>
            <th>Position</th>
            <th>Staff</th>
            <th style="width:170px;">Stage</th>
            <th style="width:160px;">Interview Date</th>
            <th style="width:260px;">Note</th>
            <th style="width:110px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr data-form-id="<?= (int)$r['form_id'] ?>">
            <td>
              <input class="form-check-input row-check" type="checkbox" value="<?= (int)$r['form_id'] ?>">
            </td>
            <td>#<?= (int)$r['form_id'] ?></td>

            <td>
              <div class="fw-semibold"><?=h($r['name'])?></div>
              <div class="small text-muted">Created: <?=h($r['created_at'])?></div>
            </td>

            <td><?=h($r['phone'])?></td>
            <td><?=h($r['position'])?></td>
            <td><?=h($r['staff_name'])?></td>

            <td>
              <select class="form-select form-select-sm stage-select">
                <?php foreach ($stages as $k => $label): ?>
                  <option value="<?=h($k)?>" <?=($r['interview_stage']===$k?'selected':'')?>>
                    <?=h($label)?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="small text-muted mt-1 stage-hint"></div>
            </td>

            <td>
              <input type="date" class="form-control form-control-sm date-input"
                     value="<?=h($r['interview_date'])?>">
            </td>

            <td>
              <input type="text" class="form-control form-control-sm note-input" maxlength="255"
                     placeholder="Optional note...">
            </td>

            <td>
              <button class="btn btn-success btn-sm w-100 save-btn" type="button">Save</button>
              <div class="small mt-1 status-msg"></div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  const stageHints = {
    new: "New submission (not scheduled yet)",
    scheduled: "Interview scheduled",
    interviewed: "Interview completed",
    passed: "Passed",
    failed: "Failed",
    no_show: "No-show",
    withdrawn: "Withdrawn"
  };

  const selectedCountEl = document.getElementById('selectedCount');
  const bulkMsg = document.getElementById('bulkMsg');

  function setBulkMsg(msg, ok) {
    bulkMsg.textContent = msg;
    bulkMsg.className = 'small mt-2 ' + (ok ? 'text-success' : 'text-danger');
  }

  function setRowMsg(tr, msg, ok) {
    const el = tr.querySelector('.status-msg');
    el.textContent = msg;
    el.className = 'small mt-1 status-msg ' + (ok ? 'text-success' : 'text-danger');
  }

  function updateHint(tr) {
    const stage = tr.querySelector('.stage-select').value;
    tr.querySelector('.stage-hint').textContent = stageHints[stage] || '';
  }

  function getSelectedIds() {
    return Array.from(document.querySelectorAll('.row-check:checked')).map(cb => cb.value);
  }

  function refreshSelectedCount() {
    selectedCountEl.textContent = String(getSelectedIds().length);
  }

  // init hints + row save handler
  document.querySelectorAll('tr[data-form-id]').forEach(tr => {
    updateHint(tr);

    tr.querySelector('.stage-select').addEventListener('change', () => {
      updateHint(tr);
      const stage = tr.querySelector('.stage-select').value;
      const dateInput = tr.querySelector('.date-input');
      const pageDate = new URLSearchParams(window.location.search).get('date');
      if (stage === 'scheduled' && !dateInput.value && pageDate) dateInput.value = pageDate;
    });

    tr.querySelector('.save-btn').addEventListener('click', async () => {
      const formId = tr.getAttribute('data-form-id');
      const stage = tr.querySelector('.stage-select').value;
      const interviewDate = tr.querySelector('.date-input').value;
      const note = tr.querySelector('.note-input').value;

      setRowMsg(tr, 'Saving...', true);

      const body = new URLSearchParams();
      body.set('form_id', formId);
      body.set('interview_stage', stage);
      body.set('interview_date', interviewDate);
      body.set('note', note);

      try {
        const res = await fetch('admin_update_stage.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        });

        const data = await res.json().catch(() => null);
        if (!res.ok || !data || !data.ok) {
          const err = (data && data.error) ? data.error : ('HTTP ' + res.status);
          setRowMsg(tr, 'Failed: ' + err, false);
          return;
        }
        setRowMsg(tr, 'Saved ✔', true);
      } catch (e) {
        setRowMsg(tr, 'Failed: ' + (e && e.message ? e.message : 'Network error'), false);
      }
    });
  });

  // checkbox logic
  const checkAll = document.getElementById('checkAll');
  if (checkAll) {
    checkAll.addEventListener('change', () => {
      document.querySelectorAll('.row-check').forEach(cb => cb.checked = checkAll.checked);
      refreshSelectedCount();
    });
  }
  document.querySelectorAll('.row-check').forEach(cb => {
    cb.addEventListener('change', refreshSelectedCount);
  });
  refreshSelectedCount();

  // Bulk Apply
  document.getElementById('bulkApplyBtn').addEventListener('click', async () => {
    const ids = getSelectedIds();
    const stage = document.getElementById('bulkStage').value;
    const date = document.getElementById('bulkDate').value;
    const note = document.getElementById('bulkNote').value;

    if (ids.length === 0) {
      setBulkMsg('Please select at least 1 row.', false);
      return;
    }
    if (!stage) {
      setBulkMsg('Bulk Stage is required.', false);
      return;
    }

    setBulkMsg('Applying bulk update...', true);

    const body = new URLSearchParams();
    body.set('form_ids', ids.join(','));
    body.set('interview_stage', stage);
    body.set('interview_date', date); // can be empty
    body.set('note', note);

    try {
      const res = await fetch('admin_bulk_update_stage.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      });

      const data = await res.json().catch(() => null);
      if (!res.ok || !data || !data.ok) {
        const err = (data && data.error) ? data.error : ('HTTP ' + res.status);
        setBulkMsg('Failed: ' + err, false);
        return;
      }

      // update UI rows locally (fast feedback)
      ids.forEach(id => {
        const tr = document.querySelector('tr[data-form-id="' + id + '"]');
        if (!tr) return;
        tr.querySelector('.stage-select').value = stage;
        updateHint(tr);
        if (date) tr.querySelector('.date-input').value = date;
        setRowMsg(tr, 'Bulk Saved ✔', true);
        tr.querySelector('.row-check').checked = false;
      });
      if (checkAll) checkAll.checked = false;
      refreshSelectedCount();

      setBulkMsg('Bulk update applied ✔ (updated ' + data.updated + ' row(s))', true);
    } catch (e) {
      setBulkMsg('Failed: ' + (e && e.message ? e.message : 'Network error'), false);
    }
  });
})();
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
