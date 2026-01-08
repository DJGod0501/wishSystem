<?php
require_once __DIR__ . "/auth_check.php";
if (($_SESSION["role"] ?? "") !== "online_posting") die("Access denied");

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) die("Invalid ID");

// 只能编辑自己的 + 30 分钟内
$stmt = $conn->prepare("
  SELECT f.*, ls.*
  FROM interview_forms f
  LEFT JOIN language_skills ls ON ls.form_id = f.form_id
  WHERE f.form_id = :id AND f.user_id = :uid
    AND f.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
");
$stmt->execute([
  "id" => $id,
  "uid" => $_SESSION["user_id"]
]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) die("You can no longer edit this form.");

$title = "Edit Interview Form";
require_once __DIR__ . "/header.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
      UPDATE interview_forms SET
        name=:name, phone=:phone, position=:position,
        age=:age, gender=:gender, expected_salary=:salary,
        work_experience=:exp
      WHERE form_id=:id AND user_id=:uid
    ");
    $stmt->execute([
      "name"=>$_POST["name"],
      "phone"=>$_POST["phone"],
      "position"=>$_POST["position"],
      "age"=>$_POST["age"],
      "gender"=>$_POST["gender"],
      "salary"=>$_POST["expected_salary"],
      "exp"=>$_POST["work_experience"],
      "id"=>$id,
      "uid"=>$_SESSION["user_id"]
    ]);

    $stmt = $conn->prepare("
      UPDATE language_skills SET
        english_writing=:ew, english_speaking=:es,
        malay_writing=:mw, malay_speaking=:ms,
        chinese_writing=:cw, chinese_speaking=:cs
      WHERE form_id=:id
    ");
    $stmt->execute([
      "ew"=>$_POST["english_writing"],
      "es"=>$_POST["english_speaking"],
      "mw"=>$_POST["malay_writing"],
      "ms"=>$_POST["malay_speaking"],
      "cw"=>$_POST["chinese_writing"],
      "cs"=>$_POST["chinese_speaking"],
      "id"=>$id
    ]);

    $conn->commit();
    header("Location: form_detail.php?id=$id&updated=1");
    exit;
  } catch(Exception $e){
    $conn->rollBack();
    echo "<div class='alert alert-danger'>Update failed.</div>";
  }
}
?>

<h3 class="ws-page-title mb-3">Edit Interview Form</h3>

<form method="post">
  <input class="form-control mb-2" name="name" value="<?= htmlspecialchars($form["name"]) ?>" required>
  <input class="form-control mb-2" name="phone" value="<?= htmlspecialchars($form["phone"]) ?>" required>
  <input class="form-control mb-2" name="position" value="<?= htmlspecialchars($form["position"]) ?>" required>
  <button class="btn btn-primary">Save Changes</button>
  <a class="btn btn-outline-secondary" href="my_form.php">Cancel</a>
</form>

<?php require_once __DIR__ . "/footer.php"; ?>
