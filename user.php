<?php
require_once __DIR__ . "/auth_check.php";
if ($_SESSION["role"] !== "admin") die("Access denied");

$title = "User Management";
require_once __DIR__ . "/header.php";

// Handle actions
if (isset($_GET["action"], $_GET["id"])) {
    $id = (int)$_GET["id"];
    if ($id !== $_SESSION["user_id"]) {
        if ($_GET["action"] === "toggle") {
            $conn->exec("
              UPDATE users
              SET status = IF(status='active','inactive','active')
              WHERE user_id = $id
            ");
        }
        if ($_GET["action"] === "promote") {
            $conn->exec("
              UPDATE users
              SET role='admin'
              WHERE user_id = $id AND role='online_posting'
            ");
        }
    }
    header("Location: user.php");
    exit;
}

$users = $conn->query("
  SELECT user_id, name, email, role, status, created_at
  FROM users
  ORDER BY created_at DESC
")->fetchAll();
?>

<h3 class="ws-page-title mb-3">User Management</h3>

<div class="card p-3">
<table class="table table-hover">
  <thead>
    <tr>
      <th>Name</th>
      <th>Email</th>
      <th>Role</th>
      <th>Status</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= htmlspecialchars($u["name"]) ?></td>
      <td><?= htmlspecialchars($u["email"]) ?></td>
      <td>
        <span class="badge bg-<?= $u["role"]==="admin"?"dark":"secondary" ?>">
          <?= $u["role"] ?>
        </span>
      </td>
      <td>
        <span class="badge bg-<?= $u["status"]==="active"?"success":"danger" ?>">
          <?= $u["status"] ?>
        </span>
      </td>
      <td>
        <?php if ($u["user_id"] !== $_SESSION["user_id"]): ?>
          <a class="btn btn-sm btn-outline-warning" href="?action=toggle&id=<?= $u["user_id"] ?>">Toggle</a>
          <?php if ($u["role"]==="online_posting"): ?>
            <a class="btn btn-sm btn-outline-primary" href="?action=promote&id=<?= $u["user_id"] ?>">Promote</a>
          <?php endif; ?>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
