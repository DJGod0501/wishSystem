<?php
require_once __DIR__ . "/auth_check.php";
if (($_SESSION["role"] ?? "") !== "online_posting") {
    die("Access denied");
}

$title = "Submit Interview Form";
require_once __DIR__ . "/header.php";

/* =========================
   Copy Last Form (Prefill)
   ========================= */
$prefill = [];

if (isset($_GET["copy"]) && $_GET["copy"] === "last") {
    $stmt = $conn->prepare("
        SELECT f.*, ls.*
        FROM interview_forms f
        LEFT JOIN language_skills ls ON ls.form_id = f.form_id
        WHERE f.user_id = :uid
        ORDER BY f.created_at DESC
        LIMIT 1
    ");
    $stmt->execute(["uid" => $_SESSION["user_id"]]);
    $prefill = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

/* =========================
   Helper
   ========================= */
function scoreSelect(string $name, $value = ""): string {
    $html = "<select class='form-select' name='$name' required>";
    $html .= "<option value=''>Select</option>";
    for ($i = 1; $i <= 5; $i++) {
        $selected = ((string)$value === (string)$i) ? "selected" : "";
        $html .= "<option value='$i' $selected>$i</option>";
    }
    return $html . "</select>";
}

function validScore($v): bool {
    return in_array((string)$v, ["1","2","3","4","5"], true);
}

/* =========================
   Handle Submit
   ========================= */
$success = "";
$error = "";
$newFormId = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name     = trim($_POST["name"] ?? "");
    $phone    = trim($_POST["phone"] ?? "");
    $position = trim($_POST["position"] ?? "");

    if ($name === "" || $phone === "" || $position === "") {
        $error = "Name, Phone and Position are required.";
    }

    $scores = [
        "english_writing","english_speaking",
        "malay_writing","malay_speaking",
        "chinese_writing","chinese_speaking"
    ];
    foreach ($scores as $s) {
        if (!validScore($_POST[$s] ?? "")) {
            $error = "Invalid language score.";
        }
    }

    if (!$error) {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("
                INSERT INTO interview_forms
                (user_id, name, age, gender, phone, position, transportation, education, area,
                 expected_salary, work_experience, start_date, created_at)
                VALUES
                (:uid, :name, :age, :gender, :phone, :position, :transportation, :education, :area,
                 :salary, :exp, :start_date, NOW())
            ");
            $stmt->execute([
                "uid" => $_SESSION["user_id"],
                "name" => $name,
                "age" => $_POST["age"] ?? null,
                "gender" => $_POST["gender"] ?? null,
                "phone" => $phone,
                "position" => $position,
                "transportation" => $_POST["transportation"] ?? null,
                "education" => $_POST["education"] ?? null,
                "area" => $_POST["area"] ?? null,
                "salary" => $_POST["expected_salary"] ?? null,
                "exp" => $_POST["work_experience"] ?? null,
                "start_date" => $_POST["start_date"] ?: null
            ]);

            $newFormId = (int)$conn->lastInsertId();

            $stmt = $conn->prepare("
                INSERT INTO language_skills
                (form_id, english_writing, english_speaking,
                 malay_writing, malay_speaking,
                 chinese_writing, chinese_speaking)
                VALUES
                (:fid, :ew, :es, :mw, :ms, :cw, :cs)
            ");
            $stmt->execute([
                "fid" => $newFormId,
                "ew" => $_POST["english_writing"],
                "es" => $_POST["english_speaking"],
                "mw" => $_POST["malay_writing"],
                "ms" => $_POST["malay_speaking"],
                "cw" => $_POST["chinese_writing"],
                "cs" => $_POST["chinese_speaking"]
            ]);

            $conn->prepare("
                UPDATE users SET last_submission_date = CURDATE()
                WHERE user_id = :id
            ")->execute(["id" => $_SESSION["user_id"]]);

            $conn->commit();
            $success = "Interview form submitted successfully.";
        } catch (Exception $e) {
            $conn->rollBack();
            $error = "Failed to submit form.";
        }
    }
}
?>

<h3 class="ws-page-title mb-3">Submit Interview Form</h3>

<a class="btn btn-outline-primary mb-3" href="submit_form.php?copy=last">
  Copy Last Form
</a>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success">
    <?= htmlspecialchars($success) ?><br>
    <b>Form ID:</b> <?= $newFormId ?><br>
    <a class="btn btn-sm btn-primary mt-2" href="form_detail.php?id=<?= $newFormId ?>">View Detail</a>
    <a class="btn btn-sm btn-outline-secondary mt-2" href="my_form.php">My Forms</a>
  </div>
<?php endif; ?>

<form method="post" id="interviewForm">

<!-- Candidate Info -->
<div class="card p-4 mb-3">
  <h5>Candidate Info</h5>
  <div class="row g-3">
    <input class="form-control" name="name" placeholder="Candidate Name *"
           value="<?= htmlspecialchars($prefill["name"] ?? "") ?>" required>

    <input class="form-control" name="phone" placeholder="Phone *"
           value="<?= htmlspecialchars($prefill["phone"] ?? "") ?>" required>

    <input class="form-control" name="position" placeholder="Position *"
           value="<?= htmlspecialchars($prefill["position"] ?? "") ?>" required>

    <input class="form-control" name="age" placeholder="Age"
           value="<?= htmlspecialchars($prefill["age"] ?? "") ?>">

    <select class="form-select" name="gender">
      <option value="">Gender</option>
      <option <?= (($prefill["gender"] ?? "")==="Male")?"selected":"" ?>>Male</option>
      <option <?= (($prefill["gender"] ?? "")==="Female")?"selected":"" ?>>Female</option>
    </select>
  </div>
</div>

<!-- Work Info -->
<div class="card p-4 mb-3">
  <h5>Work Info</h5>
  <input class="form-control mb-2" name="transportation" placeholder="Transportation"
         value="<?= htmlspecialchars($prefill["transportation"] ?? "") ?>">
  <input class="form-control mb-2" name="education" placeholder="Education"
         value="<?= htmlspecialchars($prefill["education"] ?? "") ?>">
  <input class="form-control mb-2" name="area" placeholder="Area"
         value="<?= htmlspecialchars($prefill["area"] ?? "") ?>">
  <input class="form-control mb-2" name="expected_salary" placeholder="Expected Salary (RM)"
         value="<?= htmlspecialchars($prefill["expected_salary"] ?? "") ?>">
  <textarea class="form-control" name="work_experience" placeholder="Work Experience"><?= htmlspecialchars($prefill["work_experience"] ?? "") ?></textarea>
  <input class="form-control mt-2" type="date" name="start_date"
         value="<?= htmlspecialchars($prefill["start_date"] ?? "") ?>">
</div>

<!-- Language Skills -->
<div class="card p-4 mb-3">
  <h5>Language Skills (1–5)</h5>
  <?= scoreSelect("english_writing",  $prefill["english_writing"] ?? "") ?>
  <?= scoreSelect("english_speaking", $prefill["english_speaking"] ?? "") ?>
  <?= scoreSelect("malay_writing",    $prefill["malay_writing"] ?? "") ?>
  <?= scoreSelect("malay_speaking",   $prefill["malay_speaking"] ?? "") ?>
  <?= scoreSelect("chinese_writing",  $prefill["chinese_writing"] ?? "") ?>
  <?= scoreSelect("chinese_speaking", $prefill["chinese_speaking"] ?? "") ?>
</div>

<button class="btn btn-primary">Submit</button>
</form>

<script>
/* Draft auto-save */
const form = document.getElementById("interviewForm");
const KEY = "wish_draft_";
form.querySelectorAll("input, select, textarea").forEach(el => {
  if (el.name) {
    el.value = localStorage.getItem(KEY + el.name) || el.value;
    el.addEventListener("input", () => {
      localStorage.setItem(KEY + el.name, el.value);
    });
  }
});

/* Clean inputs */
form.phone?.addEventListener("input", e => e.target.value = e.target.value.replace(/[^\d+]/g, ""));
form.expected_salary?.addEventListener("input", e => e.target.value = e.target.value.replace(/[^\d]/g, ""));

/* Confirm submit */
form.addEventListener("submit", e => {
  if (!confirm("Confirm submit this interview form?")) {
    e.preventDefault();
  } else {
    localStorage.clear();
  }
});
</script>

<?php require_once __DIR__ . "/footer.php"; ?>
