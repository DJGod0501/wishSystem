<?php
require_once __DIR__ . "/auth_check.php";

$form_id = (int)($_GET["id"] ?? 0);
if ($form_id <= 0) die("Invalid form id.");

$title = "Form Detail";
require_once __DIR__ . "/header.php";

// Admin can view all; staff can view only own
$params = ["id" => $form_id];
$sql = "
SELECT f.*, u.name AS staff_name, u.email AS staff_email,
       ls.english_writing, ls.english_speaking, ls.malay_writing, ls.malay_speaking, ls.chinese_writing, ls.chinese_speaking
FROM interview_forms f
JOIN users u ON u.user_id = f.user_id
LEFT JOIN language_skills ls ON ls.form_id = f.form_id
WHERE f.form_id = :id
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$form = $stmt->fetch();

if (!$form) die("Form not found.");

if (($_SESSION["role"] ?? "") === "online_posting" && (int)$form["user_id"] !== (int)$_SESSION["user_id"]) {
    die("Access denied.");
}

function showScore($v) {
    $v = (int)$v;
    if ($v < 1 || $v > 5) return "-";
    return $v . " / 5";
}
?>

<h3 class="ws-page-title mb-3">Interview Form Detail</h3>

<div class="card p-4 mb-3">
  <div class="row g-3">
    <div class="col-md-6"><b>Candidate Name:</b> <?= htmlspecialchars($form["name"] ?? "") ?></div>
    <div class="col-md-3"><b>Age:</b> <?= htmlspecialchars($form["age"] ?? "") ?></div>
    <div class="col-md-3"><b>Gender:</b> <?= htmlspecialchars($form["gender"] ?? "") ?></div>

    <div class="col-md-6"><b>Phone:</b> <?= htmlspecialchars($form["phone"] ?? "") ?></div>
    <div class="col-md-6"><b>Position:</b> <?= htmlspecialchars($form["position"] ?? "") ?></div>

    <div class="col-md-6"><b>Transportation:</b> <?= htmlspecialchars($form["transportation"] ?? "") ?></div>
    <div class="col-md-6"><b>Education:</b> <?= htmlspecialchars($form["education"] ?? "") ?></div>

    <div class="col-md-6"><b>Area:</b> <?= htmlspecialchars($form["area"] ?? "") ?></div>
    <div class="col-md-6"><b>Expected Salary:</b> <?= htmlspecialchars($form["expected_salary"] ?? "") ?></div>

    <div class="col-12"><b>Work Experience:</b><br><?= nl2br(htmlspecialchars($form["work_experience"] ?? "")) ?></div>

    <div class="col-md-6"><b>Start Date:</b> <?= htmlspecialchars($form["start_date"] ?? "") ?></div>
    <div class="col-md-6"><b>Submitted At:</b> <?= htmlspecialchars($form["created_at"] ?? "") ?></div>

    <div class="col-12"><hr></div>

    <div class="col-md-6">
      <b>Submitted By:</b><br>
      <?= htmlspecialchars($form["staff_name"] ?? "") ?><br>
      <span class="ws-muted"><?= htmlspecialchars($form["staff_email"] ?? "") ?></span>
    </div>

    <div class="col-md-6">
      <b>Language Skills:</b>
      <ul class="mb-0">
        <li>English: Writing <?= showScore($form["english_writing"] ?? null) ?>, Speaking <?= showScore($form["english_speaking"] ?? null) ?></li>
        <li>Malay: Writing <?= showScore($form["malay_writing"] ?? null) ?>, Speaking <?= showScore($form["malay_speaking"] ?? null) ?></li>
        <li>Chinese: Writing <?= showScore($form["chinese_writing"] ?? null) ?>, Speaking <?= showScore($form["chinese_speaking"] ?? null) ?></li>
      </ul>
    </div>
  </div>
</div>

<a class="btn btn-outline-secondary"
   href="<?= (($_SESSION["role"] ?? "") === "admin") ? "form.php" : "my_form.php" ?>">
  Back
</a>

<?php require_once __DIR__ . "/footer.php"; ?>
