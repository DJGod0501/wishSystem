<?php
echo "HIT calendar_day.php ✅";
exit;

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

// Admin guard
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

// DB handle
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    exit('DB connection $conn not found');
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$tz = new DateTimeZone('Asia/Kuala_Lumpur');

// date param: YYYY-MM-DD
$date = trim((string)($_GET['date'] ?? ''));
if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    // default today
    $date = (new DateTime('now', $tz))->format('Y-m-d');
}

$dayStart = new DateTime($date . ' 00:00:00', $tz);
$dayEnd = (clone $dayStart)->modify('+1 day');

$prevDate = (clone $dayStart)->modify('-1 day')->format('Y-m-d');
$nextDate = (clone $dayStart)->modify('+1 day')->format('Y-m-d');

// Fetch interviews for the day (F2F & Online)
$sqlInPerson = "
SELECT
  form_id, user_id, name, phone, position,
  interview_stage,
  interview_in_person_at AS interview_at
FROM interview_forms
WHERE interview_in_person_at IS NOT NULL
  AND interview_in_person_at >= :start
  AND interview_in_person_at <  :end
ORDER BY interview_in_person_at ASC, form_id ASC
";

$sqlOnline = "
SELECT
  form_id, user_id, name, phone, position,
  interview_stage,
  interview_online_at AS interview_at
FROM interview_forms
WHERE interview_online_at IS NOT NULL
  AND interview_online_at >= :start
  AND interview_online_at <  :end
ORDER BY interview_online_at ASC, form_id ASC
";

$params = [
    ':start' => $dayStart->format('Y-m-d H:i:s'),
    ':end'   => $dayEnd->format('Y-m-d H:i:s'),
];

$stmt = $conn->prepare($sqlInPerson);
$stmt->execute($params);
$inPersonList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare($sqlOnline);
$stmt->execute($params);
$onlineList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Calendar Day';
require_once __DIR__ . '/header.php';

// Helper: countdown label
function countdownLabel(DateTimeZone $tz, string $dt): array {
    $now = new DateTime('now', $tz);
    $t = new DateTime($dt, $tz);
    $diffSec = $t->getTimestamp() - $now->getTimestamp();
    $absMin = (int)floor(abs($diffSec) / 60);

    if ($diffSec > 0) {
        // future
        if ($absMin === 0) return ['Starts soon', 'text-bg-warning'];
        return ["Starts in {$absMin} min", $absMin <= 15 ? 'text-bg-warning' : 'text-bg-info'];
    }

    // past
    if ($absMin <= 59) return ["Started {$absMin} min ago", 'text-bg-secondary'];
    return ['Past', 'text-bg-secondary'];
}
?>

