<?php
session_start();
require_once __DIR__ . "/db.php";

$error = "";
$msg = $_GET["msg"] ?? "";

if ($msg === "inactive") $error = "Your account is inactive. Please contact admin.";
if ($msg === "notfound") $error = "User not found. Please login again.";
if ($msg === "timeout")  $error = "Session expired due to inactivity. Please login again.";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT user_id, password, role, status FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(["email" => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Invalid email or password.";
    } elseif (($user["status"] ?? "active") !== "active") {
        $error = "Your account is inactive. Please contact admin.";
    } elseif (!password_verify($password, $user["password"])) {
        $error = "Invalid email or password.";
    } else {
        session_regenerate_id(true);
        $_SESSION["user_id"] = (int)$user["user_id"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["last_activity"] = time();

        if ($user["role"] === "admin") {
            header("Location: dashboard.php");
        } else {
            header("Location: my_form.php");
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>WishSystem - Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <div class="card p-4">
          <h3 class="ws-page-title mb-3">Login</h3>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="post">
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input class="form-control" type="email" name="email" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Password</label>
              <input class="form-control" type="password" name="password" required>
            </div>

            <button class="btn btn-primary w-100">Login</button>

            <div class="text-center mt-3">
              <a class="text-decoration-none" href="register.php">Staff Register</a>
            </div>
          </form>
        </div>

        <p class="text-center ws-muted mt-3 mb-0">WishSystem • Internal Interview Management</p>
      </div>
    </div>
  </div>
</body>
</html>
