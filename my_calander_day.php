<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

$userId = $_SESSION['user_id'];

$day = $_GET['day'] ?? '';
$d = DateTime::createFromFormat('Y-m-d', $day);
if (!$d || $d->format('Y-m-d') !== $day) {
    exit('Invalid date');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM interview_forms
    WHERE user_id = :uid
      AND COALESCE(interview_date, DATE(created_at)) = :day
    ORDER BY created_at DESC
");
$stmt->execute([
    ':uid' => $userId,
    ':day' => $day
]);
$forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>

<div class="container">
<h4>My Interviews on <?= htmlspecialchars($day) ?></h4>

<table class="table table-bordered">
<tr>
<th>Candidate</th>
<th>Stage</th>
<th>Interview Date</th>
</tr>

<?php foreach ($forms as $f): ?>
<tr>
<td><?= htmlspecialchars($f['name']) ?></td>
<td>
<span class="badge stage-<?= htmlspecialchars($f['interview_stage']) ?>">
<?= htmlspecialchars($f['interview_stage']) ?>
</span>
</td>
<td><?= htmlspecialchars($f['interview_date'] ?? '-') ?></td>
</tr>
<?php endforeach; ?>

</table>
</div>

<?php include 'footer.php'; ?>
