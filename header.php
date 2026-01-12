<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>WishSystem</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="/wishSystem/index.php">WishSystem</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="/wishSystem/dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="/wishSystem/form.php">All Forms</a></li>
          <li class="nav-item"><a class="nav-link" href="/wishSystem/calendar.php">Calendar</a></li>
          <li class="nav-item"><a class="nav-link" href="/wishSystem/staff_manage.php">Staff</a></li>
        <?php elseif (!empty($_SESSION['role'])): ?>
          <li class="nav-item"><a class="nav-link" href="/wishSystem/submit_form.php">Submit</a></li>
          <li class="nav-item"><a class="nav-link" href="/wishSystem/my_form.php">My Forms</a></li>
          <li class="nav-item"><a class="nav-link" href="/wishSystem/my_calendar.php">My Calendar</a></li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto">
        <?php if (!empty($_SESSION['user_id'])): ?>
          <li class="nav-item"><a class="nav-link" href="/wishSystem/change_password.php">Change Password</a></li>
          <li class="nav-item"><a class="nav-link" href="/wishSystem/logout.php">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/wishSystem/login.php">Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
