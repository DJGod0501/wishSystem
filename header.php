<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

$role = $_SESSION['role'] ?? null;
$current = basename($_SERVER['PHP_SELF']);

function nav_active(string $file, string $current): string {
    return $file === $current ? ' active' : '';
}

$titleSafe = htmlspecialchars($title ?? 'WishSystem', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $titleSafe ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/app.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
<div class="container">

<a class="navbar-brand fw-semibold"
   href="<?= $role === 'admin' ? 'dashboard.php' : ($role === 'online_posting' ? 'my_form.php' : 'login.php') ?>">
   WishSystem
</a>

<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
<span class="navbar-toggler-icon"></span>
</button>

<div class="collapse navbar-collapse" id="navMain">
<ul class="navbar-nav me-auto mb-2 mb-lg-0">

<?php if ($role === 'admin'): ?>
<li class="nav-item"><a class="nav-link<?= nav_active('dashboard.php',$current) ?>" href="dashboard.php">Dashboard</a></li>
<li class="nav-item"><a class="nav-link<?= nav_active('form.php',$current) ?>" href="form.php">All Forms</a></li>
<li class="nav-item"><a class="nav-link<?= nav_active('calender.php',$current) ?>" href="calender.php">Calendar</a></li>
<li class="nav-item"><a class="nav-link<?= nav_active('staff_performance.php',$current) ?>" href="staff_performance.php">Staff Performance</a></li>
<li class="nav-item"><a class="nav-link<?= nav_active('user.php',$current) ?>" href="user.php">Users</a></li>
<li class="nav-item"><a class="nav-link<?= nav_active('export_csv.php',$current) ?>" href="export_csv.php">Export CSV</a></li>

<?php elseif ($role === 'online_posting'): ?>
<li class="nav-item"><a class="nav-link<?= nav_active('submit_form.php',$current) ?>" href="submit_form.php">Submit Form</a></li>
<li class="nav-item"><a class="nav-link<?= nav_active('my_form.php',$current) ?>" href="my_form.php">My Forms</a></li>
<li class="nav-item"><a class="nav-link<?= nav_active('my_calender.php',$current) ?>" href="my_calender.php">My Calendar</a></li>

<?php else: ?>
<li class="nav-item"><a class="nav-link<?= nav_active('login.php',$current) ?>" href="login.php">Login</a></li>
<li class="nav-item"><a class="nav-link<?= nav_active('register.php',$current) ?>" href="register.php">Register</a></li>
<?php endif; ?>

</ul>

<ul class="navbar-nav ms-auto">
<?php if ($role): ?>
<li class="nav-item"><a class="nav-link" href="change_password.php">Change Password</a></li>
<li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
<?php endif; ?>
</ul>

</div>
</div>
</nav>

<!-- ✅ CONTENT START -->
<div class="container py-4">
