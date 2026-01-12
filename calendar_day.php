<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php'; // 你已放好，含 session_start + error display

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

// Admin guard
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

// DB guard: your project uses $conn (PDO)
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    exit('DB connection $conn not found');
}

// CSRF (keep your existing convention)
if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

// Date input
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

$dayObj = new DateTimeImmutable($date);
$weekday = $dayObj->format('l');

// Use half-open range [dayStart, nextDayStart) to avoid edge bugs
$dayStart = $dayObj->format('Y-m-d 00:00:00');
$nextDayStart = $dayObj->modify('+1 day')->format('Y-m-d 00:00:00');

// Query
$stmt = $conn->prepare("
    SELECT form_id, name, phone, position, interview_stage, interview_date
    FROM interview_forms
    WHERE interview_date IS NOT NULL
      AND interview_date >= :s
      AND interview_date < :e
    ORDER BY interview_date ASC, form_id DESC
");
$stmt->execute([':s' => $dayStart, ':e' => $nextDayStart]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmt_dt(?string $raw): string {
    if (!$raw) return '-';
    try {
        $dt = new DateTimeImmutable($raw);
        return $dt->format('Y-m-d H:i') . ' (' . $dt->format('D') . ')';
    } catch (Throwable $e) {
        return (string)$raw;
    }
}

function stage_badge_class(?string $stage): string {
    $stage = (string)$stage;
    return match ($stage) {
        'new' => 'secondary',
        'scheduled' => 'primary',
        'interviewed' => 'info',
        'passed' => 'success',
        'failed' => 'danger',
        'no_show' => 'warning',
        'withdrawn' => 'dark',
        default => 'secondary',
    };
}

$pageTitle = 'Calendar Day';
require_once __DIR__ . '/header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0"><?= h($date) ?> <span class="text-muted">(<?= h($weekday) ?>)</span></h3>
      <div class="small text-muted">Total interviews: <b><?= count($rows) ?></b></div>
    </div>
    <a class="btn btn-outline-secondary" href="/wishSystem/calendar.php">Back</a>
  </div>

  <?php if (!$rows): ?>
    <div class="alert alert-info">No interviews on this date.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:220px;">Interview</th>
            <th>Candidate</th>
            <th style="width:140px;">Phone</th>
            <th style="width:180px;">Position</th>
            <th style="width:140px;">Stage</th>
            <th class="text-end" style="width:320px;">Edit time</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            $val = '';
            if (!empty($r['interview_date'])) {
                try {
                    $dt = new DateTimeImmutable($r['interview_date']);
                    $val = $dt->format('Y-m-d\TH:i');
                } catch (Throwable $e) {
                    $val = '';
                }
            }
            $badge = stage_badge_class($r['interview_stage'] ?? '');
          ?>
          <tr>
            <td><?= h(fmt_dt($r['interview_date'] ?? null)) ?></td>
            <td>
              <a class="text-decoration-none" href="/wishSystem/form_detail.php?form_id=<?= (int)$r['form_id'] ?>">
                <?= h($r['name'] ?? '') ?>
              </a>
            </td>
            <td><?= h($r['phone'] ?? '') ?></td>
            <td><?= h($r['position'] ?? '') ?></td>
            <td><span class="badge bg-<?= h($badge) ?>"><?= h($r['interview_stage'] ?? '') ?></span></td>
            <td class="text-end">
              <form class="d-inline-flex gap-2" method="post" action="/wishSystem/update_interview.php">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['_csrf']) ?>">
                <input type="hidden" name="form_id" value="<?= (int)$r['form_id'] ?>">
                <input type="hidden" name="back_date" value="<?= h($date) ?>">
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

<?php require_once __DIR__ . '/footer.php'; ?>