<div class="container my-4">

  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h3 class="mb-0">Calendar Day</h3>
      <div class="text-muted">
        <b><?= h($dayStart->format('d M Y (D)')) ?></b>
      </div>
    </div>

    <div class="btn-group" role="group" aria-label="Day navigation">
      <a class="btn btn-outline-secondary" href="calendar_day.php?date=<?= urlencode($prevDate) ?>">← Prev</a>
      <a class="btn btn-outline-secondary" href="calendar.php">Back to Calendar</a>
      <a class="btn btn-outline-secondary" href="calendar_day.php?date=<?= urlencode($nextDate) ?>">Next →</a>
    </div>
  </div>

  <!-- In-person -->
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div class="fw-semibold">Face-to-face Interviews</div>
      <span class="badge text-bg-primary">Total: <?= count($inPersonList) ?></span>
    </div>

    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 90px;">Time</th>
            <th style="width: 240px;">Candidate</th>
            <th>Position</th>
            <th style="width: 150px;">Stage</th>
            <th style="width: 160px;">Reminder</th>
            <th style="width: 220px;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$inPersonList): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No face-to-face interviews.</td></tr>
        <?php else: ?>
          <?php foreach ($inPersonList as $row): ?>
            <?php
              $dt = (string)$row['interview_at'];
              $label = countdownLabel($tz, $dt);
              $timeStr = (new DateTime($dt, $tz))->format('H:i');
            ?>
            <tr>
              <td class="fw-semibold"><?= h($timeStr) ?></td>
              <td>
                <div class="fw-semibold">
                  <a href="form_detail.php?form_id=<?= (int)$row['form_id'] ?>"><?= h($row['name'] ?? '-') ?></a>
                </div>
                <div class="small text-muted"><?= h($row['phone'] ?? '-') ?></div>
              </td>
              <td><?= h($row['position'] ?? '-') ?></td>
              <td>
                <span class="badge text-bg-secondary"><?= h($row['interview_stage'] ?? '-') ?></span>
              </td>
              <td>
                <span class="badge <?= h($label[1]) ?>"><?= h($label[0]) ?></span>
              </td>

              <td>
                <div class="d-flex gap-2 flex-wrap">
                  <!-- Inline edit time (reuse your update_interview.php) -->
                  <button class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#editTime-<?= (int)$row['form_id'] ?>-in">
                    Edit Time
                  </button>

                  <!-- Complete interview -->
                  <button class="btn btn-sm btn-outline-success"
                          data-bs-toggle="modal"
                          data-bs-target="#completeModal-<?= (int)$row['form_id'] ?>-in">
                    Complete
                  </button>

                  <!-- Copy details -->
                  <button class="btn btn-sm btn-outline-secondary"
                          type="button"
                          onclick="copyDetails('F2F', '<?= h($dayStart->format('d M Y')) ?>', '<?= h($timeStr) ?>', '<?= h($row['name'] ?? '-') ?>', '<?= h($row['phone'] ?? '-') ?>', '<?= h($row['position'] ?? '-') ?>', '<?= h($row['interview_stage'] ?? '-') ?>')">
                    Copy
                  </button>
                </div>

                <!-- Edit Time Modal -->
                <div class="modal fade" id="editTime-<?= (int)$row['form_id'] ?>-in" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog">
                    <form class="modal-content" method="post" action="update_interview.php">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Time (F2F)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="form_id" value="<?= (int)$row['form_id'] ?>">
                        <input type="hidden" name="interview_type" value="in_person">
                        <input type="hidden" name="back_to" value="calendar_day.php?date=<?= h($date) ?>">

                        <label class="form-label">New Date & Time</label>
                        <input class="form-control" type="datetime-local" name="interview_at"
                               value="<?= h((new DateTime($dt, $tz))->format('Y-m-d\TH:i')) ?>" required>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                      </div>
                    </form>
                  </div>
                </div>

                <!-- Complete Modal -->
                <div class="modal fade" id="completeModal-<?= (int)$row['form_id'] ?>-in" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog">
                    <form class="modal-content" method="post" action="complete_interview.php">
                      <div class="modal-header">
                        <h5 class="modal-title">Complete Interview (F2F)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>

                      <div class="modal-body">
                        <input type="hidden" name="form_id" value="<?= (int)$row['form_id'] ?>">
                        <input type="hidden" name="interview_type" value="in_person">
                        <input type="hidden" name="back_date" value="<?= h($date) ?>">

                        <div class="mb-3">
                          <label class="form-label">Attendance</label>
                          <div class="d-flex gap-3">
                            <label class="form-check">
                              <input class="form-check-input" type="radio" name="attendance" value="attended" required>
                              <span class="form-check-label">Attended</span>
                            </label>
                            <label class="form-check">
                              <input class="form-check-input" type="radio" name="attendance" value="no_show" required>
                              <span class="form-check-label">No-show</span>
                            </label>
                          </div>
                        </div>

                        <div class="mb-3">
                          <label class="form-label">Feedback (optional)</label>
                          <textarea class="form-control" name="feedback" rows="3" placeholder="Write short feedback..."></textarea>
                        </div>

                        <div class="small text-muted">
                          This will update stage to <b>interviewed</b> or <b>no_show</b>.
                        </div>
                      </div>

                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save</button>
                      </div>
                    </form>
                  </div>
                </div>

              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Online -->
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div class="fw-semibold">Online Interviews</div>
      <span class="badge text-bg-info text-dark">Total: <?= count($onlineList) ?></span>
    </div>

    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width: 90px;">Time</th>
            <th style="width: 240px;">Candidate</th>
            <th>Position</th>
            <th style="width: 150px;">Stage</th>
            <th style="width: 160px;">Reminder</th>
            <th style="width: 220px;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$onlineList): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">No online interviews.</td></tr>
        <?php else: ?>
          <?php foreach ($onlineList as $row): ?>
            <?php
              $dt = (string)$row['interview_at'];
              $label = countdownLabel($tz, $dt);
              $timeStr = (new DateTime($dt, $tz))->format('H:i');
            ?>
            <tr>
              <td class="fw-semibold"><?= h($timeStr) ?></td>
              <td>
                <div class="fw-semibold">
                  <a href="form_detail.php?form_id=<?= (int)$row['form_id'] ?>"><?= h($row['name'] ?? '-') ?></a>
                </div>
                <div class="small text-muted"><?= h($row['phone'] ?? '-') ?></div>
              </td>
              <td><?= h($row['position'] ?? '-') ?></td>
              <td>
                <span class="badge text-bg-secondary"><?= h($row['interview_stage'] ?? '-') ?></span>
              </td>
              <td>
                <span class="badge <?= h($label[1]) ?>"><?= h($label[0]) ?></span>
              </td>

              <td>
                <div class="d-flex gap-2 flex-wrap">
                  <button class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#editTime-<?= (int)$row['form_id'] ?>-on">
                    Edit Time
                  </button>

                  <button class="btn btn-sm btn-outline-success"
                          data-bs-toggle="modal"
                          data-bs-target="#completeModal-<?= (int)$row['form_id'] ?>-on">
                    Complete
                  </button>

                  <button class="btn btn-sm btn-outline-secondary"
                          type="button"
                          onclick="copyDetails('Online', '<?= h($dayStart->format('d M Y')) ?>', '<?= h($timeStr) ?>', '<?= h($row['name'] ?? '-') ?>', '<?= h($row['phone'] ?? '-') ?>', '<?= h($row['position'] ?? '-') ?>', '<?= h($row['interview_stage'] ?? '-') ?>')">
                    Copy
                  </button>
                </div>

                <!-- Edit Time Modal -->
                <div class="modal fade" id="editTime-<?= (int)$row['form_id'] ?>-on" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog">
                    <form class="modal-content" method="post" action="update_interview.php">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Time (Online)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="form_id" value="<?= (int)$row['form_id'] ?>">
                        <input type="hidden" name="interview_type" value="online">
                        <input type="hidden" name="back_to" value="calendar_day.php?date=<?= h($date) ?>">

                        <label class="form-label">New Date & Time</label>
                        <input class="form-control" type="datetime-local" name="interview_at"
                               value="<?= h((new DateTime($dt, $tz))->format('Y-m-d\TH:i')) ?>" required>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                      </div>
                    </form>
                  </div>
                </div>

                <!-- Complete Modal -->
                <div class="modal fade" id="completeModal-<?= (int)$row['form_id'] ?>-on" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog">
                    <form class="modal-content" method="post" action="complete_interview.php">
                      <div class="modal-header">
                        <h5 class="modal-title">Complete Interview (Online)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>

                      <div class="modal-body">
                        <input type="hidden" name="form_id" value="<?= (int)$row['form_id'] ?>">
                        <input type="hidden" name="interview_type" value="online">
                        <input type="hidden" name="back_date" value="<?= h($date) ?>">

                        <div class="mb-3">
                          <label class="form-label">Attendance</label>
                          <div class="d-flex gap-3">
                            <label class="form-check">
                              <input class="form-check-input" type="radio" name="attendance" value="attended" required>
                              <span class="form-check-label">Attended</span>
                            </label>
                            <label class="form-check">
                              <input class="form-check-input" type="radio" name="attendance" value="no_show" required>
                              <span class="form-check-label">No-show</span>
                            </label>
                          </div>
                        </div>

                        <div class="mb-3">
                          <label class="form-label">Feedback (optional)</label>
                          <textarea class="form-control" name="feedback" rows="3" placeholder="Write short feedback..."></textarea>
                        </div>

                        <div class="small text-muted">
                          This will update stage to <b>interviewed</b> or <b>no_show</b>.
