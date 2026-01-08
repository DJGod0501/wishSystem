<?php
require_once __DIR__ . "/db.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm_password"] ?? "";

    if ($name === "" || $email === "" || $password === "") {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(["email" => $email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password, role, status, created_at)
                VALUES (:name, :email, :password, 'online_posting', 'active', NOW())
            ");
            $stmt->execute([
                "name" => $name,
                "email" => $email,
                "password" => $hash
            ]);

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
  <title>WishSystem - Register</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-7 col-lg-6">
        <div class="card p-4">
          <h3 class="ws-page-title mb-3">Staff Register</h3>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
          <?php endif; ?>

          <form method="post">
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input class="form-control" name="name" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Email</label>
              <input class="form-control" type="email" name="email" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input class="form-control" type="password" name="password" required>
              <div class="form-text">Minimum 6 characters.</div>
            </div>

            <div class="mb-3">
              <label class="form-label">Confirm Password</label>
              <input class="form-control" type="password" name="confirm_password" required>
            </div>

            <button class="btn btn-primary w-100">Register</button>

            <div class="text-center mt-3">
              <a class="text-decoration-none" href="login.php">Back to login</a>
            </div>
          </form>
        </div>

        <p class="text-center ws-muted mt-3 mb-0">Role will be <b>online_posting</b> by default</p>
      </div>
    </div>
  </div>
</body>
</html>
