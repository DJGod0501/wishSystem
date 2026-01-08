<?php
require_once __DIR__ . "/auth_check.php"; // includes session + db

$title = "Change Password";
require_once __DIR__ . "/header.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current = $_POST["current_password"] ?? "";
    $new = $_POST["new_password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if ($new === "" || $current === "") {
        $error = "Please fill in all fields.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } elseif (strlen($new) < 6) {
        $error = "New password must be at least 6 characters.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = :id LIMIT 1");
        $stmt->execute(["id" => $_SESSION["user_id"]]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($current, $u["password"])) {
            $error = "Current password is incorrect.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = :p WHERE user_id = :id");
            $stmt->execute(["p" => $hash, "id" => $_SESSION["user_id"]]);
            $success = "Password updated successfully.";
        }
    }
}
?>

<h3 class="ws-page-title mb-3">Change Password</h3>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card p-4">
  <form method="post">
    <div class="mb-3">
      <label class="form-label">Current Password</label>
      <input class="form-control" type="password" name="current_password" required>
    </div>

    <div class="mb-3">
      <label class="form-label">New Password</label>
      <input class="form-control" type="password" name="new_password" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Confirm New Password</label>
      <input class="form-control" type="password" name="confirm_password" required>
    </div>

    <button class="btn btn-primary">Update Password</button>
  </form>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
