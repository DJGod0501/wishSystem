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

// DB guard (project uses $conn)
if (!isset($conn) || !($conn instanceof PDO)) {
    http_response_code(500);
    exit('DB connection $conn not found');
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Staff list + submission stats (LEFT JOIN keeps staff with 0 submissions)
$sql = "
SELECT 
  u.user_id,
  u.name,
  u.email,
  u.status,
  u.created_at,
  COUNT(f.form_id) AS submissions_total,
  SUM(CASE WHEN f.created_at >= (NOW() - INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS submissions_7d
FROM users u
LEFT JOIN interview_forms f ON f.user_id = u.user_id
WHERE u.role = 'online_posting'
GROUP BY u.user_id, u.name, u.email, u.status, u.created_at
ORDER BY u.status DESC, submissions_7d DESC, submissions_total DESC, u.name ASC
";
$staff = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Staff Management';
require_once __DIR__ . '/header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Staff Management</h3>
    <a class="btn btn-outline-secondary" href="/wishSystem/dashboard.php">Back</a>
  </div>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th style="width: 260px;">Staff</th>
          <th>Email</th>
          <th style="width: 120px;">Status</th>
          <th class="text-end" style="width: 110px;">7 Days</th>
          <th class="text-end" style="width: 110px;">All Time</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($staff as $s): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h($s['name']) ?></div>
            <div class="small text-muted">ID: <?= (int)$s['user_id'] ?></div>
          </td>
          <td><?= h($s['email']) ?></td>
          <td>
            <?php $isActive = (($s['status'] ?? '') === 'active'); ?>
            <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?>">
              <?= h($s['status'] ?? '') ?>
            </span>
          </td>
          <td class="text-end"><?= (int)$s['submissions_7d'] ?></td>
          <td class="text-end"><?= (int)$s['submissions_total'] ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
