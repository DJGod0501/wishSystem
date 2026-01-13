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

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$sql = "
SELECT
  u.user_id,
  u.name,
  u.email,
  u.status,

  SUM(
    CASE
      WHEN f.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND f.created_at <  DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
      THEN 1 ELSE 0
    END
  ) AS submissions_this_month,

  SUM(
    CASE
      WHEN f.created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
       AND f.created_at <  DATE_FORMAT(CURDATE(), '%Y-%m-01')
      THEN 1 ELSE 0
    END
  ) AS submissions_last_month

FROM users u
LEFT JOIN interview_forms f ON f.user_id = u.user_id
WHERE u.role = 'online_posting'
GROUP BY u.user_id, u.name, u.email, u.status
ORDER BY
  (u.status = 'active') DESC,
  submissions_this_month DESC,
  submissions_last_month DESC,
  u.name ASC
";

$staff = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Staff Management';
require_once __DIR__ . '/header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Staff Management</h3>
      <div class="small text-muted">
        KPI: Posting staff activity (This Month / Last Month)
      </div>
    </div>
    <a class="btn btn-outline-secondary" href="/wishSystem/dashboard.php">Back</a>
  </div>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th>Staff</th>
          <th>Email</th>
          <th>Status</th>
          <th class="text-end">This Month</th>
          <th class="text-end">Last Month</th>
          <th style="width:140px;">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($staff as $s): ?>
        <?php $isActive = ($s['status'] === 'active'); ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= h($s['name']) ?></div>
            <div class="small text-muted">ID: <?= (int)$s['user_id'] ?></div>
          </td>
          <td><?= h($s['email']) ?></td>
          <td>
            <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?>">
              <?= h($s['status']) ?>
            </span>
          </td>
          <td class="text-end"><?= (int)$s['submissions_this_month'] ?></td>
          <td class="text-end"><?= (int)$s['submissions_last_month'] ?></td>
          <td>
            <form method="post" action="staff_action.php" onsubmit="return confirm('Confirm action?');">
              <input type="hidden" name="user_id" value="<?= (int)$s['user_id'] ?>">
              <?php if ($isActive): ?>
                <input type="hidden" name="action" value="disable">
                <button class="btn btn-sm btn-outline-danger">Disable</button>
              <?php else: ?>
                <input type="hidden" name="action" value="enable">
                <button class="btn btn-sm btn-outline-success">Enable</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
