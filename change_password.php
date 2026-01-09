<?php
require_once __DIR__ . "/auth_check.php";
require_once __DIR__ . "/csrf.php";

$title = "Change Password";
require_once __DIR__ . "/header.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validate();

    $current = $_POST["current_password"] ?? "";
    $new = $_POST["new_password"] ?? "";
    $confirm = $_POST["confirm_new_password"] ?? "";

    if ($current === "" || $new === "" || $confirm === "") {
        $error = "Please fill in all fields.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } elseif (strlen($new) < 8) {
        $error = "New password must be at least 8 characters.";
    } else {
        $uid = (int)($_SESSION["user_id"] ?? 0);

        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = :id LIMIT 1");
        $stmt->execute([":id" => $uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($current, $row["password"])) {
            $error = "Current password is incorrect.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = :pw WHERE user_id = :id");
            $stmt->execute([":pw" => $hash, ":id" => $uid]);

            csrf_rotate();
            $success = "Password changed successfully.";
        }
    }
}
?>

<h3 class="ws-page-title">Change Password</h3>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card" style="max-width:520px;">
  <div class="card-body">
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

      <div class="mb-3">
        <label class="form-label">Current Password</label>
        <input class="form-control" type="password" name="current_password" required>
      </div>

      <div class="mb-3">
        <label class="form-label">New Password (min 8 chars)</label>
        <input class="form-control" type="password" name="new_password" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Confirm New Password</label>
        <input class="form-control" type="password" name="confirm_new_password" required>
      </div>

      <button class="btn btn-primary w-100">Update Password</button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
