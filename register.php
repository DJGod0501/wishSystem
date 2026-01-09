<?php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/csrf.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validate();

    $name = trim($_POST["name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if ($name === "" || $email === "" || $password === "" || $confirm === "") {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([":email" => $email]);

        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password, role, status, created_at)
                VALUES (:name, :email, :password, 'online_posting', 'active', NOW())
            ");
            $stmt->execute([
                ":name" => $name,
                ":email" => $email,
                ":password" => $hash
            ]);

            csrf_rotate();
            $success = "Registration successful. You can login now.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register - WishSystem</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="container py-4" style="max-width:520px;">
  <h3 class="ws-page-title">Register</h3>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <a class="btn btn-primary" href="login.php">Go to Login</a>
  <?php else: ?>
    <div class="card">
      <div class="card-body">
        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

          <div class="mb-3">
            <label class="form-label">Name</label>
            <input class="form-control" name="name" value="<?= htmlspecialchars($_POST["name"] ?? "") ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($_POST["email"] ?? "") ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Password (min 8 chars)</label>
            <input class="form-control" type="password" name="password" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input class="form-control" type="password" name="confirm_password" required>
          </div>

          <button class="btn btn-primary w-100">Register</button>
          <div class="mt-3 text-center">
            <a href="login.php">Already have an account? Login</a>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
