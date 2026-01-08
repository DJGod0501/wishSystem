<?php
require_once __DIR__ . "/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

/* ---- CSRF ---- */
function csrf_token() {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}
function csrf_check() {
    return isset($_POST["csrf_token"]) &&
           hash_equals($_SESSION["csrf_token"] ?? "", $_POST["csrf_token"]);
}

/* ---- Rate limit ---- */
$_SESSION["login_try"] = $_SESSION["login_try"] ?? 0;
$_SESSION["login_lock"] = $_SESSION["login_lock"] ?? 0;

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (time() < $_SESSION["login_lock"]) {
        $error = "Too many attempts. Please wait.";
    } elseif (!csrf_check()) {
        $error = "Security check failed.";
    } else {
        $email = strtolower(trim($_POST["email"] ?? ""));
        $password = $_POST["password"] ?? "";

        $stmt = $conn->prepare("SELECT user_id, password, role, status FROM users WHERE email = :e LIMIT 1");
        $stmt->execute([":e" => $email]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($password, $u["password"])) {
            $_SESSION["login_try"]++;
            if ($_SESSION["login_try"] >= 5) {
                $_SESSION["login_lock"] = time() + 300;
                $_SESSION["login_try"] = 0;
            }
            $error = "Invalid email or password.";
        } elseif ($u["status"] !== "active") {
            $error = "Account inactive.";
        } else {
            session_regenerate_id(true);
            $_SESSION["user_id"] = $u["user_id"];
            $_SESSION["role"] = $u["role"];
            $_SESSION["login_try"] = 0;
            header("Location: " . ($u["role"] === "admin" ? "dashboard.php" : "my_form.php"));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<div class="container py-4" style="max-width:420px;">
<h3 class="ws-page-title">Login</h3>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
  <div class="mb-3">
    <label>Email</label>
    <input class="form-control" type="email" name="email" required>
  </div>
  <div class="mb-3">
    <label>Password</label>
    <input class="form-control" type="password" name="password" required>
  </div>
  <button class="btn btn-primary w-100">Login</button>
</form>
</div>
</body>
</html>
