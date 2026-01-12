<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$pageTitle = 'Staff Management';
require_once __DIR__ . '/header.php';

/* =========================
   Load staff list
   ========================= */
$sql = "
SELECT 
  u.user_id,
  u.name,
  u.email,
  u.status,
  COUNT(f.form_id) AS submissions
FROM users u
LEFT JOIN interview_forms f ON f.user_id = u.user_id
WHERE u.role = 'online_posting'
GROUP BY u.user_id
ORDER BY u.status DESC, submissions DESC, u.name ASC
";
$staff = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container my-4">
  <h3>Staff Management</h3>

  <table class="table table-striped mt-3">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Status</th>
        <th>Submissions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($staff as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><?= htmlspecialchars($s['email']) ?></td>
          <td>
            <span class="badge text-bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>">
              <?= htmlspecialchars($s['status']) ?>
            </span>
          </td>
          <td><?= (int)$s['submissions'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
