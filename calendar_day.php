<?php
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
    $date = (new DateTime('now', $tz))->format('Y-m-d');
}

$dayStart = new DateTime($date . ' 00:00:00', $tz);
$dayEnd   = (clone $dayStart)->modify('+1 day');

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

// countdown label
function countdownLabel(DateTimeZone $tz, string $dt): array {
    $now = new DateTime('now', $tz);
    $t = new DateTime($dt, $tz);
    $diffSec = $t->getTimestamp() - $now->getTimestamp();
    $absMin = (int)floor(abs($diffSec) / 60);

    if ($diffSec > 0) {
        if ($absMin === 0) return ['Starts soon', 'text-bg-warning'];
        return ["Starts in {$absMin} min", $absMin <= 15 ? 'text-bg-warning' : 'text-bg-info'];
    }
    if ($absMin <= 59) return ["Started {$absMin} min ago", 'text-bg-secondary'];
    return ['Past', 'text-bg-secondary'];
}

// shared renderer
function renderInterviewTable(array $list, string $typeLabel, string $typeKey, DateTimeZone $tz, string $date): void {
    ?>
    <div class="card mb-4">
      <div class="card-header">
        <div class="fw-semibold"><?= h($typeLabel) ?></div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 90px;">Time</th>
              <th style="width: 260px;">Candidate</th>
              <th>Position</th>
              <th style="width: 150px;">Stage</th>
              <th style="width: 170px;">Reminder</th>
              <th style="width: 240px;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($list as $row): ?>
            <?php
              $dt = (string)$row['interview_at'];
              $label = countdownLabel($tz, $dt);
              $timeStr = (new DateTime($dt, $tz))->format('H:i');
              $suffix = $typeKey === 'in_person' ? 'in' : 'on';
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
              <td><span class="badge text-bg-secondary"><?= h($row['interview_stage'] ?? '-') ?></span></td>
              <td><span class="badge <?= h($label[1]) ?>"><?= h($label[0]) ?></span></td>

              <td>
                <div class="d-flex gap-2 flex-wrap">
                  <button class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#editTime-<?= (int)$row['form_id'] ?>-<?= h($suffix) ?>">
                    Edit Time
                  </button>

                  <button class="btn btn-sm btn-outline-success"
                          data-bs-toggle="modal"
                          data-bs-target="#completeModal-<?= (int)$row['form_id'] ?>-<?= h($suffix) ?>">
                    Complete
                  </button>

                  <button class="btn btn-sm btn-outline-secondary" type="button"
                          onclick="copyDetails('<?= h($typeKey === 'in_person' ? 'F2F' : 'Online') ?>','<?= h((new DateTime($date, $tz))->format('d M Y')) ?>','<?= h($timeStr) ?>','<?= h($row['name'] ?? '-') ?>','<?= h($row['phone'] ?? '-') ?>','<?= h($row['position'] ?? '-') ?>','<?= h($row['interview_stage'] ?? '-') ?>')">
                    Copy
                  </button>
                </div>

                <!-- Edit Time Modal -->
                <div class="modal fade" id="editTime-<?= (int)$row['form_id'] ?>-<?= h($suffix) ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog">
                    <form class="modal-content" method="post" action="update_interview.php">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Time (<?= h($typeKey === 'in_person' ? 'F2F' : 'Online') ?>)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="form_id" value="<?= (int)$row['form_id'] ?>">
                        <input type="hidden" name="interview_type" value="<?= h($typeKey) ?>">
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
                <div class="modal fade" id="completeModal-<?= (int)$row['form_id'] ?>-<?= h($suffix) ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog">
                    <form class="modal-content" method="post" action="complete_interview.php">
                      <div class="modal-header">
                        <h5 class="modal-title">Complete Interview (<?= h($typeKey === 'in_person' ? 'F2F' : 'Online') ?>)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="form_id" value="<?= (int)$row['form_id'] ?>">
                        <input type="hidden" name="interview_type" value="<?= h($typeKey) ?>">
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
          </tbody>
        </table>
      </div>
    </div>
    <?php
}
?>

<div class="container my-4">

  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
      <h3 class="mb-0">Calendar Day</h3>
      <div class="text-muted"><b><?= h($dayStart->format('d M Y (D)')) ?></b></div>
    </div>

    <div class="btn-group" role="group" aria-label="Day navigation">
      <a class="btn btn-outline-secondary" href="calendar_day.php?date=<?= urlencode($prevDate) ?>">← Prev</a>
      <a class="btn btn-outline-secondary" href="calendar.php">Back to Calendar</a>
      <a class="btn btn-outline-secondary" href="calendar_day.php?date=<?= urlencode($nextDate) ?>">Next →</a>
    </div>
  </div>

  <?php if (count($inPersonList) === 0 && count($onlineList) === 0): ?>
    <div class="alert alert-light border d-flex justify-content-between align-items-center">
      <div class="text-muted">No interviews scheduled for this day.</div>
      <a class="btn btn-outline-secondary btn-sm" href="calendar.php">Back to Calendar</a>
    </div>
  <?php else: ?>
    <?php if (count($inPersonList) > 0) renderInterviewTable($inPersonList, 'Face-to-face Interviews', 'in_person', $tz, $date); ?>
    <?php if (count($onlineList) > 0) renderInterviewTable($onlineList, 'Online Interviews', 'online', $tz, $date); ?>
  <?php endif; ?>

</div>

<script>
async function copyDetails(type, date, time, name, phone, position, stage) {
  const text =
`Interview Details
Type: ${type}
Date: ${date}
Time: ${time}
Name: ${name}
Phone: ${phone}
Position: ${position}
Stage: ${stage}`;
  try {
    await navigator.clipboard.writeText(text);
    alert('Copied!');
  } catch (e) {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
    alert('Copied!');
  }
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
