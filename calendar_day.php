<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  http_response_code(403);
  exit('Forbidden');
}
if (!isset($conn) || !($conn instanceof PDO)) {
  http_response_code(500);
  exit('DB connection $conn not found');
}

if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fmt_dt(?string $raw): string {
  if (!$raw) return '-';
  try {
    $dt = new DateTimeImmutable($raw);
    return $dt->format('Y-m-d H:i') . ' (' . $dt->format('D') . ')';
  } catch(Throwable $e) {
    return (string)$raw;
  }
}

function to_dt_local(?string $raw): string {
  if (!$raw) return '';
  try {
    $dt = new DateTimeImmutable($raw);
    return $dt->format('Y-m-d\TH:i');
  } catch(Throwable $e) {
    return '';
  }
}

function countdown_badge(?string $raw): array {
  if (!$raw) return ['-', 'secondary'];
  try {
    $now = new DateTimeImmutable('now');
    $dt = new DateTimeImmutable($raw);
    $diffSec = $dt->getTimestamp() - $now->getTimestamp();
    $mins = (int)floor($diffSec / 60);

    if ($diffSec >= 0 && $mins <= 15) return ["Starts in {$mins}m", 'danger'];
    if ($diffSec >= 0 && $mins <= 60) return ["Starts in {$mins}m", 'warning'];
    if ($diffSec >= 0) return ["In {$mins}m", 'info'];

    $minsPast = (int)floor(abs($diffSec)/60);
    if ($minsPast <= 60) return ["Started {$minsPast}m ago", 'secondary'];
    return ["Past", 'secondary'];
  } catch(Throwable $e) {
    return ['-', 'secondary'];
  }
}

// Date
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$dayObj = new DateTimeImmutable($date);
$weekday = $dayObj->format('l');
$dayStart = $dayObj->format('Y-m-d 00:00:00');
$nextDayStart = $dayObj->modify('+1 day')->format('Y-m-d 00:00:00');

// In-person list
$stmt1 = $conn->prepare("
  SELECT form_id, name, phone, position, interview_stage, interview_in_person_at
  FROM interview_forms
  WHERE interview_in_person_at IS NOT NULL
    AND interview_in_person_at >= :s AND interview_in_person_at < :e
  ORDER BY interview_in_person_at ASC, form_id DESC
");
$stmt1->execute([':s' => $dayStart, ':e' => $nextDayStart]);
$inPerson = $stmt1->fetchAll(PDO::FETCH_ASSOC);

// Online list
$stmt2 = $conn->prepare("
  SELECT form_id, name, phone, position, interview_stage, interview_online_at
  FROM interview_forms
  WHERE interview_online_at IS NOT NULL
    AND interview_online_at >= :s AND interview_online_at < :e
  ORDER BY interview_online_at ASC, form_id DESC
");
$stmt2->execute([':s' => $dayStart, ':e' => $nextDayStart]);
$online = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Calendar Day';
require_once __DIR__ . '/header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0"><?= h($date) ?> <span class="text-muted">(<?= h($weekday) ?>)</span></h3>
      <div class="small text-muted">
        In-person: <b><?= count($inPerson) ?></b> · Online: <b><?= count($online) ?></b>
      </div>
    </div>
    <a class="btn btn-outline-secondary" href="/wishSystem/calendar.php">Back</a>
  </div>

  <div class="row g-3">
    <!-- In-person -->
    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-header fw-semibold">In-person Interviews</div>
        <div class="card-body p-0">
          <?php if (!$inPerson): ?>
            <div class="p-3 text-muted">No in-person interviews on this date.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:190px;">Time</th>
                    <th>Candidate</th>
                    <th style="width:120px;">Stage</th>
                    <th style="width:140px;">Reminder</th>
                    <th class="text-end" style="width:320px;">Edit</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($inPerson as $r): ?>
                  <?php
                    $val = to_dt_local($r['interview_in_person_at'] ?? null);
                    [$remText, $remClass] = countdown_badge($r['interview_in_person_at'] ?? null);
                  ?>
                  <tr>
                    <td><?= h(fmt_dt($r['interview_in_person_at'] ?? null)) ?></td>
                    <td>
                      <a class="text-decoration-none" href="/wishSystem/form_detail.php?form_id=<?= (int)$r['form_id'] ?>">
                        <?= h($r['name'] ?? '') ?>
                      </a>
                      <div class="small text-muted"><?= h($r['position'] ?? '') ?> · <?= h($r['phone'] ?? '') ?></div>
                    </td>
                    <td><span class="badge bg-secondary"><?= h($r['interview_stage'] ?? '') ?></span></td>
                    <td><span class="badge text-bg-<?= h($remClass) ?>"><?= h($remText) ?></span></td>
                    <td class="text-end">
                      <form class="d-inline-flex gap-2" method="post" action="/wishSystem/update_interview.php">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['_csrf']) ?>">
                        <input type="hidden" name="form_id" value="<?= (int)$r['form_id'] ?>">
                        <input type="hidden" name="back_date" value="<?= h($date) ?>">
                        <input type="hidden" name="interview_type" value="in_person">
                        <input type="datetime-local" class="form-control form-control-sm" name="interview_datetime" value="<?= h($val) ?>" required>
                        <button class="btn btn-sm btn-primary" type="submit">Save</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Online -->
    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-header fw-semibold">Online Interviews</div>
        <div class="card-body p-0">
          <?php if (!$online): ?>
            <div class="p-3 text-muted">No online interviews on this date.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:190px;">Time</th>
                    <th>Candidate</th>
                    <th style="width:120px;">Stage</th>
                    <th style="width:140px;">Reminder</th>
                    <th class="text-end" style="width:320px;">Edit</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($online as $r): ?>
                  <?php
                    $val = to_dt_local($r['interview_online_at'] ?? null);
                    [$remText, $remClass] = countdown_badge($r['interview_online_at'] ?? null);
                  ?>
                  <tr>
                    <td><?= h(fmt_dt($r['interview_online_at'] ?? null)) ?></td>
                    <td>
                      <a class="text-decoration-none" href="/wishSystem/form_detail.php?form_id=<?= (int)$r['form_id'] ?>">
                        <?= h($r['name'] ?? '') ?>
                      </a>
                      <div class="small text-muted"><?= h($r['position'] ?? '') ?> · <?= h($r['phone'] ?? '') ?></div>
                    </td>
                    <td><span class="badge bg-secondary"><?= h($r['interview_stage'] ?? '') ?></span></td>
                    <td><span class="badge text-bg-<?= h($remClass) ?>"><?= h($remText) ?></span></td>
                    <td class="text-end">
                      <form class="d-inline-flex gap-2" method="post" action="/wishSystem/update_interview.php">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['_csrf']) ?>">
                        <input type="hidden" name="form_id" value="<?= (int)$r['form_id'] ?>">
                        <input type="hidden" name="back_date" value="<?= h($date) ?>">
                        <input type="hidden" name="interview_type" value="online">
                        <input type="datetime-local" class="form-control form-control-sm" name="interview_datetime" value="<?= h($val) ?>" required>
                        <button class="btn btn-sm btn-primary" type="submit">Save</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="small text-muted mt-3">
    Reminder badges update on refresh. (If you want auto-refresh every minute, tell me and I’ll add it.)
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
