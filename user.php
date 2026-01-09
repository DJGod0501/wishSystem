<?php
require_once __DIR__ . "/auth_check.php";
require_once __DIR__ . "/csrf.php";

if (($_SESSION["role"] ?? "") !== "admin") die("Access denied");

$title = "User Management";
require_once __DIR__ . "/header.php";

$me = (int)($_SESSION["user_id"] ?? 0);
$msg = "";

// Actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validate();

    $action = $_POST["action"] ?? "";
    $id = (int)($_POST["id"] ?? 0);

    if ($id <= 0) {
        $msg = "<div class='alert alert-danger'>Invalid user id.</div>";
    } elseif ($id === $me) {
        $msg = "<div class='alert alert-warning'>You cannot modify your own account.</div>";
    } else {
        if ($action === "toggle") {
            $stmt = $conn->prepare("SELECT status FROM users WHERE user_id = :id LIMIT 1");
            $stmt->execute([":id" => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $msg = "<div class='alert alert-danger'>User not found.</div>";
            } else {
                $newStatus = ($row["status"] === "active") ? "inactive" : "active";
                $stmt = $conn->prepare("UPDATE users SET status = :s WHERE user_id = :id");
                $stmt->execute([":s" => $newStatus, ":id" => $id]);
                $msg = "<div class='alert alert-success'>User status updated.</div>";
            }
        }

        if ($action === "promote") {
            $stmt = $conn->prepare("UPDATE users SET role='admin' WHERE user_id = :id AND role='online_posting'");
            $stmt->execute([":id" => $id]);
            $msg = "<div class='alert alert-success'>User promoted to admin (if eligible).</div>";
        }
    }

    csrf_rotate();
}

// list users
$stmt = $conn->query("SELECT user_id, name, email, role, status, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h3 class="ws-page-title">User Management</h3>
<?= $msg ?>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Created</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <?php $uid = (int)$u["user_id"]; $isMe = ($uid === $me); ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($u["name"]) ?> <?= $isMe ? "<span class='badge bg-info'>You</span>" : "" ?></td>
              <td><?= htmlspecialchars($u["email"]) ?></td>
              <td><span class="badge <?= ($u["role"] === "admin") ? "bg-dark" : "bg-secondary" ?>"><?= htmlspecialchars($u["role"]) ?></span></td>
              <td>
                <span class="badge <?= ($u["status"] === "active") ? "bg-success" : "bg-warning text-dark" ?>">
                  <?= htmlspecialchars($u["status"]) ?>
                </span>
              </td>
              <td class="ws-muted small"><?= htmlspecialchars($u["created_at"]) ?></td>
              <td class="text-end">
                <?php if ($isMe): ?>
                  <span class="ws-muted small">No action</span>
                <?php else: ?>
                  <div class="d-flex justify-content-end gap-2 flex-wrap">

                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= $uid ?>">
                      <button class="btn btn-sm btn-outline-primary" onclick="return confirm('Toggle this user status?');">
                        Activate/Deactivate
                      </button>
                    </form>

                    <?php if ($u["role"] === "online_posting"): ?>
                      <form method="post" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="promote">
                        <input type="hidden" name="id" value="<?= $uid ?>">
                        <button class="btn btn-sm btn-outline-success" onclick="return confirm('Promote this user to admin?');">
                          Promote
                        </button>
                      </form>
                    <?php endif; ?>

                  </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/footer.php"; ?>
