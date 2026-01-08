<?php
// header.php (FULL REPLACEMENT)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Security headers (compat-friendly) ----
// Note: If some pages already send headers before include header.php, these may not apply.
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    // CSP tuned for Bootstrap CDN + typical Chart.js CDN usage.
    // If you self-host all JS/CSS later, you can tighten this.
    header(
        "Content-Security-Policy: "
        . "default-src 'self'; "
        . "base-uri 'self'; "
        . "frame-ancestors 'none'; "
        . "object-src 'none'; "
        . "img-src 'self' data:; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        . "connect-src 'self';"
    );
}

$role = $_SESSION['role'] ?? null;

$current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
function nav_active(string $file, string $current): string {
    return $file === $current ? ' active' : '';
}

$titleSafe = htmlspecialchars($title ?? "WishSystem");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= $titleSafe ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- App CSS -->
  <link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="<?= ($role === 'admin') ? 'dashboard.php' : (($role === 'online_posting') ? 'my_form.php' : 'login.php') ?>">
      WishSystem
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMain">

      <!-- Left Nav -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if ($role === 'admin'): ?>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('dashboard.php', $current) ?>" href="dashboard.php">Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('form.php', $current) ?>" href="form.php">All Forms</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('calender.php', $current) ?>" href="calender.php">Calendar</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('staff_performance.php', $current) ?>" href="staff_performance.php">Staff Performance</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('user.php', $current) ?>" href="user.php">Users</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('export_csv.php', $current) ?>" href="export_csv.php">Export CSV</a>
          </li>

        <?php elseif ($role === 'online_posting'): ?>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('submit_form.php', $current) ?>" href="submit_form.php">Submit Form</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('my_form.php', $current) ?>" href="my_form.php">My Forms</a>
          </li>

          <!-- ✅ NEW: Staff Calendar -->
          <li class="nav-item">
            <a class="nav-link<?= nav_active('my_calendar.php', $current) ?>" href="my_calendar.php">My Calendar</a>
          </li>

        <?php else: ?>
          <!-- Not logged in -->
          <li class="nav-item">
            <a class="nav-link<?= nav_active('login.php', $current) ?>" href="login.php">Login</a>
          </li>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('register.php', $current) ?>" href="register.php">Register</a>
          </li>
        <?php endif; ?>
      </ul>

      <!-- Right Nav -->
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <?php if ($role): ?>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('change_password.php', $current) ?>" href="change_password.php">Change Password</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="logout.php">Logout</a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link<?= nav_active('login.php', $current) ?>" href="login.php">Login</a>
          </li>
        <?php endif; ?>
      </ul>

    </div>
  </div>
</nav>

<div class="container py-4">
