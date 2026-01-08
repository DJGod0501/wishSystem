<?php
require_once 'auth_check.php';
require_once 'db.php';

if (($_SESSION['role'] ?? '') !== 'online_posting') {
    http_response_code(403);
    exit('Forbidden');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    exit('Invalid date');
}

function stage_badge_class(string $stage): string {
    return match($stage) {
        'new' => 'bg-secondary',
        'scheduled' => 'bg-primary',
        'interviewed' => 'bg-info',
        'passed' => 'bg-success',
        'failed' => 'bg-danger',
        'no_show' => 'bg-warning text-dark',
        'withdrawn' => 'bg-dark',
        default => 'bg-secondary',
    };
}

$sql = "
SELECT form_id, name, phone, position, area, expected_salary, created_at, interview_stage, interview_date
FROM interview_forms
WHERE user_id = :uid AND interview_date = :d
ORDER BY created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $userId, ':d' => $date]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'header.php';
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <h4 class="mb-1">Interviews on <?= htmlspecialchars($date) ?></h4>
      <div class="text-muted small">Your submissions scheduled for this date.</div>
    </div>
    <a class="btn btn-outline-secondary" href="my_calendar.php">← Back to calendar</a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <?php if (empty($rows)): ?>
        <div class="alert alert-warning mb-0">No interviews found for this date.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Candidate</th>
                <th>Position</th>
                <th>Area</th>
                <th>Salary</th>
                <th>Status</th>
                <th>Submitted</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($r['name']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($r['phone'] ?? '') ?></div>
                  </td>
                  <td><?= htmlspecialchars($r['position'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['area'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['expected_salary'] ?? '') ?></td>
                  <td>
                    <span class="badge <?= stage_badge_class($r['interview_stage']) ?>">
                      <?= htmlspecialchars($r['interview_stage']) ?>
                    </span>
                  </td>
                  <td class="text-muted small"><?= htmlspecialchars($r['created_at']) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary"
                       href="form_detail.php?form_id=<?= (int)$r['form_id'] ?>">
                       View
                    </a>
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

<?php require_once 'footer.php'; ?>
