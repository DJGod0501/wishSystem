<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = $_SESSION["role"] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($title ?? "WishSystem") ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand" href="<?= ($role==="admin") ? "dashboard.php" : "my_form.php" ?>">
      WishSystem
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#wsNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="wsNav">
      <ul class="navbar-nav me-auto">

        <?php if ($role === "admin"): ?>
          <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="form.php">All Forms</a></li>
          <li class="nav-item"><a class="nav-link" href="user.php">Users</a></li>
          <li class="nav-item"><a class="nav-link" href="calender.php">Calendar</a></li>
          <li class="nav-item"><a class="nav-link" href="staff_performance.php">Staff Performance</a></li>
          <li class="nav-item"><a class="nav-link" href="export_csv.php">Export CSV</a></li>

        <?php elseif ($role === "online_posting"): ?>
          <li class="nav-item"><a class="nav-link" href="submit_form.php">Submit Form</a></li>
          <li class="nav-item"><a class="nav-link" href="my_form.php">My Forms</a></li>
        <?php endif; ?>

      </ul>

      <ul class="navbar-nav ms-auto">
        <?php if ($role): ?>
          <li class="nav-item"><a class="nav-link" href="change_password.php">Change Password</a></li>
          <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-4">
