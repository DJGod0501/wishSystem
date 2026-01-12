<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/header.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

$db = null;
if (isset($pdo) && $pdo instanceof PDO) $db = $pdo;
if (isset($conn) && $conn instanceof PDO) $db = $conn;

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// date param compatibility
$date = $_GET['date'] ?? $_GET['day'] ?? $_GET['d'] ?? date('Y-m-d');
$date = trim((string)$date);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

$rows = [];
if ($db && $userId > 0) {
    $stmt = $db->prepare("
        SELECT form_id, name, phone, position, interview_stage, interview_date, created_at
        FROM interview_forms
        WHERE user_id = ?
          AND DATE(created_at) = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId, $date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h3 class="mb-0">My Submissions</h3>
      <div class="text-muted">Date: <strong><?= h($date) ?></strong> (<?= count($rows) ?> record(s))</div>
    </div>
    <a class="btn btn-outline-secondary" href="my_calender.php">Back to Calendar</a>
  </div>

  <?php if (!$rows): ?>
    <div class="card"><div class="card-body text-muted">No submissions on this date.</div></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th style="width:90px;">Form ID</th>
            <th>Candidate</th>
            <th>Phone</th>
            <th>Position</th>
            <th style="width:140px;">Stage</th>
            <th style="width:130px;">Interview Date</th>
            <th style="width:180px;">Created At</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>#<?= (int)$r['form_id'] ?></td>
              <td class="fw-semibold"><?= h($r['name']) ?></td>
              <td><?= h($r['phone']) ?></td>
              <td><?= h($r['position']) ?></td>
              <td><?= h($r['interview_stage']) ?></td>
              <td><?= h($r['interview_date'] ?: '-') ?></td>
              <td><?= h($r['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
