<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

if (isset($conn) && !isset($pdo)) $pdo = $conn;
if (!isset($pdo)) die('DB connection not found');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$day = $_GET['day'] ?? '';
$d = DateTime::createFromFormat('Y-m-d', $day);
if (!$d || $d->format('Y-m-d') !== $day) {
    http_response_code(400);
    exit('Invalid day');
}

$stmt = $pdo->prepare("
    SELECT f.*, u.name AS staff_name, u.email AS staff_email
    FROM interview_forms f
    JOIN users u ON u.user_id = f.user_id
    WHERE COALESCE(f.interview_date, DATE(f.created_at)) = :day
    ORDER BY f.created_at DESC
");
$stmt->execute([':day' => $day]);
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<h4 class="mb-3">Interviews on <?= htmlspecialchars($day) ?></h4>

<table class="table table-bordered align-middle">
  <thead>
    <tr>
      <th>Candidate</th>
      <th>Phone</th>
      <th>Position</th>
      <th>Staff</th>
      <th>Stage</th>
      <th>Interview Date</th>
      <th>Created</th>
      <th>Detail</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($forms as $f): ?>
      <tr>
        <td><?= htmlspecialchars($f['name']) ?></td>
        <td><?= htmlspecialchars($f['phone']) ?></td>
        <td><?= htmlspecialchars($f['position']) ?></td>
        <td><?= htmlspecialchars($f['staff_name']) ?></td>
        <td>
          <span class="badge stage-<?= htmlspecialchars($f['interview_stage']) ?>">
            <?= htmlspecialchars($f['interview_stage']) ?>
          </span>
        </td>
        <td><?= htmlspecialchars($f['interview_date'] ?? '-') ?></td>
        <td><?= htmlspecialchars($f['created_at']) ?></td>
        <td>
          <a class="btn btn-sm btn-outline-primary" href="form_detail.php?form_id=<?= (int)$f['form_id'] ?>">View</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<a class="btn btn-outline-secondary" href="calender.php">Back</a>

<?php include 'footer.php'; ?>
